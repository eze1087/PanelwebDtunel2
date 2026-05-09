<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Categorías Trem Bala V8
 * @description Sorter fixado, Colores RGBA (Transparência), Watermark Animada e Versionamento.
 * =======================================================================================
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbCategories = __DIR__ . '/../db/categories.json';
$dbVersion    = __DIR__ . '/../db/version.json'; // Arquivo de controle de versão do app

// Inicializa arquivos se não existirem
foreach ([$dbCategories, $dbVersion] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        // Se for o arquivo de versão, já começa com a versão 100, se não, array vazio
        $defaultContent = ($file === $dbVersion) ? ['version' => 100] : [];
        file_put_contents($file, json_encode($defaultContent));
        chmod($file, 0644);
    }
}

// Carrega Usuario para pegar o UUID
$userData = [];
$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) {
        $userData = $u; break;
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
    $categories = json_decode(file_get_contents($dbCategories), true) ?: [];

    // LÓGICA DE ATUALIZAÇÃO DE VERSÃO: Sobe +1 sempre que algo mudar
    $updateVersion = function() use ($dbVersion) {
        $v = json_decode(file_get_contents($dbVersion), true) ?: ['version' => 100];
        $v['version'] = (isset($v['version']) ? (int)$v['version'] : 100) + 1;
        file_put_contents($dbVersion, json_encode($v, JSON_PRETTY_PRINT));
    };

    if ($action === 'save_category') {
        $id     = $input['id'] ?? null;
        $name   = trim($input['name'] ?? '');
        $color  = trim($input['color'] ?? '#1E90FFCC'); // Aceita RGBA 8 digitos
        $sorter = intval($input['sorter'] ?? 1);
        $status = $input['status'] ?? 'ACTIVE';

        if (empty($name)) { echo json_encode(['success' => false, 'error' => 'Nombre obrigatório']); exit; }

        if ($id) {
            foreach ($categories as &$c) {
                // Compara garantindo que o tipo string/int não quebre
                if (strval($c['id']) === strval($id) && $c['user_email'] === $sessionEmail) {
                    $c['name'] = $name; $c['color'] = $color; $c['sorter'] = $sorter; $c['status'] = $status;
                }
            }
        } else {
            $categories[] = [
                'id' => (int)substr(str_replace('.', '', (string)microtime(true)) . mt_rand(10, 99), 0, 9), // ID único 9 dígitos
                'user_email' => $sessionEmail, 
                'name' => $name, 
                'color' => $color, 
                'sorter' => $sorter, 
                'status' => $status
            ];
        }
        file_put_contents($dbCategories, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'delete_category') {
        $ids = isset($input['ids']) ? $input['ids'] : (isset($input['id']) ? [$input['id']] : []);
        $idsStr = array_map('strval', $ids); 
        
        $categories = array_filter($categories, function($c) use ($idsStr, $sessionEmail) {
            return !($c['user_email'] === $sessionEmail && in_array(strval($c['id']), $idsStr));
        });
        
        file_put_contents($dbCategories, json_encode(array_values($categories), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'toggle_status') {
        $id = $input['id'] ?? 0; $newEstado = $input['status'] ?? 'ACTIVE';
        foreach ($categories as &$c) {
            if (strval($c['id']) === strval($id) && $c['user_email'] === $sessionEmail) { $c['status'] = $newEstado; }
        }
        file_put_contents($dbCategories, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'list_categories') {
        $userCats = array_filter($categories, function($c) use ($sessionEmail) {
            return isset($c['user_email']) && $c['user_email'] === $sessionEmail;
        });
        usort($userCats, function($a, $b) { return ($a['sorter'] ?? 1) - ($b['sorter'] ?? 1); });
        echo json_encode(['success' => true, 'categories' => array_values($userCats)]); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Categorías';
ob_start();
?>

<!-- Importação SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - RESPONSIVIDADE E TREM BALA
   ========================================================================== */
/* Trava anti-encolhimento dos Modais */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.cat-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b;
    padding: 16px; max-width: 800px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; height: calc(100vh - 70px);
}

:root.dark .cat-wrapper, .dark .cat-wrapper, body.dark .cat-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569;
}

.cat-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1); box-sizing: border-box; }

/* Bloco Superior (Categorías, Botão, Filtro) */
.top-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 20px; margin-bottom: 20px; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.cat-title-main { font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 0; }

.btn-new-cat {
    width: 100%; background: transparent; border: 1px solid var(--card-border); padding: 14px; border-radius: 12px;
    font-size: 0.95rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; justify-content: center; gap: 8px;
    cursor: pointer; margin-bottom: 12px; background: var(--inner-bg);
}
.btn-new-cat:active { transform: scale(0.97); }

.filter-box { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: transparent; border: 1px solid var(--card-border); border-radius: 12px; }
.filter-icon { color: var(--text-subtle); width: 18px; }
.filter-select { flex: 1; background: transparent; border: none; color: var(--text-main); font-size: 0.95rem; font-weight: 700; cursor: pointer; }

/* Lista Central */
.list-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; flex: 1; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.list-title { padding: 20px 20px 10px 20px; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; }

/* Barra de Ação em Massa */
.mass-action-bar {
    display: none; padding: 12px 20px; border-bottom: 1px solid var(--card-border); align-items: center; justify-content: space-between;
    background: var(--inner-bg); animation: slideDown 0.3s ease-out;
}
@keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

.checkbox-wrap { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.custom-checkbox { width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer; border-radius: 4px; border: 1px solid var(--card-border); }
.select-all-txt { font-size: 0.9rem; font-weight: 700; color: var(--text-main); }

.btn-mass-del {
    background: var(--danger); color: white; border: none; padding: 8px 16px; border-radius: 10px;
    font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 6px; cursor: pointer; opacity: 0.5; pointer-events: none;
}
.btn-mass-del.active { opacity: 1; pointer-events: all; }
.btn-mass-del:active { transform: scale(0.95); }

/* Lista de Categorías */
.cat-scroll-list { flex: 1; overflow-y: auto; padding: 10px 20px 20px 20px; display: flex; flex-direction: column; gap: 16px; scrollbar-width: none; }
.cat-scroll-list::-webkit-scrollbar { display: none; }

/* Card da Categoría */
.cat-card { background: transparent; border: 1px solid var(--card-border); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
.cc-top { display: flex; justify-content: space-between; align-items: center; }
.cc-left-info { display: flex; align-items: center; gap: 12px; }
.cc-info { display: flex; flex-direction: column; gap: 2px; }
.cc-name { font-size: 1.05rem; font-weight: 800; color: var(--text-main); }
.cc-id { font-size: 0.75rem; font-weight: 600; color: var(--text-subtle); }

.switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--card-border); transition: .3s; border-radius: 24px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
input:checked + .slider { background-color: var(--success); }
input:checked + .slider:before { transform: translateX(20px); }

/* ==========================================================================
   ANIMAÇÃO WATERMARK "DTUNNELMOD" NO CARD
   ========================================================================== */
.cc-color-bar {
    width: 100%; height: 38px; border-radius: 50px; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 800; color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.8);
    position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,0.2);
}
.cc-color-bar::before {
    content: 'DTUNNELMOD • DTUNNELMOD • DTUNNELMOD • DTUNNELMOD • DTUNNELMOD • ';
    position: absolute; top: 0; left: 0; width: 300%; height: 100%;
    display: flex; align-items: center; justify-content: flex-start;
    font-size: 1.3rem; font-weight: 900; color: rgba(255,255,255,0.18);
    letter-spacing: 2px; text-transform: uppercase; white-space: nowrap;
    animation: scrollText 12s linear infinite; pointer-events: none; z-index: 0;
}
@keyframes scrollText {
    0% { transform: translateX(0); }
    100% { transform: translateX(-33.33%); }
}
.cc-color-bar span { position: relative; z-index: 1; letter-spacing: 0.5px;}

/* ==========================================================================
   CONTROLE DE ORDEM (SORTER)
   ========================================================================== */
.cc-bottom { display: flex; flex-direction: column; gap: 10px; }
.sorter-group { display: flex; background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; height: 40px; }
.sorter-btn { flex: 1; border: none; background: transparent; color: var(--text-main); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.sorter-btn:active { background: var(--card-border); }
.sorter-btn svg { width: 16px; }
.sorter-val { flex: 2; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; color: var(--text-main); border-left: 1px solid var(--card-border); border-right: 1px solid var(--card-border); }

.action-group { display: flex; gap: 10px; }
.btn-action-card { flex: 1; height: 40px; border-radius: 12px; border: 1px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-action-card:active { transform: scale(0.95); }
.btn-action-card svg { width: 16px; }
.btn-action-card.del { background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2); color: var(--danger); }
.btn-action-card.del:active { background: var(--danger); color: white; }

/* Empty State Colorrigido */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; gap: 10px; padding: 20px; }
.es-icon-wrap { width: 56px; height: 56px; border-radius: 50%; background: var(--inner-bg); border: 1px solid var(--card-border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); margin-bottom: 8px; }
.es-icon-wrap svg { width: 24px; height: 24px; margin: 0; display: block; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 250px; line-height: 1.4; }
.btn-es-refresh { margin-top: 10px; background: transparent; border: 1px solid var(--card-border); padding: 10px 20px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; color: var(--text-main); cursor: pointer; }
.btn-es-refresh:active { background: var(--inner-bg); transform: scale(0.95); }

/* Paginação Rodapé */
.pagination-box { padding: 16px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 16px; flex-wrap: wrap; border-top: 1px solid var(--card-border);}
.pg-items { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; color: var(--text-subtle); }
.pg-select { background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); border-radius: 8px; padding: 6px 10px; font-weight: 700; outline: none; cursor: pointer; }
.pg-controls { display: flex; align-items: center; gap: 6px; }
.btn-pg { width: 36px; height: 36px; border: 1px solid var(--card-border); background: transparent; border-radius: 10px; color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-pg:active { background: var(--inner-bg); transform: scale(0.9); }
.btn-pg:disabled { opacity: 0.3; cursor: not-allowed; }
.pg-info { background: var(--inner-bg); border: 1px solid var(--card-border); height: 36px; padding: 0 14px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; color: var(--text-main); }

/* ==========================================================================
   MODAIS SWEETALERT E COLOR PICKER COM ALPHA
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 90% !important; max-width: 400px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 4px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 20px !important; text-align: left !important;}

.swal-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px; display: block; text-align: left; }
.swal-input { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 14px; color: var(--text-main); font-size: 0.95rem; font-weight: 700; margin-bottom: 16px; outline: none; }
.swal-input:focus { border-color: var(--primary); }

.color-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 16px; }
.color-item { height: 32px; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: transform 0.1s; box-shadow: inset 0 0 5px rgba(0,0,0,0.2);}
.color-item:active { transform: scale(0.9); }
.color-item.selected { border-color: var(--text-main); }

.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { 
    flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; 
    border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important;
    outline: none !important; margin: 0 !important;
}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }

.swal-btn-cancel { background: #f3f4f6 !important; color: #111827 !important; border: 1px solid #e5e7eb !important; }
.dark .swal-btn-cancel { background: #27272a !important; color: #fff !important; border-color: #3f3f46 !important; }

.swal-btn-confirm { background: #64748b !important; color: #fff !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; color: #fff !important; } 

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); }
.toast.info .toast-icon { background: var(--primary); }
.toast.warning .toast-icon { background: var(--warning); }
.toast-msg { font-size: 0.9rem; font-weight: 700; color: var(--text-main); }
</style>

<div id="toast-container"></div>

<div class="cat-wrapper">
    
    <div class="top-block">
        <h1 class="cat-title-main" data-i18n="categories_title">Categorías</h1>
        <button class="btn-new-cat" onclick="openCatModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span data-i18n="new_category">Nueva categoria</span>
        </button>
        
        <div class="filter-box">
            <svg class="filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <select class="filter-select" id="cat-filter" onchange="resetPageAndRender()">
                <option value="ACTIVE" data-i18n="active">Activo</option>
                <option value="INACTIVE" data-i18n="inactive">Inactivo</option>
                <option value="ALL" data-i18n="all">Todos</option>
            </select>
        </div>
    </div>

    <div class="list-block">
        <h2 class="list-title" data-i18n="list_title">Lista de categorias</h2>
        
        <div class="mass-action-bar" id="mass-action-bar">
            <label class="checkbox-wrap">
                <input type="checkbox" id="selectAllCheckbox" class="custom-checkbox" onchange="toggleSelectAll(this.checked)">
                <span class="select-all-txt" data-i18n="select_all">Selecionar tudo</span>
            </label>
            <button class="btn-mass-del" id="btnMassDel" onclick="confirmMassDel()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span data-i18n="delete">Eliminar</span> <span id="mass-count"></span>
            </button>
        </div>

        <div class="cat-scroll-list" id="cat-list">
            <!-- JS Renderiza Cards ou Empty State Aqui -->
        </div>

        <div class="pagination-box" id="pagination-container" style="display:none;">
            <div class="pg-items">
                <span data-i18n="items_page">Itens por página</span>
                <select class="pg-select" id="items-per-page" onchange="changeItemsPerPage()">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
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

// API Update URL base
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$updateUrlBase = "$protocol://" . $_SERVER['HTTP_HOST'] . "/api/update_api.php?uuid=" . $userUuid;

$extraJs = <<<JS
<script>
const catDict = {
    'pt': {
        'categories_title': 'Categorías', 'new_category': 'Nueva categoria', 'active': 'Activo', 'inactive': 'Inactivo', 'all': 'Todos',
        'list_title': 'Lista de categorias', 'items_page': 'Itens por página', 'select_all': 'Selecionar tudo',
        'empty_title': 'Ningúna categoria encontrada', 'empty_desc': 'Ajuste o filtro ou cadastre uma nova categoria.', 'btn_refresh': 'Actualizar lista',
        'modal_new_title': 'Nueva categoria', 'modal_edit_title': 'Editar categoria', 'modal_desc': 'Crie uma categoria e defina cor, ordem e status.',
        'lbl_name': 'NOME', 'lbl_order': 'ORDEM', 'lbl_status': 'STATUS', 'lbl_color': 'COR (COM OPACIDADE)', 'create': 'Crear categoria', 'save': 'Guardar alterações', 'cancel': 'Cancelar',
        'placeholder_name': 'Ex: Premium', 'toast_saved': 'Categoría salva!', 'toast_deleted': 'Categoría removida!', 'toast_status_act': 'Categoría Ativada!', 'toast_status_inact': 'Categoría movida para Inactivos.', 'toast_order': 'Ordem atualizada!',
        'confirm_del_title': 'Eliminar categoria', 'confirm_del_desc': 'Deseja realmente apagar <b>{name}</b>? Isso não pode ser desfeito.', 'delete': 'Eliminar',
        'confirm_mass_title': 'Eliminar Categorías', 'confirm_mass_desc': 'Deseja realmente apagar as <b>{n}</b> categorias selecionadas?',
        'copy_link': 'Link do JSON copiado!', 'error_name': 'Digite um nome'
    },
    'en': {
        'categories_title': 'Categories', 'new_category': 'New category', 'active': 'Active', 'inactive': 'Inactive', 'all': 'All',
        'list_title': 'Category list', 'items_page': 'Items per page', 'select_all': 'Select all',
        'empty_title': 'No categories found', 'empty_desc': 'Adjust the filter or register a new category.', 'btn_refresh': 'Refresh list',
        'modal_new_title': 'New category', 'modal_edit_title': 'Edit category', 'modal_desc': 'Create a category and set color, order and status.',
        'lbl_name': 'NAME', 'lbl_order': 'ORDER', 'lbl_status': 'STATUS', 'lbl_color': 'COLOR (WITH OPACITY)', 'create': 'Create category', 'save': 'Save changes', 'cancel': 'Cancel',
        'placeholder_name': 'Ex: Premium', 'toast_saved': 'Category saved!', 'toast_deleted': 'Category removed!', 'toast_status_act': 'Category Activated!', 'toast_status_inact': 'Category moved to Inactive.', 'toast_order': 'Order updated!',
        'confirm_del_title': 'Delete category', 'confirm_del_desc': 'Delete <b>{name}</b>? This cannot be undone.', 'delete': 'Delete',
        'confirm_mass_title': 'Delete Categories', 'confirm_mass_desc': 'Are you sure you want to delete the <b>{n}</b> selected categories?',
        'copy_link': 'JSON Link copied!', 'error_name': 'Enter a name'
    },
    'es': {
        'categories_title': 'Categorías', 'new_category': 'Nueva categoría', 'active': 'Activo', 'inactive': 'Inactivo', 'all': 'Todos',
        'list_title': 'Lista de categorías', 'items_page': 'Ítems por página', 'select_all': 'Seleccionar todo',
        'empty_title': 'No se encontraron categorías', 'empty_desc': 'Ajuste el filtro o registre una nueva.', 'btn_refresh': 'Actualizar lista',
        'modal_new_title': 'Nueva categoría', 'modal_edit_title': 'Editar categoría', 'modal_desc': 'Cree una categoría y defina color, orden y estado.',
        'lbl_name': 'NOMBRE', 'lbl_order': 'ORDEN', 'lbl_status': 'ESTADO', 'lbl_color': 'COLOR (CON OPACIDAD)', 'create': 'Crear categoría', 'save': 'Guardar cambios', 'cancel': 'Cancelar',
        'placeholder_name': 'Ej: Premium', 'toast_saved': '¡Categoría guardada!', 'toast_deleted': '¡Categoría eliminada!', 'toast_status_act': '¡Categoría Activada!', 'toast_status_inact': 'Categoría movida a Inactivos.', 'toast_order': '¡Orden actualizado!',
        'confirm_del_title': 'Eliminar categoría', 'confirm_del_desc': '¿Realmente desea eliminar <b>{name}</b>? Esto no se puede deshacer.', 'delete': 'Eliminar',
        'confirm_mass_title': 'Eliminar Categorías', 'confirm_mass_desc': '¿Desea realmente eliminar las <b>{n}</b> categorías seleccionadas?',
        'copy_link': '¡Enlace JSON copiado!', 'error_name': 'Ingrese un nombre'
    }
};

let categories = [];
let currentPage = 1;
let itemsPerPage = 20;
let selectedCats = new Set();
const userUpdateUrl = '$updateUrlBase';
window.currentModalColor = '#1E90FFFF'; // Variável global pro modal de cor

function getMsg(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    return catDict[lang] && catDict[lang][key] ? catDict[lang][key] : (catDict['pt'][key] || key);
}

function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if(el.tagName === 'INPUT') el.placeholder = getMsg(key);
        else el.innerHTML = getMsg(key);
    });
    renderCats(); 
}

const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLang) originalSelectLang(langCode); applyI18n(); };

function showToast(msgKey, type = 'success') {
    const container = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast \${type}`;
    let iconSvg = '<polyline points="20 6 9 17 4 12"/>';
    if(type === 'info') iconSvg = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
    
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${getMsg(msgKey)}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}

function fetchCats() {
    fetch('?action=list_categories', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { categories = res.categories; renderCats(); }
    });
}

function resetPageAndRender() { 
    currentPage = 1; 
    selectedCats.clear(); 
    document.getElementById('selectAllCheckbox').checked = false;
    renderCats(); 
}
function changeItemsPerPage() { itemsPerPage = parseInt(document.getElementById('items-per-page').value); resetPageAndRender(); }
function changePage(delta) { 
    currentPage += delta; 
    selectedCats.clear();
    document.getElementById('selectAllCheckbox').checked = false;
    renderCats(); 
}

function renderCats() {
    const listEl = document.getElementById('cat-list');
    const pgContainer = document.getElementById('pagination-container');
    const massBar = document.getElementById('mass-action-bar');
    const filter = document.getElementById('cat-filter').value;
    
    let filtered = categories;
    if(filter !== 'ALL') filtered = categories.filter(c => c.status === filter);

    if(filtered.length === 0) {
        listEl.innerHTML = `
            <div class="empty-state">
                <div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div>
                <h3 class="es-title">\${getMsg('empty_title')}</h3>
                <p class="es-desc">\${getMsg('empty_desc')}</p>
                <button class="btn-es-refresh" onclick="fetchCats()">\${getMsg('btn_refresh')}</button>
            </div>
        `;
        pgContainer.style.display = 'none';
        massBar.style.display = 'none';
        return;
    }

    const totalPages = Math.ceil(filtered.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginated = filtered.slice(startIndex, startIndex + itemsPerPage);

    let html = '';
    paginated.forEach(c => {
        const isChecked = selectedCats.has(c.id) ? 'checked' : '';
        html += `
            <div class="cat-card">
                <div class="cc-top">
                    <div class="cc-left-info">
                        <input type="checkbox" class="custom-checkbox cb-cat" value="\${c.id}" \${isChecked} onchange="toggleSelect(this, '\${c.id}')">
                        <div class="cc-info">
                            <span class="cc-name">\${c.name}</span>
                            <span class="cc-id">#\${c.id}</span>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" \${c.status === 'ACTIVE' ? 'checked' : ''} onchange="toggleEstado('\${c.id}', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="cc-color-bar" style="background:\${c.color}"><span>\${c.name}</span></div>
                
                <div class="cc-bottom">
                    <div class="sorter-group">
                        <button class="sorter-btn" onclick="moveSorter('\${c.id}', -1)" title="Subir"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg></button>
                        <div class="sorter-val">\${c.sorter}</div>
                        <button class="sorter-btn" onclick="moveSorter('\${c.id}', 1)" title="Descer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg></button>
                    </div>
                    <div class="action-group">
                        <button class="btn-action-card" onclick="openCatModal(\${JSON.stringify(c).replace(/"/g, '&quot;')})" title="Editar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-action-card del" onclick="confirmDel('\${c.id}', '\${c.name}')" title="Eliminar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    listEl.innerHTML = html;
    
    massBar.style.display = 'flex';
    pgContainer.style.display = 'flex';
    document.getElementById('page-info').innerText = `\${currentPage}/\${totalPages}`;
    document.getElementById('btn-prev').disabled = currentPage === 1;
    document.getElementById('btn-next').disabled = currentPage === totalPages;
    updateMassDelBtn();
}

function toggleSelectAll(isChecked) {
    const checkboxes = document.querySelectorAll('.cb-cat');
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
        if(isChecked) selectedCats.add(cb.value);
        else selectedCats.delete(cb.value);
    });
    updateMassDelBtn();
}

function toggleSelect(cb, id) {
    if(cb.checked) selectedCats.add(id);
    else selectedCats.delete(id);
    
    const allCb = document.querySelectorAll('.cb-cat');
    document.getElementById('selectAllCheckbox').checked = (selectedCats.size === allCb.length && allCb.length > 0);
    updateMassDelBtn();
}

function updateMassDelBtn() {
    const btn = document.getElementById('btnMassDel');
    const cnt = document.getElementById('mass-count');
    if(selectedCats.size > 0) {
        btn.classList.add('active'); cnt.innerText = `(\${selectedCats.size})`;
    } else {
        btn.classList.remove('active'); cnt.innerText = '';
    }
}

// SORTER FIXADO (Converter para String na comparação evita bug)
function moveSorter(id, delta) {
    let c = categories.find(x => String(x.id) === String(id));
    if(c) {
        c.sorter = parseInt(c.sorter) + delta; 
        if(c.sorter < 1) c.sorter = 1;
        fetch('?action=save_category', {method:'POST', body: JSON.stringify(c)}).then(()=>{ fetchCats(); showToast('toast_order'); });
    }
}

function toggleEstado(id, isChecked) {
    const st = isChecked ? 'ACTIVE' : 'INACTIVE';
    fetch('?action=toggle_status', {method:'POST', body: JSON.stringify({id, status: st})}).then(()=>{ 
        fetchCats(); 
        if(isChecked) showToast('toast_status_act');
        else showToast('toast_status_inact', 'info');
    });
}

// ==========================================
// MODAL DE CATEGORIA COM RGB + ALPHA TRANSPARENTE
// ==========================================
function openCatModal(cat = null) {
    const isDark = document.documentElement.classList.contains('dark');
    const isEdit = !!cat;
    
    // Captura e separa HEX e Alpha
    let selectedColor = cat && cat.color ? cat.color : '#1E90FFFF';
    if (!selectedColor.startsWith('#')) selectedColor = '#1E90FFFF';
    let baseHex = selectedColor.substring(0, 7) || '#1E90FF';
    let alphaHex = selectedColor.length === 9 ? selectedColor.substring(7, 9) : 'FF';
    let alphaPct = Math.round((parseInt(alphaHex, 16) / 255) * 100);
    window.currentModalColor = selectedColor;

    const presetColors = ['#1E90FF', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#06b6d4', '#475569'];

    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div class="swal-header-custom">
                <h2 class="swal-title-custom">\${isEdit ? getMsg('modal_edit_title') : getMsg('modal_new_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('modal_desc')}</p>
            </div>
            
            <div class="swal-form-group">
                <label class="swal-label">\${getMsg('lbl_name')}</label>
                <input type="text" id="m-name" class="swal-input" value="\${isEdit ? cat.name : ''}" placeholder="\${getMsg('placeholder_name')}">
            </div>
            
            <div style="display:flex; gap:16px;">
                <div class="swal-form-group" style="flex:1;">
                    <label class="swal-label">\${getMsg('lbl_order')}</label>
                    <input type="number" id="m-sorter" class="swal-input" value="\${isEdit ? cat.sorter : 1}">
                </div>
                <div class="swal-form-group" style="flex:1;">
                    <label class="swal-label">\${getMsg('lbl_status')}</label>
                    <select id="m-status" class="swal-input" style="appearance:none;">
                        <option value="ACTIVE" \${isEdit && cat.status === 'ACTIVE' ? 'selected' : ''}>\${getMsg('active')}</option>
                        <option value="INACTIVE" \${isEdit && cat.status === 'INACTIVE' ? 'selected' : ''}>\${getMsg('inactive')}</option>
                    </select>
                </div>
            </div>

            <div class="swal-form-group" style="margin-top:20px;">
                <label class="swal-label">\${getMsg('lbl_color')}</label>
                <div style="background:var(--card-bg); border:1px solid var(--card-border); border-radius:12px; padding:14px; position:relative; overflow:hidden; display:flex; flex-direction:column; gap:10px;">
                    <span id="m-preview-text" style="font-family:'Space Grotesk', monospace; font-size:1rem; font-weight:800; color:var(--text-main);">\${selectedColor}</span>
                    <div style="height:40px; width:100%; border-radius:8px; box-shadow:inset 0 0 10px rgba(0,0,0,0.5); pointer-events:none; background:\${selectedColor}; border:1px solid rgba(255,255,255,0.1);" id="m-preview-bg"></div>
                    <input type="color" id="m-native-color" value="\${baseHex}" style="position:absolute; top:-10px; left:-10px; width:200%; height:200%; opacity:0; cursor:pointer;">
                </div>
                <div style="display:flex; flex-direction:column; gap:6px; margin-top:8px;">
                    <label style="font-size:0.75rem; color:var(--text-muted); font-weight:700; display:flex; justify-content:space-between;">Opacidade <span id="lbl-alpha-cat">\${alphaPct}%</span></label>
                    <input type="range" id="m-alpha-color" min="0" max="100" value="\${alphaPct}" style="width:100%; accent-color:var(--primary);">
                </div>
                
                <div class="color-grid">
                    \${presetColors.map(c => `<div class="color-item \${c === baseHex ? 'selected' : ''}" style="background:\${c}" onclick="selectColor(this, '\${c}')"></div>`).join('')}
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: isEdit ? getMsg('save') : getMsg('create'), cancelButtonText: getMsg('cancel'),
        didOpen: () => {
            const previewBg = document.getElementById('m-preview-bg');
            const previewText = document.getElementById('m-preview-text');
            const nativeColor = document.getElementById('m-native-color');
            const alphaSlider = document.getElementById('m-alpha-color');
            const lblAlpha = document.getElementById('lbl-alpha-cat');

            function updateColor() {
                let hex = nativeColor.value.toUpperCase();
                let alpha = parseInt(alphaSlider.value);
                lblAlpha.innerText = alpha + '%';
                let aHex = Math.round((alpha / 100) * 255).toString(16).padStart(2, '0').toUpperCase();
                let finalColor = hex + aHex;
                previewBg.style.background = finalColor;
                previewText.innerText = finalColor;
                window.currentModalColor = finalColor;
            }

            nativeColor.addEventListener('input', updateColor);
            alphaSlider.addEventListener('input', updateColor);

            window.selectColor = function(el, colorHex) {
                document.querySelectorAll('.color-item').forEach(x => x.classList.remove('selected'));
                el.classList.add('selected');
                nativeColor.value = colorHex;
                updateColor();
            };
        },
        preConfirm: () => {
            const name = document.getElementById('m-name').value.trim();
            const color = window.currentModalColor; // Pega o RGBA perfeito gerado no modal
            const sorter = document.getElementById('m-sorter').value;
            const status = document.getElementById('m-status').value;
            if(!name) { Swal.showValidationMessage(getMsg('error_name')); return false; }
            return { id: isEdit ? cat.id : null, name, color, sorter, status };
        }
    }).then((res) => {
        if(res.isConfirmed) {
            fetch('?action=save_category', {method:'POST', body: JSON.stringify(res.value)}).then(r=>r.json()).then(resp => {
                if(resp.success) { fetchCats(); showToast('toast_saved'); }
            });
        }
    });
}

function confirmDel(id, name) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 class="swal-title-custom">\${getMsg('confirm_del_title')}</h2>
            </div>
            <p class="swal-desc-custom">\${getMsg('confirm_del_desc').replace('{name}', name)}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('delete'), cancelButtonText: getMsg('cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            fetch('?action=delete_category', {method:'POST', body: JSON.stringify({id})}).then(() => { fetchCats(); showToast('toast_deleted'); });
        }
    });
}

function confirmMassDel() {
    if(selectedCats.size === 0) return;
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 class="swal-title-custom">\${getMsg('confirm_mass_title')}</h2>
            </div>
            <p class="swal-desc-custom">\${getMsg('confirm_mass_desc').replace('{n}', selectedCats.size)}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('delete'), cancelButtonText: getMsg('cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            const ids = Array.from(selectedCats);
            fetch('?action=delete_category', {method:'POST', body: JSON.stringify({ids})}).then(() => { 
                selectedCats.clear(); document.getElementById('selectAllCheckbox').checked = false;
                fetchCats(); showToast('toast_deleted'); 
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', () => { fetchCats(); applyI18n(); });
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }
?>