<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Central de Segurança e Roteamento (404 / Suspended)
 * @description Página de destino para rotas não encontradas ou bloqueios de segurança.
 * Oculta navegação lateral e prende o usuário até a resolução do status.
 * =======================================================================================
 */

// Proteção estrita do arquivo e inicialização de sessão
if (!defined('DTUNNEL_APP')) { 
    header('HTTP/1.0 403 Forbidden'); 
    exit; 
}

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// ----------------------------------------------------------------------
// 1. VERIFICAÇÃO EM TEMPO REAL DE STATUS NO BANCO DE DADOS (JSON)
// ----------------------------------------------------------------------
$dbFile = __DIR__ . '/../db/usuarios.json';
clearstatcache(true, $dbFile);

$sessionEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['username'] ?? 'Visitante';
$userEstado = 'active';

// Analisa se o email está presente e busca os dados atuais
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

// Se o usuário for o Admin Master, ele nunca fica suspenso
if (strtolower($sessionEmail) === 'elnene.admin@gmail.com') {
    $userEstado = 'active';
}

// Define o contexto da página baseado na inteligência do BD
$isSuspended = ($userEstado === 'suspended');
$pageTitle = $isSuspended ? 'Conta Suspensa' : 'Página Não Encontrada';

// Inicia o buffer para encapsular o conteúdo no layout principal sem quebrar o HTTP
ob_start();
?>

<style>
/* 1. OCULTA TODOS OS ELEMENTOS DE NAVEGAÇÃO PROIBIDOS */
aside.sidebar, #sidebar, 
.sidebar-overlay, #sidebarOverlay,
button[onclick*="forceOpenSidebar"], .btn-open-sidebar,
.header-avatar, .account-menu, #account-menu-dropdown { 
    display: none !important; 
    pointer-events: none !important;
}

/* 2. REAJUSTA O ESPAÇO (Para a tela ficar centralizada sem a sidebar) */
.main-content, #main-wrapper, .home-wrapper {
    margin-left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    padding-top: 10px !important;
}

/* 3. VARIÁVEIS DE TEMA DINÂMICO (CLARO / ESCURO) */
.security-wrapper {
    --card-bg: #ffffff; 
    --card-border: #e5e7eb; 
    --text-main: #111827; 
    --text-muted: #6b7280; 
    --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; 
    --icon-bg: #f3f4f6; 
    --primary: #3b82f6; 
    
    /* Colores de Alerta (Suspensão e Erro) */
    --orange-bg: rgba(249, 115, 22, 0.1); 
    --orange-border: rgba(249, 115, 22, 0.3); 
    --orange-text: #f97316;
    
    --red-bg: rgba(239, 68, 68, 0.1); 
    --red-border: rgba(239, 68, 68, 0.3); 
    --red-text: #ef4444;

    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center;
    min-height: calc(100vh - 140px); 
    padding: 20px; 
    font-family: 'Manrope', system-ui, sans-serif;
    animation: fadeIn 0.4s ease-out forwards;
}

