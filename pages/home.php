<?php
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }

// Inicia sessão caso o index não tenha iniciado
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ----------------------------------------------------------------------
// 0. AÇÃO: VOLTAR PARA ADMIN (QUANDO IMPERSONANDO UM USUÁRIO)
// ----------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'return_admin' && isset($_SESSION['admin_return_email'])) {
    $_SESSION['email'] = $_SESSION['admin_return_email'];
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = 'Administrador';
    unset($_SESSION['admin_return_email']); // Limpa o rastro
    header('Location: /usuarios');
    exit;
}

$isImpersonating = isset($_SESSION['admin_return_email']);

// ----------------------------------------------------------------------
// 1. LEITURA EM TEMPO REAL DO db/usuarios.json (SEGURANÇA MÁXIMA & CACHE CLEAR)
// ----------------------------------------------------------------------
$dbFile = __DIR__ . '/../db/usuarios.json';
clearstatcache(true, $dbFile); // Força o PHP a não usar cache do arquivo

$freshUserData = [];
$userFoundInDB = false;
$sessionEmail = $_SESSION['email'] ?? 'dtunnelvpn@gmail.com';
$isMasterAdmin = (strtolower($sessionEmail) === 'elnene.admin@gmail.com');

if (file_exists($dbFile)) {
    $usuarios = json_decode(file_get_contents($dbFile), true) ?: [];
    foreach ($usuarios as $u) {
        if (strtolower($u['email']) === strtolower($sessionEmail)) {
            $freshUserData = $u;
            $userFoundInDB = true;
            break;
        }
    }
}

// Mescla os dados da Sesión com os dados fresquinhos do JSON. 
// O JSON sempre tem prioridade absoluta para sobrescrever dias e status.
$currentUser = array_merge(
    [
        'id' => $_SESSION['user_id'] ?? 0, 
        'email' => $sessionEmail, 
        'role' => $_SESSION['role'] ?? 'user',
        'username' => $_SESSION['username'] ?? 'Usuario',
        'status' => 'active',
        'expires_at' => null
    ],
    $freshUserData
);

// Define se é admin antecipadamente para usar nas contagens
$isAdmin = ($currentUser['role'] === 'admin' || $isMasterAdmin);

// ----------------------------------------------------------------------
// LEITURA DOS ARQUIVOS JSON PARA CUENTAGEM (SISTEMA TREM BALA)
// ----------------------------------------------------------------------
$dbConfigs      = __DIR__ . '/../db/configs.json';
$dbCategories   = __DIR__ . '/../db/categories.json';
$dbDispositivos = __DIR__ . '/../db/dispositivos.json';

// Contagem de Configuraciones
$totalConfigs = 0;
if (file_exists($dbConfigs)) {
    $configs = json_decode(file_get_contents($dbConfigs), true) ?: [];
    foreach ($configs as $c) {
        if (isset($c['user_email']) && strtolower($c['user_email']) === strtolower($sessionEmail)) {
            $totalConfigs++;
        }
    }
}

// Contagem de Categorías
$totalCategories = 0;
if (file_exists($dbCategories)) {
    $categories = json_decode(file_get_contents($dbCategories), true) ?: [];
    foreach ($categories as $c) {
        if (isset($c['user_email']) && strtolower($c['user_email']) === strtolower($sessionEmail)) {
            $totalCategories++;
        }
    }
}

// Contagem de Dispositivos (Respeitando a permissão do Admin)
$totalDevices = 0;
if (file_exists($dbDispositivos)) {
    $dispositivos = json_decode(file_get_contents($dbDispositivos), true) ?: [];
    foreach ($dispositivos as $d) {
        // Se for admin, conta tudo. Se não for, conta só os do app do usuário logado.
        if ($isAdmin || (isset($d['owner_email']) && $d['owner_email'] === $sessionEmail)) {
            $totalDevices++;
        }
    }
}

// ----------------------------------------------------------------------
// 2. LÓGICA SUPREMA DE EXPIRAÇÃO E VITALÍCIO (PRECISÃO MILIMÉTRICA)
// ----------------------------------------------------------------------
$daysLeft = 0;
$expiresAtStr = 'Ilimitado';

