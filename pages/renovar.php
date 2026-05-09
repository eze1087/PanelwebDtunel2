<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Página de Renovación, Transacciones e Plans Premium
 * @description Gestão de assinaturas, cupons de desconto, ativação remota e transações.
 * =======================================================================================
 */

// FORÇA O NAVEGADOR A NÃO FAZER CACHE (Resolve o problema de não atualizar na hospedagem)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

// Caminho dos Bancos de Dados JSON
$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbPlans     = __DIR__ . '/../db/planos.json';
$dbCupons     = __DIR__ . '/../db/cupons.json';
$dbTransacoes = __DIR__ . '/../db/transacoes.json';

clearstatcache(true, $dbUsuarios);
clearstatcache(true, $dbPlans);
clearstatcache(true, $dbCupons);
clearstatcache(true, $dbTransacoes);

// Garante que os arquivos existam
foreach ([$dbPlans, $dbCupons, $dbTransacoes] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        file_put_contents($file, json_encode([]));
        chmod($file, 0644);
    }
}

// ----------------------------------------------------------------------
// 1. CARREGA DADOS DO USUÁRIO LOGADO E VERIFICA ADMIN
// ----------------------------------------------------------------------
$userData = [];
$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
foreach ($usuarios as $u) {
    if (strtolower($u['email']) === strtolower($sessionEmail)) {
        $userData = $u; break;
    }
}

$isAdmin = (($userData['role'] ?? 'user') === 'admin' || strtolower($sessionEmail) === 'elnene.admin@gmail.com');

// ----------------------------------------------------------------------
// 2. CONFIGURAÇÃO BASE DE PLANOS
// ----------------------------------------------------------------------
$planosBase = [
    'mensal'     => ['id' => 'mensal', 'nome' => 'Plan Mensual', 'meses' => '01 MES', 'preco' => 5.00, 'dias_add' => 30],
    'trimestral' => ['id' => 'trimestral', 'nome' => 'Plan Trimestral', 'meses' => '03 MESES', 'preco' => 11.90, 'dias_add' => 90],
    'anual'      => ['id' => 'anual', 'nome' => 'Plan Anual', 'meses' => '12 MESES', 'preco' => 30.00, 'dias_add' => 365],
    'vitalicio'  => ['id' => 'vitalicio', 'nome' => 'Plan Vitalicio', 'meses' => 'ACCESO ILIMITADO', 'preco' => 50.00, 'dias_add' => 36500]
];

$planosActivosDb = json_decode(file_get_contents($dbPlans), true) ?: [];
foreach ($planosBase as $id => &$plano) {
    $plano['ativo'] = isset($planosActivosDb[$id]) ? $planosActivosDb[$id] : true;
}
unset($plano);

$cuponsDb = json_decode(file_get_contents($dbCupons), true) ?: [];
$transacoesDb = json_decode(file_get_contents($dbTransacoes), true) ?: [];

// VERIFICA SE O USUÁRIO TEM TRANSAÇÃO PENDENTE (Para carregar na inicialização)
$transacaoPendiente = null;
foreach ($transacoesDb as $txn) {
    if ($txn['user_email'] === $userData['email'] && $txn['status'] === 'pendente') {
        $transacaoPendiente = $txn;
        break; // Apenas uma pendente ativa por vez
    }
}