:root.dark .security-wrapper, .dark .security-wrapper, body.dark .security-wrapper {
    --card-bg: #1a1a1e; 
    --card-border: #27272a; 
    --text-main: #f9fafb; 
    --text-muted: #a1a1aa; 
    --text-subtle: #71717a;
    --inner-bg: #121214; 
    --icon-bg: rgba(255, 255, 255, 0.05);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

.security-wrapper * { 
    -webkit-tap-highlight-color: transparent !important; 
    outline: none; 
}

/* 4. CARD PRINCIPAL ESTILO HOME */
.security-main-card {
    background: var(--card-bg); 
    border: 1px solid var(--card-border); 
    border-radius: 24px;
    padding: 48px 32px; 
    text-align: center; 
    max-width: 460px; 
    width: 100%;
    box-shadow: 0 20px 50px rgba(0,0,0,0.05); 
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s, background-color 0.3s, border-color 0.3s;
}
.security-main-card:hover {
    box-shadow: 0 25px 60px rgba(0,0,0,0.08); 
}
.dark .security-main-card { 
    box-shadow: 0 30px 70px rgba(0,0,0,0.5); 
}

/* Ícone Animado no Topo */
.sec-icon-container {
    width: 88px; height: 88px; border-radius: 50%; 
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px auto; 
    border: 5px solid var(--card-bg); 
    box-shadow: 0 0 0 1px var(--card-border);
    position: relative;
    transition: all 0.3s;
}
.sec-icon-container.orange { background: var(--orange-bg); color: var(--orange-text); }
.sec-icon-container.red { background: var(--red-bg); color: var(--red-text); }

/* Pulso em volta do ícone se estiver suspenso */
.sec-icon-container.orange::after {
    content: ''; position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px;
    border-radius: 50%; border: 2px solid var(--orange-text);
    animation: pulseAlert 2s infinite cubic-bezier(0.4, 0, 0.2, 1); opacity: 0;
}
@keyframes pulseAlert {
    0% { transform: scale(0.9); opacity: 0.8; }
    100% { transform: scale(1.3); opacity: 0; }
}

/* Textos e Títulos */
.sec-greeting { 
    font-size: 1.15rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; 
}
.sec-greeting strong {
    color: var(--text-main); font-weight: 800;
}
.sec-title { 
    font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; 
    line-height: 1.1; letter-spacing: -0.5px; 
}

/* Badge de Estado */
.status-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 18px; border-radius: 50px; font-size: 0.75rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 32px;
}
.status-badge.orange { background: var(--orange-bg); color: var(--orange-text); border: 1px solid var(--orange-border); }
.status-badge.red { background: var(--red-bg); color: var(--red-text); border: 1px solid var(--red-border); }

/* Caixa de Detalhes (Motivo da Suspensão / Informação 404) */
.reason-box {
    background: var(--inner-bg); border: 1px dashed var(--card-border); border-radius: 16px;
    padding: 24px; text-align: left; margin-bottom: 36px; transition: all 0.3s;
}
.reason-box .reason-label { 
    display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; 
    color: var(--text-subtle); text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; 
}
.reason-box p { 
    font-size: 0.95rem; color: var(--text-main); font-weight: 600; line-height: 1.6; margin: 0; 
}

