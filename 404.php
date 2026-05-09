<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Central de Segurança e Roteamento (404 / Suspended)
 * @description Oculta navegação lateral, separa os ícones do header e prende o usuário.
 * =======================================================================================
 */

// Proteção da rota direta (Redireciona para evitar erro de cabeçalho no Chrome)
if (!defined('DTUNNEL_APP')) { 
    header('Location: /404'); 
    exit; 
}

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// ----------------------------------------------------------------------
// 1. COLETA DE DADOS EM TEMPO REAL NO BANCO (SEM CACHE)
// ----------------------------------------------------------------------
$dbFile = __DIR__ . '/../db/usuarios.json';
clearstatcache(true, $dbFile);

$sessionEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['username'] ?? 'Visitante';
$userEstado = 'active';

if (file_exists($dbFile) && !empty($sessionEmail)) {
    $usuarios = json_decode(file_get_contents($dbFile), true) ?: [];
    foreach ($usuarios as $u) {
        if (strtolower($u['email']) === strtolower($sessionEmail)) {
            $userName = $u['username'] ?? $userName;
            $userEstado = $u['status'] ?? 'active';
            break;
        }
    }
}

// Se o usuário for o Mestre, nunca fica suspenso
if (strtolower($sessionEmail) === 'elnene.admin@gmail.com') {
    $userEstado = 'active';
}

$isSuspended = ($userEstado === 'suspended');
$pageTitle = $isSuspended ? 'Conta Suspensa' : 'Página Não Encontrada';

ob_start();
?>

<style>
/* ==========================================================================
   BLINDAGEM E REPOSICIONAMENTO DE ÍCONES (HEADER)
   ========================================================================== */
/* Oculta Sidebar, Overlay e Perfil (Bolota de Foto) */
aside.sidebar, #sidebar, .sidebar-overlay, #sidebarOverlay,
button[onclick*="forceOpenSidebar"], .btn-open-sidebar,
.header-avatar, .account-menu, #account-menu-dropdown { 
    display: none !important; 
    visibility: hidden !important;
    pointer-events: none !important;
}

/* Garante que o conteúdo ocupe a tela toda sem margin da sidebar */
.main-content, #main-wrapper { 
    margin-left: 0 !important; 
    width: 100% !important; 
    padding-top: 10px !important; 
}

/* * A MÁGICA DOS ÍCONES SEPARADOS E ALINHADOS:
 * Faz o container dos botões ocupar toda a tela e joga 
 * o Idioma pra Extrema Esquerda e o Sol/Lua pra Extrema Direita.
 */
.app-header { 
    width: 100% !important;
    padding: 16px 20px !important; 
    display: flex !important;
}

.header-actions {
    display: flex !important;
    flex-direction: row !important; 
    width: 100% !important; 
    justify-content: space-between !important; /* Um de cada lado */
    align-items: center !important;
    margin: 0 !important;
}

/* CORREÇÃO DEFINITIVA DO MODAL DE IDIOMAS: 
 * Garante que abra para a direita (para dentro da tela) e não corte no canto 
 */
.dropdown-menu.lang-menu, #lang-dropdown {
    left: 0 !important;
    right: auto !important;
    transform-origin: top left !important;
    margin-top: 10px !important;
}

/* ==========================================================================
   ESTILOS DA PÁGINA (DESIGN HOME PREMIUM)
   ========================================================================== */
.security-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --orange: #f97316; --danger: #ef4444;
    
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: calc(100vh - 120px); padding: 20px; font-family: 'Manrope', system-ui, sans-serif;
}

:root.dark .security-wrapper, .dark .security-wrapper, body.dark .security-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #121214;
}

.security-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; }

