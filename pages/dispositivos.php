<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Dispositivos Conectados (Trem Bala V5)
 * @description Rastreio em tempo real, envio de Push Notification e Gestão de Acessos.
 * =======================================================================================
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbUsuarios     = __DIR__ . '/../db/usuarios.json';
$dbDispositivos = __DIR__ . '/../db/dispositivos.json';

// Inicializa o banco de dispositivos se não existir
if (!file_exists($dbDispositivos)) {
    if (!is_dir(dirname($dbDispositivos))) { @mkdir(dirname($dbDispositivos), 0755, true); }
    file_put_contents($dbDispositivos, json_encode([]));
    chmod($dbDispositivos, 0644);
}

// Carrega o usuário logado para verificar se é Admin
$userData = [];
$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) { $userData = $u; break; }
}
$isAdmin = (($userData['role'] ?? 'user') === 'admin' || strtolower($sessionEmail) === 'elnene.admin@gmail.com');

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API INTERNA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $dispositivos = json_decode(file_get_contents($dbDispositivos), true) ?: [];

    // 1. LISTAR DISPOSITIVOS
    if ($action === 'list_data') {
        $userDevices = [];
        $total = 0; $online = 0;
        
        foreach ($dispositivos as $d) {
            // Se for admin vê todos, se for usuário vê apenas os que acessaram o app dele
            if ($isAdmin || (isset($d['owner_email']) && $d['owner_email'] === $sessionEmail)) {
                $userDevices[] = $d;
                $total++;
                if (($d['status'] ?? 'offline') === 'online') { $online++; }
            }
        }
        
        // Ordena pelos vistos mais recentemente
        usort($userDevices, function($a, $b) {
            return ($b['last_seen'] ?? 0) - ($a['last_seen'] ?? 0);
        });
        
        echo json_encode(['success' => true, 'devices' => $userDevices, 'stats' => ['total' => $total, 'online' => $online]]);
        exit;
    }

    // 2. EXCLUIR DISPOSITIVO
    if ($action === 'delete_device') {
        $id = strval($input['id'] ?? '');
        $filtrados = [];
        $deletou = false;

        foreach ($dispositivos as $d) {
            if (strval($d['id']) === $id) {
                if ($isAdmin || (isset($d['owner_email']) && $d['owner_email'] === $sessionEmail)) {
                    $deletou = true;
                    continue; // Pula este para deletar
                }
            }
            $filtrados[] = $d;
        }

        if ($deletou) {
            file_put_contents($dbDispositivos, json_encode($filtrados, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Dispositivo não encontrado ou permissão negada.']);
        }
        exit;
    }

    // 3. ENVIAR NOTIFICAÇÃO (Simulação de Envio Push)
    if ($action === 'send_notification') {
        $id = $input['id'] ?? '';
        $titulo = $input['title'] ?? '';
        $msg = $input['message'] ?? '';
        
        if (empty($id) || empty($msg)) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos.']); exit;
        }
        
        // Aqui entraria a lógica real de disparar via Firebase/OneSignal
        // Vamos simular sucesso
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Dispositivos';
ob_start();
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - PÁGINA DE DISPOSITIVOS
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.dev-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b; --icon-bg: #f3f4f6;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; min-height: calc(100vh - 70px);
}

:root.dark .dev-wrapper, .dark .dev-wrapper, body.dark .dev-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569; --icon-bg: #27272a;
}

.dev-wrapper * { outline: none; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

/* BLOCO SUPERIOR (ESTATÍSTICAS E BUSCA) */
.top-header-title { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; }

.search-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 24px; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }

.btn-mapa { width: 100%; background: transparent; border: 2px solid var(--card-border); color: var(--text-main); padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: transform 0.15s, background 0.2s; outline: none; margin-bottom: 16px;}
.btn-mapa:active { transform: scale(0.96); background: var(--inner-bg); }
.btn-mapa svg { width: 18px; color: var(--text-muted); }