// ----------------------------------------------------------------------
// 3. PROCESSAMENTO AJAX (API INTERNA PARA LÓGICAS)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    // ==========================================
    // LÓGICA DE TEMPO REAL (POLLING)
    // ==========================================
    if ($action === 'check_pending_status') {
        $hasPending = false;
        foreach ($transacoesDb as $txn) {
            if ($txn['user_email'] === $userData['email'] && $txn['status'] === 'pendente') {
                $hasPending = true; break;
            }
        }
        echo json_encode(['pending' => $hasPending]); exit;
    }

    // ==========================================
    // AÇÕES DO USUÁRIO
    // ==========================================
    if ($action === 'apply_coupon') {
        $codigo = strtoupper(trim($input['codigo'] ?? ''));
        $planoAlvo = $input['plano'] ?? ''; 
        $cupomValido = null;
        foreach ($cuponsDb as $c) {
            if ($c['codigo'] === $codigo && ($c['plano'] === 'todos' || $c['plano'] === $planoAlvo)) {
                $cupomValido = $c; break;
            }
        }
        if ($cupomValido) echo json_encode(['success' => true, 'desconto' => $cupomValido['valor']]);
        else echo json_encode(['success' => false, 'error' => 'Cupón inválido ou não aplicável a este plano.']);
        exit;
    }

    if ($action === 'request_renewal') {
        $planId = $input['plan_id'] ?? '';
        $finalPrice = floatval($input['final_price'] ?? 0);
        $cupomUsado = $input['cupom'] ?? '';

        // Bloqueio no backend: se já tem pendente, recusa nova
        $temPendiente = false;
        foreach ($transacoesDb as $txn) {
            if ($txn['user_email'] === $userData['email'] && $txn['status'] === 'pendente') { $temPendiente = true; break; }
        }
        if($temPendiente) {
            echo json_encode(['success' => false, 'error' => 'Ya tenés una transacción pendiente.']); exit;
        }

        if (!isset($planosBase[$planId])) {
            echo json_encode(['success' => false, 'error' => 'Plan inválido.']); exit;
        }

        $novaTransacao = [
            'id' => uniqid('txn_'),
            'user_email' => $userData['email'] ?? 'desconhecido',
            'user_name' => $userData['username'] ?? 'Usuario',
            'avatar_url' => $userData['avatar_url'] ?? '',
            'plan_id' => $planId,
            'plan_name' => $planosBase[$planId]['nome'],
            'price' => $finalPrice,
            'cupom' => $cupomUsado,
            'status' => 'pendente',
            'created_at' => time()
        ];

        array_unshift($transacoesDb, $novaTransacao);
        file_put_contents($dbTransacoes, json_encode($transacoesDb, JSON_PRETTY_PRINT));
        
        // Notificación al admin por email
        $adminEmail = 'elnene.admin@gmail.com';
        $adminEmailFile = __DIR__ . '/../db/.admin_config.json';
        if (file_exists($adminEmailFile)) {
            $adminCfg = json_decode(file_get_contents($adminEmailFile), true);
            if (!empty($adminCfg['notification_email'])) {
                $adminEmail = $adminCfg['notification_email'];
            }
        }
        $subject = '🔔 Nueva solicitud de renovación - ' . ($novaTransacao['user_name'] ?? 'Usuario');
        $body = "Nueva solicitud de renovación en By Elnene Panel WEB2:\n\n"
              . "👤 Usuario: " . ($novaTransacao['user_name'] ?? '') . "\n"
              . "📧 Email: " . ($novaTransacao['user_email'] ?? '') . "\n"
              . "📦 Plan: " . ($novaTransacao['plan_name'] ?? '') . "\n"
              . "💰 Valor: $" . number_format($novaTransacao['price'] ?? 0, 2) . "\n"
              . "🕐 Fecha: " . date('d/m/Y H:i') . "\n\n"
              . "Ingresá al panel para aprobar o rechazar.\n"
              . "WA: 3455236886 | TG: @El_NeNe_Sando";
        $headers = "From: noreply@dtpanel.local\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($adminEmail, $subject, $body, $headers);
        
        echo json_encode(['success' => true, 'transaction' => $novaTransacao]); exit;
    }

    // ==========================================
    // AÇÕES DO ADMIN
    // ==========================================
    if ($isAdmin) {
        if ($action === 'toggle_plan') {
            $planId = $input['plan_id'] ?? ''; $isActive = $input['active'] ?? false;
            $planosActivosDb[$planId] = $isActive;
            file_put_contents($dbPlans, json_encode($planosActivosDb, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'create_coupon') {
            $codigo = strtoupper(trim($input['codigo'] ?? '')); $valor = floatval($input['valor'] ?? 0); $plano = $input['plano'] ?? 'todos';
            if (empty($codigo) || $valor <= 0) { echo json_encode(['success' => false, 'error' => 'Dados inválidos.']); exit; }
            foreach ($cuponsDb as $c) { if ($c['codigo'] === $codigo) { echo json_encode(['success' => false, 'error' => 'Este cupom já existe.']); exit; } }
            $cuponsDb[] = ['codigo' => $codigo, 'valor' => $valor, 'plano' => $plano, 'created_at' => time()];
            file_put_contents($dbCupons, json_encode($cuponsDb, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'delete_coupon') {
            $codigo = $input['codigo'] ?? ''; $novosCupons = []; $deletou = false;
            foreach ($cuponsDb as $c) { if ($c['codigo'] !== $codigo) { $novosCupons[] = $c; } else { $deletou = true; } }
            if ($deletou) { file_put_contents($dbCupons, json_encode($novosCupons, JSON_PRETTY_PRINT)); echo json_encode(['success' => true]); } 
            else { echo json_encode(['success' => false, 'error' => 'Cupón não encontrado.']); }
            exit;
        }
        
        if ($action === 'list_coupons') { echo json_encode(['success' => true, 'cupons' => $cuponsDb]); exit; }
        if ($action === 'list_transactions') { echo json_encode(['success' => true, 'transacoes' => $transacoesDb]); exit; }

        if ($action === 'action_transaction') {
            $txnId = $input['txn_id'] ?? ''; $txnAction = $input['txn_action'] ?? '';
            $found = false; $novasTransacoes = [];
            foreach ($transacoesDb as &$txn) {
                if ($txn['id'] === $txnId) {
                    $found = true;
                    if ($txnAction === 'delete') { continue; } 
                    else if ($txnAction === 'accept') {
                        if($txn['status'] !== 'pago') {
                            $txn['status'] = 'pago';
                            $planId = $txn['plan_id']; $diasAdd = $planosBase[$planId]['dias_add'] ?? 30;
                            foreach ($usuarios as &$u) {
                                if ($u['email'] === $txn['user_email']) {
                                    $currentExpiry = strtotime($u['expires_at'] ?? 'now');
                                    if ($currentExpiry < time()) $currentExpiry = time();
                                    $u['expires_at'] = date('Y-m-d H:i:s', $currentExpiry + ($diasAdd * 86400));
                                    file_put_contents($dbUsuarios, json_encode($usuarios, JSON_PRETTY_PRINT));
                                    break;
                                }
                            }
                        }
                    } else if ($txnAction === 'cancel') { $txn['status'] = 'cancelado'; }
                }
                $novasTransacoes[] = $txn;
            }
            if ($found) { file_put_contents($dbTransacoes, json_encode($novasTransacoes, JSON_PRETTY_PRINT)); echo json_encode(['success' => true]); } 
            else { echo json_encode(['success' => false, 'error' => 'Transacción não encontrada.']); }
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Ação desconhecida ou não autorizada.']); exit;
}

$pageTitle = 'Renovar';
ob_start();
?>

<!-- Importação SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM (INTACTOS + BANNER PENDENTE + CORREÇÕES DE RESPONSIVIDADE)
   ========================================================================== */
.renew-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --icon-bg: #f3f4f6; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warning: #d97706;
    padding: 16px; max-width: 800px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
}

:root.dark .renew-wrapper, .dark .renew-wrapper, body.dark .renew-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #121214; --icon-bg: rgba(255, 255, 255, 0.05); --warning: #f59e0b;
}

.renew-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; box-sizing: border-box; }

/* Cabeçalho e Botões do Admin */
.ren-header { margin-bottom: 24px; animation: slideDown 0.4s ease-out; }
.ren-header-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
.ren-header h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 6px 0; }
.ren-header p { font-size: 0.95rem; color: var(--text-muted); margin: 0; font-weight: 500; line-height: 1.5; }

.admin-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px;}
.btn-admin { background: var(--card-bg); border: 1px solid var(--card-border); padding: 10px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
.btn-admin:active { transform: scale(0.95); background: var(--icon-bg); }
.btn-admin.highlight { color: var(--primary); border-color: rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.05); }
.btn-admin.transactions { color: #8b5cf6; border-color: rgba(139, 92, 246, 0.3); background: rgba(139, 92, 246, 0.05); }

@keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

/* ==========================================================================
   BANNER DE PAGAMENTO PENDENTE
   ========================================================================== */
.pending-banner {
    background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 20px;
    padding: 20px; margin-bottom: 24px; display: flex; flex-direction: column; gap: 16px;
    animation: slideDown 0.4s ease-out; transition: all 0.3s;
}
.pb-top { display: flex; align-items: flex-start; gap: 12px; }
.pb-icon { width: 40px; height: 40px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: var(--warning); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pb-texts { display: flex; flex-direction: column; gap: 4px; }
.pb-title { font-size: 1.05rem; font-weight: 800; color: var(--warning); margin: 0; }
.pb-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-muted); margin: 0; line-height: 1.4; }
.pb-btn {
    width: 100%; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 14px;
    padding: 14px; font-size: 0.95rem; font-weight: 800; color: var(--warning); display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: transform 0.15s; font-family: 'Manrope', sans-serif;
}
.pb-btn:active { transform: scale(0.96); background: rgba(245, 158, 11, 0.25); }

