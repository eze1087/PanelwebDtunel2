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

$customLogoUrl = ""; // Link da sua logo, se houver
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Recuperar contraseña - DTunnel Premium</title>
    
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
            --primary: #0f172a;
            --primary-hover: #334155;
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
        }

        html.dark {
            --bg-right: #09090b; 
            --surface: #121214; 
            --surface-border: #27272a;
            --surface-inner: #18181b;
            --text-primary: #f8fafc;
            --text-secondary: #a1a1aa;
            --primary: #ffffff;
            --primary-hover: #e4e4e7;
            --input-bg: #121214;
            --input-border: rgba(255, 255, 255, 0.15); 
            --icon-color: #a1a1aa;
            --success-bg: rgba(16, 185, 129, 0.15);
            --success-text: #34d399;
            --shadow-card: 0 25px 50px -12px rgba(0,0,0,0.6);
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
           CARTÃO FLUTUANTE & ESTADOS (ETAPAS)
           ========================================================= */
        .auth-card {
            width: 100%; max-width: 480px;
            background: var(--surface); border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg); padding: 2.5rem;
            box-shadow: var(--shadow-card); position: relative; z-index: 10;
        }

        .badge-header {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 20px;
            background: var(--surface-inner); border: 1px solid var(--surface-border);
            font-size: 0.75rem; font-weight: 700; letter-spacing: 1px;
            color: var(--text-secondary); text-transform: uppercase;
            margin-bottom: 1.5rem;
        }

        .auth-header h2 { font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
        .auth-header p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem; }

        /* Container de Etapas Internas */
        .step-container {
            background: var(--surface-inner); border: 1px solid var(--surface-border);
            border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem;
            display: none; animation: fadeIn 0.4s ease;
        }
        .step-container.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .step-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary); }
        .step-desc { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.4; }

        /* Estado Box (verde) */
        .status-box {
            background: var(--success-bg); color: var(--success-text); border-radius: var(--radius-sm);
            padding: 12px 16px; font-size: 0.9rem; font-weight: 600; margin-bottom: 1.5rem; display: none;
        }

        /* Info Account Box (Mostra e-mail nas etapas 2 e 3) */
        .info-box {
            margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 12px;
        }
        .info-item { display: flex; flex-direction: column; gap: 4px; }
        .info-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--text-secondary); }
        .info-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }

        /* Formulários */
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.6rem; color: var(--text-primary); }
        
        .form-control {
            width: 100%; padding: 14px 16px; background: var(--input-bg); border: 1.5px solid var(--input-border);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-family: var(--font-main); font-size: 0.95rem; font-weight: 500; transition: all 0.3s ease; outline: none;
        }
        .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; font-weight: 400;}
        .form-control:focus { border-color: var(--text-primary); box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.1); background: var(--surface); }
        html.dark .form-control:focus { box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05); }
        
        /* Estilo especial para o input de código numérico */
        .input-code { text-align: center; letter-spacing: 6px; font-size: 1.2rem; font-weight: 700; font-family: var(--font-display); }

        .input-icon {
            position: absolute; left: 14px; top: 38px; color: var(--icon-color); pointer-events: none;
        }
        .input-icon-btn {
            position: absolute; right: 12px; top: 38px; background: none; border: none; color: var(--icon-color);
            cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center;
        }
        .with-icon { padding-left: 45px; }

        .btn-action {
            width: 100%; padding: 14px; background: transparent; color: var(--text-primary);
            border: 1px solid var(--surface-border); border-radius: var(--radius-md); font-family: var(--font-main);
            font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.2s ease;
        }
        .btn-action:hover { background: var(--surface-inner); border-color: var(--text-primary); }
        .btn-action:active { transform: scale(0.98); }
        .btn-action:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Rodapé do Cartão (Volver) */
        .card-footer {
            margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;
            padding-top: 1.5rem; border-top: 1px solid var(--surface-border);
        }
        .link-back {
            display: flex; align-items: center; gap: 8px; color: var(--text-primary);
            text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: opacity 0.2s;
        }
        .link-back:hover { opacity: 0.7; }
        .btn-outline {
            padding: 8px 16px; border: 1px solid var(--surface-border); background: transparent;
            color: var(--text-primary); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; font-size: 0.85rem;
        }
        .btn-outline:hover { background: var(--surface-inner); }

        /* =========================================================
           BOTÃO GMAIL FLUTUANTE (MOCK)
           ========================================================= */
        .gmail-mock-btn {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 100;
            background: var(--surface); border: 1px solid var(--surface-border);
            padding: 12px 24px; border-radius: 30px; display: flex; align-items: center; gap: 12px;
            box-shadow: var(--shadow-card); color: var(--text-primary); font-weight: 700;
            cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none; font-size: 0.95rem;
        }
        .gmail-mock-btn:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.2); }
        .gmail-mock-btn:active { transform: translateY(0); }
        
        .notification-badge {
            position: absolute; top: -5px; right: -5px; background: #ef4444; color: white;
            font-size: 0.7rem; font-weight: 800; width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--bg-right); opacity: 0; transform: scale(0); transition: all 0.3s;
        }
        .notification-badge.show { opacity: 1; transform: scale(1); animation: pulseRed 2s infinite; }
        @keyframes pulseRed { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

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

        /* Ajustes Mobile */
        @media (max-width: 600px) {
            .auth-card { padding: 2rem 1.5rem; }
            .top-controls { top: 1rem; right: 1rem; }
            .gmail-mock-btn { bottom: 1rem; right: 1rem; padding: 10px 18px; font-size: 0.85rem; }
            .badge-header { margin-bottom: 1rem; }
            .auth-header h2 { font-size: 1.5rem; }
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

        <!-- Botão Abrir E-mail (Simulador) -->
        <a href="/valida_gmail.php" class="gmail-mock-btn" id="btn-open-gmail">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span data-i18n="open_inbox">Abrir E-mail</span>
            <div class="notification-badge" id="gmail-badge">1</div>
        </a>

        <!-- Cartão Principal -->
        <div class="auth-card">
            
            <div class="badge-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span data-i18n="badge_recovery">RECUPERAÇÃO DE SENHA</span>
            </div>

            <div class="auth-header">
                <h2 data-i18n="title_reset">Redefinir acesso</h2>
                <p data-i18n="desc_reset">Solicite um código por e-mail, valide a etapa e defina uma nova senha de forma segura.</p>
            </div>

            <!-- ETAPA 1: SOLICITAR CÓDIGO -->
            <div class="step-container active" id="step-1">
                <h3 class="step-title" data-i18n="step1_title">Solicitar código</h3>
                <p class="step-desc" data-i18n="step1_desc">Informe o e-mail da conta para receber o código de recuperação.</p>
                
                <form id="form-request" onsubmit="handleRequestCode(event)">
                    <div class="form-group">
                        <label class="form-label" for="email" data-i18n="email_label">E-mail</label>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="email" name="email" class="form-control with-icon" placeholder="vos@ejemplo.com" required>
                    </div>
                    <button type="submit" class="btn-action" id="btn-req">
                        <span data-i18n="btn_send_code">Enviar código</span>
                    </button>
                </form>
            </div>

            <!-- ETAPA 2: VALIDAR CÓDIGO -->
            <div class="step-container" id="step-2">
                <div class="status-box" id="status-sent" data-i18n="status_code_sent" style="display: block;">
                    Código enviado para o seu e-mail.
                </div>
                
                <div class="info-box">
                    <div class="info-item">
                        <span class="info-label" data-i18n="label_account">CUENTA</span>
                        <span class="info-value" id="display-email">---</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" data-i18n="label_code_status">CÓDIGO</span>
                        <span class="info-value" data-i18n="status_waiting">Aguardando preenchimento</span>
                    </div>
                </div>

                <h3 class="step-title" data-i18n="step2_title">Validar código</h3>
                <p class="step-desc" data-i18n="step2_desc">Digite o código enviado para o seu e-mail para liberar a redefinição.</p>
                
                <form id="form-validate" onsubmit="handleValidateCode(event)">
                    <div class="form-group">
                        <label class="form-label" for="code" data-i18n="code_label">Código de verificação</label>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="text" id="code" name="code" class="form-control with-icon input-code" placeholder="000000" maxlength="6" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn-action" id="btn-val">
                        <span data-i18n="btn_validate_code">Validar código</span>
                    </button>
                </form>
            </div>

            <!-- ETAPA 3: NOVA SENHA -->
            <div class="step-container" id="step-3">
                <div class="status-box" id="status-valid" data-i18n="status_code_valid" style="display: block;">
                    Código validado con éxito.
                </div>
                
                <div class="info-box">
                    <div class="info-item">
                        <span class="info-label" data-i18n="label_account">CUENTA</span>
                        <span class="info-value" id="display-email-final">---</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" data-i18n="label_code_status">CÓDIGO</span>
                        <span class="info-value" data-i18n="status_approved">Validado e aprovado</span>
                    </div>
                </div>

                <h3 class="step-title" data-i18n="step3_title">Definir nova senha</h3>
                <p class="step-desc" data-i18n="step3_desc">Escolha uma nova senha segura para concluir a recuperação.</p>
                
                <form id="form-reset" onsubmit="handleResetPassword(event)">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" for="new_password" data-i18n="new_pass_label">Nueva senha</label>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="new_password" name="new_password" class="form-control with-icon" placeholder="Mínimo 6 caracteres" required style="padding-right:40px;">
                        <button type="button" class="input-icon-btn" onclick="togglePassword('new_password', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password" data-i18n="confirm_pass_label">Confirmar contraseña</label>
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control with-icon" placeholder="Repita a nova senha" required style="padding-right:40px;">
                        <button type="button" class="input-icon-btn" onclick="togglePassword('confirm_password', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>

                    <button type="submit" class="btn-action" id="btn-reset">
                        <span data-i18n="btn_change_pass">Alterar senha</span>
                    </button>
                </form>
            </div>

            <!-- Rodapé Acciones Extras -->
            <div class="card-footer">
                <a href="/login" class="link-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <span data-i18n="back_login">Volver ao login</span>
                </a>
                
                <!-- Visível apenas no step 2 para reenviar código -->
                <button type="button" class="btn-outline" id="btn-resend" style="display:none;" onclick="resetToStep1()">
                    <span data-i18n="btn_resend">Solicitar novo</span>
                </button>
                
                <a href="/login" class="btn-outline" id="btn-fast-login" style="display:block;">
                    <span data-i18n="btn_login">Iniciar sesión</span>
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
                badge_recovery: 'RECUPERAÇÃO DE SENHA', title_reset: 'Redefinir acesso', desc_reset: 'Solicite um código por e-mail, valide a etapa e defina uma nova senha de forma segura.',
                step1_title: 'Solicitar código', step1_desc: 'Informe o e-mail da conta para receber o código de recuperação.',
                email_label: 'E-mail', btn_send_code: 'Enviar código',
                status_code_sent: 'Código enviado para o seu e-mail.', label_account: 'CUENTA', label_code_status: 'CÓDIGO', status_waiting: 'Aguardando preenchimento',
                step2_title: 'Validar código', step2_desc: 'Digite o código enviado para o seu e-mail para liberar a redefinição.',
                code_label: 'Código de verificação', btn_validate_code: 'Validar código',
                status_code_valid: 'Código validado con éxito.', status_approved: 'Validado e aprovado',
                step3_title: 'Definir nova senha', step3_desc: 'Escolha uma nova senha segura para concluir a recuperação.',
                new_pass_label: 'Nueva senha', confirm_pass_label: 'Confirmar contraseña', btn_change_pass: 'Alterar senha',
                back_login: 'Volver ao login', btn_resend: 'Solicitar novo', btn_login: 'Iniciar sesión', open_inbox: 'Abrir E-mail',
                // Toasts
                toast_email_not_found: 'E-mail não encontrado no sistema.', toast_code_sent: 'Código gerado con éxito! Verifique a caixa.',
                toast_invalid_code: 'Código inválido ou expirado.', toast_code_valid: 'Código aceito! Prossiga.',
                toast_short_pass: 'A senha deve ter pelo menos 6 caracteres.', toast_pass_mismatch: 'Las contraseñas no coinciden.',
                toast_pass_changed: 'Contraseña alterada con éxito! Faça login.', toast_server_error: 'Error de comunicación con el servidor.'
            },
            'en': {
                badge_recovery: 'PASSWORD RECOVERY', title_reset: 'Reset access', desc_reset: 'Request a code via email, validate the step and set a new password securely.',
                step1_title: 'Request code', step1_desc: 'Enter the account email to receive the recovery code.',
                email_label: 'E-mail', btn_send_code: 'Send code',
                status_code_sent: 'Code sent to your email.', label_account: 'ACCOUNT', label_code_status: 'CODE', status_waiting: 'Awaiting input',
                step2_title: 'Validate code', step2_desc: 'Enter the code sent to your email to unlock the reset.',
                code_label: 'Verification code', btn_validate_code: 'Validate code',
                status_code_valid: 'Code validated successfully.', status_approved: 'Validated and approved',
                step3_title: 'Set new password', step3_desc: 'Choose a secure new password to complete recovery.',
                new_pass_label: 'New password', confirm_pass_label: 'Confirm password', btn_change_pass: 'Change password',
                back_login: 'Back to login', btn_resend: 'Request new', btn_login: 'Sign in', open_inbox: 'Open Inbox',
                toast_email_not_found: 'Email not found in the system.', toast_code_sent: 'Code successfully generated! Check inbox.',
                toast_invalid_code: 'Invalid or expired code.', toast_code_valid: 'Code accepted! Proceed.',
                toast_short_pass: 'Password must have at least 6 characters.', toast_pass_mismatch: 'Passwords do not match.',
                toast_pass_changed: 'Password changed successfully! Log in.', toast_server_error: 'Server communication error.'
            },
            'es': {
                badge_recovery: 'RECUPERACIÓN DE CONTRASEÑA', title_reset: 'Restablecer acceso', desc_reset: 'Solicite un código por correo, valide el paso y establezca una nueva contraseña de forma segura.',
                step1_title: 'Solicitar código', step1_desc: 'Ingrese el correo de la cuenta para recibir el código.',
                email_label: 'Correo', btn_send_code: 'Enviar código',
                status_code_sent: 'Código enviado a su correo.', label_account: 'CUENTA', label_code_status: 'CÓDIGO', status_waiting: 'Esperando ingreso',
                step2_title: 'Validar código', step2_desc: 'Ingrese el código enviado a su correo para liberar el restablecimiento.',
                code_label: 'Código de verificación', btn_validate_code: 'Validar código',
                status_code_valid: 'Código validado con éxito.', status_approved: 'Validado y aprobado',
                step3_title: 'Definir nueva contraseña', step3_desc: 'Elija una nueva contraseña segura para concluir.',
                new_pass_label: 'Nueva contraseña', confirm_pass_label: 'Confirmar contraseña', btn_change_pass: 'Cambiar contraseña',
                back_login: 'Volver al login', btn_resend: 'Solicitar nuevo', btn_login: 'Ingresar', open_inbox: 'Abrir correo',
                toast_email_not_found: 'Correo no encontrado en el sistema.', toast_code_sent: '¡Código generado! Revisa la bandeja.',
                toast_invalid_code: 'Código inválido o expirado.', toast_code_valid: '¡Código aceptado! Continúe.',
                toast_short_pass: 'La contraseña debe tener al menos 6 caracteres.', toast_pass_mismatch: 'Las contraseñas no coinciden.',
                toast_pass_changed: '¡Contraseña cambiada con éxito! Inicie sesión.', toast_server_error: 'Error del servidor.'
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
            
            // Máscara para o código 000000 aceitar apenas números
            const codeInput = document.getElementById('code');
            codeInput.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.lang-menu-wrapper')) {
                    document.getElementById('lang-dropdown').classList.remove('show');
                }
            });
        });

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

        // Variáveis globais de estado
        let currentEmail = '';

        // ======================================================================
        // LÓGICA DE ETAPAS E COMUNICAÇÃO COM API (AJAX)
        // ======================================================================
        
        // ETAPA 1: SOLICITAR CÓDIGO
        function handleRequestCode(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-req');
            const email = document.getElementById('email').value;
            
            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = `<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'request_code');
            formData.append('email', email);

            fetch('/api_gmail.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                if (res.success) {
                    currentEmail = email;
                    document.getElementById('display-email').innerText = email;
                    document.getElementById('display-email-final').innerText = email;
                    
                    // Transição visual
                    document.getElementById('step-1').classList.remove('active');
                    document.getElementById('step-2').classList.add('active');
                    document.getElementById('btn-fast-login').style.display = 'none';
                    document.getElementById('btn-resend').style.display = 'block';

                    // Mostra Notificación no Botão Gmail Mock
                    document.getElementById('gmail-badge').classList.add('show');
                    showToast('success', 'toast_code_sent');
                } else {
                    showToast('error', 'toast_' + res.error_code);
                }
            })
            .catch(() => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                showToast('error', 'toast_server_error');
            });
        }

        // ETAPA 2: VALIDAR CÓDIGO
        function handleValidateCode(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-val');
            const code = document.getElementById('code').value;
            
            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = `<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'validate_code');
            formData.append('email', currentEmail);
            formData.append('code', code);

            fetch('/api_gmail.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                if (res.success) {
                    // Tira bolinha de notificação
                    document.getElementById('gmail-badge').classList.remove('show');
                    
                    // Transição visual
                    document.getElementById('step-2').classList.remove('active');
                    document.getElementById('step-3').classList.add('active');
                    document.getElementById('btn-resend').style.display = 'none';

                    showToast('success', 'toast_code_valid');
                } else {
                    showToast('error', 'toast_' + res.error_code);
                }
            })
            .catch(() => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                showToast('error', 'toast_server_error');
            });
        }

        // ETAPA 3: ALTERAR SENHA
        function handleResetPassword(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-reset');
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            const code = document.getElementById('code').value; // Envia o código junto como token
            
            if (newPass.length < 6) { showToast('error', 'toast_short_pass'); return; }
            if (newPass !== confirmPass) { showToast('error', 'toast_pass_mismatch'); return; }

            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = `<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('email', currentEmail);
            formData.append('code', code);
            formData.append('new_password', newPass);

            fetch('/api_gmail.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                if (res.success) {
                    showToast('success', 'toast_pass_changed');
                    setTimeout(() => { window.location.href = '/login'; }, 1500);
                } else {
                    showToast('error', 'toast_' + res.error_code);
                }
            })
            .catch(() => {
                btn.innerHTML = originalBtnHtml; btn.disabled = false;
                showToast('error', 'toast_server_error');
            });
        }

        // REENVIAR / RESETAR FLUXO
        function resetToStep1() {
            document.getElementById('code').value = '';
            document.getElementById('step-2').classList.remove('active');
            document.getElementById('step-1').classList.add('active');
            document.getElementById('btn-resend').style.display = 'none';
            document.getElementById('btn-fast-login').style.display = 'block';
            document.getElementById('gmail-badge').classList.remove('show');
        }
    </script>
</body>
</html>