.security-card {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px;
    padding: 48px 32px; text-align: center; max-width: 460px; width: 100%;
    box-shadow: 0 20px 50px rgba(0,0,0,0.05); animation: cardIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
.dark .security-card { box-shadow: 0 30px 70px rgba(0,0,0,0.5); }

@keyframes cardIn { 
    from { opacity: 0; transform: scale(0.9) translateY(20px); } 
    to { opacity: 1; transform: scale(1) translateY(0); } 
}

/* Ícones Animados */
.sec-icon-circle {
    width: 88px; height: 88px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px auto; position: relative; border: 5px solid var(--card-bg); box-shadow: 0 0 0 1px var(--card-border);
}
.sec-icon-circle.orange { background: rgba(249, 115, 22, 0.1); color: var(--orange); }
.sec-icon-circle.red { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

/* Efeito de Pulso para Suspensão */
.sec-icon-circle.orange::after {
    content: ''; position: absolute; inset: -5px; border-radius: 50%; border: 2px solid var(--orange);
    animation: pulseSec 2s infinite; opacity: 0;
}
@keyframes pulseSec { 0% { transform: scale(1); opacity: 0.6; } 100% { transform: scale(1.4); opacity: 0; } }

.sec-greeting { font-size: 1.15rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; }
.sec-greeting strong { color: var(--text-main); font-weight: 800; }
.sec-title { font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 20px; line-height: 1.1; }

.status-label {
    display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; border-radius: 50px;
    font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 36px;
}
.status-label.orange { background: rgba(249, 115, 22, 0.1); color: var(--orange); border: 1px solid rgba(249, 115, 22, 0.2); }
.status-label.red { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

.reason-container {
    background: var(--inner-bg); border: 1px dashed var(--card-border); border-radius: 16px;
    padding: 24px; text-align: left; margin-bottom: 36px;
}
.reason-container span { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;}
.reason-container p { font-size: 0.95rem; color: var(--text-main); font-weight: 600; line-height: 1.6; margin: 0; }

/* Botões com Efeito de Impulso Premium */
.btn-sec-action {
    width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 18px; border-radius: 16px; font-weight: 800; font-size: 1.05rem; cursor: pointer;
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), filter 0.2s, box-shadow 0.2s;
    border: none; text-decoration: none; font-family: 'Manrope', sans-serif;
}
.btn-sec-action:active { transform: scale(0.95); filter: brightness(0.9); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.btn-sec-action:hover { filter: brightness(1.1); }

.btn-blue { background: var(--primary); color: #fff; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3); }
.btn-dark { background: var(--text-main); color: var(--card-bg); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }

.btn-sec-action svg { width: 22px; height: 22px; transition: transform 0.3s; }
.btn-sec-action:hover svg { transform: translateX(-4px); }
</style>

<div class="security-wrapper">
    <div class="security-card">
        
        <?php if ($isSuspended): ?>
            <!-- =======================================================
                 TELA: CUENTA SUSPENSA (LARANJA)
                 ======================================================= -->
            <div class="sec-icon-circle orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:40px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
            
            <div class="sec-greeting"><span data-i18n="hello">Hola</span>, <strong><?= htmlspecialchars($userName) ?></strong></div>
            <h1 class="sec-title" data-i18n="account_suspended">Conta Suspensa</h1>
            
            <div class="status-label orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span data-i18n="status_suspended">STATUS: SUSPENSO</span>
            </div>
            
            <div class="reason-container">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span data-i18n="reason_label">Motivo da Restrição</span>
                </span>
                <p data-i18n="reason_desc_suspended">Sua conta foi suspensa temporariamente por motivos de segurança. Todas as funcionalidades do painel foram desabilitadas pelo administrador.</p>
            </div>

            <!-- Botão de Salir (Logout) que volta pro Login de forma limpa -->
            <a href="/logout" class="btn-sec-action btn-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span data-i18n="go_login">Ir para o Login</span>
            </a>

        <?php else: ?>
            <!-- =======================================================
                 TELA: ERRO 404 NORMAL (VERMELHO)
                 ======================================================= -->
            <div class="sec-icon-circle red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:40px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            
            <h1 class="sec-title" style="font-size: 4rem; margin-bottom: 5px;">404</h1>
            <div class="sec-greeting" style="margin-bottom: 25px; color: var(--text-main);" data-i18n="page_not_found">Página não encontrada</div>
            
            <div class="reason-container">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span data-i18n="info_label">Informação de Rota</span>
                </span>
                <p data-i18n="reason_desc_404">O link que você tentou acessar não existe, foi digitado incorretamente ou foi removido do sistema.</p>
            </div>
            
            <a href="/home" class="btn-sec-action btn-dark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                <span data-i18n="go_home">Volver ao Inicio</span>
            </a>
        <?php endif; ?>

    </div>
</div>

<?php
$pageContent = ob_get_clean();

// ==========================================================================
// INJEÇÃO I18N E LÓGICA DE SINCRONIA
// ==========================================================================
$extraJs = <<<JS
<script>
// Dicionário blindado exclusivo da página 404 / Segurança
const secDict = {
    'pt': {
        'hello': 'Hola', 'account_suspended': 'Conta Suspensa', 'status_suspended': 'STATUS: SUSPENSO', 'reason_label': 'Motivo da Restrição',
        'reason_desc_suspended': 'Sua conta foi suspensa temporariamente por motivos de segurança. Todas as funcionalidades do painel foram desabilitadas.',
        'go_login': 'Ir para o Login', 'page_not_found': 'Página não encontrada', 'info_label': 'Informação de Rota',
        'reason_desc_404': 'O link que você tentou acessar não existe, foi digitado incorretamente ou foi removido do sistema.',
        'go_home': 'Volver ao Inicio'
    },
    'en': {
        'hello': 'Hello', 'account_suspended': 'Account Suspended', 'status_suspended': 'STATUS: SUSPENDED', 'reason_label': 'Restriction Reason',
        'reason_desc_suspended': 'Your account has been temporarily suspended for security reasons. All panel features have been disabled.',
        'go_login': 'Go to Login', 'page_not_found': 'Page not found', 'info_label': 'Route Information',
        'reason_desc_404': 'The link you tried to access does not exist, was typed incorrectly, or has been removed from the system.',
        'go_home': 'Back to Home'
    },
    'es': {
        'hello': 'Hola', 'account_suspended': 'Cuenta Suspendida', 'status_suspended': 'ESTADO: SUSPENDIDO', 'reason_label': 'Motivo de la Restricción',
        'reason_desc_suspended': 'Su cuenta ha sido suspendida temporalmente por razones de seguridad. Todas as funções do painel foram desabilitadas.',
        'go_login': 'Ir al Login', 'page_not_found': 'Página no encontrada', 'info_label': 'Información de Ruta',
        'reason_desc_404': 'El enlace al que intentó acceder no existe, fue escrito incorrectamente o ha sido eliminado del sistema.',
        'go_home': 'Volver al Inicio'
    }
};

function applySecurityI18n() {
    const lang = localStorage.getItem('app_language') || 'pt';
    const dict = secDict[lang] || secDict['pt'];

    // Sincroniza com o header global se ele existir na tela
    if (window.globalTranslations && !window.globalTranslations[lang]['go_login']) {
        Object.assign(window.globalTranslations[lang], secDict[lang]);
    }

    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) el.textContent = dict[key];
    });
}

// Hook para o seletor de idiomas do header
const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(originalSelectLang) originalSelectLang(langCode);
    applySecurityI18n();
};

document.addEventListener('DOMContentLoaded', applySecurityI18n);
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) {
    include $layoutFile;
} else {
    // Se falhar o layout, renderiza básico para não dar erro 500
    echo "<!DOCTYPE html><html><head><title>{$pageTitle}</title></head><body style='background:#121214; margin:0;'>";
    echo $pageContent . $extraJs;
    echo "</body></html>";
}
?>