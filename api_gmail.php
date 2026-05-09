<?php
// ======================================================================
// API GMAIL - SISTEMA DE RECUPERAÇÃO DE CUENTAS (ENTERPRISE)
// ======================================================================

// 1. Cabeçalhos de Segurança e Retorno JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Evita warnings que quebrem o JSON no frontend
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Caminhos do Banco de Dados
$dbDir = __DIR__ . '/../db';
$usersFile = $dbDir . '/usuarios.json';
$codesFile = $dbDir . '/recovery_codes.json'; // Nuevo cofre de códigos

// Garante que o diretório e arquivos existam
if (!is_dir($dbDir)) { mkdir($dbDir, 0755, true); }
if (!file_exists($usersFile)) { file_put_contents($usersFile, json_encode([])); chmod($usersFile, 0644); }
if (!file_exists($codesFile)) { file_put_contents($codesFile, json_encode([])); chmod($codesFile, 0644); }

// Apenas requisições POST são aceitas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error_code' => 'invalid_method']);
    exit;
}

$action = $_POST['action'] ?? '';

// Função auxiliar para ler JSON
function readJson($file) {
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

// Função auxiliar para salvar JSON
function saveJson($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// ======================================================================
// ROTEADOR DE AÇÕES
// ======================================================================
switch ($action) {

    // ------------------------------------------------------------------
    // ETAPA 1: SOLICITAR CÓDIGO DE RECUPERAÇÃO
    // ------------------------------------------------------------------
    case 'request_code':
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error_code' => 'invalid_email']); exit;
        }

        // 1. Verifica se o e-mail existe no banco de usuários
        $usuarios = readJson($usersFile);
        $userExists = false;
        
        foreach ($usuarios as $user) {
            if (isset($user['email']) && strtolower($user['email']) === $email) {
                $userExists = true;
                break;
            }
        }

        if (!$userExists) {
            // Retorna erro informando que o e-mail não foi encontrado
            echo json_encode(['success' => false, 'error_code' => 'email_not_found']); exit;
        }

        // 2. Gera um código numérico seguro de 6 dígitos
        $code = sprintf("%06d", mt_rand(100000, 999999));

        // 3. Salva no cofre de códigos (Sobrescreve código anterior do mesmo e-mail)
        $codes = readJson($codesFile);
        
        // Remove códigos antigos deste e-mail para evitar conflitos
        $codes = array_filter($codes, function($c) use ($email) {
            return strtolower($c['email']) !== $email;
        });

        // Adiciona o novo código (Validez: 15 minutos)
        $codes[] = [
            'email' => $email,
            'code' => $code,
            'created_at' => time(),
            'expires_at' => time() + (15 * 60) // 15 minutos de vida
        ];

        if (saveJson($codesFile, array_values($codes))) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error_code' => 'server_error']);
        }
        break;

    // ------------------------------------------------------------------
    // ETAPA 2: VALIDAR O CÓDIGO DIGITADO
    // ------------------------------------------------------------------
    case 'validate_code':
        $email = trim(strtolower($_POST['email'] ?? ''));
        $inputCode = trim($_POST['code'] ?? '');

        // Remove hifens ou espaços caso o usuário tenha digitado "123-456"
        $inputCode = preg_replace('/[^0-9]/', '', $inputCode);

        $codes = readJson($codesFile);
        $isValid = false;

        foreach ($codes as $c) {
            if (strtolower($c['email']) === $email && $c['code'] === $inputCode) {
                // Verifica se o código ainda está no prazo de validade
                if (time() <= $c['expires_at']) {
                    $isValid = true;
                }
                break;
            }
        }

        if ($isValid) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error_code' => 'invalid_code']);
        }
        break;

    // ------------------------------------------------------------------
    // ETAPA 3: REDEFINIR A SENHA
    // ------------------------------------------------------------------
    case 'reset_password':
        $email = trim(strtolower($_POST['email'] ?? ''));
        $inputCode = preg_replace('/[^0-9]/', '', trim($_POST['code'] ?? ''));
        $newPassword = $_POST['new_password'] ?? '';

        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'error_code' => 'short_pass']); exit;
        }

        // 1. Revalida o código por segurança máxima antes de alterar o DB
        $codes = readJson($codesFile);
        $isValid = false;
        foreach ($codes as $c) {
            if (strtolower($c['email']) === $email && $c['code'] === $inputCode && time() <= $c['expires_at']) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            echo json_encode(['success' => false, 'error_code' => 'invalid_code']); exit;
        }

        // 2. Altera a senha no banco de usuários
        $usuarios = readJson($usersFile);
        $updated = false;

        foreach ($usuarios as &$user) {
            if (isset($user['email']) && strtolower($user['email']) === $email) {
                $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $updated = true;
                break;
            }
        }
        unset($user); // Limpa referência

        if ($updated && saveJson($usersFile, $usuarios)) {
            // 3. Queima/Remove o código usado do cofre para não ser reutilizado
            $codes = array_filter($codes, function($c) use ($email) {
                return strtolower($c['email']) !== $email;
            });
            saveJson($codesFile, array_values($codes));

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error_code' => 'server_error']);
        }
        break;

    // ------------------------------------------------------------------
    // ETAPA 4: BUSCAR DADOS PARA O "GMAIL" (MÉTODO ANTI-ROUBO)
    // Usado exclusivamente pela página valida_gmail.php
    // ------------------------------------------------------------------
    case 'get_inbox_data':
        $email = trim(strtolower($_POST['email'] ?? ''));
        $username = trim(strtolower($_POST['username'] ?? ''));

        if (empty($email) || empty($username)) {
            echo json_encode(['success' => false, 'error_code' => 'missing_data']); exit;
        }

        // 1. Verificação Dupla (Verifica se E-mail e Nombre de Usuario pertencem à mesma conta)
        $usuarios = readJson($usersFile);
        $accountVerified = false;
        $realUsername = '';

        foreach ($usuarios as $user) {
            if (isset($user['email']) && strtolower($user['email']) === $email) {
                // Se achou o email, verifica se o username bate exatamente
                if (isset($user['username']) && strtolower($user['username']) === $username) {
                    $accountVerified = true;
                    $realUsername = $user['username']; // Pega com as maiúsculas originais
                }
                break;
            }
        }

        if (!$accountVerified) {
            // Bloqueia a tentativa de acesso à caixa de entrada
            echo json_encode(['success' => false, 'error_code' => 'security_block']); exit;
        }

        // 2. Se for o dono real, busca o código na caixa de entrada
        $codes = readJson($codesFile);
        $activeCode = null;

        foreach ($codes as $c) {
            if (strtolower($c['email']) === $email) {
                // Se estiver expirado, não retorna
                if (time() <= $c['expires_at']) {
                    $activeCode = $c;
                }
                break;
            }
        }

        if ($activeCode) {
            // Retorna os dados do "E-mail" formatados
            echo json_encode([
                'success' => true,
                'data' => [
                    'username' => $realUsername,
                    'email' => $email,
                    // Formata o código com hífen visualmente (ex: 407-855)
                    'code_formatted' => substr($activeCode['code'], 0, 3) . '-' . substr($activeCode['code'], 3, 3),
                    'requested_time' => date('H:i', $activeCode['created_at']),
                    'time_left_seconds' => $activeCode['expires_at'] - time()
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error_code' => 'no_code_found']);
        }
        break;

    // ------------------------------------------------------------------
    // AÇÃO DESCONHECIDA
    // ------------------------------------------------------------------
    default:
        echo json_encode(['success' => false, 'error_code' => 'invalid_action']);
        break;
}
?>