if ($isAdmin) {
    $daysLeft = 999999;
    $expiresAtStr = 'VITALÍCIO';
} else {
    if (!empty($currentUser['expires_at'])) {
        $now = new DateTime();
        $expires = new DateTime($currentUser['expires_at']);
        
        if ($expires > $now) {
            $diff = $now->diff($expires);
            $daysLeft = $diff->days;
            // Se falta menos de 24h, mas ainda não virou o dia, garante que marque 1 dia
            if ($daysLeft == 0 && ($diff->h > 0 || $diff->i > 0)) {
                $daysLeft = 1; 
            }
            
            // Tratamento para Vitalicio
            if ($daysLeft > 3650) { 
                $expiresAtStr = 'VITALÍCIO';
                $daysLeft = 999999;
            } else {
                $expiresAtStr = $expires->format('d/m/Y');
            }
        } else {
            // Conta vencida
            $daysLeft = 0;
            $expiresAtStr = $expires->format('d/m/Y');
        }
    }
}

// ======================================================================
// 3. GATILHOS DE TRAVAMENTO & ENDPOINT "TREM BALA" (REAL-TIME SEM RELOAD)
// ======================================================================
$isDeleted   = (!$isMasterAdmin && !$userFoundInDB); 
$isBlocked   = (!$isMasterAdmin && isset($currentUser['status']) && $currentUser['status'] === 'blocked');
$isSuspended = (!$isMasterAdmin && isset($currentUser['status']) && $currentUser['status'] === 'suspended');
$isExpired   = (!$isAdmin && $daysLeft <= 0);
$sessionError = $_GET['error'] ?? null; 

$showSessionModal   = ($isDeleted || $sessionError);
$showBlockedModal   = (!$showSessionModal && $isBlocked);
$showSuspendedModal = (!$showSessionModal && !$showBlockedModal && $isSuspended);
$showExpiredModal   = (!$showSessionModal && !$showBlockedModal && !$showSuspendedModal && $isExpired);

// Se o Javascript estiver perguntando o status em tempo real, responde JSON e morre aqui!
if (isset($_GET['realtime_check'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'deleted' => $showSessionModal,
        'blocked' => $showBlockedModal,
        'suspended' => $showSuspendedModal,
        'expired' => $showExpiredModal
    ]);
    exit;
}

// ----------------------------------------------------------------------
// 4. CORES INTELIGENTES DA BADGE (Sincronizado perfeitamente com $daysLeft)
// ----------------------------------------------------------------------
if ($isAdmin || $daysLeft > 2) {
    $badgeClass = 'badge-success';
    $badgeIcon = '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>';
    $badgeI18n = 'status_stable';
} elseif ($daysLeft > 0) {
    $badgeClass = 'badge-warning';
    $badgeIcon = '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>';
    $badgeI18n = 'status_expiring';
} else {
    $badgeClass = 'badge-danger';
    $badgeIcon = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
    $badgeI18n = 'status_expired';
}

$createdAt = isset($currentUser['created_at']) ? date('d/m/Y', strtotime($currentUser['created_at'])) : date('d/m/Y');
$updatedAt = isset($currentUser['updated_at']) ? date('d/m/Y', strtotime($currentUser['updated_at'])) : $createdAt;

$userName = $currentUser['username'] ?? 'Usuario';
$userEmail = $currentUser['email'] ?? '';
$roleName = $isAdmin ? 'Administrador' : 'Usuario';

$pageTitle = 'Panel principal';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ======================================================================
   ESTILOS PREMIUM SWEETALERT2 (Design Idêntico ao Print - Clean & Quadrado)
   ====================================================================== */
.swal-modal-custom {
    background: var(--card-bg) !important;
    border: 1px solid var(--card-border) !important;
    border-radius: 20px !important;
    padding: 24px !important;
    width: 90% !important;
    max-width: 440px !important;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important;
}

.swal2-html-container { margin: 0 !important; overflow: hidden !important; text-align: left !important; }

/* Cabeçalho Customizado (Ícone + Textos alinhados lateralmente igual ao print) */
.swal-header-custom { 
    display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; text-align: left; 
}
.swal-icon-custom {
    width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.swal-icon-custom svg { width: 24px; height: 24px; stroke-width: 2.5; }
.swal-icon-custom.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.swal-icon-custom.danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.swal-icon-custom.orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }
.swal-icon-custom.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

.swal-header-text { display: flex; flex-direction: column; gap: 4px; }
.swal-title-custom { 
    font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2; font-family: 'Manrope', sans-serif;
}
.swal-desc-custom { 
    font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin: 0; line-height: 1.5; font-family: 'Manrope', sans-serif;
}

