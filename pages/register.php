<?php
// ======================================================================
// SISTEMA DE SEGURANÇA REFORÇADA (HEADERS)
// ======================================================================
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

if (!defined('DTUNNEL_APP')) { 
    // Para testar isoladamente, comente a linha abaixo. Em produção, deixe ativado.
    // header('HTTP/1.0 403 Forbidden'); exit; 
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ======================================================================
// CONFIGURAÇÃO DA LOGO
// ======================================================================
$customLogoUrl = ""; // Link da logo (ex: "https://i.imgur.com/sua_logo.png")

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
// 2. PROCESSAMENTO DO REGISTRO (AJAX POST)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_register'])) {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validações
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error_code' => 'empty_fields']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error_code' => 'invalid_email']); exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'error_code' => 'short_password']); exit;
    }
    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'error_code' => 'password_mismatch']); exit;
    }

    // Leitura do BD para verificar duplicidade
    $usuarios = json_decode(file_get_contents($dbFile), true) ?: [];
    foreach ($usuarios as $user) {
        if (strtolower($user['email']) === strtolower($email)) {
            echo json_encode(['success' => false, 'error_code' => 'email_taken']); exit;
        }
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            echo json_encode(['success' => false, 'error_code' => 'username_taken']); exit;
        }
    }

    // Gerador de UUID (v4)
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    // Datas (Criação e Expiração - 4 dias de bônus)
    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+4 days'));

    // Cria o novo usuário
    $newUser = [
        'uuid' => $uuid,
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'user',
        'created_at' => $createdAt,
        'expires_at' => $expiresAt,
        'status' => 'active',
        'is_blocked' => false
    ];

    $usuarios[] = $newUser;
    
    // Salva no JSON
    if (file_put_contents($dbFile, json_encode($usuarios, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'data' => [
                'username' => $username,
                'email' => $email,
                'created_at' => date('d/m/Y H:i', strtotime($createdAt)),
                'expires_at' => date('d/m/Y H:i', strtotime($expiresAt)),
                'uuid' => $uuid
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error_code' => 'server_error']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Registro - By El NeNe Panel WEB2</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        (function() {
            const theme = localStorage.getItem('dtunnel-theme') || 'dark';
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    <style>
        /* =========================================================
           RESET E VARIÁVEIS PREMIUM (Light e Dark)
           ========================================================= */
        :root {
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
           LAYOUT CENTRALIZADO PERFEITO (PC & MOBILE)
           ========================================================= */
        .page-wrapper {
            display: flex; min-height: 100vh; width: 100vw;
            align-items: center; justify-content: center;
            padding: 2rem 1.5rem; position: relative;
        }

        /* Controles Topo */
        .top-controls { position: fixed; top: 1.5rem; right: 1.5rem; display: flex; align-items: center; gap: 12px; z-index: 50; }
        .control-btn {
            width: 44px; height: 44px; border-radius: 50%; background: var(--surface); border: 1px solid var(--surface-border);
            color: var(--text-primary); display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .control-btn:hover { background: var(--input-bg); transform: scale(1.05); }
        .control-btn:active { transform: scale(0.95); }

        /* Menu de Idioma */
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
           CARTÃO FLUTUANTE DE REGISTRO
           ========================================================= */
        .auth-card {
            width: 100%; max-width: 450px;
            background: var(--surface); border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg); padding: 2.5rem;
            box-shadow: var(--shadow-card);
            position: relative; z-index: 10;
        }

        .card-logo-area { display: flex; align-items: center; gap: 14px; margin-bottom: 2rem; }
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

        .form-group { margin-bottom: 1.2rem; position: relative; }
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

        .btn-primary {
            width: 100%; padding: 15px; background: var(--primary); color: var(--bg-right);
            border: none; border-radius: var(--radius-md); font-family: var(--font-main); font-size: 1rem; font-weight: 800;
            cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 1rem;
        }
        html.dark .btn-primary { color: #000; box-shadow: 0 4px 15px rgba(255,255,255,0.1); }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-primary:active { transform: translateY(1px); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

        .auth-footer { text-align: center; margin-top: 1.8rem; font-size: 0.9rem; color: var(--text-secondary); }
        .auth-footer a { color: var(--text-primary); font-weight: 800; text-decoration: none; transition: color 0.2s; }

        /* =========================================================
           MODAL DE SUCESSO PREMIUM
           ========================================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
            z-index: 999999; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s;
        }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .success-modal {
            background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg);
            width: 90%; max-width: 420px; padding: 2rem; transform: scale(0.9) translateY(20px); 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; align-items: center;
            box-shadow: var(--shadow-card);
        }
        .modal-overlay.show .success-modal { transform: scale(1) translateY(0); }
        
        .success-icon-wrap {
            width: 70px; height: 70px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); 
            color: #10b981; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .success-icon-wrap svg { width: 36px; height: 36px; }
        .sm-title { font-size: 1.5rem; font-family: var(--font-display); font-weight: 800; color: var(--text-primary); margin: 0 0 6px 0; text-align: center; }
        .sm-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin: 0 0 24px 0; text-align: center; font-weight: 500; }
        
        .data-card {
            width: 100%; background: var(--input-bg); border: 1px solid var(--input-border);
            border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px;
        }
        .data-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed var(--surface-border); }
        .data-row:first-child { padding-top: 0; }
        .data-row:last-child { padding-bottom: 0; border-bottom: none; }
        .data-label { font-size: 0.8rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .data-value { font-size: 0.9rem; color: var(--text-primary); font-weight: 700; text-align: right; max-width: 60%; word-break: break-all; }
        .data-value.highlight { color: #10b981; }
        .data-value.mono { font-family: var(--font-display); font-size: 0.85rem; letter-spacing: -0.5px;}

        .btn-go-login { 
            background: #10b981; color: white; width: 100%; padding: 15px; border-radius: var(--radius-md); 
            font-family: var(--font-main); font-weight: 800; border: none; cursor: pointer; transition: all 0.2s; 
            display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 1rem;
        }
        .btn-go-login:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-go-login:active { transform: translateY(1px); }

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

        /* Ajuste Mobile */
        @media (max-width: 600px) {
            .auth-card { padding: 2rem 1.5rem; }
            .top-controls { top: 1rem; right: 1rem; }
        }
    </style>
</head>
<body>

    <!-- TOAST CUENTAINER -->
    <div id="toast-container"></div>

    <!-- MODAL DE SUCESSO PREMIUM -->
    <div class="modal-overlay" id="successModal">
        <div class="success-modal">
            <div class="success-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="sm-title" data-i18n="account_created">¡Cuenta Creada!</h3>
            <p class="sm-subtitle" data-i18n="save_data">Guardá tus datos con seguridad.</p>
            
            <div class="data-card">
                <div class="data-row">
                    <span class="data-label" data-i18n="account_label">Usuario</span>
                    <span class="data-value" id="res-username">---</span>
                </div>
                <div class="data-row">
                    <span class="data-label">E-mail</span>
                    <span class="data-value" id="res-email">---</span>
                </div>
                <div class="data-row">
                    <span class="data-label" data-i18n="password_label">Contraseña</span>
                    <span class="data-value highlight" id="res-password">---</span>
                </div>
                <div class="data-row">
                    <span class="data-label" data-i18n="created_at">Criado em</span>
                    <span class="data-value" id="res-created">---</span>
                </div>
                <div class="data-row">
                    <span class="data-label" data-i18n="expires_in">Expira em</span>
                    <span class="data-value" id="res-expires" style="color: #ef4444;">---</span>
                </div>
                <div class="data-row">
                    <span class="data-label">UUID</span>
                    <span class="data-value mono" id="res-uuid">---</span>
                </div>
            </div>

            <button class="btn-go-login" onclick="window.location.href='/login'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span data-i18n="go_login">Ir para o Login</span>
            </button>
        </div>
    </div>

    <!-- PÁGINA CENTRAL (SEM ESQUERDA, SEM PRELOADER) -->
    <div class="page-wrapper">
        
        <!-- Controles do Topo (Tema e Idioma) -->
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

        <!-- Card de Registro (Flutuante) -->
        <div class="auth-card">
            
            <div class="card-logo-area">
                <div class="card-logo-img">
                    <?php if (!empty($customLogoUrl)): ?>
                        <img src="<?= htmlspecialchars($customLogoUrl) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <!-- Ícone de registro -->
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <?php endif; ?>
                </div>
                <div class="card-logo-texts">
                    <div class="card-logo-title">By El NeNe Panel WEB2</div>
                    <div class="card-logo-subtitle">By El NeNe</div>
                </div>
            </div>

            <div class="auth-header">
                <h2 data-i18n="create_account_title">Crear cuenta</h2>
                <p data-i18n="register_subtitle">Registrate y obtené 4 días de acceso gratuito</p>
            </div>

            <form id="register-form" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label class="form-label" for="username" data-i18n="username_label">Nombre de usuario</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Elegí un usuario" required autocomplete="username">
                    <div class="input-icon-btn" style="pointer-events: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="vos@ejemplo.com" required autocomplete="email">
                    <div class="input-icon-btn" style="pointer-events: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password" data-i18n="password_label">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required autocomplete="new-password" style="padding-right: 45px;">
                    <button type="button" class="input-icon-btn" onclick="togglePassword('password', this)" style="pointer-events: auto;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password" data-i18n="confirm_password">Confirmar contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repetí la contraseña" required autocomplete="new-password" style="padding-right: 45px;">
                    <button type="button" class="input-icon-btn" onclick="togglePassword('confirm_password', this)" style="pointer-events: auto;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>

                <button type="submit" class="btn-primary" id="register-btn">
                    <span data-i18n="register_btn">Crear cuenta</span>
                </button>
            </form>

            <div class="auth-footer">
                <span data-i18n="already_have">Já tem uma conta?</span> <a href="/login" data-i18n="do_login">Fazer login</a>
            </div>
        </div>
    </div>

    <script>
        // ======================================================================
        // SISTEMA DE IDIOMAS SINCRONIZADO (CHAVE: app_language)
        // ======================================================================
        const dict = {
            'pt': {
                badge_panel: 'By El NeNe', create_account_title: 'Crear cuenta', register_subtitle: 'Registrate y obtené 4 días de acceso gratuito',
                username_label: 'Usuario', password_label: 'Contraseña', confirm_password: 'Confirmar contraseña',
                register_btn: 'Crear cuenta', already_have: 'Já tem uma conta?', do_login: 'Fazer login',
                account_created: '¡Cuenta Creada!', save_data: 'Guardá tus datos con seguridad.',
                account_label: 'Usuario', created_at: 'Criado em', expires_in: 'Expira em', go_login: 'Ir para o Login',
                toast_empty_fields: 'Completá todos los campos.', toast_invalid_email: 'E-mail inválido.',
                toast_short_password: 'Mínimo de 6 caracteres en la contraseña.', toast_password_mismatch: 'Las contraseñas no coinciden.',
                toast_email_taken: 'Este e-mail já está em uso.', toast_username_taken: 'Este usuário já está em uso.',
                toast_server_error: 'Erro interno ao salvar dados.', toast_success: 'Registro concluído con éxito!'
            },
            'en': {
                badge_panel: 'By El NeNe', create_account_title: 'Create account', register_subtitle: 'Register and get 4 days of free access',
                username_label: 'Username', password_label: 'Password', confirm_password: 'Confirm password',
                register_btn: 'Create account', already_have: 'Already have an account?', do_login: 'Sign in',
                account_created: 'Account Created!', save_data: 'Keep your data safe.',
                account_label: 'Username', created_at: 'Created at', expires_in: 'Expires in', go_login: 'Go to Login',
                toast_empty_fields: 'Fill in all fields.', toast_invalid_email: 'Invalid email format.',
                toast_short_password: 'Password must be at least 6 characters.', toast_password_mismatch: 'Passwords do not match.',
                toast_email_taken: 'Email already in use.', toast_username_taken: 'Username already in use.',
                toast_server_error: 'Internal server error.', toast_success: 'Registration completed successfully!'
            },
            'es': {
                badge_panel: 'By El NeNe', create_account_title: 'Crear cuenta', register_subtitle: 'Regístrate y obtén 4 días de acceso gratis',
                username_label: 'Usuario', password_label: 'Contraseña', confirm_password: 'Confirmar contraseña',
                register_btn: 'Crear cuenta', already_have: '¿Ya tienes una cuenta?', do_login: 'Iniciar sesión',
                account_created: '¡Cuenta Creada!', save_data: 'Guarda tus datos de forma segura.',
                account_label: 'Usuario', created_at: 'Creado el', expires_in: 'Expira el', go_login: 'Ir al Login',
                toast_empty_fields: 'Llene todos los campos.', toast_invalid_email: 'Correo inválido.',
                toast_short_password: 'La contraseña debe tener al menos 6 caracteres.', toast_password_mismatch: 'Las contraseñas no coinciden.',
                toast_email_taken: 'Este correo ya está en uso.', toast_username_taken: 'Este usuario ya está en uso.',
                toast_server_error: 'Error interno del servidor.', toast_success: '¡Registro completado con éxito!'
            }
        };

        function getLangMsg(key) {
            const lang = localStorage.getItem('app_language') || 'pt';
            return dict[lang][key] || key;
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
        function showToast(type, messageKey) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const message = getLangMsg(messageKey);
            const iconSvg = type === 'error' 
                ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>`;

            toast.innerHTML = `<div class="toast-icon">${iconSvg}</div><div class="toast-msg">${message}</div><div class="toast-progress"></div>`;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            setAppLang(localStorage.getItem('app_language') || 'pt');
            updateThemeIcons(document.documentElement.classList.contains('dark'));
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.lang-menu-wrapper')) {
                    document.getElementById('lang-dropdown').classList.remove('show');
                }
            });
        });

        // ======================================================================
        // MOSTRAR/OCULTAR SENHA GENÉRICO
        // ======================================================================
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
            } else {
                input.type = 'password';
                btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            }
        }

        // ======================================================================
        // SUBMIT AJAX
        // ======================================================================
        function handleRegister(e) {
            e.preventDefault();
            
            const btn = document.getElementById('register-btn');
            const formData = new FormData(document.getElementById('register-form'));
            formData.append('ajax_register', '1');

            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = `<svg style="width:24px;height:24px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            btn.disabled = true;

            fetch('/register', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'toast_success');
                    
                    document.getElementById('res-username').innerText = res.data.username;
                    document.getElementById('res-email').innerText = res.data.email;
                    document.getElementById('res-password').innerText = res.data.password;
                    document.getElementById('res-created').innerText = res.data.created_at;
                    document.getElementById('res-expires').innerText = res.data.expires_at;
                    document.getElementById('res-uuid').innerText = res.data.uuid;

                    document.getElementById('successModal').classList.add('show');
                    
                    btn.innerHTML = originalBtnHtml;
                    btn.disabled = false;
                    document.getElementById('register-form').reset();
                } else {
                    btn.innerHTML = originalBtnHtml;
                    btn.disabled = false;
                    showToast('error', 'toast_' + res.error_code);
                }
            })
            .catch(() => {
                btn.innerHTML = originalBtnHtml;
                btn.disabled = false;
                showToast('error', 'toast_server_error');
            });
        }
    </script>
</body>
</html>