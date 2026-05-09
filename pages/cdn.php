<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de CDN Trem Bala V2 (Definitiva)
 * @description Layout 100% fiel, extração inteligente de Domínio e Gatilho de Versión.
 * =======================================================================================
 */

// Bloqueia cache do navegador para sempre exibir a versão mais recente
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbUsuarios = __DIR__ . '/../db/usuarios.json';
$dbCdn      = __DIR__ . '/../db/cdn.json';
$dbVersion  = __DIR__ . '/../db/version.json'; // Arquivo de controle de versão do app

// Inicializa arquivos se não existirem
foreach ([$dbCdn, $dbVersion] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) { mkdir(dirname($file), 0755, true); }
        // Inicia a versão no 100 se for o arquivo version, senão array vazio
        $defaultContent = ($file === $dbVersion) ? ['version' => 100] : [];
        file_put_contents($file, json_encode($defaultContent));
        chmod($file, 0644);
    }
}

// Carrega Usuario (Obtém UUID)
$userData = [];
$usuarios = file_exists($dbUsuarios) ? json_decode(file_get_contents($dbUsuarios), true) ?: [] : [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) { 
        $userData = $u; 
        break; 
    }
}
$userUuid = $userData['uuid'] ?? '---';

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API INTERNA TREM BALA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $cdns = json_decode(file_get_contents($dbCdn), true) ?: [];

    // Lógica de Atualização de Versión: Sobe +1 sempre que algo mudar
    $updateVersion = function() use ($dbVersion) {
        $v = json_decode(file_get_contents($dbVersion), true) ?: ['version' => 100];
        $v['version'] = (isset($v['version']) ? (int)$v['version'] : 100) + 1;
        file_put_contents($dbVersion, json_encode($v, JSON_PRETTY_PRINT));
    };

    // ==========================================
    // SALVAR / EDITAR CDN
    // ==========================================
    if ($action === 'save_cdn') {
        $id   = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $url  = trim($input['url'] ?? '');

        if (empty($name) || empty($url)) { 
            echo json_encode(['success' => false, 'error' => 'Nombre e URL são obrigatórios.']); 
            exit; 
        }

        // O App DTunnel reconhece a CDN pelo Hash MD5 de 32 Caracteres (Ex: 9ef19c7390...)
        $status = $input['status'] ?? 'ACTIVE';
        $cdnData = [
            'id'         => $id ? $id : md5(uniqid(mt_rand(), true)),
            'user_email' => $sessionEmail,
            'name'       => $name,
            'url'        => $url,
            'status'     => strtoupper($status) === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ];

        if ($id) {
            $found = false;
            foreach ($cdns as &$c) {
                if (strval($c['id']) === strval($id) && $c['user_email'] === $sessionEmail) { 
                    $c = $cdnData; 
                    $found = true; 
                    break; 
                }
            }
            if (!$found) array_unshift($cdns, $cdnData);
        } else {
            array_unshift($cdns, $cdnData);
        }
        
        file_put_contents($dbCdn, json_encode($cdns, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $updateVersion(); // Gatilho de Versión ativado!
        
        echo json_encode(['success' => true]); 
        exit;
    }

    // ==========================================
    // EXCLUIR CDN
    // ==========================================
    if ($action === 'delete_cdn') {
        $id = strval($input['id'] ?? '');
        $cdns = array_filter($cdns, function($c) use ($id, $sessionEmail) {
            return !(strval($c['id']) === $id && $c['user_email'] === $sessionEmail);
        });
        
        file_put_contents($dbCdn, json_encode(array_values($cdns), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $updateVersion(); // Gatilho de Versión ativado!
        
        echo json_encode(['success' => true]); 
        exit;
    }

    // ==========================================
    // LISTAR CDN
    // ==========================================
    if ($action === 'list_data') {
        $userCdns = array_filter($cdns, function($c) use ($sessionEmail) { 
            return isset($c['user_email']) && $c['user_email'] === $sessionEmail; 
        });
        echo json_encode(['success' => true, 'cdns' => array_values($userCdns)]); 
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); 
    exit;
}

$pageTitle = 'CDN';
ob_start();
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - PÁGINA CDN V2 (Trem Bala)
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.cdn-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; height: calc(100vh - 70px);
}

:root.dark .cdn-wrapper, .dark .cdn-wrapper, body.dark .cdn-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569;
}

.cdn-wrapper * { 
    -webkit-tap-highlight-color: transparent !important; 
    outline: none; box-sizing: border-box; 
    transition: 0.15s cubic-bezier(0.4, 0, 0.2, 1); 
}

/* ==========================================
   BLOCO SUPERIOR (PESQUISA E ADICIONAR)
   ========================================== */
.search-block { 
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; 
    padding: 24px; margin-bottom: 20px; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
}
.cdn-title-main { font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; }

.btn-primary-outline { 
    width: 100%; background: transparent; border: 2px solid var(--card-border); color: var(--text-main); 
    padding: 16px; border-radius: 14px; font-weight: 800; font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; 
    outline: none; margin-bottom: 20px;
}
.btn-primary-outline:active { transform: scale(0.96); background: var(--inner-bg); }

.search-input-wrap { position: relative; margin-bottom: 16px; }
.search-input-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; }
.search-input { width: 100%; background: transparent; border: 2px solid var(--card-border); padding: 16px 16px 16px 44px; border-radius: 14px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; transition: border 0.2s;}
.search-input:focus { border-color: var(--primary); }

