<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Perfil do Usuario Premium V5 + Credenciais JSON (Fix Bypass 404 e 403)
 * @description Ajustado para usar UUID real do usuário nas URLs da API.
 * =======================================================================================
 */

if (!defined('DTUNNEL_APP')) { 
    header('HTTP/1.0 403 Forbidden'); 
    exit; 
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_GET['action']) && $_GET['action'] === 'return_admin' && isset($_SESSION['admin_return_email'])) {
    $_SESSION['email'] = $_SESSION['admin_return_email'];
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = 'Administrador';
    unset($_SESSION['admin_return_email']);
    unset($_SESSION['avatar_url']); 
    header('Location: /usuarios');
    exit;
}

$isImpersonating = isset($_SESSION['admin_return_email']);
$dbFile = __DIR__ . '/../db/usuarios.json';
clearstatcache(true, $dbFile); 

$sessionEmail = $_SESSION['email'] ?? '';
$userFound = false;
$userData = [];

if (file_exists($dbFile)) {
    $usuarios = json_decode(file_get_contents($dbFile), true) ?: [];
    foreach ($usuarios as $u) {
        if (strtolower($u['email']) === strtolower($sessionEmail)) {
            $userData = $u;
            $userFound = true;
            break;
        }
    }
}

if (!$userFound) {
    $userData = [
        'username' => $_SESSION['username'] ?? 'Usuario',
        'email' => $sessionEmail,
        'role' => $_SESSION['role'] ?? 'user',
        'uuid' => $_SESSION['user_id'] ?? '---',
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'avatar_url' => $_SESSION['avatar_url'] ?? ''
    ];
}

// ======================================================================
// GERAÇÃO DOS LINKS JSON - APONTANDO PARA A NOVA API (update.php) USANDO UUID
// ======================================================================
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];

// Rota oficial da API que vamos criar
$apiEndpoint = "{$protocol}://{$domain}/api/update.php";

// Extrai o UUID real do banco de dados (Ou gera um temporário se der erro)
$userUuid = $userData['uuid'] ?? '---'; 

