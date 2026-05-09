<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Usuarios Asociados V1 (Trem Bala)
 * @description UI idêntica aos prints, busca dinâmica de UUID em tempo real e DB isolado.
 * =======================================================================================
 */

// Anti-Cache e Segurança
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

// Definição dos Bancos de Dados
$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbAssociated = __DIR__ . '/../db/associated_users.json'; // Nuevo banco para as associações

// Cria o arquivo de associados com segurança (Evita Erro 500)
if (!file_exists($dbAssociated)) {
    $dir = dirname($dbAssociated);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dbAssociated, json_encode([]));
    @chmod($dbAssociated, 0644);
}

// Carrega dados do Usuario Logado para pegar o UUID dele (bloqueio de auto-associação)
$userData = [];
$usuarios = file_exists($dbUsuarios) ? (json_decode(file_get_contents($dbUsuarios), true) ?: []) : [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) { 
        $userData = $u; 
        break; 
    }
}
$userUuid = $userData['uuid'] ?? ($userData['id'] ?? '---');

// ======================================================================
// PROCESSAMENTO AJAX (API INTERNA TREM BALA)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $associated = json_decode(file_get_contents($dbAssociated), true) ?: [];

    // 1. CHECAR SE O UUID EXISTE (Para a mágica visual do Modal)
    if ($action === 'check_uuid') {
        $targetUuid = trim($input['uuid'] ?? '');
        if (empty($targetUuid)) { echo json_encode(['success' => false]); exit; }
        
        // Bloqueia auto-associação
        if ($targetUuid === $userUuid) {
            echo json_encode(['success' => false, 'error' => 'self', 'message' => 'Você não pode associar seu próprio ID.']); exit;
        }

        $foundUser = null;
        foreach ($usuarios as $u) {
            if (($u['uuid'] ?? '') === $targetUuid || strval($u['id'] ?? '') === $targetUuid) {
                $foundUser = [
                    'name' => $u['username'] ?? 'Usuario',
                    'email' => $u['email'] ?? '',
                    'avatar' => $u['avatar_url'] ?? ''
                ];
                break;
            }
        }

        if ($foundUser) {
            echo json_encode(['success' => true, 'user' => $foundUser]);
        } else {
            echo json_encode(['success' => false, 'error' => 'not_found', 'message' => 'Usuario não encontrado no sistema.']);
        }
        exit;
    }

    // 2. ASSOCIAR O USUÁRIO DEFINITIVAMENTE NO DB
    if ($action === 'associate_user') {
        $targetUuid = trim($input['uuid'] ?? '');
        if (empty($targetUuid) || $targetUuid === $userUuid) { 
            echo json_encode(['success' => false, 'error' => 'UUID Inválido ou não permitido.']); exit; 
        }

        // Verifica se ele já está na lista
        foreach ($associated as $a) {
            if ($a['owner_email'] === $sessionEmail && $a['associated_uuid'] === $targetUuid) {
                echo json_encode(['success' => false, 'error' => 'Este usuário já está na sua lista de associados!']); exit;
            }
        }

        // Confirma se o usuário realmente existe
        $foundUser = null;
        foreach ($usuarios as $u) {
            if (($u['uuid'] ?? '') === $targetUuid || strval($u['id'] ?? '') === $targetUuid) {
                $foundUser = $u; break;
            }
        }

        if (!$foundUser) {
            echo json_encode(['success' => false, 'error' => 'Usuario inexistente.']); exit;
        }

        $newAssoc = [
            'id' => time() . rand(1000, 9999),
            'owner_email' => $sessionEmail,
            'associated_uuid' => $targetUuid,
            'associated_name' => $foundUser['username'] ?? 'Usuario',
            'associated_email' => $foundUser['email'] ?? '',
            'associated_avatar' => $foundUser['avatar_url'] ?? '',
            'created_at' => time()
        ];

        array_unshift($associated, $newAssoc);
        file_put_contents($dbAssociated, json_encode($associated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        echo json_encode(['success' => true, 'assoc' => $newAssoc]); exit;
    }

    // 3. REMOVER ASSOCIAÇÃO
    if ($action === 'delete_association') {
        $id = (int)($input['id'] ?? 0);
        $associated = array_filter($associated, function($a) use ($id, $sessionEmail) {
            return !((int)$a['id'] === $id && $a['owner_email'] === $sessionEmail);
        });
        file_put_contents($dbAssociated, json_encode(array_values($associated), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]); exit;
    }

    // 4. LISTAR USUÁRIOS ASSOCIADOS PARA A TELA
    if ($action === 'list_data') {
        $myAssociated = array_filter($associated, function($a) use ($sessionEmail) {
            return $a['owner_email'] === $sessionEmail;
        });
        
        // Atualiza nomes e fotos em tempo real caso o usuário associado tenha mudado de foto/nome
        $updatedAssociated = [];
        foreach ($myAssociated as $acc) {
            $currentData = $acc;
            foreach ($usuarios as $u) {
                if (($u['uuid'] ?? '') === $acc['associated_uuid'] || strval($u['id'] ?? '') === $acc['associated_uuid']) {
                    $currentData['associated_name'] = $u['username'] ?? $acc['associated_name'];
                    $currentData['associated_email'] = $u['email'] ?? $acc['associated_email'];
                    $currentData['associated_avatar'] = $u['avatar_url'] ?? $acc['associated_avatar'];
                    break;
                }
            }
            $updatedAssociated[] = $currentData;
        }

        echo json_encode(['success' => true, 'associados' => array_values($updatedAssociated)]); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Usuarios associados';
ob_start();
?>

<!-- Importação do SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - USUÁRIOS ASSOCIADOS V1
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.assoc-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; height: calc(100vh - 70px);
}
:root.dark .assoc-wrapper, .dark .assoc-wrapper, body.dark .assoc-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569;
}
.assoc-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; box-sizing: border-box; transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s, background-color 0.2s; }

