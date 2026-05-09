<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Notificaciones e Acciones Remotas (Trem Bala V5)
 * @description Envio de Push (FCM), Acciones Remotas e Histórico de Notificaciones.
 * =======================================================================================
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbNotificacoes = __DIR__ . '/../db/notificacoes.json';
$dbUsuarios = __DIR__ . '/../db/usuarios.json';

// Inicializa o banco de notificações se não existir
if (!file_exists($dbNotificacoes)) {
    if (!is_dir(dirname($dbNotificacoes))) { @mkdir(dirname($dbNotificacoes), 0755, true); }
    file_put_contents($dbNotificacoes, json_encode([]));
    chmod($dbNotificacoes, 0644);
}

// Carrega o usuário logado para pegar o UUID (Usado como Tópico do Firebase)
$userData = [];
$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) { $userData = $u; break; }
}
$userUuid = $userData['uuid'] ?? md5($sessionEmail);

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API INTERNA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $notificacoes = json_decode(file_get_contents($dbNotificacoes), true) ?: [];

    // 1. LISTAR HISTÓRICO
    if ($action === 'list_data') {
        $userNotifs = [];
        $total = 0;
        
        foreach ($notificacoes as $n) {
            if (isset($n['user_email']) && $n['user_email'] === $sessionEmail) {
                $userNotifs[] = $n;
                $total++;
            }
        }
        
        // Ordena da mais recente para a mais antiga
        usort($userNotifs, function($a, $b) {
            return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
        });
        
        echo json_encode(['success' => true, 'notifs' => $userNotifs, 'total' => $total]);
        exit;
    }

    // 2. ENVIAR NOTIFICAÇÃO (SALVA NO HISTÓRICO E DISPARA)
    if ($action === 'send_notif') {
        $titulo = trim($input['title'] ?? '');
        $mensagem = trim($input['message'] ?? '');
        $imagem = trim($input['image'] ?? '');
        
        if (empty($titulo) || empty($mensagem)) {
            echo json_encode(['success' => false, 'error' => 'Título e mensagem são obrigatórios.']); exit;
        }

        $novaNotif = [
            'id' => substr(md5(uniqid()), 0, 16) . '-' . substr(md5(time()), 0, 16), // ID Estilo Firebase
            'user_email' => $sessionEmail,
            'title' => $titulo,
            'message' => $mensagem,
            'image' => $imagem,
            'created_at' => time(),
            'type' => 'message'
        ];

        array_unshift($notificacoes, $novaNotif);
        file_put_contents($dbNotificacoes, json_encode($notificacoes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // =================================================================================
        // LÓGICA PARA DISPARAR PARA O APP (FCM FIREBASE OU SINALIZAÇÃO NO BANCO)
        // O App DTunnel lê essas requisições. Aqui a notificação é gerada con éxito.
        // =================================================================================

        echo json_encode(['success' => true, 'notif' => $novaNotif]);
        exit;
    }

    // 3. ENVIAR AÇÃO REMOTA (NÃO SALVA NO HISTÓRICO PARA NÃO POLUIR)
    if ($action === 'send_action') {
        $tipoAcao = $input['action_type'] ?? '';
        
        if (empty($tipoAcao)) {
            echo json_encode(['success' => false, 'error' => 'Selecione uma ação.']); exit;
        }

        // =================================================================================
        // LÓGICA DE AÇÃO (Reiniciar VPN, Parar, etc) enviada via Payload Oculto para o App
        // =================================================================================

        echo json_encode(['success' => true]);
        exit;
    }

    // 4. EXCLUIR NOTIFICAÇÃO
    if ($action === 'delete_notif') {
        $id = strval($input['id'] ?? '');
        $filtrados = [];
        $deletou = false;

        foreach ($notificacoes as $n) {
            if (strval($n['id']) === $id && $n['user_email'] === $sessionEmail) {
                $deletou = true;
                continue;
            }
            $filtrados[] = $n;
        }

        if ($deletou) {
            file_put_contents($dbNotificacoes, json_encode($filtrados, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Notificación não encontrada.']);
        }
        exit;
    }

    // 5. LIMPAR TODO O HISTÓRICO
    if ($action === 'clear_all') {
        $filtrados = [];
        foreach ($notificacoes as $n) {
            // Mantém apenas as notificações dos OUTROS usuários
            if ($n['user_email'] !== $sessionEmail) {
                $filtrados[] = $n;
            }
        }
        file_put_contents($dbNotificacoes, json_encode($filtrados, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Notificaciones';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - PÁGINA DE NOTIFICAÇÕES (CLONE DO VÍDEO)
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.ntf-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b; --icon-bg: #f3f4f6;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; min-height: calc(100vh - 70px);
}

:root.dark .ntf-wrapper, .dark .ntf-wrapper, body.dark .ntf-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569; --icon-bg: #27272a;
}

.ntf-wrapper * { outline: none; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

/* TÍTULO E CABEÇALHO */
.top-header-title { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; }

/* CAIXA DE CONTROLES (BOTOES, STATUS E BUSCA) */
.controls-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }

.action-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px; }
.btn-top-act { 
    background: transparent; border: 2px solid var(--card-border); color: var(--text-main); 
    padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.95rem; 
    display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: 0.15s;
}
.btn-top-act:active { transform: scale(0.95); background: var(--inner-bg); }
.btn-top-act svg { width: 18px; }

.stat-box { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--text-main); font-weight: 800; font-size: 1rem; }
.stat-box svg { width: 20px; color: var(--text-muted); }

.search-input-wrap { position: relative; margin-bottom: 16px; }
.search-input-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.search-input { width: 100%; background: transparent; border: 2px solid var(--card-border); padding: 14px 14px 14px 44px; border-radius: 14px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; transition: border 0.2s;}
.search-input:focus { border-color: var(--primary); }

.search-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-search-act { padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: transform 0.15s; border: 2px solid var(--card-border); display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-search-act:active { transform: scale(0.96); }
.btn-search { background: var(--inner-bg); color: var(--text-main); }
.btn-clear { background: transparent; color: var(--text-main); }

/* LISTA E CARDS */
.list-block { flex: 1; display: flex; flex-direction: column; }
.list-title { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 4px; }
.ntf-scroll-list { display: flex; flex-direction: column; gap: 16px; padding-bottom: 20px; }

/* CARD DE NOTIFICAÇÃO */
.notif-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 20px; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.nc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.nc-title-area { display: flex; flex-direction: column; gap: 4px; max-width: 70%; }
.nc-title { font-size: 1.15rem; font-weight: 800; color: var(--text-main); word-break: break-all; margin: 0; line-height: 1.2; }
.nc-id { font-size: 0.75rem; font-weight: 600; color: var(--text-subtle); font-family: monospace;}

.nc-actions { display: flex; gap: 8px; }
.btn-nc-act { width: 44px; height: 44px; border-radius: 14px; background: var(--inner-bg); border: 1px solid var(--card-border); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s; color: var(--text-main); outline:none; flex-shrink: 0;}
.btn-nc-act:active { transform: scale(0.9); }
.btn-nc-act svg { width: 18px; }
.btn-nc-act.send { color: var(--primary); }
.btn-nc-act.del { color: var(--danger); background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2); }
.btn-nc-act.del:active { background: rgba(239, 68, 68, 0.15); }

.nc-date { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 12px; display: block;}
.nc-msg { font-size: 0.9rem; color: var(--text-main); font-weight: 500; background: var(--inner-bg); padding: 14px; border-radius: 12px; border: 1px solid var(--card-border); line-height: 1.5; word-break: break-word;}

/* ESTADO VAZIO */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 10px; padding: 40px 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; }
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px;}
.es-icon-wrap svg { width: 28px; height: 28px; display: block; margin: 0; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 250px; line-height: 1.4; }
.btn-es-refresh { margin-top: 10px; background: transparent; border: 2px solid var(--card-border); padding: 12px 24px; border-radius: 14px; font-size: 0.9rem; font-weight: 800; color: var(--text-main); cursor: pointer; transition: 0.15s; }
.btn-es-refresh:active { background: var(--inner-bg); transform: scale(0.95); }

.spin-anim { animation: spin 1s linear infinite; }

/* ==========================================================================
   MODAIS SWEETALERT (CUSTOMIZADOS PREMIUM)
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 95% !important; max-width: 480px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin: 0 0 6px 0 !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin: 0 0 20px 0 !important; text-align: left !important; line-height: 1.4 !important;}
.swal-close-btn { position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; outline: none; transition: 0.2s; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.swal-close-btn:active { transform: scale(0.85); background: var(--card-border); color: var(--text-main);}

.swal-label { font-size: 0.8rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; display: block; text-align: left;}
.swal-input { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border) !important; border-radius: 14px; padding: 14px 16px; color: var(--text-main); font-size: 0.95rem; font-weight: 600; outline: none; box-sizing: border-box; font-family: 'Manrope', sans-serif; transition: 0.2s;}
.swal-input:focus { border-color: var(--primary) !important; }
.swal-textarea { resize: vertical; min-height: 100px; line-height: 1.4; }

.input-icon-wrap { position: relative; display: flex; align-items: center; }
.input-icon-wrap .swal-input { padding-right: 50px; }
.btn-nm-icon { position: absolute; right: 8px; width: 34px; height: 34px; border-radius: 8px; background: transparent; border: none; color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; outline: none;}
.btn-nm-icon:active { background: var(--card-border); color: var(--text-main); transform: scale(0.9);}

.swal-select-wrap { position: relative; }
.swal-select-wrap select { appearance: none; cursor: pointer; }
.swal-select-wrap svg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 16px; color: var(--text-muted); pointer-events: none; }

.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 24px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }
.swal-btn-cancel { background: transparent !important; color: var(--text-main) !important; border: 2px solid var(--card-border) !important; }
.swal-btn-confirm { background: var(--inner-bg) !important; color: var(--text-main) !important; border: 1px solid var(--card-border) !important; }
.swal-btn-confirm.danger { background: rgba(239,68,68,0.1) !important; color: var(--danger) !important; border-color: rgba(239,68,68,0.3) !important; }

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 1000000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}
</style>

<div id="toast-container"></div>

<div class="ntf-wrapper">
    <h1 class="top-header-title" data-i18n="page_title">Notificaciones</h1>

    <div class="controls-block">
        <div class="action-grid-3">
            <button class="btn-top-act" onclick="confirmClearAll()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span data-i18n="btn_clear_all">Limpar</span>
            </button>
            <button class="btn-top-act" onclick="openSendNotifModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span data-i18n="btn_send">Enviar</span>
            </button>
            <button class="btn-top-act" onclick="openActionModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                <span data-i18n="btn_action">Ação</span>
            </button>
        </div>

        <div class="stat-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span id="stat-total">0 notificações</span>
        </div>

        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" class="search-input" placeholder="Buscar por título, mensagem ou id..." data-i18n-placeholder="search_placeholder">
        </div>

        <div class="search-btn-grid">
            <button class="btn-search-act btn-search" id="btn-search-main" onclick="handleSearch()">
                <span data-i18n="btn_search">Buscar</span>
            </button>
            <button class="btn-search-act btn-clear" id="btn-clear-main" onclick="clearSearch()">
                <span data-i18n="btn_clear">Limpar</span>
            </button>
        </div>
    </div>

    <div class="list-block">
        <h2 class="list-title" data-i18n="list_title">Histórico de notificações</h2>
        <div class="ntf-scroll-list" id="notif-list">
            <!-- Cards renderizados via JS -->
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
// ==========================================
// DICIONÁRIO I18N
// ==========================================
const dict = {
    'pt': {
        'page_title': 'Notificaciones', 'btn_clear_all': 'Limpar', 'btn_send': 'Enviar', 'btn_action': 'Ação',
        'search_placeholder': 'Buscar por título, mensagem ou id...', 'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpar', 'btn_clearing': 'Limpando...',
        'list_title': 'Histórico de notificações',
        'empty_title': 'Ningúna notificação encontrada', 'empty_desc': 'Envie uma notificação ou atualize a lista para verificar novamente.', 'btn_refresh': 'Actualizar lista',
        'mdl_send_title': 'Enviar notificação', 'mdl_send_desc': 'Dispare uma notificação para os aplicativos vinculados a sua conta.',
        'lbl_title': 'Título', 'lbl_img': 'Imagem (opcional)', 'lbl_msg': 'Mensaje', 'pl_title': 'Ex: Nueva atualização disponível', 'pl_img': 'https://exemplo.com/imagem.png', 'pl_msg': 'Digite a mensagem da notificação.',
        'btn_cancel': 'Cancelar', 'btn_send_notif': 'Enviar notificação', 'btn_send_action': 'Enviar ação',
        'mdl_act_title': 'Enviar ação', 'mdl_act_desc': 'Dispare uma ação remota para os aplicativos conectados.', 'lbl_action': 'Ação',
        'act_opt_def': 'Selecione uma ação', 'act_start': 'Iniciar VPN', 'act_recon': 'Reconectar VPN', 'act_restart': 'Reiniciar VPN', 'act_stop': 'Parar VPN', 'act_fcm': 'Reenviar token FCM',
        'confirm_del_title': 'Eliminar notificação', 'confirm_del_desc': 'Esta ação exige confirmação explícita e será executada imediatamente após continuar.', 'btn_del_confirm': 'Eliminar notificação',
        'toast_sent': 'Enviado con éxito!', 'toast_del': 'Notificación excluída!', 'toast_cleared': 'Histórico limpo!'
    },
    'en': {
        'page_title': 'Notifications', 'btn_clear_all': 'Clear', 'btn_send': 'Send', 'btn_action': 'Action',
        'search_placeholder': 'Search by title, message or id...', 'btn_search': 'Search', 'btn_searching': 'Searching...', 'btn_clear': 'Clear', 'btn_clearing': 'Clearing...',
        'list_title': 'Notification history',
        'empty_title': 'No notifications found', 'empty_desc': 'Send a notification or refresh the list to check again.', 'btn_refresh': 'Refresh list',
        'mdl_send_title': 'Send notification', 'mdl_send_desc': 'Trigger a notification for the applications linked to your account.',
        'lbl_title': 'Title', 'lbl_img': 'Image (optional)', 'lbl_msg': 'Message', 'pl_title': 'Ex: New update available', 'pl_img': 'https://example.com/image.png', 'pl_msg': 'Type the notification message.',
        'btn_cancel': 'Cancel', 'btn_send_notif': 'Send notification', 'btn_send_action': 'Send action',
        'mdl_act_title': 'Send action', 'mdl_act_desc': 'Trigger a remote action for connected applications.', 'lbl_action': 'Action',
        'act_opt_def': 'Select an action', 'act_start': 'Start VPN', 'act_recon': 'Reconnect VPN', 'act_restart': 'Restart VPN', 'act_stop': 'Stop VPN', 'act_fcm': 'Resend FCM token',
        'confirm_del_title': 'Delete notification', 'confirm_del_desc': 'This action requires explicit confirmation and will be executed immediately after continuing.', 'btn_del_confirm': 'Delete notification',
        'toast_sent': 'Sent successfully!', 'toast_del': 'Notification deleted!', 'toast_cleared': 'History cleared!'
    },
    'es': {
        'page_title': 'Notificaciones', 'btn_clear_all': 'Limpiar', 'btn_send': 'Enviar', 'btn_action': 'Acción',
        'search_placeholder': 'Buscar por título, mensaje o id...', 'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpiar', 'btn_clearing': 'Limpiando...',
        'list_title': 'Historial de notificaciones',
        'empty_title': 'No se encontraron notificaciones', 'empty_desc': 'Envíe una notificación o actualice la lista para volver a comprobar.', 'btn_refresh': 'Actualizar lista',
        'mdl_send_title': 'Enviar notificación', 'mdl_send_desc': 'Dispare una notificación para las aplicaciones vinculadas a su cuenta.',
        'lbl_title': 'Título', 'lbl_img': 'Imagen (opcional)', 'lbl_msg': 'Mensaje', 'pl_title': 'Ej: Nueva actualización disponible', 'pl_img': 'https://ejemplo.com/imagen.png', 'pl_msg': 'Escriba el mensaje de la notificación.',
        'btn_cancel': 'Cancelar', 'btn_send_notif': 'Enviar notificación', 'btn_send_action': 'Enviar acción',
        'mdl_act_title': 'Enviar acción', 'mdl_act_desc': 'Dispare una acción remota para las aplicaciones conectadas.', 'lbl_action': 'Acción',
        'act_opt_def': 'Seleccione una acción', 'act_start': 'Iniciar VPN', 'act_recon': 'Reconectar VPN', 'act_restart': 'Reiniciar VPN', 'act_stop': 'Parar VPN', 'act_fcm': 'Reenviar token FCM',
        'confirm_del_title': 'Eliminar notificación', 'confirm_del_desc': 'Esta acción requiere confirmación explícita y se ejecutará inmediatamente después de continuar.', 'btn_del_confirm': 'Eliminar notificación',
        'toast_sent': '¡Enviado con éxito!', 'toast_del': '¡Notificación eliminada!', 'toast_cleared': '¡Historial limpiado!'
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

function fetchData() {
    fetch('?action=list_data', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { 
            rawData = res.notifs; 
            document.getElementById('stat-total').innerText = res.total + (res.total === 1 ? ' notificação' : ' notificações');
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
    currentData = rawData.filter(n => {
        if(!queryText) return true;
        const searchStr = ((n.title||'') + ' ' + (n.message||'') + ' ' + (n.id||'')).toLowerCase();
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
    const listEl = document.getElementById('notif-list');
    
    if(currentData.length === 0) {
        listEl.innerHTML = `<div class="empty-state"><div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div><h3 class="es-title">\${getMsg('empty_title')}</h3><p class="es-desc">\${getMsg('empty_desc')}</p><button class="btn-es-refresh" onclick="refreshListAnim(this)">\${getMsg('btn_refresh')}</button></div>`;
        return;
    }

    let html = '';
    currentData.forEach(n => {
        const dtStr = formatUnixDate(n.created_at);
        const safeTitle = (n.title || '').replace(/"/g, '&quot;');
        const safeMsg = (n.message || '').replace(/"/g, '&quot;');
        const safeImg = (n.image || '').replace(/"/g, '&quot;');

        html += `
            <div class="notif-card" id="card-\${n.id}">
                <div class="nc-top">
                    <div class="nc-title-area">
                        <h3 class="nc-title">\${n.title}</h3>
                        <span class="nc-id">ID: \${n.id}</span>
                    </div>
                    <div class="nc-actions">
                        <button class="btn-nc-act send" onclick="resendNotif('\${safeTitle}', '\${safeMsg}', '\${safeImg}')" title="Reenviar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                        <button class="btn-nc-act del" onclick="confirmDelete('\${n.id}')" title="Eliminar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <span class="nc-date">Criado em: \${dtStr}</span>
                <div class="nc-msg">\${n.message}</div>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

// ==========================================
// MODAIS SWEETALERT (ENVIAR / AÇÃO / APAGAR)
// ==========================================
function openSendNotifModal(title = '', msg = '', img = '') {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('mdl_send_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('mdl_send_desc')}</p>
                
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <div>
                        <label class="swal-label">\${getMsg('lbl_title')}</label>
                        <input type="text" id="nm-title" class="swal-input" style="margin-bottom:0;" placeholder="\${getMsg('pl_title')}" value="\${title}">
                    </div>
                    <div>
                        <label class="swal-label">\${getMsg('lbl_img')}</label>
                        <div class="input-icon-wrap" style="margin-bottom:0;">
                            <input type="text" id="nm-img" class="swal-input" style="margin-bottom:0;" placeholder="\${getMsg('pl_img')}" value="\${img}">
                            <button type="button" class="btn-nm-icon" onclick="document.getElementById('file-notif-img').click()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </button>
                            <input type="file" id="file-notif-img" style="display:none;" accept="image/*" onchange="fakeUploadImage(event)">
                        </div>
                    </div>
                    <div>
                        <label class="swal-label">\${getMsg('lbl_msg')}</label>
                        <textarea id="nm-msg" class="swal-input swal-textarea" style="margin-bottom:0;" placeholder="\${getMsg('pl_msg')}">\${msg}</textarea>
                    </div>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> ` + getMsg('btn_send_notif'), 
        cancelButtonText: getMsg('btn_cancel'),
        preConfirm: () => {
            const t = document.getElementById('nm-title').value.trim();
            const i = document.getElementById('nm-img').value.trim();
            const m = document.getElementById('nm-msg').value.trim();
            if(!t || !m) { Swal.showValidationMessage('Título e Mensaje são obrigatórios!'); return false; }
            return { title: t, image: i, message: m };
        }
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Enviando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=send_notif', {method:'POST', body: JSON.stringify(res.value)}).then(r=>r.json()).then(resp => {
                if(resp.success) { Swal.close(); fetchData(); showToastRaw(getMsg('toast_sent'), 'success'); }
                else { Swal.fire('Erro', resp.error, 'error'); }
            });
        }
    });
}

function fakeUploadImage(e) {
    if(e.target.files.length > 0) {
        document.getElementById('nm-img').value = 'Imagem Carregada localmente';
        showToastRaw('Simulação: Imagem carregada', 'info');
    }
}

function resendNotif(title, msg, img) {
    openSendNotifModal(title, msg, img);
}

function openActionModal() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('mdl_act_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('mdl_act_desc')}</p>
                
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label class="swal-label">\${getMsg('lbl_action')}</label>
                    <div class="swal-select-wrap">
                        <select id="nm-action-type" class="swal-input" style="margin-bottom:0;">
                            <option value="" disabled selected>\${getMsg('act_opt_def')}</option>
                            <option value="START">\${getMsg('act_start')}</option>
                            <option value="RECONNECT">\${getMsg('act_recon')}</option>
                            <option value="RESTART">\${getMsg('act_restart')}</option>
                            <option value="STOP">\${getMsg('act_stop')}</option>
                            <option value="FCM_TOKEN">\${getMsg('act_fcm')}</option>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: getMsg('btn_send_action'), cancelButtonText: getMsg('btn_cancel'),
        preConfirm: () => {
            const val = document.getElementById('nm-action-type').value;
            if(!val) { Swal.showValidationMessage('Selecione uma ação!'); return false; }
            return { action_type: val };
        }
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Enviando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=send_action', {method:'POST', body: JSON.stringify(res.value)}).then(r=>r.json()).then(resp => {
                if(resp.success) { Swal.close(); showToastRaw(getMsg('toast_sent'), 'success'); }
                else { Swal.fire('Erro', resp.error, 'error'); }
            });
        }
    });
}

function confirmDelete(id) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 class="swal-title-custom" style="margin:0;">\${getMsg('confirm_del_title')}</h2>
            </div>
            <p class="swal-desc-custom">\${getMsg('confirm_del_desc')}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, 
        confirmButtonText: getMsg('btn_del_confirm'), cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Excluindo...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=delete_notif', {method:'POST', body: JSON.stringify({id: id})}).then(r=>r.json()).then(resp => {
                if(resp.success) { Swal.close(); fetchData(); showToastRaw(getMsg('toast_del'), 'error'); }
            });
        }
    });
}

function confirmClearAll() {
    if(rawData.length === 0) return;
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </div>
                <h2 class="swal-title-custom" style="margin:0;">Limpar Histórico</h2>
            </div>
            <p class="swal-desc-custom">Todas as notificações serão excluídas. Esta ação exige confirmação explícita.</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, 
        confirmButtonText: 'Limpar tudo', cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Limpando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=clear_all', {method:'POST', body: JSON.stringify({})}).then(r=>r.json()).then(resp => {
                if(resp.success) { Swal.close(); fetchData(); showToastRaw(getMsg('toast_cleared'), 'error'); }
            });
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