.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.stat-box { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--text-main); font-weight: 800; font-size: 0.95rem; }
.stat-box svg { width: 18px; color: var(--text-muted); }
.stat-box.online svg { color: var(--success); }

.search-input-wrap { position: relative; margin-bottom: 16px; }
.search-input-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.search-input { width: 100%; background: transparent; border: 2px solid var(--card-border); padding: 14px 14px 14px 44px; border-radius: 14px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; transition: border 0.2s;}
.search-input:focus { border-color: var(--primary); }

.search-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-action-top { padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: transform 0.15s; border: 2px solid var(--card-border); display: flex; align-items: center; justify-content: center; gap: 8px; outline: none;}
.btn-action-top:active { transform: scale(0.96); }
.btn-search { background: var(--inner-bg); color: var(--text-main); }
.btn-clear { background: transparent; color: var(--text-main); }

/* BLOCO DA LISTA */
.list-block { flex: 1; display: flex; flex-direction: column; }
.list-title { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 4px; }
.dev-scroll-list { display: flex; flex-direction: column; gap: 16px; padding-bottom: 20px; }

/* CARD DE DISPOSITIVO */
.dev-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 20px; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }

.dc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.dc-h-left { display: flex; gap: 12px; align-items: flex-start; }
.dc-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--icon-bg); border: 1px solid var(--card-border); display: flex; align-items: center; justify-content: center; color: var(--text-main); flex-shrink: 0; }
.dc-icon svg { width: 22px; }
.dc-info { display: flex; flex-direction: column; gap: 2px; }
.dc-os { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }
.dc-owner { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

.dc-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid transparent; }
.dc-badge.offline { background: rgba(239, 68, 68, 0.1); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }
.dc-badge.online { background: rgba(16, 185, 129, 0.1); color: var(--success); border-color: rgba(16, 185, 129, 0.2); }

.dc-date { font-size: 0.8rem; font-weight: 700; color: var(--text-subtle); margin-bottom: 20px; }

.dc-fields { display: flex; flex-direction: column; gap: 12px; border-top: 1px dashed var(--card-border); padding-top: 16px; margin-bottom: 16px;}
.dc-field { display: flex; flex-direction: column; gap: 4px; }
.dc-lbl { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
.dc-lbl svg { width: 14px; }
.dc-val { font-size: 0.95rem; font-weight: 700; color: var(--text-main); word-break: break-all; }
.dc-loc-box { background: var(--inner-bg); border: 1px solid var(--card-border); padding: 12px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-top: 4px; }

.dc-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-dc-act { padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; cursor: pointer; transition: transform 0.15s; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; outline: none;}
.btn-dc-act:active { transform: scale(0.96); }
.btn-dc-act.notif { background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); }
.btn-dc-act.del { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); }

/* VAZIO */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 10px; padding: 40px 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; }
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px;}
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 250px; line-height: 1.4; }
.btn-es-refresh { margin-top: 10px; background: transparent; border: 2px solid var(--card-border); padding: 12px 24px; border-radius: 14px; font-size: 0.9rem; font-weight: 800; color: var(--text-main); cursor: pointer; transition: 0.15s; }
.btn-es-refresh:active { background: var(--inner-bg); transform: scale(0.95); }

.spin-anim { animation: spin 1s linear infinite; }

/* MODAL DE NOTIFICAÇÃO (CUSTOMIZADO) */
#notifModalOverlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(5px);
    z-index: 999999; display: flex; align-items: center; justify-content: center;
    opacity: 0; visibility: hidden; transition: opacity 0.3s ease; padding: 16px;
}
#notifModalOverlay.show { opacity: 1; visibility: visible; }

.notif-box {
    width: 100%; max-width: 500px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px;
    display: flex; flex-direction: column; transform: scale(0.95) translateY(20px); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); overflow: hidden; font-family: 'Manrope', sans-serif;
}
#notifModalOverlay.show .notif-box { transform: scale(1) translateY(0); }