/* BLOCO SUPERIOR (PESQUISA E BOTÃO GIGANTE) */
.search-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 20px; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.assoc-title-main { font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; }

/* Botão Agregar Idêntico ao Print */
.btn-primary-outline { 
    width: 100%; background: transparent; border: 2px solid var(--card-border); color: var(--text-main); 
    padding: 16px; border-radius: 16px; font-weight: 800; font-size: 1rem;
    display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; 
    transition: transform 0.15s, background 0.2s; outline: none; margin-bottom: 20px;
}
.btn-primary-outline:active { transform: scale(0.97); background: var(--inner-bg); }

.search-input-wrap { position: relative; margin-bottom: 16px; }
.search-input-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; }
.search-input { width: 100%; background: transparent; border: 2px solid var(--card-border); padding: 16px 16px 16px 44px; border-radius: 16px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; transition: border 0.2s;}
.search-input:focus { border-color: var(--primary); }

/* Botões Buscar / Limpar */
.search-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-search-action { padding: 14px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: transform 0.15s; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; outline: none; font-size: 0.95rem;}
.btn-search-action:active { transform: scale(0.96); }
.btn-search { background: var(--inner-bg); color: var(--text-main); border: 2px solid var(--card-border); }
.btn-clear { background: transparent; color: var(--text-main); border: 2px solid var(--card-border); }

.spin-anim { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* BLOCO DA LISTA */
.list-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; flex: 1; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.list-title { padding: 24px 24px 10px 24px; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; }

.assoc-scroll-list { flex: 1; overflow-y: auto; padding: 10px 24px 24px 24px; display: flex; flex-direction: column; gap: 16px; scrollbar-width: none; }
.assoc-scroll-list::-webkit-scrollbar { display: none; }

/* Empty State Centralizado Perfeito (Idêntico ao Print) */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; gap: 10px; padding: 20px; flex: 1; }
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px; flex-shrink: 0;}
.es-icon-wrap svg { width: 24px; height: 24px; display: block; margin: 0; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 280px; line-height: 1.4; }
.btn-es-refresh { margin-top: 10px; background: transparent; border: 2px solid var(--card-border); padding: 12px 24px; border-radius: 14px; font-size: 0.9rem; font-weight: 800; color: var(--text-main); cursor: pointer; transition: 0.15s; display: flex; align-items: center; justify-content: center; gap: 8px;}
.btn-es-refresh:active { background: var(--inner-bg); transform: scale(0.95); }

