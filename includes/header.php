<?php
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }

// =======================================================================================
// 1. INICIALIZAÇÃO E COLETA DE DADOS REAIS DA SESSÃO E BANCO DE DADOS
// =======================================================================================
$currentUser = getCurrentUser();

$userEmail = !empty($_SESSION['email']) ? $_SESSION['email'] : 'dtunnelvpn@gmail.com';
$userName  = !empty($_SESSION['username']) ? $_SESSION['username'] : 'Usuario';

// Busca a foto de perfil em tempo real do DB
$dbFile = __DIR__ . '/../db/usuarios.json';
$userAvatarUrl = '';
if (file_exists($dbFile)) {
    $usuariosDB = json_decode(file_get_contents($dbFile), true) ?: [];
    foreach ($usuariosDB as $u) {
        if (strtolower($u['email']) === strtolower($userEmail)) {
            $userAvatarUrl = $u['avatar_url'] ?? '';
            break;
        }
    }
}
// Fallback para admin hardcoded ou caso esteja na sessão
if (empty($userAvatarUrl)) {
    $userAvatarUrl = $_SESSION['avatar_url'] ?? $currentUser['avatar_url'] ?? '';
}

// Função para extrair as iniciais
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
        if (strlen($initials) >= 2) break;
    }
    return empty($initials) ? 'U' : $initials;
}
$userInitials = getInitials($userName);

// =======================================================================================
// 2. MOTOR INTERNO DE GERENCIAMENTO DE MÚLTIPLAS CUENTAS (O CÉREBRO)
// =======================================================================================
if (!isset($_SESSION['saved_accounts'])) {
    $_SESSION['saved_accounts'] = [];
}

// A. Salva/Atualiza a conta atual automaticamente na lista
$isCurrentSaved = false;
foreach ($_SESSION['saved_accounts'] as &$acc) {
    if (strtolower($acc['email']) === strtolower($userEmail)) {
        $isCurrentSaved = true;
        // Atualiza os dados sempre para refletir mudanças de nome ou foto
        $acc['name'] = $userName;
        $acc['initials'] = $userInitials;
        $acc['avatar_url'] = $userAvatarUrl;
        break;
    }
}
unset($acc);

// [CORREÇÃO DE SEGURANÇA]: Impede de salvar a conta admin na lista de contas do dispositivo
if (!$isCurrentSaved && !empty($userEmail) && strtolower($userEmail) !== 'elnene.admin@gmail.com') {
    $_SESSION['saved_accounts'][] = [
        'id' => $_SESSION['user_id'] ?? uniqid(),
        'email' => $userEmail,
        'name' => $userName,
        'initials' => $userInitials,
        'avatar_url' => $userAvatarUrl
    ];
}

// Captura a ação vinda pela URL
$action = $_GET['action'] ?? '';

// B. AÇÃO: Agregar Nueva Conta
if ($action === 'add_account') {
    unset($_SESSION['email']);
    unset($_SESSION['username']);
    unset($_SESSION['role']);
    unset($_SESSION['user_id']);
    unset($_SESSION['avatar_url']);
    echo "<script>window.location.href = '/login';</script>";
    exit;
}

// C. AÇÃO: Remover Conta Salva Manualmente
if ($action === 'remove_account') {
    $targetEmail = $_GET['email'] ?? '';
    $newSaved = [];
    foreach ($_SESSION['saved_accounts'] as $acc) {
        if (strtolower($acc['email']) !== strtolower($targetEmail)) {
            $newSaved[] = $acc;
        }
    }
    $_SESSION['saved_accounts'] = $newSaved;
    echo "<script>window.location.href = window.location.pathname;</script>";
    exit;
}

