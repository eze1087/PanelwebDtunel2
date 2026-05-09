<?php
// Proteção e Inicialização de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('DTUNNEL_APP')) { 
    // header('HTTP/1.0 403 Forbidden'); exit; 
}

// Puxa as informações da sessão para o Menu
$currentUser = [
    'email' => $_SESSION['email'] ?? 'dtunnelvpn@gmail.com',
    'role'  => $_SESSION['role'] ?? 'user'
];

$isAdmin = ($currentUser['role'] === 'admin');
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Função para verificar rota ativa
function isRouteActive($route, $currentPath) {
    return ($currentPath === $route) ? 'active' : '';
}
?>

<style>
    /* =========================================================
       VARIÁVEIS E TEMA
       ========================================================= */
    :root {
        --sidebar-bg: #ffffff;
        --sidebar-border: #e5e7eb;
        --text-main: #111827;
        --text-muted: #6b7280;
        --card-bg: #f9fafb;
        --card-border: #e5e7eb;
        --icon-color: #6b7280;
        --logo-bg: #f3f4f6;
        --logo-text: #111827;
        --sidebar-width: 280px;
    }

    .dark {
        --sidebar-bg: #121214; 
        --sidebar-border: #27272a;
        --text-main: #ffffff;
        --text-muted: #a1a1aa; 
        --card-bg: #1a1a1e;
        --card-border: #27272a;
        --icon-color: #a1a1aa; 
        --logo-bg: #2a2a2e;
        --logo-text: #ffffff;
    }

    /* =========================================================
       ESTRUTURA BASE E OVERLAY
       ========================================================= */
    a, button, .nav-group-label, .nav-link-custom, .sidebar-overlay, .btn-logout-custom {
        -webkit-tap-highlight-color: transparent !important;
        outline: none;
    }

    .sidebar-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(2px);
        z-index: 9998; 
        opacity: 0; visibility: hidden; pointer-events: none; 
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1; visibility: visible; pointer-events: auto; cursor: pointer;
    }

    .sidebar {
        background-color: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        display: flex; flex-direction: column;
        height: 100vh; width: var(--sidebar-width);
        position: fixed; top: 0; left: 0; 
        z-index: 9999; overflow: hidden;
        transform: translateX(-100%); 
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease, border-color 0.3s ease;
        box-shadow: 10px 0 30px rgba(0,0,0,0.5);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    /* =========================================================
       CABEÇALHO DO MENU (LOGO E BOTÃO X)
       ========================================================= */
    .sidebar-header-custom {
        padding: 24px 20px 20px 20px; display: flex; align-items: center; gap: 14px; position: relative; z-index: 10;
    }
    .dt-logo-box {
        width: 44px; height: 44px; background: var(--logo-bg); border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: var(--logo-text); font-size: 1.15rem; letter-spacing: 0.5px;
    }
    .sidebar-header-text b { display: block; font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px; }
    .sidebar-header-text span { font-size: 0.75rem; font-weight: 500; color: var(--text-muted); }
    
    .btn-close-sidebar {
        margin-left: auto; background: var(--icon-bg); border: 1px solid var(--card-border);
        color: var(--text-muted); cursor: pointer; width: 34px; height: 34px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        position: relative; z-index: 10005;
    }
    .btn-close-sidebar:active { transform: scale(0.85); background: var(--card-border); color: var(--text-main); }
    .btn-close-sidebar svg { width: 16px; height: 16px; pointer-events: none; }

    /* =========================================================
       CORPO DO MENU
       ========================================================= */
    .sidebar-nav-custom {
        flex: 1; padding: 10px 16px; overflow-y: auto; overflow-x: hidden;
        scrollbar-width: none; 
    }
    .sidebar-nav-custom::-webkit-scrollbar { display: none; }

    .nav-group-label {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; color: var(--text-muted); border-radius: 12px;
        font-size: 0.95rem; font-weight: 600; cursor: pointer; user-select: none;
        transition: background 0.2s, color 0.2s, transform 0.15s; margin-bottom: 4px;
    }
    .nav-group-label-left { display: flex; align-items: center; gap: 14px; }
    .nav-group-label-left svg { width: 20px; height: 20px; flex-shrink: 0; color: var(--icon-color); pointer-events: none; }

    .nav-link-custom {
        display: flex; align-items: center; gap: 14px; padding: 14px 16px;
        color: var(--text-muted); background: transparent; border-radius: 12px;
        text-decoration: none; font-size: 0.95rem; font-weight: 600;
        transition: background 0.2s, color 0.2s, transform 0.15s; margin-bottom: 4px;
    }
    
    .nav-link-custom:active, .nav-group-label:active { transform: scale(0.96); background: var(--icon-bg); }
    
    .nav-link-custom.active { color: var(--text-main); background: rgba(255,255,255,0.05); }
    .nav-link-custom.active svg { color: var(--text-main); stroke-width: 2.5; }
    
    .nav-link-custom svg { width: 20px; height: 20px; flex-shrink: 0; color: var(--icon-color); transition: color 0.3s; pointer-events: none; }
    
    .nav-chevron-icon { width: 16px !important; height: 16px !important; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; color: var(--icon-color); pointer-events: none; }
    .rotate-chevron { transform: rotate(180deg); }

    /* =========================================================
       DIVISÓRIA INTERNA DA SANFONA
       ========================================================= */
    .submenu-container {
        max-height: 0; overflow: hidden; 
        margin-left: 26px; 
        padding-left: 12px;
        border-left: 1px solid rgba(255, 255, 255, 0.05); 
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    :root:not(.dark) .submenu-container { border-left-color: rgba(0, 0, 0, 0.05); }
    .submenu-container.open { max-height: 600px; margin-bottom: 8px; margin-top: 4px;}
    .submenu-container .nav-link-custom { padding: 12px 14px; font-size: 0.9rem; }
    .submenu-container .nav-link-custom svg { width: 18px; height: 18px; }

    /* =========================================================
       RODAPÉ DO MENU E BOTÃO DE SAIR
       ========================================================= */
    .sidebar-footer-custom { padding: 20px; border-top: 1px solid var(--sidebar-border); background-color: var(--sidebar-bg); }
    .user-session-card { background: transparent; border: 1px solid var(--card-border); border-radius: 12px; padding: 14px; margin-bottom: 12px; }
    .user-session-card span { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
    .user-session-card b { font-size: 0.85rem; font-weight: 700; color: var(--text-main); word-break: break-all; }
    
    .btn-logout-custom {
        width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px;
        background: transparent; border: 1px solid var(--card-border); border-radius: 12px;
        color: var(--text-main); font-size: 0.95rem; font-weight: 700; cursor: pointer;
        transition: transform 0.15s, background 0.2s; outline: none;
    }
    .btn-logout-custom:active { transform: scale(0.94); background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }
    .btn-logout-custom svg { color: inherit; width: 20px; height: 20px; pointer-events: none; }
</style>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="window.fecharMenuMaster()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header-custom">
        <div class="dt-logo-box">DT</div>
        <div class="sidebar-header-text">
            <b>By Elnene Panel WEB2</b>
            <span data-i18n="control_center">Control Center</span>
        </div>
        <button type="button" class="btn-close-sidebar" title="Cerrar Menu" onclick="window.fecharMenuMaster()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="sidebar-nav-custom">
        <a href="/home" class="nav-link-custom <?= isRouteActive('/home', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span data-i18n="home">Home</span>
        </a>

        <div class="nav-group-label" onclick="toggleAccordion('acc-config', 'chev-config')">
            <div class="nav-group-label-left">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                <span data-i18n="settings">Configuraciones</span>
            </div>
            <svg id="chev-config" class="nav-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="submenu-container" id="acc-config">
            <a href="/home-config" class="nav-link-custom <?= isRouteActive('/home-config', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21v-7m0-4V3m8 18v-9m0-4V3m8 18v-5m0-4V3M1 14h6m2-6h6m2 8h6"/></svg>
                <span data-i18n="home">Home</span>
            </a>
            <a href="/categorias" class="nav-link-custom <?= isRouteActive('/categorias', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                <span data-i18n="categories">Categorías</span>
            </a>
            
            <a href="/usuarios-associados" class="nav-link-custom <?= isRouteActive('/usuarios-associados', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span data-i18n="associated_users">Usuarios associados</span>
            </a>

            <a href="/cdn" class="nav-link-custom <?= isRouteActive('/cdn', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
                <span data-i18n="cdn">CDN</span>
            </a>
        </div>

        <div class="nav-group-label" onclick="toggleAccordion('acc-app', 'chev-app')">
            <div class="nav-group-label-left">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                <span data-i18n="app">Aplicación</span>
            </div>
            <svg id="chev-app" class="nav-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="submenu-container" id="acc-app">
            <a href="/aplicativo" class="nav-link-custom <?= isRouteActive('/aplicativo', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                <span data-i18n="home">Home</span>
            </a>
            <a href="/gerar-apk" class="nav-link-custom <?= isRouteActive('/gerar-apk', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                <span data-i18n="generate_apk">Generar APK</span>
            </a>
            <a href="/temas" class="nav-link-custom <?= isRouteActive('/temas', $currentPath) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>
                <span data-i18n="community_themes">Temas da comunidade</span>
            </a>
        </div>

        <a href="/textos" class="nav-link-custom <?= isRouteActive('/textos', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
            <span data-i18n="texts">Textos</span>
        </a>
        
        <a href="/renovar" class="nav-link-custom <?= isRouteActive('/renovar', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            <span data-i18n="renew">Renovar</span>
        </a>
        
        <a href="/transacoes" class="nav-link-custom <?= isRouteActive('/transacoes', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <span data-i18n="transactions">Transacciones</span>
        </a>

        <div style="height: 1px; background: var(--sidebar-border); margin: 12px 16px;"></div>

        <?php if ($isAdmin): ?>
        <a href="/usuarios" class="nav-link-custom <?= isRouteActive('/usuarios', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="19" cy="11" r="2"/><path d="M19 8v1"/><path d="M19 13v1"/><path d="m21.6 9.5-.87.5"/><path d="m17.27 12-.87.5"/><path d="m21.6 12.5-.87-.5"/><path d="m17.27 10-.87-.5"/></svg>
            <span data-i18n="user_management">Gestión de usuarios</span>
        </a>
        <?php endif; ?>

        <a href="/notificacoes" class="nav-link-custom <?= isRouteActive('/notificacoes', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span data-i18n="notifications">Notificaciones</span>
        </a>
        
        <a href="/dispositivos" class="nav-link-custom <?= isRouteActive('/dispositivos', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span data-i18n="devices">Dispositivos</span>
        </a>
        
        <a href="/sessoes" class="nav-link-custom <?= isRouteActive('/sessoes', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
            <span data-i18n="sessions">Sesiones</span>
        </a>
        
        <a href="/perfil" class="nav-link-custom <?= isRouteActive('/perfil', $currentPath) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span data-i18n="profile">Perfil</span>
        </a>
    </div>

    <div class="sidebar-footer-custom">
        <div class="user-session-card">
            <span data-i18n="active_session">Sesión ativa</span>
            <b><?= htmlspecialchars($currentUser['email']) ?></b>
        </div>

        <!-- Datos de contacto -->
        <div style="padding:10px 0 8px 0;text-align:center;border-top:1px solid var(--sidebar-border);margin-top:8px;">
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:6px;font-weight:600;letter-spacing:.4px;">CONTACTO / SOPORTE</div>
            <div style="display:flex;gap:8px;justify-content:center;">
                <a href="https://wa.me/543455236886" target="_blank"
                   style="display:flex;align-items:center;gap:4px;font-size:.75rem;color:#25d366;text-decoration:none;font-weight:600;background:rgba(37,211,102,.08);padding:4px 10px;border-radius:8px;border:1px solid rgba(37,211,102,.2);">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    +54 3455-236886
                </a>
                <a href="https://t.me/El_NeNe_Sando" target="_blank"
                   style="display:flex;align-items:center;gap:4px;font-size:.75rem;color:#229ed9;text-decoration:none;font-weight:600;background:rgba(34,158,217,.08);padding:4px 10px;border-radius:8px;border:1px solid rgba(34,158,217,.2);">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px;"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    @El_NeNe_Sando
                </a>
            </div>
        </div>
        
        <form action="/logout" method="POST" style="margin: 0;">
            <button type="submit" class="btn-logout-custom" onclick="this.closest('form').submit();">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span data-i18n="logout">Salir</span>
            </button>
        </form>
    </div>
</aside>

<script>
    // ======================================================================
    // FUNÇÃO GLOBAL DESTRUTIVA PARA FECHAR O MENU (SEM BLOQUEIOS)
    // ======================================================================
    window.fecharMenuMaster = function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');

        // Lógica da Arapuca (Proteção do home.php)
        if (typeof window.currentLockState !== 'undefined' && window.currentLockState === 'blocked') {
            if (typeof window.triggerUltimateLockout === 'function') {
                setTimeout(() => {
                    window.currentLockState = null; 
                    window.triggerUltimateLockout('blocked'); 
                }, 300); // Dá tempo pro menu fechar visualmente antes de piscar a trava
            }
        }
    };

    // Função de Abertura chamada pelo Header
    window.forceOpenSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
    };

    function toggleAccordion(id, chevId) {
        const submenu = document.getElementById(id);
        const chevron = document.getElementById(chevId);
        if (submenu && chevron) {
            submenu.classList.toggle('open');
            chevron.classList.toggle('rotate-chevron');
        }
    }

    // ======================================================================
    // EVENT LISTENERS DOMContentLoaded
    // ======================================================================
    document.addEventListener('DOMContentLoaded', () => {
        
        // 1. Mantém a sanfona correta aberta
        document.querySelectorAll('.submenu-container').forEach(sub => {
            if (sub.querySelector('.nav-link-custom.active')) {
                sub.classList.add('open');
                const chev = document.querySelector(`[onclick*="${sub.id}"] .nav-chevron-icon`);
                if(chev) chev.classList.add('rotate-chevron');
            }
        });

        // 2. Cerrar ao clicar em qualquer link interno
        document.querySelectorAll('.nav-link-custom').forEach(link => {
            link.addEventListener('click', (e) => {
                if (link.getAttribute('href') === '/logout') {
                    window.fecharMenuMaster();
                    return true;
                }

                if (typeof window.currentLockState !== 'undefined' && window.currentLockState === 'blocked') {
                    e.preventDefault();
                    window.fecharMenuMaster();
                    return false;
                }
                
                window.fecharMenuMaster();
            });
        });

        // 3. Lógica de Deslize (Swipe) na Tela Inteira para fechar o Menu
        let touchstartX = 0;
        let touchendX = 0;

        const sidebarEl = document.getElementById('sidebar');
        if (sidebarEl) {
            sidebarEl.addEventListener('touchstart', function(event) {
                touchstartX = event.changedTouches[0].screenX;
            }, {passive: true});

            sidebarEl.addEventListener('touchend', function(event) {
                touchendX = event.changedTouches[0].screenX;
                if (touchendX < touchstartX - 50) { 
                    window.fecharMenuMaster();
                }
            }, {passive: true}); 
        }
    });
</script>
