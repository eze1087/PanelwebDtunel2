<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Textos do App (Trem Bala V5 - Definitiva Premium 100% Sem Bugs)
 * @description Adicionado Sistema de Versión (+1 a cada alteração).
 * =======================================================================================
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbTextos = __DIR__ . '/../db/textos.json';
$dbVersion = __DIR__ . '/../db/version.json'; // Arquivo de controle de versão do app

// Inicializa arquivo de Textos e Versión se não existirem
foreach ([$dbTextos, $dbVersion] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) { mkdir(dirname($file), 0755, true); }
        // Se for o arquivo de versão, já começa com a versão 100, se não, array vazio
        $defaultContent = ($file === $dbVersion) ? ['version' => 100] : [];
        file_put_contents($file, json_encode($defaultContent));
        chmod($file, 0644);
    }
}

// JSON Padrão do Sistema Completo
$defaultTextsJson = '[{"label":"LBL_BTN_START","text":"<font color=\"#FF0000\"><b>INICIAR</b></font>"},{"label":"LBL_BTN_STOPPING","text":"PARANDO"},{"label":"LBL_BTN_STOP","text":"PARAR"},{"label":"LBL_BTN_RECONNECT","text":"RECONECTAR"},{"label":"LBL_DISCONNECTED","text":"<b>Desconectado</b>"},{"label":"LBL_RECORD","text":"REGISTRO"},{"label":"LBL_CHOOSE_CONFIG","text":"ESCOLHA UMA CONFIGURAÇÃO"},{"label":"LBL_UUID","text":"UUID V2Ray"},{"label":"LBL_USERNAME","text":"Nombre de usuario"},{"label":"LBL_PASSWORD","text":"Contraseña"},{"label":"LBL_UUID_INVALID","text":"UUID inválido"},{"label":"LBL_USERNAME_INVALID","text":"Nombre de usuario inválido"},{"label":"LBL_PASSWORD_INVALID","text":"Contraseña inválida"},{"label":"LBL_USERNAME_PASSWORD_INVALID","text":"Por favor, preencha o usuário e senha"},{"label":"LBL_CONFIG_TITLE","text":"Configuración"},{"label":"LBL_INITIALIZING_APP","text":"Inicializando aplicação"},{"label":"LBL_CONFIG_LOADED","text":"Configuración carregada"},{"label":"LBL_SEARCHING_FOR_UPDATES","text":"Procurando atualizações"},{"label":"LBL_CONFIG_UPDATED","text":"Configuraciones atualizadas con éxito"},{"label":"LBL_APP_CONFIG_UPDATED","text":"Configuraciones do app atualizadas con éxito"},{"label":"LBL_APP_TEXT_UPDATED","text":"Textos do app atualizados con éxito"},{"label":"LBL_CONFIG_NOT_SUPPORTED","text":"Parece que essa configuração não é suportada neste aplicativo"},{"label":"LBL_ERROR_ESTABLISHING_CONNECTION_SSH","text":"<b>Error al estabelecer conexão SSH</b>"},{"label":"LBL_RECONNECTION_PROCESS","text":"Processo de reconexão"},{"label":"LBL_RECONNECTING_IN","text":"Reconectando em: %ss"},{"label":"LBL_RECONNECTING","text":"Reconectando..."},{"label":"LBL_CONNECTING","text":"Conectando..."},{"label":"LBL_STOPPING","text":"Parando..."},{"label":"LBL_LOCAL_NETWORK_IP","text":"{NETWORK}: {IP}"},{"label":"LBL_LOCAL_IP","text":"IP Local: %s"},{"label":"LBL_LOCAL_IP_INFO","text":"IPv4 Local: %1$s/%2$d MTU: %3$d"},{"label":"LBL_DNS_SERVER_INFO","text":"Servidor DNS: %s"},{"label":"LBL_ROUTES_INFO_INCL","text":"Rotas: %s"},{"label":"LBL_ROUTES_INFO_EXCL","text":"Rotas excluídas: %s"},{"label":"LBL_INVALID_CONFIG_OVPN","text":"Configuración OVPN inválida"},{"label":"LBL_ERROR","text":"Erro: %s"},{"label":"LBL_CONFIG_NOT_FOUND_TITLE","text":"Configuración não encontrada"},{"label":"LBL_CONFIG_NOT_FOUND_TEXT","text":"Ningúna configuração encontrada"},{"label":"LBL_STOP_APPLICATION","text":"Para continuar, para a aplicação"},{"label":"LBL_FINGERPRINT","text":"<b>Impressão digital: %s</b>"},{"label":"LBL_AUTHENTICATING","text":"Autenticando..."},{"label":"LBL_AUTHENTICATION_SUCCESS","text":"<b>Autenticação realizada con éxito</>"},{"label":"LBL_AUTHENTICATION_FAILED","text":"Falha na autenticação"},{"label":"LBL_AUTHENTICATION_FAILED_TEXT","text":"Não foi possível autenticar com servidor."},{"label":"LBL_STATE_CONNECTED","text":"Conectado"},{"label":"LBL_STATE_DISCONNECTED","text":"Desconectado"},{"label":"LBL_STATE_CONNECTING","text":"Conectando"},{"label":"LBL_STATE_STOPPING","text":"Parando"},{"label":"LBL_STATE_NO_NETWORK","text":"Sem acesso à internet"},{"label":"LBL_STATE_AUTH","text":"Autenticando"},{"label":"LBL_STATE_AUTH_FAILED","text":"Falha na autenticação"},{"label":"LBL_STATE_UNKNOWN","text":"Desconhecido"},{"label":"LBL_STATE_ASSIGN_IP","text":"Atribuindo IP"},{"label":"LBL_STATE_ADD_ROUTES","text":"Adicionando rotas"},{"label":"LBL_STATE_RECONNECTING","text":"Reconectando"},{"label":"LBL_STATE_EXITING","text":"Saindo"},{"label":"LBL_STATE_RESOLVE","text":"Resolvendo"},{"label":"LBL_STATE_TCP_CONNECT","text":"Conectando (TCP)"},{"label":"LBL_STATE_VPN_GENERATE_CONFIG","text":"Gerando configuração"},{"label":"LBL_STATE_WAIT","text":"Aguardando"},{"label":"LBL_STATE_GET_CONFIG","text":"Obtendo configuração"},{"label":"LBL_VPN_ESTABLISHED","text":"<b>VPN estabelecido</b>"},{"label":"LBL_APP_VERSION","text":"<b>%s %s %s</b>"},{"label":"LBL_MOBILE_INFO","text":"<b>%s | %s | %s | %s</b>"},{"label":"LBL_ROUTE_REJECTED","text":"Rota rejeitada:"},{"label":"LBL_COULD_NOT_ADD_DNS","text":"Não foi possível adicionar DNS:"},{"label":"LBL_ERROR_INTERFACE_TUN","text":"Error al criar interface tun"},{"label":"LBL_OPENING_INTERFACE_TUN","text":"Abrindo interface tun"},{"label":"LBL_CHECKING_USER","text":"Verificando usuário..."},{"label":"LBL_CHECKING_USER_FAILED","text":"Falha ao verificar usuário"},{"label":"LBL_OVPN_STARTED","text":"OpenVPN iniciado"},{"label":"LBL_TLS_VERSION","text":"<b>Versión do TLS: %s</b>"},{"label":"LBL_TLS_ALGORITHM","text":"<b>Algoritmo TLS: %s</b>"},{"label":"LBL_INVALID_IP","text":"IP inválido: %s"},{"label":"LBL_CHECK_USER_TITLE","text":"INFO. DO USUÁRIO"},{"label":"LBL_CHECK_USER_MESSAGE","text":"👤 Nombre de usuario: {username}<br>📆 Expira em: {expiration_date}<br>📅 Días restantes: {expiration_days}<br>🚫 Conexoes: {count_connections}|{limit_connections}"},{"label":"LBL_NETWORK_STATUS","text":"Estado da rede: %s"},{"label":"LBL_WELCOME","text":"Bienvenido(a)"},{"label":"LBL_INFO","text":"Aviso"},{"label":"LBL_ALERT","text":"Alerta"},{"label":"LBL_SSH_LIB_NOT_FOUND","text":"SSH não encontrado"},{"label":"LBL_V2RAY_NOT_FOUND","text":"V2Ray não encontrado"},{"label":"LBL_OPENVPN_NOT_FOUND","text":"OpenVPN não encontrado"},{"label":"LBL_APP_UPDATE_TITLE","text":"ATUALIZAÇÃO DISPONÍVEL"},{"label":"LBL_APP_UPDATE_MESSAGE","text":"UMA NOVA VERSÃO DO APLICATIVO ESTÁ DISPONÍVEL."},{"label":"LBL_APP_UPDATE_BUTTON","text":"ATUALIZAR"},{"label":"LBL_APP_UPDATE_INSTALL","text":"INSTALAR"},{"label":"LBL_APP_UPDATE_DOWNLOADING","text":"BAIXANDO ATUALIZAÇÃO"},{"label":"LBL_APP_UPDATE_DOWNLOAD_COMPLETED","text":"DOWNLOAD COMPLETO"},{"label":"LBL_CONFIG_NOT_SELECTED","text":"Ningúna configuração selecionada"},{"label":"LBL_CONFIG_NOT_ACTIVE","text":"Parece que a configuração selecionada não está ativa"},{"label":"LBL_CLEAR_APP_TITLE","text":"LIMPAR APLICATIVO"},{"label":"LBL_CLEAR_APP_MESSAGE","text":"VOCÊ TEM CERTEZA QUE QUER LIMPAR O APLICATIVO?"},{"label":"LBL_VPN_PERMISSION_DENIED","text":"ERRO AO ESTABELECER CONEXÃO VPN"},{"label":"LBL_VPN_PERMISSION_DENIED_TEXT","text":"Desculpe, não foi possível estabelecer a conexão VPN."},{"label":"LBL_VPN_PERMISSION_DENIED_BTN","text":"ABRIR CONFIGURAÇÕES DE VPN"},{"label":"LBL_YES","text":"Sim"},{"label":"LBL_NO","text":"Não"},{"label":"LBL_CONFIG_IMPORT_TITLE","text":"IMPORTAR CONFIGURAÇÃO"},{"label":"LBL_CONFIG_IMPORT_MESSAGE","text":"FOI ENCONTRADO UMA CONFIGURAÇÃO NA AREA DE TRANSFERÊNCIA. DESEJA IMPORTAR?"},{"label":"LBL_CONFIG_IMPORT_BTN_IMPORT","text":"IMPORTAR"},{"label":"LBL_LIMITER_TITLE","text":"LIMITER"},{"label":"LBL_LIMITER_TEXT","text":"O número máximo de conexões permitidas foi atingido."},{"label":"LBL_VALIDATING_ACCESS","text":"Validando seu acesso..."},{"label":"LBL_DNS_FORWARDING_DISABLED","text":"<b>Encaminhamento de DNS desabilitado</b>"},{"label":"LBL_DNS_FORWARDING_ENABLED","text":"<b>Encaminhamento de DNS habilitado</b>"},{"label":"LBL_QUANTITY_PAYLOAD","text":"<b>PAYLOADS: %s</b>"},{"label":"LBL_QUANTITY_SNI","text":"<b>SNI: %s</b>"},{"label":"LBL_QUANTITY_PROXY","text":"<b>PROXIES: %s</b>"},{"label":"LBL_QUANTITY_SERVER","text":"<b>SERVIDORES: %s</b>"},{"label":"LBL_QUANTITY_PROCESS","text":"<b>PROCESSOS: %s</b>"},{"label":"LBL_PING_STARTED","text":"<b>PING INICIADO</b>"},{"label":"LBL_PING_MESSAGE","text":"<i>Ping: %sms</i>"},{"label":"LBL_PING_STOPPED","text":"<b>PING PARADO</b>"},{"label":"LBL_ASSISTANT_TITLE","text":"ATIVAR ASSISTENTE"},{"label":"LBL_ASSISTANT_TEXT","text":"Para continuar, você precisa configurar o aplicativo como assistente"},{"label":"LBL_ASSISTANT_BUTTON","text":"<b>ABRIR CONFIGURAÇÕES</b>"},{"label":"LBL_FORCE_AIRPLANE_MODE_TOGGLE","text":"<b>Tentando ativar e desativar o modo avião...</b>"},{"label":"LBL_MENU_HOTSPOT_TITLE","text":"LIGAR/DESLIGAR HOTSPOT"},{"label":"LBL_MENU_HOTSPOT_DESCRIPTION","text":"Ao clicar nessa opção, o hotspot do dispositivo será ligado ou desligado."},{"label":"LBL_MENU_AIRPLANE_TITLE","text":"ATIVAR/DESTIVAR MODO AVIAO"},{"label":"LBL_MENU_AIRPLANE_DESCRIPTION","text":"Ao clicar nessa opção, o modo avião do dispositivo será ativado ou desativado."},{"label":"LBL_MENU_APN_TITLE","text":"ABRIR CONFIGURAÇÃO DE APN"},{"label":"LBL_MENU_APN_DESCRIPTION","text":"Ao clicar nessa opção, a configuração de apn do dispositivo será aberta."},{"label":"LBL_MENU_NETWORK_TITLE","text":"ABRIR CONFIGURAÇÃO DE REDE"},{"label":"LBL_MENU_NETWORK_DESCRIPTION","text":"Ao clicar nessa opção, a configuração de rede do dispositivo será aberta."},{"label":"LBL_MENU_BATTERY_TITLE","text":"DESATIVAR OTIMIZAÇÃO DE BATERIA"},{"label":"LBL_MENU_BATTERY_DESCRIPTION","text":"Ao clicar nessa opção, a otimização de bateria será desativada"},{"label":"LBL_MENU_CLEAN_APP_TITLE","text":"LIMPAR DADOS DO APP"},{"label":"LBL_MENU_CLEAN_APP_DESCRIPTION","text":"Ao clicar nessa opção, todos os dados do app serão apagados."}]';
$defaultTexts = json_decode($defaultTextsJson, true);

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API INTERNA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean(); // Limpa buffer HTML para não corromper JSON
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    $allDbTexts = json_decode(file_get_contents($dbTextos), true) ?: [];
    
    // Função de gatilho para atualizar a versão
    $updateVersion = function() use ($dbVersion) {
        $v = json_decode(file_get_contents($dbVersion), true) ?: ['version' => 100];
        $v['version'] = (isset($v['version']) ? (int)$v['version'] : 100) + 1;
        file_put_contents($dbVersion, json_encode($v, JSON_PRETTY_PRINT));
    };
    
    if (!isset($allDbTexts[$sessionEmail])) {
        $allDbTexts[$sessionEmail] = $defaultTexts;
        file_put_contents($dbTextos, json_encode($allDbTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $userTexts = $allDbTexts[$sessionEmail];

    if ($action === 'list_texts') {
        echo json_encode(['success' => true, 'texts' => $userTexts]); exit;
    }

    if ($action === 'save_text') {
        $label = $input['label'] ?? '';
        $text  = $input['text'] ?? '';
        
        $updated = false;
        foreach ($userTexts as &$t) {
            if ($t['label'] === $label) { $t['text'] = $text; $updated = true; break; }
        }
        if (!$updated) { $userTexts[] = ['label' => $label, 'text' => $text]; }
        
        $allDbTexts[$sessionEmail] = $userTexts;
        file_put_contents($dbTextos, json_encode($allDbTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $updateVersion(); // Sobe a versão do app
        
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'import_texts') {
        $newTexts = $input['texts'] ?? [];
        if (is_array($newTexts) && !empty($newTexts)) {
            $mappedNew = [];
            foreach ($newTexts as $nt) { if (isset($nt['label']) && isset($nt['text'])) { $mappedNew[$nt['label']] = $nt['text']; } }
            
            foreach ($userTexts as &$t) {
                if (isset($mappedNew[$t['label']])) { $t['text'] = $mappedNew[$t['label']]; }
            }
            $allDbTexts[$sessionEmail] = $userTexts;
            file_put_contents($dbTextos, json_encode($allDbTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            $updateVersion(); // Sobe a versão do app
            
            echo json_encode(['success' => true]); exit;
        }
        echo json_encode(['success' => false, 'error' => 'JSON inválido']); exit;
    }

    if ($action === 'reset_single') {
        $label = $input['label'] ?? '';
        $defaultVal = '';
        foreach ($defaultTexts as $dt) { if ($dt['label'] === $label) { $defaultVal = $dt['text']; break; } }
        foreach ($userTexts as &$t) { if ($t['label'] === $label) { $t['text'] = $defaultVal; break; } }
        
        $allDbTexts[$sessionEmail] = $userTexts;
        file_put_contents($dbTextos, json_encode($allDbTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $updateVersion(); // Sobe a versão do app
        
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'reset_all') {
        $allDbTexts[$sessionEmail] = $defaultTexts;
        file_put_contents($dbTextos, json_encode($allDbTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $updateVersion(); // Sobe a versão do app
        
        echo json_encode(['success' => true]); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Textos';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - TEXTOS APP V5 (Definitiva 100% Sem Bug)
   ========================================================================== */
   
/* Z-Index Forçado para SweetAlert Nunca Ficar Atrás */
.swal2-container { z-index: 99999999 !important; }
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.home-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --success: #10b981; --danger: #ef4444; --icon-bg: #f3f4f6;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
}

:root.dark .home-wrapper, .dark .home-wrapper, body.dark .home-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #121214; --icon-bg: #27272a;
}

/* Modais Mais Premium e Destacados no Dark Mode */
body.dark .custom-modal { background: #18181b !important; border: 1px solid #3f3f46 !important; color: var(--text-main) !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,1) !important; }
body.dark .custom-modal .modal-header { border-bottom-color: #3f3f46 !important; }
body.dark .custom-modal .modal-title, body.dark .custom-modal .modal-label, body.dark .preview-box { color: var(--text-main) !important; }
body.dark .custom-modal svg, body.dark .text-card svg { stroke: currentColor !important; }
body.dark .edit-textarea, body.dark .preview-box, body.dark .editor-tools, body.dark .rgb-picker-wrapper { background: #0f0f11 !important; border-color: #3f3f46 !important; color: var(--text-main) !important; }
body.dark .toolbar-btn { background: #18181b !important; border-color: #3f3f46 !important; }

.home-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; }

.main-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.card-title { font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0 0 6px 0; }
.card-desc { font-size: 0.95rem; color: var(--text-muted); font-weight: 500; margin: 0 0 20px 0; }

/* Grid de Botões do Topo (Ajuste de Espaçamento) */
.action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-bottom: 24px; }
.btn-action { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); cursor: pointer; transition: transform 0.15s, background 0.2s, border-color 0.2s; }
.btn-action:active { transform: scale(0.96); }
.btn-action:hover { border-color: var(--text-main); color: var(--text-main); }
.btn-action.danger { background: rgba(239,68,68,0.05); color: var(--danger); border-color: rgba(239,68,68,0.2); }
.btn-action.danger:hover { background: var(--danger); color: white; border-color: var(--danger); }
.btn-action.sync-btn { background: var(--text-main); color: var(--card-bg); border: none; }
.btn-action.sync-btn:hover { background: var(--text-main); color: var(--card-bg); opacity: 0.9; }

/* Barra de Pesquisa Responsiva */
.search-container { display: flex; gap: 12px; flex-wrap: wrap; }
.search-input-wrap { position: relative; flex: 1; min-width: 200px; }
.search-input-wrap svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; pointer-events: none;}
.search-input { width: 100%; background: transparent; border: 2px solid var(--card-border); padding: 14px 14px 14px 44px; border-radius: 14px; color: var(--text-main); font-weight: 600; font-size: 0.95rem; transition: border-color 0.2s; }
.search-input:focus { border-color: var(--text-main); }
.btn-search { display: flex; align-items: center; justify-content: center; gap: 8px; background: transparent; color: var(--text-main); border: 2px solid var(--card-border); padding: 0 24px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: transform 0.15s; height: 52px;}
.btn-search:active { transform: scale(0.96); background: var(--inner-bg); }
.btn-search:hover { border-color: var(--text-main); }

.spin-anim { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Lista de Textos */
.texts-list { display: flex; flex-direction: column; gap: 20px; margin-top: 10px; }
.text-card { background: transparent; border: 2px solid var(--card-border); border-radius: 16px; padding: 20px; transition: transform 0.15s, border-color 0.2s; display: flex; flex-direction: column;}
.text-card:hover { border-color: var(--text-main); }
.tc-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.tc-id { background: var(--text-main); color: var(--card-bg); font-weight: 900; font-size: 0.75rem; padding: 4px 10px; border-radius: 8px; }
.text-label { font-size: 0.85rem; font-weight: 800; color: var(--text-main); font-family: 'Space Grotesk', monospace; word-break: break-all;}
.text-content { font-size: 0.95rem; color: var(--text-main); font-weight: 600; background: var(--inner-bg); border: 1px solid var(--card-border); padding: 14px; border-radius: 12px; margin-bottom: 20px; word-break: break-word; min-height: 48px; line-height: 1.4;}
.text-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.text-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 12px; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); font-weight: 800; cursor: pointer; transition: all 0.15s; outline: none; font-size: 0.95rem;}
.text-btn:active { transform: scale(0.95); }
.text-btn:hover { border-color: var(--text-main); color: var(--text-main); }
.text-btn-reset { color: var(--danger); background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2); }
.text-btn-reset:hover { background: var(--danger); color: white; border-color: var(--danger); }

/* ================= MODAIS (CUSTOM OVERLAY) ================= */
.custom-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 9999999; padding: 16px; opacity: 0; transition: opacity 0.3s ease; }
.custom-modal-overlay.active { display: flex; opacity: 1; }
.custom-modal { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px; width: 100%; max-width: 520px; padding: 24px; display: flex; flex-direction: column; gap: 16px; transform: translateY(30px) scale(0.95); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 60px rgba(0,0,0,0.6); }
.custom-modal-overlay.active .custom-modal { transform: translateY(0) scale(1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--card-border); padding-bottom: 16px; }
.modal-title { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0; }
.modal-close { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); cursor: pointer; transition: all 0.2s; }
.modal-close:hover { background: var(--danger); color: #fff; border-color: var(--danger); transform: rotate(90deg); }

/* Editor Visual Avançado e RGB */
.editor-tools { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.toolbar-top { display: flex; gap: 10px; align-items: center; }
.toolbar-btn { background: var(--card-bg); border: 2px solid var(--card-border); color: var(--text-main); border-radius: 12px; width: 44px; height: 44px; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: transform 0.15s, background 0.2s; display: flex; align-items: center; justify-content: center; }
.toolbar-btn:active { transform: scale(0.9); background: var(--text-main); color: var(--card-bg); border-color: var(--text-main); }

/* Color Picker RGB Moderno */
.rgb-picker-wrapper { display: flex; align-items: center; gap: 10px; flex: 1; background: var(--card-bg); border: 2px solid var(--card-border); padding: 6px 12px; border-radius: 12px; }
.rgb-input { -webkit-appearance: none; -moz-appearance: none; appearance: none; width: 32px; height: 32px; background: transparent; border: none; cursor: pointer; padding: 0; border-radius: 50%; overflow: hidden; }
.rgb-input::-webkit-color-swatch-wrapper { padding: 0; }
.rgb-input::-webkit-color-swatch { border: 2px solid var(--card-border); border-radius: 50%; }
.rgb-text-display { font-family: monospace; font-size: 0.85rem; font-weight: 800; color: var(--text-main); flex: 1; }
.btn-apply-color { background: var(--text-main); color: var(--card-bg); border: none; padding: 8px 14px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; cursor: pointer; transition: transform 0.15s; }
.btn-apply-color:active { transform: scale(0.9); }

.edit-textarea { width: 100%; height: 120px; padding: 16px; border-radius: 14px; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-main); outline: none; font-family: monospace; font-size: 0.95rem; resize: vertical; transition: border-color 0.2s; }
.edit-textarea:focus { border-color: var(--text-main); }
.preview-box { background: var(--inner-bg); border: 2px solid var(--card-border); border-radius: 14px; padding: 16px; min-height: 60px; color: var(--text-main); margin-top: 4px; word-break: break-word; font-weight: 600; font-size: 1rem; }
.modal-label { font-size: 0.8rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 4px; }

/* Importação File/Text */
.import-methods { display: flex; gap: 10px; margin-bottom: 16px; }
.method-btn { flex: 1; padding: 12px; border-radius: 12px; border: 2px solid var(--card-border); background: var(--inner-bg); color: var(--text-muted); font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; }
.method-btn.active { background: var(--text-main); color: var(--card-bg); border-color: var(--text-main); }
.import-section { display: none; }
.import-section.active { display: block; }
.file-upload-box { border: 2px dashed var(--card-border); border-radius: 16px; padding: 30px; text-align: center; cursor: pointer; background: var(--inner-bg); transition: border-color 0.2s; }
.file-upload-box:hover { border-color: var(--text-main); }
.file-upload-box input[type="file"] { display: none; }
.file-upload-text { font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-top: 10px; }

/* Custom Scrollbar Modal */
.custom-modal::-webkit-scrollbar { width: 6px; }
.custom-modal::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 10px; }

/* Toasts Elevados para não bugar embaixo */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 99999999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.error .toast-icon { background: var(--danger); }
.toast.info .toast-icon { background: var(--text-main); color: var(--card-bg); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); }
</style>

<div id="toast-container"></div>

<div class="home-wrapper">

    <div class="main-card">
        <h2 class="card-title" data-i18n="texts_title">Textos do Aplicación</h2>
        <p class="card-desc" data-i18n="texts_desc">Gerencie os textos e nomenclaturas exibidos dentro do aplicativo. Todas as edições refletem no JSON automaticamente.</p>
        
        <div class="action-grid">
            <button class="btn-action sync-btn" onclick="syncAllTexts()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span data-i18n="btn_sync">Sincronizar</span>
            </button>
            <button class="btn-action" onclick="openImportModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span data-i18n="btn_import">Importar</span>
            </button>
            <button class="btn-action" onclick="openExportModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span data-i18n="btn_export">Exportar</span>
            </button>
            <button class="btn-action danger" onclick="resetAllTexts()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span data-i18n="btn_reset_all">Resetar tudo</span>
            </button>
        </div>

        <div class="search-container">
            <div class="search-input-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por label ou conteúdo..." oninput="filterTexts()" data-i18n-placeholder="search_placeholder">
            </div>
            <button class="btn-search" id="btn-clear-main" onclick="clearSearch()">
                <span data-i18n="btn_clear">Limpar</span>
            </button>
        </div>
    </div>

    <div class="texts-list" id="textsListContainer">
        </div>
</div>

<div class="custom-modal-overlay" id="editModalOverlay">
    <div class="custom-modal">
        <div class="modal-header">
            <h3 class="modal-title" data-i18n="mdl_edit_title">Editar texto</h3>
            <button class="modal-close" onclick="closeModal('editModalOverlay')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        
        <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-subtle); font-family: monospace; letter-spacing: 1px;" id="editLabelName">LBL_NOME</div>
        
        <div class="editor-tools">
            <div class="toolbar-top">
                <button class="toolbar-btn" onclick="insertTag('b')" title="Negrito"><b>B</b></button>
                <button class="toolbar-btn" style="font-style: italic;" onclick="insertTag('i')" title="Itálico">I</button>
                
                <div class="rgb-picker-wrapper">
                    <input type="color" id="modernColorPicker" class="rgb-input" value="#ff0000">
                    <span class="rgb-text-display" id="hexDisplay">#ff0000</span>
                    <button class="btn-apply-color" onclick="applyColorFromPicker()">Aplicar Color</button>
                </div>
            </div>
        </div>

        <div>
            <label class="modal-label" data-i18n="lbl_raw_text">Texto Bruto</label>
            <textarea id="editInput" class="edit-textarea" oninput="updatePreview()"></textarea>
        </div>

        <div>
            <label class="modal-label" data-i18n="preview">Pré-visualização</label>
            <div id="editPreview" class="preview-box"></div>
        </div>

        <div style="display:flex; gap:16px; margin-top: 24px;">
            <button class="btn-action" style="flex:1;" onclick="closeModal('editModalOverlay')" data-i18n="btn_cancel">Cancelar</button>
            <button class="btn-action sync-btn" style="flex:1;" onclick="saveEditText()" data-i18n="btn_save_changes">Guardar alterações</button>
        </div>
    </div>
</div>

<div class="custom-modal-overlay" id="exportModalOverlay">
    <div class="custom-modal">
        <div class="modal-header">
            <h3 class="modal-title" data-i18n="mdl_export_title">Exportar textos</h3>
            <button class="modal-close" onclick="closeModal('exportModalOverlay')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <p class="card-desc" style="margin-bottom:0;" data-i18n="mdl_export_desc">Exporte os textos atuais em JSON ou baixe o arquivo físico para backup de segurança.</p>
        <textarea id="exportTextarea" class="edit-textarea" style="height: 180px;" readonly></textarea>
        <div style="display:flex; gap:16px; margin-top: 24px;">
            <button class="btn-action" style="flex:1;" onclick="copyExportJson()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> <span data-i18n="btn_copy_json">Copiar JSON</span>
            </button>
            <button class="btn-action sync-btn" style="flex:1;" onclick="downloadExportJson()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> <span data-i18n="btn_download">Descargar arquivo</span>
            </button>
        </div>
    </div>
</div>

<div class="custom-modal-overlay" id="importModalOverlay">
    <div class="custom-modal">
        <div class="modal-header">
            <h3 class="modal-title" data-i18n="mdl_import_title">Importar textos</h3>
            <button class="modal-close" onclick="closeModal('importModalOverlay')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        
        <div class="import-methods">
            <button class="method-btn active" onclick="switchImportTab('file')" id="tab-file">Arquivo .JSON</button>
            <button class="method-btn" onclick="switchImportTab('text')" id="tab-text">Texto Bruto</button>
        </div>

        <div class="import-section active" id="sec-file">
            <div class="file-upload-box" onclick="document.getElementById('fileInputJson').click()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:48px; color:var(--text-main);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="12"/><line x1="15" y1="15" x2="12" y2="12"/></svg>
                <div class="file-upload-text" id="fileUploadName">Clique para selecionar o arquivo textos.json</div>
                <input type="file" id="fileInputJson" accept=".json" onchange="handleFileUpload(event)">
            </div>
        </div>

        <div class="import-section" id="sec-text">
            <textarea id="importTextarea" class="edit-textarea" style="height: 160px;" placeholder='[{"label": "LBL_...", "text": "..."}]'></textarea>
        </div>

        <div style="display:flex; gap:16px; margin-top: 24px;">
            <button class="btn-action" style="flex:1;" onclick="closeModal('importModalOverlay')" data-i18n="btn_cancel">Cancelar</button>
            <button class="btn-action sync-btn" style="flex:1;" onclick="processImport()" id="btnProcessImport">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> <span data-i18n="btn_import_texts">Processar</span>
            </button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
// ==========================================
// MOTOR DE TRADUÇÃO (i18n INDEPENDENTE)
// ==========================================
const dictTexts = {
    'pt': {
        'texts_title': 'Textos do Aplicación', 'texts_desc': 'Gerencie os textos e nomenclaturas exibidos dentro do aplicativo. Todas as edições refletem no JSON automaticamente.',
        'btn_sync': 'Sincronizar', 'btn_import': 'Importar', 'btn_export': 'Exportar', 'btn_reset_all': 'Resetar tudo',
        'search_placeholder': 'Buscar por label ou conteúdo...', 'btn_clear': 'Limpar',
        'mdl_edit_title': 'Editar texto', 'lbl_raw_text': 'Texto Bruto', 'preview': 'Pré-visualização', 'btn_cancel': 'Cancelar', 'btn_save_changes': 'Guardar alterações',
        'mdl_export_title': 'Exportar textos', 'mdl_export_desc': 'Exporte os textos atuais em JSON ou baixe o arquivo físico para backup de segurança.', 'btn_copy_json': 'Copiar JSON', 'btn_download': 'Descargar arquivo',
        'mdl_import_title': 'Importar textos', 'btn_import_texts': 'Processar',
        'toast_copied': 'Link copiado!', 'toast_synced': 'Sincronizado.', 'toast_saved': 'Guardado con éxito!',
        'confirm_reset_title': 'Restaurar texto', 'confirm_reset_desc': 'O texto <b>{label}</b> será restaurado para o padrão.', 'btn_restore': 'Sim, restaurar',
        'confirm_reset_all_title': 'Resetar todos os textos?', 'confirm_reset_all_desc': 'Esta ação exige confirmação explícita e será executada imediatamente.', 'btn_reset_all_confirm': 'Sim, resetar todos'
    },
    'en': {
        'texts_title': 'App Texts', 'texts_desc': 'Manage the texts and nomenclature displayed inside the application. All edits reflect in the JSON automatically.',
        'btn_sync': 'Sync', 'btn_import': 'Import', 'btn_export': 'Export', 'btn_reset_all': 'Reset all',
        'search_placeholder': 'Search by label or content...', 'btn_clear': 'Clear',
        'mdl_edit_title': 'Edit text', 'lbl_raw_text': 'Raw Text', 'preview': 'Preview', 'btn_cancel': 'Cancel', 'btn_save_changes': 'Save changes',
        'mdl_export_title': 'Export texts', 'mdl_export_desc': 'Export current texts in JSON or download the physical file for security backup.', 'btn_copy_json': 'Copy JSON', 'btn_download': 'Download file',
        'mdl_import_title': 'Import texts', 'btn_import_texts': 'Process',
        'toast_copied': 'Link copied!', 'toast_synced': 'Synced.', 'toast_saved': 'Saved successfully!',
        'confirm_reset_title': 'Restore text', 'confirm_reset_desc': 'The text <b>{label}</b> will be restored to default.', 'btn_restore': 'Yes, restore',
        'confirm_reset_all_title': 'Reset all texts?', 'confirm_reset_all_desc': 'This action requires explicit confirmation and will be executed immediately.', 'btn_reset_all_confirm': 'Yes, reset all'
    },
    'es': {
        'texts_title': 'Textos de la App', 'texts_desc': 'Administre los textos y nomenclaturas dentro de la aplicación. Todas las ediciones se reflejan en el JSON automáticamente.',
        'btn_sync': 'Sincronizar', 'btn_import': 'Importar', 'btn_export': 'Exportar', 'btn_reset_all': 'Restablecer todo',
        'search_placeholder': 'Buscar por label o contenido...', 'btn_clear': 'Limpiar',
        'mdl_edit_title': 'Editar texto', 'lbl_raw_text': 'Texto Bruto', 'preview': 'Vista previa', 'btn_cancel': 'Cancelar', 'btn_save_changes': 'Guardar cambios',
        'mdl_export_title': 'Exportar textos', 'mdl_export_desc': 'Exporte los textos actuales en JSON o descargue el archivo físico para copia de seguridad.', 'btn_copy_json': 'Copiar JSON', 'btn_download': 'Descargar archivo',
        'mdl_import_title': 'Importar textos', 'btn_import_texts': 'Procesar',
        'toast_copied': '¡Enlace copiado!', 'toast_synced': 'Sincronizado.', 'toast_saved': '¡Guardado con éxito!',
        'confirm_reset_title': 'Restaurar texto', 'confirm_reset_desc': 'El texto <b>{label}</b> será restaurado al valor predeterminado.', 'btn_restore': 'Sí, restaurar',
        'confirm_reset_all_title': '¿Restablecer todos los textos?', 'confirm_reset_all_desc': 'Esta acción requiere confirmación explícita y se ejecutará de inmediato.', 'btn_reset_all_confirm': 'Sí, restablecer todo'
    }
};

function getT(key) { const lang = localStorage.getItem('app_language') || 'pt'; return dictTexts[lang] && dictTexts[lang][key] ? dictTexts[lang][key] : (dictTexts['pt'][key] || key); }

function applyI18nTexts() {
    document.querySelectorAll('[data-i18n]').forEach(el => { el.innerHTML = getT(el.getAttribute('data-i18n')); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = getT(el.getAttribute('data-i18n-placeholder')); });
}
const originalSelectLangText = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLangText) originalSelectLangText(langCode); applyI18nTexts(); };

// ==========================================
// TOASTS DE NOTIFICAÇÃO
// ==========================================
function showToast(text, type = 'success') {
    const container = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast \${type}`;
    let iconSvg = '<polyline points="20 6 9 17 4 12"/>';
    if(type === 'error') iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    if(type === 'info') iconSvg = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
    
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${text}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}

function getSpinIcon() { return `<svg class="spin-anim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px; margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>`; }

// ==========================================
// LÓGICA PRINCIPAL (CRUD via AJAX)
// ==========================================
let currentTexts = [];
let currentEditLabel = '';

async function fetchTexts() {
    try {
        let r = await fetch('?action=list_texts', {method: 'POST'});
        let res = await r.json();
        if(res.success) { currentTexts = res.texts; renderTexts(); }
    } catch(e) { console.warn('Error al buscar textos:', e); }
}

function renderTexts() {
    const container = document.getElementById('textsListContainer');
    const term = document.getElementById('searchInput').value.toLowerCase();
    
    let html = '';
    currentTexts.forEach((t, index) => {
        if(term && !t.label.toLowerCase().includes(term) && !t.text.toLowerCase().includes(term)) return;
        
        let safeLabel = t.label.replace(/"/g, '&quot;');
        let safeText = t.text.replace(/"/g, '&quot;');
        
        html += `
            <div class="text-card" data-label="\${safeLabel}">
                <div class="tc-header">
                    <span class="tc-id">#\${index + 1}</span>
                    <span class="text-label" style="margin:0;">\${t.label}</span>
                </div>
                <div class="text-content">\${t.text}</div>
                <div class="text-actions">
                    <button class="text-btn text-btn-edit" onclick="openEditModal('\${safeLabel}')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Editar
                    </button>
                    <button class="text-btn text-btn-reset" onclick="confirmReset('\${safeLabel}')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        Restaurar
                    </button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function filterTexts() { renderTexts(); }

// Botão Limpar com Animação
function clearSearch() { 
    const btn = document.getElementById('btn-clear-main');
    btn.innerHTML = getSpinIcon() + `<span>Limpando...</span>`;
    
    setTimeout(() => {
        document.getElementById('searchInput').value = ''; 
        renderTexts();
        showToast('Pesquisa limpa.', 'info');
        btn.innerHTML = `<span data-i18n="btn_clear">\${getT('btn_clear')}</span>`;
    }, 500);
}

async function sendToApi(action, bodyData) {
    try {
        let res = await fetch(`?action=\${action}`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(bodyData)
        });
        return await res.json();
    } catch(e) { return { success: false, error: e.message }; }
}

// Botão Sincronizar (Modal + Reload)
window.syncAllTexts = function() { 
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({title: 'Sincronizando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false, background: isDark?'#18181b':'#ffffff', customClass:{popup:'custom-modal'}});
    
    setTimeout(() => {
        Swal.close();
        showToast(getT('toast_synced'), 'success'); 
        setTimeout(() => { location.reload(); }, 600);
    }, 1200);
};

// ==========================================
// MODAL DE EDIÇÃO (CORRIGIDO E 100% FUNCIONAL)
// ==========================================
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

window.openEditModal = function(label) {
    currentEditLabel = label;
    document.getElementById('editLabelName').innerText = label;
    
    let obj = currentTexts.find(x => x.label === label);
    document.getElementById('editInput').value = obj ? obj.text : '';
    
    updatePreview();
    openModal('editModalOverlay');
};

function updatePreview() {
    let content = document.getElementById('editInput').value;
    document.getElementById('editPreview').innerHTML = content || '<span style="color:var(--text-muted);font-weight:normal;"><i>(vazio)</i></span>';
}

// LÓGICA DE COR E NEGRITO 100% CORRIGIDA (Substituição Inteligente)
function insertTag(tag, color = null) {
    let textarea = document.getElementById('editInput');
    let val = textarea.value;
    let start = textarea.selectionStart; 
    let end = textarea.selectionEnd;
    
    // Se NADA estiver selecionado (cursor parado ou clique direto)
    if (start === end) {
        if (color) {
            // Se o texto já tiver a tag font color, ele SUBSTITUI a cor existente (Nada de lixo)
            if (/<font color="[^"]*">/i.test(val)) {
                textarea.value = val.replace(/<font color="[^"]*">/gi, `<font color="\${color}">`);
            } else {
                // Se não tiver tag font, ele envolve todo o texto
                textarea.value = `<font color="\${color}">\${val}</font>`;
            }
        } else {
            // Se for Negrito ou Itálico, envolve tudo (Simples)
            textarea.value = `<\${tag}>\${val}</\${tag}>`;
        }
    } else {
        // Se o usuário SELECIONOU uma parte específica do texto
        let selectedText = val.substring(start, end);
        let before = val.substring(0, start); 
        let after = val.substring(end, val.length);
        
        let wrap = color ? `<font color="\${color}">\${selectedText}</font>` : `<\${tag}>\${selectedText}</\${tag}>`;
        textarea.value = before + wrap + after; 
    }
    
    textarea.focus();
    updatePreview();
}

document.getElementById('modernColorPicker').addEventListener('input', function(e) {
    document.getElementById('hexDisplay').innerText = e.target.value;
});

window.applyColorFromPicker = function() {
    let color = document.getElementById('modernColorPicker').value;
    insertTag('font', color);
};

window.saveEditText = async function() {
    let newVal = document.getElementById('editInput').value;
    closeModal('editModalOverlay');
    
    Swal.fire({title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false, background: document.documentElement.classList.contains('dark')?'#18181b':'#ffffff', customClass:{popup:'custom-modal'}});
    
    let res = await sendToApi('save_text', { label: currentEditLabel, text: newVal });
    if(res.success) { Swal.close(); fetchTexts(); showToast(getT('toast_saved'), 'success'); }
    else { Swal.fire('Erro!', res.error, 'error'); }
};

// ==========================================
// IMPORTAR, EXPORTAR E RESETAR
// ==========================================
window.openExportModal = function() {
    document.getElementById('exportTextarea').value = JSON.stringify(currentTexts, null, 4);
    openModal('exportModalOverlay');
};

window.copyExportJson = function() {
    document.getElementById('exportTextarea').select(); document.execCommand('copy');
    showToast(getT('toast_copied'));
};

window.downloadExportJson = function() {
    const dataStr = JSON.stringify(currentTexts, null, 4);
    const blob = new Blob([dataStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    
    let dlAnchorElem = document.createElement('a');
    dlAnchorElem.setAttribute("href", url);
    dlAnchorElem.setAttribute("download", "textos.json");
    document.body.appendChild(dlAnchorElem);
    dlAnchorElem.click();
    document.body.removeChild(dlAnchorElem);
    URL.revokeObjectURL(url);
    showToast('Download iniciado!', 'success');
};

window.switchImportTab = function(tab) {
    document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.import-section').forEach(s => s.classList.remove('active'));
    document.getElementById(`tab-\${tab}`).classList.add('active');
    document.getElementById(`sec-\${tab}`).classList.add('active');
};

window.openImportModal = function() {
    document.getElementById('importTextarea').value = '';
    document.getElementById('fileInputJson').value = '';
    document.getElementById('fileUploadName').innerText = 'Clique para selecionar o arquivo textos.json';
    switchImportTab('file');
    openModal('importModalOverlay');
};

let uploadedJsonData = null;
window.handleFileUpload = function(event) {
    const file = event.target.files[0];
    if(!file) return;
    document.getElementById('fileUploadName').innerText = file.name;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            uploadedJsonData = JSON.parse(e.target.result);
            showToast('Arquivo carregado. Clique em Processar.', 'success');
        } catch(err) {
            uploadedJsonData = null;
            Swal.fire('Erro', 'O arquivo selecionado não é um JSON válido.', 'error');
            document.getElementById('fileUploadName').innerText = 'Error al ler arquivo. Tente novamente.';
        }
    };
    reader.readAsText(file);
};

window.processImport = async function() {
    let dataToImport = null;
    let activeTab = document.querySelector('.method-btn.active').id;
    
    if (activeTab === 'tab-file') {
        if(!uploadedJsonData) return Swal.fire('Aviso', 'Selecione um arquivo primeiro.', 'warning');
        dataToImport = uploadedJsonData;
    } else {
        try { dataToImport = JSON.parse(document.getElementById('importTextarea').value); }
        catch(e) { return Swal.fire('Erro', 'Texto JSON Inválido.', 'error'); }
    }
    
    if(!Array.isArray(dataToImport)) return Swal.fire('Erro', 'O formato do JSON precisa ser uma lista de textos.', 'error');
    
    closeModal('importModalOverlay');
    Swal.fire({title: 'Importando...', didOpen: () => Swal.showLoading(), background: document.documentElement.classList.contains('dark')?'#18181b':'#ffffff', customClass:{popup:'custom-modal'}});
    
    let res = await sendToApi('import_texts', { texts: dataToImport });
    if(res.success) { Swal.close(); fetchTexts(); showToast('Textos importados!', 'success'); }
    else { Swal.fire('Erro', res.error, 'error'); }
};

window.confirmReset = function(label) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></div><h2 style="font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin:0;">\${getT('confirm_reset_title')}</h2></div><p style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">\${getT('confirm_reset_desc').replace('{label}', label)}</p>`,
        customClass: { popup: 'custom-modal', confirmButton: 'btn-action danger', cancelButton: 'btn-action', actions: 'swal2-actions' },
        background: isDark ? '#18181b' : '#ffffff', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: getT('btn_restore'), cancelButtonText: getT('btn_cancel')
    }).then(async (res) => {
        if(res.isConfirmed) {
            await sendToApi('reset_single', { label: label });
            fetchTexts(); showToast('Restaurado para o padrão.');
        }
    });
};

window.resetAllTexts = function() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        html: `<div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></div><h2 style="font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin:0;">\${getT('confirm_reset_all_title')}</h2></div><p style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">\${getT('confirm_reset_all_desc')}</p>`,
        customClass: { popup: 'custom-modal', confirmButton: 'btn-action danger', cancelButton: 'btn-action', actions: 'swal2-actions' },
        background: isDark ? '#18181b' : '#ffffff', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: getT('btn_reset_all_confirm'), cancelButtonText: getT('btn_cancel')
    }).then(async (res) => {
        if(res.isConfirmed) {
            Swal.fire({title:'Resetando...', didOpen:()=>Swal.showLoading(), background: isDark?'#18181b':'#ffffff', customClass:{popup:'custom-modal'}});
            await sendToApi('reset_all', {});
            Swal.close(); fetchTexts(); showToast('Banco de dados resetado!', 'success');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => { fetchTexts(); applyI18nTexts(); });
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }
?>