// D. AÇÃO: Trocar de Conta
if ($action === 'switch_account') {
    $targetEmail = $_GET['email'] ?? '';
    $loginSuccess = false;
    
    if ($targetEmail === 'elnene.admin@gmail.com') {
        $loginSuccess = true;
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = 'Administrador';
        $_SESSION['avatar_url'] = '';
    } elseif (file_exists($dbFile)) {
        $usuariosDB = json_decode(file_get_contents($dbFile), true) ?: [];
        foreach ($usuariosDB as $user) {
            if (strtolower($user['email']) === strtolower($targetEmail)) {
                $loginSuccess = true;
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['username'] = $user['username'] ?? 'Usuario';
                $_SESSION['user_id'] = $user['id'] ?? $user['uuid'] ?? uniqid();
                $_SESSION['avatar_url'] = $user['avatar_url'] ?? '';
                break;
            }
        }
    }
    
    if ($loginSuccess) {
        $_SESSION['email'] = $targetEmail;
        $_SESSION['login_attempts'] = 0;
        echo "<script>window.location.href = window.location.pathname;</script>";
        exit;
    } else {
        $newSaved = [];
        foreach ($_SESSION['saved_accounts'] as $acc) {
            if (strtolower($acc['email']) !== strtolower($targetEmail)) {
                $newSaved[] = $acc;
            }
        }
        $_SESSION['saved_accounts'] = $newSaved;
        echo "<script>alert('Esta cuenta ya no existe en el sistema y fue eliminada de tu dispositivo.'); window.location.href = window.location.pathname;</script>";
        exit;
    }
}

// Limpeza de contas fantasmas silenciosa
if (file_exists($dbFile) && !empty($_SESSION['saved_accounts'])) {
    $usuariosDB = json_decode(file_get_contents($dbFile), true) ?: [];
    $validEmails = array_map('strtolower', array_column($usuariosDB, 'email'));

    $filteredAccounts = [];
    foreach ($_SESSION['saved_accounts'] as $acc) {
        if (in_array(strtolower($acc['email']), $validEmails)) {
            $filteredAccounts[] = $acc;
        }
    }
    if (count($filteredAccounts) !== count($_SESSION['saved_accounts'])) {
        $_SESSION['saved_accounts'] = $filteredAccounts;
    }
}

$savedAccounts = $_SESSION['saved_accounts'];
?>

<style>
/* ================= CSS DO HEADER E DROPDOWNS ================= */
.app-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 24px; background: transparent; position: relative; z-index: 900;
}

.header-btn {
    width: 42px; height: 42px; border-radius: 50%;
    border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-main);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; outline: none; -webkit-tap-highlight-color: transparent;
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), background 0.3s, border-color 0.3s;
}
.header-btn:active { transform: scale(0.92); background: var(--icon-bg); }

.header-actions { display: flex; align-items: center; gap: 12px; }

/* Avatar da Conta Logada */
.header-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: var(--icon-bg); color: var(--text-main);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.95rem; letter-spacing: 0.5px;
    cursor: pointer; border: 1px solid var(--card-border);
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    -webkit-tap-highlight-color: transparent; user-select: none;
    overflow: hidden; 
}
.header-avatar img { width: 100%; height: 100%; object-fit: cover; }
.header-avatar:active { transform: scale(0.92); }

/* Motor de Dropdowns Fluidos */
.dropdown-container { position: relative; }

/* [CORREÇÃO]: Removido overflow: hidden para permitir a setinha estilo balão */
.dropdown-menu {
    position: absolute; top: calc(100% + 12px); right: 0;
    background: var(--card-bg); border: 1px solid var(--card-border);
    border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    opacity: 0; visibility: hidden; transform: translateY(-10px) scale(0.98);
    transform-origin: top right; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}
.dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
:root.dark .dropdown-menu, .dark .dropdown-menu { box-shadow: 0 10px 40px rgba(0,0,0,0.6); }

/* --- A SETINHA ESTILO BALÃO DE PENSAMENTO 💭 --- */
.dropdown-menu::before, .dropdown-menu::after {
    content: ''; position: absolute; right: 14px;
    width: 0; height: 0; border-style: solid; pointer-events: none;
}
/* Borda da setinha */
.dropdown-menu::before {
    top: -9px; border-width: 0 9px 9px 9px;
    border-color: transparent transparent var(--card-border) transparent;
}
/* Preenchimento da setinha */
.dropdown-menu::after {
    top: -8px; border-width: 0 8px 8px 8px;
    border-color: transparent transparent var(--card-bg) transparent;
}

