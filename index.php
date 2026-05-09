<?php
/**
 * By El NeNe Panel WEB2 - Sistema de Gestión e Roteamento Principal (ENTERPRISE BLINDADO)
 * Este arquivo controla a segurança, sessões, WAF interno e o fluxo de páginas.
 */

// ==============================================================================
// 1. HEADERS DE SEGURANÇA EXTREMA (NÍVEL BANCÁRIO)
// ==============================================================================
header('X-Frame-Options: DENY'); // Bloqueia Clickjacking (ninguém embute seu site)
header('X-XSS-Protection: 1; mode=block'); // Força o bloqueio de scripts maliciosos cross-site
header('X-Content-Type-Options: nosniff'); // Impede o navegador de adivinhar mimes
header('Referrer-Policy: strict-origin-when-cross-origin'); // Protege dados na URL ao sair do site
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload'); // Força HTTPS estrito
}

// ==============================================================================
// 2. CONFIGURAÇÕES DE SESSÃO BLINDADA
// ==============================================================================
ini_set('session.cookie_httponly', 1); // Impede roubo de sessão via JavaScript
ini_set('session.use_only_cookies', 1); // Impede fixação de sessão via URL (PHPSESSID na url)
ini_set('session.gc_maxlifetime', 7200); // Define o tempo de vida do lixo da sessão no servidor (2 horas)

// Força parâmetros rigorosos no cookie da sessão
session_set_cookie_params([
    'lifetime' => 0, // Expira ao fechar o navegador
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Só trafega em HTTPS
    'httponly' => true,
    'samesite' => 'Strict' // Bloqueia totalmente ataques CSRF cross-origin
]);

session_start();

// Define a constante de proteção para evitar acesso direto aos arquivos internos
define('DTUNNEL_APP', true);

// Carregamento dos arquivos base de dados e autenticação
if (file_exists(__DIR__ . '/db.php')) { require_once __DIR__ . '/db.php'; }
if (file_exists(__DIR__ . '/auth.php')) { require_once __DIR__ . '/auth.php'; }

// ==============================================================================
// 3. WAF INTERNO (RATE LIMITING ANTI-DDOS/BOTS)
// ==============================================================================
// Bloqueia quem tentar fazer mais de 15 requisições por segundo (DDoS de aplicação)
if (!isset($_SESSION['req_count'])) { $_SESSION['req_count'] = 0; }
if (!isset($_SESSION['req_time'])) { $_SESSION['req_time'] = time(); }

// Bypass rate limiter for AJAX POST requests (APK upload/build)
$_isAjaxPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && (
    isset($_GET['action']) || 
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart') !== false) ||
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false)
));

if (!$_isAjaxPost) {
if (time() - $_SESSION['req_time'] < 1) {
    $_SESSION['req_count']++;
    if ($_SESSION['req_count'] > 15) {
        http_response_code(429);
        die("<h1>429 - Too Many Requests</h1><p>Sistema de proteção ativado. Reduza o ritmo de requisições.</p>");
    }
} else {
    $_SESSION['req_count'] = 1;
    $_SESSION['req_time'] = time();
}
}

// ==============================================================================
// 4. FUNÇÃO DE CAPTURA DE IP REAL (ANTI-SPOOFING)
// ==============================================================================
function getRealUserIP() {
    // Prioridade 1: Cloudflare (extremamente difícil de forjar se configurado certo no servidor)
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
    } 
    // Prioridade 2: Proxy Reverso Padrão
    elseif (isset($_SERVER["HTTP_X_REAL_IP"])) {
        $ip = $_SERVER["HTTP_X_REAL_IP"];
    } 
    // Prioridade 3: Cadeia de proxies (Perigoso: pode ser forjado, pegamos apenas o primeiro e validamos)
    elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ipList = explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"]);
        $ip = trim($ipList[0]);
    } 
    // Prioridade 4: IP direto da conexão TCP
    else {
        $ip = $_SERVER["REMOTE_ADDR"] ?? '0.0.0.0';
    }

    // VALIDAÇÃO RIGOROSA: Garante que é um IP válido
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        return htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
    }
    
    return htmlspecialchars($_SERVER["REMOTE_ADDR"] ?? '0.0.0.0', ENT_QUOTES, 'UTF-8');
}

// ==============================================================================
// 5. SISTEMA DE PROTEÇÃO: INATIVIDADE (2 HORAS) E TROCA DE REDE
// ==============================================================================
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $currentIP = getRealUserIP();
    $currentUserAgent = md5($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); // Hasheado para segurança

    // A. TIMEOUT ABSOLUTO DE 2 HORAS (7200 segundos)
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > 7200) {
            session_unset();
            session_destroy();
            header('Location: /login?error=timeout');
            exit;
        }
    }
    $_SESSION['last_activity'] = time(); // Atualiza o tempo da última ação

    // B. PROTEÇÃO CONTRA ROUBO DE SESSÃO E MUDANÇA BRUSCA DE REDE
    if (!isset($_SESSION['security_ip'])) {
        $_SESSION['security_ip'] = $currentIP;
        $_SESSION['security_agent'] = $currentUserAgent;
    } else {
        // Se o Agente mudar completamente (Roubo de Cookie) expulsa na hora
        if ($_SESSION['security_agent'] !== $currentUserAgent) {
            session_unset();
            session_destroy();
            header('Location: /login?error=security_breach');
            exit;
        }
        
        // Observação sobre o IP: Celulares mudam de IP ao ir do 4G pro Wi-Fi. 
        // Se quiser bloquear isso também, descomente a linha abaixo. (Pode gerar falsos positivos em mobile).
        /*
        if ($_SESSION['security_ip'] !== $currentIP) {
            session_unset(); session_destroy(); header('Location: /login?error=network_changed'); exit;
        }
        */
    }
}