/* Grid de Plans */
.plans-grid { display: flex; flex-direction: column; gap: 20px; padding-bottom: 40px; }
.plan-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); animation: slideUp 0.5s ease-out forwards; opacity: 0; position: relative; overflow: hidden; }
.dark .plan-card { box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.plan-card.disabled { opacity: 0.7; filter: grayscale(40%); }

/* Ajuste Responsivo no PC TOP (Impede sobreposição em telas muito pequenas) */
.pc-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
.pc-info { display: flex; flex-direction: column; gap: 6px; }
.pc-badge-wrap { display: flex; align-items: center; gap: 10px; }
.pc-badge { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px; }
.badge-disp { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
.badge-esg { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

/* Switch Toggle (iOS Style) */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--card-border); transition: .3s; border-radius: 24px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
input:checked + .slider { background-color: var(--success); }
input:checked + .slider:before { transform: translateX(20px); }

.pc-title { font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0; }
.pc-months { font-size: 0.75rem; font-weight: 800; color: var(--text-subtle); letter-spacing: 1px; display: inline-block; border: 1px solid var(--card-border); padding: 4px 10px; border-radius: 8px; margin-top: 6px;}

/* Preço e Descuento (Flex-wrap adicionado para não acavalar) */
.pc-price-box { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 20px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 16px; }
.price-final { font-size: 1.8rem; font-weight: 800; color: var(--text-main); line-height: 1; font-family: 'Space Grotesk', sans-serif;}
.price-row { display: flex; flex-direction: column; gap: 4px; }
.price-row span { font-size: 0.7rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 1px; }
.price-row strong { font-size: 1.1rem; color: var(--text-main); font-weight: 700; font-family: 'Space Grotesk', sans-serif;}
.price-row.discount strong { color: var(--success); }

/* ==========================================================================
   CAIXA DE CUPOM (Totalmente Responsiva, sem engolir o botão Aplicar!)
   ========================================================================== */
.pc-coupon-box { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px; margin-bottom: 20px; }
.cb-header { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 800; color: var(--text-main); margin-bottom: 12px; }
.cb-header svg { width: 16px; color: var(--text-muted); }

/* Aqui é onde a mágica do ajuste perfeito acontece */
.cb-input-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: stretch; }
.cb-input { 
    flex: 1 1 150px; min-width: 0; background: var(--inner-bg); border: 1px solid var(--card-border); 
    border-radius: 12px; padding: 14px 16px; font-size: 0.95rem; font-weight: 600; color: var(--text-main); 
    outline: none; transition: border 0.2s; 
}
.cb-input:focus { border-color: var(--primary); }

.btn-apply { 
    flex: 1 1 auto; flex-shrink: 0; background: transparent; border: 1px solid var(--card-border); 
    border-radius: 12px; padding: 14px 20px; font-size: 0.9rem; font-weight: 800; color: var(--text-main); 
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap;
}
.btn-apply:active { transform: scale(0.95); background: var(--icon-bg); }

.btn-renovar { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); padding: 18px; border-radius: 16px; font-size: 1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: all 0.15s; font-family: 'Manrope', sans-serif; }
.btn-renovar:not(:disabled):active { transform: scale(0.96); background: var(--icon-bg); border-color: var(--text-subtle); }
.btn-renovar:disabled { background: rgba(239, 68, 68, 0.05); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); cursor: not-allowed; }

.msg-esgotado { background: rgba(239, 68, 68, 0.05); color: var(--danger); padding: 12px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.2); text-align: left; }

/* ==========================================================================
   SWEETALERT2 STYLES & TRANSACTIONS
   ========================================================================== */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin: 0 !important; }
