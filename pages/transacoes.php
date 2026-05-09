<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Histórico de Transacciones do Usuario (Trem Bala)
 * @description Exibe transações do usuário logado, com polling em tempo real e QR Code.
 * =======================================================================================
 */

// FORÇA O NAVEGADOR A NÃO FAZER CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

// Caminho dos Bancos de Dados JSON
$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbTransacoes = __DIR__ . '/../db/transacoes.json';

clearstatcache(true, $dbUsuarios);
clearstatcache(true, $dbTransacoes);

// ----------------------------------------------------------------------
// 1. CARREGA DADOS DO USUÁRIO LOGADO (Lógica do Perfil para UUID)
// ----------------------------------------------------------------------
$userData = [];
$userFound = false;

if (file_exists($dbUsuarios)) {
    $usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
    foreach ($usuarios as $u) {
        if (strtolower($u['email']) === strtolower($sessionEmail)) {
            $userData = $u;
            $userFound = true;
            break;
        }
    }
}

// Fallback caso não ache (Segurança)
if (!$userFound) {
    $userData = [
        'username' => $_SESSION['username'] ?? 'Usuario',
        'email' => $sessionEmail,
        'uuid' => $_SESSION['user_id'] ?? '---'
    ];
}

$userUuid = $userData['uuid'] ?? '---';
$userName = $userData['username'] ?? 'Usuario';

// ----------------------------------------------------------------------
// 2. PROCESSAMENTO AJAX (API INTERNA TREM BALA)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    if ($action === 'fetch_my_transactions') {
        $transacoesDb = file_exists($dbTransacoes) ? (json_decode(file_get_contents($dbTransacoes), true) ?: []) : [];
        $minhasTransacoes = [];
        
        foreach ($transacoesDb as $txn) {
            if (strtolower($txn['user_email']) === strtolower($sessionEmail)) {
                $minhasTransacoes[] = $txn;
            }
        }
        
        echo json_encode(['success' => true, 'transacoes' => $minhasTransacoes]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Ação desconhecida.']); exit;
}

// Busca inicial para injetar no JS (Evita tela em branco no load)
$transacoesIniciaisDb = file_exists($dbTransacoes) ? (json_decode(file_get_contents($dbTransacoes), true) ?: []) : [];
$minhasTransacoesIniciais = [];
foreach ($transacoesIniciaisDb as $txn) {
    if (strtolower($txn['user_email']) === strtolower($sessionEmail)) {
        $minhasTransacoesIniciais[] = $txn;
    }
}
$jsInitialData = json_encode($minhasTransacoesIniciais);

$pageTitle = 'Transacciones';
ob_start();
?>

<!-- Importação SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - TRANSAÇÕES DO USUÁRIO
   ========================================================================== */
.txn-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --icon-bg: #f3f4f6; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warning: #d97706;
    padding: 16px; max-width: 800px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; height: calc(100vh - 80px); /* Para rolagem interna */
}

:root.dark .txn-wrapper, .dark .txn-wrapper, body.dark .txn-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #121214; --icon-bg: rgba(255, 255, 255, 0.05); --warning: #f59e0b;
}

.txn-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; }

/* Cabeçalho Fixo */
.txn-header-section { flex-shrink: 0; }
.txn-title-main { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 0; animation: slideDown 0.4s ease-out; }

@keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

/* Box de Estatísticas (Stats) */
.stats-box {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px;
    padding: 16px; display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px;
    animation: slideDown 0.5s ease-out; box-shadow: 0 10px 30px rgba(0,0,0,0.02);
}
.dark .stats-box { box-shadow: 0 10px 30px rgba(0,0,0,0.3); }

.stat-pill {
    background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 50px;
    padding: 8px 16px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-main);
    flex: 1; min-width: 130px; justify-content: center;
}
.stat-pill svg { width: 16px; }
.stat-total svg { color: var(--text-muted); }
.stat-pendente svg { color: var(--warning); }
.stat-aprovada svg { color: var(--success); }
.stat-cancelada { flex: 100%; justify-content: center; } /* Força ir pra linha de baixo se precisar, igual print */
.stat-cancelada svg { color: var(--danger); }

/* Subtítulo */
.txn-subtitle { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 6px 0; }
.txn-desc { font-size: 0.9rem; color: var(--text-muted); margin: 0 0 20px 0; font-weight: 500; }

/* Lista Rolável de Cards */
.txn-list-container {
    flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 20px;
    padding-bottom: 40px; scrollbar-width: none; /* Oculta scroll nativo */
}
.txn-list-container::-webkit-scrollbar { display: none; }