/* Botões com Efeito de Impulso Premium */
.btn-impulse {
    width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 18px; border-radius: 16px; font-weight: 800; font-size: 1.05rem; 
    border: none; cursor: pointer; transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), filter 0.2s, box-shadow 0.2s;
    text-decoration: none; font-family: 'Manrope', sans-serif;
}
.btn-impulse:active { 
    transform: scale(0.95); filter: brightness(0.9); box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.btn-impulse:hover { 
    filter: brightness(1.1); 
}

.btn-primary { 
    background: var(--primary); color: #fff; 
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}
.btn-dark { 
    background: var(--text-main); color: var(--card-bg); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.btn-impulse svg { width: 22px; height: 22px; transition: transform 0.3s; }
.btn-impulse:hover svg { transform: translateX(-4px); }
</style>

<div class="security-wrapper">
    <div class="security-main-card">
        
        <?php if ($isSuspended): ?>
            <div class="sec-icon-container orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:40px;height:40px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
            
            <div class="sec-greeting">
                <span data-i18n="hello">Hola</span>, <strong><?= htmlspecialchars($userName) ?></strong>
            </div>
            
            <h1 class="sec-title" data-i18n="account_suspended">Conta Suspensa</h1>
            
            <div class="status-badge orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;height:14px;"><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span data-i18n="status_suspended">STATUS: SUSPENSO</span>
            </div>
            
            <div class="reason-box">
                <div class="reason-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span data-i18n="reason_label">Detalhes da Restrição</span>
                </div>
                <p data-i18n="reason_desc_suspended">Sua conta foi suspensa temporariamente por motivos de segurança. O acesso à rede e aos recursos do painel foi completamente desabilitado.</p>
            </div>

            <button class="btn-impulse btn-primary" onclick="window.location.href='/logout'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span data-i18n="go_login">Ir para o Login</span>
            </button>

        <?php else: ?>
            <div class="sec-icon-container red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            
            <h1 class="sec-title" style="font-size: 4.5rem; margin-bottom: 4px;">404</h1>
            <div class="sec-greeting" style="margin-bottom: 24px; color: var(--text-main);" data-i18n="page_not_found">Página não encontrada</div>
            
            <div class="reason-box">
                <div class="reason-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span data-i18n="info_label">Informação de Rota</span>
                </div>
                <p data-i18n="reason_desc_404">Desculpe, o link que você acessou pode estar quebrado, digitado incorretamente ou a página foi removida do sistema.</p>
            </div>
            
            <a href="/home" class="btn-impulse btn-dark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                <span data-i18n="go_home">Volver para o Inicio</span>
            </a>
        <?php endif; ?>

    </div>
</div>

<?php
$pageContent = ob_get_clean();

// ==========================================================================
// INJEÇÃO DO DICIONÁRIO E SINCRONIZAÇÃO I18N
// ==========================================================================
$extraJs = <<<JS
<script>
const secTranslations = {
    'pt': {
        'hello': 'Hola',
        'account_suspended': 'Conta Suspensa',
        'status_suspended': 'STATUS: SUSPENSO',
        'reason_label': 'Detalhes da Restrição',
        'reason_desc_suspended': 'Sua conta foi suspensa temporariamente por motivos de segurança. O acesso à rede e aos recursos do painel foi completamente desabilitado.',
        'go_login': 'Ir para o Login',
        'page_not_found': 'Página não encontrada',
        'info_label': 'Informação de Rota',
        'reason_desc_404': 'Desculpe, o link que você acessou pode estar quebrado, digitado incorretamente ou a página foi removida do sistema.',
        'go_home': 'Volver para o Inicio'
    },
    'en': {
        'hello': 'Hello',
        'account_suspended': 'Account Suspended',
        'status_suspended': 'STATUS: SUSPENDED',
        'reason_label': 'Restriction Details',
        'reason_desc_suspended': 'Your account has been temporarily suspended for security reasons. Access to the network and panel features has been completely disabled.',
        'go_login': 'Go to Login',
        'page_not_found': 'Page not found',
        'info_label': 'Route Information',
        'reason_desc_404': 'Sorry, the link you accessed may be broken, typed incorrectly, or the page has been removed from the system.',
        'go_home': 'Back to Home'
    },
    'es': {
        'hello': 'Hola',
        'account_suspended': 'Cuenta Suspendida',
        'status_suspended': 'ESTADO: SUSPENDIDO',
        'reason_label': 'Detalles de la Restricción',
        'reason_desc_suspended': 'Su cuenta ha sido suspendida temporalmente por razones de seguridad. El acceso a la red y a las funciones del panel ha sido completamente deshabilitado.',
        'go_login': 'Ir al Login',
        'page_not_found': 'Página no encontrada',
        'info_label': 'Información de Ruta',
        'reason_desc_404': 'Lo sentimos, el enlace al que accedió puede estar roto, escrito incorrectamente o la página ha sido eliminada del sistema.',
        'go_home': 'Volver al Inicio'
    }
};

// Aplica a tradução com base no localStorage do usuário
function updateSecurityI18n() {
    const currentLang = localStorage.getItem('app_language') || 'pt';
    
    // Mescla silenciosamente se o Header já tiver carregado o objeto global
    if (window.globalTranslations) {
        for (let lang in secTranslations) {
            if (!window.globalTranslations[lang]) window.globalTranslations[lang] = {};
            Object.assign(window.globalTranslations[lang], secTranslations[lang]);
        }
    }

    const dict = secTranslations[currentLang] || secTranslations['pt'];
    
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });
}

// Hook de segurança para interceptar cliques de troca de idioma na barra superior
const oldSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(oldSelectLang) oldSelectLang(langCode);
    updateSecurityI18n();
};

document.addEventListener('DOMContentLoaded', () => {
    updateSecurityI18n();
});
</script>
JS;

// ----------------------------------------------------------------------
// RENDERIZAÇÃO INFALÍVEL
// ----------------------------------------------------------------------
// Garante que o layout.php receba o conteúdo, se ele não existir printa diretamente
$layoutPath = __DIR__ . '/../includes/layout.php'; 
if(file_exists($layoutPath)) {
    include $layoutPath;
} else if(file_exists(__DIR__ . '/layout.php')) {
    include __DIR__ . '/layout.php';
} else {
    // Fallback de emergência (Resolve o erro net::ERR_HTTP_RESPONSE_CODE_FAILURE)
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>{$pageTitle}</title></head><body style='margin:0; background:#121214;'>";
    echo $pageContent;
    echo $extraJs;
    echo "</body></html>";
}
?>