.swal-desc-custom { font-size: 0.95rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-top: 8px !important; text-align: left !important;}
.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 24px !important;}
.swal-btn-confirm, .swal-btn-cancel { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; font-size: 0.95rem !important; border: none !important; cursor: pointer !important; transition: transform 0.15s !important; font-family: 'Manrope', sans-serif !important; display: flex !important; align-items: center !important; justify-content: center !important; gap:8px !important;}
.swal-btn-confirm:active, .swal-btn-cancel:active { transform: scale(0.95) !important; }
.swal-btn-confirm { background: var(--primary) !important; color: #fff !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; }
.swal-btn-confirm.success { background: #10b981 !important; }
.swal-btn-confirm.warning { background: #f59e0b !important; }
.swal-btn-cancel { background: var(--inner-bg) !important; border: 1px solid var(--card-border) !important; color: var(--text-main) !important; }

.swal-form-group { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; text-align: left; }
.swal-label { font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
.swal-input, .swal-select { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 14px; border-radius: 12px; font-size: 0.95rem; font-weight: 700; outline: none; font-family: 'Manrope', sans-serif; }
.swal-input:focus, .swal-select:focus { border-color: var(--primary); }

.data-list { max-height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; margin-top: 16px; scrollbar-width: none; padding-bottom: 10px;}
.data-list::-webkit-scrollbar { display: none; }
.list-item { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 12px; text-align: left; }
.list-item-top { display: flex; justify-content: space-between; align-items: center; }
.list-user-info { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: var(--card-border); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--text-muted); font-size: 1.2rem; }
.user-details { display: flex; flex-direction: column; }
.user-name { font-size: 0.95rem; font-weight: 800; color: var(--text-main); }
.user-email { font-size: 0.75rem; color: var(--text-muted); }

.txn-details { display: flex; justify-content: space-between; align-items: flex-end; background: var(--card-bg); padding: 10px; border-radius: 10px; border: 1px solid var(--card-border); }
.txn-plan { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
.txn-price { font-size: 1.1rem; font-weight: 800; color: var(--primary); font-family: 'Space Grotesk', sans-serif;}

.status-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
.status-pendente { background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); }
.status-pago { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
.status-cancelado { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

.txn-actions { display: flex; gap: 8px; margin-top: 4px; justify-content: flex-end; }
.btn-txn { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid transparent; transition: transform 0.15s; }
.btn-txn:active { transform: scale(0.85); }
.btn-txn.accept { background: rgba(16, 185, 129, 0.1); color: var(--success); border-color: rgba(16, 185, 129, 0.2); }
.btn-txn.cancel { background: rgba(245, 158, 11, 0.1); color: var(--warning); border-color: rgba(245, 158, 11, 0.2); }
.btn-txn.delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; width: 320px; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.dark .toast { box-shadow: 0 10px 30px rgba(0,0,0,0.6); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; }
.toast.error .toast-icon { background: #ef4444; }
.toast.success .toast-icon { background: #10b981; }
.toast-msg { font-size: 0.95rem; font-weight: 600; line-height: 1.4; color: var(--text-main); }
</style>

<div id="toast-container"></div>

<div class="renew-wrapper">
    <div class="ren-header">
        <div class="ren-header-top">
            <div>
                <h1 data-i18n="renew_title">Renovar</h1>
                <p data-i18n="renew_desc">Elegí un plan, aplicá un cupón si querés y generá el pago de renovación.</p>
            </div>
        </div>

        <?php if($isAdmin): ?>
        <div class="admin-actions">
            <!-- Botão Transacciones -->
            <button class="btn-admin transactions" onclick="openTransactionsModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span data-i18n="transactions">Transacciones</span>
            </button>
            <button class="btn-admin highlight" onclick="openCreateCouponModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span data-i18n="create_coupon">Generar Cupón</span>
            </button>
            <button class="btn-admin" onclick="openHistoryModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span data-i18n="coupon_history">Cupons</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- BANNER DE PAGAMENTO PENDENTE (Controlado via JS/PHP) -->
    <div id="pending-banner" class="pending-banner" style="<?= $transacaoPendiente ? 'display:flex;' : 'display:none;' ?>">
        <div class="pb-top">
            <div class="pb-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="pb-texts">
                <h3 class="pb-title" data-i18n="pending_payment_title">Existe um pagamento pendente</h3>
                <p class="pb-desc" data-i18n="pending_payment_desc">Abra o pagamento atual antes de gerar uma nova renovação.</p>
            </div>
        </div>
        <button class="pb-btn" onclick="openPendingDetails()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.1"/></svg>
            <span data-i18n="view_pending_payment">Ver pagamento pendente</span>
        </button>
    </div>

    <div class="plans-grid">
        <?php foreach ($planosBase as $id => $plano): 
            $isActive = $plano['ativo'];
            $cardClass = $isActive ? '' : 'disabled';
            $badgeClass = $isActive ? 'badge-disp' : 'badge-esg';
            $badgeTextKey = $isActive ? 'available' : 'sold_out';
            $badgeTextDef = $isActive ? 'Disponible' : 'Plan esgotado';
        ?>
        <div class="plan-card <?= $cardClass ?>" id="card-<?= $id ?>">
            
            <div class="pc-top">
                <div class="pc-info">
                    <span class="pc-months" data-i18n="plan_<?= $id ?>_months"><?= $plano['meses'] ?></span>
                    <h2 class="pc-title" data-i18n="plan_<?= $id ?>_name"><?= $plano['nome'] ?></h2>
                </div>
                <div class="pc-badge-wrap">
                    <div class="pc-badge <?= $badgeClass ?>" id="badge-<?= $id ?>" data-i18n="<?= $badgeTextKey ?>"><?= $badgeTextDef ?></div>
                    
                    <?php if($isAdmin): ?>
                    <label class="toggle-switch">
                        <input type="checkbox" onchange="togglePlanStatus('<?= $id ?>', this.checked)" <?= $isActive ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pc-price-box">
                <div class="price-row">
                    <span data-i18n="final_value">VALOR FINAL</span>
                    <div class="price-final" id="final-price-<?= $id ?>">R$ <?= number_format($plano['preco'], 2, ',', '.') ?></div>
                </div>
                <div style="display:flex; justify-content:space-between; flex-wrap: wrap; gap: 10px;">
                    <div class="price-row">
                        <span data-i18n="base_price">PREÇO BASE</span>
                        <strong>R$ <?= number_format($plano['preco'], 2, ',', '.') ?></strong>
                    </div>
                    <div class="price-row discount" style="text-align:right;">
                        <span data-i18n="discount_applied">DESCONTO APLICADO</span>
                        <strong id="discount-val-<?= $id ?>">R$ 0,00</strong>
                    </div>
                </div>
            </div>

            <div class="pc-coupon-box">
                <div class="cb-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 12H16c-.7 2-3 3-4.5 1.5S10 9 12 8c2-.5 3 2 3 2h5.5"/><circle cx="5.5" cy="11.5" r="2.5"/></svg>
                    <span data-i18n="discount_coupon">Cupón de desconto</span>
                </div>
                <div class="cb-input-group">
                    <input type="text" class="cb-input" id="input-coupon-<?= $id ?>" placeholder="Digite um cupom" data-i18n-placeholder="type_coupon" <?= !$isActive ? 'disabled' : '' ?>>
                    <button class="btn-apply" onclick="applyCoupon('<?= $id ?>', <?= $plano['preco'] ?>)" <?= !$isActive ? 'disabled' : '' ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span data-i18n="apply">Aplicar</span>
                    </button>
                </div>
            </div>

            <?php if(!$isActive): ?>
                <div class="msg-esgotado" data-i18n="msg_sold_out">Plan agotado. Elegí otro plan para continuar.</div>
            <?php endif; ?>

            <button class="btn-renovar" onclick="confirmRenew('<?= $id ?>', <?= $plano['preco'] ?>, '<?= $plano['nome'] ?>')" <?= !$isActive ? 'disabled' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.1"/></svg>
                <span data-i18n="renew_now">Renovar agora</span>
            </button>

        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$userJsData = json_encode([
    'nome' => $userData['username'] ?? '',
    'email' => $userData['email'] ?? '',
    'criado' => date('d/m/Y', strtotime($userData['created_at'] ?? 'now')),
    'vencimento' => !empty($userData['expires_at']) ? date('d/m/Y H:i', strtotime($userData['expires_at'])) : 'Vitalicio'
]);

$pendingTxnJs = $transacaoPendiente ? json_encode($transacaoPendiente) : 'null';

$extraJs = <<<JS
<script>
const renDict = {
    'pt': {
        'renew_title': 'Renovar', 'renew_desc': 'Elegí un plan, aplicá un cupón si querés y generá el pago de renovación.',
        'create_coupon': 'Generar Cupón', 'coupon_history': 'Cupones', 'transactions': 'Transacciones', 'available': 'Disponible', 'sold_out': 'Plan esgotado',
        'final_value': 'VALOR FINAL', 'base_price': 'PREÇO BASE', 'discount_applied': 'DESCONTO APLICADO', 'discount_coupon': 'Cupón de desconto',
        'type_coupon': 'Digite um cupom de desconto', 'apply': 'Aplicar', 'msg_sold_out': 'Plan agotado. Elegí otro plan para continuar.',
        'renew_now': 'Renovar ahora', 'modal_renew_title': 'Generar pagamento de renovação', 
        'modal_renew_desc': 'Uma transação será gerada para o plano <b>{plan}</b>. O administrador será notificado. Deseja prosseguir com o valor de R$ {val}?',
        'generate_payment': 'Generar pagamento', 'cancel': 'Cancelar',
        
        // Nuevos textos do banner pendente
        'pending_payment_title': 'Existe un pago pendiente', 'pending_payment_desc': 'Abrí el pago actual antes de generar una nueva renovación.', 'view_pending_payment': 'Ver pago pendiente',
        'modal_pending_title': 'Detalles del Pedido', 'status': 'Estado da Conta', 'close': 'Cerrar', 'block_pending': 'Você já tem um pagamento pendente. Aguardá.',
        
        'plan_mensal_name': 'Plan Mensual', 'plan_mensal_months': '01 MES', 'plan_trimestral_name': 'Plan Trimestral', 'plan_trimestral_months': '03 MESES',
        'plan_anual_name': 'Plan Anual', 'plan_anual_months': '12 MESES', 'plan_vitalicio_name': 'Plan Vitalicio', 'plan_vitalicio_months': 'ACCESO ILIMITADO',
        
        'create_coupon_title': 'Crear Nuevo Cupón', 'coupon_code_lbl': 'Código do Cupón (Ex: PROMO10)', 'discount_val_lbl': 'Valor do Descuento (R$)',
        'plan_target_lbl': 'Plan Alvo', 'all_plans': 'Todos os Plans', 'create': 'Crear', 'history_title': 'Historial de Cupones', 'delete_coupon_title': 'Eliminar Cupón', 'delete_coupon_desc': 'Tem certeza que deseja apagar o cupom <b>{code}</b>?', 'delete': 'Eliminar',
        'transactions_title': 'Gestionar Transacciones', 'no_transactions': 'Ningúna transação no momento.', 'status_pendente': 'Pendiente', 'status_pago': 'Pagado', 'status_cancelado': 'Cancelado',
        'confirm_accept_title': 'Aceitar Renovación', 'confirm_accept_desc': 'Confirmar pagamento de R$ {val} para <b>{user}</b> (Plan {plan})? Os dias serão adicionados à conta.',
        'confirm_cancel_title': 'Cancelar Transacción', 'confirm_cancel_desc': 'Marcar a transação de <b>{user}</b> como Cancelada?',
        'confirm_delete_txn_title': 'Eliminar Transacción', 'confirm_delete_txn_desc': 'Tem certeza que deseja apagar o histórico dessa transação?',
        
        'toast_coupon_success': 'Cupón aplicado con éxito!', 'toast_coupon_empty': 'Ingresá un código.', 'toast_coupon_created': 'Cupón gerado con éxito!', 
        'toast_coupon_deleted': 'Cupón deletado.', 'toast_txn_created': '¡Solicitud enviada! Redirigiendo...', 'toast_txn_updated': 'Estado da transação atualizado!',
        'toast_txn_approved': '¡Tu pago fue aprobado por el Admin!'
    },
    'en': {
        'renew_title': 'Renew', 'renew_desc': 'Choose a plan, apply a coupon and generate your renewal payment.', 'create_coupon': 'Create Coupon', 'coupon_history': 'Coupons', 'transactions': 'Transactions', 'available': 'Available', 'sold_out': 'Sold out',
        'final_value': 'FINAL VALUE', 'base_price': 'BASE PRICE', 'discount_applied': 'DISCOUNT APPLIED', 'discount_coupon': 'Discount coupon', 'type_coupon': 'Enter a discount coupon', 'apply': 'Apply', 'msg_sold_out': 'Plan sold out. Choose another plan.',
        'renew_now': 'Renew now', 'modal_renew_title': 'Generate renewal payment', 'modal_renew_desc': 'A transaction will be generated for <b>{plan}</b>. Proceed with $ {val}?', 'generate_payment': 'Generate payment', 'cancel': 'Cancel',
        'pending_payment_title': 'Pending payment exists', 'pending_payment_desc': 'Open the current payment before generating a new one.', 'view_pending_payment': 'View pending payment', 'modal_pending_title': 'Order Details', 'status': 'Account Estado', 'close': 'Close', 'block_pending': 'You already have a pending payment.',
        'plan_mensal_name': 'Monthly Plan', 'plan_mensal_months': '01 MONTH', 'plan_trimestral_name': 'Quarterly Plan', 'plan_trimestral_months': '03 MONTHS', 'plan_anual_name': 'Yearly Plan', 'plan_anual_months': '12 MONTHS', 'plan_vitalicio_name': 'Lifetime Plan', 'plan_vitalicio_months': 'INFINITE ACCESS',
        'create_coupon_title': 'Create New Coupon', 'coupon_code_lbl': 'Coupon Code', 'discount_val_lbl': 'Discount Value ($)', 'plan_target_lbl': 'Target Plan', 'all_plans': 'All Plans', 'create': 'Create', 'history_title': 'Coupon History', 'delete_coupon_title': 'Delete Coupon', 'delete_coupon_desc': 'Delete coupon <b>{code}</b>?', 'delete': 'Delete',
        'transactions_title': 'Manage Transactions', 'no_transactions': 'No transactions yet.', 'status_pendente': 'Pending', 'status_pago': 'Paid', 'status_cancelado': 'Canceled',
        'confirm_accept_title': 'Accept Renewal', 'confirm_accept_desc': 'Confirm payment of $ {val} for <b>{user}</b>? Days will be added to account.', 'confirm_cancel_title': 'Cancel Transaction', 'confirm_cancel_desc': 'Mark transaction for <b>{user}</b> as Canceled?', 'confirm_delete_txn_title': 'Delete Transaction', 'confirm_delete_txn_desc': 'Delete this transaction history?',
        'toast_coupon_success': 'Coupon applied!', 'toast_coupon_empty': 'Enter code.', 'toast_coupon_created': 'Coupon generated!', 'toast_coupon_deleted': 'Coupon deleted.', 'toast_txn_created': 'Request sent! Redirecting...', 'toast_txn_updated': 'Transaction updated!', 'toast_txn_approved': 'Yay! Payment approved by Admin!'
    }
};

const userRealData = $userJsData;
let currentPendingTxn = $pendingTxnJs; // Carregado do PHP se existir
let currentDiscounts = { 'mensal': 0, 'trimestral': 0, 'anual': 0, 'vitalicio': 0 };
let activeCoupons = { 'mensal': '', 'trimestral': '', 'anual': '', 'vitalicio': '' };

function getLocalMsg(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    return renDict[lang] && renDict[lang][key] ? renDict[lang][key] : (renDict['pt'][key] || key);
}

function applyRenI18n() {
    const lang = localStorage.getItem('app_language') || 'pt';
    const dict = renDict[lang] || renDict['pt'];
    if (window.globalTranslations) {
        for (let langKey in renDict) {
            if (!window.globalTranslations[langKey]) window.globalTranslations[langKey] = {};
            Object.assign(window.globalTranslations[langKey], renDict[langKey]);
        }
    }
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) { if(el.tagName === 'INPUT') el.placeholder = dict[key]; else el.innerHTML = dict[key]; }
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (dict[key]) el.placeholder = dict[key];
    });
}

const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) { if(originalSelectLang) originalSelectLang(langCode); applyRenI18n(); };

function showToast(type, msgKeyOrText) {
    const container = document.getElementById('toast-container'); const toast = document.createElement('div'); toast.className = `toast \${type}`;
    const icon = type === 'error' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><path d="M18 6 6 18M6 6l12 12"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>';
    let text = getLocalMsg(msgKeyOrText); if(text === msgKeyOrText && renDict['pt'][msgKeyOrText] === undefined) text = msgKeyOrText; 
    toast.innerHTML = `<div class="toast-icon">\${icon}</div><div class="toast-msg">\${text}</div><div class="toast-progress"></div>`;
    container.appendChild(toast); requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
}

// ----------------------------------------------------------------------
// SISTEMA DE POLLING "TREM BALA" (Verifica status em tempo real)
// ----------------------------------------------------------------------
setInterval(() => {
    // Só faz requisição se existir uma transação pendente na tela
    if (currentPendingTxn) {
        fetch('?action=check_pending_status', { method: 'POST', body: JSON.stringify({}) })
        .then(r=>r.json()).then(res => {
            // Se o backend disser que NÃO tem mais pendente (Admin aceitou, cancelou ou excluiu)
            if (!res.pending) {
                currentPendingTxn = null; // Limpa variável
                document.getElementById('pending-banner').style.display = 'none'; // Some o banner amarelo
                showToast('success', 'toast_txn_approved'); // Aviso de sucesso!
                if(Swal.isVisible() && Swal.getTitle().textContent === getLocalMsg('modal_pending_title')) {
                    Swal.close(); // Fecha o modal de detalhes pendentes se estiver aberto
                }
            }
        }).catch(()=>{}); // ignora erros de rede silenciosamente no polling
    }
}, 3000); // Verifica a cada 3 segundos


// ----------------------------------------------------------------------
// LÓGICA DE USUÁRIO
// ----------------------------------------------------------------------
function applyCoupon(planId, basePrice) {
    const input = document.getElementById('input-coupon-' + planId); const code = input.value.trim();
    if(!code) { showToast('error', 'toast_coupon_empty'); return; }
    fetch('?action=apply_coupon', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ codigo: code, plano: planId }) })
    .then(r => r.json()).then(res => {
        if(res.success) { showToast('success', 'toast_coupon_success'); currentDiscounts[planId] = parseFloat(res.desconto); activeCoupons[planId] = code; updatePriceUI(planId, basePrice); } 
        else { showToast('error', res.error); currentDiscounts[planId] = 0; activeCoupons[planId] = ''; updatePriceUI(planId, basePrice); }
    });
}