.notif-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 24px; border-bottom: 1px solid var(--card-border); }
.notif-title-wrap h2 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); }
.notif-title-wrap p { margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-muted); font-weight: 500; line-height: 1.3;}
.btn-close-notif { width: 36px; height: 36px; border-radius: 50%; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; outline: none; flex-shrink: 0; }
.btn-close-notif:active { transform: scale(0.9); background: var(--danger); color: #fff; border-color: var(--danger);}

.notif-body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.nm-field { display: flex; flex-direction: column; gap: 6px; }
.nm-label { font-size: 0.8rem; font-weight: 800; color: var(--text-main); }

.nm-device-badge { background: var(--inner-bg); border: 1px solid var(--card-border); padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px; }
.nm-device-badge svg { width: 18px; color: var(--text-muted); }
.nm-dev-id { font-size: 0.85rem; font-weight: 800; color: var(--text-muted); word-break: break-all;}

.nm-input { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 14px 16px; color: var(--text-main); font-size: 0.95rem; font-weight: 600; outline: none; transition: 0.2s; font-family: 'Manrope', sans-serif;}
.nm-input:focus { border-color: var(--primary); }
.nm-textarea { resize: vertical; min-height: 100px; }

.nm-input-icon-wrap { position: relative; display: flex; }
.nm-input-icon-wrap .nm-input { padding-right: 50px; }
.btn-nm-icon { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 8px; background: transparent; border: none; color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;}
.btn-nm-icon:active { background: var(--card-border); color: var(--text-main); transform: translateY(-50%) scale(0.9);}

.notif-footer { display: flex; gap: 12px; padding: 20px 24px; border-top: 1px solid var(--card-border); background: var(--card-bg); }
.btn-nf { flex: 1; padding: 16px; border-radius: 14px; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: 0.15s; border: none; display: flex; align-items: center; justify-content: center; gap: 8px;}
.btn-nf:active { transform: scale(0.96); }
.btn-nf-cancel { background: transparent; border: 1px solid var(--card-border); color: var(--text-main); }
.btn-nf-send { background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); }

/* SWAL CUSTOM PARA DELETE */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin: 0 !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.9rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-top: 12px !important; text-align: left !important;}
.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 20px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; font-size: 0.95rem !important; border: none !important; cursor: pointer !important; transition: transform 0.15s !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }
.swal-btn-cancel { background: var(--inner-bg) !important; color: var(--text-main) !important; border: 1px solid var(--card-border) !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; color: white !important; }

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 10000000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}
</style>

<div id="toast-container"></div>

<div class="dev-wrapper">
    <h1 class="top-header-title" data-i18n="dev_title">Dispositivos</h1>

    <div class="search-block">
        <button class="btn-mapa">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span data-i18n="btn_map">Mapa</span>
        </button>

        <div class="stats-grid">
            <div class="stat-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                <span id="stat-total">0 dispositivos</span>
            </div>
            <div class="stat-box online">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M2 12h4l2-9 5 18 2-9h5"/></svg>
                <span id="stat-online">0 online</span>
            </div>
        </div>

        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" class="search-input" placeholder="Buscar por dispositivo, usuário, versão ou id..." data-i18n-placeholder="search_placeholder">
        </div>

        <div class="search-btn-grid">
            <button class="btn-action-top btn-search" id="btn-search-main" onclick="handleSearch()">
                <span data-i18n="btn_search">Buscar</span>
            </button>
            <button class="btn-action-top btn-clear" id="btn-clear-main" onclick="clearSearch()">
                <span data-i18n="btn_clear">Limpar</span>
            </button>
        </div>
    </div>

    <div class="list-block">
        <h2 class="list-title" data-i18n="list_title">Lista de dispositivos</h2>
        
        <div class="dev-scroll-list" id="device-list">
            <!-- Cards rendered via JS -->
        </div>
    </div>
</div>