/* Card de Usuario Associado */
.user-card { background: transparent; border: 2px solid var(--card-border); border-radius: 16px; padding: 16px; display: flex; justify-content: space-between; align-items: center; gap: 14px; transition: border-color 0.2s;}
.uc-left { display: flex; align-items: center; gap: 14px; overflow: hidden; flex: 1; }
.uc-avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--inner-bg); border: 2px solid var(--card-border); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800; color: var(--text-muted); flex-shrink: 0; overflow: hidden;}
.uc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.uc-info { display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
.uc-name { font-size: 1.05rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.uc-id { font-size: 0.75rem; font-weight: 700; color: var(--text-subtle); font-family: 'Space Grotesk', monospace; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}

.uc-btn-del { width: 44px; height: 44px; border-radius: 12px; border: 2px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.05); color: var(--danger); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s; flex-shrink: 0; outline: none;}
.uc-btn-del:active { transform: scale(0.85); background: var(--danger); color: white; border-color: var(--danger); }

/* Paginação */
.pagination-box { display: none; padding: 16px 24px; justify-content: flex-end; align-items: center; gap: 16px; flex-wrap: wrap; border-top: 1px solid var(--card-border);}
.pg-items { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-subtle); }
.pg-select { background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-main); border-radius: 10px; padding: 6px 10px; font-weight: 800; outline: none; cursor: pointer; }
.pg-controls { display: flex; align-items: center; gap: 8px; }
.btn-pg { width: 38px; height: 38px; border: 2px solid var(--card-border); background: transparent; border-radius: 12px; color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s;}
.btn-pg:active { background: var(--inner-bg); transform: scale(0.9); }
.btn-pg:disabled { opacity: 0.3; cursor: not-allowed; }
.pg-info { background: var(--inner-bg); border: 2px solid var(--card-border); height: 38px; padding: 0 16px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; color: var(--text-main); }

/* ==========================================================================
   MODAIS SWEETALERT E TOASTS (IDÊNTICO AOS PRINTS)
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 95% !important; max-width: 450px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 6px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 24px !important; text-align: left !important;}
.swal-close-btn { position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; outline: none; transition: 0.15s;}
.swal-close-btn:active { transform: scale(0.85); color: var(--text-main);}

.swal-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block; text-align: left; letter-spacing: 0.5px;}
.swal-input { width: 100%; background: var(--inner-bg); border: 2px solid var(--card-border); border-radius: 14px; padding: 14px 16px; color: var(--text-main); font-size: 0.95rem; font-weight: 700; margin-bottom: 20px; outline: none; transition: border 0.2s; box-sizing: border-box; font-family: 'Manrope', sans-serif;}
.swal-input:focus { border-color: var(--primary); }

.input-icon-wrap { position: relative; margin-bottom: 20px; }
.input-icon-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.input-icon-wrap .swal-input { margin-bottom: 0; padding-left: 44px; }

/* A Caixa "Aguardando ID" Mágica do Print */
.id-preview-box { 
    background: var(--inner-bg); border: 2px solid var(--card-border); border-radius: 14px; 
    padding: 14px 16px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
    transition: all 0.3s;
}
.id-preview-box.success { background: rgba(16,185,129,0.05); border-color: rgba(16,185,129,0.3); }
.id-preview-box.error { background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.3); }

.id-preview-icon { color: var(--text-muted); width: 24px; height: 24px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden;}
.id-preview-icon img { width: 100%; height: 100%; object-fit: cover; }
.id-preview-box.success .id-preview-icon { color: var(--success); }
.id-preview-box.error .id-preview-icon { color: var(--danger); }

.id-preview-text { font-size: 0.95rem; font-weight: 800; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; text-align: left;}
.id-preview-box.success .id-preview-text { color: var(--text-main); }
.id-preview-box.error .id-preview-text { color: var(--danger); }

.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }

.swal-btn-cancel { background: #f3f4f6 !important; color: #111827 !important; border: 2px solid var(--card-border) !important; }
.dark .swal-btn-cancel { background: #27272a !important; color: #ffffff !important; border-color: var(--card-border) !important; }
.swal-btn-confirm { background: #64748b !important; color: #ffffff !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; }

/* TOASTS (Notificaciones Trem Bala) */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}
</style>

<div id="toast-container"></div>

<div class="assoc-wrapper">
    
    <div class="search-block">
        <h1 class="assoc-title-main" data-i18n="assoc_title">Usuarios associados</h1>
        
        <button class="btn-primary-outline" onclick="openAssociateModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span data-i18n="btn_assoc">Associar</span>
        </button>

        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" class="search-input" placeholder="Buscar usuário associado por nome ou id..." data-i18n-placeholder="search_placeholder">
        </div>

        <div class="search-btn-grid">
            <button class="btn-search-action btn-search" id="btn-search-main" onclick="handleSearch()">
                <span data-i18n="btn_search">Buscar</span>
            </button>
            <button class="btn-search-action btn-clear" id="btn-clear-main" onclick="clearSearch()">
                <span data-i18n="btn_clear">Limpar</span>
            </button>
        </div>
    </div>

    <div class="list-block">
        <h2 class="list-title" data-i18n="list_title">Lista de usuários associados</h2>
        
        <div class="assoc-scroll-list" id="assoc-list">
            <!-- JS Renderiza Cards aqui -->
        </div>

        <div class="pagination-box" id="pagination-container">
            <div class="pg-items">
                <span data-i18n="items_page">Itens por página</span>
                <select class="pg-select" id="items-per-page" onchange="changeItemsPerPage()">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="40">40</option>
                </select>
            </div>
            <div class="pg-controls">
                <button class="btn-pg" id="btn-prev" onclick="changePage(-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><polyline points="15 18 9 12 15 6"/></svg></button>
                <div class="pg-info" id="page-info">1/1</div>
                <button class="btn-pg" id="btn-next" onclick="changePage(1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><polyline points="9 18 15 12 9 6"/></svg></button>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
// ==========================================
// MOTOR DE TRADUÇÃO E TEXTOS
// ==========================================
const dict = {
    'pt': {
        'assoc_title': 'Usuarios associados', 'btn_assoc': 'Associar',
        'search_placeholder': 'Buscar usuário associado por nome ou id...',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpar', 'btn_clearing': 'Limpando...',
        'list_title': 'Lista de usuários associados', 
        'empty_title': 'Ningún usuário associado encontrado', 'empty_desc': 'Associe um usuário pelo ID ou ajuste a pesquisa atual.', 'btn_refresh': 'Actualizar lista',
        'items_page': 'Itens por página', 'toast_saved': 'Usuario associado con éxito!', 'toast_deleted': 'Associação removida!',
        'confirm_del_title': 'Remover associação', 'confirm_del_desc': 'Deseja realmente remover <b>{name}</b> dos seus associados?', 'delete': 'Remover', 'btn_cancel': 'Cancelar',
        'mdl_assoc_title': 'Associar usuário', 'mdl_assoc_desc': 'Informe o ID do usuário que deve ficar associado a sua conta.', 'lbl_id': 'ID do usuário', 'pl_id': 'Cole o ID do usuário', 'wait_id': 'Aguardando ID...', 'btn_associate': 'Associar'
    },
    'en': {
        'assoc_title': 'Associated users', 'btn_assoc': 'Associate',
        'search_placeholder': 'Search associated user by name or id...',
        'btn_search': 'Search', 'btn_searching': 'Searching...', 'btn_clear': 'Clear', 'btn_clearing': 'Clearing...',
        'list_title': 'Associated users list', 
        'empty_title': 'No associated users found', 'empty_desc': 'Associate a user by ID or adjust current search.', 'btn_refresh': 'Refresh list',
        'items_page': 'Items per page', 'toast_saved': 'User successfully associated!', 'toast_deleted': 'Association removed!',
        'confirm_del_title': 'Remove association', 'confirm_del_desc': 'Do you really want to remove <b>{name}</b> from your associates?', 'delete': 'Remove', 'btn_cancel': 'Cancel',
        'mdl_assoc_title': 'Associate user', 'mdl_assoc_desc': 'Enter the ID of the user that should be associated with your account.', 'lbl_id': 'User ID', 'pl_id': 'Paste user ID', 'wait_id': 'Waiting for ID...', 'btn_associate': 'Associate'
    },
    'es': {
        'assoc_title': 'Usuarios asociados', 'btn_assoc': 'Asociar',
        'search_placeholder': 'Buscar usuario asociado por nombre o id...',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpiar', 'btn_clearing': 'Limpiando...',
        'list_title': 'Lista de usuarios asociados', 
        'empty_title': 'No se encontraron usuarios asociados', 'empty_desc': 'Asocia un usuario por ID o ajusta la búsqueda actual.', 'btn_refresh': 'Actualizar lista',
        'items_page': 'Ítems por pág', 'toast_saved': '¡Usuario asociado con éxito!', 'toast_deleted': '¡Asociación eliminada!',
        'confirm_del_title': 'Eliminar asociación', 'confirm_del_desc': '¿Deseas realmente eliminar a <b>{name}</b> de tus asociados?', 'delete': 'Eliminar', 'btn_cancel': 'Cancelar',
        'mdl_assoc_title': 'Asociar usuario', 'mdl_assoc_desc': 'Informe el ID del usuario que debe estar asociado a su cuenta.', 'lbl_id': 'ID del usuario', 'pl_id': 'Pegue el ID del usuario', 'wait_id': 'Esperando ID...', 'btn_associate': 'Asociar'
    }
};

let rawData = [];
let currentData = []; 
let currentPage = 1;
let itemsPerPage = 20;
let queryText = ''; 

function getMsg(key) { const lang = localStorage.getItem('app_language') || 'pt'; return dict[lang] && dict[lang][key] ? dict[lang][key] : (dict['pt'][key] || key); }

function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(el => { el.innerHTML = getMsg(el.getAttribute('data-i18n')); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = getMsg(el.getAttribute('data-i18n-placeholder')); });
}
const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLang) originalSelectLang(langCode); applyI18n(); renderList(); };

