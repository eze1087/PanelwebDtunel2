<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Configuraciones Trem Bala V14 (Mestra)
 * @description Sorter Colorrigido, Scroll Nativo Fluido e Gatilho de Versión Automático.
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
$dbConfigs    = __DIR__ . '/../db/configs.json';
$dbVersion    = __DIR__ . '/../db/version.json'; // Arquivo novo para controle de versão do app
$dbContacto   = __DIR__ . '/../db/contacto.json';

// Inicializa arquivos se não existirem
foreach ([$dbCategories, $dbConfigs, $dbVersion] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        // Se for o arquivo de versão, já começa com a versão 100, se não, array vazio
        $defaultContent = ($file === $dbVersion) ? ['version' => 100] : [];
        file_put_contents($file, json_encode($defaultContent));
        chmod($file, 0644);
    }
}

// Carrega Usuario (Obtém o UUID para o Link da API)
$userData = [];
$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) { $userData = $u; break; }
}
$userUuid = $userData['uuid'] ?? '---';

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API INTERNA TREM BALA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $configs = json_decode(file_get_contents($dbConfigs), true) ?: [];
    $categories = json_decode(file_get_contents($dbCategories), true) ?: [];

    // LÓGICA DE ATUALIZAÇÃO DE VERSÃO: Sobe +1 sempre que algo mudar
    $updateVersion = function() use ($dbVersion) {
        $v = json_decode(file_get_contents($dbVersion), true) ?: ['version' => 100];
        $v['version'] = (isset($v['version']) ? (int)$v['version'] : 100) + 1;
        file_put_contents($dbVersion, json_encode($v, JSON_PRETTY_PRINT));
    };

    // SALVAR / DUPLICAR
    if ($action === 'save_config') {
        $configData = $input['config'] ?? null;
        if (!$configData) { echo json_encode(['success' => false, 'error' => 'Dados inválidos']); exit; }
        
        $configData['user_email'] = $sessionEmail;
        // Forçando tipos corretos para o app conseguir ler o JSON nativamente
        $configData['category_id'] = (int)($configData['category_id'] ?? 1);
        $configData['sorter'] = (int)($configData['sorter'] ?? 1);

        if (empty($configData['id'])) {
            // ID gerado com no MÁXIMO 6 dígitos para o App não bugar
            $configData['id'] = (int)rand(100000, 999999);
            array_unshift($configs, $configData); 
        } else {
            $configData['id'] = (int)$configData['id']; // Garante que seja Inteiro
            $found = false;
            foreach ($configs as &$c) {
                if (strval($c['id']) === strval($configData['id']) && $c['user_email'] === $sessionEmail) { $c = $configData; $found = true; break; }
            }
            if (!$found) array_unshift($configs, $configData);
        }
        file_put_contents($dbConfigs, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    // EXCLUIR EM MASSA 
    if ($action === 'delete_multiple') {
        $ids = $input['ids'] ?? [];
        $idsStr = array_map('strval', $ids); 
        
        $configs = array_filter($configs, function($c) use ($idsStr, $sessionEmail) {
            return !($c['user_email'] === $sessionEmail && in_array(strval($c['id']), $idsStr));
        });
        
        file_put_contents($dbConfigs, json_encode(array_values($configs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    // EXCLUIR ÚNICO
    if ($action === 'delete_config') {
        $id = strval($input['id'] ?? '');
        $configs = array_filter($configs, function($c) use ($id, $sessionEmail) {
            return !(strval($c['id']) === $id && $c['user_email'] === $sessionEmail);
        });
        file_put_contents($dbConfigs, json_encode(array_values($configs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    // IMPORTAR CONFIGURAÇÕES
    if ($action === 'import_configs') {
        $newConfigs = $input['configs'] ?? [];
        $targetCatId = $input['category_id'] ?? 'KEEP';
        if (is_array($newConfigs)) {
            foreach ($newConfigs as $nc) {
                $nc['user_email'] = $sessionEmail;
                // Gera ID de no máximo 6 dígitos para o import também
                $nc['id'] = (int)rand(100000, 999999);
                if ($targetCatId !== 'KEEP') $nc['category_id'] = (int)$targetCatId;
                array_unshift($configs, $nc);
            }
            file_put_contents($dbConfigs, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $updateVersion(); // Gatilho de Versión
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false, 'error' => 'Formato JSON inválido']); }
        exit;
    }

    // ALTERAR STATUS
    if ($action === 'toggle_status') {
        $id = strval($input['id'] ?? ''); $newEstado = $input['status'] ?? 'ACTIVE';
        foreach ($configs as &$c) { if (strval($c['id']) === $id && $c['user_email'] === $sessionEmail) { $c['status'] = $newEstado; } }
        file_put_contents($dbConfigs, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    // SALVAR ORDEM
    if ($action === 'save_sorter') {
        $id = strval($input['id'] ?? ''); $sorter = (int)($input['sorter'] ?? 1);
        foreach ($configs as &$c) { if (strval($c['id']) === $id && $c['user_email'] === $sessionEmail) { $c['sorter'] = $sorter; } }
        file_put_contents($dbConfigs, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion(); // Gatilho de Versión
        echo json_encode(['success' => true]); exit;
    }

    // LISTAR DADOS
    if ($action === 'list_data') {
        $userConfigs = array_filter($configs, function($c) use ($sessionEmail) { return (isset($c['user_email']) && $c['user_email'] === $sessionEmail); });
        usort($userConfigs, function($a, $b) { return ($a['sorter'] ?? 1) - ($b['sorter'] ?? 1); });
        $userCats = array_filter($categories, function($c) use ($sessionEmail) { return (isset($c['user_email']) && $c['user_email'] === $sessionEmail); });
        
        echo json_encode(['success' => true, 'configs' => array_values($userConfigs), 'categories' => array_values($userCats)]); exit;
    }

    // NOTIFICAR PUSH
    if ($action === 'send_push') {
        echo json_encode(['success' => true]); exit;
    }

    // UPLOAD DE ICONO PERSONALIZADO
    if ($action === 'upload_icon') {
        $iconDir = __DIR__ . '/../assets/img/icons';
        if (!is_dir($iconDir)) { @mkdir($iconDir, 0775, true); }
        if (!is_writable($iconDir)) { @chmod($iconDir, 0775); }

        if (!isset($_FILES['icon_file']) || $_FILES['icon_file']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['icon_file']['error'] ?? 99;
            echo json_encode(['success' => false, 'error' => "Error de upload (código $err). Máximo: 2MB"]); exit;
        }
        $file = $_FILES['icon_file'];
        $allowed = ['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Solo se permiten PNG, JPG, GIF, WEBP o SVG.']); exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Máximo 2MB por icono.']); exit;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $dest = $iconDir . '/' . $safeName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            @chmod($dest, 0644);
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $url = "{$protocol}://{$domain}/assets/img/icons/{$safeName}";
            echo json_encode(['success' => true, 'url' => $url, 'filename' => $safeName]); 
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al guardar. Verificá permisos de assets/img/icons/']);
        }
        exit;
    }

    // LISTAR ICONOS SUBIDOS
    if ($action === 'list_icons') {
        $iconDir = __DIR__ . '/../assets/img/icons';
        $icons = [];
        if (is_dir($iconDir)) {
            $files = glob($iconDir . '/*.{png,jpg,jpeg,gif,webp,svg}', GLOB_BRACE);
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            foreach ($files as $f) {
                $icons[] = "{$protocol}://{$domain}/assets/img/icons/" . basename($f);
            }
        }
        echo json_encode(['success' => true, 'icons' => $icons]); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Configuraciones';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - PÁGINA DE CONFIGURAÇÕES V14
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.cfg-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    /* Alteração para Rolagem Fluida na página toda */
    display: flex; flex-direction: column; min-height: calc(100vh - 70px);
}
:root.dark .cfg-wrapper, .dark .cfg-wrapper, body.dark .cfg-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569;
}
.cfg-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; box-sizing: border-box; }

/* BLOCO SUPERIOR */
.search-block { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 24px; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.cfg-title-main { font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 0; }

.action-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px; }
@media(max-width: 600px) { .action-grid { grid-template-columns: 1fr 1fr; } .action-grid button:last-child { grid-column: span 2; } }
.btn-primary-outline { background: transparent; border: 2px solid var(--card-border); color: var(--text-main); padding: 14px; border-radius: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: transform 0.15s, background 0.2s; outline: none;}
.btn-primary-outline:active { transform: scale(0.94); background: var(--inner-bg); }

.search-input-wrap { position: relative; margin-bottom: 16px; }
.search-input-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; }
.search-input { width: 100%; background: transparent; border: 2px solid var(--card-border); padding: 14px 14px 14px 44px; border-radius: 14px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; transition: border 0.2s;}
.search-input:focus { border-color: var(--primary); }

.filter-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.filter-box { display: flex; align-items: center; gap: 8px; background: transparent; border: 2px solid var(--card-border); padding: 12px 14px; border-radius: 14px; }
.filter-box svg { color: var(--text-subtle); width: 16px; flex-shrink: 0;}
.filter-select { flex: 1; background: transparent; border: none; color: var(--text-main); font-weight: 700; cursor: pointer; width: 100%; appearance: none; outline: none;}

/* Botões Buscar / Limpar */
.search-btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-search-action { padding: 14px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: transform 0.15s; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; outline: none;}
.btn-search-action:active { transform: scale(0.96); }
.btn-search { background: var(--inner-bg); color: var(--text-main); border: 2px solid var(--card-border); }
.btn-clear { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 2px solid rgba(239, 68, 68, 0.3); }

.spin-anim { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* BLOCO DA LISTA (AGORA FLUIDO) */
.list-block { flex: 1; display: flex; flex-direction: column; }
.list-title { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 8px; }

/* BARRA DE AÇÃO EM MASSA (AGORA É UM CARD) */
.mass-action-bar { display: none; padding: 16px 20px; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; animation: slideDown 0.3s ease-out; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);}
@keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

.checkbox-wrap { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;}
.custom-checkbox { width: 22px; height: 22px; accent-color: var(--primary); cursor: pointer; border-radius: 6px; border: 2px solid var(--card-border); appearance: none; background: var(--card-bg); display: flex; align-items: center; justify-content: center; transition: 0.2s; outline: none; flex-shrink: 0;}
.custom-checkbox:checked { background: var(--primary); border-color: var(--primary); }
.custom-checkbox:checked::after { content: ''; position: absolute; width: 5px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); margin-bottom: 2px; }
.select-all-txt { font-size: 0.9rem; font-weight: 800; color: var(--text-main); }

.mass-btns { display: flex; gap: 8px; flex: 1; justify-content: flex-end;}
.btn-mass { padding: 10px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: transform 0.15s, opacity 0.2s; flex: 1; max-width: 140px; outline: none;}
.btn-mass:active { transform: scale(0.95); }
.btn-mass.del { background: var(--danger); color: white; border: none; opacity: 0.5; pointer-events: none;}
.btn-mass.del.active { opacity: 1; pointer-events: all; }
.btn-mass.exp { background: transparent; border: 2px solid var(--card-border); color: var(--text-main); opacity: 0.5; pointer-events: none;}
.btn-mass.exp.active { opacity: 1; pointer-events: all; }

/* LISTA FLUIDA */
.cfg-scroll-list { display: flex; flex-direction: column; gap: 16px; padding-bottom: 20px; }

.cfg-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; transition: border-color 0.2s, transform 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.02);}
.cfg-card.selected { border-color: var(--primary); background: rgba(59, 130, 246, 0.03); transform: scale(0.99);}

.cc-top { display: flex; justify-content: space-between; align-items: flex-start; }
.cc-left-info { display: flex; align-items: flex-start; gap: 12px; }
.cc-info { display: flex; flex-direction: column; gap: 2px; }
.cc-name { font-size: 1.05rem; font-weight: 800; color: var(--text-main); word-break: break-all;}
.cc-mode { font-size: 0.75rem; font-weight: 700; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 0.5px;}

/* Switch de Ativação */
.switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink:0;}
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--card-border); transition: .3s; border-radius: 24px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2);}
input:checked + .slider { background-color: var(--success); }
input:checked + .slider:before { transform: translateX(20px); }