<!-- MODAL DE NOTIFICAÇÃO CUSTOMIZADO -->
<div id="notifModalOverlay" onclick="closeNotifModal(event)">
    <div class="notif-box" onclick="event.stopPropagation()">
        <div class="notif-header">
            <div class="notif-title-wrap">
                <h2 data-i18n="mdl_notif_title">Notificar dispositivo</h2>
                <p data-i18n="mdl_notif_desc">Envie uma notificação direta para este dispositivo.</p>
            </div>
            <button type="button" class="btn-close-notif" onclick="closeNotifModal(event, true)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="notif-body">
            <div class="nm-device-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                <span class="nm-dev-id" id="nm-dev-id-lbl">ID: 0000000</span>
            </div>
            <input type="hidden" id="nm-input-id">

            <div class="nm-field">
                <label class="nm-label" data-i18n="lbl_notif_title">Título</label>
                <input type="text" id="nm-input-title" class="nm-input" placeholder="Ex: Atualização disponível">
            </div>

            <div class="nm-field">
                <label class="nm-label" data-i18n="lbl_notif_img">Imagem (opcional)</label>
                <div class="nm-input-icon-wrap">
                    <input type="text" id="nm-input-img" class="nm-input" placeholder="https://exemplo.com/imagem.png">
                    <button type="button" class="btn-nm-icon" onclick="document.getElementById('file-notif-img').click()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </button>
                    <input type="file" id="file-notif-img" style="display:none;" accept="image/*" onchange="fakeUploadImage(event, 'nm-input-img')">
                </div>
            </div>

            <div class="nm-field">
                <label class="nm-label" data-i18n="lbl_notif_msg">Mensaje</label>
                <textarea id="nm-input-msg" class="nm-input nm-textarea" placeholder="Digite a mensagem que será enviada para o aplicativo."></textarea>
            </div>
        </div>
        <div class="notif-footer">
            <button type="button" class="btn-nf btn-nf-cancel" onclick="closeNotifModal(event, true)" data-i18n="btn_cancel">Cancelar</button>
            <button type="button" class="btn-nf btn-nf-send" onclick="sendNotification()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span data-i18n="btn_send_notif">Enviar notificação</span>
            </button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$isAdminJs = $isAdmin ? 'true' : 'false';

