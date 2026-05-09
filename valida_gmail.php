<?php
// ======================================================================
// SISTEMA DE SEGURANÇA REFORÇADA (HEADERS)
// ======================================================================
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

if (!defined('DTUNNEL_APP')) { 
    // Para testar isoladamente, comente a linha abaixo.
    // header('HTTP/1.0 403 Forbidden'); exit; 
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Caixa de Entrada - DTunnel Mail</title>
    
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
            --surface-inner: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --primary: #ea4335; /* Vermelho estilo Gmail */
            --primary-hover: #d33426;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --icon-color: #64748b;
            --success-bg: rgba(16, 185, 129, 0.1);
            --success-text: #059669;
            --radius-lg: 24px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --font-main: 'Manrope', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
            --shadow-card: 0 10px 40px -10px rgba(0,0,0,0.1);
            
            /* Específico do E-mail */
            --email-bg: #ffffff;
            --code-box-bg: #f8fafc;
            --code-box-border: #e2e8f0;
        }

        html.dark {
            --bg-right: #09090b; 
            --surface: #121214; 
            --surface-border: #27272a;
            --surface-inner: #18181b;
            --text-primary: #f8fafc;
            --text-secondary: #a1a1aa;
            --primary: #ea4335; /* Mantém o vermelho no dark mode */
            --primary-hover: #ff5244;
            --input-bg: #121214;
            --input-border: rgba(255, 255, 255, 0.15); 
            --icon-color: #a1a1aa;
            --shadow-card: 0 25px 50px -12px rgba(0,0,0,0.6);
            
            /* Específico do E-mail */
            --email-bg: #18181b;
            --code-box-bg: #09090b;
            --code-box-border: #27272a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: var(--font-main); color: var(--text-primary);
            background-color: var(--bg-right); overflow-x: hidden; min-height: 100vh;
        }

        /* =========================================================
           LAYOUT CENTRALIZADO
           ========================================================= */
        .page-wrapper {
            display: flex; min-height: 100vh; width: 100vw; flex-direction: column;
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
        .control-btn:hover { background: var(--surface-inner); transform: scale(1.05); }
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
        .lang-option:hover { background: var(--surface-inner); }

        /* =========================================================
           CARTÕES (AUTENTICAÇÃO & E-MAIL)
           ========================================================= */
        .card {
            width: 100%; max-width: 480px;
            background: var(--surface); border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg); padding: 2.5rem;
            box-shadow: var(--shadow-card); position: relative; z-index: 10;
            display: none; animation: fadeInScale 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card.active { display: block; }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* SVG Logo Estilo Gmail */
        .gmail-logo-container {
            display: flex; align-items: center; justify-content: center;
            width: 60px; height: 60px; border-radius: 16px;
            background: rgba(234, 67, 53, 0.1); color: var(--primary);
            margin: 0 auto 1.5rem auto; box-shadow: 0 0 20px rgba(234, 67, 53, 0.15);
        }

        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header h2 { font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; margin-bottom: 0.5rem; }
        .auth-header p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; }

        /* Formulários */
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.6rem; color: var(--text-primary); }
        
        .form-control {
            width: 100%; padding: 14px 16px; background: var(--input-bg); border: 1.5px solid var(--input-border);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-family: var(--font-main); font-size: 0.95rem; font-weight: 500; transition: all 0.3s ease; outline: none;
        }
        .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; font-weight: 400;}
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(234, 67, 53, 0.1); background: var(--surface); }
        html.dark .form-control:focus { box-shadow: 0 0 0 4px rgba(234, 67, 53, 0.2); }
        
        .input-icon { position: absolute; left: 14px; top: 38px; color: var(--icon-color); pointer-events: none; }
        .with-icon { padding-left: 45px; }

        .btn-action {
            width: 100%; padding: 15px; background: var(--primary); color: #ffffff;
            border: none; border-radius: var(--radius-md); font-family: var(--font-main);
            font-size: 1rem; font-weight: 800; cursor: pointer; transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 15px rgba(234, 67, 53, 0.3);
        }
        .btn-action:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(234, 67, 53, 0.4); }
        .btn-action:active { transform: translateY(1px); }
        .btn-action:disabled { opacity: 0.7; cursor: not-allowed; }

        .link-back {
            display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--text-secondary);
            text-decoration: none; font-weight: 700; font-size: 0.9rem; transition: color 0.2s; margin-top: 1.5rem;
        }
        .link-back:hover { color: var(--text-primary); }

        /* =========================================================
           DESIGN DO E-MAIL (SIMULAÇÃO INBOX)
           ========================================================= */
        #email-card { max-width: 550px; padding: 0; overflow: hidden; }
        
        .email-topbar {
            background: var(--surface-inner); border-bottom: 1px solid var(--surface-border);
            padding: 16px 24px; display: flex; justify-content: space-between; align-items: center;
        }
        .email-topbar-title { font-weight: 800; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; }
        .email-topbar-title svg { color: var(--primary); }
        
        .email-content { padding: 24px; background: var(--email-bg); }
        
        .email-sender-area { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
        .sender-avatar {
            width: 44px; height: 44px; border-radius: 50%; background: var(--surface-border);
            display: flex; align-items: center; justify-content: center; color: var(--text-primary);
        }
        .sender-info { display: flex; flex-direction: column; }
        .sender-name { font-weight: 800; font-size: 1rem; color: var(--text-primary); }
        .sender-email { font-size: 0.8rem; color: var(--text-secondary); }
        .email-time { margin-left: auto; font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; }

        .email-subject { font-family: var(--font-display); font-size: 1.4rem; font-weight: 800; margin-bottom: 20px; color: var(--text-primary); }
        
        .email-body { font-size: 0.95rem; line-height: 1.6; color: var(--text-primary); }
        .email-body p { margin-bottom: 16px; }

        .code-display-box {
            background: var(--code-box-bg); border: 2px dashed var(--code-box-border);
            border-radius: var(--radius-md); padding: 24px; text-align: center; margin: 24px 0;
            position: relative;
        }
        .the-code {
            font-family: var(--font-display); font-size: 2.5rem; font-weight: 800; letter-spacing: 4px;
            color: var(--primary); margin: 0;
        }
        .code-warning {
            display: flex; align-items: flex-start; gap: 10px; padding: 16px;
            background: rgba(234, 67, 53, 0.05); border-left: 4px solid var(--primary);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0; margin-top: 24px;
        }
        .code-warning p { margin: 0; font-size: 0.85rem; color: var(--text-secondary); }
        .code-warning strong { color: var(--text-primary); }

        .email-footer-actions {
            padding: 24px; border-top: 1px solid var(--surface-border); background: var(--surface-inner);
            display: flex; gap: 12px;
        }
        .btn-secondary {
            flex: 1; padding: 14px; background: transparent; color: var(--text-primary);
            border: 1px solid var(--surface-border); border-radius: var(--radius-md); font-weight: 700;
            cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-secondary:hover { background: var(--surface-border); }
        .btn-secondary:active { transform: scale(0.98); }

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
        .toast.info .toast-icon { background: #3b82f6; }
        .toast-msg { font-size: 0.9rem; font-weight: 600; line-height: 1.4; flex: 1; }

        @media (max-width: 600px) {
            .card { padding: 2rem 1.5rem; }
            .top-controls { top: 1rem; right: 1rem; }
            .email-content { padding: 16px; }
            .the-code { font-size: 2rem; }
            .email-footer-actions { flex-direction: column; }
        }
    </style>
</head>
<body>

    <!-- TOAST CUENTAINER -->
    <div id="toast-container"></div>

    <div class="page-wrapper">
        
        <!-- Controles Topo -->
        <div class="top-controls">
            <div class="lang-menu-wrapper">
                <button class="control-btn" onclick="toggleLangMenu()" title="Idioma">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </button>
                <div class="lang-dropdown" id="lang-dropdown">
                    <div class="lang-option" onclick="setAppLang('pt')">Português</div>
                    <div class="lang-option" onclick="setAppLang('en')">English</div>
                    <div class="lang-option" onclick="setAppLang('es')">Español</div>
                </div>
            </div>
            <button class="control-btn" onclick="toggleTheme()" title="Alternar tema">
                <svg id="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;width:20px;height:20px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <svg id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>

        <!-- PASSO 1: AUTENTICAÇÃO DE SEGURANÇA -->
        <div class="card active" id="auth-card">
            
            <div class="gmail-logo-container">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:32px;height:32px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>

            <div class="auth-header">
                <h2 data-i18n="auth_title">Verificação de Segurança</h2>
                <p data-i18n="auth_desc">Para acessar sua caixa de entrada e ler o código, confirme os dados exatos da sua conta.</p>
            </div>

            <form id="form-auth" onsubmit="handleCheckInbox(event)">
                <div class="form-group">
                    <label class="form-label" for="email" data-i18n="email_label">E-mail da conta</label>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <input type="email" id="email" name="email" class="form-control with-icon" placeholder="vos@ejemplo.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username" data-i18n="username_label">Nombre de usuario (Username)</label>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" id="username" name="username" class="form-control with-icon" placeholder="Nombre exato registrado" required>
                </div>

                <button type="submit" class="btn-action" id="btn-open">
                    <span data-i18n="btn_open_inbox">Acessar E-mail</span>
                </button>
            </form>

            <a href="/recuperar-senha" class="link-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <span data-i18n="back_to_recovery">Volver para a recuperação</span>
            </a>
        </div>

        <!-- PASSO 2: CAIXA DE ENTRADA (MOCK GMAIL) -->
        <div class="card" id="email-card">
            
            <div class="email-topbar">
                <div class="email-topbar-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    DTunnel Inbox
                </div>
                <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;" data-i18n="inbox_label">Caixa de Entrada</div>
            </div>

            <div class="email-content">
                <div class="email-subject" data-i18n="email_subject">Redefinição de senha DTunnel</div>
                
                <div class="email-sender-area">
                    <div class="sender-avatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div class="sender-info">
                        <div class="sender-name">Equipe DTunnel</div>
                        <div class="sender-email">no-reply@dtunnel.com.br</div>
                    </div>
                    <div class="email-time" id="email-time">00:00</div>
                </div>

                <div class="email-body">
                    <p><span data-i18n="email_hello">Hola</span> <strong id="display-user">Usuario</strong>,</p>
                    <p data-i18n="email_body_text">Foi solicitada uma redefinição de senha para sua conta. Utilize o código de verificação abaixo para prosseguir com a alteração:</p>
                    
                    <div class="code-display-box">
                        <h1 class="the-code" id="display-code">000-000</h1>
                    </div>

                    <div class="code-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;flex-shrink:0;color:var(--primary);"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <p><strong data-i18n="warning_title">ATENÇÃO:</strong> <span data-i18n="warning_text">Nunca compartilhe este código. A equipe DTunnel nunca pedirá suas credenciais por e-mail ou mensagem.</span></p>
                    </div>
                </div>
            </div>

            <div class="email-footer-actions">
                <button type="button" class="btn-secondary" onclick="copyCode()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span data-i18n="btn_copy">Copiar Código</span>
                </button>
                <a href="/recuperar-senha" class="btn-action" style="text-decoration:none;">
                    <span data-i18n="btn_return">Volver e Colar</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
            
        </div>

    </div>

    <script>
        // ======================================================================
        // SISTEMA DE IDIOMAS SINCRONIZADO
        // ======================================================================
        const dict = {
            'pt': {
                auth_title: 'Verificação de Segurança', auth_desc: 'Para acessar sua caixa de entrada e ler o código, confirme os dados exatos da sua conta.',
                email_label: 'E-mail da conta', username_label: 'Nombre de usuario (Username)', btn_open_inbox: 'Acessar E-mail',
                back_to_recovery: 'Volver para a recuperação', inbox_label: 'Caixa de Entrada', email_subject: 'Redefinição de senha DTunnel',
                email_hello: 'Hola', email_body_text: 'Foi solicitada uma redefinição de senha para sua conta. Utilize o código de verificação abaixo para prosseguir com a alteração:',
                warning_title: 'ATENÇÃO:', warning_text: 'Nunca compartilhe este código. A equipe DTunnel nunca pedirá suas credenciais por e-mail ou mensagem.',
                btn_copy: 'Copiar Código', btn_return: 'Volver e Colar',
                toast_missing_data: 'Completá todos los campos.', toast_security_block: 'Acesso Negado! E-mail e Nombre de Usuario não correspondem a uma conta válida.',
                toast_no_code_found: 'Ningún código ativo encontrado para este e-mail.', toast_server_error: 'Error de comunicación con el servidor.',
                toast_copied: 'Código copiado con éxito!'
            },
            'en': {
                auth_title: 'Security Verification', auth_desc: 'To access your inbox and read the code, confirm your exact account details.',
                email_label: 'Account E-mail', username_label: 'Username', btn_open_inbox: 'Access E-mail',
                back_to_recovery: 'Back to recovery', inbox_label: 'Inbox', email_subject: 'DTunnel password reset',
                email_hello: 'Hello', email_body_text: 'A password reset has been requested for your account. Use the verification code below to proceed with the change:',
                warning_title: 'WARNING:', warning_text: 'Never share this code. The DTunnel team will never ask for your credentials via email or message.',
                btn_copy: 'Copy Code', btn_return: 'Go Back and Paste',
                toast_missing_data: 'Fill in all fields.', toast_security_block: 'Access Denied! E-mail and Username do not match a valid account.',
                toast_no_code_found: 'No active code found for this email.', toast_server_error: 'Server communication error.',
                toast_copied: 'Code copied successfully!'
            },
            'es': {
                auth_title: 'Verificación de Seguridad', auth_desc: 'Para acceder a su bandeja de entrada y leer el código, confirme los datos exactos de su cuenta.',
                email_label: 'Colorreo de la cuenta', username_label: 'Nombre de usuario (Username)', btn_open_inbox: 'Acceder al Colorreo',
                back_to_recovery: 'Volver a la recuperación', inbox_label: 'Bandeja de Entrada', email_subject: 'Restablecimiento de contraseña DTunnel',
                email_hello: 'Hola', email_body_text: 'Se ha solicitado un restablecimiento de contraseña para su cuenta. Utilice el código de verificación a continuación para continuar:',
                warning_title: 'ATENCIÓN:', warning_text: 'Nunca comparta este código. El equipo de DTunnel nunca pedirá sus credenciales por correo o mensaje.',
                btn_copy: 'Copiar Código', btn_return: 'Volver y Pegar',
                toast_missing_data: 'Llene todos los campos.', toast_security_block: '¡Acceso Denegado! El correo y nombre de usuario no coinciden.',
                toast_no_code_found: 'No se encontró ningún código activo para este correo.', toast_server_error: 'Error de comunicación con el servidor.',
                toast_copied: '¡Código copiado con éxito!'
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

        function toggleLangMenu() { document.getElementById('lang-dropdown').classList.toggle('show'); }

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
            if (isDark) { sun.style.display = 'block'; moon.style.display = 'none'; } 
            else { sun.style.display = 'none'; moon.style.display = 'block'; }
        }

        // ======================================================================
        // TOAST NOTIFICATIONS
        // ======================================================================
        function showToast(type, messageKey) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const message = messageKey.startsWith('toast_') ? getLangMsg(messageKey) : messageKey;
            
            let iconSvg = '';
            if(type === 'error') iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
            else if(type === 'success') iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>`;
            else iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;

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
        // COMUNICAÇÃO COM API_GMAIL.PHP (VERIFICAÇÃO DUPLA)
        // ======================================================================
        let rawCodeToCopy = "";

        function handleCheckInbox(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-open');
            const email = document.getElementById('email').value;
            const username = document.getElementById('username').value;
            
            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = `<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'get_inbox_data');
            formData.append('email', email);
            formData.append('username', username);

            fetch('/api_gmail.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                if (res.success) {
                    // Preenche a Carta com os dados do banco
                    document.getElementById('display-user').innerText = res.data.username;
                    document.getElementById('display-code').innerText = res.data.code_formatted;
                    document.getElementById('email-time').innerText = res.data.requested_time;
                    
                    // Salva a versão sem traço para a função de copiar
                    rawCodeToCopy = res.data.code_formatted.replace('-', '');

                    // Animação de Troca de Card
                    document.getElementById('auth-card').classList.remove('active');
                    document.getElementById('email-card').classList.add('active');
                } else {
                    showToast('error', 'toast_' + res.error_code);
                }
            })
            .catch(() => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                showToast('error', 'toast_server_error');
            });
        }

        // ======================================================================
        // FUNÇÃO COPIAR CÓDIGO
        // ======================================================================
        function copyCode() {
            if(!rawCodeToCopy) return;
            
            // Tenta usar a API Clipboard (Moderna)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(rawCodeToCopy).then(() => {
                    showToast('info', 'toast_copied');
                });
            } else {
                // Fallback (Método Antigo)
                let textArea = document.createElement("textarea");
                textArea.value = rawCodeToCopy;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast('info', 'toast_copied');
                } catch (err) {
                    console.error('Error al copiar', err);
                }
                textArea.remove();
            }
        }
    </script>
</body>
</html>