/* Card de Transacción */
.txn-card {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px;
    padding: 20px; display: flex; flex-direction: column; gap: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.02); animation: slideUp 0.5s ease-out forwards;
}
.dark .txn-card { box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

/* Topo do Card */
.tc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.tc-id-wrap { display: flex; flex-direction: column; gap: 4px; overflow: hidden; }
.tc-title { font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0; word-break: break-all; line-height: 1.3;}
.tc-userid { font-size: 0.75rem; font-weight: 600; color: var(--text-subtle); }

/* Badges Estado */
.badge-status { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; flex-shrink: 0; }
.b-pendente { background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); }
.b-pago { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
.b-cancelado { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

/* Tags PIX e Preço */
.tc-tags { display: flex; gap: 8px; margin-top: 4px; }
.tag-pix { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; }
.tag-price { background: var(--inner-bg); color: var(--text-main); border: 1px solid var(--card-border); padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; font-family: 'Space Grotesk', monospace;}

/* Datas */
.tc-dates { display: flex; flex-direction: column; gap: 12px; margin-top: 4px; }
.date-row { display: flex; flex-direction: column; gap: 2px; }
.date-label { font-size: 0.65rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 0.5px; }
.date-val { font-size: 0.85rem; font-weight: 500; color: var(--text-main); }

/* Descripción Interna */
.tc-desc-box { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 12px 16px; font-size: 0.8rem; font-weight: 500; color: var(--text-muted); display: flex; align-items: center; }

/* Botões Ação */
.tc-actions { display: flex; gap: 10px; margin-top: 4px; }
.btn-action {
    flex: 1; background: transparent; border: 1px solid var(--card-border); border-radius: 14px;
    padding: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-main);
    cursor: pointer; transition: all 0.15s;
}
.btn-action:active { transform: scale(0.92); background: var(--inner-bg); }
.btn-action svg { width: 18px; }

/* Modal SweetAlert QR Code */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 20px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.1rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin: 0 !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-top: 4px !important; text-align: left !important;}
.swal-close-btn { position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-muted); cursor: pointer; }
.qr-container { margin-top: 20px; }
.qr-header-box { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 12px; margin-bottom: 16px; text-align: left; }
.qr-header-box span { display: block; }
.qr-h-title { font-size: 0.8rem; font-weight: 800; color: var(--text-main); word-break: break-all;}
.qr-h-sub { font-size: 0.7rem; font-weight: 600; color: var(--text-subtle); margin-top: 2px; }
.qr-img-wrap { background: #ffffff; padding: 16px; border-radius: 16px; display: inline-block; width: 100%; border: 1px solid var(--card-border); }
.qr-img-wrap img { width: 100%; max-width: 250px; height: auto; display: block; margin: 0 auto; }

.swal2-actions { width: 100% !important; margin-top: 16px !important;}
.swal-btn-confirm { width: 100% !important; background: var(--inner-bg) !important; border: 1px solid var(--card-border) !important; color: var(--text-main) !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; font-size: 0.95rem !important; cursor: pointer !important; transition: transform 0.15s !important; font-family: 'Manrope', sans-serif !important; display: flex !important; align-items: center !important; justify-content: center !important; gap:8px !important; margin:0 !important;}
.swal-btn-confirm:active { transform: scale(0.96) !important; background: var(--card-bg) !important;}

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; width: 320px; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.dark .toast { box-shadow: 0 10px 30px rgba(0,0,0,0.6); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; }
.toast.success .toast-icon { background: #10b981; }
.toast-msg { font-size: 0.95rem; font-weight: 600; line-height: 1.4; color: var(--text-main); }
</style>

<div id="toast-container"></div>

<div class="txn-wrapper">
    
    <div class="txn-header-section">
        <h1 class="txn-title-main" data-i18n="transactions_title_main">Transacciones</h1>
        
        <div class="stats-box">
            <div class="stat-pill stat-total">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span id="st-total">0 transações</span>
            </div>
            <div class="stat-pill stat-pendente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span id="st-pendentes">0 pendentes</span>
            </div>
            <div class="stat-pill stat-aprovada">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span id="st-aprovadas">0 aprovadas</span>
            </div>
            <div class="stat-pill stat-cancelada">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span id="st-canceladas">0 canceladas</span>
            </div>
        </div>

        <h2 class="txn-subtitle" data-i18n="history_subtitle">Histórico de transações</h2>
        <p class="txn-desc" data-i18n="history_desc">Acompanhe pagamentos, status e códigos de cobrança.</p>
    </div>

    <!-- Lista com Rolagem -->
    <div class="txn-list-container" id="txn-list">
        <!-- JS Renderiza Aqui -->
        <p style="text-align:center; color:var(--text-muted); margin-top:20px; font-weight:600;" data-i18n="loading">Cargando...</p>
    </div>

</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
const langDict = {
    'pt': {
        'transactions_title_main': 'Transacciones', 'history_subtitle': 'Histórico de transações', 'history_desc': 'Acompanhe pagamentos, status e códigos de cobrança.',
        'loading': 'Buscando transações...', 'no_transactions': 'Ningúna transação encontrada.',
        'status_pendente': 'Pendiente', 'status_pago': 'Aprovada', 'status_cancelado': 'Cancelada',
        'lbl_created': 'CRIADO EM', 'lbl_expires': 'EXPIRA EL', 'desc_payment': 'PAGAMENTO DO ACESSO',
        'qr_modal_title': 'QRCode do pagamento', 'qr_modal_desc': 'Visualize e copie o código deste pagamento.', 'copy_code': 'Copiar código',
        'toast_copied': 'Código PIX copiado con éxito!',
        'st_total': '{n} transações', 'st_pendente': '{n} pendentes', 'st_aprovada': '{n} aprovadas', 'st_cancelada': '{n} canceladas'
    },
    'en': {
        'transactions_title_main': 'Transactions', 'history_subtitle': 'Transaction History', 'history_desc': 'Track payments, status, and billing codes.',
        'loading': 'Loading transactions...', 'no_transactions': 'No transactions found.',
        'status_pendente': 'Pending', 'status_pago': 'Approved', 'status_cancelado': 'Canceled',
        'lbl_created': 'CREATED AT', 'lbl_expires': 'EXPIRES AT', 'desc_payment': 'ACCESS PAYMENT',
        'qr_modal_title': 'Payment QRCode', 'qr_modal_desc': 'View and copy the code for this payment.', 'copy_code': 'Copy code',
        'toast_copied': 'PIX Code copied successfully!',
        'st_total': '{n} transactions', 'st_pendente': '{n} pending', 'st_aprovada': '{n} approved', 'st_cancelada': '{n} canceled'
    },
    'es': {
        'transactions_title_main': 'Transacciones', 'history_subtitle': 'Historial de transacciones', 'history_desc': 'Realice un seguimiento de pagos, estados y códigos.',
        'loading': 'Cargando transacciones...', 'no_transactions': 'No se encontraron transacciones.',
        'status_pendente': 'Pendiente', 'status_pago': 'Aprobada', 'status_cancelado': 'Cancelada',
        'lbl_created': 'CREADO EN', 'lbl_expires': 'EXPIRA EN', 'desc_payment': 'PAGO DE ACCESO',
        'qr_modal_title': 'Código QR de pago', 'qr_modal_desc': 'Ver y copiar el código de este pago.', 'copy_code': 'Copiar código',
        'toast_copied': '¡Código PIX copiado con éxito!',
        'st_total': '{n} transacciones', 'st_pendente': '{n} pendientes', 'st_aprovada': '{n} aprobadas', 'st_cancelada': '{n} canceladas'
    }
};

const userUuid = '$userUuid';
const userName = '$userName';
let currentTxns = $jsInitialData; // Dados iniciais injetados pelo PHP

function getMsg(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    return langDict[lang] && langDict[lang][key] ? langDict[lang][key] : (langDict['pt'][key] || key);
}

function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (getMsg(key) !== key) el.innerHTML = getMsg(key);
    });
    renderTransactions(currentTxns); // Re-renderiza para aplicar idiomas nos cards
}

