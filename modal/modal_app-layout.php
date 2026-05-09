<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Modal Avançado do Aplicación (Editor de JSON, Lavancas e Código)
 * @description Modal premium responsivo, edição de código hacker, preview, idiomas e temas.
 * =======================================================================================
 */
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }
?>

<style>
/* ==========================================================================
   FORÇA BRUTA PARA O SWEETALERT (NOTIFICAÇÃO) FICAR NA FRENTE DE TUDO
   ========================================================================== */
body.swal2-shown .swal2-container, .swal2-container { 
    z-index: 999999999 !important; 
}

/* ==========================================================================
   VARIÁVEIS DE TEMA (CLARO E ESCURO) - CONECTADO AO SVG SOL/LUA
   ========================================================================== */
:root {
    --adv-bg: #ffffff;
    --adv-border: #e5e7eb;
    --adv-text: #111827;
    --adv-subtext: #6b7280;
    --adv-inner: #f9fafb;
    --adv-input-bg: #ffffff;
    --adv-input-border: #d1d5db;
    --adv-btn: #ffffff;
    --adv-code-bg: #000000;
    --adv-code-text: #10b981; /* Verde de Programação Hacker */
}

html.dark, body.dark, .dark {
    --adv-bg: #161618;
    --adv-border: #27272a;
    --adv-text: #f9fafb;
    --adv-subtext: #a1a1aa;
    --adv-inner: #1e1e22;
    --adv-input-bg: #1e1e22;
    --adv-input-border: #3f3f46;
    --adv-btn: #1e1e22;
    --adv-code-bg: #050505;
    --adv-code-text: #10b981; /* Verde de Programação Hacker */
}

/* ==========================================================================
   ESTILOS PREMIUM - MODAL AVANÇADO (LÁPIS)
   ========================================================================== */
#advancedEditorOverlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px);
    z-index: 9999999; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
    opacity: 0; visibility: hidden; transition: opacity 0.3s ease; padding: 10px; overflow: hidden;
}
#advancedEditorOverlay.show { opacity: 1; visibility: visible; }

.adv-container {
    width: 100%; max-width: 650px; background: var(--adv-bg); border: 1px solid var(--adv-border); border-radius: 24px;
    display: flex; flex-direction: column; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 -20px 50px rgba(0,0,0,0.6); margin-top: auto; 
    max-height: 92vh; 
    overflow: hidden; font-family: 'Manrope', sans-serif;
}
#advancedEditorOverlay.show .adv-container { transform: translateY(0); }

/* HEADER DO MODAL */
.adv-header {
    display: flex; justify-content: space-between; align-items: center; padding: 24px;
    border-bottom: 1px solid var(--adv-border); flex-shrink: 0; background: var(--adv-bg); border-radius: 24px 24px 0 0;
}
.adv-header-info h2 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--adv-text); display: flex; align-items: center; gap: 8px;}
.adv-header-info p { margin: 4px 0 0 0; font-size: 0.85rem; color: var(--adv-subtext); font-weight: 500; }
.adv-close-btn {
    width: 40px; height: 40px; border-radius: 12px; background: transparent; border: 1px solid transparent;
    color: var(--adv-subtext); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; outline: none;
}
.adv-close-btn:active { background: var(--adv-inner); transform: scale(0.9); border-color: var(--adv-input-border); color: var(--adv-text);}

/* CORPO DO MODAL (ROLAGEM SUAVE) */
.adv-body {
    flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 24px;
    scrollbar-width: thin; scrollbar-color: var(--adv-input-border) transparent; background: var(--adv-inner);
}
.adv-body::-webkit-scrollbar { width: 6px; }
.adv-body::-webkit-scrollbar-thumb { background-color: var(--adv-input-border); border-radius: 10px; }

/* SEÇÕES E CARDS */
.adv-section-title { font-size: 0.95rem; font-weight: 800; color: var(--adv-text); margin: 0 0 4px 0; }
.adv-section-desc { font-size: 0.8rem; color: var(--adv-subtext); margin: 0 0 16px 0; font-weight: 500; line-height: 1.4;}

.adv-card { background: var(--adv-bg); border: 1px solid var(--adv-border); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 16px; }

/* LAVANCAS (TOGGLES ESTILO VÍDEO) */
.adv-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.adv-row span { font-size: 0.9rem; font-weight: 700; color: var(--adv-text); }

