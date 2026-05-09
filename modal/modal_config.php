<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Modal de Configuraciones - Clone Perfeito e Profissional
 * @description Modal idêntico ao vídeo, grids protegidos lado a lado, DTunnel separado.
 * =======================================================================================
 */
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }
?>

<style>
/* ==========================================================================
   ESTILOS PREMIUM DO MODAL (IDÊNTICO AO SEU CÓDIGO)
   ========================================================================== */
#cfg-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    z-index: 999990; 
    display: flex; justify-content: center; align-items: center;
    opacity: 0; visibility: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 20px; box-sizing: border-box;
}

#cfg-modal-overlay.show { opacity: 1; visibility: visible; }

/* Colorreção da Notificación (Toast sempre na frente de tudo) */
#toast-container { z-index: 9999999 !important; }

.cm-box {
    background: #1c1c1e; 
    border: 1px solid #2c2c2e; border-radius: 18px;
    width: 100%; max-width: 600px; max-height: 90vh;
    display: flex; flex-direction: column; overflow: hidden;
    transform: scale(0.95) translateY(20px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7);
    font-family: 'Manrope', system-ui, sans-serif;
}

/* Compatibilidade com Tema Claro */
:root:not(.dark) .cm-box { background: #ffffff; border-color: #e5e7eb; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); }
:root:not(.dark) .cm-input, :root:not(.dark) .cm-textarea, :root:not(.dark) .cm-select { background: #f3f4f6; border-color: #d1d5db; color: #111827; }
:root:not(.dark) .cm-label { color: #4b5563; }
:root:not(.dark) .cm-section-title { color: #111827; }

#cfg-modal-overlay.show .cm-box { transform: scale(1) translateY(0); }

/* --- HEADER DO MODAL --- */
.cm-header {
    padding: 20px 24px 16px 24px;
    border-bottom: 1px solid #2c2c2e;
    flex-shrink: 0;
}
:root:not(.dark) .cm-header { border-color: #e5e7eb; }

/* --- CORPO ROLÁVEL --- */
.cm-body {
    padding: 24px; overflow-y: auto; flex: 1;
    display: flex; flex-direction: column; gap: 20px;
    scrollbar-width: thin; scrollbar-color: #4a4a4c transparent;
}
.cm-body::-webkit-scrollbar { width: 6px; }
.cm-body::-webkit-scrollbar-thumb { background: #4a4a4c; border-radius: 10px; }

/* --- GRIDS (Protegidos para não quebrar) --- */
.cm-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.cm-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

@media (max-width: 500px) {
    .cm-grid-2, .cm-grid-3 { grid-template-columns: 1fr; gap: 16px; }
}

/* --- INPUTS & LABELS --- */
.cm-field { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.cm-label { font-size: 0.8rem; font-weight: 700; color: #9ca3af; letter-spacing: 0.3px; }

.cm-input, .cm-select, .cm-textarea {
    width: 100%; background: #242426; border: 1px solid #3a3a3c;
    border-radius: 12px; padding: 14px 16px; color: #f9fafb;
    font-size: 0.95rem; font-weight: 600; outline: none; transition: all 0.2s;
    box-sizing: border-box; font-family: inherit;
}
.cm-textarea { resize: vertical; min-height: 100px; line-height: 1.4; }
.cm-select { appearance: none; cursor: pointer; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 14px center; background-size: 16px; padding-right: 40px; }
.cm-input:focus, .cm-select:focus, .cm-textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }

/* Ícones nos Inputs */
.cm-input-icon-wrap { position: relative; width: 100%; }
.cm-icon-btn {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: #9ca3af; padding: 6px;
    border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;
}
.cm-icon-btn:hover { color: #f9fafb; background: #3a3a3c; }
.cm-icon-btn.eye { right: 44px; }
:root:not(.dark) .cm-icon-btn:hover { color: #111827; background: #e5e7eb; }

/* --- SEÇÃO PARÂMETROS DO MODO --- */
.cm-section-title {
    font-size: 0.85rem; font-weight: 800; color: #ffffff;
    text-transform: uppercase; letter-spacing: 1px; margin: 10px 0 0 0;
    padding-bottom: 8px; border-bottom: 1px solid #2c2c2e;
}
:root:not(.dark) .cm-section-title { border-color: #e5e7eb; }

/* --- FOOTER (BOTÕES) --- */
.cm-footer {
    padding: 16px 24px; border-top: 1px solid #2c2c2e;
    display: flex; gap: 12px; background: #1c1c1e; border-radius: 0 0 18px 18px; flex-shrink: 0;
}
:root:not(.dark) .cm-footer { border-color: #e5e7eb; background: #ffffff; }

.cm-btn {
    flex: 1; padding: 14px; border-radius: 12px; font-size: 0.95rem; font-weight: 800;
    border: none; cursor: pointer; transition: transform 0.15s, background 0.2s; outline: none;
}
.cm-btn:active { transform: scale(0.96); }
.cm-btn-cancel { background: transparent; border: 1px solid #4a4a4c; color: #f9fafb; }
.cm-btn-save { background: #3b82f6; color: #ffffff; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }

:root:not(.dark) .cm-btn-cancel { border-color: #d1d5db; color: #111827; }

/* ==========================================================================
   ESTILOS EXTRAS: MODAL DE ÍCONE E GALERIA
   ========================================================================== */
.mc-sub-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 999995;
    display: flex; align-items: center; justify-content: center; flex-direction: column;
    opacity: 0; visibility: hidden; transition: all 0.3s; padding: 20px; box-sizing: border-box;
}
.mc-sub-overlay.show { opacity: 1; visibility: visible; }

.ip-box {
    width: 200px; height: 200px; background: #1c1c1e; border-radius: 30px;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
    border: 2px solid #2c2c2e; padding: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    transform: scale(0.8); transition: transform 0.3s;
}
:root:not(.dark) .ip-box { background: #ffffff; border-color: #e5e7eb; }
.mc-sub-overlay.show .ip-box { transform: scale(1); }
.ip-box img { width: 100%; height: 100%; object-fit: contain; }

.ip-close {
    margin-top: 30px; width: 50px; height: 50px; border-radius: 50%; background: #242426;
    border: 1px solid #2c2c2e; color: #f9fafb; display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: 0.2s; outline: none;
}
:root:not(.dark) .ip-close { background: #f3f4f6; border-color: #d1d5db; color: #111827; }
.ip-close:active { transform: scale(0.85); background: #ef4444; color: white; border-color: #ef4444; }

.gal-box {
    width: 100%; max-width: 500px; background: #1c1c1e; border-radius: 24px;
    display: flex; flex-direction: column; max-height: 85vh; border: 1px solid #2c2c2e;
    transform: translateY(20px); transition: transform 0.3s; overflow: hidden;
}
:root:not(.dark) .gal-box { background: #ffffff; border-color: #e5e7eb; }
.mc-sub-overlay.show .gal-box { transform: translateY(0); }
.gal-header { padding: 20px; border-bottom: 1px solid #2c2c2e; display: flex; justify-content: space-between; align-items: center; gap:10px; }
:root:not(.dark) .gal-header { border-color: #e5e7eb; }
.gal-title { font-size: 1.1rem; font-weight: 800; color: #ffffff; }
:root:not(.dark) .gal-title { color: #111827; }
.gal-upload-btn { background: #3b82f6; color: #fff; border: none; border-radius: 10px; padding: 8px 14px; font-size: 0.78rem; font-weight: 800; cursor: pointer; white-space: nowrap; transition: 0.2s; }
.gal-upload-btn:active { transform: scale(0.95); background: #2563eb; }
.gal-section-label { grid-column: 1 / -1; font-size: 0.65rem; font-weight: 900; letter-spacing: 1px; color: #6b7280; text-transform: uppercase; padding: 4px 0 2px 0; }
.gal-custom { border-color: #3b82f6 !important; }
.gal-grid {
    padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 16px;
    overflow-y: auto; flex: 1; scrollbar-width: thin; scrollbar-color: #4a4a4c transparent;
}
.gal-item {
    aspect-ratio: 1; background: #242426; border: 2px solid #2c2c2e; border-radius: 16px;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; padding: 12px;
}
:root:not(.dark) .gal-item { background: #f3f4f6; border-color: #d1d5db; }
.gal-item img { width: 100%; height: 100%; object-fit: contain; }
.gal-item:active { transform: scale(0.9); border-color: #3b82f6; }
</style>

<div id="cfg-modal-overlay">
    <div class="cm-box">
        
        <div class="cm-header">
            <div class="cm-field">
                <label class="cm-label" data-i18n="conn_mode">Modo de conexão</label>
                <select id="cfg-mode" class="cm-select" onchange="ConfigModalManager.handleModeChange()">
                    <option value="DTUNNEL">DTunnel</option>
                    <option value="SSH_DIRECT">SSH Direct</option>
                    <option value="SSH_PROXY">SSH Proxy</option>
                    <option value="SSH_DNSTT">SSH DNSTT</option>
                    <option value="SSL_DIRECT">SSL Direct</option>
                    <option value="SSL_PROXY">SSL Proxy</option>
                    <option value="V2RAY">V2Ray / XRay</option>
                    <option value="HYSTERIA">Hysteria</option>
                </select>
            </div>
        </div>

        <div class="cm-body">
            <div class="cm-grid-2">
                <div class="cm-field">
                    <label class="cm-label" data-i18n="name_req">Nombre *</label>
                    <input type="text" id="cfg-name" class="cm-input" placeholder="Ex: SSH Premium" required>
                </div>
                <div class="cm-field">
                    <label class="cm-label" data-i18n="desc">Descripción</label>
                    <input type="text" id="cfg-desc" class="cm-input" placeholder="Ex: Acesso principal do app">
                </div>
            </div>

            <div class="cm-grid-2">
                <div class="cm-field">
                    <label class="cm-label" data-i18n="cat_req">Categorías *</label>
                    <select id="cfg-category" class="cm-select">
                        <!-- Puxado via JS -->
                    </select>
                </div>
                <div class="cm-field">
                    <label class="cm-label" data-i18n="order">Ordem</label>
                    <input type="number" id="cfg-sorter" class="cm-input" value="1">
                </div>
            </div>

            <div class="cm-field">
                <label class="cm-label" data-i18n="icon_url">Ícone (URL)</label>
                <div class="cm-input-icon-wrap">
                    <input type="text" id="cfg-icon" class="cm-input" placeholder="https://site.com/icon.png">
                    <button class="cm-icon-btn eye" onclick="ConfigModalManager.previewIcon()" title="Visualizar Ícone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                    <button class="cm-icon-btn" onclick="ConfigModalManager.openGallery()" title="Arquivos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></button>
                </div>
            </div>

            <div class="cm-field">
                <label class="cm-label" data-i18n="url_check">URL Check User (opcional)</label>
                <input type="text" id="cfg-url-check" class="cm-input" placeholder="https://site.com/check-user">
            </div>

            <h3 class="cm-section-title" data-i18n="params_mode">PARÂMETROS DO MODO</h3>

            <!-- CUENTAINERS "WRAP" -> Usamos div block normal por fora para o JS não destruir o GRID 2 -->

            <div id="wrap-payload" style="display:none; padding-bottom: 16px;">
                <div class="cm-field">
                    <label class="cm-label" data-i18n="payload">Payload (opcional)</label>
                    <textarea id="cfg-payload" class="cm-textarea" placeholder="GET / HTTP/1.1[crlf]Host: example.com[crlf][crlf]"></textarea>
                </div>
            </div>

            <!-- EXCLUSIVO DTUNNEL: Protocolo + TLS -->
            <div id="wrap-dtunnel-proto" style="display:none; padding-bottom: 16px;">
                <div class="cm-grid-2">
                    <div class="cm-field">
                        <label class="cm-label">Protocolo</label>
                        <select id="cfg-protocol" class="cm-select">
                            <option value="TCP">TCP</option>
                            <option value="UDP">UDP</option>
                            <option value="QUIC">QUIC</option>
                        </select>
                    </div>
                    <div class="cm-field">
                        <label class="cm-label" data-i18n="tls_ver">Versión do TLS</label>
                        <select id="cfg-dt-tls" class="cm-select">
                            <option value="TLSv1.3">TLSv1.3</option>
                            <option value="TLSv1.2" selected>TLSv1.2</option>
                            <option value="TLSv1.1">TLSv1.1</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- COMPARTILHADO: SNI & TLS -->
            <div id="wrap-sni-tls" style="display:none; padding-bottom: 16px;">
                <div class="cm-grid-2">
                    <div class="cm-field">
                        <label class="cm-label" data-i18n="sni">SNI (opcional)</label>
                        <input type="text" id="cfg-sni" class="cm-input" placeholder="Ex: google.com">
                    </div>
                    <div class="cm-field" id="inner-wrap-tls">
                        <label class="cm-label" data-i18n="tls_ver">Versión do TLS</label>
                        <select id="cfg-tls" class="cm-select">
                            <option value="TLSv1.3">TLSv1.3</option>
                            <option value="TLSv1.2" selected>TLSv1.2</option>
                            <option value="TLSv1.1">TLSv1.1</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- COMPARTILHADO: SERVIDOR / PORTA -->
            <div id="wrap-server" style="display:none; padding-bottom: 16px;">
                <div class="cm-grid-2">
                    <div class="cm-field">
                        <label class="cm-label" id="lbl-server" data-i18n="server">Servidor</label>
                        <input type="text" id="cfg-server-host" class="cm-input" placeholder="Ex: 127.0.0.1">
                    </div>
                    <div class="cm-field">
                        <label class="cm-label" data-i18n="port">Puerto</label>
                        <input type="number" id="cfg-server-port" class="cm-input" placeholder="Ex: 80">
                    </div>
                </div>
            </div>

            <!-- COMPARTILHADO: PROXY / PORTA PROXY -->
            <div id="wrap-proxy" style="display:none; padding-bottom: 16px;">
                <div class="cm-grid-2">
                    <div class="cm-field">
                        <label class="cm-label" data-i18n="proxy">Proxy</label>
                        <input type="text" id="cfg-proxy-host" class="cm-input" placeholder="Ex: 127.0.0.1">
                    </div>
                    <div class="cm-field">
                        <label class="cm-label" data-i18n="proxy_port">Puerto do proxy</label>
                        <input type="number" id="cfg-proxy-port" class="cm-input" placeholder="Ex: 8080">
                    </div>
                </div>
            </div>

            <!-- EXCLUSIVO: DNSTT -->
            <div id="wrap-dnstt" style="display:none; padding-bottom: 16px;">
                <div style="display:flex; flex-direction: column; gap: 16px;">
                    <div class="cm-field">
                        <label class="cm-label">Key</label>
                        <textarea id="cfg-dnstt-key" class="cm-textarea" placeholder="Cole aqui a key do DNSTT" style="min-height: 70px;"></textarea>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="dnstt_ns">Nombre do servidor</label>
                            <input type="text" id="cfg-dnstt-server" class="cm-input" placeholder="Ex: ns.exemplo.com">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="dnstt_dns">DNS do servidor</label>
                            <input type="text" id="cfg-dnstt-nameserver" class="cm-input" placeholder="Ex: 8.8.8.8">
                        </div>
                    </div>
                </div>
            </div>

            <!-- COMPARTILHADO: DNS & USUÁRIO -->
            <div id="wrap-auth-dns" style="display:none; padding-bottom: 16px;">
                <div style="display:flex; flex-direction: column; gap: 16px;">
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label">DNS 1</label>
                            <input type="text" id="cfg-dns1" class="cm-input" value="8.8.8.8">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label">DNS 2</label>
                            <input type="text" id="cfg-dns2" class="cm-input" value="8.8.4.4">
                        </div>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="user">Usuario</label>
                            <input type="text" id="cfg-user" class="cm-input" placeholder="Ex: vpn">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="pass">Contraseña</label>
                            <input type="text" id="cfg-pass" class="cm-input" placeholder="Ex: 1234">
                        </div>
                    </div>
                    <div class="cm-field" id="wrap-udp-ports">
                        <label class="cm-label" data-i18n="udp_ports">Puertos UDP</label>
                        <input type="text" id="cfg-udp" class="cm-input" value="7300" placeholder="Ex: 7100, 7200, 7300">
                    </div>
                </div>
            </div>

            <!-- EXCLUSIVO: V2RAY -->
            <div id="wrap-v2ray" style="display:none; padding-bottom: 16px;">
                <div style="display:flex; flex-direction: column; gap: 16px;">
                    <div class="cm-field">
                        <label class="cm-label">Configuración V2Ray / XRay</label>
                        <textarea id="cfg-v2ray-config" class="cm-textarea" placeholder="Cole o JSON ou a configuração completa do V2Ray/XRay"></textarea>
                    </div>
                    <div class="cm-field">
                        <label class="cm-label">UUID</label>
                        <input type="text" id="cfg-v2ray-uuid" class="cm-input" placeholder="Ex: 00000000-0000-0000-0000-000000000000">
                    </div>
                </div>
            </div>

            <!-- EXCLUSIVO: HYSTERIA -->
            <div id="wrap-hysteria" style="display:none; padding-bottom: 16px;">
                <div style="display:flex; flex-direction: column; gap: 16px;">
                    <div class="cm-field">
                        <label class="cm-label" data-i18n="hy_ver">Versión do Hysteria</label>
                        <select id="cfg-hy-version" class="cm-select">
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="server">Servidor</label>
                            <input type="text" id="cfg-hy-host" class="cm-input" placeholder="Ex: tunnel.example.com">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="port">Puerto</label>
                            <input type="number" id="cfg-hy-port" class="cm-input" value="13375">
                        </div>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="sni">SNI (opcional)</label>
                            <input type="text" id="cfg-hy-sni" class="cm-input" placeholder="Ex: example.com">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="pass">Contraseña</label>
                            <input type="text" id="cfg-hy-pass" class="cm-input" placeholder="Ex: secret">
                        </div>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="obfs">Ofuscação (opcional)</label>
                            <input type="text" id="cfg-hy-obfs" class="cm-input" placeholder="Ex: obfs_password">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="insecure">Conexões Inseguras</label>
                            <select id="cfg-hy-insecure" class="cm-select">
                                <option value="true">Sim</option>
                                <option value="false">Não</option>
                            </select>
                        </div>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="upload">Upload (Mbps)</label>
                            <input type="number" id="cfg-hy-up" class="cm-input" value="100">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label" data-i18n="download">Download (Mbps)</label>
                            <input type="number" id="cfg-hy-down" class="cm-input" value="150">
                        </div>
                    </div>
                    <div class="cm-grid-2">
                        <div class="cm-field">
                            <label class="cm-label">DNS 1</label>
                            <input type="text" id="cfg-hy-dns1" class="cm-input" value="8.8.8.8">
                        </div>
                        <div class="cm-field">
                            <label class="cm-label">DNS 2</label>
                            <input type="text" id="cfg-hy-dns2" class="cm-input" value="8.8.4.4">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="cm-footer">
            <button class="cm-btn cm-btn-cancel" onclick="ConfigModalManager.close()" data-i18n="btn_close">Cerrar</button>
            <button class="cm-btn cm-btn-save" onclick="ConfigModalManager.save()" data-i18n="btn_save">Guardar</button>
        </div>
    </div>
</div>

<!-- =====================================================================================
     MODAIS FLUTUANTES (OLHO / GALERIA)
====================================================================================== -->
<div class="mc-sub-overlay" id="ip-overlay" onclick="if(event.target === this) ConfigModalManager.closeIconPreview()">
    <div class="ip-box">
        <img id="ip-img" src="" alt="Icon" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23ef4444\' stroke-width=\'2\'%3e%3ccircle cx=\'12\' cy=\'12\' r=\'10\'/%3e%3cline x1=\'15\' y1=\'9\' x2=\'9\' y2=\'15\'/%3e%3cline x1=\'9\' y1=\'9\' x2=\'15\' y2=\'15\'/%3e%3c/svg%3e';">
    </div>
    <button class="ip-close" onclick="ConfigModalManager.closeIconPreview()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:24px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
</div>

<div class="mc-sub-overlay" id="gal-overlay" onclick="if(event.target === this) ConfigModalManager.closeGallery()">
    <div class="gal-box">
        <div class="gal-header">
            <div class="gal-title" data-i18n="gal_title">Galería de Iconos</div>
            <div style="display:flex;align-items:center;gap:8px;">
                <input type="file" id="gal-upload-inp" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none" onchange="ConfigModalManager.uploadGalleryIcon()">
                <button id="gal-upload-btn" class="gal-upload-btn" onclick="document.getElementById('gal-upload-inp').click()">+ Subir icono</button>
                <button class="ip-close" style="margin:0; width:36px; height:36px;" onclick="ConfigModalManager.closeGallery()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="gal-grid" id="gal-grid">
            <!-- Puxado via JS -->
        </div>
    </div>
</div>

<script>
// ==========================================================================
// CÉREBRO DO MODAL (LÓGICA, VALIDAÇÃO E MONTAGEM DO JSON)
// ==========================================================================
const ConfigModalManager = {
    currentConfigId: null,
    statusHolder: 'ACTIVE',

    // Ícones da Galeria Profissional
    galleryIcons: [
        // ── Telecom LATAM ──
        'https://cdn.simpleicons.org/movistar/009BDE',
        'https://cdn.simpleicons.org/claro/DA291C',
        'https://cdn.simpleicons.org/tim/1A4098',
        'https://cdn.simpleicons.org/vivo/415FFF',
        'https://cdn.simpleicons.org/tuenti/01ADEF',
        'https://cdn.simpleicons.org/vodafone/E60000',
        'https://cdn.simpleicons.org/att/00A8E0',
        'https://cdn.simpleicons.org/tmobile/E20074',
        'https://cdn.simpleicons.org/verizon/CD040B',
        'https://cdn.simpleicons.org/orange/FF7900',
        // ── Mensajería y Redes Sociales ──
        'https://cdn.simpleicons.org/whatsapp/25D366',
        'https://cdn.simpleicons.org/telegram/26A5E4',
        'https://cdn.simpleicons.org/signal/3A76F0',
        'https://cdn.simpleicons.org/facebook/1877F2',
        'https://cdn.simpleicons.org/instagram/E4405F',
        'https://cdn.simpleicons.org/youtube/FF0000',
        'https://cdn.simpleicons.org/tiktok/000000',
        'https://cdn.simpleicons.org/discord/5865F2',
        'https://cdn.simpleicons.org/x/000000',
        'https://cdn.simpleicons.org/linkedin/0A66C2',
        'https://cdn.simpleicons.org/snapchat/FFFC00',
        'https://cdn.simpleicons.org/pinterest/E60023',
        // ── Internet / VPN / Tecnología ──
        'https://cdn.simpleicons.org/cloudflare/F38020',
        'https://cdn.simpleicons.org/openvpn/EA7E20',
        'https://cdn.simpleicons.org/wireguard/88171A',
        'https://cdn.simpleicons.org/nordvpn/4687FF',
        'https://cdn.simpleicons.org/google/4285F4',
        'https://cdn.simpleicons.org/android/34A853',
        'https://cdn.simpleicons.org/apple/000000',
        'https://cdn.simpleicons.org/windows/0078D4',
        'https://cdn.simpleicons.org/netflix/E50914',
        'https://cdn.simpleicons.org/spotify/1DB954',
        'https://cdn.simpleicons.org/amazon/FF9900',
        // ── Iconos genéricos de red (flaticon free) ──
        'https://cdn-icons-png.flaticon.com/512/2950/2950114.png',
        'https://cdn-icons-png.flaticon.com/512/1005/1005141.png',
        'https://cdn-icons-png.flaticon.com/512/808/808476.png',
        'https://cdn-icons-png.flaticon.com/512/873/873100.png',
        // ── Banderas LATAM y más ──
        'https://flagcdn.com/w160/ar.png',
        'https://flagcdn.com/w160/br.png',
        'https://flagcdn.com/w160/co.png',
        'https://flagcdn.com/w160/mx.png',
        'https://flagcdn.com/w160/cl.png',
        'https://flagcdn.com/w160/pe.png',
        'https://flagcdn.com/w160/pa.png',
        'https://flagcdn.com/w160/bo.png',
        'https://flagcdn.com/w160/ve.png',
        'https://flagcdn.com/w160/py.png',
        'https://flagcdn.com/w160/uy.png',
        'https://flagcdn.com/w160/ec.png',
        'https://flagcdn.com/w160/do.png',
        'https://flagcdn.com/w160/gt.png',
        'https://flagcdn.com/w160/hn.png',
        'https://flagcdn.com/w160/cr.png',
        'https://flagcdn.com/w160/es.png',
        'https://flagcdn.com/w160/pt.png',
        'https://flagcdn.com/w160/gb.png',
        'https://flagcdn.com/w160/us.png',
        'https://flagcdn.com/w160/de.png',
        'https://flagcdn.com/w160/fr.png',
        'https://flagcdn.com/w160/it.png',
        'https://flagcdn.com/w160/cn.png',
        'https://flagcdn.com/w160/jp.png',
    ],

    // Iconos subidos por el usuario (se cargan desde el servidor)
    customIcons: [],

    loadCustomIcons: function(callback) {
        fetch('/home-config?action=list_icons', {method:'POST', headers:{'Content-Type':'application/json'}, body:'{}'})
        .then(r=>r.json()).then(res=>{
            if(res.success) this.customIcons = res.icons || [];
            if(callback) callback();
        }).catch(()=>{ if(callback) callback(); });
    },

    // Dicionário do Modal
    dict: {
        'pt': { 'conn_mode':'Modo de conexão', 'name_req':'Nombre *', 'desc':'Descripción', 'cat_req':'Categorías *', 'order':'Ordem', 'icon_url':'Ícone (URL)', 'url_check':'URL Check User (opcional)', 'params_mode':'PARÂMETROS DO MODO', 'payload':'Payload (opcional)', 'sni':'SNI (opcional)', 'tls_ver':'Versión do TLS', 'server':'Servidor', 'port':'Puerto', 'proxy':'Proxy', 'proxy_port':'Puerto do proxy', 'dnstt_ns':'Nombre do servidor', 'dnstt_dns':'DNS do servidor', 'user':'Usuario', 'pass':'Contraseña', 'udp_ports':'Puertos UDP', 'hy_ver':'Versión do Hysteria', 'obfs':'Ofuscação (opcional)', 'insecure':'Conexões Inseguras', 'upload':'Upload (Mbps)', 'download':'Download (Mbps)', 'btn_close':'Cerrar', 'btn_save':'Guardar', 'err_name':'O campo Nombre é obrigatório!', 'err_cat':'Por favor, selecione uma Categoría!', 'gal_title':'Galeria de Ícones', 'sel_cat':'Selecione uma categoria...' },
        'en': { 'conn_mode':'Connection Mode', 'name_req':'Name *', 'desc':'Description', 'cat_req':'Categories *', 'order':'Order', 'icon_url':'Icon (URL)', 'url_check':'URL Check User (optional)', 'params_mode':'MODE PARAMETERS', 'payload':'Payload (optional)', 'sni':'SNI (optional)', 'tls_ver':'TLS Version', 'server':'Server', 'port':'Port', 'proxy':'Proxy', 'proxy_port':'Proxy Port', 'dnstt_ns':'Server Name', 'dnstt_dns':'Server DNS', 'user':'User', 'pass':'Password', 'udp_ports':'UDP Ports', 'hy_ver':'Hysteria Version', 'obfs':'Obfuscation (optional)', 'insecure':'Insecure Connections', 'upload':'Upload (Mbps)', 'download':'Download (Mbps)', 'btn_close':'Close', 'btn_save':'Save', 'err_name':'Name field is required!', 'err_cat':'Please select a Category!', 'gal_title':'Icon Gallery', 'sel_cat':'Select a category...' },
        'es': { 'conn_mode':'Modo de Conexión', 'name_req':'Nombre *', 'desc':'Descripción', 'cat_req':'Categorías *', 'order':'Orden', 'icon_url':'Icono (URL)', 'url_check':'URL Check User (opcional)', 'params_mode':'PARÁMETROS DEL MODO', 'payload':'Payload (opcional)', 'sni':'SNI (opcional)', 'tls_ver':'Versión TLS', 'server':'Servidor', 'port':'Puerto', 'proxy':'Proxy', 'proxy_port':'Puerto Proxy', 'dnstt_ns':'Nombre del Servidor', 'dnstt_dns':'DNS del Servidor', 'user':'Usuario', 'pass':'Contraseña', 'udp_ports':'Puertos UDP', 'hy_ver':'Versión de Hysteria', 'obfs':'Ofuscación (opcional)', 'insecure':'Conexiones Inseguras', 'upload':'Upload (Mbps)', 'download':'Download (Mbps)', 'btn_close':'Cerrar', 'btn_save':'Guardar', 'err_name':'¡El campo Nombre es obligatorio!', 'err_cat':'¡Por favor seleccione una Categoría!', 'gal_title':'Galería de Iconos', 'sel_cat':'Seleccione una categoría...' }
    },

    applyI18n: function() {
        var lang = localStorage.getItem('app_language') || 'pt';
        var t = this.dict[lang] || this.dict['pt'];
        var els = document.querySelectorAll('[data-i18n]');
        for(var i=0; i<els.length; i++) {
            var key = els[i].getAttribute('data-i18n');
            if(t[key]) els[i].innerText = t[key];
        }
    },

    open: function(configData) {
        this.applyI18n();
        this.renderCategories();
        this.resetFields();

        if (configData) {
            this.currentConfigId = configData.id;
            this.statusHolder = configData.status || 'ACTIVE';
            this.populateFields(configData);
        } else {
            this.currentConfigId = null;
            this.statusHolder = 'ACTIVE';
            document.getElementById('cfg-mode').value = 'DTUNNEL';
            document.getElementById('cfg-dns1').value = '8.8.8.8';
            document.getElementById('cfg-dns2').value = '8.8.4.4';
            document.getElementById('cfg-udp').value = '7300';
            document.getElementById('cfg-tls').value = 'TLSv1.2';
            document.getElementById('cfg-dt-tls').value = 'TLSv1.2';
            document.getElementById('cfg-protocol').value = 'TCP';
            document.getElementById('cfg-hy-version').value = '1';
            document.getElementById('cfg-sorter').value = '1';
        }

        this.handleModeChange();
        var overlay = document.getElementById('cfg-modal-overlay');
        overlay.classList.add('show');
    },

    close: function() {
        var overlay = document.getElementById('cfg-modal-overlay');
        overlay.classList.remove('show');
    },

    // Renderização Clássica (Garante que PHP não vai quebrar String de JS)
    renderCategories: function() {
        var select = document.getElementById('cfg-category');
        var lang = localStorage.getItem('app_language') || 'pt';
        var t = this.dict[lang] || this.dict['pt'];
        
        var html = '<option value="" disabled selected>' + t['sel_cat'] + '</option>';
        if (window.userCategories && window.userCategories.length > 0) {
            for(var i=0; i<window.userCategories.length; i++) {
                var c = window.userCategories[i];
                html += '<option value="' + c.id + '">' + c.name + '</option>';
            }
        }
        select.innerHTML = html;
    },

    // Modais de Ícone e Galeria
    previewIcon: function() {
        var url = document.getElementById('cfg-icon').value.trim();
        if(!url) { this.toastErro("Digite ou cole uma URL primeiro."); return; }
        document.getElementById('ip-img').src = url;
        document.getElementById('ip-overlay').classList.add('show');
    },
    closeIconPreview: function() {
        document.getElementById('ip-overlay').classList.remove('show');
    },

    openGallery: function() {
        var self = this;
        self.loadCustomIcons(function() {
            var grid = document.getElementById('gal-grid');
            var allIcons = self.customIcons.concat(self.galleryIcons);
            var html = '';

            // Sección iconos subidos
            if(self.customIcons.length > 0) {
                html += '<div class="gal-section-label">MIS ICONOS</div>';
                for(var i=0; i<self.customIcons.length; i++) {
                    var u = self.customIcons[i];
                    var fname = u.split('/').pop();
                    html += '<div class="gal-item gal-custom" title="'+fname+'" onclick="ConfigModalManager.selectGalleryIcon(\''+u+'\')"><img src="'+u+'" onerror="this.parentNode.style.display=\'none\'"></div>';
                }
            }

            // Sección galería base
            html += '<div class="gal-section-label">GALERÍA</div>';
            for(var j=0; j<self.galleryIcons.length; j++) {
                var url = self.galleryIcons[j];
                html += '<div class="gal-item" onclick="ConfigModalManager.selectGalleryIcon(\''+url+'\')"><img src="'+url+'" onerror="this.parentNode.style.display=\'none\'"></div>';
            }

            grid.innerHTML = html;
            document.getElementById('gal-overlay').classList.add('show');
        });
    },
    closeGallery: function() {
        document.getElementById('gal-overlay').classList.remove('show');
    },
    selectGalleryIcon: function(url) {
        document.getElementById('cfg-icon').value = url;
        this.closeGallery();
    },
    uploadGalleryIcon: function() {
        var inp = document.getElementById('gal-upload-inp');
        if(!inp.files[0]) return;
        var file = inp.files[0];
        if(file.size > 2*1024*1024) { alert('Máximo 2MB'); return; }
        var fd = new FormData();
        fd.append('icon_file', file);
        var btn = document.getElementById('gal-upload-btn');
        btn.textContent = 'Subiendo...';
        fetch('/home-config?action=upload_icon', {method:'POST', body: fd})
        .then(r=>r.json()).then(res=>{
            btn.textContent = '+ Subir icono';
            inp.value = '';
            if(res.success) {
                ConfigModalManager.openGallery();
            } else {
                alert('Error: ' + (res.error || 'desconocido'));
            }
        }).catch(()=>{ btn.textContent = '+ Subir icono'; alert('Error de red'); });
    },

    // Lógica das Caixas Dinâmicas baseada no Vídeo Exato
    handleModeChange: function() {
        var mode = document.getElementById('cfg-mode').value;
        
        // Oculta tudo usando block para respeitar a estrutura interna
        var blocks = ['wrap-payload', 'wrap-dtunnel-proto', 'wrap-sni-tls', 'wrap-server', 'wrap-proxy', 'wrap-dnstt', 'wrap-auth-dns', 'wrap-v2ray', 'wrap-hysteria'];
        for(var i=0; i<blocks.length; i++) {
            var el = document.getElementById(blocks[i]);
            if(el) el.style.display = 'none';
        }

        document.getElementById('inner-wrap-tls').style.display = 'none'; // Esconde TLS do SSL
        document.getElementById('wrap-udp-ports').style.display = 'none'; // UDP port só as vezes

        var lang = localStorage.getItem('app_language') || 'pt';
        var t = this.dict[lang] || this.dict['pt'];
        document.getElementById('lbl-server').innerText = t['server'];

        // Lógica Fina igual do Vídeo e da sua Imagem
        switch (mode) {
            case 'DTUNNEL':
                this.showBlocks(['wrap-payload', 'wrap-dtunnel-proto', 'wrap-sni-tls', 'wrap-server', 'wrap-auth-dns']);
                document.getElementById('wrap-udp-ports').style.display = 'block';
                break;
            case 'SSH_DIRECT':
                this.showBlocks(['wrap-payload', 'wrap-server', 'wrap-auth-dns']);
                document.getElementById('wrap-udp-ports').style.display = 'block';
                break;
            case 'SSH_PROXY':
                this.showBlocks(['wrap-payload', 'wrap-server', 'wrap-proxy', 'wrap-auth-dns']);
                document.getElementById('wrap-udp-ports').style.display = 'block';
                break;
            case 'SSH_DNSTT':
                this.showBlocks(['wrap-dnstt', 'wrap-auth-dns']);
                document.getElementById('wrap-udp-ports').style.display = 'block';
                break;
            case 'SSL_DIRECT':
                this.showBlocks(['wrap-payload', 'wrap-sni-tls', 'wrap-server', 'wrap-auth-dns']);
                document.getElementById('inner-wrap-tls').style.display = 'flex';
                document.getElementById('wrap-udp-ports').style.display = 'block';
                break;
            case 'SSL_PROXY':
                this.showBlocks(['wrap-payload', 'wrap-sni-tls', 'wrap-server', 'wrap-proxy', 'wrap-auth-dns']);
                document.getElementById('inner-wrap-tls').style.display = 'flex';
                document.getElementById('wrap-udp-ports').style.display = 'block';
                break;
            case 'V2RAY':
                this.showBlocks(['wrap-v2ray']);
                break;
            case 'HYSTERIA':
                this.showBlocks(['wrap-hysteria']);
                break;
        }
    },

    showBlocks: function(ids) {
        for(var i=0; i<ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if(el) el.style.display = 'block';
        }
    },

    // Guardar e Validar Ferozmente
    save: function() {
        var lang = localStorage.getItem('app_language') || 'pt';
        var t = this.dict[lang] || this.dict['pt'];

        var name = document.getElementById('cfg-name').value.trim();
        var catId = document.getElementById('cfg-category').value;

        if (!name) { this.toastErro(t['err_name']); return; }
        if (!catId) { this.toastErro(t['err_cat']); return; }

        var mode = document.getElementById('cfg-mode').value;
        
        var payloadObj = {
            id: this.currentConfigId,
            category_id: parseInt(catId),
            name: name,
            description: document.getElementById('cfg-desc').value.trim(),
            mode: mode,
            status: this.statusHolder,
            sorter: parseInt(document.getElementById('cfg-sorter').value) || 1,
            url_check_user: document.getElementById('cfg-url-check').value.trim() || null,
            icon: document.getElementById('cfg-icon').value.trim() || null,
            tls_version: document.getElementById('cfg-tls').value, // Fallback base
            
            // Base do json
            config_payload: { payload: null, sni: null },
            config_openvpn: null,
            config_v2ray: null,
            auth: { username: null, password: null, v2ray_uuid: null },
            proxy: { host: null, port: null },
            server: { host: null, port: null },
            dnstt_key: null,
            dnstt_server: null,
            dnstt_name_server: null,
            hy_obfs: null,
            hy_up_mbps: 100,
            hy_down_mbps: 150,
            hy_insecure: true,
            hy_port: null,
            hy_version: 1,
            dns_server: { dns1: "8.8.8.8", dns2: "8.8.4.4" },
            udp_ports: []
        };

        var rawUdp = document.getElementById('cfg-udp').value;
        if (rawUdp) {
            var splitted = rawUdp.split(',');
            for(var i=0; i<splitted.length; i++) {
                var u = parseInt(splitted[i].trim());
                if(!isNaN(u)) payloadObj.udp_ports.push(u);
            }
        }

        if (mode === 'DTUNNEL' || mode === 'SSH_DIRECT' || mode === 'SSH_PROXY' || mode === 'SSL_DIRECT' || mode === 'SSL_PROXY') {
            payloadObj.config_payload.payload = document.getElementById('cfg-payload').value || null;
            payloadObj.server.host = document.getElementById('cfg-server-host').value || null;
            payloadObj.server.port = parseInt(document.getElementById('cfg-server-port').value) || 80;
            payloadObj.auth.username = document.getElementById('cfg-user').value || null;
            payloadObj.auth.password = document.getElementById('cfg-pass').value || null;
            payloadObj.dns_server.dns1 = document.getElementById('cfg-dns1').value || "8.8.8.8";
            payloadObj.dns_server.dns2 = document.getElementById('cfg-dns2').value || "8.8.4.4";
            
            if (mode === 'DTUNNEL') {
                payloadObj.config_payload.sni = document.getElementById('cfg-sni').value || null;
                payloadObj.tls_version = document.getElementById('cfg-dt-tls').value;
                payloadObj.protocol = document.getElementById('cfg-protocol').value; // Adicionado extra pra garantir
            }
            if (mode === 'SSL_DIRECT' || mode === 'SSL_PROXY') {
                payloadObj.config_payload.sni = document.getElementById('cfg-sni').value || null;
                payloadObj.tls_version = document.getElementById('cfg-tls').value;
            }
            if (mode === 'SSH_PROXY' || mode === 'SSL_PROXY') {
                payloadObj.proxy.host = document.getElementById('cfg-proxy-host').value || null;
                payloadObj.proxy.port = parseInt(document.getElementById('cfg-proxy-port').value) || 8080;
            }
        } 
        else if (mode === 'SSH_DNSTT') {
            payloadObj.dnstt_key = document.getElementById('cfg-dnstt-key').value || null;
            payloadObj.dnstt_server = document.getElementById('cfg-dnstt-server').value || null;
            payloadObj.dnstt_name_server = document.getElementById('cfg-dnstt-nameserver').value || null;
            payloadObj.auth.username = document.getElementById('cfg-user').value || null;
            payloadObj.auth.password = document.getElementById('cfg-pass').value || null;
            payloadObj.dns_server.dns1 = document.getElementById('cfg-dns1').value || "8.8.8.8";
            payloadObj.dns_server.dns2 = document.getElementById('cfg-dns2').value || "8.8.4.4";
        }
        else if (mode === 'V2RAY') {
            payloadObj.config_v2ray = document.getElementById('cfg-v2ray-config').value || null;
            payloadObj.auth.v2ray_uuid = document.getElementById('cfg-v2ray-uuid').value || null;
        }
        else if (mode === 'HYSTERIA') {
            payloadObj.hy_version = parseInt(document.getElementById('cfg-hy-version').value) || 1;
            payloadObj.server.host = document.getElementById('cfg-hy-host').value || null;
            payloadObj.hy_port = document.getElementById('cfg-hy-port').value || "13375";
            payloadObj.config_payload.sni = document.getElementById('cfg-hy-sni').value || null; 
            payloadObj.auth.password = document.getElementById('cfg-hy-pass').value || null;
            payloadObj.hy_obfs = document.getElementById('cfg-hy-obfs').value || null;
            payloadObj.hy_insecure = document.getElementById('cfg-hy-insecure').value === 'true';
            payloadObj.hy_up_mbps = parseInt(document.getElementById('cfg-hy-up').value) || 100;
            payloadObj.hy_down_mbps = parseInt(document.getElementById('cfg-hy-down').value) || 150;
            payloadObj.dns_server.dns1 = document.getElementById('cfg-hy-dns1').value || "8.8.8.8";
            payloadObj.dns_server.dns2 = document.getElementById('cfg-hy-dns2').value || "8.8.4.4";
        }

        if (typeof showToastRaw !== 'undefined') {
            var btn = document.querySelector('.cm-btn-save');
            var originalText = btn.innerText;
            btn.innerText = 'Salvando...'; btn.disabled = true;

            fetch('?action=save_config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ config: payloadObj })
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                btn.innerText = originalText; btn.disabled = false;
                if(res.success) {
                    ConfigModalManager.close();
                    if(typeof fetchData === 'function') fetchData();
                    showToastRaw('Configuración salva con éxito!', 'success');
                } else {
                    ConfigModalManager.toastErro(res.error || 'Error al salvar.');
                }
            }).catch(function(e){
                btn.innerText = originalText; btn.disabled = false;
                ConfigModalManager.toastErro('Erro de conexão ao salvar.');
            });
        }
    },

    // Preencher Caixas (Modo Editar)
    populateFields: function(data) {
        document.getElementById('cfg-mode').value = data.mode || 'DTUNNEL';
        document.getElementById('cfg-name').value = data.name || '';
        document.getElementById('cfg-desc').value = data.description || '';
        document.getElementById('cfg-category').value = data.category_id || '';
        document.getElementById('cfg-sorter').value = data.sorter || 1;
        document.getElementById('cfg-icon').value = data.icon || '';
        document.getElementById('cfg-url-check').value = data.url_check_user || '';
        document.getElementById('cfg-tls').value = data.tls_version || 'TLSv1.2';
        document.getElementById('cfg-dt-tls').value = data.tls_version || 'TLSv1.2';
        if (data.protocol) document.getElementById('cfg-protocol').value = data.protocol;
        
        document.getElementById('cfg-payload').value = (data.config_payload && data.config_payload.payload) ? data.config_payload.payload : '';
        document.getElementById('cfg-sni').value = (data.config_payload && data.config_payload.sni) ? data.config_payload.sni : '';
        
        document.getElementById('cfg-server-host').value = (data.server && data.server.host) ? data.server.host : '';
        document.getElementById('cfg-server-port').value = (data.server && data.server.port) ? data.server.port : '';
        
        document.getElementById('cfg-proxy-host').value = (data.proxy && data.proxy.host) ? data.proxy.host : '';
        document.getElementById('cfg-proxy-port').value = (data.proxy && data.proxy.port) ? data.proxy.port : '';

        document.getElementById('cfg-user').value = (data.auth && data.auth.username) ? data.auth.username : '';
        document.getElementById('cfg-pass').value = (data.auth && data.auth.password) ? data.auth.password : '';
        
        document.getElementById('cfg-dns1').value = (data.dns_server && data.dns_server.dns1) ? data.dns_server.dns1 : '8.8.8.8';
        document.getElementById('cfg-dns2').value = (data.dns_server && data.dns_server.dns2) ? data.dns_server.dns2 : '8.8.4.4';
        
        if (data.udp_ports && data.udp_ports.length > 0) {
            document.getElementById('cfg-udp').value = data.udp_ports.join(', ');
        } else {
            document.getElementById('cfg-udp').value = '7300';
        }

        document.getElementById('cfg-dnstt-key').value = data.dnstt_key || '';
        document.getElementById('cfg-dnstt-server').value = data.dnstt_server || '';
        document.getElementById('cfg-dnstt-nameserver').value = data.dnstt_name_server || '';

        document.getElementById('cfg-v2ray-config').value = data.config_v2ray || '';
        document.getElementById('cfg-v2ray-uuid').value = (data.auth && data.auth.v2ray_uuid) ? data.auth.v2ray_uuid : '';

        document.getElementById('cfg-hy-version').value = data.hy_version || 1;
        document.getElementById('cfg-hy-host').value = (data.server && data.server.host) ? data.server.host : '';
        document.getElementById('cfg-hy-port').value = data.hy_port || '13375';
        document.getElementById('cfg-hy-sni').value = (data.config_payload && data.config_payload.sni) ? data.config_payload.sni : '';
        document.getElementById('cfg-hy-pass').value = (data.auth && data.auth.password) ? data.auth.password : '';
        document.getElementById('cfg-hy-obfs').value = data.hy_obfs || '';
        document.getElementById('cfg-hy-insecure').value = data.hy_insecure === false ? 'false' : 'true';
        document.getElementById('cfg-hy-up').value = data.hy_up_mbps || 100;
        document.getElementById('cfg-hy-down').value = data.hy_down_mbps || 150;
        document.getElementById('cfg-hy-dns1').value = (data.dns_server && data.dns_server.dns1) ? data.dns_server.dns1 : '8.8.8.8';
        document.getElementById('cfg-hy-dns2').value = (data.dns_server && data.dns_server.dns2) ? data.dns_server.dns2 : '8.8.4.4';
    },

    resetFields: function() {
        var inputs = document.querySelectorAll('.cm-input, .cm-textarea');
        for(var i=0; i<inputs.length; i++) {
            var el = inputs[i];
            if(el.id !== 'cfg-dns1' && el.id !== 'cfg-dns2' && el.id !== 'cfg-udp' && el.id !== 'cfg-hy-port' && el.id !== 'cfg-hy-up' && el.id !== 'cfg-hy-down' && el.id !== 'cfg-hy-dns1' && el.id !== 'cfg-hy-dns2' && el.id !== 'cfg-sorter') {
                el.value = '';
            }
        }
        document.getElementById('cfg-category').value = '';
    },

    toastErro: function(msg) {
        if (typeof showToastRaw !== 'undefined') { showToastRaw(msg, 'error'); } 
        else { alert(msg); }
    }
};

// Conecta a tradução do header ao Modal
var originalModalLangCall = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(originalModalLangCall) originalModalLangCall(langCode);
    ConfigModalManager.applyI18n();
    ConfigModalManager.renderCategories();
};
</script>