// ==========================================
// TOAST NOTIFICATIONS (Verde / Vermelho com X)
// ==========================================
function showToastRaw(text, type = 'success') {
    const container = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast \${type}`;
    let iconSvg = '<polyline points="20 6 9 17 4 12"/>';
    if (type === 'info') iconSvg = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
    if (type === 'error') iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'; // Ícone X Vermelho
    
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${text}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}
function showToast(msgKey, type = 'success') { showToastRaw(getMsg(msgKey), type); }

// ==========================================
// BUSCAR E FILTRAR (TREM BALA)
// ==========================================
function fetchData() {
    fetch('?action=list_data', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { rawData = res.associados; executeSearchLogic(); }
    });
}

function getSpinIcon() { return `<svg class="spin-anim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>`; }

function handleSearch() {
    const btn = document.getElementById('btn-search-main');
    btn.innerHTML = getSpinIcon() + `<span data-i18n="btn_searching">\${getMsg('btn_searching')}</span>`;
    queryText = document.getElementById('search-input').value.toLowerCase();
    setTimeout(() => { executeSearchLogic(); btn.innerHTML = `<span data-i18n="btn_search">\${getMsg('btn_search')}</span>`; }, 500);
}

function clearSearch() {
    const btn = document.getElementById('btn-clear-main');
    btn.innerHTML = getSpinIcon() + `<span data-i18n="btn_clearing">\${getMsg('btn_clearing')}</span>`;
    document.getElementById('search-input').value = '';
    queryText = '';
    setTimeout(() => { executeSearchLogic(); showToastRaw('Pesquisa limpa.', 'info'); btn.innerHTML = `<span data-i18n="btn_clear">\${getMsg('btn_clear')}</span>`; }, 500);
}

function executeSearchLogic() {
    currentData = rawData.filter(c => {
        if(!queryText) return true;
        const searchStr = (c.associated_name + ' ' + c.associated_uuid + ' ' + c.associated_email).toLowerCase();
        return searchStr.includes(queryText);
    });
    currentPage = 1; renderList();
}

function refreshListAnim(btn) {
    btn.innerHTML = getSpinIcon() + `Atualizando...`;
    setTimeout(() => { fetchData(); }, 600);
}

function changeItemsPerPage() { itemsPerPage = parseInt(document.getElementById('items-per-page').value); currentPage = 1; renderList(); }
function changePage(delta) { currentPage += delta; renderList(); }

// ==========================================
// RENDERIZAR CARDS
// ==========================================
function renderList() {
    const listEl = document.getElementById('assoc-list'); const pgContainer = document.getElementById('pagination-container');
    
    if(currentData.length === 0) {
        listEl.innerHTML = `<div class="empty-state"><div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div><h3 class="es-title">\${getMsg('empty_title')}</h3><p class="es-desc">\${getMsg('empty_desc')}</p><button class="btn-es-refresh" onclick="refreshListAnim(this)">\${getMsg('btn_refresh')}</button></div>`;
        pgContainer.style.display = 'none'; return;
    }

    const totalPages = Math.ceil(currentData.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginated = currentData.slice(startIndex, startIndex + itemsPerPage);

    let html = '';
    paginated.forEach(c => {
        const initial = c.associated_name.charAt(0).toUpperCase();
        const avatarHtml = c.associated_avatar ? `<img src="\${c.associated_avatar}" alt="Avatar">` : initial;
        
        html += `
            <div class="user-card" id="card-\${c.id}">
                <div class="uc-left">
                    <div class="uc-avatar">\${avatarHtml}</div>
                    <div class="uc-info">
                        <span class="uc-name">\${c.associated_name}</span>
                        <span class="uc-id">ID: \${c.associated_uuid}</span>
                    </div>
                </div>
                <button class="uc-btn-del" onclick="confirmDel(\${c.id}, '\${c.associated_name}')" title="Remover">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            </div>
        `;
    });
    
    listEl.innerHTML = html;
    pgContainer.style.display = 'flex';
    document.getElementById('page-info').innerText = `\${currentPage}/\${totalPages}`;
    document.getElementById('btn-prev').disabled = currentPage === 1;
    document.getElementById('btn-next').disabled = currentPage === totalPages;
}

// ==========================================
// EXCLUIR ASSOCIAÇÃO (Modal + X Vermelho)
// ==========================================
function confirmDel(id, name) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><h2 class="swal-title-custom">\${getMsg('confirm_del_title')}</h2></div><p class="swal-desc-custom">\${getMsg('confirm_del_desc').replace('{name}', name)}</p>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('delete'), cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            rawData = rawData.filter(c => parseInt(c.id) !== parseInt(id)); executeSearchLogic();
            fetch('?action=delete_association', {method:'POST', body: JSON.stringify({id})}).then(() => showToast('toast_deleted', 'error'));
        }
    });
}

