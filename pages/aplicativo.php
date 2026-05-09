<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Aplicación e Layouts Trem Bala V3 (Suprema e Definitiva)
 * @description Botão INICIAR e espaços ajustados, + Sistema de Versión Automática.
 * @version 3.0.1
 * =======================================================================================
 */

// Força Bruta Anti-Cache para o navegador sempre puxar a versão mais nova
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbAppLayouts = __DIR__ . '/../db/app_layouts.json';
$dbCommunity  = __DIR__ . '/../db/community_themes.json';
$dbVersion    = __DIR__ . '/../db/version.json'; // Arquivo de controle de versão do app

// Inicializa arquivos se não existirem com permissões seguras
foreach ([$dbAppLayouts, $dbCommunity, $dbVersion] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
        // Se for o arquivo de versão, já começa com a versão 100
        $defaultContent = ($file === $dbVersion) ? ['version' => 100] : [];
        @file_put_contents($file, json_encode($defaultContent));
        @chmod($file, 0644);
    }
}

// Carrega Usuario (Obtém o UUID para o Link da API)
$userData = [];
$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) { $userData = $u; break; }
}
$userUuid = $userData['uuid'] ?? ($userData['id'] ?? '---');

// JSON Padrão do Layout Completo Extenso
$defaultLayoutJson = '[{"name":"APP_LOGO","value":null,"type":"IMAGE"},{"name":"APP_BACKGROUND_IMAGE","value":null,"type":"IMAGE"},{"name":"APP_BACKGROUND_TYPE","value":{"options":[{"label":"Imagem","value":"IMAGE"},{"label":"Color","value":"COLOR"}],"selected":"COLOR"},"type":"SELECT"},{"name":"APP_BACKGROUND_COLOR","value":"#080e16c6","type":"COLOR"},{"name":"APP_CARD_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_CARD_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_CARD_STATUS_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_CARD_STATUS_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_CARD_CONFIG_COLOR","value":"#0E171EC9","type":"COLOR"},{"name":"APP_DIALOG_BACKGROUND_COLOR","value":"#050C5AE4","type":"COLOR"},{"name":"APP_DIALOG_LOGGER_COLOR","value":"#080e16c7","type":"COLOR"},{"name":"APP_BORDER_COLOR","value":"#1d242e00","type":"COLOR"},{"name":"APP_INPUT_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_INPUT_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_TEXT_COLOR","value":"#FFFFFFFF","type":"COLOR"},{"name":"APP_BUTTON_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_BUTTON_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_ICON_COLOR","value":"#FFFFFFFF","type":"COLOR"},{"name":"APP_SHOW_CONNECTION_MODE","value":true,"type":"BOOLEAN"},{"name":"APP_CONFIG_AUTO_UPDATE","value":false,"type":"BOOLEAN"},{"name":"APP_CONNECTION_LIMITER","value":false,"type":"BOOLEAN"},{"name":"APP_BTN_UPDATE_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_BTN_LOGGER_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_BTN_PAGE_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_BTN_MENU_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_UPDATE_LAST_SEEN_ENABLED","value":false,"type":"BOOLEAN"},{"name":"APP_CONFIG_LOCATION_PERMISSION","value":true,"type":"BOOLEAN"},{"name":"APP_DIALOG_ERROR_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_CHECKUSER_DIALOG_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_SUCCESS_TOAST_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_ERROR_TOAST_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_LOCAL_IP_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_CONFIG_FILTER_ENABLED","value":false,"type":"BOOLEAN"},{"name":"APP_PING_SERVICE_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_CDN_COUNT_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_AIRPLANE_MODE","value":true,"type":"BOOLEAN"},{"name":"APP_AIRPLANE_MODE_TIMEOUT","value":1,"type":"INTEGER"},{"name":"APP_ALERT_SOUND_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_LAYOUT_WEBVIEW_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_MESSAGE","value":null,"type":"TEXT"},{"name":"APP_MESSAGE_TYPE","value":{"options":[{"label":"Alerta","value":"ALERT"},{"label":"Informação","value":"INFO"},{"label":"Boas vindas","value":"WELCOME"},{"label":"Sem mensagem","value":"NONE"}],"selected":"NONE"},"type":"SELECT"},{"name":"APP_LAYOUT_WEBVIEW","value":null,"type":"HTML"},{"name":"APP_SUPPORT_BUTTON","value":null,"type":"HTML"},{"name":"APP_WEB_VIEW","value":null,"type":"HTML"}]';

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API INTERNA DE AÇÕES DA PÁGINA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $layouts = json_decode(file_get_contents($dbAppLayouts), true) ?: [];

    // Gatilho de Atualização de Versión
    $updateVersion = function() use ($dbVersion) {
        $v = json_decode(file_get_contents($dbVersion), true) ?: ['version' => 100];
        $v['version'] = (isset($v['version']) ? (int)$v['version'] : 100) + 1;
        file_put_contents($dbVersion, json_encode($v, JSON_PRETTY_PRINT));
    };

    // GARANTIA DE INTEGRIDADE: Se não houver layout nenhum, cria o base.
    $userLayoutsCheck = array_filter($layouts, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
    if (empty($userLayoutsCheck) && $action === 'list_data') {
        $firstLayout = [
            'id' => time() . rand(100, 999),
            'user_email' => $sessionEmail,
            'name' => 'Layout Principal',
            'is_active' => true,
            'layout_data' => json_decode($defaultLayoutJson, true),
            'created_at' => time()
        ];
        array_unshift($layouts, $firstLayout);
        file_put_contents($dbAppLayouts, json_encode($layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion();
        $userLayoutsCheck = array_filter($layouts, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
    }

    // SALVAR / ATUALIZAR LAYOUT NO BANCO
    if ($action === 'save_layout') {
        $layoutData = $input['layout'] ?? null;
        
        $userLayouts = array_filter($layouts, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
        
        // Bloqueio de Limite de Criação
        if (empty($layoutData["id"]) && count($userLayouts) >= 20) {
            echo json_encode(['success' => false, 'error' => 'LIMIT_REACHED', 'message' => 'Limite máximo atingido.']); exit;
        }

        if (empty($layoutData['id'])) {
            $isFirst = count($userLayouts) === 0;
            $newLayout = [
                'id' => time() . rand(100, 999),
                'user_email' => $sessionEmail,
                'name' => $layoutData['name'] ?? 'Nuevo Layout',
                'is_active' => $isFirst,
                'layout_data' => isset($layoutData['layout_data']) ? $layoutData['layout_data'] : json_decode($defaultLayoutJson, true),
                'created_at' => time()
            ];
            
            if ($isFirst || ($layoutData['is_active'] ?? false)) {
                foreach ($layouts as &$l) { if ($l['user_email'] === $sessionEmail) $l['is_active'] = false; }
            }
            array_unshift($layouts, $newLayout);
        } else {
            // Edição Exata
            foreach ($layouts as &$l) {
                if (strval($l['id']) === strval($layoutData['id']) && $l['user_email'] === $sessionEmail) {
                    if (isset($layoutData['name'])) $l['name'] = $layoutData['name'];
                    if (isset($layoutData['layout_data'])) $l['layout_data'] = $layoutData['layout_data'];
                    break;
                }
            }
        }
        
        file_put_contents($dbAppLayouts, json_encode($layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion();
        echo json_encode(['success' => true]); exit;
    }

    // ATIVAR UM LAYOUT
    if ($action === 'activate_layout') {
        $idToActivate = strval($input['id'] ?? '');
        foreach ($layouts as &$l) {
            if ($l['user_email'] === $sessionEmail) {
                $l['is_active'] = (strval($l['id']) === $idToActivate);
            }
        }
        file_put_contents($dbAppLayouts, json_encode($layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion();
        echo json_encode(['success' => true]); exit;
    }

    // EXCLUIR LAYOUT
    if ($action === 'delete_layout') {
        $id = strval($input['id'] ?? '');
        $wasActive = false;
        
        $userLayouts = array_filter($layouts, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
        if (count($userLayouts) <= 1) {
            echo json_encode(['success' => false, 'error' => 'O layout principal não pode ser excluído.']); exit;
        }

        $layouts = array_filter($layouts, function($l) use ($id, $sessionEmail, &$wasActive) {
            if (strval($l['id']) === $id && $l['user_email'] === $sessionEmail) {
                if ($l['is_active']) $wasActive = true;
                return false;
            }
            return true;
        });

        if ($wasActive) {
            foreach ($layouts as &$l) {
                if ($l['user_email'] === $sessionEmail) { $l['is_active'] = true; break; }
            }
        }
        
        file_put_contents($dbAppLayouts, json_encode(array_values($layouts), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $updateVersion();
        echo json_encode(['success' => true]); exit;
    }

    // COMPARTILHAR TEMA
    if ($action === 'share_layout') {
        $id = strval($input['id'] ?? '');
        $desc = trim($input['description'] ?? '');
        
        $targetLayout = null;
        foreach ($layouts as $l) {
            if (strval($l['id']) === $id && $l['user_email'] === $sessionEmail) { $targetLayout = $l; break; }
        }

        if ($targetLayout) {
            $community = json_decode(file_get_contents($dbCommunity), true) ?: [];
            $community[] = [
                'id' => time() . rand(1000, 9999),
                'author' => $userData['username'] ?? 'Usuario',
                'description' => $desc,
                'layout_data' => $targetLayout['layout_data'],
                'downloads' => 0,
                'created_at' => time()
            ];
            file_put_contents($dbCommunity, json_encode($community, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo json_encode(['success' => true]); exit;
        }
        echo json_encode(['success' => false, 'error' => 'Layout não encontrado.']); exit;
    }

    // IMPORTAR LAYOUT VIA JSON MANUAL
    if ($action === 'import_layout') {
        $importedData = $input['layout_data'] ?? null;
        
        $userLayouts = array_filter($layouts, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
        if (count($userLayouts) >= 20) {
            echo json_encode(['success' => false, 'error' => 'LIMIT_REACHED', 'message' => 'Limite máximo atingido.']); exit;
        }

        if (is_array($importedData)) {
            $isFirst = count($userLayouts) === 0;
            $newLayout = [
                'id' => time() . rand(100, 999),
                'user_email' => $sessionEmail,
                'name' => 'Layout Importado',
                'is_active' => $isFirst,
                'layout_data' => $importedData,
                'created_at' => time()
            ];
            array_unshift($layouts, $newLayout);
            file_put_contents($dbAppLayouts, json_encode($layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $updateVersion();
            echo json_encode(['success' => true]); exit;
        }
        echo json_encode(['success' => false, 'error' => 'JSON Inválido']); exit;
    }

    // RETORNA A LISTA DE LAYOUTS DESTE USUÁRIO
    if ($action === 'list_data') {
        $userLayouts = array_filter($layouts, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
        
        usort($userLayouts, function($a, $b) { 
            if ($a['is_active'] && !$b['is_active']) return -1;
            if (!$a['is_active'] && $b['is_active']) return 1;
            return $b['created_at'] - $a['created_at']; 
        });
        
        echo json_encode(['success' => true, 'layouts' => array_values($userLayouts)]); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Aplicación';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   FORÇA BRUTA: Z-INDEX MAXIMO PARA O SWEETALERT (NOTIFICAÇÕES SEMPRE NA FRENTE)
   ========================================================================== */
body.swal2-shown .swal2-container, 
.swal2-container, 
.swal2-container.swal2-center { 
    z-index: 999999999 !important; 
}

/* ==========================================================================
   ESTILOS PREMIUM - PÁGINA DE APLICATIVO E MOCKUPS (Layout Moderno)
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.app-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --slate: #64748b;
    --mock-bg: #080e16c6; --mock-el: #1d242e73; --mock-border: rgba(255,255,255,0.05); --mock-text: #ffffff; --mock-icon: #ffffff;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; height: calc(100vh - 70px);
}
:root.dark .app-wrapper, .dark .app-wrapper, body.dark .app-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; --slate: #475569;
}
.app-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; box-sizing: border-box; }

/* AÇÕES DE TOPO (Agregar, Importar, Sincronizar, Generar APK) */
.action-top-block { background: transparent; margin-bottom: 24px; flex-shrink: 0; }
.app-title-main { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; }

.action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.btn-top-action { 
    background: transparent; border: 2px solid var(--card-border); color: var(--text-main); 
    padding: 16px; border-radius: 16px; font-weight: 800; font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; 
    transition: transform 0.15s, background 0.2s; outline: none;
}
.btn-top-action:active { transform: scale(0.95); background: var(--inner-bg); }
.btn-top-action svg { width: 18px; }
.btn-top-action.highlight { background: var(--inner-bg); }

/* CORPO DA LISTA */
.list-block { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.list-title { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 0; padding-left: 4px;}
.app-scroll-list { flex: 1; overflow-y: auto; padding-bottom: 40px; display: flex; flex-direction: column; gap: 24px; scrollbar-width: none; }
.app-scroll-list::-webkit-scrollbar { display: none; }

/* CARDS DE CADA LAYOUT */
.layout-card { 
    background: var(--card-bg); border: 2px solid var(--card-border); border-radius: 24px; 
    padding: 24px; display: flex; flex-direction: column; gap: 20px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: border-color 0.2s; position: relative;
}
.dark .layout-card { box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.layout-card.active-layout { border-color: var(--success); }

.lc-header { display: flex; justify-content: space-between; align-items: center; }
.lc-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;}
.lc-badge { background: rgba(16,185,129,0.1); color: var(--success); padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;}

/* MOCKUP DO CELULAR E CAIXAS - CORRIGIDOS ESPAÇOS E BOTÃO INICIAR */
.phone-mockup-wrapper {
    cursor: pointer; transition: transform 0.2s; width: 100%; max-width: 280px; margin: 0 auto; outline: none;
    -webkit-tap-highlight-color: transparent;
}
.phone-mockup-wrapper:active { transform: scale(0.97); }

.phone-mockup-container {
    width: 100%; max-width: 280px; min-height: 400px; margin: 0 auto;
    background: var(--mock-bg); border-radius: 30px; padding: 20px;
    border: 4px solid var(--mock-border-color, #1d242e); display: flex; flex-direction: column; gap: 14px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3); position: relative; background-size: cover; background-position: center;
    overflow: hidden; justify-content: center; /* CENTRA TUDO, TIRANDO O ESPAÇO VAZIO EMBAIXO */
}

.mock-field { 
    background: var(--mock-el); border-radius: var(--mock-radius, 25px); padding: 12px 16px; 
    display: flex; align-items: center; gap: 10px; color: var(--mock-text); font-size: 0.85rem; font-weight: 600; 
    border: 1px solid var(--mock-border); box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
}
.mock-field svg { width: 16px; color: var(--mock-icon); opacity: 0.8;}
.mock-select { justify-content: space-between; }

/* LINHA DOS BOTÕES DO APLICATIVO CORRIGIDA */
.mock-btn-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: 6px;}
.mock-btn-main { 
    background: var(--mock-btn); flex: 1; padding: 10px 4px; border-radius: var(--mock-btn-radius, 25px); 
    color: var(--mock-text); font-weight: 900; font-size: 0.8rem; border: 1px solid var(--mock-border);
    display: flex; align-items: center; justify-content: center; letter-spacing: 0.5px;
    /* Removido o truncate para aparecer "INICIAR" inteiro */
}
.mock-btn-circle { 
    width: 32px; height: 32px; border-radius: 50%; background: var(--mock-btn); 
    display: flex; align-items: center; justify-content: center; color: var(--mock-icon); border: 1px solid var(--mock-border); flex-shrink: 0;
}
.mock-btn-circle svg { width: 14px; }

.mock-logger { background: var(--mock-card); height: 26px; border-radius: var(--mock-radius, 25px); margin-top: 10px; border: 1px solid var(--mock-border); opacity: 0.8;}

/* AÇÕES DA LISTA INFERIOR (Círculos Lápis, Exportar, etc) */
.layout-actions-row { 
    display: flex; justify-content: center; gap: 8px; margin-top: 16px; 
    flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none;
}
.layout-actions-row::-webkit-scrollbar { display: none; }

.btn-circle-act { 
    width: 40px; height: 40px; border-radius: 50%; background: transparent; border: 2px solid var(--card-border);
    color: var(--text-main); display: flex; align-items: center; justify-content: center; 
    cursor: pointer; transition: transform 0.15s, background 0.2s, border-color 0.2s; outline: none; flex-shrink: 0;
}
.btn-circle-act:active { transform: scale(0.9); background: var(--inner-bg); }
.btn-circle-act svg { width: 18px; }

.btn-circle-act.check { color: var(--success); border-color: rgba(16,185,129,0.3); }
.btn-circle-act.check:active { background: rgba(16,185,129,0.1); }
.btn-circle-act.del { color: var(--danger); border-color: rgba(239,68,68,0.3); }
.btn-circle-act.del:active { background: rgba(239,68,68,0.1); }

/* VAZIO (Ningún Layout) */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; gap: 10px; padding: 20px; flex: 1; }
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--card-bg); border: 2px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px;}
.es-icon-wrap svg { width: 28px; height: 28px; display: block; margin: 0; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 250px; line-height: 1.4; }

/* GIRA GIRA CARREGANDO */
.spin-anim { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* PAGINAÇÃO INFERIOR */
.pagination-box { display: none; padding: 16px 24px; justify-content: flex-end; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: 10px;}
.pg-items { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-subtle); }
.pg-select { background: var(--inner-bg); border: 2px solid var(--card-border); color: var(--text-main); border-radius: 10px; padding: 6px 10px; font-weight: 800; outline: none; cursor: pointer; }
.pg-controls { display: flex; align-items: center; gap: 8px; }
.btn-pg { width: 38px; height: 38px; border: 2px solid var(--card-border); background: transparent; border-radius: 12px; color: var(--text-main); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s;}
.btn-pg:active { background: var(--inner-bg); transform: scale(0.9); }
.btn-pg:disabled { opacity: 0.3; cursor: not-allowed; }
.pg-info { background: var(--inner-bg); border: 2px solid var(--card-border); height: 38px; padding: 0 16px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; color: var(--text-main); }

/* ==========================================================================
   ESTILIZAÇÃO DOS MODAIS (SweetAlert e Editor Visual)
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 95% !important; max-width: 500px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 6px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 24px !important; text-align: left !important;}
.swal-close-btn { position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; outline: none; transition: 0.15s;}
.swal-close-btn:active { transform: scale(0.85); color: var(--text-main);}

.swal-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block; text-align: left; letter-spacing: 0.5px;}
.swal-input { width: 100%; background: var(--inner-bg); border: 2px solid var(--card-border) !important; border-radius: 14px; padding: 14px 16px; color: var(--text-main); font-size: 0.95rem; font-weight: 700; margin-bottom: 20px; outline: none; transition: border 0.2s; box-sizing: border-box; font-family: 'Manrope', sans-serif;}
.swal-input:focus { border-color: var(--primary) !important; }

textarea.swal-input { min-height: 120px; resize: vertical; font-family: 'Manrope', sans-serif; font-size: 0.85rem; line-height: 1.4; padding: 16px;}
textarea.json-area { font-family: 'Space Grotesk', monospace; min-height: 180px; border-color: var(--text-main) !important; }

.input-icon-wrap { position: relative; margin-bottom: 20px; }
.input-icon-wrap svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.input-icon-wrap .swal-input { margin-bottom: 0; padding-right: 40px; }
.input-icon-wrap.btn-style { cursor: pointer; }
.input-icon-wrap.btn-style .swal-input { cursor: pointer; pointer-events: none; }

.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }

.swal-btn-cancel { background: var(--inner-bg) !important; color: var(--text-main) !important; border: 1px solid var(--card-border) !important; }
.swal-btn-confirm { background: #64748b !important; color: #ffffff !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; }
.swal-btn-confirm.primary { background: #3b82f6 !important; }

.export-stats { display: flex; gap: 10px; margin-bottom: 16px; }
.exp-badge { background: var(--inner-bg); border: 2px solid var(--card-border); padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); }
.export-btn-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 10px; }
.btn-exp-act { background: transparent; border: 2px solid var(--card-border); color: var(--text-main); padding: 12px; border-radius: 14px; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: transform 0.15s, background 0.1s; outline: none;}
.btn-exp-act:active { transform: scale(0.94); background: var(--inner-bg); }
.btn-exp-act svg { width: 16px; flex-shrink: 0;}

/* TOASTS GLOBAIS DE MENSAGEM */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 1000000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}

/* ==========================================================================
   MODAL VISUAL DE CELULAR (LÁPIS DO MEIO) - COMPORTA O CLIQUE NAS CAIXAS
   ========================================================================== */
#editorOverlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(5px);
    z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
    opacity: 0; visibility: hidden; transition: opacity 0.3s ease; padding: 10px;
}
#editorOverlay.show { opacity: 1; visibility: visible; }

.editor-container {
    width: 100%; max-width: 500px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px;
    display: flex; flex-direction: column; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 -20px 50px rgba(0,0,0,0.6); margin-top: auto; padding-top: 20px; 
    max-height: 85vh; overflow: hidden; 
}
#editorOverlay.show .editor-container { transform: translateY(0); }

.editor-phone-wrap { display: flex; justify-content: center; padding: 0 0 16px 0; flex-shrink: 0; }
.editor-phone-wrap .phone-mockup-container { max-width: 250px; min-height: 380px; padding: 16px; border-width: 4px; border-radius: 24px; gap: 10px; box-shadow: none; position: relative; justify-content: center; }
.editor-phone-wrap .mock-field { padding: 8px 12px; font-size: 0.75rem; cursor: pointer; transition: 0.2s;}
.editor-phone-wrap .mock-btn-row { gap: 6px; }
.editor-phone-wrap .mock-btn-main { padding: 10px 4px; font-size: 0.8rem; cursor: pointer; transition: 0.2s;}
.editor-phone-wrap .mock-btn-circle { width: 32px; height: 32px; cursor: pointer; transition: 0.2s;}
.editor-phone-wrap .mock-btn-circle svg { width: 14px; }
.editor-phone-wrap .mock-logger { height: 20px; cursor: pointer; transition: 0.2s;}

/* FOCO DO CLIQUE (EFEITO VISUAL) */
.mockup-focus { box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.7) !important; transform: scale(1.03); z-index: 10;}
#mockup-badge { position: absolute; top: 12px; left: 12px; background: rgba(0,0,0,0.4); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 4px 10px; font-size: 0.7rem; font-weight: 800; backdrop-filter: blur(4px); z-index: 20; opacity: 0; transition: 0.3s; pointer-events: none;}
#mockup-badge.show { opacity: 1; }

.editor-controls {
    background: var(--inner-bg); border-top: 1px solid var(--card-border); border-radius: 24px; padding: 24px;
    display: flex; flex-direction: column; gap: 20px; flex: 1; overflow-y: auto; scrollbar-width: none;
}
.editor-controls::-webkit-scrollbar { display: none; }
.editor-title { font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

.editor-grid-modes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; flex-shrink: 0;}
.btn-editor-mode {
    background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 14px; border-radius: 12px;
    font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.2s; outline: none;
}
.btn-editor-mode.active { border-color: var(--primary); background: rgba(59, 130, 246, 0.05); }
.btn-editor-mode:active { transform: scale(0.95); }
.btn-editor-mode svg { width: 16px; color: var(--text-muted); }

.ed-section { display: none; flex-direction: column; gap: 16px; margin-top: 10px; flex-shrink: 0;}
.ed-section.active { display: flex; }

.ed-label { font-size: 0.8rem; font-weight: 800; color: var(--text-main); display: block; margin-bottom: 4px; }
.ed-desc { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px; display: block;}

.ed-toggle-row { display: flex; background: var(--card-bg); border-radius: 12px; padding: 4px; border: 1px solid var(--card-border); }
.ed-toggle-btn { flex: 1; padding: 10px; text-align: center; color: var(--text-muted); font-weight: 800; font-size: 0.85rem; cursor: pointer; border-radius: 8px; transition: 0.2s;}
.ed-toggle-btn.active { background: var(--card-border); color: var(--text-main); }

.fake-color-box {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 14px;
    display: flex; flex-direction: column; gap: 10px; position: relative; overflow: hidden;
}
.fcb-hex { font-family: 'Space Grotesk', monospace; font-size: 0.95rem; font-weight: 800; color: var(--text-main); }
.fcb-gradient {
    height: 60px; width: 100%; border-radius: 8px; background: linear-gradient(90deg, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff, #ff00ff, #ff0000);
    box-shadow: inset 0 0 10px rgba(0,0,0,0.5); pointer-events: none;
}
.native-color-picker { position: absolute; top: -10px; left: -10px; width: 200%; height: 200%; opacity: 0; cursor: pointer;}

.alpha-slider-wrap { display: flex; flex-direction: column; gap: 6px; margin-top: 8px;}
.alpha-slider-wrap label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: flex; justify-content: space-between;}
.alpha-slider { width: 100%; accent-color: var(--primary); }

.ed-input { width: 100%; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 14px; color: var(--text-main); font-size: 0.9rem; font-weight: 700; outline: none; }
.ed-input:focus { border-color: var(--primary); }

.ed-btn-upload { flex: 1; background: var(--card-border); color: var(--text-main); border: 1px solid var(--card-border); padding: 14px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: 0.15s;}
.ed-btn-upload:active { transform: scale(0.95); background: var(--inner-bg); }

.btn-trash-input { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;}
.btn-trash-input:active { transform: scale(0.9); background: rgba(239,68,68,0.2); }

.btn-radius-preset { background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 8px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; cursor: pointer; flex: 1; transition: 0.2s;}
.btn-radius-preset:active { transform: scale(0.95); background: var(--inner-bg); }

.ed-footer { display: flex; gap: 12px; margin-top: 10px; flex-shrink: 0; padding-bottom: 20px;}
.ed-btn-close { flex: 1; background: transparent; border: 1px solid var(--card-border); color: var(--text-main); padding: 16px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.15s; outline: none;}
.ed-btn-save { flex: 1; background: #64748b; color: #fff; border: none; padding: 16px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.15s; outline: none;}
.ed-btn-close:active, .ed-btn-save:active { transform: scale(0.95); }
</style>

<div id="toast-container"></div>

<div class="app-wrapper">
    
    <div class="action-top-block">
        <h1 class="app-title-main" data-i18n="app_title">Aplicación</h1>
        
        <div class="action-grid">
            <button type="button" class="btn-top-action" onclick="addLayout()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span data-i18n="btn_add">Agregar</span>
            </button>
            <button type="button" class="btn-top-action" onclick="openImportModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span data-i18n="btn_import">Importar</span>
            </button>
            <button type="button" class="btn-top-action highlight" id="btn-sync" onclick="syncDatabase()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                <span data-i18n="btn_sync">Sincronizar</span>
            </button>
            <button type="button" class="btn-top-action highlight" onclick="window.location.href='/gerar-apk'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span data-i18n="btn_apk">Generar APK</span>
            </button>
        </div>
    </div>

    <div class="list-block">
        <h2 class="list-title" data-i18n="list_title">Lista de aplicativos</h2>
        
        <div class="app-scroll-list" id="layout-list">
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
                <button type="button" class="btn-pg" id="btn-prev" onclick="changePage(-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
                <div class="pg-info" id="page-info">1/1</div>
                <button type="button" class="btn-pg" id="btn-next" onclick="changePage(1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
            </div>
        </div>
    </div>
</div>

<div id="editorOverlay" onclick="CellEditor.close(event)">
    <div class="editor-container" onclick="event.stopPropagation()">
        
        <div class="editor-phone-wrap" id="editor-mockup-target">
            </div>

        <div class="editor-controls">
            <div>
                <div class="editor-title" data-i18n-vis="vis_edit_now">EDITANDO AGORA</div>
                <div style="font-size:1.1rem; font-weight:800; color:var(--text-main);" id="ed-current-mode-title">Fundo</div>
                <div style="font-size:0.8rem; color:var(--text-muted);" id="ed-current-mode-desc">Color ou imagem de fundo</div>
            </div>

            <div class="editor-grid-modes">
                <button type="button" class="btn-editor-mode active" id="btn-mode-fundo" onclick="CellEditor.switchMode('fundo', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> <span data-i18n-vis="vis_bg">Fundo</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-logo" onclick="CellEditor.switchMode('logo', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><path d="m9 16 2-3 2 3"/></svg> <span data-i18n-vis="vis_logo">Logo</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-card" onclick="CellEditor.switchMode('card', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="6" width="16" height="12" rx="2" ry="2"/><line x1="8" y1="12" x2="16" y2="12"/></svg> <span data-i18n-vis="vis_card">Card</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-status" onclick="CellEditor.switchMode('status', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span data-i18n-vis="vis_status">Estado</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-campos" onclick="CellEditor.switchMode('campos', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="8" width="16" height="8" rx="2" ry="2"/><line x1="8" y1="12" x2="8.01" y2="12"/></svg> <span data-i18n-vis="vis_campos">Campos</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-botoes" onclick="CellEditor.switchMode('botoes', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="8" width="12" height="8" rx="2" ry="2"/></svg> <span data-i18n-vis="vis_btn">Botões</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-icones" onclick="CellEditor.switchMode('icones', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> <span data-i18n-vis="vis_icons">Ícones</span></button>
                <button type="button" class="btn-editor-mode" id="btn-mode-textos" onclick="CellEditor.switchMode('textos', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg> <span data-i18n-vis="vis_texts">Textos</span></button>
            </div>

            <div class="ed-section active" id="sec-fundo">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_bg">Fundo do aplicativo</span>
                    <span class="ed-desc" data-i18n-vis="vis_bg_desc">Altere a cor principal ou troque a imagem de fundo.</span>
                    <div class="ed-toggle-row">
                        <div class="ed-toggle-btn active" id="fundo-t-cor" onclick="CellEditor.toggleFundoType('COLOR')" data-i18n-vis="lbl_cor">Color</div>
                        <div class="ed-toggle-btn" id="fundo-t-img" onclick="CellEditor.toggleFundoType('IMAGE')" data-i18n-vis="lbl_img">Imagem</div>
                    </div>
                </div>
                
                <div id="fundo-box-cor">
                    <span class="ed-label" data-i18n-vis="lbl_cor_bg">Color de fundo</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-fundo">#080e16c7</span>
                        <div class="fcb-gradient"></div>
                        <input type="color" id="inp-color-fundo" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_BACKGROUND_COLOR', this.id, 'alpha-fundo', 'hex-fundo')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-fundo">100%</span></label>
                        <input type="range" id="alpha-fundo" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_BACKGROUND_COLOR', 'inp-color-fundo', this.id, 'hex-fundo')">
                    </div>
                </div>

                <div id="fundo-box-img" style="display:none;">
                    <span class="ed-label" data-i18n-vis="lbl_url_img">URL da imagem</span>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="inp-img-fundo" class="ed-input" placeholder="https://..." oninput="CellEditor.updateBgImage(this.value)">
                        <button type="button" class="btn-trash-input" onclick="document.getElementById('inp-img-fundo').value=''; CellEditor.updateBgImage('');" title="Limpar URL">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                    <div style="display:flex; margin-top:10px;">
                        <button type="button" class="ed-btn-upload" onclick="document.getElementById('fundo-file').click()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <span data-i18n-vis="btn_env_fundo">Enviar fundo do dispositivo</span>
                        </button>
                    </div>
                    <input type="file" id="fundo-file" style="display:none" accept="image/*" onchange="CellEditor.uploadBase64(event, 'APP_BACKGROUND_IMAGE')">
                </div>
            </div>

            <div class="ed-section" id="sec-logo">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_logo">Logo do aplicativo</span>
                    <span class="ed-desc" data-i18n-vis="vis_logo_desc">Atualize a URL ou envie uma nova imagem para a logo.</span>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="inp-img-logo" class="ed-input" placeholder="https://..." oninput="CellEditor.updateProp('APP_LOGO', this.value, 'IMAGE')">
                        <button type="button" class="btn-trash-input" onclick="document.getElementById('inp-img-logo').value=''; CellEditor.updateProp('APP_LOGO', '', 'IMAGE');" title="Limpar URL">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                    <div style="display:flex; margin-top:10px;">
                        <button type="button" class="ed-btn-upload" onclick="document.getElementById('logo-file').click()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <span data-i18n-vis="btn_env_logo">Enviar logo do dispositivo</span>
                        </button>
                    </div>
                    <input type="file" id="logo-file" style="display:none" accept="image/*" onchange="CellEditor.uploadBase64(event, 'APP_LOGO')">
                </div>
            </div>

            <div class="ed-section" id="sec-card">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_card">Card principal</span>
                    <span class="ed-desc" data-i18n-vis="vis_card_desc">Controla a cor e o arredondamento do bloco principal.</span>
                    
                    <span class="ed-label" style="margin-top:10px;" data-i18n-vis="lbl_cor_card">Color do card</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-card">#1d242e73</span>
                        <div class="fcb-gradient"></div>
                        <input type="color" id="inp-color-card" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_CARD_COLOR', this.id, 'alpha-card', 'hex-card')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-card">100%</span></label>
                        <input type="range" id="alpha-card" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_CARD_COLOR', 'inp-color-card', this.id, 'hex-card')">
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
                        <span class="ed-label" style="margin:0;" data-i18n-vis="lbl_arred">Arredondamento</span>
                        <span style="color:var(--text-main); font-weight:800; font-size:0.9rem;" id="val-rad-card">25</span>
                    </div>
                    <input type="range" id="inp-rad-card" min="0" max="40" value="25" style="width:100%; accent-color:var(--primary);" oninput="CellEditor.updateRadius('APP_CARD_RADIUS', this.value, 'val-rad-card')">
                    <div style="display:flex; gap:8px; margin-top:8px;">
                        <button type="button" class="btn-radius-preset" onclick="CellEditor.setPresetRadius('APP_CARD_RADIUS', 0, 'val-rad-card', 'inp-rad-card')" data-i18n-vis="btn_quad">Quadrado</button>
                        <button type="button" class="btn-radius-preset" onclick="CellEditor.setPresetRadius('APP_CARD_RADIUS', 25, 'val-rad-card', 'inp-rad-card')" data-i18n-vis="btn_red">Redondo</button>
                    </div>
                </div>
            </div>

            <div class="ed-section" id="sec-status">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_status">Card de status</span>
                    <span class="ed-desc" data-i18n-vis="vis_status_desc">Ajuste o card inferior exibido no aplicativo.</span>
                    <span class="ed-label" style="margin-top:10px;" data-i18n-vis="lbl_cor_card">Color do card</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-status">#1d242e73</span>
                        <div class="fcb-gradient"></div>
                        <input type="color" id="inp-color-status" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_CARD_STATUS_COLOR', this.id, 'alpha-status', 'hex-status')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-status">100%</span></label>
                        <input type="range" id="alpha-status" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_CARD_STATUS_COLOR', 'inp-color-status', this.id, 'hex-status')">
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <span class="ed-label" data-i18n-vis="lbl_log_conex">Log de Conexão</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-logger">#080e16c7</span>
                        <div class="fcb-gradient"></div>
                        <input type="color" id="inp-color-logger" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_DIALOG_LOGGER_COLOR', this.id, 'alpha-logger', 'hex-logger')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-logger">100%</span></label>
                        <input type="range" id="alpha-logger" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_DIALOG_LOGGER_COLOR', 'inp-color-logger', this.id, 'hex-logger')">
                    </div>
                </div>
            </div>

            <div class="ed-section" id="sec-campos">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_campos">Campos de Entrada</span>
                    <span class="ed-desc" data-i18n-vis="vis_campos_desc">Colores dos inputs (usuário, senha, etc).</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-campos">#1d242e73</span>
                        <div class="fcb-gradient"></div>
                        <input type="color" id="inp-color-campos" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_INPUT_COLOR', this.id, 'alpha-campos', 'hex-campos')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-campos">100%</span></label>
                        <input type="range" id="alpha-campos" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_INPUT_COLOR', 'inp-color-campos', this.id, 'hex-campos')">
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
                        <span class="ed-label" style="margin:0;" data-i18n-vis="lbl_arred_camp">Arredondamento dos campos</span>
                        <span style="color:var(--text-main); font-weight:800; font-size:0.9rem;" id="val-rad-campos">25</span>
                    </div>
                    <input type="range" id="inp-rad-campos" min="0" max="40" value="25" style="width:100%; accent-color:var(--primary);" oninput="CellEditor.updateRadius('APP_INPUT_RADIUS', this.value, 'val-rad-campos')">
                    <div style="display:flex; gap:8px; margin-top:8px;">
                        <button type="button" class="btn-radius-preset" onclick="CellEditor.setPresetRadius('APP_INPUT_RADIUS', 0, 'val-rad-campos', 'inp-rad-campos')" data-i18n-vis="btn_quad">Quadrado</button>
                        <button type="button" class="btn-radius-preset" onclick="CellEditor.setPresetRadius('APP_INPUT_RADIUS', 25, 'val-rad-campos', 'inp-rad-campos')" data-i18n-vis="btn_red">Redondo</button>
                    </div>
                </div>
            </div>

            <div class="ed-section" id="sec-botoes">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_btn">Botões</span>
                    <span class="ed-desc" data-i18n-vis="vis_btn_desc">Color principal e arredondamento dos botões (INICIAR, ações).</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-botoes">#1d242e73</span>
                        <div class="fcb-gradient"></div>
                        <input type="color" id="inp-color-botoes" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_BUTTON_COLOR', this.id, 'alpha-botoes', 'hex-botoes')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-botoes">100%</span></label>
                        <input type="range" id="alpha-botoes" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_BUTTON_COLOR', 'inp-color-botoes', this.id, 'hex-botoes')">
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
                        <span class="ed-label" style="margin:0;" data-i18n-vis="lbl_arred">Arredondamento</span>
                        <span style="color:var(--text-main); font-weight:800; font-size:0.9rem;" id="val-rad-botoes">25</span>
                    </div>
                    <input type="range" id="inp-rad-botoes" min="0" max="40" value="25" style="width:100%; accent-color:var(--primary);" oninput="CellEditor.updateRadius('APP_BUTTON_RADIUS', this.value, 'val-rad-botoes')">
                    <div style="display:flex; gap:8px; margin-top:8px;">
                        <button type="button" class="btn-radius-preset" onclick="CellEditor.setPresetRadius('APP_BUTTON_RADIUS', 0, 'val-rad-botoes', 'inp-rad-botoes')" data-i18n-vis="btn_quad">Quadrado</button>
                        <button type="button" class="btn-radius-preset" onclick="CellEditor.setPresetRadius('APP_BUTTON_RADIUS', 25, 'val-rad-botoes', 'inp-rad-botoes')" data-i18n-vis="btn_red">Redondo</button>
                    </div>
                </div>
            </div>

            <div class="ed-section" id="sec-icones">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_icons">Ícones</span>
                    <span class="ed-desc" data-i18n-vis="vis_icons_desc">Controla a cor dos ícones desenhados no app.</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-icones">#FFFFFFFF</span>
                        <div class="fcb-gradient" style="background:linear-gradient(90deg, #fff, #aaa);"></div>
                        <input type="color" id="inp-color-icones" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_ICON_COLOR', this.id, 'alpha-icones', 'hex-icones')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-icones">100%</span></label>
                        <input type="range" id="alpha-icones" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_ICON_COLOR', 'inp-color-icones', this.id, 'hex-icones')">
                    </div>
                </div>
            </div>

            <div class="ed-section" id="sec-textos">
                <div>
                    <span class="ed-label" data-i18n-vis="vis_texts">Textos</span>
                    <span class="ed-desc" data-i18n-vis="vis_texts_desc">Controla a cor do texto principal do aplicativo.</span>
                    <div class="fake-color-box">
                        <span class="fcb-hex" id="hex-textos">#FFFFFFFF</span>
                        <div class="fcb-gradient" style="background:linear-gradient(90deg, #fff, #aaa);"></div>
                        <input type="color" id="inp-color-textos" class="native-color-picker" oninput="CellEditor.updateColorAndAlpha('APP_TEXT_COLOR', this.id, 'alpha-textos', 'hex-textos')">
                    </div>
                    <div class="alpha-slider-wrap">
                        <label><span data-i18n-vis="lbl_opa">Opacidade</span> <span id="lbl-alpha-textos">100%</span></label>
                        <input type="range" id="alpha-textos" class="alpha-slider" min="0" max="100" value="100" oninput="CellEditor.updateColorAndAlpha('APP_TEXT_COLOR', 'inp-color-textos', this.id, 'hex-textos')">
                    </div>
                </div>
            </div>

            <div class="ed-footer">
                <button type="button" class="ed-btn-close" onclick="CellEditor.close(event, true)" data-i18n-vis="vis_cancel">Cancelar</button>
                <button type="button" class="ed-btn-save" onclick="CellEditor.saveLayout()" data-i18n-vis="vis_save">Guardar alterações</button>
            </div>
        </div>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$updateUrlBase = "$protocol://" . $_SERVER['HTTP_HOST'] . "/api/update_api.php?uuid=" . $userUuid;

// =======================================================================================
// INÍCIO DO JAVASCRIPT GLOBAL DA PÁGINA (Tradução, Interação, AJAX e Lógica Completa)
// =======================================================================================
$extraJs = <<<JS
<script>
const dict = {
    'pt': {
        'app_title': 'Aplicación', 'btn_add': 'Agregar', 'btn_import': 'Importar', 'btn_sync': 'Sincronizar', 'btn_apk': 'Generar APK',
        'list_title': 'Lista de aplicativos', 
        'empty_title': 'Ningún layout', 'empty_desc': 'Adicione ou importe um layout para o seu aplicativo.',
        'toast_saved': 'Layout salvo con éxito!', 'toast_deleted': 'Layout apagado!', 'toast_active': 'Tema ativado!', 'toast_copied': 'Copiado para área de transferência!', 'toast_sync': 'Sincronização concluída!', 'toast_shared': 'Tema publicado na comunidade!',
        'limit_title': 'Limite atingido', 'limit_desc': 'Você pode criar até 20 layouts.',
        'sync_title': 'Sincronizar configurações', 'sync_desc': 'Esta ação atualiza a configuração padrão e a lista salva do aplicativo.',
        'confirm_del_title': 'Eliminar layout', 'confirm_del_desc': 'Deseja realmente apagar este layout?',
        'import_title': 'Importar configuração', 'import_desc': 'Cole o JSON, carregue um link bruto ou envie um arquivo.', 'imp_raw': 'Link bruto', 'imp_file': 'Arquivo JSON', 'imp_manual': 'JSON manual', 'btn_process': 'Processar',
        'export_title': 'Exportar configuração', 'export_desc': 'Exporte o JSON ou gere um link compartilhável.', 'btn_copy': 'Copiar', 'btn_down': 'Descargar', 'btn_link': 'Link API',
        'share_title': 'Publicar tema', 'share_desc': 'Compartilhe este tema no feed da comunidade.', 'lbl_desc': 'Descripción', 'ph_desc': 'Descreva o que este tema personaliza...', 'btn_publish': 'Publicar', 'btn_cancel': 'Cancelar', 'btn_confirm': 'Sincronizar', 'btn_del': 'Eliminar', 'items_page': 'Itens por pág.',
        'current_acc': 'ATIVO',
        // Dicionário do Modal Visual (Celular)
        'vis_edit_now': 'EDITANDO AGORA', 'vis_bg': 'Fundo', 'vis_bg_desc': 'Altere a cor principal ou troque a imagem de fundo.',
        'vis_logo': 'Logo', 'vis_logo_desc': 'Atualize a URL ou envie uma nova imagem para a logo.',
        'vis_card': 'Card', 'vis_card_desc': 'Controla a cor e o arredondamento do bloco principal.',
        'vis_status': 'Estado', 'vis_status_desc': 'Ajuste o card inferior exibido no aplicativo.',
        'vis_campos': 'Campos', 'vis_campos_desc': 'Colores dos inputs (usuário, senha, etc).',
        'vis_btn': 'Botões', 'vis_btn_desc': 'Color principal e arredondamento dos botões (INICIAR, ações).',
        'vis_icons': 'Ícones', 'vis_icons_desc': 'Controla a cor dos ícones desenhados no app.',
        'vis_texts': 'Textos', 'vis_texts_desc': 'Controla a cor do texto principal do aplicativo.',
        'lbl_cor': 'Color', 'lbl_img': 'Imagem', 'lbl_cor_bg': 'Color de fundo', 'lbl_opa': 'Opacidade',
        'lbl_url_img': 'URL da imagem', 'btn_env_fundo': 'Enviar fundo do dispositivo', 'btn_env_logo': 'Enviar logo do dispositivo',
        'lbl_cor_card': 'Color do card', 'lbl_arred': 'Arredondamento', 'btn_quad': 'Quadrado', 'btn_red': 'Redondo',
        'lbl_log_conex': 'Log de Conexão', 'lbl_arred_camp': 'Arredondamento dos campos',
        'vis_cancel': 'Cancelar', 'vis_save': 'Guardar alterações'
    },
    'en': {
        'app_title': 'Application', 'btn_add': 'Add', 'btn_import': 'Import', 'btn_sync': 'Sync', 'btn_apk': 'Generate APK',
        'list_title': 'Application List', 
        'empty_title': 'No Layout', 'empty_desc': 'Add or import a layout for your application.',
        'toast_saved': 'Layout successfully saved!', 'toast_deleted': 'Layout deleted!', 'toast_active': 'Theme activated!', 'toast_copied': 'Copied to clipboard!', 'toast_sync': 'Sync completed!', 'toast_shared': 'Theme published to community!',
        'limit_title': 'Limit Reached', 'limit_desc': 'You can create up to 20 layouts.',
        'sync_title': 'Sync Settings', 'sync_desc': 'This action updates the default config and saved app list.',
        'confirm_del_title': 'Delete Layout', 'confirm_del_desc': 'Are you sure you want to delete this layout?',
        'import_title': 'Import Configuration', 'import_desc': 'Paste JSON, load a raw link, or upload a file.', 'imp_raw': 'Raw Link', 'imp_file': 'JSON File', 'imp_manual': 'Manual JSON', 'btn_process': 'Process',
        'export_title': 'Export Configuration', 'export_desc': 'Export JSON or generate a shareable link.', 'btn_copy': 'Copy', 'btn_down': 'Download', 'btn_link': 'API Link',
        'share_title': 'Publish Theme', 'share_desc': 'Share this theme in the community feed.', 'lbl_desc': 'Description', 'ph_desc': 'Describe what this theme customizes...', 'btn_publish': 'Publish', 'btn_cancel': 'Cancel', 'btn_confirm': 'Sync', 'btn_del': 'Delete', 'items_page': 'Items per page',
        'current_acc': 'ACTIVE',
        // Dicionário do Modal Visual (Celular)
        'vis_edit_now': 'EDITING NOW', 'vis_bg': 'Background', 'vis_bg_desc': 'Change the main color or background image.',
        'vis_logo': 'Logo', 'vis_logo_desc': 'Update URL or upload a new logo image.',
        'vis_card': 'Card', 'vis_card_desc': 'Controls the color and rounding of the main block.',
        'vis_status': 'Estado', 'vis_status_desc': 'Adjust the bottom card displayed in the app.',
        'vis_campos': 'Fields', 'vis_campos_desc': 'Input colors (user, password, etc).',
        'vis_btn': 'Buttons', 'vis_btn_desc': 'Main color and rounding of buttons (START, actions).',
        'vis_icons': 'Icons', 'vis_icons_desc': 'Controls the color of the drawn icons.',
        'vis_texts': 'Texts', 'vis_texts_desc': 'Controls the main text color of the app.',
        'lbl_cor': 'Color', 'lbl_img': 'Image', 'lbl_cor_bg': 'Background color', 'lbl_opa': 'Opacity',
        'lbl_url_img': 'Image URL', 'btn_env_fundo': 'Upload background', 'btn_env_logo': 'Upload logo',
        'lbl_cor_card': 'Card color', 'lbl_arred': 'Rounding', 'btn_quad': 'Square', 'btn_red': 'Round',
        'lbl_log_conex': 'Connection Log', 'lbl_arred_camp': 'Fields rounding',
        'vis_cancel': 'Cancel', 'vis_save': 'Save changes'
    },
    'es': {
        'app_title': 'Aplicación', 'btn_add': 'Añadir', 'btn_import': 'Importar', 'btn_sync': 'Sincronizar', 'btn_apk': 'Generar APK',
        'list_title': 'Lista de aplicaciones', 
        'empty_title': 'Ningún diseño', 'empty_desc': 'Añade o importa un diseño para tu aplicación.',
        'toast_saved': '¡Diseño guardado con éxito!', 'toast_deleted': '¡Diseño eliminado!', 'toast_active': '¡Tema activado!', 'toast_copied': '¡Copiado al portapapeles!', 'toast_sync': '¡Sincronización completada!', 'toast_shared': '¡Tema publicado en la comunidad!',
        'limit_title': 'Límite Alcanzado', 'limit_desc': 'Podés crear hasta 20 diseños.',
        'sync_title': 'Sincronizar Ajustes', 'sync_desc': 'Esta acción actualiza la configuración predeterminada y la lista guardada de la aplicación.',
        'confirm_del_title': 'Eliminar diseño', 'confirm_del_desc': '¿Realmente deseas eliminar este diseño?',
        'import_title': 'Importar Configuración', 'import_desc': 'Pega el JSON, carga un enlace en bruto o sube un archivo.', 'imp_raw': 'Enlace en bruto', 'imp_file': 'Archivo JSON', 'imp_manual': 'JSON Manual', 'btn_process': 'Procesar',
        'export_title': 'Exportar Configuración', 'export_desc': 'Exporta el JSON o genera un enlace para compartir.', 'btn_copy': 'Copiar', 'btn_down': 'Descargar', 'btn_link': 'Enlace API',
        'share_title': 'Publicar Tema', 'share_desc': 'Comparte este tema en el feed de la comunidad.', 'lbl_desc': 'Descripción', 'ph_desc': 'Describe qué personaliza este tema...', 'btn_publish': 'Publicar', 'btn_cancel': 'Cancelar', 'btn_confirm': 'Sincronizar', 'btn_del': 'Eliminar', 'items_page': 'Ítems por pág.',
        'current_acc': 'ACTIVO',
        // Dicionário do Modal Visual (Celular)
        'vis_edit_now': 'EDITANDO AHORA', 'vis_bg': 'Fondo', 'vis_bg_desc': 'Cambia el color principal o la imagen de fondo.',
        'vis_logo': 'Logo', 'vis_logo_desc': 'Actualiza la URL o sube una nueva imagen para el logo.',
        'vis_card': 'Tarjeta', 'vis_card_desc': 'Controla el color y el redondeo del bloque principal.',
        'vis_status': 'Estado', 'vis_status_desc': 'Ajusta la tarjeta inferior mostrada en la app.',
        'vis_campos': 'Campos', 'vis_campos_desc': 'Colores de entradas (usuario, contraseña, etc).',
        'vis_btn': 'Botones', 'vis_btn_desc': 'Color principal y redondeo de botones (INICIAR, acciones).',
        'vis_icons': 'Iconos', 'vis_icons_desc': 'Controla el color de los iconos dibujados.',
        'vis_texts': 'Textos', 'vis_texts_desc': 'Controla el color del texto principal de la app.',
        'lbl_cor': 'Color', 'lbl_img': 'Imagen', 'lbl_cor_bg': 'Color de fondo', 'lbl_opa': 'Opacidad',
        'lbl_url_img': 'URL de imagen', 'btn_env_fundo': 'Subir fondo', 'btn_env_logo': 'Subir logo',
        'lbl_cor_card': 'Color tarjeta', 'lbl_arred': 'Redondeo', 'btn_quad': 'Cuadrado', 'btn_red': 'Redondo',
        'lbl_log_conex': 'Log de conexión', 'lbl_arred_camp': 'Redondeo de campos',
        'vis_cancel': 'Cancelar', 'vis_save': 'Guardar cambios'
    }
};

let layoutsData = [];
let currentPage = 1;
let itemsPerPage = 20;
const userUpdateUrl = '$updateUrlBase';

// Tradução Global e do Modal Visual
function getMsg(key) { 
    const lang = localStorage.getItem('app_language') || 'pt';
    const currentDict = dict[lang] || dict['pt'];
    return currentDict[key] || dict['pt'][key] || key; 
}

function applyI18n() { 
    document.querySelectorAll('[data-i18n]').forEach(el => { el.innerHTML = getMsg(el.getAttribute('data-i18n')); }); 
    document.querySelectorAll('[data-i18n-vis]').forEach(el => { el.innerHTML = getMsg(el.getAttribute('data-i18n-vis')); });
    const impRaw = document.getElementById('imp-raw-link');
    if(impRaw) impRaw.placeholder = "https://...";
}

// ESPIÃO INVISÍVEL - Garante que se você mudar o idioma na página principal, a lista e o modal atualizam!
document.addEventListener('click', (e) => {
    if(e.target.closest('.lang-option')) {
        setTimeout(() => { applyI18n(); if(layoutsData.length > 0) renderList(); }, 150);
    }
});

// Sobrescreve a função global do header se existir para dupla segurança
const existingSelectAppLang = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(typeof existingSelectAppLang === 'function') {
        existingSelectAppLang(langCode);
    } else {
        localStorage.setItem('app_language', langCode);
    }
    applyI18n();
    if(layoutsData.length > 0) renderList(); 
};

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

function fetchData() {
    fetch('?action=list_data', {method:'POST'}).then(r=>r.json()).then(res => {
        if(res.success) { 
            layoutsData = res.layouts; 
            renderList(); 
            applyI18n(); 
        }
    });
}

function getVal(arr, key, def) {
    if(!Array.isArray(arr)) return def;
    let item = arr.find(x => x.name === key);
    return item ? (item.value ?? def) : def;
}

// Renderização Suprema da Lista com Webview Nativo no Preview
function renderList() {
    const listEl = document.getElementById('layout-list');
    const pgContainer = document.getElementById('pagination-container');
    
    if(layoutsData.length === 0) {
        listEl.innerHTML = `<div class="empty-state"><div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div><h3 class="es-title">\${getMsg('empty_title')}</h3><p class="es-desc">\${getMsg('empty_desc')}</p></div>`;
        pgContainer.style.display = 'none'; return;
    }

    const totalPages = Math.ceil(layoutsData.length / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginated = layoutsData.slice(startIndex, startIndex + itemsPerPage);

    let html = '';
    paginated.forEach((l, index) => {
        const isActive = l.is_active;
        const cardClass = isActive ? 'active-layout' : '';
        const badgeHtml = isActive ? `<span class="lc-badge">\${getMsg('current_acc') || 'ATIVO'}</span>` : `<span></span>`;
        
        // Bloqueios de Ação Baseados na Posição
        const btnActivar = isActive ? '' : `<button type="button" class="btn-circle-act check" onclick="activateLayout('\${l.id}')" title="Activar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></button>`;
        const btnDelete = (isActive && index === 0) ? '' : `<button type="button" class="btn-circle-act del" onclick="confirmDel('\${l.id}')" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>`;

        const arr = l.layout_data;
        const bgType = getVal(arr, 'APP_BACKGROUND_TYPE', {selected: 'COLOR'}).selected;
        let mockBg = getVal(arr, 'APP_BACKGROUND_COLOR', '#080e16c6');
        if(bgType === 'IMAGE') {
            const imgUrl = getVal(arr, 'APP_BACKGROUND_IMAGE', '');
            if(imgUrl) mockBg = `url('\${imgUrl}')`;
        }

        const mockCard = getVal(arr, 'APP_CARD_COLOR', '#1d242e73');
        const mockInput = getVal(arr, 'APP_INPUT_COLOR', '#1d242e73');
        const mockBtn = getVal(arr, 'APP_BUTTON_COLOR', '#1d242e73');
        const mockText = getVal(arr, 'APP_TEXT_COLOR', '#FFFFFF');
        const mockIcon = getVal(arr, 'APP_ICON_COLOR', '#FFFFFF');
        const mockRadC = getVal(arr, 'APP_CARD_RADIUS', 25) + 'px';
        const mockRadI = getVal(arr, 'APP_INPUT_RADIUS', 25) + 'px';
        const mockRadB = getVal(arr, 'APP_BUTTON_RADIUS', 25) + 'px';
        const mockLogger = getVal(arr, 'APP_DIALOG_LOGGER_COLOR', '#080e16c7');
        const logo = getVal(arr, 'APP_LOGO', '');

        // VERIFICAÇÃO INTELIGENTE DO WEBVIEW PARA RENDERIZAR NA LISTA PRINCIPAL
        const useWebviewVal = getVal(arr, 'APP_LAYOUT_WEBVIEW_ENABLED', false);
        const isWebviewActive = (useWebviewVal === true || String(useWebviewVal) === "true" || useWebviewVal === 1);
        const htmlWebView = getVal(arr, 'APP_LAYOUT_WEBVIEW', '');

        const mockStyle = `--mock-bg: \${mockBg}; --mock-el: \${mockInput}; --mock-card: \${mockCard}; --mock-btn: \${mockBtn}; --mock-text: \${mockText}; --mock-icon: \${mockIcon}; --mock-radius: \${mockRadI}; --mock-btn-radius: \${mockRadB};`;

        let phoneInnerHtml = '';

        if(isWebviewActive && htmlWebView.trim() !== '') {
            // MOSTRA O HTML PREVIEW PERFEITAMENTE DENTRO DO CELULAR DA LISTA
            const safeHtml = htmlWebView.replace(/"/g, '&quot;');
            phoneInnerHtml = `<iframe srcdoc="\${safeHtml}" style="width:100%; height:100%; min-height: 380px; border:none; border-radius: 20px; flex: 1; background: transparent;"></iframe>`;
        } else {
            // MOSTRA O LAYOUT NATIVO CLÁSSICO
            phoneInnerHtml = `
                \${logo ? `<img src="\${logo}" style="width:60px; height:60px; margin: 0 auto; object-fit:contain; flex-shrink:0;">` : ''}
                <div style="background:var(--mock-card); border-radius:\${mockRadC}; padding:16px; border:1px solid rgba(255,255,255,0.05); display:flex; flex-direction:column; gap:12px; flex-shrink:0;">
                    <div class="mock-field mock-select"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> <span style="color:var(--mock-text)">configuração</span> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                    <div class="mock-field"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <span style="color:var(--mock-text)">usuário</span></div>
                    <div class="mock-field"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> <span style="color:var(--mock-text)">senha</span></div>
                    <div class="mock-btn-row">
                        <div class="mock-btn-main" style="background:var(--mock-btn); color:var(--mock-text);">INICIAR</div>
                        <div class="mock-btn-circle" style="background:var(--mock-btn); color:var(--mock-icon);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></div>
                        <div class="mock-btn-circle" style="background:var(--mock-btn); color:var(--mock-icon);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                        <div class="mock-btn-circle" style="background:var(--mock-btn); color:var(--mock-icon);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
                    </div>
                </div>
                <div class="mock-logger" style="background:\${mockLogger}; border-radius:\${mockRadC}; flex-shrink:0;"></div>
            `;
        }

        html += `
            <div class="layout-card \${cardClass}" id="card-\${l.id}">
                <div class="lc-header">
                    <div class="lc-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px; color:var(--text-muted);"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> \${l.name}</div>
                    \${badgeHtml}
                </div>
                
                <div class="phone-mockup-wrapper" onclick="CellEditor.open('\${l.id}')">
                    <div class="phone-mockup-container" style="\${mockStyle}; \${bgType === 'IMAGE' ? 'background-image: '+mockBg+'; background-size: cover; background-position: center;' : 'background-color: '+mockBg+';'}">
                        \${phoneInnerHtml}
                    </div>
                </div>

                <div class="layout-actions-row">
                    \${btnActivar}
                    <button type="button" class="btn-circle-act" onclick="openExternalAppModal('\${l.id}')" title="Configuraciones Avançadas">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button type="button" class="btn-circle-act" onclick="CellEditor.open('\${l.id}')" title="Editor Visual">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </button>
                    <button type="button" class="btn-circle-act" onclick="openExportModal('\${l.id}')" title="Exportar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </button>
                    <button type="button" class="btn-circle-act" onclick="openShareModal('\${l.id}')" title="Compartilhar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </button>
                    \${btnDelete}
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
// AÇÕES DE LIMITES E BANCO
// ==========================================
function checkLimit() {
    if(layoutsData.length >= 20) {
        const isDark = document.documentElement.classList.contains('dark');
        Swal.fire({
            scrollbarPadding: false,
            html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(245,158,11,0.1);color:var(--warning);display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><h2 class="swal-title-custom">\${getMsg('limit_title')}</h2></div><p class="swal-desc-custom">\${getMsg('limit_desc')}</p>`,
            customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', actions: 'swal2-actions' },
            background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: false, confirmButtonText: 'Entendi'
        });
        return true;
    }
    return false;
}

function addLayout() {
    if(checkLimit()) return;
    fetch('?action=save_layout', {method:'POST', body: JSON.stringify({layout: {name: 'Nuevo Layout'}})}).then(r=>r.json()).then(res=>{
        if(res.success) { showToast('toast_saved'); fetchData(); }
    });
}

function activateLayout(id) {
    fetch('?action=activate_layout', {method:'POST', body: JSON.stringify({id})}).then(r=>r.json()).then(res=>{
        if(res.success) { showToast('toast_active'); fetchData(); }
    });
}

function confirmDel(id) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><h2 class="swal-title-custom">\${getMsg('confirm_del_title')}</h2></div><p class="swal-desc-custom">\${getMsg('confirm_del_desc')}</p>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('btn_del'), cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            fetch('?action=delete_layout', {method:'POST', body: JSON.stringify({id})}).then(() => { showToast('toast_deleted', 'error'); fetchData(); });
        }
    });
}

function syncDatabase() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,0.1);color:#3b82f6;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></div><h2 class="swal-title-custom">\${getMsg('sync_title')}</h2></div><p class="swal-desc-custom">\${getMsg('sync_desc')}</p><div style="background:var(--inner-bg); padding:16px; border-radius:12px; font-size:0.85rem; font-weight:600; color:var(--text-muted); text-align:left; border:1px solid var(--card-border);">Esta ação exige confirmação explícita e será executada imediatamente após continuar.</div>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('btn_confirm'), cancelButtonText: getMsg('btn_cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({
                scrollbarPadding: false,
                html: `<div style="display:flex; flex-direction:column; align-items:center; gap:16px; padding:20px 0;"><svg class="spin-anim" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" style="width:40px;"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg><h3 style="margin:0; font-family:'Manrope',sans-serif; font-weight:800; color:var(--text-main);">Sincronizando banco de dados...</h3></div>`,
                showConfirmButton: false, allowOutsideClick: false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}
            });
            // OTIMIZADO: Recarrega a página automaticamente em 1.5s
            setTimeout(() => { Swal.close(); showToast('toast_sync'); setTimeout(()=> window.location.reload(true), 500); }, 1500); 
        }
    });
}

// CHAMAR O MODAL DO LÁPIS (EDITOR AVANÇADO DE TODAS AS LAVANCAS)
function openExternalAppModal(id) {
    if(typeof AppLayoutModalManager !== 'undefined') {
        let lData = layoutsData.find(x => String(x.id) === String(id));
        AppLayoutModalManager.open(lData);
    } else { showToastRaw('Erro: modal_app-layout.php não incluído!', 'error'); }
}

// ==========================================
// MÁGICA REAL: O EDITOR DE CELULAR DO VÍDEO
// ==========================================
const CellEditor = (function() {
    let currentLayout = null;
    let localData = [];

    function open(id) {
        let l = layoutsData.find(x => String(x.id) === String(id));
        if(!l) return;
        currentLayout = l;
        localData = JSON.parse(JSON.stringify(l.layout_data));
        
        applyI18n(); 
        document.getElementById('editorOverlay').classList.add('show');
        renderMockup();
        switchMode('fundo', document.getElementById('btn-mode-fundo'));
    }

    function close(e, force = false) {
        if(e && e.target.id !== 'editorOverlay' && !force) return;
        document.getElementById('editorOverlay').classList.remove('show');
    }

    function switchMode(mode, btn) {
        document.querySelectorAll('.btn-editor-mode').forEach(b => b.classList.remove('active'));
        if(!btn) btn = document.getElementById('btn-mode-' + mode);
        if(btn) btn.classList.add('active');
        
        document.querySelectorAll('.ed-section').forEach(s => s.classList.remove('active'));
        document.getElementById('sec-' + mode).classList.add('active');

        const titles = {
            'fundo': [getMsg('vis_bg'), getMsg('vis_bg_desc')], 'logo': [getMsg('vis_logo'), getMsg('vis_logo_desc')],
            'card': [getMsg('vis_card'), getMsg('vis_card_desc')], 'status': [getMsg('vis_status'), getMsg('vis_status_desc')],
            'campos': [getMsg('vis_campos'), getMsg('vis_campos_desc')], 'botoes': [getMsg('vis_btn'), getMsg('vis_btn_desc')],
            'icones': [getMsg('vis_icons'), getMsg('vis_icons_desc')], 'textos': [getMsg('vis_texts'), getMsg('vis_texts_desc')]
        };
        document.getElementById('ed-current-mode-title').innerText = titles[mode][0];
        document.getElementById('ed-current-mode-desc').innerText = titles[mode][1];
        
        const badge = document.getElementById('mockup-badge');
        if(badge) {
            badge.innerText = titles[mode][0];
            badge.classList.add('show');
        }
        
        syncInputs();
    }

    // A MÁGICA DA BOLINHA TRANSPARENTE: O Clique DENTRO do celular
    function selectElement(mode, el) {
        document.querySelectorAll('.editor-phone-wrap .mockup-focus').forEach(x => x.classList.remove('mockup-focus'));
        if(mode !== 'fundo' && el) el.classList.add('mockup-focus');
        switchMode(mode, null);
    }

    function parseHexAlpha(hex8) {
        if(!hex8 || hex8.length < 9) return { hex: hex8 ? hex8.substring(0,7) : '#080e16', alpha: 100 };
        const alphaHex = hex8.substring(7, 9);
        const alpha = Math.round((parseInt(alphaHex, 16) / 255) * 100);
        return { hex: hex8.substring(0,7), alpha: alpha };
    }

    function syncInputs() {
        const bgType = getVal(localData, 'APP_BACKGROUND_TYPE', {selected: 'COLOR'}).selected;
        toggleFundoType(bgType);
        
        const cFundo = parseHexAlpha(getVal(localData, 'APP_BACKGROUND_COLOR', '#080e16FF'));
        document.getElementById('inp-color-fundo').value = cFundo.hex;
        document.getElementById('alpha-fundo').value = cFundo.alpha;
        document.getElementById('lbl-alpha-fundo').innerText = cFundo.alpha + '%';
        document.getElementById('hex-fundo').innerText = getVal(localData, 'APP_BACKGROUND_COLOR', '#080e16c7');
        
        document.getElementById('inp-img-fundo').value = getVal(localData, 'APP_BACKGROUND_IMAGE', '');
        document.getElementById('inp-img-logo').value = getVal(localData, 'APP_LOGO', '');

        const cCard = parseHexAlpha(getVal(localData, 'APP_CARD_COLOR', '#1d242eFF'));
        document.getElementById('inp-color-card').value = cCard.hex;
        document.getElementById('alpha-card').value = cCard.alpha;
        document.getElementById('lbl-alpha-card').innerText = cCard.alpha + '%';
        document.getElementById('hex-card').innerText = getVal(localData, 'APP_CARD_COLOR', '#1d242e73');
        document.getElementById('inp-rad-card').value = getVal(localData, 'APP_CARD_RADIUS', 25);
        document.getElementById('val-rad-card').innerText = getVal(localData, 'APP_CARD_RADIUS', 25);

        const cEstado = parseHexAlpha(getVal(localData, 'APP_CARD_STATUS_COLOR', '#1d242eFF'));
        document.getElementById('inp-color-status').value = cEstado.hex;
        document.getElementById('alpha-status').value = cEstado.alpha;
        document.getElementById('lbl-alpha-status').innerText = cEstado.alpha + '%';
        document.getElementById('hex-status').innerText = getVal(localData, 'APP_CARD_STATUS_COLOR', '#1d242e73');

        const cLogger = parseHexAlpha(getVal(localData, 'APP_DIALOG_LOGGER_COLOR', '#080e16FF'));
        document.getElementById('inp-color-logger').value = cLogger.hex;
        document.getElementById('alpha-logger').value = cLogger.alpha;
        document.getElementById('lbl-alpha-logger').innerText = cLogger.alpha + '%';
        document.getElementById('hex-logger').innerText = getVal(localData, 'APP_DIALOG_LOGGER_COLOR', '#080e16c7');

        const cCampos = parseHexAlpha(getVal(localData, 'APP_INPUT_COLOR', '#1d242eFF'));
        document.getElementById('inp-color-campos').value = cCampos.hex;
        document.getElementById('alpha-campos').value = cCampos.alpha;
        document.getElementById('lbl-alpha-campos').innerText = cCampos.alpha + '%';
        document.getElementById('hex-campos').innerText = getVal(localData, 'APP_INPUT_COLOR', '#1d242e73');
        document.getElementById('inp-rad-campos').value = getVal(localData, 'APP_INPUT_RADIUS', 25);
        document.getElementById('val-rad-campos').innerText = getVal(localData, 'APP_INPUT_RADIUS', 25);

        const cBotoes = parseHexAlpha(getVal(localData, 'APP_BUTTON_COLOR', '#1d242eFF'));
        document.getElementById('inp-color-botoes').value = cBotoes.hex;
        document.getElementById('alpha-botoes').value = cBotoes.alpha;
        document.getElementById('lbl-alpha-botoes').innerText = cBotoes.alpha + '%';
        document.getElementById('hex-botoes').innerText = getVal(localData, 'APP_BUTTON_COLOR', '#1d242e73');
        document.getElementById('inp-rad-botoes').value = getVal(localData, 'APP_BUTTON_RADIUS', 25);
        document.getElementById('val-rad-botoes').innerText = getVal(localData, 'APP_BUTTON_RADIUS', 25);

        const cIcones = parseHexAlpha(getVal(localData, 'APP_ICON_COLOR', '#ffffffff'));
        document.getElementById('inp-color-icones').value = cIcones.hex;
        document.getElementById('alpha-icones').value = cIcones.alpha;
        document.getElementById('lbl-alpha-icones').innerText = cIcones.alpha + '%';
        document.getElementById('hex-icones').innerText = getVal(localData, 'APP_ICON_COLOR', '#FFFFFFFF');

        const cTextos = parseHexAlpha(getVal(localData, 'APP_TEXT_COLOR', '#ffffffff'));
        document.getElementById('inp-color-textos').value = cTextos.hex;
        document.getElementById('alpha-textos').value = cTextos.alpha;
        document.getElementById('lbl-alpha-textos').innerText = cTextos.alpha + '%';
        document.getElementById('hex-textos').innerText = getVal(localData, 'APP_TEXT_COLOR', '#FFFFFFFF');
    }

    function toggleFundoType(type) {
        updateProp('APP_BACKGROUND_TYPE', {options: [{label:"Imagem",value:"IMAGE"},{label:"Color",value:"COLOR"}], selected: type}, 'SELECT');
        if(type === 'COLOR') {
            document.getElementById('fundo-t-cor').classList.add('active');
            document.getElementById('fundo-t-img').classList.remove('active');
            document.getElementById('fundo-box-cor').style.display = 'block';
            document.getElementById('fundo-box-img').style.display = 'none';
        } else {
            document.getElementById('fundo-t-cor').classList.remove('active');
            document.getElementById('fundo-t-img').classList.add('active');
            document.getElementById('fundo-box-cor').style.display = 'none';
            document.getElementById('fundo-box-img').style.display = 'block';
        }
        renderMockup();
    }

    function updateColorAndAlpha(key, hexInputId, alphaSliderId, displayHexId) {
        const hex = document.getElementById(hexInputId).value;
        const alpha = parseInt(document.getElementById(alphaSliderId).value);
        document.getElementById('lbl-' + alphaSliderId).innerText = alpha + '%';
        
        let aHex = Math.round((alpha / 100) * 255).toString(16).padStart(2, '0').toUpperCase();
        const fullHex = hex.toUpperCase() + aHex;
        
        document.getElementById(displayHexId).innerText = fullHex;
        updateProp(key, fullHex, 'COLOR');
        renderMockup();
    }

    function updateRadius(key, val, lblId) {
        document.getElementById(lblId).innerText = val;
        updateProp(key, parseInt(val), 'INTEGER');
        renderMockup();
    }

    function setPresetRadius(key, val, lblId, inputId) {
        document.getElementById(inputId).value = val;
        updateRadius(key, val, lblId);
    }

    function updateBgImage(val) { updateProp('APP_BACKGROUND_IMAGE', val, 'IMAGE'); renderMockup(); }

    function updateProp(key, value, type) {
        let item = localData.find(x => x.name === key);
        if(item) { item.value = value; } 
        else { localData.push({name: key, value: value, type: type}); }
        if(key === 'APP_LOGO' || key === 'APP_BACKGROUND_IMAGE') renderMockup();
    }

    function uploadBase64(event, key) {
        const file = event.target.files[0]; if(!file) return;
        const r = new FileReader();
        r.onload = function(e) {
            updateProp(key, e.target.result, 'IMAGE');
            if(key === 'APP_BACKGROUND_IMAGE') document.getElementById('inp-img-fundo').value = 'Imagem Carregada do Dispositivo';
            if(key === 'APP_LOGO') document.getElementById('inp-img-logo').value = 'Logo Carregada do Dispositivo';
            showToastRaw('Upload concluído! Atualizado na tela.', 'info');
        };
        r.readAsDataURL(file);
    }

    function renderMockup() {
        const arr = localData;
        const bgType = getVal(arr, 'APP_BACKGROUND_TYPE', {selected: 'COLOR'}).selected;
        let mockBg = getVal(arr, 'APP_BACKGROUND_COLOR', '#080e16');
        if(bgType === 'IMAGE') { const imgUrl = getVal(arr, 'APP_BACKGROUND_IMAGE', ''); if(imgUrl) mockBg = `url('\${imgUrl}')`; }

        const mockCard = getVal(arr, 'APP_CARD_COLOR', '#1d242e');
        const mockInput = getVal(arr, 'APP_INPUT_COLOR', '#1d242e');
        const mockBtn = getVal(arr, 'APP_BUTTON_COLOR', '#1d242e');
        const mockText = getVal(arr, 'APP_TEXT_COLOR', '#ffffff');
        const mockIcon = getVal(arr, 'APP_ICON_COLOR', '#ffffff');
        const mockRadC = getVal(arr, 'APP_CARD_RADIUS', 25) + 'px';
        const mockRadI = getVal(arr, 'APP_INPUT_RADIUS', 25) + 'px';
        const mockRadB = getVal(arr, 'APP_BUTTON_RADIUS', 25) + 'px';
        const mockLogger = getVal(arr, 'APP_DIALOG_LOGGER_COLOR', '#080e16');
        
        const logo = getVal(arr, 'APP_LOGO', '');
        const style = `--mock-bg: \${mockBg}; --mock-el: \${mockInput}; --mock-card: \${mockCard}; --mock-btn: \${mockBtn}; --mock-text: \${mockText}; --mock-icon: \${mockIcon}; --mock-radius: \${mockRadI}; --mock-btn-radius: \${mockRadB};`;

        const useWebviewVal = getVal(arr, 'APP_LAYOUT_WEBVIEW_ENABLED', false);
        const isWebviewActive = (useWebviewVal === true || String(useWebviewVal) === "true" || useWebviewVal === 1);
        const htmlWebView = getVal(arr, 'APP_LAYOUT_WEBVIEW', '');

        let innerContent = '';

        if (isWebviewActive && htmlWebView.trim() !== '') {
            const safeHtml = htmlWebView.replace(/"/g, '&quot;');
            innerContent = `
                <div id="mockup-badge">Webview Activo</div>
                <iframe srcdoc="\${safeHtml}" style="width:100%; height:100%; border:none; border-radius: 20px; flex: 1; background: transparent; z-index:5;"></iframe>
            `;
        } else {
            innerContent = `
                <div id="mockup-badge">Fundo</div>
                
                \${logo ? `<img src="\${logo}" onclick="event.stopPropagation(); CellEditor.selectElement('logo', this)" style="width:50px; height:50px; margin: 0 auto; object-fit:contain; flex-shrink:0; cursor:pointer; border-radius:8px; z-index:5;">` : ''}
                
                <div onclick="event.stopPropagation(); CellEditor.selectElement('card', this)" style="background:var(--mock-card); border-radius:\${mockRadC}; padding:14px 12px; border:1px solid rgba(255,255,255,0.05); display:flex; flex-direction:column; gap:10px; flex-shrink:0; cursor:pointer; z-index:5;">
                    <div class="mock-field mock-select" onclick="event.stopPropagation(); CellEditor.selectElement('campos', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> <span style="color:var(--mock-text)">configuração</span> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                    <div class="mock-field" onclick="event.stopPropagation(); CellEditor.selectElement('campos', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <span style="color:var(--mock-text)">usuário</span></div>
                    <div class="mock-field" onclick="event.stopPropagation(); CellEditor.selectElement('campos', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> <span style="color:var(--mock-text)">senha</span></div>
                    
                    <div class="mock-btn-row">
                        <div class="mock-btn-main" onclick="event.stopPropagation(); CellEditor.selectElement('botoes', this)" style="background:var(--mock-btn); color:var(--mock-text);">INICIAR</div>
                        <div class="mock-btn-circle" onclick="event.stopPropagation(); CellEditor.selectElement('icones', this)" style="background:var(--mock-btn); color:var(--mock-icon);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg></div>
                        <div class="mock-btn-circle" onclick="event.stopPropagation(); CellEditor.selectElement('icones', this)" style="background:var(--mock-btn); color:var(--mock-icon);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                        <div class="mock-btn-circle" onclick="event.stopPropagation(); CellEditor.selectElement('icones', this)" style="background:var(--mock-btn); color:var(--mock-icon);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
                    </div>
                </div>

                <div class="mock-logger" onclick="event.stopPropagation(); CellEditor.selectElement('status', this)" style="background:\${mockLogger}; border-radius:\${mockRadC}; flex-shrink:0; z-index:5;"></div>
            `;
        }

        document.getElementById('editor-mockup-target').innerHTML = `
            <div class="phone-mockup-container" onclick="CellEditor.selectElement('fundo', null)" style="\${style}; \${bgType === 'IMAGE' ? 'background-image: '+mockBg+'; background-size: cover; background-position: center;' : 'background-color: '+mockBg+';'}">
                \${innerContent}
            </div>
        `;
    }

    // SALVAMENTO OTIMIZADO DA EDIÇÃO VISUAL
    function saveLayout() {
        const isDark = document.documentElement.classList.contains('dark');
        Swal.fire({title:'Salvando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
        
        let copyToSave = JSON.parse(JSON.stringify(currentLayout));
        copyToSave.layout_data = localData;

        fetch('?action=save_layout', {method:'POST', body: JSON.stringify({layout: copyToSave})}).then(r=>r.json()).then(res=>{
            if(res.success) {
                close(null, true);
                fetchData(); // Recarrega o grid atrás rapidamente
                Swal.close();
                showToast('toast_saved');
            } else {
                Swal.fire('Erro', 'Falha ao salvar!', 'error');
            }
        });
    }

    return { open, close, selectElement, closeFromOutside: close, switchMode, toggleFundoType, updateColorAndAlpha, updateRadius, setPresetRadius, updateBgImage, updateProp, uploadBase64, saveLayout };
})();

// IMPORTAÇÃO
function openImportModal() {
    if(checkLimit()) return;
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button type="button" class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('import_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('import_desc')}</p>
                <label class="swal-label">\${getMsg('imp_raw')}</label>
                <div class="input-icon-wrap"><input type="text" id="imp-raw-link" class="swal-input" placeholder="https://exemplo.com/app-layout.json"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                <label class="swal-label">\${getMsg('imp_file')}</label>
                <div class="input-icon-wrap btn-style" onclick="document.getElementById('imp-file-btn').click()"><input type="text" class="swal-input" value="Procurar Arquivo JSON" readonly><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                <input type="file" id="imp-file-btn" style="display:none;" accept=".json" onchange="handleImportFileApp(event)">
                <label class="swal-label">\${getMsg('imp_manual')}</label>
                <textarea id="imp-manual-json" class="swal-input json-area" placeholder="Cole aqui o JSON exportado"></textarea>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm primary', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('btn_process'), cancelButtonText: getMsg('btn_cancel'),
        preConfirm: async () => {
            const rawLink = document.getElementById('imp-raw-link').value.trim();
            const manualJson = document.getElementById('imp-manual-json').value.trim();
            if(!rawLink && !manualJson) { Swal.showValidationMessage('Insira um Link ou JSON.'); return false; }
            return { rawLink, manualJson };
        }
    }).then(async (res) => {
        if(res.isConfirmed) {
            const data = res.value; let jsonStr = '';
            Swal.fire({title:'Procesando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            try {
                if(data.rawLink) { const r = await fetch(data.rawLink); jsonStr = await r.text(); } else { jsonStr = data.manualJson; }
                const parsedData = JSON.parse(jsonStr);
                const saveResp = await fetch('?action=import_layout', { method: 'POST', body: JSON.stringify({layout_data: parsedData}) });
                const result = await saveResp.json();
                if(result.success) { Swal.close(); fetchData(); showToastRaw('Layout importado!', 'success'); } 
                else { Swal.fire('Erro', result.error || 'Erro', 'error'); }
            } catch(e) { Swal.fire('Erro', 'JSON inválido ou URL falhou.', 'error'); }
        }
    });
}

window.handleImportFileApp = function(e) {
    const file = e.target.files[0]; if(!file) return; const reader = new FileReader();
    reader.onload = function(evt) { document.getElementById('imp-manual-json').value = evt.target.result; showToastRaw('Arquivo JSON Lido!', 'info'); }; 
    reader.readAsText(file);
};

// EXPORTAÇÃO PROTEGIDA E DOWNLOAD 100% FUNCIONAL
function openExportModal(id) {
    const isDark = document.documentElement.classList.contains('dark');
    let l = layoutsData.find(x => String(x.id) === String(id)); if(!l) return;
    
    let rawData = l.layout_data;
    if(typeof rawData === 'string') { try { rawData = JSON.parse(rawData); } catch(e){} }
    const jsonStr = JSON.stringify(rawData, null, 4);
    const sizeKB = (new Blob([jsonStr]).size / 1024).toFixed(1) + ' KB';
    const fieldsCount = Array.isArray(rawData) ? rawData.length : 0;

    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative;">
                <button type="button" class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('export_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('export_desc')}</p>
                <div class="export-stats"><span class="exp-badge">app_layout.json</span><span class="exp-badge">\${fieldsCount} campos</span><span class="exp-badge">\${sizeKB}</span></div>
                <textarea id="exp-textarea" class="swal-input json-area" readonly>\${jsonStr}</textarea>
                <div class="export-btn-grid">
                    <button type="button" class="btn-exp-act" onclick="navigator.clipboard.writeText(document.getElementById('exp-textarea').value); showToast('toast_copied');"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> \${getMsg('btn_copy')}</button>
                    <button type="button" class="btn-exp-act" onclick="downloadJSONApp()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> \${getMsg('btn_down')}</button>
                    <button type="button" class="btn-exp-act" onclick="navigator.clipboard.writeText(userUpdateUrl+'&layout=\${l.id}'); showToastRaw('Link protegido copiado!', 'info');"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> \${getMsg('btn_link')}</button>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom' }, background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showConfirmButton: false
    });
}

// ARQUIVO JSON GERADO E BAIXADO COM SUCESSO
window.downloadJSONApp = function() {
    try {
        const jsonText = document.getElementById('exp-textarea').value;
        if(!jsonText) return;
        const blob = new Blob([jsonText], { type: "application/json;charset=utf-8" });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = "app_layout.json";
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showToastRaw('Download iniciado!', 'success');
    } catch(e) {
        console.error(e);
        Swal.fire('Erro', 'Falha ao baixar o arquivo. Verifique as permissões do navegador.', 'error');
    }
}

// COMPARTILHAR - JOGA PRA PÁGINA DE TEMAS
function openShareModal(id) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        scrollbarPadding: false,
        html: `
            <div style="position:relative; text-align: left;">
                <button type="button" class="swal-close-btn" onclick="Swal.close()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <h2 class="swal-title-custom">\${getMsg('share_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('share_desc')}</p>
                
                <label class="swal-label">\${getMsg('lbl_desc')}</label>
                <textarea id="share-desc" class="swal-input" placeholder="\${getMsg('ph_desc')}" maxlength="120" oninput="document.getElementById('char-count').innerText = this.value.length + '/120'" style="min-height:80px; margin-bottom:5px; resize: none;"></textarea>
                <div id="char-count" style="text-align:right; font-size:0.75rem; color:var(--text-muted); font-weight:700;">0/120</div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm primary', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('btn_publish'), cancelButtonText: getMsg('btn_cancel'),
        preConfirm: () => {
            const desc = document.getElementById('share-desc').value.trim();
            if(!desc) { Swal.showValidationMessage('Insira uma descrição para publicar.'); return false; }
            return desc;
        }
    }).then((res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Publicando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
            fetch('?action=share_layout', {method:'POST', body: JSON.stringify({id: id, description: res.value})})
            .then(r=>r.json()).then(data => {
                if(data.success) {
                    showToast('toast_shared');
                    setTimeout(() => { window.location.href = '/temas.php'; }, 1000);
                } else { Swal.fire('Erro', data.error, 'error'); }
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', () => { fetchData(); });
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }

// INCLUSÃO DO MODAL EXTERNO NO FINAL DA PÁGINA (LÁPIS AVANÇADO)
@include_once __DIR__ . '/../modal/modal_app-layout.php';
?>