const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLang) originalSelectLang(langCode); applyI18n(); };

function showToast(msgKey) {
    const container = document.getElementById('toast-container'); const toast = document.createElement('div'); toast.className = `toast success`;
    const icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>';
    toast.innerHTML = `<div class="toast-icon">\${icon}</div><div class="toast-msg">\${getMsg(msgKey)}</div>`;
    container.appendChild(toast); requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 3000);
}

function renderTransactions(data) {
    const listEl = document.getElementById('txn-list');
    
    // Calcula Estatísticas
    let tTotal = data.length, tPend = 0, tAprov = 0, tCanc = 0;
    
    if (tTotal === 0) {
        listEl.innerHTML = `<p style="text-align:center; color:var(--text-muted); margin-top:40px; font-weight:600;">\${getMsg('no_transactions')}</p>`;
    } else {
        let html = '';
        // Inverte para mostrar as mais novas primeiro
        [...data].forEach(txn => {
            if(txn.status === 'pendente') tPend++;
            if(txn.status === 'pago') tAprov++;
            if(txn.status === 'cancelado') tCanc++;

            const stClass = txn.status === 'pago' ? 'b-pago' : (txn.status === 'cancelado' ? 'b-cancelado' : 'b-pendente');
            const stText = getMsg('status_' + txn.status);
            
            // Datas formatadas
            const dtCreated = new Date(txn.created_at * 1000);
            const strCreated = dtCreated.toLocaleDateString('pt-BR') + ', ' + dtCreated.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
            
            // Simula expiração (+24h)
            const dtExpires = new Date((txn.created_at + 86400) * 1000);
            const strExpires = dtExpires.toLocaleDateString('pt-BR') + ', ' + dtExpires.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});

            const priceStr = parseFloat(txn.price).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            html += `
                <div class="txn-card">
                    <div class="tc-header">
                        <div class="tc-id-wrap">
                            <h3 class="tc-title">Pagamento #\${txn.id}</h3>
                            <span class="tc-userid">User ID: \${userUuid}</span>
                        </div>
                        <div class="badge-status \${stClass}">\${stText}</div>
                    </div>
                    
                    <div class="tc-tags">
                        <span class="tag-pix">PIX</span>
                        <span class="tag-price">R$ \${priceStr}</span>
                    </div>
                    
                    <div class="tc-dates">
                        <div class="date-row">
                            <span class="date-label">\${getMsg('lbl_created')}</span>
                            <span class="date-val">\${strCreated}</span>
                        </div>
                        \${txn.status === 'pendente' ? `
                        <div class="date-row">
                            <span class="date-label">\${getMsg('lbl_expires')}</span>
                            <span class="date-val">\${strExpires}</span>
                        </div>` : ''}
                    </div>
                    
                    <div class="tc-desc-box">
                        \${getMsg('desc_payment')} \${userName} | \${txn.plan_name}
                    </div>
                    
                    <div class="tc-actions">
                        <button class="btn-action" onclick="openQrModal('\${txn.id}')" title="QR Code">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        </button>
                        <button class="btn-action" onclick="copyCode('\${txn.id}')" title="Copiar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </div>
                </div>
            `;
        });
        listEl.innerHTML = html;
    }

    // Atualiza Badges Superiores
    document.getElementById('st-total').innerText = getMsg('st_total').replace('{n}', tTotal);
    document.getElementById('st-pendentes').innerText = getMsg('st_pendente').replace('{n}', tPend);
    document.getElementById('st-aprovadas').innerText = getMsg('st_aprovada').replace('{n}', tAprov);
    document.getElementById('st-canceladas').innerText = getMsg('st_cancelada').replace('{n}', tCanc);
}

