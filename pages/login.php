<?php
// ======================================================================
// SISTEMA DE SEGURANÇA REFORÇADA (HEADERS)
// ======================================================================
// Impede que o site seja aberto dentro de iframes (evita Clickjacking)
header('X-Frame-Options: DENY');
// Ativa o filtro XSS do navegador
header('X-XSS-Protection: 1; mode=block');
// Impede que o navegador tente adivinhar o tipo de arquivo (MIME sniffing)
header('X-Content-Type-Options: nosniff');
// Força o uso de cookies seguros
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

if (!defined('DTUNNEL_APP')) { 
    // Para testar isoladamente, comente a linha abaixo. Em produção, deixe ativado.
    // header('HTTP/1.0 403 Forbidden'); exit; 
}

// Inicia a sessão apenas se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ======================================================================
// GERAÇÃO DE TOKEN CSRF (ANTI-FALSIFICAÇÃO DE REQUISIÇÃO)
// ======================================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ======================================================================
// CONFIGURAÇÃO DA LOGO E ESTILO
// ======================================================================
// Coloque o link da sua logo aqui (ex: "https://i.imgur.com/sua_logo.png")
$customLogoUrl = ""; 

// ======================================================================
// 1. CRIAÇÃO AUTOMÁTICA DO DB (db/usuarios.json)
// ======================================================================
$dbDir = __DIR__ . '/../db';
$dbFile = $dbDir . '/usuarios.json';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}
if (!file_exists($dbFile)) {
    file_put_contents($dbFile, json_encode([]));
    chmod($dbFile, 0644);
}

// ======================================================================
// 2. SISTEMA ANTI-BRUTE FORCE (BLOQUEIO EXATO DE 3 TENTATIVAS)
// ======================================================================
if (!isset($_SESSION['login_attempts'])) { $_SESSION['login_attempts'] = 0; }
if (!isset($_SESSION['block_time'])) { $_SESSION['block_time'] = 0; }

$isBlocked = false;
$remainingTime = 0;

if ($_SESSION['login_attempts'] >= 3) {
    $timePassed = time() - $_SESSION['block_time'];
    if ($timePassed < 120) { // Bloqueado por 2 minutos (120 seg)
        $isBlocked = true;
        $remainingTime = 120 - $timePassed;
    } else {
        // O tempo passou, reseta os dados
        $_SESSION['login_attempts'] = 0;
        $_SESSION['block_time'] = 0;
    }
}