// ==============================================================================
// 6. SISTEMA DE ROTEAMENTO (MAPPING & API BYPASS)
// ==============================================================================
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');
if (empty($path)) { $path = '/'; }

// ---------------------------------------------------------
// LIBERAÇÃO DE ROTAS DE API (Bypass de Roteador Front-end)
// ---------------------------------------------------------
if ($path === '/api_gmail.php' || $path === '/api_gmail') {
    if (file_exists(__DIR__ . '/api_gmail.php')) {
        require_once __DIR__ . '/api_gmail.php';
        exit;
    }
}

if (strpos($path, '/api/') === 0) {
    if (file_exists(__DIR__ . '/api/index.php')) {
        require_once __DIR__ . '/api/index.php';
    }
    exit;
}

/**
 * Mapeamento de Rotas
 */
$routes = [
    '/'                    => ['page' => 'login',               'auth' => false],
    '/login'               => ['page' => 'login',               'auth' => false],
    '/register'            => ['page' => 'register',            'auth' => false], 
    '/registro'            => ['page' => 'register',            'auth' => false],
    '/recuperar-senha'     => ['page' => 'recuperar-senha',     'auth' => false],
    '/valida_gmail'        => ['page' => 'valida_gmail',        'auth' => false], 
    '/valida_gmail.php'    => ['page' => 'valida_gmail',        'auth' => false],
    
    // Rotas Protegidas do Panel
    '/home'                => ['page' => 'home',                'auth' => true],
    '/aplicativo'          => ['page' => 'aplicativo',          'auth' => true],
    '/gerar-apk'           => ['page' => 'gerar-apk',           'auth' => true],
    '/temas'               => ['page' => 'temas',               'auth' => true],
    '/usuarios'            => ['page' => 'usuarios',            'auth' => true],
    '/usuarios-associados' => ['page' => 'usuarios-associados', 'auth' => true],
    '/categorias'          => ['page' => 'categorias',          'auth' => true],
    '/cdn'                 => ['page' => 'cdn',                 'auth' => true],
    '/home-config'         => ['page' => 'home-config',         'auth' => true],
    '/textos'              => ['page' => 'textos',              'auth' => true],
    '/renovar'             => ['page' => 'renovar',             'auth' => true],
    '/transacoes'          => ['page' => 'transacoes',          'auth' => true],
    '/notificacoes'        => ['page' => 'notificacoes',        'auth' => true],
    '/dispositivos'        => ['page' => 'dispositivos',        'auth' => true],
    '/sessoes'             => ['page' => 'sessoes',             'auth' => true],
    '/perfil'              => ['page' => 'perfil',              'auth' => true],
    '/logout'              => ['page' => 'logout',              'auth' => false],
    '/404'                 => ['page' => '404',                 'auth' => false]
];

// Se a rota digitada não existe no array, redireciona para 404
if (!isset($routes[$path])) {
    $route = $routes['/404'];
    http_response_code(404);
} else {
    $route = $routes[$path];
}

// ==============================================================================
// 7. VALIDAÇÃO DE ACESSO E REDIRECIONAMENTOS
// ==============================================================================

// Caso a rota exija autenticação e o usuário NÃO esteja logado
if ($route['auth']) {
    if (function_exists('isLoggedIn') && !isLoggedIn()) {
        header('Location: /login');
        exit;
    }
}

// Caso o usuário TENTE acessar login/registro/recuperação já estando LOGADO
if (!$route['auth'] && !in_array($route['page'], ['logout', '404'])) {
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        header('Location: /home');
        exit;
    }
}

// Logout específico
if ($route['page'] === 'logout') {
    session_unset();
    session_destroy();
    
    // Deleta também o cookie do "Lembrar de Mim"
    if (isset($_COOKIE['dtunnel_remember_user'])) {
        setcookie('dtunnel_remember_user', '', time() - 3600, "/", "", false, true);
    }
    
    header('Location: /login');
    exit;
}

// ==============================================================================
// 8. RENDERIZAÇÃO FINAL DA PÁGINA
// ==============================================================================
$pageName = $route['page'];

$fileInRoot = __DIR__ . '/' . $pageName . '.php';
$fileInPages = __DIR__ . '/pages/' . $pageName . '.php';

$authFiles = ['login', 'registro', 'recuperar-senha', 'valida_gmail'];

if (in_array($pageName, $authFiles) && file_exists($fileInRoot)) {
    require_once $fileInRoot;
} elseif (file_exists($fileInPages)) {
    require_once $fileInPages;
} elseif (file_exists($fileInRoot)) {
    require_once $fileInRoot;
} else {
    // 404 Final - Arquivo físico não encontrado
    http_response_code(404);
    echo "<div style='font-family: sans-serif; text-align: center; padding: 100px; background: #121214; color: #fff; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;'>
            <svg viewBox='0 0 24 24' fill='none' stroke='#ef4444' stroke-width='2' style='width: 80px; margin-bottom: 20px;'><circle cx='12' cy='12' r='10'/><line x1='12' y1='8' x2='12' y2='12'/><line x1='12' y1='16' x2='12.01' y2='16'/></svg>
            <h1 style='font-size: 60px; margin-bottom: 10px; margin-top:0;'>404</h1>
            <p style='font-size: 18px; color: #a1a1aa; max-width: 400px; line-height: 1.5;'>Arquivo da página (<b>{$pageName}.php</b>) não encontrado no servidor.</p>
            <br>
            <a href='/home' style='background: #ffffff; color: #000; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: 0.2s;'>Volver ao Inicio</a>
          </div>";
}