// Modal Realista de QR Code (Copia design da print)
window.openQrModal = function(txnId) {
    const isDark = document.documentElement.classList.contains('dark');
    
    // Gera um QR code visual de verdade usando a API do qrserver baseado no txnId
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=PIX_CODE_SIMULATION_\${txnId}`;

    Swal.fire({
        html: `
            <div style="position:relative;">
                <button class="swal-close-btn" onclick="Swal.close()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <h2 class="swal-title-custom">\${getMsg('qr_modal_title')}</h2>
                <p class="swal-desc-custom">\${getMsg('qr_modal_desc')}</p>
                
                <div class="qr-container">
                    <div class="qr-header-box">
                        <span class="qr-h-title">Pagamento #\${txnId}</span>
                        <span class="qr-h-sub">PIX</span>
                    </div>
                    <div class="qr-img-wrap">
                        <img src="\${qrUrl}" alt="QR Code PIX">
                    </div>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: false,
        confirmButtonText: `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> 
            \${getMsg('copy_code')}
        `
    }).then((result) => {
        if (result.isConfirmed) {
            copyCode(txnId);
        }
    });
};

window.copyCode = function(txnId) {
    // Simula a cópia do código "Copia e Cola" do PIX
    const mockPixCode = `00020101021126580014br.gov.bcb.pix0136\${txnId}5204000053039865802BR5913DTunnel Admin6009Sao Paulo62070503***6304`;
    navigator.clipboard.writeText(mockPixCode).then(() => {
        showToast('toast_copied');
    });
};

// ==========================================
// POLLING (TREM BALA) - Atualiza em tempo real
// ==========================================
function fetchTransactions() {
    fetch('?action=fetch_my_transactions', { method: 'POST', body: JSON.stringify({}) })
    .then(r => r.json()).then(res => {
        if (res.success) {
            // Verifica de forma simples se houve mudança para não re-renderizar à toa
            const newDataStr = JSON.stringify(res.transacoes);
            const oldDataStr = JSON.stringify(currentTxns);
            if (newDataStr !== oldDataStr) {
                currentTxns = res.transacoes;
                renderTransactions(currentTxns);
            }
        }
    }).catch(()=>{}); // Ignora erro de rede silenciosamente
}

// Inicia Render com dados Iniciais do PHP e seta o Polling
document.addEventListener('DOMContentLoaded', () => {
    applyI18n(); // Renderiza a primeira vez já traduzindo
    setInterval(fetchTransactions, 3000); // Checa a cada 3 segundos
});

</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else if (file_exists(__DIR__ . '/layout.php')) { include __DIR__ . '/layout.php'; } 
else { echo $pageContent . $extraJs; }
?>