// ======================================================================
// 3. PROCESSAMENTO DE LOGIN (AJAX) COM PROTEÇÕES
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_login'])) {
    header('Content-Type: application/json');
    
    // HONEYPOT: Armadilha para Bots Maliciosos (Se preenchido, é bot)
    if (!empty($_POST['contact_email_hp'])) {
        echo json_encode(['success' => false, 'error_code' => 'bot_detected']);
        exit;
    }

    // CSRF CHECK: Verifica se a requisição veio realmente do nosso formulário
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error_code' => 'invalid_token']);
        exit;
    }
    
    // Se está bloqueado, não processa nada
    if ($isBlocked) {
        echo json_encode(['success' => false, 'error_code' => 'blocked', 'timer' => $remainingTime]);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';

    // Validação de E-mail
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error_code' => 'empty_fields']);
        exit;
    }
    if (strpos($username, '@') === false && $username !== 'admin') {
        echo json_encode(['success' => false, 'error_code' => 'invalid_email']);
        exit;
    }

    $loginSuccess = false;
    $role = 'user';
    $userData = [];

    // Acesso Administrador Mestre (Blindado)
    if ($username === 'elnene.admin@gmail.com' && $password === 'admin2004') {
        $loginSuccess = true;
        $role = 'admin';
        $userData = [
            'id' => 1,
            'uuid' => 'admin-master',
            'username' => 'Administrador',
            'email' => $username,
            'role' => 'admin'
        ];
    } else {
        // Verificación en DB (db/usuarios.json)
        $usuarios = json_decode(file_get_contents($dbFile), true) ?: [];
        foreach ($usuarios as $user) {
            if ($user['email'] === $username && password_verify($password, $user['password'])) {
                // Verificar si está bloqueado
                if (!empty($user['is_blocked']) && $user['is_blocked'] === true) {
                    echo json_encode(['success' => false, 'error_code' => 'blocked', 'timer' => 0]);
                    exit;
                }
                // Verificar si venció (excepto admins)
                $userRole = $user['role'] ?? 'user';
                if ($userRole !== 'admin' && !empty($user['expires_at'])) {
                    $expDate = strtotime($user['expires_at']);
                    if ($expDate !== false && $expDate < time()) {
                        echo json_encode(['success' => false, 'error_code' => 'expired']);
                        exit;
                    }
                }
                $loginSuccess = true;
                $role = $userRole;
                $userData = $user;
                break;
            }
        }
    }

    if ($loginSuccess) {
        // Autenticado con éxito
        $_SESSION['login_attempts'] = 0; 
        $_SESSION['block_time'] = 0;
        
        // Regenera o ID da sessão para prevenir Session Fixation
        session_regenerate_id(true);
        
        // Popula todas as chaves
        $_SESSION['email'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['user_id'] = $userData['id'] ?? $userData['uuid'] ?? uniqid();
        $_SESSION['id'] = $_SESSION['user_id'];
        $_SESSION['username'] = $userData['username'] ?? 'Usuario';
        
        // Lógica de "Lembrar de Mim" via Cookie Seguro (30 dias)
        if ($rememberMe) {
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('dtunnel_remember_user', $username, time() + (86400 * 30), "/", "", $isSecure, true);
        } else {
            setcookie('dtunnel_remember_user', '', time() - 3600, "/", "", false, true);
        }

        unset($_SESSION['security_ip']);
        unset($_SESSION['security_agent']);
        
        session_write_close(); 
        
        echo json_encode(['success' => true]);
        exit;
    } else {
        // Errou a senha/usuário
        $_SESSION['login_attempts']++;
        
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['block_time'] = time();
            session_write_close();
            echo json_encode(['success' => false, 'error_code' => 'blocked', 'timer' => 120]);
            exit;
        }
        
        $tentativasRestantes = 3 - $_SESSION['login_attempts'];
        session_write_close();
        echo json_encode(['success' => false, 'error_code' => 'invalid_credentials', 'attempts_left' => $tentativasRestantes]);
        exit;
    }
}