function updatePriceUI(planId, basePrice) {
    let discount = currentDiscounts[planId]; let final = basePrice - discount; if (final < 0) final = 0;
    const fmt = (val) => val.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('discount-val-' + planId).innerText = `R$ \${fmt(discount)}`; document.getElementById('final-price-' + planId).innerText = `R$ \${fmt(final)}`;
}

function confirmRenew(planId, basePrice, planNameObj) {
    // BLOQUEIO SE HOUVER PAGAMENTO PENDENTE
    if (currentPendingTxn) {
        showToast('error', 'block_pending');
        openPendingDetails(); // Já abre o modal pra ele ver
        return;
    }

    const planName = getLocalMsg('plan_' + planId + '_name') || planNameObj;
    let finalPrice = basePrice - currentDiscounts[planId]; if (finalPrice < 0) finalPrice = 0;
    const fmtPrice = finalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const isDark = document.documentElement.classList.contains('dark');
    let desc = getLocalMsg('modal_renew_desc').replace('{plan}', planName).replace('{val}', fmtPrice);

    Swal.fire({
        html: `
            <div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,0.1);color:#3b82f6;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M7 15h.01"/></svg>
                </div>
                <h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getLocalMsg('modal_renew_title')}</h2>
            </div>
            <p class="swal-desc-custom" style="text-align:left;">\${desc}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`, buttonsStyling: false, showCancelButton: true,
        confirmButtonText: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><polyline points="9 18 15 12 9 6"/></svg> ` + getLocalMsg('generate_payment'),
        cancelButtonText: getLocalMsg('cancel')
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({title:'Procesando', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'} });
            
            fetch('?action=request_renewal', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ plan_id: planId, final_price: finalPrice, cupom: activeCoupons[planId] })
            }).then(r=>r.json()).then(res => {
                Swal.close();
                if(res.success) {
                    showToast('success', 'toast_txn_created');
                    
                    // Atualiza variável pendente e mostra banner!
                    currentPendingTxn = res.transaction;
                    document.getElementById('pending-banner').style.display = 'flex';
                    
                    let text = `¡Hola! Solicito una renovación en el panel 🚀%0A%0A👤 *Nombre:* \${userRealData.nome}%0A📧 *Email:* \${userRealData.email}%0A⏳ *Vencimiento actual:* \${userRealData.vencimento}%0A%0A📦 *Plan elegido:* \${planName}%0A💰 *Valor a pagar:* $ \${fmtPrice}`;
                    if(activeCoupons[planId]) text += `%0A🎟️ *Cupón de descuento:* \${activeCoupons[planId]}`;
                    
                    const suporteUrl = `https://wa.me/543455236886?text=\${text}`;
                    setTimeout(() => { window.open(suporteUrl, '_blank'); }, 1000);
                } else { showToast('error', res.error || 'Error al procesar'); }
            });
        }
    });
}