$extraJs = <<<JS
<script>
// ==========================================
// DICIONÁRIO I18N
// ==========================================
const dict = {
    'pt': {
        'dev_title': 'Dispositivos', 'btn_map': 'Mapa', 'search_placeholder': 'Buscar por dispositivo, usuário, versão ou id...',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpar', 'btn_clearing': 'Limpando...',
        'list_title': 'Lista de dispositivos', 
        'empty_title': 'Ningún dispositivo encontrado', 'empty_desc': 'Ajuste o termo pesquisado ou tente novamente mais tarde.', 'btn_refresh': 'Actualizar lista',
        'lbl_user': 'USUÁRIO', 'lbl_ver': 'VERSÃO DO APP', 'lbl_id': 'ID', 'lbl_created': 'CRIADO EM', 'lbl_loc': 'LOCALIZAÇÃO', 'loc_unavail': 'Localização indisponível para este dispositivo.',
        'not_informed': 'Não informado', 'btn_notif': 'Notificar', 'btn_rem': 'Remover',
        'mdl_notif_title': 'Notificar dispositivo', 'mdl_notif_desc': 'Envie uma notificação direta para este dispositivo.',
        'lbl_notif_title': 'Título', 'lbl_notif_img': 'Imagem (opcional)', 'lbl_notif_msg': 'Mensaje', 'btn_cancel': 'Cancelar', 'btn_send_notif': 'Enviar notificação',
        'confirm_del_title': 'Remover dispositivo', 'confirm_del_desc': 'Esta ação exclui o dispositivo e encerra o acesso vinculado a ele. Esta ação exige confirmação explícita e será executada imediatamente após continuar.', 'btn_del_confirm': 'Remover dispositivo',
        'toast_del': 'Dispositivo removido!', 'toast_notif': 'Notificación enviada!'
    },
    'en': {
        'dev_title': 'Devices', 'btn_map': 'Map', 'search_placeholder': 'Search by device, user, version or id...',
        'btn_search': 'Search', 'btn_searching': 'Searching...', 'btn_clear': 'Clear', 'btn_clearing': 'Clearing...',
        'list_title': 'Device list', 
        'empty_title': 'No devices found', 'empty_desc': 'Adjust search term or try again later.', 'btn_refresh': 'Refresh list',
        'lbl_user': 'USER', 'lbl_ver': 'APP VERSION', 'lbl_id': 'ID', 'lbl_created': 'CREATED AT', 'lbl_loc': 'LOCATION', 'loc_unavail': 'Location unavailable for this device.',
        'not_informed': 'Not informed', 'btn_notif': 'Notify', 'btn_rem': 'Remove',
        'mdl_notif_title': 'Notify device', 'mdl_notif_desc': 'Send a direct notification to this device.',
        'lbl_notif_title': 'Title', 'lbl_notif_img': 'Image (optional)', 'lbl_notif_msg': 'Message', 'btn_cancel': 'Cancel', 'btn_send_notif': 'Send notification',
        'confirm_del_title': 'Remove device', 'confirm_del_desc': 'This action deletes the device and terminates linked access. This requires explicit confirmation and runs immediately.', 'btn_del_confirm': 'Remove device',
        'toast_del': 'Device removed!', 'toast_notif': 'Notification sent!'
    },
    'es': {
        'dev_title': 'Dispositivos', 'btn_map': 'Mapa', 'search_placeholder': 'Buscar por dispositivo, usuario, versión o id...',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpiar', 'btn_clearing': 'Limpiando...',
        'list_title': 'Lista de dispositivos', 
        'empty_title': 'Ningún dispositivo encontrado', 'empty_desc': 'Ajuste la búsqueda o intente más tarde.', 'btn_refresh': 'Actualizar lista',
        'lbl_user': 'USUARIO', 'lbl_ver': 'VERSIÓN APP', 'lbl_id': 'ID', 'lbl_created': 'CREADO EN', 'lbl_loc': 'UBICACIÓN', 'loc_unavail': 'Ubicación no disponible para este dispositivo.',
        'not_informed': 'No informado', 'btn_notif': 'Notificar', 'btn_rem': 'Remover',
        'mdl_notif_title': 'Notificar dispositivo', 'mdl_notif_desc': 'Envíe una notificación directa a este dispositivo.',
        'lbl_notif_title': 'Título', 'lbl_notif_img': 'Imagen (opcional)', 'lbl_notif_msg': 'Mensaje', 'btn_cancel': 'Cancelar', 'btn_send_notif': 'Enviar notificación',
        'confirm_del_title': 'Remover dispositivo', 'confirm_del_desc': 'Esta acción elimina el dispositivo y finaliza el acceso. Esto requiere confirmación explícita y se ejecuta de inmediato.', 'btn_del_confirm': 'Remover dispositivo',
        'toast_del': '¡Dispositivo removido!', 'toast_notif': '¡Notificación enviada!'
    }
};

function getMsg(key) { const lang = localStorage.getItem('app_language') || 'pt'; return dict[lang] && dict[lang][key] ? dict[lang][key] : (dict['pt'][key] || key); }
function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(el => { el.innerHTML = getMsg(el.getAttribute('data-i18n')); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = getMsg(el.getAttribute('data-i18n-placeholder')); });
}
const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLang) originalSelectLang(langCode); applyI18n(); renderList(); };