/* Categoría Tag (Barra Colorida) */
.cc-cat-tag { width: 100%; padding: 10px 14px; border-radius: 50px; font-size: 0.8rem; font-weight: 800; color: white; display: flex; align-items: center; justify-content: center; gap: 6px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
.cc-cat-tag::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: white; box-shadow: 0 0 4px rgba(255,255,255,0.8); }

/* CONTROLE DE ORDEM CORRIGIDO (Fixo e Flexbox puro) */
.cc-bottom { display: flex; flex-direction: column; gap: 10px; }
.sorter-group { display: flex; background: var(--inner-bg); border: 2px solid var(--card-border); border-radius: 14px; overflow: hidden; height: 44px; min-width: 100px; flex-shrink: 0; align-items: center; justify-content: space-between;}
.sorter-btn { height: 100%; width: 40px; border: none; background: transparent; color: var(--text-main); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.15s; outline:none; flex-shrink: 0;}
.sorter-btn:active { background: var(--card-border); }
.sorter-btn svg { width: 18px; }
.sorter-val { flex: 1; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 800; color: var(--text-main); border-left: 2px solid var(--card-border); border-right: 2px solid var(--card-border); }

.action-group { display: flex; gap: 10px; }
.btn-action-card { flex: 1; height: 44px; border-radius: 14px; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.15s, background 0.1s; outline:none;}
.btn-action-card:active { transform: scale(0.92); background: var(--card-border); }
.btn-action-card svg { width: 20px; }
.btn-action-card.del { background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2); color: var(--danger); }
.btn-action-card.del:active { background: var(--danger); color: white; border-color: var(--danger);}