// Montagem do JSON perfeito que o DTunnel vai ler
$credJson = [
    "cdn" => "{$apiEndpoint}?type=cdn&uuid={$userUuid}",
    "category" => "{$apiEndpoint}?type=category&uuid={$userUuid}",
    "app_config" => "{$apiEndpoint}?type=config&uuid={$userUuid}",
    "app_layout" => "{$apiEndpoint}?type=layout&uuid={$userUuid}",
    "app_text" => "{$apiEndpoint}?type=text&uuid={$userUuid}",
    "credits" => "@El_NeNe_Sando",
    "channel" => "@El_NeNe_Sando",
    "group" => "@El_NeNe_Sando"
];
$credJsonString = json_encode($credJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$msgType = ''; 
$msgContent = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $usuarios = json_decode(file_get_contents($dbFile), true) ?: [];
    $updated = false;

    foreach ($usuarios as &$u) {
        if (strtolower($u['email']) === strtolower($sessionEmail)) {
            if ($action === 'save_profile') {
                $u['username'] = trim($_POST['username'] ?? $u['username']);
                $_SESSION['username'] = $u['username'];

                $newAvatar = trim($_POST['avatar_url'] ?? '');
                $u['avatar_url'] = $newAvatar;
                $_SESSION['avatar_url'] = $newAvatar;

                if (!empty($_POST['new_password'])) {
                    if ($_POST['new_password'] === $_POST['confirm_password']) {
                        $u['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    } else {
                        $msgType = 'error'; $msgContent = 'toast_passwords_not_match';
                        break;
                    }
                }
                
                $u['updated_at'] = date('Y-m-d H:i:s');
                $updated = true;
                $msgType = 'success'; $msgContent = 'toast_profile_updated';
                
                $userData['username'] = $u['username'];
                $userData['avatar_url'] = $u['avatar_url'];
                $userData['updated_at'] = $u['updated_at'];
            }
            break;
        }
    }
    unset($u);

    if ($updated) {
        file_put_contents($dbFile, json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

$createdAt = date('d/m/Y', strtotime($userData['created_at'] ?? 'now'));
$updatedAt = date('d/m/Y', strtotime($userData['updated_at'] ?? $userData['created_at']));
$expiresAt = !empty($userData['expires_at']) ? date('d/m/Y', strtotime($userData['expires_at'])) : 'VITALÍCIO';

$words = explode(' ', trim($userData['username']));
$initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

$pageTitle = 'Perfil';
ob_start();
?>

<style>
.profile-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --icon-bg: #f3f4f6; --primary: #3b82f6; --success: #10b981; --danger: #ef4444;
    padding: 16px; max-width: 800px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
}
:root.dark .profile-wrapper, body.dark .profile-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #121214; --icon-bg: rgba(255, 255, 255, 0.03);
}
.profile-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; }

body.dark .custom-modal { background: #18181b !important; border: 1px solid #3f3f46 !important; color: var(--text-main) !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,1) !important; }
body.dark .custom-modal .modal-header { border-bottom-color: #3f3f46 !important; }
body.dark .custom-modal .modal-title, body.dark .custom-modal .modal-label, body.dark .preview-box { color: var(--text-main) !important; }
body.dark .custom-modal svg { stroke: currentColor !important; }
body.dark .edit-textarea { background: #0f0f11 !important; border-color: #3f3f46 !important; color: var(--text-main) !important; }

.profile-header { margin-bottom: 24px; }
.profile-header h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0; }
.ph-tabs { display: flex; gap: 10px; margin-top: 20px; }
.ph-tab { background: var(--card-bg); border: 1px solid var(--card-border); padding: 10px 16px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 8px; transition: transform 0.15s; }
.ph-tab:active { transform: scale(0.96); }
.ph-tab.active { background: var(--inner-bg); color: var(--text-main); border-color: var(--card-border); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

.profile-main-card {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px; padding: 40px 24px;
    display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.02); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.profile-main-card:active { transform: scale(0.99); }

.avatar-container { position: relative; margin-bottom: 20px; }
.avatar-big {
    width: 120px; height: 120px; border-radius: 50%; background: var(--icon-bg); border: 4px solid var(--card-bg);
    display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; color: var(--text-main);
    overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}
.avatar-big img { width: 100%; height: 100%; object-fit: cover; }

.btn-gallery-trigger {
    position: absolute; bottom: 0px; right: 0px; width: 40px; height: 40px; background: var(--icon-bg);
    border: 2px solid var(--card-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center;
    color: var(--text-main); cursor: pointer; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.btn-gallery-trigger:active { transform: scale(0.85); background: var(--card-border); }

.profile-name { font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
.profile-email-sub { font-size: 0.95rem; color: var(--text-muted); font-weight: 500; margin-bottom: 16px; }
.status-pill { padding: 6px 16px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }

.info-item {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 20px;
    display: flex; flex-direction: column; gap: 6px; text-align: left; margin-bottom: 12px; width: 100%;
}
.info-item span { font-size: 0.7rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 1px; }
.info-item strong { font-size: 1rem; color: var(--text-main); font-weight: 700; }

.id-copy-wrapper {
    background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px;
    display: flex; justify-content: space-between; align-items: center; padding: 14px 16px;
    margin-top: 8px; font-family: 'Space Grotesk', monospace; font-size: 0.85rem; color: var(--text-main); word-break: break-all;
}
.btn-copy-uuid {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 8px;
    padding: 8px 12px; font-size: 0.75rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 6px; cursor: pointer; transition: transform 0.15s; flex-shrink: 0; margin-left: 12px;
}
.btn-copy-uuid:active { transform: scale(0.92); background: var(--icon-bg); }

.btn-action { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); cursor: pointer; transition: transform 0.15s, background 0.2s, border-color 0.2s; outline: none; }
.btn-action:active { transform: scale(0.96); }
.btn-action:hover { border-color: var(--text-main); color: var(--text-main); }
.btn-action.sync-btn { background: var(--text-main); color: var(--card-bg); border: none; }
.btn-action.sync-btn:hover { background: var(--text-main); color: var(--card-bg); opacity: 0.9; }

.custom-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 9999999; padding: 16px; opacity: 0; transition: opacity 0.3s ease; }
.custom-modal-overlay.active { display: flex; opacity: 1; }
.custom-modal { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px; width: 100%; max-width: 520px; padding: 24px; display: flex; flex-direction: column; gap: 16px; transform: translateY(30px) scale(0.95); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 60px rgba(0,0,0,0.6); }
.custom-modal-overlay.active .custom-modal { transform: translateY(0) scale(1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--card-border); padding-bottom: 16px; }
.modal-title { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0; }
.modal-close { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); cursor: pointer; transition: all 0.2s; }
.modal-close:hover { background: var(--danger); color: #fff; border-color: var(--danger); transform: rotate(90deg); }
.edit-textarea { width: 100%; padding: 16px; border-radius: 14px; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); outline: none; font-family: monospace; font-size: 0.85rem; resize: vertical; transition: border-color 0.2s; }
.edit-textarea:focus { border-color: var(--text-main); }
.custom-modal::-webkit-scrollbar { width: 6px; }
.custom-modal::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 10px; }

.edit-title-group { margin: 40px 0 24px 0; }
.edit-title-group h2 { font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.edit-title-group p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

.form-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 20px; transition: transform 0.2s;}
.form-card:focus-within { border-color: var(--text-main); }
.ec-title { display: flex; align-items: center; gap: 10px; font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 24px; }
.ec-title svg { width: 18px; color: var(--text-muted); }

.form-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.form-label { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
.input-wrapper { position: relative; }
.input-wrapper svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-subtle); width: 18px; pointer-events: none; }
.form-control { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 16px 16px 48px; font-size: 0.95rem; font-weight: 600; color: var(--text-main); transition: all 0.2s; outline: none; }
.form-control:focus { border-color: var(--text-main); background: var(--card-bg); }
.form-control:read-only { opacity: 0.6; cursor: not-allowed; }

.btn-action-icon { width: 54px; height: 54px; flex-shrink: 0; background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--text-main); cursor: pointer; transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1); }
.btn-action-icon:active { transform: scale(0.90); background: var(--icon-bg); }
.btn-action-icon.danger { color: var(--danger); }
.btn-action-icon.danger:active { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); }
.btn-action-icon svg { width: 22px; height: 22px; }