/* Fix de bordas arredondadas já que removemos o overflow:hidden */
.lang-menu > div:first-child, .account-menu > div:first-child { border-top-left-radius: 20px; border-top-right-radius: 20px; }
.lang-menu > div:last-child, .account-menu > a:last-child { border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }

/* --- Menu de Idiomas --- */
.lang-menu { width: 190px; }
.lang-option {
    display: flex; align-items: center; gap: 10px; padding: 14px 16px;
    color: var(--text-muted); font-size: 0.9rem; font-weight: 500;
    cursor: pointer; transition: background 0.2s, color 0.2s; -webkit-tap-highlight-color: transparent;
}
.lang-option:active { background: var(--icon-bg); }
.lang-option.active-lang { color: var(--text-main); font-weight: 600; }

/* --- Menu de Multi-Contas --- */
.account-menu { 
    width: 320px; 
    max-width: calc(100vw - 32px); 
    display: flex; flex-direction: column;
}
.acc-menu-header {
    padding: 20px 20px 8px; font-size: 0.65rem; font-weight: 800;
    color: var(--text-subtle); letter-spacing: 1px; text-transform: uppercase;
}
.acc-current-email { padding: 0 20px 16px; font-size: 0.9rem; color: var(--text-main); font-weight: 700; }

/* CAIXA DE ROLAGEM (Scroll) apenas para as contas salvas */
.acc-scroll-area {
    max-height: 250px; 
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--card-border) transparent;
}
.acc-scroll-area::-webkit-scrollbar { width: 4px; }
.acc-scroll-area::-webkit-scrollbar-thumb { background-color: var(--card-border); border-radius: 4px; }

/* Sistema de Impulso Adicionado (.acc-item) */
.acc-item {
    display: flex; align-items: center; gap: 12px; padding: 14px 20px;
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s; cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.acc-item:active { transform: scale(0.96); background: var(--icon-bg); }

.acc-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--icon-bg); color: var(--text-main);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.9rem; flex-shrink: 0; overflow: hidden;
}
.acc-avatar img { width: 100%; height: 100%; object-fit: cover; }

