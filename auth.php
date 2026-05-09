<?php
if (!defined('DTUNNEL_APP')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login');
        exit;
    }
    // Verificar se o usuário está bloqueado
    $user = getCurrentUser();
    if ($user && $user['is_blocked']) {
        session_destroy();
        header('Location: /login?blocked=1');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function loginUser($username, $password) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        return ['success' => false, 'message' => 'Usuario ou senha inválidos'];
    }
    
    if ($user['is_blocked']) {
        return ['success' => false, 'message' => 'Tu cuenta fue bloqueada. Contactá al soporte: WA 3455236886 / TG @El_NeNe_Sando', 'blocked' => true];
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    return ['success' => true];
}

function registerUser($username, $email, $password) {
    $pdo = db();
    
    // Verificar se username já existe
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Nombre de usuario já está em uso'];
    }
    
    // Verificar se email já existe
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'E-mail já está em uso'];
    }
    
    $hashedPassword = hashPassword($password);
    $token = generateToken();
    // Dar 4 dias de acesso ao usuário
    $expiresAt = date('Y-m-d H:i:s', strtotime('+4 days'));
    
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, token, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, $hashedPassword, $token, $expiresAt]);
    $userId = $pdo->lastInsertId();
    
    // Crear textos padrão para o usuário
    createDefaultAppTexts($userId);
    
    return ['success' => true, 'user_id' => $userId];
}

function createDefaultAppTexts($userId) {
    $pdo = db();
    $defaults = getDefaultAppTexts();
    $stmt = $pdo->prepare('INSERT INTO app_texts (label, text, user_id) VALUES (?, ?, ?)');
    foreach ($defaults as $item) {
        $stmt->execute([$item['label'], $item['text'], $userId]);
    }
}

function getDefaultAppTexts() {
    return [
        ['label' => 'LBL_BTN_START', 'text' => 'INICIAR'],
        ['label' => 'LBL_BTN_STOPPING', 'text' => 'PARANDO'],
        ['label' => 'LBL_BTN_STOP', 'text' => 'PARAR'],
        ['label' => 'LBL_BTN_RECONNECT', 'text' => 'RECONECTAR'],
        ['label' => 'LBL_DISCONNECTED', 'text' => '<b>Desconectado</b>'],
        ['label' => 'LBL_RECORD', 'text' => 'REGISTRO'],
        ['label' => 'LBL_CHOOSE_CONFIG', 'text' => 'ESCOLHA UMA CONFIGURAÇÃO'],
        ['label' => 'LBL_UUID', 'text' => 'UUID V2Ray'],
        ['label' => 'LBL_USERNAME', 'text' => 'Nombre de usuario'],
        ['label' => 'LBL_PASSWORD', 'text' => 'Contraseña'],
        ['label' => 'LBL_UUID_INVALID', 'text' => 'UUID inválido'],
        ['label' => 'LBL_USERNAME_INVALID', 'text' => 'Nombre de usuario inválido'],
        ['label' => 'LBL_PASSWORD_INVALID', 'text' => 'Contraseña inválida'],
        ['label' => 'LBL_USERNAME_PASSWORD_INVALID', 'text' => 'Por favor, preencha o usuário e senha'],
        ['label' => 'LBL_CONFIG_TITLE', 'text' => 'Configuración'],
        ['label' => 'LBL_INITIALIZING_APP', 'text' => 'Inicializando aplicação'],
        ['label' => 'LBL_CONFIG_LOADED', 'text' => 'Configuración carregada'],
        ['label' => 'LBL_SEARCHING_FOR_UPDATES', 'text' => 'Procurando atualizações'],
        ['label' => 'LBL_CONFIG_UPDATED', 'text' => 'Configuraciones atualizadas con éxito'],
        ['label' => 'LBL_APP_CONFIG_UPDATED', 'text' => 'Configuraciones do app atualizadas con éxito'],
        ['label' => 'LBL_APP_TEXT_UPDATED', 'text' => 'Textos do app atualizados con éxito'],
        ['label' => 'LBL_CONFIG_NOT_SUPPORTED', 'text' => 'Parece que essa configuração não é suportada neste aplicativo'],
        ['label' => 'LBL_ERROR_ESTABLISHING_CONNECTION_SSH', 'text' => '<b>Error al estabelecer conexão SSH</b>'],
        ['label' => 'LBL_RECONNECTION_PROCESS', 'text' => 'Processo de reconexão'],
        ['label' => 'LBL_RECONNECTING_IN', 'text' => 'Reconectando em: %ss'],
        ['label' => 'LBL_CONNECTED', 'text' => '<b>Conectado</b>'],
        ['label' => 'LBL_CONNECTING', 'text' => '<b>Conectando...</b>'],
        ['label' => 'LBL_CONNECTION_TIME', 'text' => 'Tempo de conexão'],
        ['label' => 'LBL_DOWNLOAD', 'text' => 'Download'],
        ['label' => 'LBL_UPLOAD', 'text' => 'Upload'],
        ['label' => 'LBL_PING', 'text' => 'Ping'],
        ['label' => 'LBL_NOTIFICATION_TITLE', 'text' => 'By Elnene Panel WEB2'],
        ['label' => 'LBL_NOTIFICATION_CONNECTED', 'text' => 'Conectado con éxito'],
        ['label' => 'LBL_NOTIFICATION_DISCONNECTED', 'text' => 'Desconectado'],
    ];
}