.btn-save-master { width: 100%; background: var(--inner-bg) !important; color: var(--text-main) !important; border: 1px solid var(--card-border) !important; padding: 18px; border-radius: 16px; font-weight: 800; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 12px; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
.btn-save-master:active { transform: scale(0.95); background: var(--icon-bg) !important; border-color: var(--text-subtle) !important; }
.btn-save-master svg { transition: transform 0.3s; color: var(--text-muted); }
.btn-save-master:hover svg { transform: scale(1.1); color: var(--text-main); }

#toast-container { position: fixed; top: 20px; right: 20px; z-index: 1000000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; pointer-events: auto; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; width: 320px; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
.dark .toast { box-shadow: 0 10px 30px rgba(0,0,0,0.6); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: white; }
.toast.error .toast-icon { background: #ef4444; }
.toast.success .toast-icon { background: #10b981; }
.toast-msg { font-size: 0.95rem; font-weight: 600; line-height: 1.4; flex: 1; color: var(--text-main); }
.toast-progress { position: absolute; bottom: 0; left: 0; height: 4px; animation: toastTime 4s linear forwards; }
.toast.error .toast-progress { background: #ef4444; }
.toast.success .toast-progress { background: #10b981; }
@keyframes toastTime { from { width: 100%; } to { width: 0%; } }
</style>

<div id="toast-container"></div>

<div class="profile-wrapper">
    <div class="profile-header">
        <h1 data-i18n="profile">Perfil</h1>
        <div class="ph-tabs">
            <div class="ph-tab active">
                <svg width="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span data-i18n="personal_account">Conta pessoal</span>
            </div>
            <div class="ph-tab">
                <span data-i18n="role_<?= strtolower($userData['role']) ?>"><?= strtoupper($userData['role']) ?></span>
            </div>
        </div>
    </div>

    <div class="profile-main-card">
        <div class="avatar-container">
            <div class="avatar-big" id="main-avatar-view">
                <?php if (!empty($userData['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($userData['avatar_url']) ?>" alt="Avatar" onerror="this.onerror=null; this.parentElement.innerHTML='<?= $initials ?>';">
                <?php else: ?>
                    <?= $initials ?>
                <?php endif; ?>
            </div>
            <input type="file" id="avatar_file_input" accept="image/*" style="display:none;" onchange="handleFileUpload(this)">
            <button class="btn-gallery-trigger" onclick="document.getElementById('avatar_file_input').click()" title="Escolher da Galeria">
                <svg width="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </button>
        </div>
        
        <h2 class="profile-name" id="display-name"><?= htmlspecialchars($userData['username']) ?></h2>
        <p class="profile-email-sub"><?= htmlspecialchars($userData['email']) ?></p>
        <div class="status-pill" data-i18n="active">ACTIVE</div>
    </div>

    <div class="info-item">
        <span data-i18n="user_id">ID DO USUÁRIO</span>
        <div class="id-copy-wrapper">
            <span id="user-uuid-text"><?= htmlspecialchars($userData['uuid']) ?></span>
            <button class="btn-copy-uuid" onclick="copyUUID()">
                <svg width="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span data-i18n="copy">Copiar</span>
            </button>
        </div>
        
        <button class="btn-action sync-btn" style="margin-top: 16px; width: 100%;" onclick="openModal('credModalOverlay')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span data-i18n="copy_credentials">Copiar Credenciais</span>
        </button>
    </div>

    <div class="info-item"><span data-i18n="account_type">TIPO DE CUENTA</span><strong data-i18n="role_<?= strtolower($userData['role']) ?>"><?= strtoupper($userData['role']) ?></strong></div>
    <div class="info-item"><span data-i18n="status">STATUS</span><strong data-i18n="active">ACTIVE</strong></div>
    <div class="info-item"><span data-i18n="created_at">CRIADO EM</span><strong><?= $createdAt ?></strong></div>
    <div class="info-item"><span data-i18n="updated_at">ACTUALIZADO EL</span><strong><?= $updatedAt ?></strong></div>
    <div class="info-item"><span data-i18n="expires_at">EXPIRA EL</span><strong><?= $expiresAt ?></strong></div>

    <div class="edit-title-group">
        <h2 data-i18n="edit_info">Editar informações</h2>
        <p data-i18n="edit_info_desc">Atualize seus dados principais, avatar e senha de acesso.</p>
    </div>

    <form method="POST" id="profileForm">
        <input type="hidden" name="action" value="save_profile">

        <div class="form-card">
            <div class="ec-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span data-i18n="main_data">Dados principais</span></div>

            <div class="form-group">
                <label class="form-label" data-i18n="full_name">Nombre completo</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($userData['username']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">E-mail</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" data-i18n="avatar_url">URL do avatar</label>
                <div style="display:flex; gap:8px;">
                    <div class="input-wrapper" style="flex:1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <input type="text" id="avatar_url_input" name="avatar_url" class="form-control" value="<?= htmlspecialchars($userData['avatar_url']) ?>" placeholder="https://exemplo.com/avatar.png" oninput="updatePreviewFromInput()">
                    </div>
                    <button type="button" class="btn-action-icon" onclick="document.getElementById('avatar_file_input').click()" title="Galeria">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </button>
                    <button type="button" class="btn-action-icon danger" onclick="clearAvatar()" title="Limpar Foto">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="ec-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span data-i18n="security">Segurança</span></div>
            <div class="form-group">
                <label class="form-label" data-i18n="new_password">Nueva senha</label>
                <input type="password" name="new_password" class="form-control" style="padding-left:20px" placeholder="Opcional" data-i18n-placeholder="optional">
            </div>
            <div class="form-group">
                <label class="form-label" data-i18n="confirm_password">Confirmar contraseña</label>
                <input type="password" name="confirm_password" class="form-control" style="padding-left:20px" placeholder="Repetí la contraseña" data-i18n-placeholder="repeat_password">
            </div>
        </div>

        <div class="form-card" style="padding: 12px;">
            <div class="ec-title" style="margin: 10px 0 10px 10px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span data-i18n="account_actions">Acciones da conta</span></div>
            <button type="submit" class="btn-save-master"><svg width="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg><span data-i18n="save_profile">Guardar perfil</span></button>
        </div>
    </form>
</div>

<div class="custom-modal-overlay" id="credModalOverlay">
    <div class="custom-modal">
        <div class="modal-header">
            <h3 class="modal-title" data-i18n="mdl_cred_title">Credenciais do App</h3>
            <button class="modal-close" onclick="closeModal('credModalOverlay')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <p style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin: 0 0 10px 0;" data-i18n="mdl_cred_desc">Utilize este JSON para configurar o aplicación del panel.</p>
        <textarea id="credTextarea" class="edit-textarea" style="height: 220px;" readonly><?= $credJsonString ?></textarea>
        <div style="display:flex; gap:16px; margin-top: 16px;">
            <button class="btn-action" style="flex:1;" onclick="copyCredJson()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> <span data-i18n="btn_copy_json">Copiar JSON</span></button>
            <button class="btn-action sync-btn" style="flex:1;" onclick="downloadCredJson()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> <span data-i18n="btn_download">Descargar arquivo</span></button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
const profTranslations = {
    'pt': {
        'profile': 'Perfil', 'personal_account': 'Conta pessoal', 'role_admin': 'ADMINISTRADOR', 'role_user': 'USUÁRIO', 'role_vendor': 'VENDEDOR',
        'user_id': 'ID DO USUÁRIO', 'account_type': 'TIPO DE CUENTA', 'status': 'STATUS', 'active': 'ACTIVE', 'created_at': 'CRIADO EM',
        'updated_at': 'ACTUALIZADO EL', 'expires_at': 'EXPIRA EL', 'copy': 'Copiar', 'edit_info': 'Editar informações',
        'edit_info_desc': 'Atualize seus dados principais, avatar e senha de acesso.', 'main_data': 'Dados principais',
        'full_name': 'Nombre completo', 'security': 'Segurança', 'new_password': 'Nueva senha', 'confirm_password': 'Confirmar contraseña', 
        'optional': 'Opcional', 'repeat_password': 'Repetí la contraseña', 'account_actions': 'Acciones da conta', 'save_profile': 'Guardar perfil',
        'toast_copied': 'ID copiado con éxito!', 'toast_profile_updated': 'Perfil atualizado con éxito!', 'toast_passwords_not_match': 'Las contraseñas no coinciden.',
        'toast_error': 'Error al processar alteração.', 'avatar_url': 'URL do avatar',
        'copy_credentials': 'Copiar Credenciais', 'mdl_cred_title': 'Credenciais do App', 'mdl_cred_desc': 'Utilize este JSON para configurar o aplicación del panel.', 'btn_copy_json': 'Copiar JSON', 'btn_download': 'Descargar arquivo', 'toast_json_copied': 'JSON copiado!'
    },
    'en': {
        'profile': 'Profile', 'personal_account': 'Personal account', 'role_admin': 'ADMINISTRATOR', 'role_user': 'USER', 'role_vendor': 'VENDOR',
        'user_id': 'USER ID', 'account_type': 'ACCOUNT TYPE', 'status': 'STATUS', 'active': 'ACTIVE', 'created_at': 'CREATED AT',
        'updated_at': 'UPDATED AT', 'expires_at': 'EXPIRES AT', 'copy': 'Copy', 'edit_info': 'Edit information',
        'edit_info_desc': 'Update your main data, avatar and access password.', 'main_data': 'Main data',
        'full_name': 'Full name', 'security': 'Security', 'new_password': 'New password', 'confirm_password': 'Confirm password', 
        'optional': 'Optional', 'repeat_password': 'Repeat password', 'account_actions': 'Account actions', 'save_profile': 'Save profile',
        'toast_copied': 'ID copied successfully!', 'toast_profile_updated': 'Profile updated successfully!', 'toast_passwords_not_match': 'Passwords do not match.',
        'toast_error': 'Error processing changes.', 'avatar_url': 'Avatar URL',
        'copy_credentials': 'Copy Credentials', 'mdl_cred_title': 'App Credentials', 'mdl_cred_desc': 'Use this JSON to configure the panel application.', 'btn_copy_json': 'Copy JSON', 'btn_download': 'Download file', 'toast_json_copied': 'JSON copied!'
    },
    'es': {
        'profile': 'Perfil', 'personal_account': 'Cuenta personal', 'role_admin': 'ADMINISTRADOR', 'role_user': 'USUARIO', 'role_vendor': 'VENDEDOR',
        'user_id': 'ID DE USUARIO', 'account_type': 'TIPO DE CUENTA', 'status': 'ESTADO', 'active': 'ACTIVE', 'created_at': 'CREADO EM',
        'updated_at': 'ACTUALIZADO EN', 'expires_at': 'EXPIRA EN', 'copy': 'Copiar', 'edit_info': 'Editar información',
        'edit_info_desc': 'Actualice sus datos principales, avatar y contraseña.', 'main_data': 'Datos principales',
        'full_name': 'Nombre completo', 'security': 'Seguridad', 'new_password': 'Nueva senha', 'confirm_password': 'Confirmar contraseña', 
        'optional': 'Opcional', 'repeat_password': 'Repetí la contraseña', 'account_actions': 'Acciones da cuenta', 'save_profile': 'Guardar perfil',
        'toast_copied': '¡ID copiado con éxito!', 'toast_profile_updated': '¡Perfil actualizado con éxito!', 'toast_passwords_not_match': 'Las contraseñas no coinciden.',
        'toast_error': 'Error al procesar la alteración.', 'avatar_url': 'URL del avatar',
        'copy_credentials': 'Copiar Credenciales', 'mdl_cred_title': 'Credenciales de la App', 'mdl_cred_desc': 'Utilice este JSON para configurar la aplicación del panel.', 'btn_copy_json': 'Copiar JSON', 'btn_download': 'Descargar archivo', 'toast_json_copied': '¡JSON copiado!'
    }
};

if (window.globalTranslations) {
    for (let lang in profTranslations) {
        if (!window.globalTranslations[lang]) window.globalTranslations[lang] = {};
        Object.assign(window.globalTranslations[lang], profTranslations[lang]);
    }
}

const originalSelectAppLang = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(originalSelectAppLang) originalSelectAppLang(langCode);
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (profTranslations[langCode] && profTranslations[langCode][key]) {
            el.placeholder = profTranslations[langCode][key];
        }
    });
    triggerLocalTranslation(langCode);
};

function triggerLocalTranslation(langCode) {
    const dict = profTranslations[langCode] || profTranslations['pt'];
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) el.textContent = dict[key];
    });
}

function getLocalMsg(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    return profTranslations[lang] && profTranslations[lang][key] ? profTranslations[lang][key] : key;
}

function showToast(type, msgKey) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast \${type}`;
    const icon = type === 'error' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>';
    toast.innerHTML = `<div class="toast-icon">\${icon}</div><div class="toast-msg">\${getLocalMsg(msgKey)}</div><div class="toast-progress"></div>`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function copyCredJson() {
    const textarea = document.getElementById('credTextarea');
    textarea.select(); document.execCommand('copy');
    showToast('success', 'toast_json_copied');
}

function downloadCredJson() {
    const jsonStr = document.getElementById('credTextarea').value;
    const blob = new Blob([jsonStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const dlAnchorElem = document.createElement('a');
    dlAnchorElem.setAttribute("href", url);
    dlAnchorElem.setAttribute("download", "DTunnelmod.json");
    document.body.appendChild(dlAnchorElem);
    dlAnchorElem.click();
    document.body.removeChild(dlAnchorElem);
    URL.revokeObjectURL(url);
    showToast('success', 'toast_json_copied');
}

function updatePreviewFromInput() {
    const val = document.getElementById('avatar_url_input').value.trim();
    const preview = document.getElementById('main-avatar-view');
    if (val) preview.innerHTML = `<img src="\${val}" alt="Avatar" onerror="this.onerror=null; this.parentElement.innerHTML='<?= $initials ?>';">`;
    else preview.innerHTML = '<?= $initials ?>';
}

function handleFileUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (!file.type.match('image.*')) { showToast('error', 'toast_error'); return; }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar_url_input').value = e.target.result;
            updatePreviewFromInput(); 
        };
        reader.readAsDataURL(file);
    }
}

function clearAvatar() {
    document.getElementById('avatar_url_input').value = '';
    updatePreviewFromInput();
}

function copyUUID() {
    const text = document.getElementById('user-uuid-text').innerText;
    navigator.clipboard.writeText(text).then(() => { showToast('success', 'toast_copied'); });
}

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('app_language') || 'pt';
    triggerLocalTranslation(savedLang);
    const msgType = '$msgType'; const msgContent = '$msgContent';
    if(msgType && msgContent) setTimeout(() => showToast(msgType, msgContent), 300);
});
</script>
JS;

include __DIR__ . '/../includes/layout.php';