// ==========================================
// TOASTS E SPINNER
// ==========================================
function showToastRaw(text, type = 'success') {
    const container = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast \${type}`;
    let iconSvg = '<polyline points="20 6 9 17 4 12"/>';
    if (type === 'info') iconSvg = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
    if (type === 'error') iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${text}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}

function getSpinIcon() { return `<svg class="spin-anim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>`; }

// ==========================================
// LÓGICA PRINCIPAL DE DADOS
// ==========================================
let rawData = [];
let currentData = [];
let queryText = '';
const isAdmin = $isAdminJs;

function fetchData() {
    fetch('?action=list_data', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { 
            rawData = res.devices; 
            document.getElementById('stat-total').innerText = res.stats.total + ' dispositivos';
            document.getElementById('stat-online').innerText = res.stats.online + ' online';
            executeSearchLogic(); 
        }
    });
}

function handleSearch() {
    const btn = document.getElementById('btn-search-main');
    btn.innerHTML = getSpinIcon() + `<span data-i18n="btn_searching">\${getMsg('btn_searching')}</span>`;
    queryText = document.getElementById('search-input').value.toLowerCase();
    setTimeout(() => { executeSearchLogic(); btn.innerHTML = `<span data-i18n="btn_search">\${getMsg('btn_search')}</span>`; }, 500);
}

function clearSearch() {
    const btn = document.getElementById('btn-clear-main');
    btn.innerHTML = getSpinIcon() + `<span data-i18n="btn_clearing">\${getMsg('btn_clearing')}</span>`;
    document.getElementById('search-input').value = ''; queryText = '';
    setTimeout(() => { executeSearchLogic(); btn.innerHTML = `<span data-i18n="btn_clear">\${getMsg('btn_clear')}</span>`; }, 500);
}

function executeSearchLogic() {
    currentData = rawData.filter(d => {
        if(!queryText) return true;
        const searchStr = ((d.os||'') + ' ' + (d.app_user||'') + ' ' + (d.app_version||'') + ' ' + (d.id||'')).toLowerCase();
        return searchStr.includes(queryText);
    });
    renderList();
}

function refreshListAnim(btn) {
    btn.innerHTML = getSpinIcon() + `Atualizando...`;
    setTimeout(() => { fetchData(); }, 600);
}

function formatUnixDate(unixTime) {
    if(!unixTime) return '---';
    const dt = new Date(unixTime * 1000);
    return dt.toLocaleDateString('pt-BR') + ', ' + dt.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
}