/* Botões Buscar / Limpar */
.search-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-search-action { padding: 14px; border-radius: 14px; font-weight: 800; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; outline: none; font-size: 0.95rem;}
.btn-search-action:active { transform: scale(0.96); }
.btn-search { background: var(--inner-bg); color: var(--text-main); border: 2px solid var(--card-border); }
.btn-clear { background: transparent; color: var(--text-main); border: 2px solid var(--card-border); }

.spin-anim { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* ==========================================
   BLOCO DA LISTA
   ========================================== */
.list-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; flex: 1; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.list-title { padding: 24px 24px 10px 24px; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; }

.cdn-scroll-list { flex: 1; overflow-y: auto; padding: 10px 24px 24px 24px; display: flex; flex-direction: column; gap: 16px; scrollbar-width: none; }
.cdn-scroll-list::-webkit-scrollbar { display: none; }

/* Empty State Centralizado Perfeito */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; gap: 10px; padding: 20px; flex: 1; }
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px; flex-shrink: 0;}
.es-icon-wrap svg { width: 24px; height: 24px; display: block; margin: 0; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 280px; line-height: 1.4; }
.btn-es-refresh { margin-top: 10px; background: transparent; border: 2px solid var(--card-border); padding: 12px 24px; border-radius: 14px; font-size: 0.9rem; font-weight: 800; color: var(--text-main); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;}
.btn-es-refresh:active { background: var(--inner-bg); transform: scale(0.95); }