/* Caixa Cinza de Informações Extra (Idêntica ao Print) */
.swal-infobox-custom {
    background: var(--inner-bg); border-radius: 14px; padding: 16px; color: var(--text-main); 
    text-align: left; margin-bottom: 24px; border: 1px solid var(--card-border);
}
.swal-infobox-custom span { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; line-height: 1.5;}
.swal-infobox-custom strong { font-size: 0.95rem; color: var(--text-main); font-weight: 700; word-break: break-all;}

/* Botões (Idêntico ao print) */
.swal2-actions { width: 100% !important; margin-top: 0 !important; }
.swal-btn-confirm {
    width: 100% !important; border-radius: 12px !important; padding: 14px !important; font-weight: 700 !important; 
    font-size: 1rem !important; display: flex !important; align-items: center !important; justify-content: center !important; 
    gap: 8px !important; outline: none !important; box-shadow: none !important; border: none !important; cursor: pointer !important;
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), filter 0.2s !important;
    font-family: 'Manrope', sans-serif !important; letter-spacing: 0.3px;
}
.swal-btn-confirm:active { transform: scale(0.96) !important; filter: brightness(0.9) !important; }
.swal-btn-confirm:hover { filter: brightness(1.1) !important; }

/* Colores Absolutas dos Botões */
.swal-btn-primary { background: var(--primary) !important; color: #fff !important; }
.swal-btn-danger { background: #ef4444 !important; color: #fff !important; }
.swal-btn-orange { background: #f97316 !important; color: #fff !important; }

/* CLASSE DE TRAVA CEGA */
body.locked-system { overflow: hidden !important; }
body.locked-system .app-header, body.locked-system .sidebar, body.locked-system .sidebar-overlay, body.locked-system #main-wrapper {
    pointer-events: none !important; user-select: none !important; filter: blur(5px) grayscale(30%) !important; opacity: 0.4 !important; transition: all 0.3s ease-in-out;
}

/* * VARIÁVEIS DE TEMA GERAL */
.home-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --icon-bg: #f3f4f6; --icon-color: #4b5563; --link-color: #111827;
    --primary: #3b82f6; --danger: #ef4444; --success: #10b981; --warning: #f59e0b;
}

:root.dark .home-wrapper, .dark .home-wrapper, body.dark .home-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: rgba(255, 255, 255, 0.02); --icon-bg: rgba(255, 255, 255, 0.03); --icon-color: #e4e4e7; --link-color: #f9fafb;
}

.home-wrapper { padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif; position: relative; transition: all 0.3s;}
a, button, .module-card, .date-card, .main-card, .stat-item { -webkit-tap-highlight-color: transparent !important; outline: none; }

/* -- BADGES DINÂMICOS INTELIGENTES -- */
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 20px; text-transform: uppercase; }
.badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
.badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
.badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

/* -- CARD PRINCIPAL -- */
.main-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 24px; transition: transform 0.15s, background 0.3s, border-color 0.3s; }
.main-card:active { transform: scale(0.98); }
.welcome-title { color: var(--text-main); font-size: 1.8rem; font-weight: 800; margin: 0 0 8px 0; }
.welcome-desc { color: var(--text-muted); font-size: 0.95rem; margin: 0 0 24px 0; font-weight: 500; }
.stats-list { display: flex; flex-direction: column; gap: 8px; }
.stat-item { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; transition: transform 0.15s; }
.stat-item:active { transform: scale(0.97); }
.s-label { font-size: 0.65rem; font-weight: 700; color: var(--text-subtle); letter-spacing: 1px; text-transform: uppercase; }
.s-value { font-size: 1rem; font-weight: 800; color: var(--text-main); }
.s-vitalicio { color: #10b981; font-family: 'Space Grotesk', monospace; letter-spacing: 1px; }
.s-expirado { color: #ef4444; }

/* -- CARDS DE DATAS -- */
.date-cards-grid { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 32px; }
.date-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 16px; transition: transform 0.15s; }
.date-card:active { transform: scale(0.96); }
.dc-icon { width: 48px; height: 48px; background: var(--icon-bg); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--icon-color); flex-shrink: 0; }
.dc-content { display: flex; flex-direction: column; gap: 4px; }
.dc-label { font-size: 0.65rem; font-weight: 700; color: var(--text-subtle); letter-spacing: 1px; text-transform: uppercase; }
.dc-value { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }
.dc-desc { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }

/* -- MÓDULOS E CABEÇALHO FLEX -- */
.modules-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;}
.mh-text h2 { color: var(--text-main); font-size: 1.4rem; font-weight: 800; margin: 0 0 6px 0; }
.mh-text p { color: var(--text-muted); font-size: 0.9rem; margin: 0; font-weight: 500; }

/* -- O BOTÃO REDONDO DE VOLTAR PRO ADMIN -- */
.btn-return-admin {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    background: var(--primary) !important; color: #ffffff !important;
    padding: 12px 20px; border-radius: 50px; font-weight: 800; font-size: 0.9rem;
    border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s;
    animation: pulseAdmin 2s infinite; text-decoration: none;
}
.btn-return-admin:active { transform: scale(0.92); box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4); }

/* -- GRID DE MÓDULOS -- */
.modules-grid { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 40px; }
.module-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; text-decoration: none !important; cursor: pointer; transition: transform 0.15s; }
.module-card:active { transform: scale(0.96); }
.mc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.mc-icon { width: 44px; height: 44px; background: var(--icon-bg); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--icon-color); }
.mc-total { display: flex; flex-direction: column; align-items: flex-end; }
.mc-total span { font-size: 0.65rem; font-weight: 800; color: var(--text-subtle); letter-spacing: 1px; }
.mc-total strong { font-size: 1.3rem; font-weight: 800; color: var(--text-main); line-height: 1.2; font-family: 'Space Grotesk', sans-serif;}
.module-card h3 { color: var(--text-main); font-size: 1.1rem; font-weight: 800; margin: 0 0 8px 0; }
.module-card p { color: var(--text-muted); font-size: 0.85rem; margin: 0 0 20px 0; line-height: 1.5; flex-grow: 1; font-weight: 500; }
.mc-link { display: flex; align-items: center; justify-content: space-between; color: var(--text-main); font-size: 0.9rem; font-weight: 700; padding-top: 16px; border-top: 1px solid var(--card-border); }

@media (min-width: 768px) { .date-cards-grid, .modules-grid { grid-template-columns: repeat(3, 1fr); } }
</style>