.acc-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.acc-name { font-size: 0.95rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.acc-email { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.acc-badge {
    background: var(--icon-bg); color: var(--text-muted); border: 1px solid var(--card-border);
    font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 8px; display: inline-flex; align-items: center; gap: 4px;
}
.acc-actions { display: flex; align-items: center; gap: 6px; }
.acc-btn {
    width: 36px; height: 36px; border-radius: 10px; border: 1px solid transparent;
    background: transparent; color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s; outline: none; -webkit-tap-highlight-color: transparent;
}
.acc-btn:active { transform: scale(0.90); }
.acc-btn.switch:active { background: var(--icon-bg); border-color: var(--card-border); }
.acc-btn.trash:active { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }

.acc-divider { height: 1px; background: var(--card-border); margin: 6px 0; }
.acc-bottom-action {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; color: var(--text-main); font-size: 0.95rem; font-weight: 600;
    cursor: pointer; transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s; text-decoration: none; -webkit-tap-highlight-color: transparent;
}
.acc-bottom-action:active { transform: scale(0.96); background: var(--icon-bg); }

@media (max-width: 600px) {
    .dropdown-menu { position: absolute; top: calc(100% + 12px); right: 0; }
}

/* ================= MODAL DE EXCLUSÃO (Conta Salva) ================= */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(5px);
    z-index: 9999999; display: flex; align-items: center; justify-content: center;
    opacity: 0; visibility: hidden; transition: all 0.3s;
}
.modal-overlay.show { opacity: 1; visibility: visible; }
.modal-box {
    background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 20px;
    width: 90%; max-width: 400px; transform: scale(0.9) translateY(20px); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.dark .modal-box { background: #1a1a1e; border-color: #27272a; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
.modal-overlay.show .modal-box { transform: scale(1) translateY(0); }
.modal-body { padding: 32px 24px 24px 24px; text-align: center; }
.modal-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(239,68,68,0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; }
.modal-title { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 8px 0; }
.dark .modal-title { color: #fff; }
.modal-desc { font-size: 0.95rem; color: var(--text-muted); margin: 0 0 20px 0; font-weight: 500;}
.modal-info-box { background: var(--inner-bg, #f3f4f6); border-radius: 12px; padding: 16px; font-size: 0.85rem; color: var(--text-main); font-weight: 600; }
.dark .modal-info-box { background: #121214; color: #fff; }
.modal-footer { display: flex; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--card-border); }
.btn-modal { flex: 1; padding: 14px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; transition: transform 0.15s, background 0.2s; outline: none;}
.btn-modal:active { transform: scale(0.94); }
.btn-cancel { background: transparent; border: 1px solid var(--card-border); color: var(--text-main); }
.dark .btn-cancel { color: #fff; }
.btn-delete { background: #ef4444; color: white; }
</style>

<header class="app-header">
    <button class="header-btn" onclick="forceOpenSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="header-actions">
        <div class="dropdown-container">
            <button class="header-btn" onclick="toggleDropdown('lang-dropdown', event)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
            </button>
            <div class="dropdown-menu lang-menu" id="lang-dropdown">
                <div class="lang-option" onclick="selectAppLang('pt')">
                    <svg id="chk-pt" style="width:16px;height:16px;opacity:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Português (Brasil)
                </div>
                <div class="lang-option" onclick="selectAppLang('en')">
                    <svg id="chk-en" style="width:16px;height:16px;opacity:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> English
                </div>
                <div class="lang-option" onclick="selectAppLang('es')">
                    <svg id="chk-es" style="width:16px;height:16px;opacity:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Español
                </div>
            </div>
        </div>

        <button class="header-btn" onclick="toggleThemeGlobal()" id="global-theme-btn">
            </button>

        <div class="dropdown-container">
            <div class="header-avatar" onclick="toggleDropdown('account-menu-dropdown', event)">
                <?php if (!empty($userAvatarUrl)): ?>
                    <img src="<?= htmlspecialchars($userAvatarUrl) ?>" alt="Foto de Perfil">
                <?php else: ?>
                    <?= $userInitials ?>
                <?php endif; ?>
            </div>
            
            <div class="dropdown-menu account-menu" id="account-menu-dropdown">
                <div class="acc-menu-header" data-i18n="logged_account">CUENTA LOGADA</div>
                <div class="acc-current-email"><?= htmlspecialchars($userEmail) ?></div>
                
                <div class="acc-item">
                    <div class="acc-avatar">
                        <?php if (!empty($userAvatarUrl)): ?>
                            <img src="<?= htmlspecialchars($userAvatarUrl) ?>" alt="Foto">
                        <?php else: ?>
                            <?= $userInitials ?>
                        <?php endif; ?>
                    </div>
                    <div class="acc-info">
                        <div class="acc-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="acc-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="acc-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:12px;height:12px;"><polyline points="20 6 9 17 4 12"/></svg>
                        <span data-i18n="current_acc">Atual</span>
                    </div>
                </div>
                
                <?php 
                $hasOtherAccounts = false;
                foreach($savedAccounts as $acc) {
                    if(strtolower($acc['email']) !== strtolower($userEmail) && strtolower($acc['email']) !== 'elnene.admin@gmail.com') { 
                        $hasOtherAccounts = true; 
                        break; 
                    }
                }
                ?>
                
                <?php if ($hasOtherAccounts): ?>
                <div class="acc-divider"></div>
                <div class="acc-menu-header" data-i18n="saved_accounts">CUENTAS SALVAS</div>
                
                <div class="acc-scroll-area">
                    <?php foreach($savedAccounts as $acc): ?>
                        <?php 
                        // Esconde a atual e previne 100% da admin aparecer aqui
                        if(strtolower($acc['email']) === strtolower($userEmail)) continue; 
                        if(strtolower($acc['email']) === 'elnene.admin@gmail.com') continue;
                        ?>
                        
                        <div class="acc-item" onclick="window.location.href='?action=switch_account&email=<?= urlencode($acc['email']) ?>'">
                            <div class="acc-avatar" style="background: var(--icon-bg); color: var(--text-main);">
                                <?php if (!empty($acc['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($acc['avatar_url']) ?>" alt="Foto">
                                <?php else: ?>
                                    <?= htmlspecialchars($acc['initials'] ?? 'U') ?>
                                <?php endif; ?>
                            </div>
                            <div class="acc-info">
                                <div class="acc-name"><?= htmlspecialchars($acc['name']) ?></div>
                                <div class="acc-email"><?= htmlspecialchars($acc['email']) ?></div>
                            </div>
                            <div class="acc-actions">
                                <button class="acc-btn switch" title="Iniciar sesión">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="9 18 15 12 9 6"/></svg>
                                </button>
                                <button class="acc-btn trash" onclick="event.stopPropagation(); openDeleteAccountModal('<?= addslashes($acc['email']) ?>')" title="Remover">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="acc-divider"></div>
                <a href="?action=add_account" class="acc-bottom-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span data-i18n="add_account">Agregar outra conta</span>
                </a>
                <a href="/logout" class="acc-bottom-action" style="padding-top:4px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span data-i18n="logout">Salir</span>
                </a>
            </div>
        </div>
    </div>
</header>

<div class="modal-overlay" id="deleteAccountModal" onclick="if(event.target===this) closeDeleteAccountModal()">
    <div class="modal-box">
        <div class="modal-body">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:28px;height:28px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <h3 class="modal-title" data-i18n="confirm_delete_title">Remover conta salva</h3>
            <p class="modal-desc">
                <span data-i18n="confirm_delete_msg">¿Estás seguro que querés eliminar la cuenta guardada de tu dispositivo?</span>
            </p>
            <div class="modal-info-box" id="del-acc-email">admin@exemplo.com</div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-cancel" onclick="closeDeleteAccountModal()" data-i18n="cancel">Cancelar</button>
            <button class="btn-modal btn-delete" onclick="confirmDeleteAccount()" data-i18n="delete">Eliminar</button>
        </div>
    </div>
</div>
<input type="hidden" id="del-acc-target">

<script>
// ==================== LÓGICA DE DROPDOWNS E EXCLUSÃO ====================
function toggleDropdown(id, event) {
    if(event) event.stopPropagation();
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if(menu.id !== id) menu.classList.remove('show');
    });
    const menu = document.getElementById(id);
    if(menu) menu.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-container')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));
    }
});

function openDeleteAccountModal(email) {
    document.getElementById('del-acc-email').innerText = email;
    document.getElementById('del-acc-target').value = email;
    document.getElementById('deleteAccountModal').classList.add('show');
    document.getElementById('account-menu-dropdown').classList.remove('show');
}

function closeDeleteAccountModal() {
    document.getElementById('deleteAccountModal').classList.remove('show');
}

function confirmDeleteAccount() {
    const email = document.getElementById('del-acc-target').value;
    window.location.href = '?action=remove_account&email=' + encodeURIComponent(email);
}

// ==================== DICIONÁRIO E MOTOR DE TRADUÇÃO GLOBAL ====================
// [CORREÇÃO]: Usando um dicionário exclusivo do Header para evitar conflitos com outras páginas.
window.headerTranslations = {
    'pt': {
        'logged_account': 'CUENTA LOGADA', 'current_acc': 'Atual', 'saved_accounts': 'CUENTAS SALVAS',
        'add_account': 'Agregar outra conta', 'logout': 'Salir',
        'confirm_delete_title': 'Remover conta salva',
        'confirm_delete_msg': '¿Estás seguro que querés eliminar la cuenta guardada de tu dispositivo?',
        'warning_undo': 'Esta acción no podrá deshacerse.',
        'cancel': 'Cancelar', 'delete': 'Eliminar',
        'status_stable': 'OPERAÇÃO ESTÁVEL', 'hello': 'Hola', 'welcome_desc': 'Acesse os módulos principais e acompanhe o ambiente.',
        'account': 'CUENTA', 'role_usuário': 'Usuario', 'role_administrador': 'Administrador',
        'expiration': 'EXPIRAÇÃO', 'days_left': '{days} dias restantes', 'settings': 'CONFIGURAÇÕES',
        'created_at': 'CRIADO EM', 'account_initial_date': 'Data inicial da conta', 'updated_at': 'ACTUALIZADO EL',
        'last_updated': 'Última actualización registrada', 'expires_at': 'EXPIRA EL', 'main_modules': 'Módulos principales',
        'modules_desc': 'Acceso rápido a los módulos más usados del panel.', 'total': 'TOTAL',
        'settings_desc': 'Gestioná modos, orden, categorías y distribución.', 'categories': 'Categorías',
        'categories_desc': 'Organizá grupos y mantené la estructura del panel limpia.', 'devices': 'Dispositivos',
        'devices_desc': 'Acompanhe o volume e a atividade dos dispositivos.', 'access': 'Acessar',
        'control_center': 'Control Center', 'home': 'Home', 'associated_users': 'Usuarios associados',
        'cdn': 'CDN', 'app': 'Aplicación', 'generate_apk': 'Generar APK', 'community_themes': 'Temas da comunidade',
        'texts': 'Textos', 'renew': 'Renovar', 'transactions': 'Transacciones', 'notifications': 'Notificaciones',
        'sessions': 'Sesiones', 'profile': 'Perfil', 'active_session': 'Sesión ativa'
    },
    'en': {
        'logged_account': 'LOGGED ACCOUNT', 'current_acc': 'Current', 'saved_accounts': 'SAVED ACCOUNTS',
        'add_account': 'Add another account', 'logout': 'Logout',
        'confirm_delete_title': 'Remove saved account', 'confirm_delete_msg': 'Are you sure you want to delete the saved account from this device?',
        'warning_undo': 'This action cannot be undone.', 'cancel': 'Cancel', 'delete': 'Delete',
        'status_stable': 'STABLE OPERATION', 'hello': 'Hello', 'welcome_desc': 'Access the main modules and monitor the environment.',
        'account': 'ACCOUNT', 'role_usuário': 'User', 'role_administrador': 'Admin',
        'expiration': 'EXPIRATION', 'days_left': '{days} days left', 'settings': 'SETTINGS',
        'created_at': 'CREATED AT', 'account_initial_date': 'Account creation date', 'updated_at': 'UPDATED AT',
        'last_updated': 'Last recorded update', 'expires_at': 'EXPIRES AT', 'main_modules': 'Main modules',
        'modules_desc': 'Quick access to the most used panel modules.', 'total': 'TOTAL',
        'settings_desc': 'Manage modes, order, categories and distribution.', 'categories': 'Categories',
        'categories_desc': 'Organize groups and keep the panel structure clean.', 'devices': 'Devices',
        'devices_desc': 'Track the volume and activity of devices.', 'access': 'Access',
        'control_center': 'Control Center', 'home': 'Home', 'associated_users': 'Associated Users',
        'cdn': 'CDN', 'app': 'Application', 'generate_apk': 'Generate APK', 'community_themes': 'Community Themes',
        'texts': 'Texts', 'renew': 'Renew', 'transactions': 'Transactions', 'notifications': 'Notifications',
        'sessions': 'Sessions', 'profile': 'Profile', 'active_session': 'Active session'
    },
    'es': {
        'logged_account': 'CUENTA CONECTADA', 'current_acc': 'Actual', 'saved_accounts': 'CUENTAS GUARDADAS',
        'add_account': 'Añadir otra cuenta', 'logout': 'Salir',
        'confirm_delete_title': 'Eliminar cuenta guardada', 'confirm_delete_msg': '¿Estás seguro de que deseas eliminar la cuenta guardada de este dispositivo?',
        'warning_undo': 'Esta acción no se puede deshacer.', 'cancel': 'Cancelar', 'delete': 'Eliminar',
        'status_stable': 'OPERACIÓN ESTABLE', 'hello': 'Hola', 'welcome_desc': 'Acceda a los módulos principales y monitoree el entorno.',
        'account': 'CUENTA', 'role_usuário': 'Usuario', 'role_administrador': 'Administrador',
        'expiration': 'EXPIRACIÓN', 'days_left': '{days} días restantes', 'settings': 'CONFIGURACIONES',
        'created_at': 'CREADO EN', 'account_initial_date': 'Fecha inicial de la cuenta', 'updated_at': 'ACTUALIZADO EN',
        'last_updated': 'Última actualización registrada', 'expires_at': 'EXPIRA EN', 'main_modules': 'Módulos principales',
        'modules_desc': 'Acceso rápido a los módulos más usados del panel.', 'total': 'TOTAL',
        'settings_desc': 'Administre modos, orden, categorías y distribución.', 'categories': 'Categorías',
        'categories_desc': 'Organice grupos y mantenga limpia la estructura del panel.', 'devices': 'Dispositivos',
        'devices_desc': 'Realice un seguimiento del volumen y la actividad de los dispositivos.', 'access': 'Acceder',
        'control_center': 'Centro de Control', 'home': 'Inicio', 'associated_users': 'Usuarios asociados',
        'cdn': 'CDN', 'app': 'Aplicación', 'generate_apk': 'Generar APK', 'community_themes': 'Temas de la comunidad',
        'texts': 'Textos', 'renew': 'Renovar', 'transactions': 'Transacciones', 'notifications': 'Notificaciones',
        'sessions': 'Sesiones', 'profile': 'Perfil', 'active_session': 'Sesión activa'
    }
};

window.selectAppLang = function(langCode) {
    localStorage.setItem('app_language', langCode);
    
    // [CORREÇÃO]: Faz um merge do dicionário do header com o dicionário global da página (se existir). 
    // Assim o menu da sidebar nunca vai voltar pra inglês atoa!
    const globalDict = window.globalTranslations && window.globalTranslations[langCode] ? window.globalTranslations[langCode] : {};
    const headerDict = window.headerTranslations[langCode] || window.headerTranslations['pt'];
    const finalDict = { ...globalDict, ...headerDict }; 
    
    document.querySelectorAll('.lang-option svg').forEach(svg => svg.style.opacity = '0');
    document.querySelectorAll('.lang-option').forEach(opt => opt.classList.remove('active-lang'));
    const chk = document.getElementById('chk-' + langCode);
    if(chk) { chk.style.opacity = '1'; chk.parentElement.classList.add('active-lang'); }
    
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (finalDict[key]) {
            let text = finalDict[key];
            if (el.hasAttribute('data-days')) text = text.replace('{days}', el.getAttribute('data-days'));
            el.textContent = text;
        }
    });
    
    const menu = document.getElementById('lang-dropdown');
    if(menu) menu.classList.remove('show');
};

// ==================== TEMA SOL/LUA ====================
window.toggleThemeGlobal = function() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('dtunnel-theme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
};

function updateThemeIcon(isDark) {
    const btn = document.getElementById('global-theme-btn');
    if(!btn) return;
    if(isDark) {
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
    } else {
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('app_language') || 'pt';
    selectAppLang(savedLang);
    updateThemeIcon(document.documentElement.classList.contains('dark'));
});

window.forceOpenSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if(sidebar) sidebar.classList.add('open');
    if(overlay) overlay.classList.add('show');
}
</script>