/* Card da CDN (Idêntico ao Print) */
.cdn-card { background: transparent; border: 2px solid var(--card-border); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.cdn-card-top { display: flex; justify-content: space-between; align-items: flex-start; }
.cdn-c-name { font-size: 1.1rem; font-weight: 800; color: var(--text-main); word-break: break-all; }
.cdn-c-id { font-size: 0.75rem; font-weight: 600; color: var(--text-subtle); margin-top: 4px; display: block; }
.cdn-cloud-icon { color: var(--text-muted); width: 22px; flex-shrink: 0; }

.cdn-url-box { background: var(--inner-bg); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
.cdn-url-box svg { color: var(--text-muted); width: 18px; flex-shrink: 0; }
.cdn-url-text { font-size: 0.9rem; font-weight: 700; color: var(--text-main); font-family: 'Space Grotesk', monospace; word-break: break-all; }

.cdn-card-actions { display: flex; gap: 12px; }
.btn-c-act { flex: 1; height: 44px; border-radius: 12px; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; outline:none; }
.btn-c-act:active { transform: scale(0.92); background: var(--card-border); }
.btn-c-act svg { width: 18px; }
.btn-c-act.del { background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2); color: var(--danger); }
.btn-c-act.del:active { background: var(--danger); color: white; border-color: var(--danger); }

/* Paginação */
.pagination-box { display: none; padding: 16px 24px; justify-content: flex-end; align-items: center; gap: 16px; flex-wrap: wrap; border-top: 1px solid var(--card-border);}
.pg-items { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-subtle); }
.pg-select { background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-main); border-radius: 10px; padding: 6px 10px; font-weight: 800; outline: none; cursor: pointer; }
.pg-controls { display: flex; align-items: center; gap: 8px; }
.btn-pg { width: 38px; height: 38px; border: 2px solid var(--card-border); background: transparent; border-radius: 12px; color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-pg:active { background: var(--inner-bg); transform: scale(0.9); }
.btn-pg:disabled { opacity: 0.3; cursor: not-allowed; }
.pg-info { background: var(--inner-bg); border: 2px solid var(--card-border); height: 38px; padding: 0 16px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; color: var(--text-main); }

/* ==========================================================================
   MODAIS SWEETALERT (NOVA CDN / EDIÇÃO / TAGS DINÂMICAS)
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 95% !important; max-width: 480px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 6px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 20px !important; text-align: left !important;}
.swal-close-btn { position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; outline: none; }
.swal-close-btn:active { transform: scale(0.85); color: var(--text-main);}

.swal-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block; text-align: left; letter-spacing: 0.5px;}
.swal-input { width: 100%; background: var(--inner-bg); border: 2px solid var(--card-border); border-radius: 14px; padding: 14px 16px; color: var(--text-main); font-size: 0.95rem; font-weight: 700; margin-bottom: 20px; outline: none; box-sizing: border-box; font-family: 'Manrope', sans-serif;}
.swal-input:focus { border-color: var(--primary); }

.input-icon-wrap { position: relative; margin-bottom: 20px; }
.input-icon-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.input-icon-wrap .swal-input { margin-bottom: 0; padding-left: 44px; }

/* Tags Dinâmicas Otimizadas */
.dynamic-tags-box { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; border: 1px solid var(--card-border); padding: 12px; border-radius: 14px; background: var(--inner-bg);}
.dyn-tag { 
    background: var(--card-border); color: var(--text-main); padding: 4px 12px; 
    border-radius: 50px; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 6px; 
    max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
}

.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }

.swal-btn-cancel { background: #f3f4f6 !important; color: #111827 !important; border: 2px solid var(--card-border) !important; }
.dark .swal-btn-cancel { background: #27272a !important; color: #ffffff !important; border-color: var(--card-border) !important; }
.swal-btn-confirm { background: #64748b !important; color: #ffffff !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; }

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}
</style>

<div id="toast-container"></div>

<div class="cdn-wrapper">
    
    <div class="search-block">
        <h1 class="cdn-title-main" data-i18n="cdn_title">CDN</h1>
        
        <button class="btn-primary-outline" onclick="openCdnModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span data-i18n="btn_add">Nueva CDN</span>
        </button>

        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" class="search-input" placeholder="Buscar CDN por nome ou URL..." data-i18n-placeholder="search_placeholder">
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
        <h2 class="list-title" data-i18n="list_title">Lista de CDN</h2>
        
        <div class="cdn-scroll-list" id="cdn-list">
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
        'cdn_title': 'CDN', 'btn_add': 'Nueva CDN',
        'search_placeholder': 'Buscar CDN por nome ou URL...',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpar', 'btn_clearing': 'Limpando...',
        'list_title': 'Lista de CDN', 
        'empty_title': 'Ningúna CDN encontrada', 'empty_desc': 'Cadastre uma nova CDN ou ajuste o termo pesquisado.', 'btn_refresh': 'Actualizar lista',
        'items_page': 'Itens por página', 'toast_saved': 'CDN salva con éxito!', 'toast_deleted': 'CDN removida!',
        'confirm_del_title': 'Eliminar CDN', 'confirm_del_desc': 'Deseja realmente apagar <b>{name}</b>?', 'delete': 'Eliminar', 'btn_cancel': 'Cancelar',
        'mdl_title': 'Nueva CDN', 'mdl_edit_title': 'Editar CDN', 'mdl_desc': 'Cadastre nome e URL da CDN.', 'lbl_name': 'Nombre', 'lbl_url': 'URL', 'btn_save': 'Crear CDN', 'btn_save_edit': 'Guardar CDN'
    },
    'en': {
        'cdn_title': 'CDN', 'btn_add': 'New CDN',
        'search_placeholder': 'Search CDN by name or URL...',
        'btn_search': 'Search', 'btn_searching': 'Searching...', 'btn_clear': 'Clear', 'btn_clearing': 'Clearing...',
        'list_title': 'CDN List', 
        'empty_title': 'No CDN found', 'empty_desc': 'Register a new CDN or adjust the search term.', 'btn_refresh': 'Refresh list',
        'items_page': 'Items per page', 'toast_saved': 'CDN saved successfully!', 'toast_deleted': 'CDN removed!',
        'confirm_del_title': 'Delete CDN', 'confirm_del_desc': 'Delete <b>{name}</b>?', 'delete': 'Delete', 'btn_cancel': 'Cancel',
        'mdl_title': 'New CDN', 'mdl_edit_title': 'Edit CDN', 'mdl_desc': 'Register CDN name and URL.', 'lbl_name': 'Name', 'lbl_url': 'URL', 'btn_save': 'Create CDN', 'btn_save_edit': 'Save CDN'
    },
    'es': {
        'cdn_title': 'CDN', 'btn_add': 'Nueva CDN',
        'search_placeholder': 'Buscar CDN por nombre o URL...',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpiar', 'btn_clearing': 'Limpiando...',
        'list_title': 'Lista de CDN', 
        'empty_title': 'No se encontró CDN', 'empty_desc': 'Registre una nueva CDN o ajuste la búsqueda.', 'btn_refresh': 'Actualizar lista',
        'items_page': 'Ítems por pág', 'toast_saved': '¡CDN guardada!', 'toast_deleted': '¡CDN eliminada!',
        'confirm_del_title': 'Eliminar CDN', 'confirm_del_desc': '¿Eliminar <b>{name}</b>?', 'delete': 'Eliminar', 'btn_cancel': 'Cancelar',
        'mdl_title': 'Nueva CDN', 'mdl_edit_title': 'Editar CDN', 'mdl_desc': 'Registre el nombre y la URL de la CDN.', 'lbl_name': 'Nombre', 'lbl_url': 'URL', 'btn_save': 'Crear CDN', 'btn_save_edit': 'Guardar CDN'
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
    if (type === 'error') iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${text}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}
function showToast(msgKey, type = 'success') { showToastRaw(getMsg(msgKey), type); }

// ==========================================
// BUSCAR DADOS (CDN)
// ==========================================
function fetchData() {
    fetch('?action=list_data', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { rawData = res.cdns; executeSearchLogic(); }
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
        const searchStr = (c.name + ' ' + c.url).toLowerCase();
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
// RENDERIZAR CARDS DE CDN
// ==========================================
function renderList() {
    const listEl = document.getElementById('cdn-list'); const pgContainer = document.getElementById('pagination-container');
    
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
        html += `
            <div class="cdn-card" id="card-\${c.id}">
                <div class="cdn-card-top">
                    <div>
                        <div class="cdn-c-name">\${c.name}</div>
                        <span class="cdn-c-id">ID: \${c.id}</span>
                    </div>
                    <svg class="cdn-cloud-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
                </div>
                
                <div class="cdn-url-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span class="cdn-url-text">\${c.url}</span>
                </div>

                <div class="cdn-card-actions">
                    <button class="btn-c-act" onclick="openCdnModal(\${JSON.stringify(c).replace(/"/g, '&quot;')})" title="Editar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="btn-c-act del" onclick="confirmDel('\${c.id}', '\${c.name}')" title="Eliminar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
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
// EXCLUIR CDN
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
            rawData = rawData.filter(c => String(c.id) !== String(id)); executeSearchLogic();
            fetch('?action=delete_cdn', {method:'POST', body: JSON.stringify({id})}).then(() => showToast('toast_deleted', 'error'));
        }
    });
}

// ==========================================
// MODAL DE CRIAR / EDITAR CDN
// ==========================================
function updateDynTags() {
    const name = document.getElementById('cdn-input-name').value.trim();
    const url = document.getElementById('cdn-input-url').value.trim();
    const box = document.getElementById('dyn-tags-box');
    
    let html = '';
    
    if(name) {
        let safeName = name.length > 15 ? name.substring(0, 15) + '...' : name;
        html += `<span class="dyn-tag" title="\${name}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg> \${safeName}</span>`;
    }
    
    if(url) {
        let proto = 'HTTP';
        if (url.toLowerCase().startsWith('https://')) proto = 'HTTPS';
        else if (url.toLowerCase().startsWith('http://')) proto = 'HTTP';
        else if (url.length > 3) proto = 'HTTP';
        
        let rawDomain = url.replace(/^(https?:\/\/)/i, '').split(/[\/\?#:]/)[0];
        let safeDomain = rawDomain.length > 15 ? rawDomain.substring(0, 12) + '...' : rawDomain;

        html += `<span class="dyn-tag">\${proto}</span>`;
        if(rawDomain) html += `<span class="dyn-tag" title="\${rawDomain}">\${safeDomain}</span>`;
    }
    
    if(!html) html = `<span style="color:var(--text-muted); font-size:0.8rem; font-weight:600;">Digite os dados abaixo...</span>`;
    box.innerHTML = html;
}

window.triggerCdnUpdate = updateDynTags;

function openCdnModal(cdnData = null) {
    const isDark = document.documentElement.classList.contains('dark');
    const isEdit = !!cdnData;
    
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${isEdit ? getMsg('mdl_edit_title') : getMsg('mdl_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('mdl_desc')}</p>
                
                <div class="dynamic-tags-box" id="dyn-tags-box">
                    <span style="color:var(--text-muted); font-size:0.8rem; font-weight:600;">Digite os dados abaixo...</span>
                </div>

                <label class="swal-label">\${getMsg('lbl_name')}</label>
                <input type="text" id="cdn-input-name" class="swal-input" value="\${isEdit ? cdnData.name : ''}" oninput="triggerCdnUpdate()">
                
                <label class="swal-label">\${getMsg('lbl_url')}</label>
                <div class="input-icon-wrap">
                    <input type="text" id="cdn-input-url" class="swal-input" value="\${isEdit ? cdnData.url : ''}" placeholder="https://cdn.exemplo.com" oninput="triggerCdnUpdate()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: isEdit ? getMsg('btn_save_edit') : getMsg('btn_save'), cancelButtonText: getMsg('btn_cancel'),
        didOpen: () => {
            if(isEdit) updateDynTags(); 
        },
        preConfirm: () => {
            const name = document.getElementById('cdn-input-name').value.trim();
            const url = document.getElementById('cdn-input-url').value.trim();
            if(!name || !url) { Swal.showValidationMessage('Preencha o Nombre e a URL!'); return false; }
            return { id: isEdit ? cdnData.id : null, name, url };
        }
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Salvando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=save_cdn', {method:'POST', body: JSON.stringify(res.value)}).then(r=>r.json()).then(resp => {
                if(resp.success) { Swal.close(); fetchData(); showToast('toast_saved', 'success'); }
                else { Swal.fire('Ops!', resp.error || 'Erro desconhecido', 'error'); }
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