// Recupera o email salvo no cookie de "Lembrar de Mim" (se existir)
$savedEmail = $_COOKIE['dtunnel_remember_user'] ?? '';
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - By El NeNe Panel WEB2</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        (function() {
            const theme = localStorage.getItem('dtunnel-theme') || 'dark'; // Padrão escuro
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    <style>
        /* =========================================================
           RESET E VARIÁVEIS PREMIUM (Light e Dark)
           ========================================================= */
        :root {
            --bg-left: #eef2f6;
            --bg-right: #f1f5f9; 
            --surface: #ffffff;
            --surface-border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --primary: #0f172a;
            --primary-hover: #334155;
            --input-bg: #f8fafc;
            --input-border: #cbd5e1; 
            --icon-color: #64748b;
            --radius-lg: 24px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --font-main: 'Manrope', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
            --shadow-card: 0 10px 40px -10px rgba(0,0,0,0.1);
        }

        html.dark {
            --bg-left: #18181b; 
            --bg-right: #09090b; 
            --surface: #121214; 
            --surface-border: #27272a;
            --text-primary: #f8fafc;
            --text-secondary: #a1a1aa;
            --primary: #ffffff;
            --primary-hover: #e4e4e7;
            --input-bg: #18181b;
            --input-border: rgba(255, 255, 255, 0.15); 
            --icon-color: #a1a1aa;
            --shadow-card: 0 25px 50px -12px rgba(0,0,0,0.6);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: var(--font-main); color: var(--text-primary);
            background-color: var(--bg-right); overflow-x: hidden; min-height: 100vh;
        }

        /* =========================================================
           LAYOUT DE TELA DIVIDIDA (SPLIT SCREEN)
           ========================================================= */
        .split-layout { display: flex; min-height: 100vh; width: 100vw; }

        .split-left {
            flex: 1.2; background: linear-gradient(135deg, var(--bg-left) 0%, var(--bg-right) 100%);
            display: flex; flex-direction: column; justify-content: center;
            padding: 4rem 10%; position: relative; overflow: hidden;
            border-right: 1px solid var(--surface-border);
        }

        html.dark .split-left::before {
            content: ''; position: absolute; top: -20%; left: -20%; width: 60%; height: 60%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }

        .split-left h1 { font-family: var(--font-display); font-size: 3.5rem; font-weight: 700; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -1px; }
        .split-left p { font-size: 1.1rem; line-height: 1.6; color: var(--text-secondary); max-width: 480px; font-weight: 500; }

        .split-right {
            flex: 1; background-color: var(--bg-right);
            display: flex; align-items: center; justify-content: center;
            position: relative; padding: 2rem;
        }

        /* Controles Topo (Idioma e Tema) */
        .top-controls { position: absolute; top: 2rem; right: 2rem; display: flex; align-items: center; gap: 12px; z-index: 50; }
        .control-btn {
            width: 44px; height: 44px; border-radius: 50%; background: var(--surface); border: 1px solid var(--surface-border);
            color: var(--text-primary); display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .control-btn:hover { background: var(--input-bg); transform: scale(1.05); }
        .control-btn:active { transform: scale(0.95); }

        .lang-menu-wrapper { position: relative; }
        .lang-dropdown {
            position: absolute; top: 55px; right: 0; background: var(--surface); border: 1px solid var(--surface-border);
            border-radius: var(--radius-md); width: 150px; box-shadow: var(--shadow-card);
            opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .lang-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .lang-option { padding: 12px 16px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border-bottom: 1px solid var(--surface-border); color: var(--text-primary); }
        .lang-option:last-child { border-bottom: none; }
        .lang-option:hover { background: var(--input-bg); }

        /* =========================================================
           CARTÃO DE LOGIN E FORMULÁRIO
           ========================================================= */
        .auth-card {
            width: 100%; max-width: 420px;
            background: var(--surface); border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg); padding: 2.5rem;
            box-shadow: var(--shadow-card);
            position: relative; z-index: 10;
        }

        .card-logo-area { display: flex; align-items: center; gap: 14px; margin-bottom: 2.5rem; }
        .card-logo-img {
            width: 50px; height: 50px; border-radius: 14px; background: var(--input-bg); border: 1px solid var(--surface-border);
            display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--text-primary);
        }
        .card-logo-texts { display: flex; flex-direction: column; gap: 2px; }
        .card-logo-title { font-family: var(--font-display); font-weight: 800; font-size: 1.3rem; letter-spacing: -0.5px; line-height: 1; }
        .card-logo-subtitle { font-size: 0.7rem; color: var(--text-secondary); font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }

        .auth-header { margin-bottom: 2rem; }
        .auth-header h2 { font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
        .auth-header p { color: var(--text-secondary); font-size: 0.95rem; }

        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.6rem; color: var(--text-primary); }
        
        .form-control {
            width: 100%; padding: 14px 16px;
            background: var(--input-bg); 
            border: 1.5px solid var(--input-border); 
            border-radius: var(--radius-md); color: var(--text-primary);
            font-family: var(--font-main); font-size: 0.95rem; font-weight: 500;
            transition: all 0.3s ease; outline: none;
        }
        .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; font-weight: 400;}
        .form-control:focus { border-color: var(--text-primary); box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.1); background: var(--surface); }
        html.dark .form-control:focus { box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05); }
        
        .input-icon-btn {
            position: absolute; right: 12px; top: 38px;
            background: none; border: none; color: var(--icon-color);
            cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center;
        }

        /* =========================================================
           AÇÕES (LEMBRAR DE MIM + ESQUECEU A SENHA)
           ========================================================= */
        .auth-actions { 
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; 
            margin-bottom: 1.8rem; margin-top: -0.5rem; gap: 10px;
        }
        
        /* Design Checkbox "Top" */
        .custom-checkbox {
            display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;
        }
        .custom-checkbox input {
            position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0;
        }
        .checkmark {
            height: 20px; width: 20px; background-color: var(--input-bg); border: 1.5px solid var(--input-border);
            border-radius: 6px; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .custom-checkbox:hover input ~ .checkmark { border-color: var(--text-primary); }
        .custom-checkbox input:checked ~ .checkmark { background-color: var(--primary); border-color: var(--primary); }
        
        .checkmark svg { width: 12px; height: 12px; color: var(--bg-right); opacity: 0; transform: scale(0.5); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        html.dark .checkmark svg { color: #000; }
        .custom-checkbox input:checked ~ .checkmark svg { opacity: 1; transform: scale(1); }
        
        .checkbox-text { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); transition: color 0.2s; }
        .custom-checkbox input:checked ~ .checkbox-text { color: var(--text-primary); }

        .action-link { color: var(--text-secondary); text-decoration: none; font-size: 0.85rem; font-weight: 700; transition: color 0.2s; }
        .action-link:hover { color: var(--text-primary); }

        .btn-primary {
            width: 100%; padding: 15px; background: var(--primary); color: var(--bg-right);
            border: none; border-radius: var(--radius-md); font-family: var(--font-main); font-size: 1rem; font-weight: 800;
            cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        html.dark .btn-primary { color: #000; box-shadow: 0 4px 15px rgba(255,255,255,0.1); }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-primary:active { transform: translateY(1px); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

        .auth-footer { text-align: center; margin-top: 1.8rem; font-size: 0.9rem; color: var(--text-secondary); }
        .auth-footer a { color: var(--text-primary); font-weight: 800; text-decoration: none; }

        /* Bloqueio / Erro Temporizador */
        #block-card {
            background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: var(--radius-md); padding: 20px; margin-bottom: 20px; display: none; text-align: center;
        }
        #block-card h4 { color: #ef4444; margin-bottom: 8px; font-size: 1.1rem; display:flex; align-items:center; justify-content:center; gap:8px; }
        #block-card p { color: var(--text-primary); font-size: 0.9rem; margin-bottom: 15px; }
        #timer-display { font-size: 2rem; font-weight: 800; color: #ef4444; font-family: var(--font-display); background: var(--surface); border: 1px solid var(--surface-border); padding: 10px 20px; border-radius: var(--radius-sm); display: inline-block; }

        /* =========================================================
           PRELOADER
           ========================================================= */
        #preloader {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: var(--bg-right);
            z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        .pl-logo-box {
            width: 70px; height: 70px; background: var(--surface); border: 1px solid var(--surface-border); border-radius: 20px;
            display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 1.8rem; font-weight: 800;
            color: var(--text-primary); margin-bottom: 30px; box-shadow: var(--shadow-card); overflow: hidden;
        }
        .pl-title { font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; margin-bottom: 8px; }
        .pl-name { font-size: 1.8rem; font-weight: 800; color: var(--text-primary); margin-bottom: 15px; font-family: var(--font-display);}
        .pl-desc { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 40px; text-align: center; max-width: 300px; }
        .pl-bar { width: 250px; height: 4px; background: var(--surface-border); border-radius: 4px; overflow: hidden; position: relative; }
        .pl-progress { position: absolute; top: 0; left: -100%; height: 100%; width: 100%; background: var(--text-primary); animation: loadingBar 2s ease-in-out infinite; }
        @keyframes loadingBar { 0% { left: -100%; } 50% { left: 0; } 100% { left: 100%; } }

        /* =========================================================
           TOAST NOTIFICATIONS
           ========================================================= */
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-md);
            padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; width: 320px; color: var(--text-primary);
            transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;
        }
        .toast.show { transform: translateX(0); }
        .toast-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: white; }
        .toast.error .toast-icon { background: #ef4444; }
        .toast.success .toast-icon { background: #10b981; }
        .toast-msg { font-size: 0.9rem; font-weight: 600; line-height: 1.4; flex: 1; }
        .toast-progress { position: absolute; bottom: 0; left: 0; height: 3px; background: var(--text-primary); animation: toastTime 4s linear forwards; }
        .toast.error .toast-progress { background: #ef4444; }
        .toast.success .toast-progress { background: #10b981; }
        @keyframes toastTime { from { width: 100%; } to { width: 0%; } }

        /* =========================================================
           RESPONSIVIDADE (MOBILE PERFEITO E CENTRALIZADO)
           ========================================================= */
        @media (max-width: 900px) {
            .split-layout { flex-direction: column; }
            .split-left { display: none; }
            .split-right { 
                padding: 1.5rem; 
                align-items: center; 
                justify-content: center; 
                min-height: 100vh;
            }
            .auth-card { 
                padding: 2.2rem 1.5rem; 
                width: 100%; 
                max-width: 450px; 
            }
            .top-controls { position: fixed; top: 1rem; right: 1rem; }
            
            /* Ajuste extra no flexbox para telas ultra pequenas (ex: iPhone SE) */
            @media (max-width: 360px) {
                .auth-actions { flex-direction: column; align-items: flex-start; gap: 14px; }
            }
        }
    </style>
</head>
<body>

    <div id="preloader">
        <div class="pl-logo-box">
            <?php if (!empty($customLogoUrl)): ?>
                <img src="<?= htmlspecialchars($customLogoUrl) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 16px;">
            <?php else: ?>
                DT
            <?php endif; ?>
        </div>
        <div class="pl-title" data-i18n="loading_panel">CARGANDO PANEL</div>
        <div class="pl-name">By El NeNe Panel WEB2</div>
        <div class="pl-desc" data-i18n="preparing_env">Preparando el entorno y sincronizando la sesión del usuario.</div>
        <div class="pl-bar"><div class="pl-progress"></div></div>
    </div>

    <div id="toast-container"></div>

    <div class="split-layout">
        
        <div class="split-left">
            <div class="card-logo-area" style="margin-bottom: 2rem;">
                <div class="card-logo-img" style="background: transparent; border:none; width:auto; height:auto;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:28px;height:28px;color:var(--text-primary)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="card-logo-texts">
                    <div class="card-logo-title">By El NeNe Panel WEB2</div>
                    <div class="card-logo-subtitle">By El NeNe</div>
                </div>
            </div>
            
            <h1 data-i18n="marketing_title">By El NeNe Panel WEB2</h1>
            <p data-i18n="marketing_desc">Accedé a tu panel de control exclusivo para gestionar tus túneles, monitorear conexiones y configurar integraciones. Todo con una interfaz elegante, rápida y segura.</p>
        </div>

        <div class="split-right">
            
            <div class="top-controls">
                <div class="lang-menu-wrapper">
                    <button class="control-btn" onclick="toggleLangMenu()" title="Idioma">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                            <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="lang-dropdown">
                        <div class="lang-option" onclick="setAppLang('pt')">Português</div>
                        <div class="lang-option" onclick="setAppLang('en')">English</div>
                        <div class="lang-option" onclick="setAppLang('es')">Español</div>
                    </div>
                </div>

                <button class="control-btn" onclick="toggleTheme()" title="Alternar tema">
                    <svg id="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;width:20px;height:20px;">
                        <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <svg id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>

            <div class="auth-card">
                
                <div class="card-logo-area">
                    <div class="card-logo-img">
                        <?php if (!empty($customLogoUrl)): ?>
                            <img src="<?= htmlspecialchars($customLogoUrl) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="card-logo-texts">
                        <div class="card-logo-title">By El NeNe Panel WEB2</div>
                        <div class="card-logo-subtitle" data-i18n="badge_panel">By El NeNe</div>
                    </div>
                </div>

                <div id="block-card">
                    <h4>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span data-i18n="account_blocked">Acceso Bloqueado</span>
                    </h4>
                    <p data-i18n="too_many_attempts">Erraste la contraseña varias veces. Esperá:</p>
                    <div id="timer-display">02:00</div>
                </div>

                <div id="login-content">
                    <div class="auth-header">
                        <h2 data-i18n="welcome_title">Accedé a tu cuenta</h2>
                        <p data-i18n="welcome_subtitle">Iniciá sesión con tu e-mail para abrir el panel</p>
                    </div>

                    <form id="login-form" onsubmit="handleLogin(event)">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div style="position: absolute; left: -9999px;">
                            <label for="contact_email_hp">Deixe em branco</label>
                            <input type="text" id="contact_email_hp" name="contact_email_hp" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="username" data-i18n="email_label">E-mail</label>
                            <input type="text" id="username" name="username" class="form-control" placeholder="vos@ejemplo.com" required autocomplete="username" value="<?= htmlspecialchars($savedEmail) ?>">
                            <div class="input-icon-btn" style="pointer-events: none;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password" data-i18n="password_label">Contraseña</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Tu contraseña" required autocomplete="current-password" style="padding-right: 45px;">
                            <button type="button" class="input-icon-btn" onclick="togglePassword()" style="pointer-events: auto;">
                                <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>

                        <div class="auth-actions">
                            <label class="custom-checkbox">
                                <input type="checkbox" name="remember_me" id="remember_me" <?= !empty($savedEmail) ? 'checked' : '' ?>>
                                <span class="checkmark">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                                <span class="checkbox-text" data-i18n="remember_me">Recordarme</span>
                            </label>

                            <a href="/recuperar-senha" class="action-link" data-i18n="forgot_pass">¿Olvidaste tu contraseña?</a>
                        </div>

                        <button type="submit" class="btn-primary" id="login-btn">
                            <span data-i18n="login_btn">Iniciar sesión</span>
                        </button>
                    </form>

                    <div class="auth-footer">
                        <span data-i18n="no_account">¿No tenés cuenta?</span> <a href="/register" data-i18n="create_account">Crear cuenta</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // ======================================================================
        // SISTEMA DE IDIOMAS SINCRONIZADO
        // ======================================================================
        const dict = {
            'pt': {
                loading_panel: 'CARGANDO PANEL', preparing_env: 'Preparando el entorno y sincronizando la sesión.',
                marketing_title: 'By El NeNe Panel WEB2',
                marketing_desc: 'Accedé a tu panel de control exclusivo para gestionar tus túneles, monitorear conexiones y configurar integraciones. Todo con una interfaz elegante, rápida y segura.',
                badge_panel: 'By El NeNe', welcome_title: 'Accedé a tu cuenta', welcome_subtitle: 'Iniciá sesión con tu e-mail para abrir el panel',
                email_label: 'E-mail', password_label: 'Contraseña', forgot_pass: '¿Olvidaste tu contraseña?', remember_me: 'Recordarme',
                login_btn: 'Iniciar sesión', no_account: '¿No tenés cuenta?', create_account: 'Crear cuenta',
                account_blocked: 'Acceso Bloqueado', too_many_attempts: 'Erraste la contraseña varias veces. Esperá:',
                toast_empty_fields: 'Completá todos los campos.', toast_invalid_email: 'Insira um E-mail válido.',
                toast_invalid_credentials: 'Credenciales inválidas. Quedan {left} intentos.', toast_blocked: 'Cuenta bloqueada.', toast_expired: 'Tu acceso venció. Contactá al soporte.',
                toast_success: '¡Acceso autorizado! Cargando...', toast_server_error: 'Error de comunicación con el servidor.',
                toast_invalid_token: 'Aviso de seguridad: Token inválido. Recargá la página.', toast_bot_detected: 'Acceso bloqueado por sospecha de automatización.'
            },
            'en': {
                loading_panel: 'LOADING PANEL', preparing_env: 'Preparing environment and syncing session.',
                marketing_title: 'Total control,<br>premium interface.',
                marketing_desc: 'Access your exclusive control panel to manage tunnels, monitor connections, and configure integrations. All with an elegant, fast and secure interface.',
                badge_panel: 'By El NeNe', welcome_title: 'Access your account', welcome_subtitle: 'Sign in with your email to open the panel',
                email_label: 'E-mail', password_label: 'Password', forgot_pass: 'Forgot your password?', remember_me: 'Remember me',
                login_btn: 'Sign In', no_account: 'Don\'t have an account?', create_account: 'Create account',
                account_blocked: 'Access Blocked', too_many_attempts: 'Too many failed attempts. Please wait:',
                toast_empty_fields: 'Fill in all fields.', toast_invalid_email: 'Enter a valid E-mail.',
                toast_invalid_credentials: 'Invalid credentials. {left} attempts left.', toast_blocked: 'Account blocked. Wait.',
                toast_success: 'Access granted! Loading...', toast_server_error: 'Server communication error.',
                toast_invalid_token: 'Security warning: Invalid token. Reload the page.', toast_bot_detected: 'Access blocked due to suspected automation.'
            },
            'es': {
                loading_panel: 'CARGANDO PANEL', preparing_env: 'Preparando entorno y sincronizando sesión.',
                marketing_title: 'Control total,<br>interfaz premium.',
                marketing_desc: 'Acceda a su panel de control exclusivo para administrar túneles, monitorear conexiones y configurar integraciones. Todo con una interfaz elegante, rápida y segura.',
                badge_panel: 'By El NeNe', welcome_title: 'Accede a tu cuenta', welcome_subtitle: 'Inicia sesión con tu correo para abrir el panel',
                email_label: 'Colorreo', password_label: 'Contraseña', forgot_pass: '¿Olvidaste tu contraseña?', remember_me: 'Acuérdate de mí',
                login_btn: 'Ingresar', no_account: '¿No tienes cuenta?', create_account: 'Crear cuenta',
                account_blocked: 'Acceso Bloqueado', too_many_attempts: 'Demasiados intentos fallidos. Espere:',
                toast_empty_fields: 'Llene todos los campos.', toast_invalid_email: 'Ingrese un correo válido.',
                toast_invalid_credentials: 'Credenciales inválidas. Quedan {left} intentos.', toast_blocked: 'Cuenta bloqueada.', toast_expired: 'Tu acceso venció. Contactá al soporte.',
                toast_success: '¡Acceso autorizado! Cargando...', toast_server_error: 'Error de comunicación con el servidor.',
                toast_invalid_token: 'Advertencia de seguridad: Token no válido. Recarga la página.', toast_bot_detected: 'Acceso bloqueado por sospecha de automatización.'
            }
        };

        function getLangMsg(key, replaceObj = {}) {
            const lang = localStorage.getItem('app_language') || 'pt';
            let msg = dict[lang][key] || key;
            if (replaceObj.left !== undefined) { msg = msg.replace('{left}', replaceObj.left); }
            return msg;
        }

        function setAppLang(langCode) {
            localStorage.setItem('app_language', langCode);
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (dict[langCode] && dict[langCode][key]) {
                    if (el.tagName === 'INPUT') el.placeholder = dict[langCode][key];
                    else el.innerHTML = dict[langCode][key];
                }
            });
            document.getElementById('lang-dropdown').classList.remove('show');
        }

        function toggleLangMenu() {
            document.getElementById('lang-dropdown').classList.toggle('show');
        }

        // ======================================================================
        // TEMA CLARO/ESCURO
        // ======================================================================
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('dtunnel-theme', isDark ? 'dark' : 'light');
            updateThemeIcons(isDark);
        }

        function updateThemeIcons(isDark) {
            const sun = document.getElementById('icon-sun');
            const moon = document.getElementById('icon-moon');
            if (isDark) {
                sun.style.display = 'block'; moon.style.display = 'none';
            } else {
                sun.style.display = 'none'; moon.style.display = 'block';
            }
        }

        // ======================================================================
        // TOAST NOTIFICATIONS
        // ======================================================================
        function showToast(type, messageKey, replaceObj = {}) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const message = getLangMsg(messageKey, replaceObj);
            const iconSvg = type === 'error' 
                ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>`;

            toast.innerHTML = `<div class="toast-icon">${iconSvg}</div><div class="toast-msg">${message}</div><div class="toast-progress"></div>`;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
        }

        // ======================================================================
        // INICIALIZAÇÃO
        // ======================================================================
        let isTimerRunning = false;

        document.addEventListener('DOMContentLoaded', () => {
            setAppLang(localStorage.getItem('app_language') || 'pt');
            updateThemeIcons(document.documentElement.classList.contains('dark'));

            setTimeout(() => {
                const pl = document.getElementById('preloader');
                pl.style.opacity = '0'; pl.style.visibility = 'hidden';
            }, 2000);

            <?php if ($isBlocked): ?>
                startBlockTimer(<?= $remainingTime ?>);
            <?php endif; ?>
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.lang-menu-wrapper')) {
                    document.getElementById('lang-dropdown').classList.remove('show');
                }
            });
        });

        // ======================================================================
        // MOSTRAR/OCULTAR SENHA
        // ======================================================================
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
            } else {
                input.type = 'password';
                icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
            }
        }

        // ======================================================================
        // SUBMIT E TIMER
        // ======================================================================
        function handleLogin(e) {
            e.preventDefault();
            if (isTimerRunning) { showToast('error', 'toast_blocked'); return; }

            const btn = document.getElementById('login-btn');
            const formData = new FormData(document.getElementById('login-form'));
            formData.append('ajax_login', '1');

            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = `<svg style="width:24px;height:24px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            btn.disabled = true;

            fetch('/login', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'toast_success');
                    setTimeout(() => { window.location.href = '/home'; }, 1000);
                } else {
                    btn.innerHTML = originalBtnHtml; btn.disabled = false;
                    if (res.error_code === 'expired') {
                        showToast('error', 'toast_expired');
                    } else if (res.error_code === 'blocked') {
                        showToast('error', 'toast_blocked'); startBlockTimer(res.timer);
                    } else {
                        showToast('error', 'toast_' + res.error_code, { left: res.attempts_left });
                    }
                }
            })
            .catch(() => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                showToast('error', 'toast_server_error');
            });
        }

        function startBlockTimer(secondsToWait) {
            isTimerRunning = true;
            document.getElementById('login-content').style.display = 'none';
            const card = document.getElementById('block-card');
            const display = document.getElementById('timer-display');
            card.style.display = 'block';

            const endTime = Date.now() + (secondsToWait * 1000);
            const interval = setInterval(() => {
                let remaining = Math.ceil((endTime - Date.now()) / 1000);
                if (remaining <= 0) {
                    clearInterval(interval); isTimerRunning = false;
                    card.style.display = 'none'; document.getElementById('login-content').style.display = 'block';
                } else {
                    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
                    const s = (remaining % 60).toString().padStart(2, '0');
                    display.innerText = `${m}:${s}`;
                }
            }, 1000);
        }
    </script>
</body>
</html>