/* Empty State Moderno (estilo Card) */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 10px; padding: 40px 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);}
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px; flex-shrink: 0;}
.es-icon-wrap svg { width: 28px; height: 28px; display: block; margin: 0; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 250px; line-height: 1.4; }
.btn-es-refresh { margin-top: 10px; background: transparent; border: 2px solid var(--card-border); padding: 12px 24px; border-radius: 14px; font-size: 0.9rem; font-weight: 800; color: var(--text-main); cursor: pointer; transition: 0.15s; display: flex; align-items: center; justify-content: center; gap: 8px;}
.btn-es-refresh:active { background: var(--inner-bg); transform: scale(0.95); }

/* Paginação (Como um card solto no final) */
.pagination-box { display: none; padding: 16px 20px; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; margin-bottom: 20px;}
.pg-items { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-subtle); }
.pg-select { background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-main); border-radius: 10px; padding: 6px 10px; font-weight: 800; outline: none; cursor: pointer; }
.pg-controls { display: flex; align-items: center; gap: 8px; }
.btn-pg { width: 38px; height: 38px; border: 2px solid var(--card-border); background: transparent; border-radius: 12px; color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s;}
.btn-pg:active { background: var(--inner-bg); transform: scale(0.9); }
.btn-pg:disabled { opacity: 0.3; cursor: not-allowed; }
.pg-info { background: var(--inner-bg); border: 2px solid var(--card-border); height: 38px; padding: 0 16px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; color: var(--text-main); }

