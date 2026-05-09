<?php
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }
?>

<style>
/* ==========================================================================
   ESTILOS DO MODAL DE CONEXÃO (DESIGN PREMIUM INSPIRADO NO VÍDEO)
   ========================================================================== */
.cm-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); backdrop-filter: blur(3px);
    display: flex; align-items: flex-end; justify-content: center; /* Abre de baixo para cima no mobile */
    z-index: 10000; opacity: 0; visibility: hidden;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    -webkit-tap-highlight-color: transparent;
}
.cm-overlay.show { opacity: 1; visibility: visible; }

.cm-box {
    background: var(--card-bg, #ffffff);
    width: 100%; max-width: 650px; height: 90vh;
    border-radius: 24px 24px 0 0; /* Arredondado no topo */
    display: flex; flex-direction: column;
    transform: translateY(100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 -10px 40px rgba(0,0,0,0.1);
}
.cm-overlay.show .cm-box { transform: translateY(0); }

/* Adaptação para Desktop (Fica no centro da tela) */
@media (min-width: 768px) {
    .cm-overlay { align-items: center; }
    .cm-box { height: auto; max-height: 90vh; border-radius: 24px; transform: scale(0.95) translateY(20px); }
    .cm-overlay.show .cm-box { transform: scale(1) translateY(0); box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
}

:root.dark .cm-box, .dark .cm-box {
    border: 1px solid var(--card-border, #27272a);
    box-shadow: 0 -10px 50px rgba(0,0,0,0.6);
}

.cm-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--card-border, #e5e7eb);
    display: flex; align-items: center; justify-content: space-between;
}
.cm-title { font-size: 1.15rem; font-weight: 800; color: var(--text-main, #111827); margin: 0; }
.cm-close {
    background: transparent; border: none; color: var(--text-muted); cursor: pointer;
    padding: 6px; border-radius: 50%; transition: background 0.2s; outline: none;
}
.cm-close:active { background: var(--icon-bg); transform: scale(0.9); }

.cm-body {
    flex: 1; overflow-y: auto; padding: 24px;
    display: flex; flex-direction: column; gap: 16px;
    scrollbar-width: thin; scrollbar-color: var(--card-border) transparent;
}

/* Campos de Formulário com Fundo Inteligente (Corrige el bug da cor branca) */
.cm-form-group { display: flex; flex-direction: column; gap: 8px; position: relative; }
.cm-label { font-size: 0.8rem; font-weight: 600; color: var(--text-main); }

.cm-input, .cm-select, .cm-textarea {
    width: 100%;
    background: transparent; /* No modo claro usa a cor do card (branco) */
    color: var(--text-main, #111827);
    border: 1px solid var(--input-border, #d1d5db);
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 0.95rem; font-family: inherit;
    outline: none; transition: border-color 0.2s, background-color 0.2s;
    appearance: none; /* Remove seta nativa do select */
}

/* MODO ESCURO FORÇADO NOS INPUTS */
:root.dark .cm-input, .dark .cm-input,
:root.dark .cm-select, .dark .cm-select,
:root.dark .cm-textarea, .dark .cm-textarea {
    background: #121214; 
    border-color: #27272a;
    color: #f9fafb;
}

.cm-input:focus, .cm-select:focus, .cm-textarea:focus { border-color: var(--primary, #3b82f6); }
.cm-textarea { min-height: 100px; resize: vertical; font-family: monospace; font-size: 0.85rem; }

/* Grid para campos lado a lado */
.cm-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Estilo do Select customizado com setinha */
.cm-select-wrapper { position: relative; }
.cm-select-wrapper::after {
    content: ""; position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
    width: 12px; height: 12px; pointer-events: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e");
    background-size: cover;
}

/* Divisória de Seção */
.cm-section-title {
    font-size: 0.75rem; font-weight: 800; color: var(--text-muted);
    letter-spacing: 1px; text-transform: uppercase;
    margin: 16px 0 8px 0; border-bottom: 1px solid var(--card-border); padding-bottom: 8px;
}

/* Input com Ícones Dentro (Olho e Upload) */
.cm-input-icons-wrapper { position: relative; display: flex; align-items: center; }
.cm-input-icons-wrapper .cm-input { padding-right: 90px; }
.cm-icons-group { position: absolute; right: 8px; display: flex; gap: 4px; }
.cm-icon-btn {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--input-border);
    background: var(--card-bg); color: var(--text-muted);
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    transition: all 0.2s; outline: none;
}
:root.dark .cm-icon-btn, .dark .cm-icon-btn { background: rgba(255,255,255,0.05); border-color: #27272a; }
.cm-icon-btn:active { transform: scale(0.9); }

/* Esconder elementos dinâmicos suavemente */
.dyn-field { transition: opacity 0.3s; }
.dyn-field.hidden { display: none !important; }

.cm-footer {
    padding: 16px 24px; border-top: 1px solid var(--card-border);
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.cm-btn {
    padding: 14px; border-radius: 14px; font-weight: 600; font-size: 0.95rem;
    border: none; cursor: pointer; transition: transform 0.15s, opacity 0.2s; outline: none;
}
.cm-btn:active { transform: scale(0.96); }
.cm-btn-outline { background: transparent; border: 1px solid var(--input-border); color: var(--text-main); }
.cm-btn-primary { background: var(--primary, #3b82f6); color: white; }

</style>

<div id="connectionModal" class="cm-overlay" onclick="if(event.target===this) closeConfigModal()">
    <div class="cm-box">
        
        <div class="cm-header">
            <h3 class="cm-title" id="cm-modal-title" data-i18n="new_config">Nueva Configuración</h3>
            <button class="cm-close" onclick="closeConfigModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="cm-body" id="cm-body">
            <input type="hidden" id="cm_id">

            <!-- CABEÇALHO PADRÃO SEMPRE VISÍVEL -->
            <div class="cm-form-group">
                <label class="cm-label" data-i18n="conn_mode">Modo de conexão</label>
                <div class="cm-select-wrapper">
                    <select class="cm-select" id="cm_mode" onchange="updateConnModalFields()">
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

            <div class="cm-grid-2">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="name">Nombre</label>
                    <input type="text" class="cm-input" id="cm_name" placeholder="Ex: SSH Premium">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="desc">Descripción</label>
                    <input type="text" class="cm-input" id="cm_desc" placeholder="Ex: Acesso principal">
                </div>
            </div>

            <div class="cm-grid-2">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="categories">Categorías</label>
                    <div class="cm-select-wrapper">
                        <!-- Populated by JS -->
                        <select class="cm-select" id="cm_category"></select>
                    </div>
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="order">Ordem</label>
                    <input type="number" class="cm-input" id="cm_sorter" value="1">
                </div>
            </div>

            <div class="cm-form-group">
                <label class="cm-label" data-i18n="icon_url">Ícone (URL)</label>
                <div class="cm-input-icons-wrapper">
                    <input type="text" class="cm-input" id="cm_icon" placeholder="https://site.com/icon.png">
                    <div class="cm-icons-group">
                        <button class="cm-icon-btn" onclick="previewIcon()" title="Testar Imagem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="cm-icon-btn" onclick="document.getElementById('cm_upload_trigger').click()" title="Carregar do dispositivo">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        </button>
                        <input type="file" id="cm_upload_trigger" style="display:none;" accept="image/*" onchange="convertImageToBase64(event)">
                    </div>
                </div>
            </div>

            <div class="cm-form-group">
                <label class="cm-label" data-i18n="url_check">URL Check User (opcional)</label>
                <input type="text" class="cm-input" id="cm_url_check" placeholder="https://site.com/check-user">
            </div>

            <!-- SEPARADOR -->
            <div class="cm-section-title" data-i18n="mode_params">Parâmetros do Modo</div>

            <!-- ================= CAMPOS DINÂMICOS ================= -->

            <!-- PAYLOAD -->
            <div class="cm-form-group dyn-field" data-modes="SSH_DIRECT,SSH_PROXY,SSL_PROXY">
                <label class="cm-label" data-i18n="payload">Payload (opcional)</label>
                <textarea class="cm-textarea" id="cm_payload" placeholder="GET / HTTP/1.1[crlf]Host: example.com[crlf][crlf]"></textarea>
            </div>

            <!-- V2RAY / XRAY -->
            <div class="cm-form-group dyn-field" data-modes="V2RAY">
                <label class="cm-label" data-i18n="v2ray_config">Configuración V2Ray / XRay</label>
                <textarea class="cm-textarea" id="cm_v2ray" placeholder="Cole o JSON ou a configuração completa..." style="min-height:140px;"></textarea>
            </div>
            <div class="cm-form-group dyn-field" data-modes="V2RAY">
                <label class="cm-label">UUID</label>
                <input type="text" class="cm-input" id="cm_v2ray_uuid" placeholder="Ex: 00000000-0000-0000-0000-000000000000">
            </div>

            <!-- DNSTT KEY -->
            <div class="cm-form-group dyn-field" data-modes="SSH_DNSTT">
                <label class="cm-label">Key</label>
                <textarea class="cm-textarea" id="cm_dnstt_key" placeholder="Cole aqui a key do DNSTT" style="min-height:80px;"></textarea>
            </div>

            <!-- PROTOCOLO E TLS -->
            <div class="cm-grid-2 dyn-field" data-modes="SSH_DIRECT,SSH_PROXY,SSL_DIRECT,SSL_PROXY">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="protocol">Protocolo</label>
                    <div class="cm-select-wrapper">
                        <!-- O protocolo é puramente visual para o App no json export, as vezes não entra no schema restrito, mas mantemos para UX -->
                        <select class="cm-select" id="cm_protocol"><option value="TCP">TCP</option><option value="UDP">UDP</option><option value="QUIC">QUIC</option></select>
                    </div>
                </div>
                <div class="cm-form-group dyn-field" data-modes="SSL_DIRECT,SSL_PROXY">
                    <label class="cm-label" data-i18n="tls_version">Versión do TLS</label>
                    <div class="cm-select-wrapper">
                        <select class="cm-select" id="cm_tls"><option value="TLSv1.3">TLSv1.3</option><option value="TLSv1.2">TLSv1.2</option><option value="TLSv1.1">TLSv1.1</option></select>
                    </div>
                </div>
            </div>

            <!-- HYSTERIA VERSÃO -->
            <div class="cm-form-group dyn-field" data-modes="HYSTERIA">
                <label class="cm-label" data-i18n="hy_version">Versión do Hysteria</label>
                <div class="cm-select-wrapper">
                    <select class="cm-select" id="cm_hy_version"><option value="1">1</option><option value="2">2</option></select>
                </div>
            </div>

            <!-- SNI -->
            <div class="cm-form-group dyn-field" data-modes="SSL_DIRECT,SSL_PROXY,HYSTERIA">
                <label class="cm-label" data-i18n="sni">SNI (opcional)</label>
                <input type="text" class="cm-input" id="cm_sni" placeholder="Ex: google.com">
            </div>

            <!-- SERVIDOR E PORTA -->
            <div class="cm-grid-2 dyn-field" data-modes="SSH_DIRECT,SSH_PROXY,SSL_DIRECT,SSL_PROXY,HYSTERIA">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="server">Servidor</label>
                    <input type="text" class="cm-input" id="cm_server_host" placeholder="Ex: 127.0.0.1">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="port">Puerto</label>
                    <input type="text" class="cm-input" id="cm_server_port" placeholder="Ex: 80">
                </div>
            </div>

            <!-- PROXY -->
            <div class="cm-grid-2 dyn-field" data-modes="SSH_PROXY,SSL_PROXY">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="proxy">Proxy</label>
                    <input type="text" class="cm-input" id="cm_proxy_host" placeholder="Ex: 127.0.0.1">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="proxy_port">Puerto do proxy</label>
                    <input type="number" class="cm-input" id="cm_proxy_port" placeholder="Ex: 80">
                </div>
            </div>

            <!-- DNSTT SERVERS -->
            <div class="cm-grid-2 dyn-field" data-modes="SSH_DNSTT">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="dnstt_ns">Nombre do servidor</label>
                    <input type="text" class="cm-input" id="cm_dnstt_ns" placeholder="Ex: ns.exemplo.com">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="dnstt_server">DNS do servidor</label>
                    <input type="text" class="cm-input" id="cm_dnstt_server" placeholder="Ex: 8.8.8.8">
                </div>
            </div>

            <!-- HYSTERIA SPECS -->
            <div class="cm-grid-2 dyn-field" data-modes="HYSTERIA">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="hy_obfs">Ofuscação (opcional)</label>
                    <input type="text" class="cm-input" id="cm_hy_obfs" placeholder="Ex: obfs_password">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="hy_insecure">Conexoes inseguras</label>
                    <div class="cm-select-wrapper">
                        <select class="cm-select" id="cm_hy_insecure"><option value="1">Sim</option><option value="0">Não</option></select>
                    </div>
                </div>
            </div>
            <div class="cm-grid-2 dyn-field" data-modes="HYSTERIA">
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="hy_up">Upload (Mbps)</label>
                    <input type="number" class="cm-input" id="cm_hy_up" value="100">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="hy_down">Download (Mbps)</label>
                    <input type="number" class="cm-input" id="cm_hy_down" value="150">
                </div>
            </div>

            <!-- DNS 1 E DNS 2 -->
            <div class="cm-grid-2 dyn-field" data-modes="SSH_DIRECT,SSH_PROXY,SSH_DNSTT,SSL_DIRECT,SSL_PROXY,HYSTERIA">
                <div class="cm-form-group">
                    <label class="cm-label">DNS 1</label>
                    <input type="text" class="cm-input" id="cm_dns1" placeholder="8.8.8.8">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label">DNS 2</label>
                    <input type="text" class="cm-input" id="cm_dns2" placeholder="8.8.4.4">
                </div>
            </div>

            <!-- USUÁRIO E SENHA -->
            <div class="cm-grid-2 dyn-field" data-modes="SSH_DIRECT,SSH_PROXY,SSH_DNSTT,SSL_DIRECT,SSL_PROXY,HYSTERIA">
                <div class="cm-form-group" id="cm_user_container">
                    <label class="cm-label" data-i18n="user">Usuario</label>
                    <input type="text" class="cm-input" id="cm_auth_user" placeholder="Ex: vpn">
                </div>
                <div class="cm-form-group">
                    <label class="cm-label" data-i18n="password">Contraseña</label>
                    <input type="text" class="cm-input" id="cm_auth_pass" placeholder="Ex: 1234">
                </div>
            </div>

            <!-- PORTAS UDP -->
            <div class="cm-form-group dyn-field" data-modes="SSH_DIRECT,SSH_PROXY,SSH_DNSTT,SSL_DIRECT,SSL_PROXY">
                <label class="cm-label" data-i18n="udp_ports">Puertos UDP</label>
                <input type="text" class="cm-input" id="cm_udp_ports" placeholder="7100, 7200, 7300">
            </div>

        </div>

        <div class="cm-footer">
            <button class="cm-btn cm-btn-outline" onclick="closeConfigModal()" data-i18n="close">Cerrar</button>
            <button class="cm-btn cm-btn-primary" id="cm_btn_save" onclick="saveConfigData()" data-i18n="save">Guardar</button>
        </div>
    </div>
</div>

<script>
// =================================================================================
// DICIONÁRIO DE TRADUÇÃO (Injetado dinamicamente para complementar o global)
// =================================================================================
setTimeout(() => {
    if (window.globalTranslations) {
        window.globalTranslations['pt'] = { ...window.globalTranslations['pt'], 
            'new_config': 'Nueva Configuración', 'edit_config': 'Editar Configuración', 'conn_mode': 'Modo de conexão',
            'name': 'Nombre', 'desc': 'Descripción', 'categories': 'Categorías', 'order': 'Ordem', 'icon_url': 'Ícone (URL)',
            'url_check': 'URL Check User (opcional)', 'mode_params': 'Parametros do Modo', 'payload': 'Payload (opcional)',
            'v2ray_config': 'Configuración V2Ray / XRay', 'protocol': 'Protocolo', 'tls_version': 'Versión do TLS',
            'hy_version': 'Versión do Hysteria', 'sni': 'SNI (opcional)', 'server': 'Servidor', 'port': 'Puerto',
            'proxy': 'Proxy', 'proxy_port': 'Puerto do proxy', 'dnstt_ns': 'Nombre do servidor', 'dnstt_server': 'DNS do servidor',
            'hy_obfs': 'Ofuscação (opcional)', 'hy_insecure': 'Conexões inseguras', 'hy_up': 'Upload (Mbps)', 'hy_down': 'Download (Mbps)',
            'user': 'Usuario', 'password': 'Contraseña', 'udp_ports': 'Puertos UDP', 'close': 'Cerrar', 'save': 'Guardar'
        };
        // Tradução em tempo real caso já exista linguagem ativa
        if(typeof window.selectAppLang === 'function') window.selectAppLang(localStorage.getItem('app_language') || 'pt');
    }
}, 100);

// =================================================================================
// LÓGICA DE EXIBIÇÃO DE CAMPOS (MÁGICA DOS MODOS)
// =================================================================================
function updateConnModalFields() {
    const mode = document.getElementById('cm_mode').value;
    
    // Oculta todos
    document.querySelectorAll('.dyn-field').forEach(el => {
        el.classList.add('hidden');
    });

    // Exibe apenas os que tem o modo atual no atributo data-modes
    document.querySelectorAll('.dyn-field').forEach(el => {
        const modes = el.getAttribute('data-modes');
        if (modes && modes.split(',').includes(mode)) {
            el.classList.remove('hidden');
        }
    });

    // O Hysteria não tem campo de "Usuario" no seu vídeo, apenas "Contraseña".
    if (mode === 'HYSTERIA') {
        document.getElementById('cm_user_container').classList.add('hidden');
    } else {
        document.getElementById('cm_user_container').classList.remove('hidden');
    }
}

// =================================================================================
// PREVIEW E UPLOAD DE ÍCONE
// =================================================================================
function previewIcon() {
    const url = document.getElementById('cm_icon').value;
    if (url) {
        window.open(url, '_blank');
    } else {
        alert("Ningún link de ícone fornecido.");
    }
}

function convertImageToBase64(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Limite de tamanho de arquivo (Ex: 100kb para não quebrar o banco)
    if (file.size > 102400) {
        alert("Imagem muito grande! Use uma imagem menor que 100KB ou insira um link direto.");
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('cm_icon').value = e.target.result;
    };
    reader.readAsDataURL(file);
}

// =================================================================================
// ABRIR, FECHAR E CARREGAR DADOS (INTEGRAÇÃO COM HOME-CONFIG)
// =================================================================================
window.initConfigModal = function(id = null) {
    // Copia as opções de categorias do filtro da tela de trás para o modal
    const mainSelect = document.getElementById('category-select');
    const modalSelect = document.getElementById('cm_category');
    modalSelect.innerHTML = '';
    
    let hasRealCategories = false;
    if (mainSelect) {
        Array.from(mainSelect.options).forEach(opt => {
            if (opt.value && opt.value !== 'null') {
                modalSelect.add(new Option(opt.text, opt.value));
                hasRealCategories = true;
            }
        });
    }

    // PROTEÇÃO SOLICITADA: Se não houver categorias no banco, não deixa abrir!
    if (!hasRealCategories) {
        alert("Erro de Proteção: Você precisa criar pelo menos UMA categoria antes de adicionar configurações!");
        window.location.href = '/categorias'; // Redireciona para tela de categorias
        return;
    }

    const t = window.globalTranslations[localStorage.getItem('app_language') || 'pt'];
    const titleEl = document.getElementById('cm-modal-title');
    
    if (id) {
        titleEl.innerText = t['edit_config'] || 'Editar Configuración';
        // Aqui você faz o fetch na sua API para puxar os dados do ID e preencher os campos.
        // fetch(`/api/config/${id}`).then(r=>r.json()).then(data => populateModal(data));
        console.log("Editando ID: " + id);
        document.getElementById('cm_id').value = id;
    } else {
        titleEl.innerText = t['new_config'] || 'Nueva Configuración';
        document.getElementById('cm_id').value = '';
        // Limpa os campos
        document.querySelectorAll('.cm-input, .cm-textarea').forEach(el => el.value = '');
        document.getElementById('cm_sorter').value = '1';
        document.getElementById('cm_mode').value = 'SSH_DIRECT';
        document.getElementById('cm_dns1').value = '8.8.8.8';
        document.getElementById('cm_dns2').value = '8.8.4.4';
        document.getElementById('cm_udp_ports').value = '7300';
    }

    updateConnModalFields();
    
    // Abre o Modal com animação flutuante
    document.getElementById('connectionModal').classList.add('show');
};

window.closeConfigModal = function() {
    document.getElementById('connectionModal').classList.remove('show');
};

// =================================================================================
// SALVAR E VALIDAR PROTEÇÕES (ZOD SCHEMA COMPLIANT)
// =================================================================================
window.saveConfigData = function() {
    const mode = document.getElementById('cm_mode').value;
    const name = document.getElementById('cm_name').value.trim();
    const catId = document.getElementById('cm_category').value;
    const server = document.getElementById('cm_server_host').value.trim();
    const proxy = document.getElementById('cm_proxy_host').value.trim();

    // 1. PROTEÇÃO DE NOME E CATEGORIA
    if (!name) return alert("Erro: O Nombre da configuração é obrigatório!");
    if (!catId) return alert("Erro de Proteção: Categoría é obrigatória!");

    // 2. PROTEÇÃO DE SERVIDOR E PROXY BASEADO NO MODO
    if (['SSH_DIRECT', 'SSH_PROXY', 'SSL_DIRECT', 'SSL_PROXY', 'HYSTERIA'].includes(mode)) {
        if (!server) return alert("Erro: O Endereço do Servidor é obrigatório para este modo.");
    }
    if (['SSH_PROXY', 'SSL_PROXY'].includes(mode)) {
        if (!proxy) return alert("Erro: O Endereço do Proxy é obrigatório para este modo.");
    }
    if (mode === 'SSH_DNSTT') {
        if (!document.getElementById('cm_dnstt_ns').value.trim()) return alert("Erro: Nombre do servidor NS obrigatório.");
    }

    // Montando a estrutura exata exigida pelo Backend (AppConfigSchema / getDateCreateAppConfig)
    const payload = {
        name: name,
        description: document.getElementById('cm_desc').value.trim(),
        category_id: parseInt(catId),
        sorter: parseInt(document.getElementById('cm_sorter').value) || 1,
        icon: document.getElementById('cm_icon').value.trim(),
        url_check_user: document.getElementById('cm_url_check').value.trim(),
        mode: mode,
        status: 'ACTIVE',
        
        // Objetos Aninhados
        auth: {
            username: document.getElementById('cm_auth_user').value.trim() || null,
            password: document.getElementById('cm_auth_pass').value.trim() || null,
            v2ray_uuid: document.getElementById('cm_v2ray_uuid').value.trim() || null
        },
        config_payload: {
            payload: document.getElementById('cm_payload').value.trim() || null,
            sni: document.getElementById('cm_sni').value.trim() || null
        },
        config_v2ray: document.getElementById('cm_v2ray').value.trim() || null,
        dns_server: {
            dns1: document.getElementById('cm_dns1').value.trim() || null,
            dns2: document.getElementById('cm_dns2').value.trim() || null
        },
        proxy: {
            host: proxy || null,
            port: parseInt(document.getElementById('cm_proxy_port').value) || null
        },
        server: {
            host: server || null,
            port: parseInt(document.getElementById('cm_server_port').value) || null
        },

        // Campos Avulsos
        dnstt_key: document.getElementById('cm_dnstt_key').value.trim() || null,
        dnstt_name_server: document.getElementById('cm_dnstt_ns').value.trim() || null,
        dnstt_server: document.getElementById('cm_dnstt_server').value.trim() || null,
        tls_version: document.getElementById('cm_tls').value || null,
        
        // Hysteria Specs
        hy_obfs: document.getElementById('cm_hy_obfs').value.trim() || null,
        hy_insecure: document.getElementById('cm_hy_insecure').value === '1',
        hy_port: document.getElementById('cm_server_port').value.trim() || "13375",
        hy_up_mbps: parseInt(document.getElementById('cm_hy_up').value) || 100,
        hy_down_mbps: parseInt(document.getElementById('cm_hy_down').value) || 150,
        hy_version: parseInt(document.getElementById('cm_hy_version').value) || 1,

        // Tratamento de Puertos UDP (Quebra a string "7100, 7200" em Array numérico)
        udp_ports: document.getElementById('cm_udp_ports').value.split(',')
                    .map(s => parseInt(s.trim()))
                    .filter(n => !isNaN(n))
    };

    const id = document.getElementById('cm_id').value;
    const url = id ? '/api/config/' + id : '/api/config';
    const method = id ? 'PUT' : 'POST';

    const btn = document.getElementById('cm_btn_save');
    const originalText = btn.innerText;
    btn.innerHTML = '<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
    btn.disabled = true;

    console.log("ENVIANDO PARA A API:", payload);

    // Substitua este bloco comentado pelo seu Fetch real
    /*
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(res => {
        if(res.ok) location.reload();
        else alert("Error al salvar configuração.");
    });
    */

    // Simulação visual de salvamento concluído
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        closeConfigModal();
        alert("Configuración pronta e validada. Para funcionar no backend, remova os comentários da requisição 'fetch' no arquivo.");
        // location.reload(); // Recarrega a tela para listar
    }, 1000);
};

// Inicializa a visibilidade padrão na tela
document.addEventListener('DOMContentLoaded', () => {
    updateConnModalFields();
});
</script>