<div class="home-wrapper" id="main-wrapper">
    
    <div class="main-card">
        <div class="status-badge <?= $badgeClass ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;">
                <?= $badgeIcon ?>
            </svg>
            <span data-i18n="<?= $badgeI18n ?>">ESTÁVEL</span>
        </div>
        
        <h1 class="welcome-title">
            <span data-i18n="hello">Hola</span>, <?= htmlspecialchars($userName) ?>
        </h1>
        <p class="welcome-desc" data-i18n="welcome_desc">Anticipá la renovación para evitar bloqueos operacionales.</p>

        <div class="stats-list">
            <div class="stat-item">
                <span class="s-label" data-i18n="account">CUENTA</span>
                <strong class="s-value" data-i18n="role_<?= strtolower($roleName) ?>"><?= $roleName ?></strong>
            </div>
            <div class="stat-item">
                <span class="s-label" data-i18n="expiration">EXPIRAÇÃO</span>
                <?php if($isAdmin || $expiresAtStr === 'VITALÍCIO'): ?>
                    <strong class="s-value s-vitalicio" data-i18n="lifetime">VITALÍCIO</strong>
                <?php elseif($daysLeft <= 0): ?>
                    <strong class="s-value s-expirado" data-i18n="expired_now">Expirado</strong>
                <?php else: ?>
                    <strong class="s-value"><span data-i18n="days_left" data-days="<?= $daysLeft ?>"><?= $daysLeft ?> dia(s) restante(s)</span></strong>
                <?php endif; ?>
            </div>
            <div class="stat-item">
                <span class="s-label" data-i18n="settings_title">CONFIGURAÇÕES</span>
                <strong class="s-value"><?= $totalConfigs ?></strong>
            </div>
        </div>
    </div>

    <div class="date-cards-grid">
        <div class="date-card">
            <div class="dc-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="dc-content">
                <span class="dc-label" data-i18n="created_at">CRIADO EM</span>
                <strong class="dc-value"><?= $createdAt ?></strong>
                <span class="dc-desc" data-i18n="account_initial_date">Data inicial da conta</span>
            </div>
        </div>
        
        <div class="date-card">
            <div class="dc-icon">
                 <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
            </div>
            <div class="dc-content">
                <span class="dc-label" data-i18n="updated_at">ACTUALIZADO EL</span>
                <strong class="dc-value"><?= $updatedAt ?></strong>
                <span class="dc-desc" data-i18n="last_updated">Última actualización registrada</span>
            </div>
        </div>

        <div class="date-card">
            <div class="dc-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><circle cx="12" cy="15" r="3"/><polyline points="12 13 12 15 13.5 16.5"/></svg>
            </div>
            <div class="dc-content">
                <span class="dc-label" data-i18n="expires_at">EXPIRA EL</span>
                <strong class="dc-value" <?= ($isAdmin || $expiresAtStr === 'VITALÍCIO') ? 'style="color:#10b981; font-family: \'Space Grotesk\', monospace;" data-i18n="lifetime"' : '' ?>><?= $expiresAtStr ?></strong>
                <span class="dc-desc"><?= htmlspecialchars($userEmail) ?></span>
            </div>
        </div>
    </div>

    <div class="modules-header">
        <div class="mh-text">
            <h2 data-i18n="main_modules">Módulos principales</h2>
            <p data-i18n="modules_desc">Acceso rápido a los módulos más usados del panel.</p>
        </div>
        
        <?php if ($isImpersonating): ?>
        <a href="?action=return_admin" class="btn-return-admin">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
            <span data-i18n="return_to_admin">Volver para Admin</span>
        </a>
        <?php endif; ?>
    </div>

    <div class="modules-grid">
        <a href="/home-config" class="module-card">
            <div class="mc-top">
                <div class="mc-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-1.2-1.8A2 2 0 0 0 8.55 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/><circle cx="12" cy="13" r="2"/><path d="M12 10v1"/><path d="M12 15v1"/><path d="M15 13h-1"/><path d="M10 13H9"/></svg>
                </div>
                <div class="mc-total">
                    <span data-i18n="total">TOTAL</span>
                    <strong><?= $totalConfigs ?></strong>
                </div>
            </div>
            <h3 data-i18n="settings_title">Configuraciones</h3>
            <p data-i18n="settings_desc">Gestioná modos, orden, categorías y distribución.</p>
            <div class="mc-link">
                <span data-i18n="access">Acessar</span> 
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </div>
        </a>

        <a href="/categorias" class="module-card">
            <div class="mc-top">
                <div class="mc-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </div>
                <div class="mc-total">
                    <span data-i18n="total">TOTAL</span>
                    <strong><?= $totalCategories ?></strong>
                </div>
            </div>
            <h3 data-i18n="categories">Categorías</h3>
            <p data-i18n="categories_desc">Organizá grupos y mantené la estructura del panel limpia.</p>
            <div class="mc-link">
                <span data-i18n="access">Acessar</span> 
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </div>
        </a>

        <a href="/dispositivos" class="module-card">
            <div class="mc-top">
                <div class="mc-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <div class="mc-total">
                    <span data-i18n="total">TOTAL</span>
                    <strong><?= $totalDevices ?></strong>
                </div>
            </div>
            <h3 data-i18n="devices">Dispositivos</h3>
            <p data-i18n="devices_desc">Acompanhe o volume e a atividade dos dispositivos.</p>
            <div class="mc-link">
                <span data-i18n="access">Acessar</span> 
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </div>
        </a>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