function renderList() {
    const listEl = document.getElementById('device-list');
    
    if(currentData.length === 0) {
        listEl.innerHTML = `<div class="empty-state"><div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div><h3 class="es-title">\${getMsg('empty_title')}</h3><p class="es-desc">\${getMsg('empty_desc')}</p><button class="btn-es-refresh" onclick="refreshListAnim(this)">\${getMsg('btn_refresh')}</button></div>`;
        return;
    }

    let html = '';
    currentData.forEach(d => {
        const isOnline = (d.status || 'offline') === 'online';
        const badgeClass = isOnline ? 'online' : 'offline';
        const badgeText = isOnline ? 'Online' : 'Offline';
        const dateSeen = formatUnixDate(d.last_seen);
        const dateCreated = formatUnixDate(d.created_at);
        
        // Se for admin, mostra o dono real do app (email/nome). Se não, mostra o usuário comum ou 'Não informado'
        let ownerHtml = '';
        if(isAdmin) {
            const donoName = d.owner_name ? d.owner_name : (d.owner_email || 'Desconhecido');
            ownerHtml = `<div class="dc-owner" style="color:var(--primary);">App de: \${donoName}</div>`;
        } else {
            ownerHtml = `<div class="dc-owner">\${d.owner_name || 'Usuario'}</div>`;
        }

        html += `
            <div class="dev-card" id="card-\${d.id}">
                <div class="dc-header">
                    <div class="dc-h-left">
                        <div class="dc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
                        <div class="dc-info">
                            <span class="dc-os">\${d.os || 'Android'}</span>
                            \${ownerHtml}
                        </div>
                    </div>
                    <div class="dc-badge \${badgeClass}">\${badgeText}</div>
                </div>
                <div class="dc-date">\${dateSeen}</div>
                
                <div class="dc-fields">
                    <div class="dc-field"><span class="dc-lbl" data-i18n="lbl_user">USUÁRIO</span><span class="dc-val">\${d.app_user || getMsg('not_informed')}</span></div>
                    <div class="dc-field"><span class="dc-lbl" data-i18n="lbl_ver">VERSÃO DO APP</span><span class="dc-val">\${d.app_version || '---'}</span></div>
                    <div class="dc-field"><span class="dc-lbl" data-i18n="lbl_id">ID</span><span class="dc-val" style="font-family:monospace;">\${d.id}</span></div>
                    <div class="dc-field"><span class="dc-lbl" data-i18n="lbl_created">CRIADO EM</span><span class="dc-val">\${dateCreated}</span></div>
                    
                    <div class="dc-field" style="margin-top:10px;">
                        <span class="dc-lbl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <span data-i18n="lbl_loc">LOCALIZAÇÃO</span></span>
                        <div class="dc-loc-box">\${d.location || getMsg('loc_unavail')}</div>
                    </div>
                </div>

                <div class="dc-actions">
                    <button class="btn-dc-act notif" onclick="openNotifModal('\${d.id}')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span data-i18n="btn_notif">Notificar</span>
                    </button>
                    <button class="btn-dc-act del" onclick="confirmDeleteDevice('\${d.id}')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span data-i18n="btn_rem">Remover</span>
                    </button>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

// ==========================================
// MODAL E EXCLUSÃO
// ==========================================
function confirmDeleteDevice(id) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        html: `
            <div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getMsg('confirm_del_title')}</h2>
            </div>
            <p class="swal-desc-custom" style="text-align:left;">\${getMsg('confirm_del_desc')}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('btn_del_confirm'), cancelButtonText: getMsg('btn_cancel')
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({title:'Procesando', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'} });
            fetch('?action=delete_device', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: id}) })
            .then(r=>r.json()).then(res => { 
                Swal.close();
                if(res.success) { showToastRaw(getMsg('toast_del'), 'error'); fetchData(); } 
                else { Swal.fire('Erro', res.error, 'error'); } 
            });
        }
    });
}

// ==========================================
// MODAL DE NOTIFICAÇÃO (CUSTOM HTML)
// ==========================================
function openNotifModal(id) {
    document.getElementById('nm-dev-id-lbl').innerText = 'ID: ' + id;
    document.getElementById('nm-input-id').value = id;
    document.getElementById('nm-input-title').value = '';
    document.getElementById('nm-input-img').value = '';
    document.getElementById('nm-input-msg').value = '';
    document.getElementById('notifModalOverlay').classList.add('show');
}

function closeNotifModal(e, force = false) {
    if(e && e.target.id !== 'notifModalOverlay' && !force) return;
    document.getElementById('notifModalOverlay').classList.remove('show');
}

function fakeUploadImage(e, targetId) {
    if(e.target.files.length > 0) {
        document.getElementById(targetId).value = 'Imagem Carregada localmente';
        showToastRaw('Simulação: Imagem carregada', 'info');
    }
}

function sendNotification() {
    const id = document.getElementById('nm-input-id').value;
    const title = document.getElementById('nm-input-title').value.trim();
    const img = document.getElementById('nm-input-img').value.trim();
    const msg = document.getElementById('nm-input-msg').value.trim();

    if(!msg) { showToastRaw('A mensagem é obrigatória', 'error'); return; }

    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({title:'Enviando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
    
    fetch('?action=send_notification', {method: 'POST', body: JSON.stringify({id: id, title: title, message: msg, image: img})})
    .then(r=>r.json()).then(res => {
        Swal.close();
        if(res.success) {
            closeNotifModal(null, true);
            showToastRaw(getMsg('toast_notif'), 'success');
        } else {
            Swal.fire('Erro', res.error, 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => { fetchData(); applyI18n(); });
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }
?>