// Abre Modal de Detalhes da Transacción Pendiente do Usuario
function openPendingDetails() {
    if(!currentPendingTxn) return;
    const isDark = document.documentElement.classList.contains('dark');
    const fmtPrice = parseFloat(currentPendingTxn.price).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    Swal.fire({
        html: `
            <div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:20px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(245, 158, 11, 0.1);color:#f59e0b;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><path d="M7 15h.01"/></svg>
                </div>
                <h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getLocalMsg('modal_pending_title')}</h2>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:14px; text-align:left;">
                <div style="background:var(--inner-bg); border:1px solid var(--card-border); padding:16px; border-radius:16px;">
                    <span style="font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Plan Escolhido</span>
                    <div style="font-size:1.1rem; font-weight:800; color:var(--text-main); margin-top:4px;">\${currentPendingTxn.plan_name}</div>
                </div>
                
                <div style="background:var(--inner-bg); border:1px solid var(--card-border); padding:16px; border-radius:16px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Valor Final</span>
                        <div style="font-size:1.4rem; font-weight:800; color:var(--primary); font-family:'Space Grotesk', sans-serif;">R$ \${fmtPrice}</div>
                    </div>
                    \${currentPendingTxn.cupom ? `<div style="background:rgba(16,185,129,0.1); color:#10b981; padding:4px 8px; border-radius:8px; font-size:0.75rem; font-weight:800;">Cupón: \${currentPendingTxn.cupom}</div>` : ''}
                </div>
                
                <div style="background:var(--inner-bg); border:1px solid var(--card-border); padding:16px; border-radius:16px;">
                    <span style="font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">\${getLocalMsg('status')}</span>
                    <div style="font-size:1rem; font-weight:800; color:var(--warning); margin-top:4px; display:flex; align-items:center; gap:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Esperando al Administrador
                    </div>
                </div>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`, buttonsStyling: false,
        confirmButtonText: getLocalMsg('close')
    });
}