// --- DICIONÁRIO DE TRADUÇÃO SUPREMO ---
const appTranslations = {
    'pt': {
        'status_stable': 'OPERAÇÃO ESTÁVEL', 'status_expiring': 'EXPIRA EL BREVE', 'status_expired': 'ACESSO VENCE EM INSTANTES',
        'hello': 'Hola', 'welcome_desc': 'Anticipá la renovación para evitar bloqueos operacionales.',
        'account': 'CUENTA', 'role_usuário': 'Usuario', 'role_administrador': 'Administrador',
        'expiration': 'EXPIRAÇÃO', 'days_left': '{days} dia(s) restante(s)', 'lifetime': 'VITALÍCIO', 'expired_now': 'Expirado',
        'settings_title': 'CONFIGURAÇÕES', 'created_at': 'CRIADO EM', 'account_initial_date': 'Data inicial da conta',
        'updated_at': 'ACTUALIZADO EL', 'last_updated': 'Última actualización registrada', 'expires_at': 'EXPIRA EL',
        'main_modules': 'Módulos principales', 'modules_desc': 'Acceso rápido a los módulos más usados del panel.', 'total': 'TOTAL',
        'settings_desc': 'Gestioná modos, orden, categorías y distribución.', 'categories': 'Categorías',
        'categories_desc': 'Organizá grupos y mantené la estructura del panel limpia.', 'devices': 'Dispositivos',
        'devices_desc': 'Acompanhe o volume e a atividade dos dispositivos.', 'access': 'Acessar',
        'logout': 'Salir', 'return_to_admin': 'Volver para Admin',
        
        // Textos dos Modais de Segurança
        'modal_session_title': 'Sesión Expirada', 'modal_session_desc': 'La cuenta no fue encontrada o hubo inactividad.', 'modal_btn_login': 'Iniciar sesión',
        'modal_expired_title': 'Acceso Expirado', 'modal_expired_desc': 'Tu tiempo de uso llegó a su fin.', 'modal_btn_renew': 'Renovar Ahora',
        'modal_blocked_title': 'Conta Bloqueada', 'modal_blocked_desc': 'Esta conta foi bloqueada permanentemente pelo administrador do sistema.', 'modal_btn_support': 'Falar com Soporte',
        'modal_suspended_title': 'Conta Suspensa', 'modal_suspended_desc': 'O acesso desta conta foi suspenso temporariamente por motivos de segurança.', 'modal_btn_404': 'Página de Segurança',
        'modal_info_title': 'Acción necesaria e inmediata', 'modal_info_block': 'El acceso a la red y al panel fue completamente bloqueado.'
    },
    'en': {
        'status_stable': 'STABLE OPERATION', 'status_expiring': 'EXPIRING SOON', 'status_expired': 'ACCESS EXPIRES SHORTLY',
        'hello': 'Hello', 'welcome_desc': 'Renew early to avoid operational blockages.',
        'account': 'ACCOUNT', 'role_usuário': 'User', 'role_administrador': 'Admin',
        'expiration': 'EXPIRATION', 'days_left': '{days} day(s) left', 'lifetime': 'LIFETIME', 'expired_now': 'Expired',
        'settings_title': 'SETTINGS', 'created_at': 'CREATED AT', 'account_initial_date': 'Account initial date',
        'updated_at': 'UPDATED AT', 'last_updated': 'Last recorded update', 'expires_at': 'EXPIRES AT',
        'main_modules': 'Main modules', 'modules_desc': 'Quick access to the most used panel modules.', 'total': 'TOTAL',
        'settings_desc': 'Manage modes, order, categories and distribution.', 'categories': 'Categories',
        'categories_desc': 'Organize groups and keep the panel structure clean.', 'devices': 'Devices',
        'devices_desc': 'Track the volume and activity of devices.', 'access': 'Access',
        'logout': 'Logout', 'return_to_admin': 'Return to Admin',

        'modal_session_title': 'Session Expired', 'modal_session_desc': 'Account not found or inactivity detected.', 'modal_btn_login': 'Sign In',
        'modal_expired_title': 'Access Expired', 'modal_expired_desc': 'Your usage time has ended.', 'modal_btn_renew': 'Renew Now',
        'modal_blocked_title': 'Account Blocked', 'modal_blocked_desc': 'This account has been permanently blocked by the system administrator.', 'modal_btn_support': 'Contact Support',
        'modal_suspended_title': 'Account Suspended', 'modal_suspended_desc': 'Access to this account has been temporarily suspended for security reasons.', 'modal_btn_404': 'Security Page',
        'modal_info_title': 'Immediate action required', 'modal_info_block': 'Access to the network and panel has been completely denied.'
    },
    'es': {
        'status_stable': 'OPERACIÓN ESTABLE', 'status_expiring': 'PRONTO A EXPIRAR', 'status_expired': 'ACCESO EXPIRA EN BREVE',
        'hello': 'Hola', 'welcome_desc': 'Anticipe la renovación para evitar bloqueos operativos.',
        'account': 'CUENTA', 'role_usuário': 'Usuario', 'role_administrador': 'Administrador',
        'expiration': 'EXPIRACIÓN', 'days_left': '{days} día(s) restante(s)', 'lifetime': 'VITALICIO', 'expired_now': 'Expirado',
        'settings_title': 'CONFIGURACIONES', 'created_at': 'CREADO EN', 'account_initial_date': 'Fecha inicial de la cuenta',
        'updated_at': 'ACTUALIZADO EN', 'last_updated': 'Última actualización registrada', 'expires_at': 'EXPIRA EN',
        'main_modules': 'Módulos principales', 'modules_desc': 'Acceso rápido a los módulos más usados del panel.', 'total': 'TOTAL',
        'settings_desc': 'Administre modos, orden, categorías y distribución.', 'categories': 'Categorías',
        'categories_desc': 'Organice grupos y mantenga limpia la estructura del panel.', 'devices': 'Dispositivos',
        'devices_desc': 'Realice un seguimiento del volumen de dispositivos.', 'access': 'Acceder',
        'logout': 'Salir', 'return_to_admin': 'Volver al Admin',

        'modal_session_title': 'Sesión Expirada', 'modal_session_desc': 'No se encontró la cuenta o hubo inactividad.', 'modal_btn_login': 'Iniciar Sesión',
        'modal_expired_title': 'Acceso Expirado', 'modal_expired_desc': 'Su tiempo de uso ha finalizado.', 'modal_btn_renew': 'Renovar Ahora',
        'modal_blocked_title': 'Cuenta Bloqueada', 'modal_blocked_desc': 'Esta cuenta ha sido bloqueada permanentemente por el administrador.', 'modal_btn_support': 'Contactar Soporte',
        'modal_suspended_title': 'Cuenta Suspendida', 'modal_suspended_desc': 'El acceso a esta cuenta ha sido suspendido temporalmente por seguridad.', 'modal_btn_404': 'Página de Seguridad',
        'modal_info_title': 'Acción inmediata requerida', 'modal_info_block': 'El acceso a la red y al panel ha sido completamente denegado.'
    }
};