// ==========================================
// MODAL MÁGICO: ASSOCIAR USUÁRIO
// ==========================================
let typingTimer;

function openAssociateModal() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('mdl_assoc_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('mdl_assoc_desc')}</p>
                
                <div class="id-preview-box" id="assoc-preview">
                    <div class="id-preview-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                    <span class="id-preview-text" id="assoc-preview-txt">\${getMsg('wait_id')}</span>
                </div>

                <label class="swal-label">\${getMsg('lbl_id')}</label>
                <div class="input-icon-wrap">
                    <input type="text" id="assoc-input-id" class="swal-input" placeholder="\${getMsg('pl_id')}" oninput="triggerCheckUuid(this.value)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: getMsg('btn_associate'), cancelButtonText: getMsg('btn_cancel'),
        preConfirm: () => {
            const uuid = document.getElementById('assoc-input-id').value.trim();
            if(!uuid) { Swal.showValidationMessage('Cole o ID do usuário!'); return false; }
            return uuid;
        }
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Associando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=associate_user', {method:'POST', body: JSON.stringify({uuid: res.value})}).then(r=>r.json()).then(resp => {
                if(resp.success) { Swal.close(); fetchData(); showToast('toast_saved', 'success'); }
                else { Swal.fire({title:'Ops!', text:resp.error || 'Erro', icon:'error', background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', customClass: {popup: 'swal-modal-custom'}}); }
            });
        }
    });
}

// A Magia de Buscar o ID em tempo real no Modal
window.triggerCheckUuid = function(val) {
    clearTimeout(typingTimer);
    const box = document.getElementById('assoc-preview');
    const txt = document.getElementById('assoc-preview-txt');
    const icon = box.querySelector('.id-preview-icon');
    
    box.className = 'id-preview-box'; // Reseta
    txt.innerText = getMsg('wait_id');
    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

    if(val.length > 5) {
        typingTimer = setTimeout(() => {
            fetch('?action=check_uuid', {method:'POST', body: JSON.stringify({uuid: val.trim()})}).then(r=>r.json()).then(res => {
                if(res.success && res.user) {
                    box.className = 'id-preview-box success';
                    txt.innerText = res.user.name;
                    if(res.user.avatar) icon.innerHTML = `<img src="\${res.user.avatar}">`;
                    else icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                } else {
                    box.className = 'id-preview-box error';
                    txt.innerText = res.message || 'Usuario não encontrado!';
                    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                }
            });
        }, 500);
    }
};

document.addEventListener('DOMContentLoaded', () => { fetchData(); applyI18n(); });
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }
?>