// ----------------------------------------------------------------------
// LÓGICA DE ADMIN (PLANOS, CUPONS E TRANSAÇÕES)
// ----------------------------------------------------------------------
function togglePlanStatus(planId, isActive) {
    fetch('?action=toggle_plan', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ plan_id: planId, active: isActive }) })
    .then(() => {
        const card = document.getElementById('card-' + planId); const badge = document.getElementById('badge-' + planId);
        const btn = card.querySelector('.btn-renovar'); const input = card.querySelector('.cb-input'); const btnApply = card.querySelector('.btn-apply');
        if(isActive) {
            card.classList.remove('disabled'); badge.className = 'pc-badge badge-disp'; badge.innerText = getLocalMsg('available');
            btn.disabled = false; input.disabled = false; btnApply.disabled = false;
            let msg = card.querySelector('.msg-esgotado'); if(msg) msg.remove();
        } else {
            card.classList.add('disabled'); badge.className = 'pc-badge badge-esg'; badge.innerText = getLocalMsg('sold_out');
            btn.disabled = true; input.disabled = true; btnApply.disabled = true;
            if(!card.querySelector('.msg-esgotado')) {
                const msg = document.createElement('div'); msg.className = 'msg-esgotado'; msg.innerText = getLocalMsg('msg_sold_out'); card.insertBefore(msg, btn);
            }
        }
    });
}

function openCreateCouponModal() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        html: `
            <div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(16, 185, 129, 0.1);color:#10b981;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M21.5 12H16c-.7 2-3 3-4.5 1.5S10 9 12 8c2-.5 3 2 3 2h5.5"/><circle cx="5.5" cy="11.5" r="2.5"/></svg>
                </div>
                <h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getLocalMsg('create_coupon_title')}</h2>
            </div>
            <div class="swal-form-group">
                <label class="swal-label">\${getLocalMsg('coupon_code_lbl')}</label>
                <input type="text" id="swal-coupon-code" class="swal-input" style="text-transform: uppercase;">
            </div>
            <div class="swal-form-group">
                <label class="swal-label">\${getLocalMsg('discount_val_lbl')}</label>
                <input type="number" id="swal-coupon-val" class="swal-input" step="0.01">
            </div>
            <div class="swal-form-group">
                <label class="swal-label">\${getLocalMsg('plan_target_lbl')}</label>
                <select id="swal-coupon-plan" class="swal-select">
                    <option value="todos">\${getLocalMsg('all_plans')}</option>
                    <option value="mensal">\${getLocalMsg('plan_mensal_name')}</option>
                    <option value="trimestral">\${getLocalMsg('plan_trimestral_name')}</option>
                    <option value="anual">\${getLocalMsg('plan_anual_name')}</option>
                    <option value="vitalicio">\${getLocalMsg('plan_vitalicio_name')}</option>
                </select>
            </div>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getLocalMsg('create'), cancelButtonText: getLocalMsg('cancel'),
        preConfirm: () => {
            const code = document.getElementById('swal-coupon-code').value; const val = document.getElementById('swal-coupon-val').value; const plan = document.getElementById('swal-coupon-plan').value;
            if(!code || !val) { Swal.showValidationMessage('Completá los campos obligatorios'); return false; }
            return {codigo: code, valor: val, plano: plan};
        }
    }).then((result) => {
        if(result.isConfirmed) {
            Swal.fire({title:'Procesando', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'} });
            fetch('?action=create_coupon', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(result.value) })
            .then(r=>r.json()).then(res => { if(res.success) { Swal.close(); showToast('success', 'toast_coupon_created'); } else { Swal.close(); showToast('error', res.error); } });
        }
    });
}

function openHistoryModal() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({title:'Buscando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
    fetch('?action=list_coupons', {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({})}).then(r=>r.json()).then(res => {
        if(res.success) {
            let htmlList = '';
            if(res.cupons.length === 0) htmlList = '<p style="color:var(--text-muted); text-align:center; padding: 20px;">Ningún cupom criado.</p>';
            else {
                res.cupons.reverse().forEach(c => {
                    const planName = c.plano === 'todos' ? 'Todos' : (getLocalMsg('plan_'+c.plano+'_name') || c.plano);
                    htmlList += `
                        <div class="list-item" id="hist-\${c.codigo}" style="flex-direction:row; justify-content:space-between; align-items:center; padding: 14px;">
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <span style="font-size: 1rem; font-weight: 800; color: var(--text-main); font-family: 'Space Grotesk', monospace;">\${c.codigo}</span>
                                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">R$ \${parseFloat(c.valor).toFixed(2)} • \${planName}</span>
                            </div>
                            <button class="btn-txn delete" onclick="confirmDeleteCoupon('\${c.codigo}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    `;
                });
            }
            Swal.fire({
                html: `<div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:0;">
                            <div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,0.1);color:#3b82f6;display:flex;align-items:center;justify-content:center;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getLocalMsg('history_title')}</h2>
                        </div>
                        <div class="data-list">\${htmlList}</div>`,
                showConfirmButton: false, showCloseButton: true, customClass: { popup: 'swal-modal-custom' },
                background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`
            });
        }
    });
}

function confirmDeleteCoupon(code) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        html: `
            <div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getLocalMsg('delete_coupon_title')}</h2>
            </div>
            <p class="swal-desc-custom" style="text-align:left;">\${getLocalMsg('delete_coupon_desc').replace('{code}', code)}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getLocalMsg('delete'), cancelButtonText: getLocalMsg('cancel')
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('?action=delete_coupon', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({codigo: code}) })
            .then(r=>r.json()).then(res => { if(res.success) { showToast('success', 'toast_coupon_deleted'); document.getElementById('hist-'+code).remove(); } });
        } else { openHistoryModal(); }
    });
}