function getDictText(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    return appTranslations[lang] && appTranslations[lang][key] ? appTranslations[lang][key] : appTranslations['pt'][key];
}

window.changeAppLanguage = function(langCode) {
    localStorage.setItem('app_language', langCode);
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (appTranslations[langCode] && appTranslations[langCode][key]) {
            let text = appTranslations[langCode][key];
            if (el.hasAttribute('data-days')) text = text.replace('{days}', el.getAttribute('data-days'));
            el.textContent = text;
        }
    });
};

// =========================================================================================
// MOTOR "TREM BALA" (SWEETALERT2 EM TEMPO REAL) & ARMADILHA DE CLIQUES CORRIGIDA
// =========================================================================================
let currentLockState = null;
let isAlertShowing = false;

// Função para montar o HTML idêntico ao Print
function buildModalHTML(iconClass, svgIcon, titleKey, descKey, extraText) {
    return `
        <div class="swal-header-custom">
            <div class="swal-icon-custom \${iconClass}">\${svgIcon}</div>
            <div class="swal-header-text">
                <h2 class="swal-title-custom">\${getDictText(titleKey)}</h2>
                <p class="swal-desc-custom">\${getDictText(descKey)}</p>
            </div>
        </div>
        <div class="swal-infobox-custom">
            <span>\${getDictText('modal_info_title')}</span>
            <strong>\${extraText}</strong>
        </div>
    `;
}