.adv-switch { position: relative; display: inline-block; width: 54px; height: 28px; flex-shrink: 0; }
.adv-switch input { opacity: 0; width: 0; height: 0; }
.adv-slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--adv-border); transition: .3s; border-radius: 30px; border: 1px solid var(--adv-input-border);
}
.adv-slider:before {
    position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px;
    background-color: var(--adv-subtext); transition: .3s; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.power-icon {
    position: absolute; width: 10px; height: 10px; top: 50%; left: 50%; transform: translate(-50%, -50%);
    color: var(--adv-bg); opacity: 1; transition: 0.3s; z-index: 2; pointer-events: none;
}
.adv-switch input:checked + .adv-slider { background-color: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); }
.adv-switch input:checked + .adv-slider:before { transform: translateX(26px); background-color: #10b981; }
.adv-switch input:checked + .adv-slider .power-icon { color: #fff; }

/* CAMPOS DE TEXTO E CORES */
.adv-input-group { display: flex; flex-direction: column; gap: 8px; }
.adv-input-group label { font-size: 0.8rem; font-weight: 800; color: var(--adv-text); }
.adv-input {
    width: 100%; background: var(--adv-input-bg); border: 1px solid var(--adv-input-border); border-radius: 12px;
    padding: 14px 16px; color: var(--adv-text); font-size: 0.9rem; font-weight: 600; outline: none; font-family: 'Space Grotesk', 'Manrope', sans-serif;
    transition: 0.2s;
}
.adv-input:focus { border-color: var(--primary, #3b82f6); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1); }

/* INPUT DE COR ESTILO QUADRICULADO */
.adv-color-wrap { position: relative; display: flex; align-items: center; }
.adv-color-wrap .adv-input { padding-right: 50px; }
.adv-color-box {
    position: absolute; right: 10px; width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--adv-input-border);
    background-image: linear-gradient(45deg, #a1a1aa 25%, transparent 25%), linear-gradient(-45deg, #a1a1aa 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #a1a1aa 75%), linear-gradient(-45deg, transparent 75%, #a1a1aa 75%);
    background-size: 10px 10px; background-position: 0 0, 0 5px, 5px -5px, -5px 0px; cursor: pointer; overflow: hidden;
}
.dark .adv-color-box {
    background-image: linear-gradient(45deg, #27272a 25%, transparent 25%), linear-gradient(-45deg, #27272a 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #27272a 75%), linear-gradient(-45deg, transparent 75%, #27272a 75%);
}
.adv-color-inner { width: 100%; height: 100%; pointer-events: none; }
.adv-color-picker-native { position: absolute; opacity: 0; width: 200%; height: 200%; top: -10px; left: -10px; cursor: pointer; }

/* ÁREA DE CÓDIGO (HTML WEBVIEW) - TEMA HACKER VERDE */
.adv-code-container { background: var(--adv-bg); border: 1px solid var(--adv-border); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; }
.adv-code-tabs { display: flex; background: var(--adv-inner); border-bottom: 1px solid var(--adv-border); }
.adv-code-tab {
    flex: 1; text-align: center; padding: 14px; font-size: 0.85rem; font-weight: 800; color: var(--adv-subtext);
    cursor: pointer; transition: 0.2s; border-bottom: 2px solid transparent;
}
.adv-code-tab.active { color: var(--adv-text); border-bottom-color: #3b82f6; background: rgba(59, 130, 246, 0.05); }

.adv-code-area { 
    width: 100%; min-height: 250px; background: var(--adv-code-bg); border: none; padding: 16px; 
    color: var(--adv-code-text); /* VERDE DE PROGRAMADOR AQUI! */
    font-family: 'Space Grotesk', 'Courier New', monospace; font-size: 0.85rem; line-height: 1.5; outline: none; resize: vertical; 
}
.adv-code-preview { width: 100%; min-height: 250px; background: #fff; display: none; padding: 0; border: none; }
.adv-code-preview iframe { width: 100%; height: 100%; min-height: 250px; border: none; }

.adv-code-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 16px; background: var(--adv-bg); border-top: 1px solid var(--adv-border); }
.adv-btn-code {
    background: var(--adv-btn); border: 1px solid var(--adv-input-border); color: var(--adv-text); border-radius: 12px; padding: 12px;
    font-size: 0.8rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: 0.15s; outline: none;
}
.adv-btn-code:active { transform: scale(0.95); background: var(--adv-inner); }
.adv-btn-code svg { width: 16px; color: var(--adv-subtext); }

/* FOOTER DO MODAL */
.adv-footer {
    display: flex; gap: 12px; padding: 24px; background: var(--adv-bg); border-top: 1px solid var(--adv-border); border-radius: 0 0 24px 24px; flex-shrink: 0;
}
.adv-btn-cancel { flex: 1; background: transparent; border: 1px solid var(--adv-input-border); color: var(--adv-text); padding: 16px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.15s; outline: none;}
.adv-btn-save { flex: 1; background: #64748b; color: #fff; border: none; padding: 16px; border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.15s; outline: none;}
.adv-btn-cancel:active, .adv-btn-save:active { transform: scale(0.95); }
</style>

<div id="advancedEditorOverlay" onclick="AppLayoutModalManager.close(event)">
    <div class="adv-container" onclick="event.stopPropagation()">
        
        <div class="adv-header">
            <div class="adv-header-info">
                <h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px;"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> <span data-i18n-adv="adv_title">Personalizar aplicativo</span></h2>
                <p data-i18n-adv="adv_subtitle">Ajuste a aparência do aplicativo com preview em tempo real.</p>
            </div>
            <button type="button" class="adv-close-btn" onclick="AppLayoutModalManager.close(event, true)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="adv-body">
            
            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_home">Acciones da tela inicial</h3>
                <p class="adv-section-desc" data-i18n-adv="sec_home_desc">Controla os atalhos e botoes principais exibidos no aplicativo.</p>
                <div class="adv-card">
                    <div class="adv-row">
                        <span data-i18n-adv="btn_upd">Usar botão de atualizações</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_BTN_UPDATE_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_BTN_UPDATE_ENABLED', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="btn_web">Usar botão de pagina webview</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_BTN_PAGE_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_BTN_PAGE_ENABLED', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="btn_log">Usar botão de registros</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_BTN_LOGGER_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_BTN_LOGGER_ENABLED', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="btn_last">Usar atualização de visto por ultimo</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_UPDATE_LAST_SEEN_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_UPDATE_LAST_SEEN_ENABLED', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="btn_menu">Usar botão de menu</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_BTN_MENU_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_BTN_MENU_ENABLED', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title">ANDROID WEBVIEW (POR SUA CUENTA E RISCO)</h3>
                <div class="adv-code-container" id="container-code-APP_WEB_VIEW">
                    <div class="adv-code-tabs">
                        <div class="adv-code-tab active" data-i18n-adv="tab_cod" onclick="AppLayoutModalManager.switchTab('APP_WEB_VIEW', 'code')">Código</div>
                        <div class="adv-code-tab" data-i18n-adv="tab_prev" onclick="AppLayoutModalManager.switchTab('APP_WEB_VIEW', 'preview')">Preview</div>
                    </div>
                    <textarea id="code-area-APP_WEB_VIEW" class="adv-code-area" placeholder="Ex: <!DOCTYPE html>..."></textarea>
                    <div id="preview-area-APP_WEB_VIEW" class="adv-code-preview"></div>
                    <div class="adv-code-actions">
                        <button type="button" class="adv-btn-code" onclick="document.getElementById('code-area-APP_WEB_VIEW').value=''"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> <span data-i18n-adv="btn_limpar">Limpar</span></button>
                        <button type="button" class="adv-btn-code" onclick="AppLayoutModalManager.switchTab('APP_WEB_VIEW', 'preview')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <span data-i18n-adv="btn_visu">Visualizar</span></button>
                        <button type="button" class="adv-btn-code" onclick="AppLayoutModalManager.switchTab('APP_WEB_VIEW', 'code')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> <span data-i18n-adv="btn_edit">Editor</span></button>
                        <button type="button" class="adv-btn-code" onclick="document.getElementById('file-import-APP_WEB_VIEW').click()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <span data-i18n-adv="btn_imp">Importar</span></button>
                        <input type="file" id="file-import-APP_WEB_VIEW" style="display:none;" accept=".html,.txt,.js" onchange="AppLayoutModalManager.importFile(event, 'code-area-APP_WEB_VIEW')">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_auto">Automação</h3>
                <p class="adv-section-desc" data-i18n-adv="sec_auto_desc">Regras automaticas e ajustes de comportamento do app.</p>
                <div class="adv-card">
                    <div class="adv-row">
                        <span data-i18n-adv="aviao_auto">Activar modo avião automático</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_AIRPLANE_MODE" onchange="AppLayoutModalManager.updateToggle('APP_AIRPLANE_MODE', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                    <div class="adv-input-group">
                        <label data-i18n-adv="aviao_time">Timeout do modo avião automático (em segundos)</label>
                        <input type="number" id="adv-APP_AIRPLANE_MODE_TIMEOUT" class="adv-input" oninput="AppLayoutModalManager.updateText('APP_AIRPLANE_MODE_TIMEOUT', this.value, 'INTEGER')">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_outros">Outros recursos</h3>
                <p class="adv-section-desc" data-i18n-adv="sec_outros_desc">Campos restantes ligados ao funcionamento do aplicativo.</p>
                <div class="adv-card">
                    <div class="adv-row">
                        <span data-i18n-adv="alerta_som">Activar alertas sonoros</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_ALERT_SOUND_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_ALERT_SOUND_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="dial_check">Activar dialog de checkuser</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_CHECKUSER_DIALOG_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_CHECKUSER_DIALOG_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="dial_erro">Activar dialog de erros</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_DIALOG_ERROR_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_DIALOG_ERROR_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="ip_local">Activar IP local no layout nativo</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_LOCAL_IP_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_LOCAL_IP_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="serv_ping">Activar serviço de ping</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_PING_SERVICE_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_PING_SERVICE_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="toast_err">Activar toast de erro</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_ERROR_TOAST_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_ERROR_TOAST_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="toast_suc">Activar toast de sucesso</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_SUCCESS_TOAST_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_SUCCESS_TOAST_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="mod_conex">Exibir modo de conexão</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_SHOW_CONNECTION_MODE" onchange="AppLayoutModalManager.updateToggle('APP_SHOW_CONNECTION_MODE', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="filt_rede">Filtrar configurações pelo nome da rede</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_CONFIG_FILTER_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_CONFIG_FILTER_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="most_cdn">Mostrar quantidade de CDNs</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_CDN_COUNT_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_CDN_COUNT_ENABLED', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="perm_loc">Pedir permissão de localização do usuário</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_CONFIG_LOCATION_PERMISSION" onchange="AppLayoutModalManager.updateToggle('APP_CONFIG_LOCATION_PERMISSION', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                    <div class="adv-row">
                        <span data-i18n-adv="limit_con">Usar limiter de conexão</span>
                        <label class="adv-switch"><input type="checkbox" id="adv-APP_CONNECTION_LIMITER" onchange="AppLayoutModalManager.updateToggle('APP_CONNECTION_LIMITER', this.checked)"><span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span></label>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_dialogs">Dialogs do app</h3>
                <p class="adv-section-desc" data-i18n-adv="sec_dialogs_desc">Colores dos dialogs padrao, config e log do aplicativo.</p>
                <div class="adv-card">
                    <div class="adv-input-group">
                        <label data-i18n-adv="cor_fundo_dial">Color do fundo dos dialogs</label>
                        <div class="adv-color-wrap">
                            <input type="text" id="txt-APP_DIALOG_BACKGROUND_COLOR" class="adv-input" oninput="AppLayoutModalManager.syncColorBox('APP_DIALOG_BACKGROUND_COLOR')">
                            <div class="adv-color-box"><div class="adv-color-inner" id="box-APP_DIALOG_BACKGROUND_COLOR"></div><input type="color" id="clr-APP_DIALOG_BACKGROUND_COLOR" class="adv-color-picker-native" oninput="AppLayoutModalManager.syncColorText('APP_DIALOG_BACKGROUND_COLOR')"></div>
                        </div>
                    </div>
                    <div class="adv-input-group">
                        <label data-i18n-adv="cor_card_conf">Color do card de configurações</label>
                        <div class="adv-color-wrap">
                            <input type="text" id="txt-APP_CARD_CONFIG_COLOR" class="adv-input" oninput="AppLayoutModalManager.syncColorBox('APP_CARD_CONFIG_COLOR')">
                            <div class="adv-color-box"><div class="adv-color-inner" id="box-APP_CARD_CONFIG_COLOR"></div><input type="color" id="clr-APP_CARD_CONFIG_COLOR" class="adv-color-picker-native" oninput="AppLayoutModalManager.syncColorText('APP_CARD_CONFIG_COLOR')"></div>
                        </div>
                    </div>
                    <div class="adv-input-group">
                        <label data-i18n-adv="cor_fundo_log">Color do fundo do dialog de log</label>
                        <div class="adv-color-wrap">
                            <input type="text" id="txt-APP_DIALOG_LOGGER_COLOR" class="adv-input" oninput="AppLayoutModalManager.syncColorBox('APP_DIALOG_LOGGER_COLOR')">
                            <div class="adv-color-box"><div class="adv-color-inner" id="box-APP_DIALOG_LOGGER_COLOR"></div><input type="color" id="clr-APP_DIALOG_LOGGER_COLOR" class="adv-color-picker-native" oninput="AppLayoutModalManager.syncColorText('APP_DIALOG_LOGGER_COLOR')"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_pub">Publicacao do app</h3>
                <p class="adv-section-desc" data-i18n-adv="sec_pub_desc">Versionamento e link de distribuicao do aplicativo.</p>
                <div class="adv-card">
                    <div class="adv-input-group">
                        <label data-i18n-adv="link_down">Link para download do aplicativo</label>
                        <input type="text" id="adv-APP_DOWNLOAD_LINK" class="adv-input" placeholder="https://exemplo.com/app.apk" oninput="AppLayoutModalManager.updateText('APP_DOWNLOAD_LINK', this.value, 'TEXT')">
                    </div>
                    <div class="adv-input-group">
                        <label data-i18n-adv="ver_app">Versión do aplicativo</label>
                        <input type="text" id="adv-APP_VERSION" class="adv-input" placeholder="4.5.0" oninput="AppLayoutModalManager.updateText('APP_VERSION', this.value, 'TEXT')">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_demais">Demais campos</h3>
                <div class="adv-code-container" id="container-code-APP_SUPPORT_BUTTON">
                    <div class="adv-input-group" style="padding: 16px 16px 0 16px;">
                        <label data-i18n-adv="pag_suporte">PÁGINA DE SOPORTE (BOTÓN HTML)</label>
                    </div>
                    <div class="adv-code-tabs" style="margin-top:10px;">
                        <div class="adv-code-tab active" data-i18n-adv="tab_cod" onclick="AppLayoutModalManager.switchTab('APP_SUPPORT_BUTTON', 'code')">Código</div>
                        <div class="adv-code-tab" data-i18n-adv="tab_prev" onclick="AppLayoutModalManager.switchTab('APP_SUPPORT_BUTTON', 'preview')">Preview</div>
                    </div>
                    <textarea id="code-area-APP_SUPPORT_BUTTON" class="adv-code-area" placeholder="Ex: <a href='...'>Soporte</a>"></textarea>
                    <div id="preview-area-APP_SUPPORT_BUTTON" class="adv-code-preview"></div>
                    <div class="adv-code-actions">
                        <button type="button" class="adv-btn-code" onclick="document.getElementById('code-area-APP_SUPPORT_BUTTON').value=''"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> <span data-i18n-adv="btn_limpar">Limpar</span></button>
                        <button type="button" class="adv-btn-code" onclick="AppLayoutModalManager.switchTab('APP_SUPPORT_BUTTON', 'preview')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <span data-i18n-adv="btn_visu">Visualizar</span></button>
                        <button type="button" class="adv-btn-code" onclick="AppLayoutModalManager.switchTab('APP_SUPPORT_BUTTON', 'code')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> <span data-i18n-adv="btn_edit">Editor</span></button>
                        <button type="button" class="adv-btn-code" onclick="document.getElementById('file-import-APP_SUPPORT_BUTTON').click()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <span data-i18n-adv="btn_imp">Importar</span></button>
                        <input type="file" id="file-import-APP_SUPPORT_BUTTON" style="display:none;" accept=".html,.txt,.js" onchange="AppLayoutModalManager.importFile(event, 'code-area-APP_SUPPORT_BUTTON')">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="adv-section-title" data-i18n-adv="sec_modo">Modo de layout</h3>
                <p class="adv-section-desc" data-i18n-adv="sec_modo_desc">Campos que definem como o app renderiza a interface.</p>
                <div class="adv-card">
                    <div class="adv-row">
                        <span data-i18n-adv="usar_lay_web">Usar layout webview</span>
                        <label class="adv-switch">
                            <input type="checkbox" id="adv-APP_LAYOUT_WEBVIEW_ENABLED" onchange="AppLayoutModalManager.updateToggle('APP_LAYOUT_WEBVIEW_ENABLED', this.checked)">
                            <span class="adv-slider"><svg class="power-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></span>
                        </label>
                    </div>
                </div>

                <div class="adv-code-container" style="margin-top: 16px;" id="container-code-APP_LAYOUT_WEBVIEW">
                    <div class="adv-input-group" style="padding: 16px 16px 0 16px;">
                        <label data-i18n-adv="lay_web">LAYOUT WEBVIEW (POR SUA CUENTA E RISCO)</label>
                        <span class="sub-label" style="font-size:0.75rem; color:var(--adv-subtext); margin-top:2px; display:block;">Atenção: O código deve conter as identificações de segurança do Panel WEB2.</span>
                    </div>
                    <div class="adv-code-tabs" style="margin-top:10px;">
                        <div class="adv-code-tab active" data-i18n-adv="tab_cod" onclick="AppLayoutModalManager.switchTab('APP_LAYOUT_WEBVIEW', 'code')">Código</div>
                        <div class="adv-code-tab" data-i18n-adv="tab_prev" onclick="AppLayoutModalManager.switchTab('APP_LAYOUT_WEBVIEW', 'preview')">Preview</div>
                    </div>
                    <textarea id="code-area-APP_LAYOUT_WEBVIEW" class="adv-code-area" placeholder="Ex: <!DOCTYPE html>..."></textarea>
                    <div id="preview-area-APP_LAYOUT_WEBVIEW" class="adv-code-preview"></div>
                    <div class="adv-code-actions">
                        <button type="button" class="adv-btn-code" onclick="document.getElementById('code-area-APP_LAYOUT_WEBVIEW').value=''"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> <span data-i18n-adv="btn_limpar">Limpar</span></button>
                        <button type="button" class="adv-btn-code" onclick="AppLayoutModalManager.switchTab('APP_LAYOUT_WEBVIEW', 'preview')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <span data-i18n-adv="btn_visu">Visualizar</span></button>
                        <button type="button" class="adv-btn-code" onclick="AppLayoutModalManager.switchTab('APP_LAYOUT_WEBVIEW', 'code')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> <span data-i18n-adv="btn_edit">Editor</span></button>
                        <button type="button" class="adv-btn-code" onclick="document.getElementById('file-import-APP_LAYOUT_WEBVIEW').click()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <span data-i18n-adv="btn_imp">Importar</span></button>
                        <input type="file" id="file-import-APP_LAYOUT_WEBVIEW" style="display:none;" accept=".html,.txt,.js" onchange="AppLayoutModalManager.importFile(event, 'code-area-APP_LAYOUT_WEBVIEW')">
                    </div>
                </div>
            </div>

        </div>

        <div class="adv-footer">
            <button type="button" class="adv-btn-cancel" onclick="AppLayoutModalManager.close(event, true)" data-i18n-adv="btn_cancel">Cancelar</button>
            <button type="button" class="adv-btn-save" onclick="AppLayoutModalManager.save()" data-i18n-adv="btn_salvar">Guardar alterações</button>
        </div>

    </div>
</div>

<script>
/**
 * =======================================================================================
 * DICIONÁRIO INTERNO E PROTEGIDO DO MODAL
 * =======================================================================================
 */
var advDict = {
    'pt': {
        'adv_title': 'Personalizar aplicativo', 'adv_subtitle': 'Ajuste regras, automações e layout webview.',
        'sec_home': 'Acciones da tela inicial', 'sec_home_desc': 'Controla os atalhos e botoes principais exibidos no aplicativo.',
        'btn_upd': 'Usar botão de atualizações', 'btn_web': 'Usar botão de pagina webview', 'btn_log': 'Usar botão de registros',
        'btn_last': 'Usar atualização de visto por ultimo', 'btn_menu': 'Usar botão de menu',
        'sec_auto': 'Automação', 'sec_auto_desc': 'Regras automaticas e ajustes de comportamento do app.',
        'aviao_auto': 'Activar modo avião automático', 'aviao_time': 'Timeout do modo avião (em segundos)',
        'sec_outros': 'Outros recursos', 'sec_outros_desc': 'Campos restantes ligados ao funcionamento do aplicativo.',
        'alerta_som': 'Activar alertas sonoros', 'dial_check': 'Activar dialog de checkuser', 'dial_erro': 'Activar dialog de erros',
        'ip_local': 'Activar IP local no layout nativo', 'serv_ping': 'Activar serviço de ping', 'toast_err': 'Activar toast de erro',
        'toast_suc': 'Activar toast de sucesso', 'mod_conex': 'Exibir modo de conexão', 'filt_rede': 'Filtrar configurações pelo nome da rede',
        'most_cdn': 'Mostrar quantidade de CDNs', 'perm_loc': 'Pedir permissão de localização do usuário', 'limit_con': 'Usar limiter de conexão',
        'sec_dialogs': 'Dialogs do app', 'sec_dialogs_desc': 'Colores dos dialogs padrao, config e log do aplicativo.',
        'cor_fundo_dial': 'Color do fundo dos dialogs', 'cor_card_conf': 'Color do card de configurações', 'cor_fundo_log': 'Color do fundo do dialog de log',
        'sec_pub': 'Publicacao do app', 'sec_pub_desc': 'Versionamento e link de distribuicao do aplicativo.',
        'link_down': 'Link para download do aplicativo', 'ver_app': 'Versión do aplicativo',
        'sec_demais': 'Otros campos', 'pag_web': 'PÁGINA WEBVIEW (BAJO TU RIESGO)', 'pag_suporte': 'PÁGINA DE SOPORTE (BOTÓN HTML)',
        'sec_modo': 'Modo de layout', 'sec_modo_desc': 'Campos que definem como o app renderiza a interface.',
        'usar_lay_web': 'Usar layout webview', 'lay_web': 'LAYOUT WEBVIEW (POR SUA CUENTA E RISCO)',
        'tab_cod': 'Código', 'tab_prev': 'Preview', 'btn_limpar': 'Limpar', 'btn_visu': 'Visualizar', 'btn_edit': 'Editor', 'btn_imp': 'Importar',
        'btn_cancel': 'Cancelar', 'btn_salvar': 'Guardar alterações'
    },
    'en': {
        'adv_title': 'Customize application', 'adv_subtitle': 'Adjust rules, automations, and webview layout.',
        'sec_home': 'Home Screen Actions', 'sec_home_desc': 'Controls the main shortcuts and buttons displayed in the application.',
        'btn_upd': 'Use updates button', 'btn_web': 'Use webview page button', 'btn_log': 'Use logs button',
        'btn_last': 'Use last seen update', 'btn_menu': 'Use menu button',
        'sec_auto': 'Automation', 'sec_auto_desc': 'Automatic rules and app behavior adjustments.',
        'aviao_auto': 'Enable automatic airplane mode', 'aviao_time': 'Automatic airplane mode timeout (in seconds)',
        'sec_outros': 'Other features', 'sec_outros_desc': 'Remaining fields linked to the operation of the application.',
        'alerta_som': 'Enable sound alerts', 'dial_check': 'Enable checkuser dialog', 'dial_erro': 'Enable error dialog',
        'ip_local': 'Enable local IP in native layout', 'serv_ping': 'Enable ping service', 'toast_err': 'Enable error toast',
        'toast_suc': 'Enable success toast', 'mod_conex': 'Show connection mode', 'filt_rede': 'Filter configurations by network name',
        'most_cdn': 'Show CDN count', 'perm_loc': 'Request user location permission', 'limit_con': 'Use connection limiter',
        'sec_dialogs': 'App Dialogs', 'sec_dialogs_desc': 'Colors of standard, config, and log dialogs of the application.',
        'cor_fundo_dial': 'Dialogs background color', 'cor_card_conf': 'Configuration card color', 'cor_fundo_log': 'Log dialog background color',
        'sec_pub': 'App Publishing', 'sec_pub_desc': 'Versioning and distribution link of the application.',
        'link_down': 'Application download link', 'ver_app': 'Application version',
        'sec_demais': 'Other fields', 'pag_web': 'WEBVIEW PAGE (AT YOUR OWN RISK)', 'pag_suporte': 'SUPPORT PAGE (HTML BUTTON)',
        'sec_modo': 'Layout mode', 'sec_modo_desc': 'Fields that define how the app renders the interface.',
        'usar_lay_web': 'Use webview layout', 'lay_web': 'WEBVIEW LAYOUT (AT YOUR OWN RISK)',
        'tab_cod': 'Code', 'tab_prev': 'Preview', 'btn_limpar': 'Clear', 'btn_visu': 'View', 'btn_edit': 'Editor', 'btn_imp': 'Import',
        'btn_cancel': 'Cancel', 'btn_salvar': 'Save changes'
    },
    'es': {
        'adv_title': 'Personalizar aplicación', 'adv_subtitle': 'Ajuste reglas, automatizaciones y diseño webview.',
        'sec_home': 'Acciones de inicio', 'sec_home_desc': 'Controla los atajos y botones principales en la aplicación.',
        'btn_upd': 'Usar botón de actualizaciones', 'btn_web': 'Usar botón de página webview', 'btn_log': 'Usar botón de registros',
        'btn_last': 'Usar actualización de visto por último', 'btn_menu': 'Usar botón de menú',
        'sec_auto': 'Automatización', 'sec_auto_desc': 'Reglas automáticas y ajustes de comportamiento de la app.',
        'aviao_auto': 'Activar modo avión automático', 'aviao_time': 'Tiempo de espera modo avión (en segundos)',
        'sec_outros': 'Otros recursos', 'sec_outros_desc': 'Campos restantes vinculados al funcionamiento.',
        'alerta_som': 'Activar alertas sonoras', 'dial_check': 'Activar diálogo de checkuser', 'dial_erro': 'Activar diálogo de errores',
        'ip_local': 'Activar IP local en diseño nativo', 'serv_ping': 'Activar servicio de ping', 'toast_err': 'Activar toast de error',
        'toast_suc': 'Activar toast de éxito', 'mod_conex': 'Mostrar modo de conexión', 'filt_rede': 'Filtrar configuraciones por nombre de red',
        'most_cdn': 'Mostrar cantidad de CDNs', 'perm_loc': 'Solicitar permiso de ubicación', 'limit_con': 'Usar limitador de conexión',
        'sec_dialogs': 'Diálogos de la app', 'sec_dialogs_desc': 'Colores de los diálogos estándar, config y log.',
        'cor_fundo_dial': 'Color de fondo de los diálogos', 'cor_card_conf': 'Color del card de configuraciones', 'cor_fundo_log': 'Color de fondo del log',
        'sec_pub': 'Publicación de la app', 'sec_pub_desc': 'Versionado y enlace de distribución.',
        'link_down': 'Enlace de descarga de la aplicación', 'ver_app': 'Versión de la aplicación',
        'sec_demais': 'Otros campos', 'pag_web': 'PÁGINA WEBVIEW (BAJO SU RIESGO)', 'pag_suporte': 'PÁGINA DE SOPORTE (BOTÓN HTML)',
        'sec_modo': 'Modo de diseño', 'sec_modo_desc': 'Campos que definen cómo la app renderiza la interfaz.',
        'usar_lay_web': 'Usar diseño webview', 'lay_web': 'DISEÑO WEBVIEW (BAJO SU RIESGO)',
        'tab_cod': 'Código', 'tab_prev': 'Vista previa', 'btn_limpar': 'Limpiar', 'btn_visu': 'Visualizar', 'btn_edit': 'Editor', 'btn_imp': 'Importar',
        'btn_cancel': 'Cancelar', 'btn_salvar': 'Guardar cambios'
    }
};

function applyAdvI18n() {
    var lang = localStorage.getItem('app_language') || 'pt';
    var curDict = advDict[lang] || advDict['pt'];
    var els = document.querySelectorAll('[data-i18n-adv]');
    for(var i=0; i<els.length; i++) {
        var el = els[i];
        var k = el.getAttribute('data-i18n-adv');
        if(curDict[k]) el.innerHTML = curDict[k];
    }
}

// ESPIÃO INVISÍVEL - Garante que se você mudar o idioma na página principal, o modal muda instantaneamente
document.addEventListener('click', function(e) {
    if(e.target.closest('.lang-option')) {
        setTimeout(applyAdvI18n, 100);
    }
});

/**
 * =======================================================================================
 * CÉREBRO DO MODAL AVANÇADO (LÁPIS) - LÓGICA DE FERRO E COMUNICAÇÃO PERFEITA
 * =======================================================================================
 */
var AppLayoutModalManager = (function() {
    var currentLayoutObj = null;
    var localData = [];

    function open(layoutObj) {
        currentLayoutObj = layoutObj;
        localData = JSON.parse(JSON.stringify(layoutObj.layout_data));
        applyAdvI18n(); 
        syncToUI();
        document.getElementById('advancedEditorOverlay').classList.add('show');
    }

    function close(e, force) {
        if(e && e.target.id !== 'advancedEditorOverlay' && !force) return;
        document.getElementById('advancedEditorOverlay').classList.remove('show');
        var previews = document.querySelectorAll('.adv-code-preview');
        for(var i=0; i<previews.length; i++) previews[i].innerHTML = '';
    }

    function getVal(key, def) {
        for(var i=0; i<localData.length; i++) {
            if(localData[i].name === key) return localData[i].value;
        }
        return def;
    }

    function updateProp(key, value, type) {
        var found = false;
        for(var i=0; i<localData.length; i++) {
            if(localData[i].name === key) {
                localData[i].value = value;
                found = true;
                break;
            }
        }
        if(!found) {
            localData.push({name: key, value: value, type: type});
        }
    }

    function updateToggle(key, isChecked) { updateProp(key, isChecked, 'BOOLEAN'); }
    function updateText(key, text, type) { updateProp(key, type === 'INTEGER' ? parseInt(text||0) : text, type); }

    function syncToUI() {
        var toggles = [
            'APP_BTN_UPDATE_ENABLED', 'APP_BTN_PAGE_ENABLED', 'APP_BTN_LOGGER_ENABLED', 
            'APP_UPDATE_LAST_SEEN_ENABLED', 'APP_BTN_MENU_ENABLED', 'APP_AIRPLANE_MODE',
            'APP_ALERT_SOUND_ENABLED', 'APP_CHECKUSER_DIALOG_ENABLED', 'APP_DIALOG_ERROR_ENABLED',
            'APP_LOCAL_IP_ENABLED', 'APP_PING_SERVICE_ENABLED', 'APP_ERROR_TOAST_ENABLED',
            'APP_SUCCESS_TOAST_ENABLED', 'APP_SHOW_CONNECTION_MODE', 'APP_CONFIG_FILTER_ENABLED',
            'APP_CDN_COUNT_ENABLED', 'APP_CONFIG_LOCATION_PERMISSION', 'APP_CONNECTION_LIMITER',
            'APP_LAYOUT_WEBVIEW_ENABLED'
        ];
        
        for(var i=0; i<toggles.length; i++) {
            var t = toggles[i];
            var el = document.getElementById('adv-' + t);
            if(el) {
                var val = getVal(t, true);
                el.checked = (val === true || String(val) === 'true' || val === 1);
            }
        }

        document.getElementById('adv-APP_AIRPLANE_MODE_TIMEOUT').value = getVal('APP_AIRPLANE_MODE_TIMEOUT', 1);
        document.getElementById('adv-APP_DOWNLOAD_LINK').value = getVal('APP_DOWNLOAD_LINK', '');
        document.getElementById('adv-APP_VERSION').value = getVal('APP_VERSION', '');

        var colors = ['APP_DIALOG_BACKGROUND_COLOR', 'APP_CARD_CONFIG_COLOR', 'APP_DIALOG_LOGGER_COLOR'];
        for(var j=0; j<colors.length; j++) {
            var c = colors[j];
            var hex = getVal(c, '#00000000');
            document.getElementById('txt-' + c).value = hex.toUpperCase();
            document.getElementById('clr-' + c).value = hex.substring(0,7);
            document.getElementById('box-' + c).style.backgroundColor = hex;
        }

        document.getElementById('code-area-APP_WEB_VIEW').value = getVal('APP_WEB_VIEW', '');
        document.getElementById('code-area-APP_LAYOUT_WEBVIEW').value = getVal('APP_LAYOUT_WEBVIEW', '');
        document.getElementById('code-area-APP_SUPPORT_BUTTON').value = getVal('APP_SUPPORT_BUTTON', '');

        switchTab('APP_WEB_VIEW', 'code');
        switchTab('APP_LAYOUT_WEBVIEW', 'code');
        switchTab('APP_SUPPORT_BUTTON', 'code');
    }

    function syncColorText(key) {
        var hexVal = document.getElementById('clr-' + key).value.toUpperCase() + 'FF';
        document.getElementById('txt-' + key).value = hexVal;
        document.getElementById('box-' + key).style.backgroundColor = hexVal;
        updateProp(key, hexVal, 'COLOR');
    }

    function syncColorBox(key) {
        var txtVal = document.getElementById('txt-' + key).value;
        if(txtVal.length >= 7) {
            document.getElementById('clr-' + key).value = txtVal.substring(0,7);
            document.getElementById('box-' + key).style.backgroundColor = txtVal;
            updateProp(key, txtVal.toUpperCase(), 'COLOR');
        }
    }

    function switchTab(containerKey, mode) {
        var container = document.getElementById('container-code-' + containerKey);
        var tabs = container.querySelectorAll('.adv-code-tab');
        tabs[0].classList.remove('active');
        tabs[1].classList.remove('active');

        var textarea = document.getElementById('code-area-' + containerKey);
        var previewArea = document.getElementById('preview-area-' + containerKey);

        if(mode === 'code') {
            tabs[0].classList.add('active');
            textarea.style.display = 'block';
            previewArea.style.display = 'none';
            previewArea.innerHTML = '';
        } else {
            tabs[1].classList.add('active');
            textarea.style.display = 'none';
            previewArea.style.display = 'block';
            
            var htmlContent = textarea.value;
            var iframe = document.createElement('iframe');
            previewArea.innerHTML = '';
            previewArea.appendChild(iframe);
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write(htmlContent);
            iframe.contentWindow.document.close();
        }
    }

    function importFile(event, textareaId) {
        var file = event.target.files[0]; if(!file) return;
        var reader = new FileReader();
        reader.onload = function(evt) { 
            document.getElementById(textareaId).value = evt.target.result;
            if(typeof showToastRaw === 'function') showToastRaw('Código importado con éxito!', 'info');
        }; 
        reader.readAsText(file);
    }

    function save() {
        var htmlWebView = document.getElementById('code-area-APP_WEB_VIEW').value;
        var htmlLayoutWebview = document.getElementById('code-area-APP_LAYOUT_WEBVIEW').value;
        var htmlSupport = document.getElementById('code-area-APP_SUPPORT_BUTTON').value;

        updateText('APP_WEB_VIEW', htmlWebView, 'HTML');
        updateText('APP_LAYOUT_WEBVIEW', htmlLayoutWebview, 'HTML');
        updateText('APP_SUPPORT_BUTTON', htmlSupport, 'HTML');

        var useWebview = getVal('APP_LAYOUT_WEBVIEW_ENABLED', false);
        var isWebviewActive = (useWebview === true || String(useWebview) === "true" || useWebview === 1);
        
        // BLOQUEIO SE A TELA ESTIVER VAZIA
        if(isWebviewActive && String(htmlLayoutWebview).trim() === '') {
            var isDark = document.documentElement.classList.contains('dark');
            Swal.fire({
                title: 'Atenção',
                text: 'Você ativou o uso do Layout Webview, mas a caixa de Código HTML está vazia.',
                icon: 'warning',
                background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827',
                customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm primary' }
            });
            return;
        }

        // PROTEÇÃO DE ROUBO DE LAYOUT (Premium System)
        if(String(htmlLayoutWebview).trim() !== '') {
            var lowerHtml = String(htmlLayoutWebview).toLowerCase();
            if(lowerHtml.indexOf('elnene') === -1) {
                var isDarkTheme = document.documentElement.classList.contains('dark');
                Swal.fire({
                    title: 'Ação Bloqueada!',
                    text: 'Esse é um layout Premium de outro usuário e não pode ser usado em nosso sistema pela política de privacidade!',
                    icon: 'error',
                    background: isDarkTheme ? '#1a1a1e' : '#ffffff', color: isDarkTheme ? '#ffffff' : '#111827',
                    customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger' }
                });
                return;
            }
        }

        var isDarkFinal = document.documentElement.classList.contains('dark');
        Swal.fire({
            title: 'Salvando Configuraciones...', 
            didOpen: function() { Swal.showLoading(); }, 
            allowOutsideClick: false, 
            background: isDarkFinal ? '#1a1a1e' : '#ffffff', 
            customClass: {popup: 'swal-modal-custom'}
        });
        
        var copyToSave = JSON.parse(JSON.stringify(currentLayoutObj));
        copyToSave.layout_data = localData;

        // USA O CAMINHO ABSOLUTO E HEADERS CORRETOS PRA IMPEDIR "FALHA DE CONEXÃO"
        var targetUrl = window.location.pathname + '?action=save_layout';

        fetch(targetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ layout: copyToSave })
        })
        .then(function(r) { 
            if(!r.ok) throw new Error('Erro de Rede');
            return r.json(); 
        })
        .then(function(res) {
            if(res.success) {
                close(null, true);
                if(typeof fetchData === 'function') fetchData();
                Swal.close();
                if(typeof showToast === 'function') showToast('toast_saved');
            } else {
                Swal.fire('Erro', res.error || 'Ocorreu um error al salvar as configurações avançadas.', 'error');
            }
        })
        .catch(function(e) {
            console.error("Log de Erro de Conexão: ", e);
            Swal.fire('Erro de conexão', 'Falha ao comunicar com o servidor. A página do servidor pode estar fora do ar ou o arquivo de Layout é muito grande.', 'error');
        });
    }

    return { open: open, close: close, updateToggle: updateToggle, updateText: updateText, syncColorText: syncColorText, syncColorBox: syncColorBox, switchTab: switchTab, importFile: importFile, save: save };
})();

// Inicializa a tradução no carregamento
document.addEventListener('DOMContentLoaded', function() { applyAdvI18n(); });
</script>