/* ==========================================================================
   MODAIS SWEETALERT E TOASTS (INALETRADOS)
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 95% !important; max-width: 500px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 6px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 24px !important; text-align: left !important;}
.swal-close-btn { position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; outline: none; transition: 0.15s;}
.swal-close-btn:active { transform: scale(0.85); color: var(--text-main);}
.swal-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block; text-align: left; letter-spacing: 0.5px;}
.swal-input { width: 100%; background: var(--inner-bg); border: 2px solid var(--card-border) !important; border-radius: 14px; padding: 14px; color: var(--text-main); font-size: 0.95rem; font-weight: 700; margin-bottom: 20px; outline: none; transition: border 0.2s; box-sizing: border-box;}
.swal-input:focus { border-color: var(--primary) !important; }
.exp-text-area { width: 100%; min-height: 220px; resize: none; overflow-y: auto; background: var(--inner-bg); color: var(--text-main); font-family: 'Space Grotesk', monospace; font-size: 0.8rem; padding: 16px; border-radius: 14px; border: 2px solid #000000 !important; outline: none; margin-bottom: 20px; box-sizing: border-box; line-height: 1.4; }
.dark .exp-text-area { border: 2px solid #8b4513 !important; }
.exp-text-area::-webkit-scrollbar { width: 6px; }
.exp-text-area::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 10px; }
.input-icon-wrap { position: relative; margin-bottom: 20px; }
.input-icon-wrap svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.input-icon-wrap .swal-input { margin-bottom: 0; padding-right: 40px; }
.input-icon-wrap.btn-style { cursor: pointer; }
.input-icon-wrap.btn-style .swal-input { cursor: pointer; pointer-events: none; }
.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }
.swal-btn-cancel { background: #f3f4f6 !important; color: #111827 !important; border: 1px solid #e5e7eb !important; }
.dark .swal-btn-cancel { background: #27272a !important; color: #ffffff !important; border-color: #3f3f46 !important; }
.swal-btn-confirm { background: #64748b !important; color: #ffffff !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; }
.swal-btn-confirm.primary { background: #3b82f6 !important; }
.export-stats { display: flex; gap: 10px; margin-bottom: 16px; }
.exp-badge { background: var(--inner-bg); border: 2px solid var(--card-border); padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); }
.export-btn-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 10px; }
.btn-exp-act { background: transparent; border: 2px solid var(--card-border); color: var(--text-main); padding: 12px; border-radius: 14px; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: transform 0.15s, background 0.1s; outline: none;}
.btn-exp-act:active { transform: scale(0.94); background: var(--inner-bg); }
.btn-exp-act svg { width: 16px; flex-shrink: 0;}

#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}
</style>

<div id="toast-container"></div>

<div class="cfg-wrapper">
    
    <div class="search-block">
        <h1 class="cfg-title-main" data-i18n="configs_title">Configuraciones</h1>
        
        <div class="action-grid">
            <button class="btn-primary-outline" onclick="openExternalModal('new')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span data-i18n="btn_add">Agregar</span>
            </button>
            <button class="btn-primary-outline" onclick="openImportModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span data-i18n="btn_import">Importar</span>
            </button>
            <button class="btn-primary-outline" onclick="openNotesModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span data-i18n="btn_notes">Enviar Notas</span>
            </button>
        </div>

        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" class="search-input" placeholder="Buscar por nome, categoria, modo ou id..." data-i18n-placeholder="search_placeholder">
        </div>

        <div class="filter-grid">
            <div class="filter-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <select class="filter-select" id="filter-cat" onchange="executeSearchLogic()">
                    <option value="ALL" data-i18n="cat_all">Categorías</option>
                </select>
            </div>
            <div class="filter-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <select class="filter-select" id="filter-status" onchange="executeSearchLogic()">
                    <option value="ALL" data-i18n="stat_all">Todos Estado</option>
                    <option value="ACTIVE" data-i18n="stat_act">Activo</option>
                    <option value="INACTIVE" data-i18n="stat_inact">Inactivo</option>
                </select>
            </div>
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
        <h2 class="list-title" data-i18n="list_title">Lista de configurações</h2>
        
        <div class="mass-action-bar" id="mass-action-bar">
            <label class="checkbox-wrap">
                <input type="checkbox" id="selectAllCheckbox" class="custom-checkbox" onchange="toggleSelectAll(this.checked)">
                <span class="select-all-txt" data-i18n="select_all">Selecionar tudo</span>
            </label>
            <div class="mass-btns">
                <button class="btn-mass del" id="btnMassDel" onclick="confirmMassDel()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <span data-i18n="delete">Eliminar</span> <span id="mass-count"></span>
                </button>
                <button class="btn-mass exp" id="btnMassExp" onclick="openExportModal(true)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span data-i18n="export">Exportar</span>
                </button>
            </div>
        </div>

        <div class="cfg-scroll-list" id="cfg-list">
            <!-- JS Renderiza Cards aqui de forma fluida -->
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

// Link de Update da API protegido pelo UUID do usuário logado
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$updateUrlBase = "$protocol://" . $_SERVER['HTTP_HOST'] . "/api/update_api.php?uuid=" . $userUuid;

$extraJs = <<<JS
<script>
// ==========================================
// MOTOR DE TRADUÇÃO E TEXTOS
// ==========================================
const dict = {
    'pt': {
        'configs_title': 'Configuraciones', 'btn_add': 'Agregar', 'btn_import': 'Importar', 'btn_notes': 'Enviar Notas',
        'search_placeholder': 'Buscar por nome, categoria, modo ou id...',
        'cat_all': 'Categorías', 'stat_all': 'Todos Estado', 'stat_act': 'Activo', 'stat_inact': 'Inactivo',
        'btn_search': 'Buscar', 'btn_searching': 'Buscando...', 'btn_clear': 'Limpar', 'btn_clearing': 'Limpando...',
        'list_title': 'Lista de configurações', 'select_all': 'Selecionar tudo', 'delete': 'Eliminar', 'export': 'Exportar',
        'empty_title': 'Ningúna configuração', 'empty_desc': 'Ajuste os filtros ou crie uma nova.', 'btn_refresh': 'Actualizar lista',
        'items_page': 'Itens por página', 'toast_saved': 'Guardado con éxito!', 'toast_deleted': 'Apagado do sistema!', 'toast_order': 'Ordem alterada!', 'toast_copied': 'Copiado para a área de transferência!',
        'confirm_del_title': 'Eliminar configuração', 'confirm_del_desc': 'Deseja realmente apagar <b>{name}</b>?',
        'confirm_mass_title': 'Exclusão em massa', 'confirm_mass_desc': 'Tem certeza que deseja apagar as <b>{n}</b> configs selecionadas?',
        'import_title': 'Importar configurações', 'import_desc': 'Carregue por link, arquivo ou cole o JSON.', 'imp_raw': 'Link bruto', 'imp_raw_pl': 'https://exemplo.com/config.json', 'imp_file': 'Arquivo JSON', 'imp_file_btn': 'Procurar Arquivo', 'imp_cat': 'Categorías', 'imp_cat_keep': 'Manter original', 'imp_manual': 'JSON manual', 'imp_manual_pl': 'Cole aqui o JSON exportado das configurações...', 'btn_process': 'Processar e Guardar', 'btn_cancel': 'Cancelar',
        'export_title': 'Exportar configurações', 'export_desc': 'Exporte o JSON ou gere um link.', 'btn_copy': 'Copiar', 'btn_down': 'Descargar', 'btn_link': 'Link API', 'btn_app': 'App',
        'notes_title': 'Notificación Push', 'notes_desc': 'Envie um aviso real para todos os dispositivos do app.', 'lbl_notes_title': 'Título', 'lbl_notes_msg': 'Mensaje', 'btn_send': 'Enviar'
    },
    'en': {
        'configs_title': 'Configurations', 'btn_add': 'Add', 'btn_import': 'Import', 'btn_notes': 'Send Notes',
        'search_placeholder': 'Search by name, category, mode or id...',
        'cat_all': 'Categories', 'stat_all': 'All Estado', 'stat_act': 'Active', 'stat_inact': 'Inactive',
        'btn_search': 'Search', 'btn_searching': 'Searching...', 'btn_clear': 'Clear', 'btn_clearing': 'Clearing...',
        'list_title': 'Configurations list', 'select_all': 'Select all', 'delete': 'Delete', 'export': 'Export',
        'empty_title': 'No configurations', 'empty_desc': 'Adjust filters or add a new one.', 'btn_refresh': 'Refresh list',
        'items_page': 'Items per page', 'toast_saved': 'Saved successfully!', 'toast_deleted': 'Deleted from system!', 'toast_order': 'Order changed!', 'toast_copied': 'Copied to clipboard!',
        'confirm_del_title': 'Delete config', 'confirm_del_desc': 'Delete <b>{name}</b>?',
        'confirm_mass_title': 'Mass deletion', 'confirm_mass_desc': 'Delete the <b>{n}</b> selected configs?',
        'import_title': 'Import configs', 'import_desc': 'Load by link, file or paste JSON.', 'imp_raw': 'Raw link', 'imp_raw_pl': 'https://example.com/config.json', 'imp_file': 'JSON File', 'imp_file_btn': 'Browse File', 'imp_cat': 'Categories', 'imp_cat_keep': 'Keep original', 'imp_manual': 'Manual JSON', 'imp_manual_pl': 'Paste exported JSON here...', 'btn_process': 'Process & Save', 'btn_cancel': 'Cancel',
        'export_title': 'Export configs', 'export_desc': 'Export JSON or generate link.', 'btn_copy': 'Copy', 'btn_down': 'Download', 'btn_link': 'API Link', 'btn_app': 'App',
        'notes_title': 'Push Notification', 'notes_desc': 'Send a real alert to devices.', 'lbl_notes_title': 'Title', 'lbl_notes_msg': 'Message', 'btn_send': 'Send'
    }
};

let rawConfigs = [];
let categories = [];
let currentConfigs = []; 
let selectedIds = new Set(); 
let currentPage = 1;
let itemsPerPage = 20;

let queryText = ''; let queryCat = 'ALL'; let queryEstado = 'ALL';
const userUpdateUrl = '$updateUrlBase';

function getMsg(key) { const lang = localStorage.getItem('app_language') || 'pt'; return dict[lang] && dict[lang][key] ? dict[lang][key] : (dict['pt'][key] || key); }

function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(el => { el.innerHTML = getMsg(el.getAttribute('data-i18n')); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = getMsg(el.getAttribute('data-i18n-placeholder')); });
}
const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLang) originalSelectLang(langCode); applyI18n(); renderList(); };

// ==========================================
// TOAST NOTIFICATIONS
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
// BUSCAR DADOS
// ==========================================
function fetchData() {
    fetch('?action=list_data', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { rawConfigs = res.configs; categories = res.categories; updateCatFilterOptions(); executeSearchLogic(); }
    });
}

function updateCatFilterOptions() {
    const sel = document.getElementById('filter-cat'); const currVal = sel.value;
    let opts = `<option value="ALL">\${getMsg('cat_all')}</option>`;
    categories.forEach(c => { opts += `<option value="\${c.id}">\${c.name}</option>`; });
    sel.innerHTML = opts; sel.value = currVal; 
}

function getSpinIcon() { return `<svg class="spin-anim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>`; }

function handleSearch() {
    const btn = document.getElementById('btn-search-main');
    btn.innerHTML = getSpinIcon() + `<span data-i18n="btn_searching">\${getMsg('btn_searching')}</span>`;
    queryText = document.getElementById('search-input').value.toLowerCase();
    queryCat = document.getElementById('filter-cat').value;
    queryEstado = document.getElementById('filter-status').value;
    
    setTimeout(() => { executeSearchLogic(); btn.innerHTML = `<span data-i18n="btn_search">\${getMsg('btn_search')}</span>`; }, 500);
}

function clearSearch() {
    const btn = document.getElementById('btn-clear-main');
    btn.innerHTML = getSpinIcon() + `<span data-i18n="btn_clearing">\${getMsg('btn_clearing')}</span>`;
    document.getElementById('search-input').value = '';
    document.getElementById('filter-cat').value = 'ALL';
    document.getElementById('filter-status').value = 'ALL';
    queryText = ''; queryCat = 'ALL'; queryEstado = 'ALL';
    
    setTimeout(() => { executeSearchLogic(); showToastRaw('Filtros removidos e lista atualizada.', 'info'); btn.innerHTML = `<span data-i18n="btn_clear">\${getMsg('btn_clear')}</span>`; }, 500);
}

function executeSearchLogic() {
    currentConfigs = rawConfigs.filter(c => {
        let match = true;
        if(queryEstado !== 'ALL' && c.status !== queryEstado) match = false;
        if(queryCat !== 'ALL' && c.category_id != queryCat) match = false;
        if(queryText) {
            const catObj = categories.find(x => x.id == c.category_id);
            const catName = catObj ? catObj.name.toLowerCase() : '';
            const searchStr = (c.name + ' ' + c.mode + ' ' + c.id + ' ' + catName).toLowerCase();
            if(!searchStr.includes(queryText)) match = false;
        }
        return match;
    });
    currentPage = 1; selectedIds.clear(); document.getElementById('selectAllCheckbox').checked = false; renderList();
}

function refreshListAnim(btn) {
    btn.innerHTML = getSpinIcon() + `Atualizando...`;
    setTimeout(() => { fetchData(); }, 600);
}

function changeItemsPerPage() { itemsPerPage = parseInt(document.getElementById('items-per-page').value); currentPage = 1; renderList(); }
function changePage(delta) { currentPage += delta; document.getElementById('selectAllCheckbox').checked = false; renderList(); }

function toggleSelect(cb, id) {
    if(cb.checked) selectedIds.add(String(id)); else selectedIds.delete(String(id));
    const allCb = document.querySelectorAll('.cb-cfg');
    document.getElementById('selectAllCheckbox').checked = (selectedIds.size === allCb.length && allCb.length > 0);
    updateMassUI();
}

function toggleSelectAll(isChecked) {
    document.querySelectorAll('.cb-cfg').forEach(cb => { 
        cb.checked = isChecked; 
        if(isChecked) selectedIds.add(String(cb.value)); else selectedIds.delete(String(cb.value)); 
    });
    updateMassUI();
}

function updateMassUI() {
    const btnDel = document.getElementById('btnMassDel'); 
    const btnExp = document.getElementById('btnMassExp');
    const cnt = document.getElementById('mass-count');
    const allCb = document.querySelectorAll('.cb-cfg');
    if(!btnDel) return;
    
    if(selectedIds.size > 0) { 
        btnDel.classList.add('active'); btnExp.classList.add('active'); cnt.innerText = `(\${selectedIds.size})`; 
    } else { 
        btnDel.classList.remove('active'); btnExp.classList.remove('active'); cnt.innerText = ''; 
    }
    
    document.getElementById('selectAllCheckbox').checked = (selectedIds.size === allCb.length && allCb.length > 0);
    document.querySelectorAll('.cfg-card').forEach(card => {
        const id = card.id.replace('card-', '');
        if(selectedIds.has(String(id))) card.classList.add('selected'); else card.classList.remove('selected');
    });
}

function renderList() {
    const listEl = document.getElementById('cfg-list'); 
    const pgContainer = document.getElementById('pagination-container');
    const massBar = document.getElementById('mass-action-bar');
    
    if(currentConfigs.length === 0) {
        listEl.innerHTML = `<div class="empty-state"><div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div><h3 class="es-title">\${getMsg('empty_title')}</h3><p class="es-desc">\${getMsg('empty_desc')}</p><button class="btn-es-refresh" onclick="refreshListAnim(this)">\${getMsg('btn_refresh')}</button></div>`;
        pgContainer.style.display = 'none'; massBar.style.display = 'none'; return;
    }

    const totalPages = Math.ceil(currentConfigs.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginated = currentConfigs.slice(startIndex, startIndex + itemsPerPage);

    let html = '';
    paginated.forEach(c => {
        const strId = String(c.id);
        const isChecked = selectedIds.has(strId) ? 'checked' : '';
        const catObj = categories.find(cat => cat.id == c.category_id);
        const catName = catObj ? catObj.name : 'Sem Categoría';
        const catColor = catObj ? catObj.color : '#64748b';
        
        html += `
            <div class="cfg-card \${isChecked ? 'selected' : ''}" id="card-\${strId}">
                <div class="cc-top">
                    <div class="cc-left-info">
                        <input type="checkbox" class="custom-checkbox cb-cfg" value="\${strId}" \${isChecked} onchange="toggleSelect(this, '\${strId}')">
                        <div class="cc-info">
                            <span class="cc-name">\${c.name}</span>
                            <span class="cc-mode">\${c.mode}</span>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" \${c.status === 'ACTIVE' ? 'checked' : ''} onchange="toggleEstado('\${strId}', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="cc-cat-tag" style="background:\${catColor}">\${catName}</div>
                
                <div class="cc-bottom">
                    <div class="sorter-group">
                        <button class="sorter-btn" onclick="moveSorter('\${strId}', -1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg></button>
                        <div class="sorter-val">\${c.sorter}</div>
                        <button class="sorter-btn" onclick="moveSorter('\${strId}', 1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg></button>
                    </div>
                    <div class="action-group">
                        <button class="btn-action-card" onclick="openExternalModal('\${strId}')" title="Editar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-action-card" onclick="duplicateConfig('\${strId}')" title="Duplicar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                        <button class="btn-action-card del" onclick="confirmDel('\${strId}', '\${c.name}')" title="Eliminar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    listEl.innerHTML = html;
    massBar.style.display = 'flex'; pgContainer.style.display = 'flex';
    document.getElementById('page-info').innerText = `\${currentPage}/\${totalPages}`;
    document.getElementById('btn-prev').disabled = currentPage === 1;
    document.getElementById('btn-next').disabled = currentPage === totalPages;
    updateMassUI();
}

// ==========================================
// LÓGICA DE SORTER (SETA PARA CIMA E PARA BAIXO)
// ==========================================
function moveSorter(id, delta) {
    let c = rawConfigs.find(x => String(x.id) === String(id));
    if(c) {
        c.sorter = parseInt(c.sorter) + delta; 
        if(c.sorter < 1) c.sorter = 1;
        
        // REORDENA O ARRAY PRINCIPAL NA HORA (EFEITO VISUAL IMEDIATO)
        rawConfigs.sort((a, b) => (parseInt(a.sorter) || 1) - (parseInt(b.sorter) || 1));
        executeSearchLogic(); 
        
        // SALVA NO BANCO
        fetch('?action=save_sorter', {method:'POST', body: JSON.stringify({id: c.id, sorter: c.sorter})}).then(()=>showToast('toast_order'));
    }
}

function toggleEstado(id, isChecked) {
    let c = rawConfigs.find(x => String(x.id) === id); if(c) c.status = isChecked ? 'ACTIVE' : 'INACTIVE';
    
    let inativasCount = rawConfigs.filter(x => x.status === 'INACTIVE').length;
    let activasCount = rawConfigs.filter(x => x.status === 'ACTIVE').length;
    const msg = isChecked ? (document.documentElement.lang==='en'?`Activated. Total: \${activasCount} active.`:`Ativada! Total: \${activasCount} ativas.`) : (document.documentElement.lang==='en'?`Deactivated. Total: \${inativasCount} inactive.`:`Desativada! Total: \${inativasCount} inativas.`);

    fetch('?action=toggle_status', {method:'POST', body: JSON.stringify({id, status: isChecked ? 'ACTIVE' : 'INACTIVE'})}).then(()=>{ 
        executeSearchLogic(); showToastRaw(msg, isChecked ? 'success' : 'info');
    });
}

function duplicateConfig(id) {
    let c = rawConfigs.find(x => String(x.id) === id);
    if(c) {
        let copy = JSON.parse(JSON.stringify(c));
        copy.id = null; copy.name = copy.name + " - Cópia";
        fetch('?action=save_config', {method:'POST', body: JSON.stringify({config: copy})}).then(r=>r.json()).then(res=>{
            if(res.success) { showToastRaw('Cópia gerada con éxito!', 'success'); fetchData(); }
        });
    }
}

function confirmDel(id, name) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><h2 class="swal-title-custom">\${getMsg('confirm_del_title')}</h2></div><p class="swal-desc-custom">\${getMsg('confirm_del_desc').replace('{name}', name)}</p>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('delete'), cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            rawConfigs = rawConfigs.filter(c => String(c.id) !== String(id)); executeSearchLogic();
            fetch('?action=delete_config', {method:'POST', body: JSON.stringify({id})}).then(() => showToast('toast_deleted', 'error'));
        }
    });
}

function confirmMassDel() {
    if(selectedIds.size === 0) return;
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><h2 class="swal-title-custom">\${getMsg('confirm_mass_title')}</h2></div><p class="swal-desc-custom">\${getMsg('confirm_mass_desc').replace('{n}', selectedIds.size)}</p>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('delete'), cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            const ids = Array.from(selectedIds);
            
            rawConfigs = rawConfigs.filter(c => !selectedIds.has(String(c.id))); 
            selectedIds.clear(); 
            document.getElementById('selectAllCheckbox').checked = false; 
            executeSearchLogic();
            
            fetch('?action=delete_multiple', {method:'POST', body: JSON.stringify({ids})}).then(() => { 
                showToast('toast_deleted', 'error'); 
            });
        }
    });
}

function openExternalModal(id) {
    if(typeof ConfigModalManager !== 'undefined') {
        let configData = null;
        if (id !== 'new') { configData = rawConfigs.find(c => String(c.id) === String(id)); }
        window.userCategories = categories; 
        ConfigModalManager.open(configData);
    } else { 
        showToastRaw('Erro: modal_config.php não foi encontrado ou tem erros! Estamos prontos para o vídeo dele.', 'error'); 
    }
}

function openNotesModal() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,0.1);color:#3b82f6;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                    <h2 class="swal-title-custom" style="margin:0;">\${getMsg('notes_title')}</h2>
                </div>
                <p class="swal-desc-custom">\${getMsg('notes_desc')}</p>
                <label class="swal-label">\${getMsg('lbl_notes_title')}</label>
                <input type="text" id="note-title" class="swal-input" placeholder="Ex: Atualização Importante">
                <label class="swal-label">\${getMsg('lbl_notes_msg')}</label>
                <textarea id="note-msg" class="swal-input" placeholder="Sua mensagem aqui..." style="min-height:100px; resize:vertical; font-family:inherit;"></textarea>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm primary', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: getMsg('btn_send'), cancelButtonText: getMsg('btn_cancel'),
        preConfirm: () => {
            const title = document.getElementById('note-title').value.trim();
            const msg = document.getElementById('note-msg').value.trim();
            if(!title || !msg) { Swal.showValidationMessage('Preencha o título e a mensagem'); return false; }
            return {title, msg};
        }
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Enviando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=send_push', {method:'POST'}).then(r=>r.json()).then(resp => {
                Swal.close(); showToastRaw('Notificación Push enviada con éxito!', 'success');
            });
        }
    });
}

window.handleImportFile = function(e) {
    const file = e.target.files[0];
    if(!file) return;
    const reader = new FileReader();
    reader.onload = function(evt) {
        document.getElementById('imp-manual-json').value = evt.target.result;
        showToastRaw('Arquivo lido con éxito!', 'info');
    };
    reader.readAsText(file);
};

function openImportModal() {
    const isDark = document.documentElement.classList.contains('dark');
    let catOpts = `<option value="KEEP">\${getMsg('imp_cat_keep')}</option>`;
    categories.forEach(c => { catOpts += `<option value="\${c.id}">\${c.name}</option>`; });

    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('import_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('import_desc')}</p>
                
                <label class="swal-label">\${getMsg('imp_raw')}</label>
                <div class="input-icon-wrap">
                    <input type="text" id="imp-raw-link" class="swal-input" placeholder="\${getMsg('imp_raw_pl')}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </div>

                <label class="swal-label">\${getMsg('imp_file')}</label>
                <div class="input-icon-wrap btn-style" onclick="document.getElementById('imp-file-btn').click()">
                    <input type="text" class="swal-input" value="\${getMsg('imp_file_btn')}" readonly>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <input type="file" id="imp-file-btn" style="display:none;" accept=".json" onchange="handleImportFile(event)">

                <label class="swal-label">\${getMsg('imp_cat')}</label>
                <select id="imp-cat-select" class="swal-input" style="appearance:none;">\${catOpts}</select>

                <label class="swal-label">\${getMsg('imp_manual')}</label>
                <textarea id="imp-manual-json" class="swal-input" placeholder="\${getMsg('imp_manual_pl')}"></textarea>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm primary', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: getMsg('btn_process'), cancelButtonText: getMsg('btn_cancel'),
        preConfirm: async () => {
            const rawLink = document.getElementById('imp-raw-link').value.trim();
            const manualJson = document.getElementById('imp-manual-json').value.trim();
            if(!rawLink && !manualJson) { Swal.showValidationMessage('Insira um Link Bruto, suba um Arquivo ou cole o JSON Manual.'); return false; }
            return { rawLink, manualJson, catId: document.getElementById('imp-cat-select').value };
        }
    }).then(async (res) => {
        if(res.isConfirmed) {
            const data = res.value;
            let jsonStr = '';
            Swal.fire({title:'Procesando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            
            try {
                if(data.rawLink) { const response = await fetch(data.rawLink); jsonStr = await response.text(); } 
                else { jsonStr = data.manualJson; }
                
                const parsedConfigs = JSON.parse(jsonStr);
                const arr = Array.isArray(parsedConfigs) ? parsedConfigs : [parsedConfigs];
                
                const saveResp = await fetch('?action=import_configs', { method: 'POST', body: JSON.stringify({configs: arr, category_id: data.catId}) });
                const result = await saveResp.json();
                
                if(result.success) { Swal.close(); fetchData(); showToastRaw(`Configuraciones importadas!`); } 
                else { Swal.fire('Erro', result.error, 'error'); }
            } catch(e) { Swal.fire('Erro', 'JSON inválido ou Falha na URL.', 'error'); }
        }
    });
}

function openExportModal() {
    const isDark = document.documentElement.classList.contains('dark');
    
    let itemsToExport = currentConfigs.filter(c => selectedIds.has(String(c.id)));
    
    if(itemsToExport.length === 0) {
        showToastRaw('Selecione pelo menos uma configuração para exportar.', 'error');
        return;
    }

    let cleanExport = itemsToExport.map(c => { let copy = JSON.parse(JSON.stringify(c)); delete copy.user_email; return copy; });
    const jsonStr = JSON.stringify(cleanExport, null, 4);
    const sizeKB = (new Blob([jsonStr]).size / 1024).toFixed(1) + ' KB';

    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('export_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('export_desc')}</p>
                
                <div class="export-stats">
                    <span class="exp-badge">\${itemsToExport.length} itens</span>
                    <span class="exp-badge">\${sizeKB}</span>
                </div>

                <textarea id="exp-textarea" class="exp-text-area" readonly>\${jsonStr}</textarea>

                <div class="export-btn-grid">
                    <button class="btn-exp-act" onclick="navigator.clipboard.writeText(document.getElementById('exp-textarea').value); showToast('toast_copied');">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> \${getMsg('btn_copy')}
                    </button>
                    <button class="btn-exp-act" onclick="downloadJSON()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> \${getMsg('btn_down')}
                    </button>
                    <button class="btn-exp-act" onclick="navigator.clipboard.writeText(userUpdateUrl); showToastRaw('Link protegido copiado!', 'success');">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> \${getMsg('btn_link')}
                    </button>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showConfirmButton: false
    });
}

window.downloadJSON = function() {
    const jsonText = document.getElementById('exp-textarea').value;
    const blob = new Blob([jsonText], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = "config_app.json";
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    showToastRaw("Download iniciado!", "success");
}

document.addEventListener('DOMContentLoaded', () => { fetchData(); applyI18n(); });
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }

// INCLUSÃO DO SEU MODAL EXTERNO NO FINAL DA PÁGINA (GARANTIA DE FUNCIONAMENTO)
@include_once __DIR__ . '/../modal/modal_config.php';
?>