function triggerUltimateLockout(type) {
    // Evita o pisca-pisca infinito se já estiver aberto (ou se o cara fechou na "Arapuca Armada")
    if (currentLockState === type && isAlertShowing) return;
    if (currentLockState === type && !isAlertShowing && type === 'blocked') return;

    currentLockState = type;
    
    if(type !== 'blocked') document.body.classList.add('locked-system');
    else document.body.classList.remove('locked-system');

    isAlertShowing = true;
    
    const isDark = document.documentElement.classList.contains('dark');
    const swalConfig = {
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm' },
        background: isDark ? '#1a1a1e' : '#ffffff',
        color: isDark ? '#ffffff' : '#111827',
        backdrop: `rgba(0,0,0,0.80)`,
        buttonsStyling: false, showCloseButton: false, allowEscapeKey: false,
        allowOutsideClick: false,
        scrollbarPadding: false // Corrige el bug de a página "encolher" nos lados quando o modal abre
    };

    let btnText = "", btnClass = "";

    // 1. SESSÃO EXPIRADA (Relógio)
    if (type === 'deleted') {
        const svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
        swalConfig.html = buildModalHTML('warning', svg, 'modal_session_title', 'modal_session_desc', getDictText('modal_info_block'));
        btnText = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> ` + getDictText('modal_btn_login');
        btnClass = 'swal-btn-primary';
    } 
    // 2. ACESSO EXPIRADO (Atenção Triângulo)
    else if (type === 'expired') {
        const svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
        swalConfig.html = buildModalHTML('danger', svg, 'modal_expired_title', 'modal_expired_desc', getDictText('modal_info_block'));
        btnText = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg> ` + getDictText('modal_btn_renew');
        btnClass = 'swal-btn-danger';
    }
    // 3. CUENTA SUSPENSA (Proibido Slash)
    else if (type === 'suspended') {
        const svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>`;
        swalConfig.html = buildModalHTML('orange', svg, 'modal_suspended_title', 'modal_suspended_desc', getDictText('modal_info_block'));
        btnText = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> ` + getDictText('modal_btn_404');
        btnClass = 'swal-btn-orange';
    }
    // 4. CUENTA BLOQUEADA (Cadeado idêntico) - A Arapuca
    else if (type === 'blocked') {
        const svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>`;
        swalConfig.html = buildModalHTML('danger', svg, 'modal_blocked_title', 'modal_blocked_desc', getDictText('modal_info_block'));
        btnText = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> ` + getDictText('modal_btn_support');
        btnClass = 'swal-btn-danger'; // Vermelho puro para o cadeado
        swalConfig.allowOutsideClick = true; // Libera o clique fora para a armadilha funcionar
    }

    swalConfig.confirmButtonText = btnText;
    swalConfig.customClass.confirmButton += ' ' + btnClass;

    Swal.fire(swalConfig).then((result) => {
        isAlertShowing = false; 
        if (result.isConfirmed) {
            // SESSÃO EXPIRADA agora vai para /logout forçadamente para limpar a sessão cacheada
            if (type === 'deleted') window.location.assign('/logout');
            else if (type === 'expired') window.location.assign('/renovar');
            else if (type === 'suspended') window.location.assign('/404.php');
            else if (type === 'blocked') window.location.assign('https://wa.me/543455236886');
        }
    });
}

// O Polling Infalível a cada 1.5s
function checkRealTimeEstado() {
    fetch('?realtime_check=1')
        .then(r => r.json())
        .then(data => {
            if (data.deleted) triggerUltimateLockout('deleted');
            else if (data.blocked) triggerUltimateLockout('blocked');
            else if (data.suspended) triggerUltimateLockout('suspended');
            else if (data.expired) triggerUltimateLockout('expired');
            else {
                currentLockState = null;
                document.body.classList.remove('locked-system');
                if(isAlertShowing) { Swal.close(); isAlertShowing = false; }
            }
        }).catch(() => {});
}
setInterval(checkRealTimeEstado, 1500); 

// =========================================================================================
// ARAPUCA SUPREMA REFINADA (Permite Header, Idiomas e Logout)
// =========================================================================================
document.addEventListener('click', function(e) {
    if (currentLockState === 'blocked') {
        
        // Se clicou no popup do Swal, passa direto
        if (e.target.closest('.swal2-container')) return;
        
        // ALLOW-LIST: O usuário pode mexer nisso sem ser bloqueado:
        // Sol/Lua, Idioma, Avatar, Botão Salir do Menu, Volver Admin, Eliminar conta salva, Link físico de Logout
        if (e.target.closest('.header-btn, .header-actions, .header-avatar, .dropdown-menu, .btn-logout-custom, .btn-return-admin, .acc-btn.trash, a[href="/logout"]')) {
            
            // Mas se ele tentar clicar no item da conta salva para trocar de conta, ele toma block!
            if (e.target.closest('.acc-item') && !e.target.closest('.acc-btn.trash')) {
                e.preventDefault(); e.stopPropagation();
                currentLockState = null; triggerUltimateLockout('blocked'); return false;
            }
            
            return; // Permite a ação fluir normalmente (Trocar tema, sair, etc)
        }
        
        // BLOCK-LIST: Se ele clicou em qualquer outra coisa (Link, card, texto, menu)
        const target = e.target.closest('a, button, .module-card, .sidebar-nav-custom, .date-card, .main-card');
        if (target) {
            e.preventDefault(); e.stopPropagation();
            currentLockState = null; triggerUltimateLockout('blocked'); // O Modal PULA NA CARA DELE de novo
            return false;
        }
    }
}, true); // "true" captura o evento antes de qualquer outra função nativa

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('app_language');
    if(savedLang) changeAppLanguage(savedLang);
    checkRealTimeEstado(); 
});
</script>
JS;

include __DIR__ . '/../includes/layout.php';