function openTransactionsModal() {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({title:'Buscando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
    
    fetch('?action=list_transactions', {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({})})
    .then(r=>r.json()).then(res => {
        if(res.success) {
            let htmlList = '';
            if(res.transacoes.length === 0) htmlList = `<p style="color:var(--text-muted); text-align:center; padding: 20px;">\${getLocalMsg('no_transactions')}</p>`;
            else {
                res.transacoes.forEach(txn => {
                    const avatarContent = txn.avatar_url ? `<img src="\${txn.avatar_url}" class="user-avatar" onerror="this.outerHTML='<div class=\\'user-avatar\\'>\${txn.user_name.charAt(0).toUpperCase()}</div>'">` : `<div class="user-avatar">\${txn.user_name.charAt(0).toUpperCase()}</div>`;
                    const dt = new Date(txn.created_at * 1000).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'});
                    const stClass = 'status-' + txn.status; const stText = getLocalMsg('status_' + txn.status);
                    
                    htmlList += `
                        <div class="list-item" id="txn-\${txn.id}">
                            <div class="list-item-top">
                                <div class="list-user-info">\${avatarContent}<div class="user-details"><span class="user-name">\${txn.user_name}</span><span class="user-email">\${txn.user_email}</span></div></div>
                                <span class="status-badge \${stClass}">\${stText}</span>
                            </div>
                            <div class="txn-details">
                                <div><div class="txn-plan">\${txn.plan_name}</div><div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">\${dt}</div></div>
                                <div class="txn-price">R$ \${parseFloat(txn.price).toFixed(2)}</div>
                            </div>
                            <div class="txn-actions">
                                \${txn.status === 'pendente' ? `
                                <button class="btn-txn accept" title="Aceitar" onclick="actionTxn('\${txn.id}', 'accept', '\${txn.user_name}', \${txn.price}, '\${txn.plan_name}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:16px;"><polyline points="20 6 9 17 4 12"/></svg></button>
                                <button class="btn-txn cancel" title="Cancelar" onclick="actionTxn('\${txn.id}', 'cancel', '\${txn.user_name}', \${txn.price}, '')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:16px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                ` : ''}
                                <button class="btn-txn delete" title="Eliminar" onclick="actionTxn('\${txn.id}', 'delete', '\${txn.user_name}', 0, '')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </div>
                    `;
                });
            }
            Swal.fire({
                html: `<div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:0;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(139, 92, 246, 0.1);color:#8b5cf6;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><h2 class="swal-title-custom" style="text-align:left; margin:0;">\${getLocalMsg('transactions_title')}</h2></div><div class="data-list">\${htmlList}</div>`,
                showConfirmButton: false, showCloseButton: true, customClass: { popup: 'swal-modal-custom' }, background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`
            });
        }
    });
}

function actionTxn(txnId, actionType, userName, price, planName) {
    const isDark = document.documentElement.classList.contains('dark');
    let title = '', desc = '', btnClass = '', btnIcon = '';
    if(actionType === 'accept') { title = getLocalMsg('confirm_accept_title'); desc = getLocalMsg('confirm_accept_desc').replace('{val}', price.toFixed(2)).replace('{user}', userName).replace('{plan}', planName); btnClass = 'success'; btnIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:18px;"><polyline points="20 6 9 17 4 12"/></svg>'; } 
    else if (actionType === 'cancel') { title = getLocalMsg('confirm_cancel_title'); desc = getLocalMsg('confirm_cancel_desc').replace('{user}', userName); btnClass = 'warning'; btnIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:18px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'; } 
    else { title = getLocalMsg('confirm_delete_txn_title'); desc = getLocalMsg('confirm_delete_txn_desc'); btnClass = 'danger'; btnIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'; }

    Swal.fire({
        html: `<div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><h2 class="swal-title-custom" style="text-align:left; margin:0;">\${title}</h2></div><p class="swal-desc-custom" style="text-align:left;">\${desc}</p>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: `swal-btn-confirm \${btnClass}`, cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.8)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: `\${btnIcon} Confirmar`, cancelButtonText: getLocalMsg('cancel')
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('?action=action_transaction', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({txn_id: txnId, txn_action: actionType}) })
            .then(r=>r.json()).then(res => {
                if(res.success) { showToast('success', 'toast_txn_updated'); openTransactionsModal(); } 
                else { showToast('error', res.error); openTransactionsModal(); }
            });
        } else { openTransactionsModal(); }
    });
}

document.addEventListener('DOMContentLoaded', applyRenI18n);
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else if (file_exists(__DIR__ . '/layout.php')) { include __DIR__ . '/layout.php'; } 
else { echo $pageContent . $extraJs; }
?>