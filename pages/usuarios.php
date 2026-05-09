<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Usuarios Premium
 * @description Controle total de usuários, integração de Avatar dinâmica, i18n instantâneo e UI imersiva.
 * =======================================================================================
 */

if (!defined('DTUNNEL_APP')) { 
    header('HTTP/1.0 403 Forbidden'); 
    exit; 
}

// =======================================================================================
// 1. GERENCIAMENTO DE SESSÃO E SEGURANÇA BÁSICA
// =======================================================================================
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$currentUser = getCurrentUser();
$dbFile = __DIR__ . '/../db/usuarios.json';

// Garante que o DB exista e tenha permissões adequadas
if (!file_exists($dbFile)) {
    if (!is_dir(dirname($dbFile))) { 
        mkdir(dirname($dbFile), 0755, true); 
    }
    file_put_contents($dbFile, json_encode([]));
    chmod($dbFile, 0644);
}

// LÊ O BANCO DE DADOS PRINCIPAL
$users = json_decode(file_get_contents($dbFile), true) ?: [];

// =======================================================================================
// 2. SISTEMA DE AUTO-LIMPEZA (DELETA VENCIDOS HÁ MAIS DE 3 DIAS)
// =======================================================================================
$nowDate = new DateTime();
$dbUpdated = false;

foreach ($users as $key => $u) {
    // A regra de ouro: Nunca deletar o Administrador automaticamente
    if (($u['role'] ?? 'user') === 'admin') {
        continue; 
    }
    
    // Verifica a data de expiração
    if (!empty($u['expires_at'])) {
        $expDate = new DateTime($u['expires_at']);
        
        // Se a data de expiração já passou
        if ($expDate < $nowDate) {
            $diff = $nowDate->diff($expDate);
            
            // Se expirou há 3 dias ou mais, removemos a conta permanentemente
            if ($diff->days >= 3) {
                unset($users[$key]);
                $dbUpdated = true;
            }
        }
    }
}

// Se alguma conta foi deletada pela limpeza, salvamos o BD
if ($dbUpdated) {
    $users = array_values($users); // Reindexa o array para manter a integridade JSON
    file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// =======================================================================================
// 3. MOTOR DE API INTERNA (AJAX PARA JSON) - CRUD COMPLETO
// =======================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Recarrega do arquivo pra garantir sincronia no momento exato da gravação (evita race conditions)
    $users = json_decode(file_get_contents($dbFile), true) ?: [];
    
    // ------------------------------------------------------------------
    // AÇÃO: CRIAR NOVO USUÁRIO
    // ------------------------------------------------------------------
    if ($action === 'create') {
        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $days     = (int)($input['days'] ?? 4);
        $role     = $input['role'] ?? 'user';
        
        // Validações de entrada
        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'error_code' => 'empty_fields']); 
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error_code' => 'invalid_email']); 
            exit;
        }
        if ($days > 999999) {
            echo json_encode(['success' => false, 'error_code' => 'max_days']); 
            exit;
        }
        
        // Verifica duplicidade de E-mail
        foreach ($users as $u) {
            if (strtolower($u['email']) === strtolower($email)) {
                echo json_encode(['success' => false, 'error_code' => 'email_taken']); 
                exit;
            }
        }
        
        // Geração de UUID seguro v4
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), 
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, 
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $createdAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$days days"));
        
        // Estrutura completa do novo usuário (Pronto para puxar Avatar depois)
        $newUser = [
            'uuid'       => $uuid, 
            'username'   => $username, 
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => $role, 
            'created_at' => $createdAt, 
            'expires_at' => $expiresAt, 
            'status'     => 'active',
            'avatar_url' => '' // A foto será inserida aqui quando o usuário fizer upload no perfil.php
        ];
        
        $users[] = $newUser;
        
        // Salva DB de forma segura
        file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        echo json_encode(['success' => true, 'user' => $newUser, 'plain_password' => $password]); 
        exit;
    }
    
    // ------------------------------------------------------------------
    // AÇÃO: EDITAR USUÁRIO EXISTENTE
    // ------------------------------------------------------------------
    if ($action === 'edit') {
        $uuid = $input['uuid'] ?? '';
        $found = false;
        
        $email = trim($input['email'] ?? '');
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error_code' => 'invalid_email']); 
            exit;
        }
        
        $daysToAdd = (int)($input['add_days'] ?? 0);
        if ($daysToAdd > 999999) {
            echo json_encode(['success' => false, 'error_code' => 'max_days']); 
            exit;
        }

        foreach ($users as &$u) {
            if ($u['uuid'] === $uuid) {
                // Atualização dos campos permitidos
                $u['username'] = trim($input['username'] ?? $u['username']);
                $u['email']    = $email ?: $u['email'];
                $u['role']     = $input['role'] ?? $u['role'];
                
                // Atualiza senha somente se o admin digitou uma nova
                if (!empty($input['password'])) {
                    $u['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
                }
                
                // Processamento avançado de Adição/Remoção de dias
                if ($daysToAdd !== 0) {
                    try {
                        $currentExp = new DateTime($u['expires_at'] ?: 'now');
                    } catch (\Exception $e) {
                        $currentExp = new DateTime();
                    }
                    $now = new DateTime();
                    
                    if ($currentExp < $now && $daysToAdd > 0) { 
                        $currentExp = $now; 
                    }
                    
                    // Tratamento rigoroso do Vitalicio
                    if ($daysToAdd >= 999999) {
                        $u['expires_at'] = date('Y-m-d H:i:s', strtotime("+999999 days"));
                    } 
                    // Se o admin tentar matar o vitalício subtraindo -999999
                    elseif ($daysToAdd <= -999999) {
                        $u['expires_at'] = date('Y-m-d H:i:s', time() - 3600); // Expirado imediatamente
                    } 
                    else {
                        $currentExp->modify(($daysToAdd > 0 ? "+" : "") . $daysToAdd . " days");
                        $u['expires_at'] = $currentExp->format('Y-m-d H:i:s');
                    }
                }
                $found = true; 
                break;
            }
        }
        unset($u); 
        
        if ($found) {
            file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error_code' => 'user_not_found']);
        }
        exit;
    }
    
    // ------------------------------------------------------------------
    // AÇÃO: ALTERAR STATUS (BLOQUEAR / SUSPENDER / ATIVAR)
    // ------------------------------------------------------------------
    if ($action === 'change_status') {
        $uuid = $input['uuid'] ?? '';
        $newEstado = $input['status'] ?? 'active'; 
        $found = false;
        
        foreach ($users as &$u) {
            if ($u['uuid'] === $uuid) {
                $u['status'] = $newEstado;
                $found = true;
                break;
            }
        }
        unset($u);
        
        if ($found) {
            file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            echo json_encode(['success' => true]); 
        } else {
            echo json_encode(['success' => false, 'error_code' => 'user_not_found']);
        }
        exit;
    }
    
    // ------------------------------------------------------------------
    // AÇÃO: LOGAR COMO O USUÁRIO (ACESSO ADMIN DIRETO)
    // ------------------------------------------------------------------
    if ($action === 'login_as') {
        $uuid = $input['uuid'] ?? '';
        foreach ($users as $u) {
            if ($u['uuid'] === $uuid) {
                $_SESSION['admin_return_email'] = $_SESSION['email']; 
                $_SESSION['email']      = $u['email'];
                $_SESSION['role']       = $u['role'];
                $_SESSION['username']   = $u['username'];
                $_SESSION['user_id']    = $u['id'] ?? $u['uuid'];
                $_SESSION['avatar_url'] = $u['avatar_url'] ?? ''; 
                
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error_code' => 'user_not_found']); 
        exit;
    }

    // ------------------------------------------------------------------
    // AÇÃO: EXCLUIR ÚNICO OU EXCLUIR EM MASSA
    // ------------------------------------------------------------------
    if ($action === 'delete') {
        $uuids = is_array($input['uuids']) ? $input['uuids'] : [$input['uuids']];
        $newUsers = [];
        
        foreach ($users as $u) {
            if (!in_array($u['uuid'], $uuids)) {
                $newUsers[] = $u;
            }
        }
        file_put_contents($dbFile, json_encode($newUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        echo json_encode(['success' => true]); 
        exit;
    }
}

// =======================================================================================
// 4. PREPARAÇÃO DE DADOS PARA A TELA E SISTEMA DE PAGINAÇÃO
// =======================================================================================
$search = trim($_GET['q'] ?? '');
$filteredUsers = [];

foreach ($users as $u) {
    if (strtolower($u['email']) === 'elnene.admin@gmail.com') {
        continue; 
    }
    
    if ($search) {
        $s = strtolower($search);
        if (strpos(strtolower($u['username']), $s) !== false || 
            strpos(strtolower($u['email']), $s) !== false || 
            strpos(strtolower($u['uuid']), $s) !== false) {
            $filteredUsers[] = $u;
        }
    } else {
        $filteredUsers[] = $u;
    }
}

$filteredUsers = array_reverse($filteredUsers);

$totalUsers = count($users);
$activeUsers = 0;
$blockedUsers = 0;
$suspendedUsers = 0;

foreach ($users as $u) {
    if (($u['status'] ?? 'active') === 'blocked') {
        $blockedUsers++;
    } elseif (($u['status'] ?? 'active') === 'suspended') {
        $suspendedUsers++;
    } else {
        $activeUsers++;
    }
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15; 
$totalPages = ceil(count($filteredUsers) / $perPage);
$offset = ($page - 1) * $perPage;
$paginatedUsers = array_slice($filteredUsers, $offset, $perPage);

$pageTitle = 'Gestão de Usuarios';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* =====================================================================
   ESTILOS GLOBAIS E VARIÁVEIS DO TEMA (DINÂMICO CLARO/ESCURO)
   ===================================================================== */
.users-wrapper {
    --card-bg: #ffffff; 
    --card-border: #e5e7eb; 
    --text-main: #111827; 
    --text-muted: #6b7280; 
    --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; 
    --icon-bg: #f3f4f6; 
    --icon-color: #4b5563; 
    
    --primary: #3b82f6; 
    --danger: #ef4444; 
    --success: #10b981; 
    --warning: #f59e0b; 
    --orange: #f97316;
}

:root.dark .users-wrapper, .dark .users-wrapper, body.dark .users-wrapper {
    --card-bg: #1a1a1e; 
    --card-border: #27272a; 
    --text-main: #f9fafb; 
    --text-muted: #a1a1aa; 
    --text-subtle: #71717a;
    --inner-bg: #121214; 
    --icon-bg: rgba(255, 255, 255, 0.05); 
    --icon-color: #e4e4e7;
}

.users-wrapper { padding: 16px; max-width: 1000px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif; }
.users-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; }

/* ---------------------------------------------------------------------
   CABEÇALHO DA PÁGINA (Títulos e Botão de Crear)
   --------------------------------------------------------------------- */
.page-header-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.ph-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 6px 0; }
.ph-titles p { font-size: 0.95rem; color: var(--text-muted); margin: 0; font-weight: 500; }

.btn-sys {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
    padding: 14px 20px; border-radius: 12px; font-size: 0.95rem; font-weight: 800; 
    cursor: pointer; transition: transform 0.15s, filter 0.2s, box-shadow 0.2s; border: none;
}
.btn-sys:active { transform: scale(0.94); filter: brightness(0.9); }
.btn-primary { background-color: var(--primary) !important; color: #ffffff !important; }
.btn-danger { background-color: var(--danger) !important; color: #ffffff !important; }
.btn-cancel { background-color: #e5e7eb !important; color: #374151 !important; box-shadow: none !important; } 
.dark .btn-cancel, :root.dark .btn-cancel { background-color: #27272a !important; color: #f9fafb !important; } 
.btn-outline { background: transparent !important; border: 1px solid var(--card-border) !important; color: var(--text-main) !important; box-shadow: none !important; }
.btn-outline:active { background: var(--icon-bg) !important; }

/* ---------------------------------------------------------------------
   BARRA DE PESQUISA INTELIGENTE
   --------------------------------------------------------------------- */
.search-bar-container { display: flex; gap: 12px; width: 100%; max-width: 400px; margin-bottom: 24px;}
.search-input-wrap { position: relative; flex: 1; }
.search-input-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; }
.search-input { width: 100%; background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 12px 14px 12px 40px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; transition: border 0.3s; }
.search-input:focus { border-color: var(--primary); }

/* ---------------------------------------------------------------------
   CARDS DE ESTATÍSTICAS
   --------------------------------------------------------------------- */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px 20px; display: flex; flex-direction: column; gap: 4px; transition: transform 0.15s; }
.stat-card:active { transform: scale(0.96); }
.st-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.st-label { font-size: 0.7rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 1px; }
.st-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.st-icon.blue { background: rgba(59,130,246,0.1); color: var(--primary); }
.st-icon.green { background: rgba(16,185,129,0.1); color: var(--success); }
.st-icon.red { background: rgba(239,68,68,0.1); color: var(--danger); }
.st-icon.orange { background: rgba(249,115,22,0.1); color: var(--orange); }
.st-value { font-size: 1.6rem; font-weight: 800; color: var(--text-main); font-family: 'Space Grotesk', sans-serif; }

/* ---------------------------------------------------------------------
   AÇÕES EM MASSA E CHECKBOX
   --------------------------------------------------------------------- */
.bulk-actions { background: var(--inner-bg); border: 1px dashed var(--card-border); border-radius: 14px; padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
#btn-bulk-delete { display: none; animation: slideIn 0.2s ease forwards; padding: 10px 16px; font-size: 0.85rem;}
@keyframes slideIn { from { opacity:0; transform:translateX(10px); } to { opacity:1; transform:translateX(0); } }
.chk-wrapper { display: flex; align-items: center; cursor: pointer; user-select: none; }
.chk-wrapper input { display: none; }
.chk-box { width: 22px; height: 22px; border: 2px solid var(--card-border); border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; background: var(--inner-bg); }
.chk-wrapper input:checked ~ .chk-box { background: var(--primary); border-color: var(--primary); }
.chk-box svg { width: 14px; height: 14px; color: white; opacity: 0; transition: opacity 0.2s; }
.chk-wrapper input:checked ~ .chk-box svg { opacity: 1; }

/* ---------------------------------------------------------------------
   LISTA DE USUÁRIOS
   --------------------------------------------------------------------- */
.users-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px; max-height: 60vh; overflow-y: auto; scrollbar-width: none; padding-bottom: 20px; }
.users-list::-webkit-scrollbar { display: none; }

.user-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 12px; transition: transform 0.15s, border 0.3s; position: relative; }
.user-card:active { transform: scale(0.98); }
.user-card.selected { border-color: var(--primary); background: rgba(59,130,246,0.03); }

.uc-top { display: flex; align-items: center; gap: 14px; }
.uc-avatar { width: 44px; height: 44px; background: var(--icon-bg); color: var(--text-main); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; flex-shrink: 0; overflow: hidden; border: 1px solid var(--card-border); }
.uc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.uc-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.uc-name { font-size: 1rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.uc-email { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.uc-status-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; }
.badge-active { background: rgba(16,185,129,0.1); color: var(--success); }
.badge-blocked { background: rgba(239,68,68,0.1); color: var(--danger); }
.badge-suspended { background: rgba(249,115,22,0.1); color: var(--orange); }
.badge-expired { background: rgba(245,158,11,0.1); color: var(--warning); }

.uc-details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: var(--inner-bg); border-radius: 10px; padding: 12px; border: 1px dashed var(--card-border); }
.uc-det-item { display: flex; flex-direction: column; gap: 2px; }
.uc-det-label { font-size: 0.65rem; color: var(--text-subtle); font-weight: 800; text-transform: uppercase; }
.uc-det-val { font-size: 0.85rem; color: var(--text-main); font-weight: 800; font-family: 'Space Grotesk', monospace; }

/* =====================================================================
   AÇÕES DE LISTA - CORES ABSOLUTAS E BRILHANTES (100% VISÍVEIS)
   ===================================================================== */
.uc-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; border-top: 1px dashed var(--card-border); padding-top: 12px; }
.action-btn {
    width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--card-border); 
    display: flex; align-items: center; justify-content: center; cursor: pointer; 
    transition: transform 0.15s, filter 0.2s, background 0.2s, color 0.2s; outline:none; font-weight: bold;
}
.action-btn:active { transform: scale(0.85); filter: brightness(0.9); }

/* Colores Inquebráveis: Fundo sólido com opacidade e ícones em cores puras */
.btn-login-as { color: #8b5cf6; border-color: rgba(139,92,246,0.3); background: rgba(139,92,246,0.1); }
.btn-edit { color: #3b82f6; border-color: rgba(59,130,246,0.3); background: rgba(59,130,246,0.1); }

/* Bloquear/Eliminar agora são super visíveis em qualquer modo */
.btn-lock { color: #ef4444; border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.15); }
.btn-unlock { color: #10b981; border-color: rgba(16,185,129,0.5); background: rgba(16,185,129,0.15); }
.btn-suspend { color: #f97316; border-color: rgba(249,115,22,0.5); background: rgba(249,115,22,0.15); }
.btn-unsuspend { color: #3b82f6; border-color: rgba(59,130,246,0.5); background: rgba(59,130,246,0.15); }
.btn-del { color: #f43f5e; border-color: rgba(244,63,94,0.5); background: rgba(244,63,94,0.15); border-style: dashed; }

/* Efeito Hover Premium */
.btn-login-as:hover { background: #8b5cf6; color: #fff; }
.btn-edit:hover { background: #3b82f6; color: #fff; }
.btn-lock:hover { background: #ef4444; color: #fff; border-color: #ef4444; }
.btn-unlock:hover { background: #10b981; color: #fff; border-color: #10b981; }
.btn-suspend:hover { background: #f97316; color: #fff; border-color: #f97316; }
.btn-unsuspend:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.btn-del:hover { background: #f43f5e; color: #fff; border-style: solid; border-color: #f43f5e;}

/* ---------------------------------------------------------------------
   HTML MODAIS (CRIAÇÃO/SUCESSO)
   --------------------------------------------------------------------- */
.super-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 9999999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s; padding: 16px; }
.super-modal-overlay.show { opacity: 1; visibility: visible; }
.super-modal-box { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; width: 100%; max-width: 440px; max-height: 90vh; display: flex; flex-direction: column; transform: scale(0.9) translateY(20px); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 25px 50px rgba(0,0,0,0.5); overflow: hidden; }
.super-modal-overlay.show .super-modal-box { transform: scale(1) translateY(0); }
.sm-header { padding: 20px 24px; border-bottom: 1px solid var(--card-border); display: flex; align-items: center; justify-content: space-between; }
.sm-title { font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px; }
.sm-close { background: var(--icon-bg); border: 1px solid var(--card-border); color: var(--text-muted); cursor: pointer; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items:center; justify-content:center; transition: transform 0.2s; outline:none; }
.sm-close:active { transform: scale(0.8); color: var(--text-main); }
.sm-body { padding: 24px; overflow-y: auto; scrollbar-width: none; display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-label { font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.form-input { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 14px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; outline: none; transition: border 0.3s; }
.form-input:focus { border-color: var(--primary); }
.form-input:read-only { opacity: 0.6; font-family: monospace; }
.role-accordion { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; }
.role-header { padding: 14px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 800; color: var(--text-main); font-size: 0.9rem; user-select: none; }
.role-body { max-height: 0; transition: max-height 0.3s; overflow: hidden; background: var(--card-bg); }
.role-body.open { max-height: 200px; border-top: 1px solid var(--card-border); }
.role-option { padding: 12px 14px; display: flex; align-items: center; gap: 10px; cursor: pointer; color: var(--text-muted); font-weight: 600; transition: background 0.2s; }
.role-option:active { background: var(--icon-bg); }
.role-option.active { color: var(--primary); }
.sm-footer { padding: 16px 24px; border-top: 1px solid var(--card-border); display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Detalhes Mágicos no Modal de Éxito de Criação */
.confirm-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; }
.confirm-icon.green { background: rgba(16,185,129,0.1); color: var(--success); }
.success-details { background: var(--inner-bg); border: 1px dashed var(--card-border); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.sd-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--card-border); padding-bottom: 8px; }
.sd-row:last-child { border-bottom: none; padding-bottom: 0; }
.sd-label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
.sd-value { font-size: 0.85rem; font-weight: 800; color: var(--text-main); word-break: break-all; text-align: right; font-family: 'Space Grotesk', monospace; }

/* ---------------------------------------------------------------------
   TOASTS (Notificaciones Flutuantes no canto superior direito)
   --------------------------------------------------------------------- */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 16px 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 12px; width: 320px; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
.toast.show { transform: translateX(0); }
.toast-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: white; }
.toast.error .toast-icon { background: var(--danger); }
.toast.success .toast-icon { background: var(--success); }
.toast-msg { font-size: 0.9rem; font-weight: 600; line-height: 1.4; flex: 1; color: var(--text-main); }
.toast-progress { position: absolute; bottom: 0; left: 0; height: 3px; background: var(--primary); animation: toastTime 4s linear forwards; }
.toast.error .toast-progress { background: var(--danger); }
.toast.success .toast-progress { background: var(--success); }
@keyframes toastTime { from { width: 100%; } to { width: 0%; } }

/* ======================================================================
   ESTILOS PREMIUM SWEETALERT2 PARA AÇÕES DE BANCO DE DADOS (Clean & Quadrado)
   ====================================================================== */
.swal-modal-custom {
    background: var(--card-bg) !important; border: 1px solid var(--card-border) !important;
    border-radius: 20px !important; padding: 24px !important; width: 90% !important; max-width: 440px !important;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important;
}
.swal2-html-container { margin: 0 !important; overflow: hidden !important; text-align: left !important; }
.swal-header-custom { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; text-align: left; }
.swal-icon-custom { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.swal-icon-custom svg { width: 24px; height: 24px; stroke-width: 2.5; }
.swal-icon-custom.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.swal-icon-custom.danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.swal-icon-custom.orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }
.swal-icon-custom.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.swal-header-text { display: flex; flex-direction: column; gap: 4px; }
.swal-title-custom { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2; font-family: 'Manrope', sans-serif; }
.swal-desc-custom { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin: 0; line-height: 1.5; font-family: 'Manrope', sans-serif; }
.swal2-actions { width: 100% !important; margin-top: 0 !important; display: flex !important; gap: 10px !important;}
.swal-btn-confirm, .swal-btn-cancel {
    flex: 1 !important; border-radius: 12px !important; padding: 14px !important; font-weight: 700 !important; 
    font-size: 1rem !important; display: flex !important; align-items: center !important; justify-content: center !important; 
    gap: 8px !important; outline: none !important; box-shadow: none !important; border: none !important; cursor: pointer !important;
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), filter 0.2s !important; font-family: 'Manrope', sans-serif !important;
}
.swal-btn-confirm:active, .swal-btn-cancel:active { transform: scale(0.96) !important; filter: brightness(0.9) !important; }
.swal-btn-confirm:hover, .swal-btn-cancel:hover { filter: brightness(1.1) !important; }
.swal-btn-primary { background: var(--primary) !important; color: #fff !important; }
.swal-btn-danger { background: #ef4444 !important; color: #fff !important; }
.swal-btn-orange { background: #f97316 !important; color: #fff !important; }
.swal-btn-success { background: #10b981 !important; color: #fff !important; }
.swal-btn-cancel { background: transparent !important; border: 1px solid var(--card-border) !important; color: var(--text-main) !important; }

@media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div id="toast-container"></div>

<div class="users-wrapper">
    <div class="page-header-flex">
        <div class="ph-titles">
            <h1 data-i18n="user_management">Gestão de Usuarios</h1>
            <p data-i18n="manage_users_desc">Controle absoluto de todas as contas do sistema.</p>
        </div>
        <button class="btn-sys btn-primary" onclick="openCreateModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span data-i18n="create_user">Crear Usuario</span>
        </button>
    </div>

    <div class="search-bar-container">
        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" class="search-input" data-i18n-placeholder="search_placeholder" placeholder="Buscar por nome, email ou UUID..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter') executeSearch()">
        </div>
        <button class="btn-sys btn-outline" id="btn-search" onclick="executeSearch()">
            <span data-i18n="search">Buscar</span>
        </button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="st-top">
                <span class="st-label" data-i18n="total_users">TOTAL DE CUENTAS</span>
                <div class="st-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            </div>
            <div class="st-value"><?= $totalUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="st-top">
                <span class="st-label" data-i18n="active_users">ATIVOS</span>
                <div class="st-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            </div>
            <div class="st-value"><?= $activeUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="st-top">
                <span class="st-label" data-i18n="blocked_users">BLOQUEADOS</span>
                <div class="st-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div>
            </div>
            <div class="st-value"><?= $blockedUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="st-top">
                <span class="st-label" data-i18n="suspended_users">SUSPENSOS</span>
                <div class="st-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg></div>
            </div>
            <div class="st-value"><?= $suspendedUsers ?></div>
        </div>
    </div>

    <div class="bulk-actions">
        <label class="chk-wrapper">
            <input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this)">
            <div class="chk-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
            <span style="font-size:0.95rem; font-weight:800; color:var(--text-main); margin-left:10px;"><span data-i18n="select_all">Selecionar Todos</span></span>
        </label>
        <button class="btn-sys btn-danger" id="btn-bulk-delete" onclick="openBulkDeleteModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            <span data-i18n="delete_selected">Eliminar Contas</span>
        </button>
    </div>

    <div class="users-list">
        <?php if (empty($paginatedUsers)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted); font-weight:700; border: 1px dashed var(--card-border); border-radius: 16px;">
                <span data-i18n="no_users_found">Ningún usuário encontrado.</span>
            </div>
        <?php else: ?>
            <?php foreach ($paginatedUsers as $u): 
                $words = explode(' ', trim($u['username']));
                $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                
                $now = new DateTime();
                $exp = new DateTime($u['expires_at']);
                $diff = $now->diff($exp);
                $daysLeft = $diff->days;
                
                if ($exp < $now) $daysLeft = -$daysLeft;
                if ($daysLeft == 0 && $diff->h > 0 && $exp > $now) $daysLeft = 1;
                
                $status = $u['status'] ?? 'active';
                $isExpired = ($daysLeft < 0);
                
                if ($status === 'blocked') { 
                    $badgeClass = 'badge-blocked'; $badgeText = 'BLOQUEADO'; $i18nBadge = 'badge_blocked'; 
                } elseif ($status === 'suspended') { 
                    $badgeClass = 'badge-suspended'; $badgeText = 'SUSPENSO'; $i18nBadge = 'badge_suspended'; 
                } elseif ($isExpired) { 
                    $badgeClass = 'badge-expired'; $badgeText = 'EXPIRADO'; $i18nBadge = 'badge_expired'; 
                } else { 
                    $badgeClass = 'badge-active'; $badgeText = 'ATIVO'; $i18nBadge = 'badge_active'; 
                }
                
                $isBlocked = ($status === 'blocked');
                $isSuspended = ($status === 'suspended');
            ?>
            <div class="user-card" id="card-<?= $u['uuid'] ?>">
                <div class="uc-top">
                    <label class="chk-wrapper">
                        <input type="checkbox" class="user-chk" value="<?= $u['uuid'] ?>" onchange="checkSelection()">
                        <div class="chk-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                    </label>
                    
                    <div class="uc-avatar">
                        <?php if (!empty($u['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($u['avatar_url']) ?>" alt="Avatar">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>

                    <div class="uc-info">
                        <div class="uc-name"><?= htmlspecialchars($u['username']) ?> <span style="font-size:0.7rem; color:var(--primary);">(<?= strtoupper($u['role'] ?? 'USER') ?>)</span></div>
                        <div class="uc-email"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    
                    <div class="uc-status-badge <?= $badgeClass ?>"><span data-i18n="<?= $i18nBadge ?>"><?= $badgeText ?></span></div>
                </div>

                <div class="uc-details">
                    <div class="uc-det-item">
                        <span class="uc-det-label" data-i18n="created_at">CRIADO EM</span>
                        <span class="uc-det-val"><?= date('d/m/Y', strtotime($u['created_at'])) ?></span>
                    </div>
                    <div class="uc-det-item">
                        <span class="uc-det-label" data-i18n="expires_at">EXPIRA EL</span>
                        <span class="uc-det-val" style="<?= $isExpired ? 'color:var(--danger);' : 'color:var(--success);' ?>">
                            <?= $daysLeft > 3650 ? '<span data-i18n="lifetime">VITALÍCIO</span>' : date('d/m/Y H:i', strtotime($u['expires_at'])) ?>
                        </span>
                    </div>
                </div>

                <div class="uc-actions">
                    <button class="action-btn btn-login-as" title="Logar como" onclick="openLoginAsModal('<?= $u['uuid'] ?>', '<?= addslashes($u['username']) ?>')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    </button>
                    
                    <button class="action-btn btn-edit" title="Editar" onclick='openEditModal(<?= json_encode($u) ?>)'>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    
                    <?php if($isBlocked): ?>
                    <button class="action-btn btn-unlock" title="Desbloquear" onclick="openEstadoModal('<?= $u['uuid'] ?>', '<?= addslashes($u['username']) ?>', 'active', 'unblock')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                    </button>
                    <?php else: ?>
                    <button class="action-btn btn-lock" title="Bloquear" onclick="openEstadoModal('<?= $u['uuid'] ?>', '<?= addslashes($u['username']) ?>', 'blocked', 'block')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </button>
                    <?php endif; ?>

                    <?php if($isSuspended): ?>
                    <button class="action-btn btn-unsuspend" title="Tirar Suspensão" onclick="openEstadoModal('<?= $u['uuid'] ?>', '<?= addslashes($u['username']) ?>', 'active', 'unsuspend')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                    </button>
                    <?php else: ?>
                    <button class="action-btn btn-suspend" title="Suspender" onclick="openEstadoModal('<?= $u['uuid'] ?>', '<?= addslashes($u['username']) ?>', 'suspended', 'suspend')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    </button>
                    <?php endif; ?>

                    <button class="action-btn btn-del" title="Eliminar" onclick="openDeleteSingle('<?= $u['uuid'] ?>', '<?= addslashes($u['username']) ?>')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="super-modal-overlay" id="userModal">
    <div class="super-modal-box">
        <div class="sm-header">
            <h3 class="sm-title" id="modalTitle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <span id="modalTitleText" data-i18n="create_user">Crear Usuario</span>
            </h3>
            <button class="sm-close" onclick="closeModal('userModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="sm-body">
            <input type="hidden" id="fm-action" value="create">
            <div class="form-group" id="box-uuid" style="display:none;">
                <label class="form-label">UUID</label>
                <input type="text" class="form-input" id="fm-uuid" readonly>
            </div>
            <div class="form-group">
                <label class="form-label"><span data-i18n="username">Nombre da Conta</span></label>
                <input type="text" class="form-input" id="fm-username" placeholder="Ex: JoaoSilva" data-i18n-placeholder="ex_name">
            </div>
            <div class="form-group">
                <label class="form-label"><span data-i18n="email">E-mail</span></label>
                <input type="email" class="form-input" id="fm-email" placeholder="Ex: joao@gmail.com">
            </div>
            <div class="form-group">
                <label class="form-label" id="lbl-password"><span data-i18n="password_initial">Contraseña Inicial</span></label>
                <input type="text" class="form-input" id="fm-password" placeholder="Tu contraseña forte" data-i18n-placeholder="your_strong_password">
            </div>
            <div class="form-group">
                <label class="form-label" id="lbl-days"><span data-i18n="access_days">Días de Acesso (Max: 999999)</span></label>
                <input type="number" class="form-input" id="fm-days" placeholder="Ex: 30" data-i18n-placeholder="ex_days">
            </div>
            
            <div class="form-group">
                <label class="form-label"><span data-i18n="permissions">Permissões (Cargo)</span></label>
                <div class="role-accordion">
                    <div class="role-header" onclick="toggleRoleMenu()">
                        <span id="fm-role-display"><span data-i18n="common_user">Usuario Comum</span></span>
                        <svg id="role-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; transition:transform 0.3s;"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="role-body" id="role-body">
                        <div class="role-option active" onclick="selectRole('user', 'Usuario Comum', 'common_user', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <span data-i18n="common_user">Usuario Comum</span>
                        </div>
                        <div class="role-option" onclick="selectRole('admin', 'Administrador (Acesso Total)', 'admin_user', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg> <span data-i18n="admin_user">Administrador (Acesso Total)</span>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="fm-role" value="user">
            </div>
        </div>
        
        <div class="sm-footer">
            <button class="btn-sys btn-cancel" onclick="closeModal('userModal')"><span data-i18n="cancel">Cancelar</span></button>
            <button class="btn-sys btn-primary" id="btn-save-user" onclick="saveUser()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span data-i18n="save">Guardar</span>
            </button>
        </div>
    </div>
</div>

<div class="super-modal-overlay" id="successModal">
    <div class="super-modal-box" style="max-width: 460px;">
        <div class="sm-header" style="border-bottom: none; padding-bottom: 0;">
            <div style="width:100%; display:flex; flex-direction:column; align-items:center; gap:12px;">
                <div class="confirm-icon green" style="margin: 0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:36px;"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 class="sm-title" style="font-size: 1.3rem;"><span data-i18n="user_created_success">Usuario Criado!</span></h3>
            </div>
            <button class="sm-close" style="position:absolute; top: 16px; right: 16px;" onclick="closeModal('successModal'); window.location.reload();">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sm-body">
            <div class="success-details">
                <div class="sd-row">
                    <span class="sd-label">UUID</span>
                    <span class="sd-value" id="s-uuid">---</span>
                </div>
                <div class="sd-row">
                    <span class="sd-label" data-i18n="username">Conta</span>
                    <span class="sd-value" id="s-username">---</span>
                </div>
                <div class="sd-row">
                    <span class="sd-label">Email</span>
                    <span class="sd-value" id="s-email">---</span>
                </div>
                <div class="sd-row">
                    <span class="sd-label" data-i18n="password_initial">Contraseña</span>
                    <span class="sd-value" id="s-password" style="color:var(--primary);">---</span>
                </div>
                <div class="sd-row">
                    <span class="sd-label" data-i18n="created_at">Criado em</span>
                    <span class="sd-value" id="s-created">---</span>
                </div>
                <div class="sd-row">
                    <span class="sd-label" data-i18n="expires_at">Expira em</span>
                    <span class="sd-value" id="s-expires" style="color:var(--success);">---</span>
                </div>
            </div>
        </div>
        <div class="sm-footer">
            <button class="btn-sys btn-outline" id="btn-copy-data" onclick="copyUserData()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span data-i18n="copy">Copiar Dados</span>
            </button>
            <button class="btn-sys btn-primary" id="btn-tg-data" onclick="sendToTelegram()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span>Telegram</span>
            </button>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
// --- DICIONÁRIO INTERNO EXCLUSIVO DESTA PÁGINA (Sincronizado depois com o globalTranslations) ---
const localDict = {
    'pt': {
        'empty_fields': 'Completá todos los campos.', 'invalid_email': 'Insira um E-mail válido com @.',
        'max_days': 'O máximo permitido é 999999 dias.', 'email_taken': 'Este E-mail já está cadastrado.',
        'user_not_found': 'Usuario não encontrado no banco de dados.', 'toast_success': 'Ação concluída con éxito!',
        'create_user': 'Crear Usuario', 'edit_user': 'Editar Usuario', 'password_initial': 'Contraseña Inicial',
        'password_edit': 'Nueva contraseña (Vazio = sem mudar)', 'days_add': 'Agregar Días (Use negativo p/ remover)',
        'block_title': 'Bloquear Conta', 'unblock_title': 'Desbloquear Conta',
        'suspend_title': 'Suspender Conta', 'unsuspend_title': 'Tirar Suspensão',
        'block_msg': 'Tem certeza que deseja bloquear a conta <b>{name}</b>? O acesso à rede será negado.',
        'unblock_msg': 'Deseja desbloquear a conta <b>{name}</b>? O acesso voltará ao normal.',
        'suspend_msg': 'Tem certeza que deseja suspender a conta <b>{name}</b>? O usuário será jogado para a página 404.',
        'unsuspend_msg': 'Deseja retirar a suspensão da conta <b>{name}</b>?',
        'delete_title': 'Eliminar Conta', 'delete_bulk_title': 'Eliminar Contas',
        'delete_msg': 'A conta <b>{name}</b> será deletada permanentemente.',
        'delete_bulk_msg': 'Tem certeza que deseja deletar as <b>{name}</b> contas selecionadas?',
        'login_title': 'Logar na Conta', 'login_msg': 'Deseja entrar no painel como <b>{name}</b>?',
        'btn_block': 'Bloquear', 'btn_unblock': 'Desbloquear', 'btn_suspend': 'Suspender',
        'btn_unsuspend': 'Reativar', 'btn_delete': 'Eliminar', 'btn_login': 'Logar', 'toast_copied': 'Dados copiados para a área de transferência!',
        'search_placeholder': 'Buscar por nome, email ou UUID...', 'ex_name': 'Ex: JoaoSilva', 'ex_days': 'Ex: 30',
        'error_max_days_title': 'Limite Inválido', 'error_max_days_desc': 'Você não pode adicionar mais que 999999 dias. O sistema atingiu o limite de tempo permitido.'
    },
    'en': {
        'empty_fields': 'Fill in all fields.', 'invalid_email': 'Enter a valid E-mail.',
        'max_days': 'Maximum allowed is 999999 days.', 'email_taken': 'This E-mail is already registered.',
        'user_not_found': 'User not found in database.', 'toast_success': 'Action completed successfully!',
        'create_user': 'Create User', 'edit_user': 'Edit User', 'password_initial': 'Initial Password',
        'password_edit': 'New Password (Blank = no change)', 'days_add': 'Add Days (Negative to remove)',
        'block_title': 'Block Account', 'unblock_title': 'Unblock Account',
        'suspend_title': 'Suspend Account', 'unsuspend_title': 'Remove Suspension',
        'block_msg': 'Are you sure you want to block the account <b>{name}</b>?',
        'unblock_msg': 'Do you want to unblock the account <b>{name}</b>?',
        'suspend_msg': 'Are you sure you want to suspend the account <b>{name}</b>?',
        'unsuspend_msg': 'Do you want to remove the suspension for <b>{name}</b>?',
        'delete_title': 'Delete Account', 'delete_bulk_title': 'Delete Accounts',
        'delete_msg': 'The account <b>{name}</b> will be permanently deleted.',
        'delete_bulk_msg': 'Are you sure you want to delete the <b>{name}</b> selected accounts?',
        'login_title': 'Login as User', 'login_msg': 'Do you want to login as <b>{name}</b>?',
        'btn_block': 'Block', 'btn_unblock': 'Unblock', 'btn_suspend': 'Suspend',
        'btn_unsuspend': 'Unsuspend', 'btn_delete': 'Delete', 'btn_login': 'Login', 'toast_copied': 'Data copied to clipboard!',
        'search_placeholder': 'Search by name, email or UUID...', 'ex_name': 'Ex: JohnDoe', 'ex_days': 'Ex: 30',
        'error_max_days_title': 'Invalid Limit', 'error_max_days_desc': 'You cannot add more than 999999 days. The system reached the allowed time limit.'
    },
    'es': {
        'empty_fields': 'Complete todos los campos.', 'invalid_email': 'Ingrese un correo válido.',
        'max_days': 'El máximo permitido es 999999 días.', 'email_taken': 'Este correo ya está registrado.',
        'user_not_found': 'Usuario no encontrado.', 'toast_success': '¡Acción completada con éxito!',
        'create_user': 'Crear Usuario', 'edit_user': 'Editar Usuario', 'password_initial': 'Contraseña Inicial',
        'password_edit': 'Nueva Contraseña (Blanco = no cambiar)', 'days_add': 'Añadir Días (Negativo para quitar)',
        'block_title': 'Bloquear Cuenta', 'unblock_title': 'Desbloquear Cuenta',
        'suspend_title': 'Suspender Cuenta', 'unsuspend_title': 'Quitar Suspensión',
        'block_msg': '¿Estás seguro de que deseas bloquear la cuenta <b>{name}</b>?',
        'unblock_msg': '¿Deseas desbloquear la cuenta <b>{name}</b>?',
        'suspend_msg': '¿Deseas suspender la cuenta <b>{name}</b>?',
        'unsuspend_msg': '¿Deseas quitar la suspensión de <b>{name}</b>?',
        'delete_title': 'Eliminar Cuenta', 'delete_bulk_title': 'Eliminar Cuentas',
        'delete_msg': 'La cuenta <b>{name}</b> será eliminada permanentemente.',
        'delete_bulk_msg': '¿Estás seguro de que deseas eliminar las <b>{name}</b> cuentas seleccionadas?',
        'login_title': 'Iniciar Sesión', 'login_msg': '¿Deseas iniciar sesión como <b>{name}</b>?',
        'btn_block': 'Bloquear', 'btn_unblock': 'Desbloquear', 'btn_suspend': 'Suspender',
        'btn_unsuspend': 'Reactivar', 'btn_delete': 'Eliminar', 'btn_login': 'Iniciar Sesión', 'toast_copied': '¡Datos copiados al portapapeles!',
        'search_placeholder': 'Buscar por nombre, correo o UUID...', 'ex_name': 'Ej: JuanPerez', 'ex_days': 'Ej: 30',
        'error_max_days_title': 'Límite Inválido', 'error_max_days_desc': 'No puedes añadir más de 999999 días. El sistema alcanzó el límite de tiempo permitido.'
    }
};

// ================= INJEÇÃO I18N INSTANTÂNEA E PERFEITA ================= 
if (window.globalTranslations) {
    for (let lang in localDict) {
        if (!window.globalTranslations[lang]) window.globalTranslations[lang] = {};
        Object.assign(window.globalTranslations[lang], localDict[lang]);
    }
}

const originalSelectAppLang = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(originalSelectAppLang) originalSelectAppLang(langCode);
    
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (window.globalTranslations && window.globalTranslations[langCode] && window.globalTranslations[langCode][key]) {
            el.placeholder = window.globalTranslations[langCode][key];
        }
    });
    triggerGlobalTranslation();
};

function getLocalMsg(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    if(window.globalTranslations && window.globalTranslations[lang] && window.globalTranslations[lang][key]) {
        return window.globalTranslations[lang][key];
    }
    return localDict[lang][key] || key;
}

function triggerGlobalTranslation() {
    const langCode = localStorage.getItem('app_language') || 'pt';
    const dict = window.globalTranslations ? window.globalTranslations[langCode] : localDict[langCode];
    if(!dict) return;
    
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) {
            let text = dict[key];
            if (el.hasAttribute('data-days')) text = text.replace('{days}', el.getAttribute('data-days'));
            el.innerHTML = text;
        }
    });
}

// -----------------------------------------------------------------------
// MOTOR DE TOAST (Alertas visuais no canto direito)
// -----------------------------------------------------------------------
function showToast(type, msgKey) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast \${type}`;
    const icon = type === 'error' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>';
    toast.innerHTML = `<div class="toast-icon">\${icon}</div><div class="toast-msg">\${getLocalMsg(msgKey)}</div><div class="toast-progress"></div>`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
}

// -----------------------------------------------------------------------
// BUSCA E SELEÇÃO EM MASSA (Checkboxes)
// -----------------------------------------------------------------------
function executeSearch() {
    const btn = document.getElementById('btn-search');
    btn.innerHTML = `<svg style="width:18px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> ...`;
    setTimeout(() => { window.location = '/usuarios?q=' + encodeURIComponent(document.getElementById('searchInput').value); }, 400); 
}

function toggleSelectAll(source) {
    document.querySelectorAll('.user-chk').forEach(cb => { cb.checked = source.checked; });
    checkSelection();
}

function checkSelection() {
    const checkboxes = document.querySelectorAll('.user-chk:checked');
    const btnDelete = document.getElementById('btn-bulk-delete');
    document.querySelectorAll('.user-chk').forEach(cb => {
        const card = document.getElementById('card-' + cb.value);
        if(cb.checked) card.classList.add('selected'); else card.classList.remove('selected');
    });
    if (checkboxes.length > 0) btnDelete.style.display = 'inline-flex';
    else { btnDelete.style.display = 'none'; document.getElementById('selectAllChk').checked = false; }
}

// -----------------------------------------------------------------------
// ABERTURA DE MODAIS DE FORMULÁRIO (Crear e Editar)
// -----------------------------------------------------------------------
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function toggleRoleMenu() {
    document.getElementById('role-body').classList.toggle('open');
    document.getElementById('role-chev').classList.toggle('rotate-chevron');
}
function selectRole(val, text, i18nKey, el) {
    document.getElementById('fm-role').value = val;
    document.getElementById('fm-role-display').innerHTML = `<span data-i18n="\${i18nKey}">\${text}</span>`;
    document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('role-body').classList.remove('open');
    document.getElementById('role-chev').classList.remove('rotate-chevron');
    triggerGlobalTranslation();
}

function openCreateModal() {
    document.getElementById('fm-action').value = 'create';
    document.getElementById('modalTitleText').innerText = getLocalMsg('create_user');
    document.getElementById('modalTitleText').setAttribute('data-i18n', 'create_user');
    document.getElementById('box-uuid').style.display = 'none';
    
    document.getElementById('lbl-password').innerHTML = `<span data-i18n="password_initial">\${getLocalMsg('password_initial')}</span>`;
    document.getElementById('lbl-days').innerHTML = `<span data-i18n="access_days">Días de Acesso (Max: 999999)</span>`;
    
    document.getElementById('fm-uuid').value = '';
    document.getElementById('fm-username').value = '';
    document.getElementById('fm-email').value = '';
    document.getElementById('fm-password').value = '';
    document.getElementById('fm-days').value = '30';
    selectRole('user', 'Usuario Comum', 'common_user', document.querySelector('.role-option:first-child'));
    
    document.getElementById('userModal').classList.add('show');
    triggerGlobalTranslation();
}

function openEditModal(u) {
    document.getElementById('fm-action').value = 'edit';
    document.getElementById('modalTitleText').innerText = getLocalMsg('edit_user');
    document.getElementById('modalTitleText').setAttribute('data-i18n', 'edit_user');
    document.getElementById('box-uuid').style.display = 'flex';
    
    document.getElementById('lbl-password').innerHTML = `<span data-i18n="password_edit">\${getLocalMsg('password_edit')}</span>`;
    document.getElementById('lbl-days').innerHTML = `<span data-i18n="days_add">\${getLocalMsg('days_add')}</span>`;
    
    document.getElementById('fm-uuid').value = u.uuid;
    document.getElementById('fm-username').value = u.username;
    document.getElementById('fm-email').value = u.email;
    document.getElementById('fm-password').value = '';
    document.getElementById('fm-days').value = '';
    
    const roleOpt = u.role === 'admin' ? document.querySelectorAll('.role-option')[1] : document.querySelectorAll('.role-option')[0];
    const roleTxt = u.role === 'admin' ? 'Administrador (Acesso Total)' : 'Usuario Comum';
    const roleI18n = u.role === 'admin' ? 'admin_user' : 'common_user';
    selectRole(u.role || 'user', roleTxt, roleI18n, roleOpt);
    
    document.getElementById('userModal').classList.add('show');
    triggerGlobalTranslation();
}

// -----------------------------------------------------------------------
// MODAL DE SUCESSO DE CRIAÇÃO (Exibe dados para Copiar e Compartilhar no Telegram)
// -----------------------------------------------------------------------
let lastCreatedData = '';

function openSuccessModal(user, plainPassword) {
    document.getElementById('s-uuid').innerText = user.uuid;
    document.getElementById('s-username').innerText = user.username;
    document.getElementById('s-email').innerText = user.email;
    document.getElementById('s-password').innerText = plainPassword;
    
    // Formata datas para o BR padrao super legal
    const formatData = (dStr) => {
        if(!dStr) return '---';
        const [y,m,d] = dStr.split(' ')[0].split('-');
        const time = dStr.split(' ')[1] || '00:00';
        return `\${d}/\${m}/\${y} \${time.slice(0,5)}`;
    };
    
    document.getElementById('s-created').innerText = formatData(user.created_at);
    
    let isVitalicio = false;
    if(user.expires_at) {
        const eDate = new Date(user.expires_at);
        const nDate = new Date();
        const diff = (eDate - nDate) / (1000 * 60 * 60 * 24);
        if(diff > 3650) isVitalicio = true;
    }
    
    document.getElementById('s-expires').innerHTML = isVitalicio ? '<span data-i18n="lifetime">VITALÍCIO</span>' : formatData(user.expires_at);
    
    lastCreatedData = `🎉 *CUENTA CRIADA COM SUCESSO* 🎉\\n\\n👤 *Nombre:* \${user.username}\\n📧 *Email:* \${user.email}\\n🔑 *Contraseña:* \${plainPassword}\\n⏳ *Expira em:* \${isVitalicio ? 'VITALÍCIO' : formatData(user.expires_at)}\\n🆔 *UUID:* \${user.uuid}`;
    
    document.getElementById('successModal').classList.add('show');
    triggerGlobalTranslation();
}

function copyUserData() {
    navigator.clipboard.writeText(lastCreatedData).then(() => {
        showToast('success', 'toast_copied');
    });
}

function sendToTelegram() {
    window.open('https://t.me/share/url?text=' + encodeURIComponent(lastCreatedData), '_blank');
}

// -----------------------------------------------------------------------
// FETCH (Guardar ou Editar dados na API interna)
// -----------------------------------------------------------------------
function saveUser() {
    const action = document.getElementById('fm-action').value;
    const data = {
        uuid: document.getElementById('fm-uuid').value, username: document.getElementById('fm-username').value,
        email: document.getElementById('fm-email').value, password: document.getElementById('fm-password').value,
        role: document.getElementById('fm-role').value
    };
    if (action === 'create') data.days = document.getElementById('fm-days').value;
    else data.add_days = document.getElementById('fm-days').value;
    
    const btn = document.getElementById('btn-save-user');
    const originalText = btn.innerHTML;
    btn.innerHTML = `<svg style="width:18px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> ...`;
    btn.disabled = true;

    fetch('?action=' + action, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) })
    .then(r => r.json()).then(res => {
        if(res.success) { 
            if(action === 'create' && res.user) {
                closeModal('userModal');
                openSuccessModal(res.user, res.plain_password);
                btn.innerHTML = originalText; btn.disabled = false;
            } else {
                closeModal('userModal');
                showToast('success', 'toast_success'); 
                setTimeout(() => window.location.reload(), 600); 
            }
        }
        else { 
            if (res.error_code === 'max_days') {
                const isDark = document.documentElement.classList.contains('dark');
                Swal.fire({
                    html: `
                        <div class="swal-header-custom">
                            <div class="swal-icon-custom danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                            <div class="swal-header-text">
                                <h2 class="swal-title-custom">\${getLocalMsg('error_max_days_title')}</h2>
                                <p class="swal-desc-custom" style="margin-top: 6px;">\${getLocalMsg('error_max_days_desc')}</p>
                            </div>
                        </div>
                    `,
                    customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm swal-btn-danger' },
                    background: isDark ? '#1a1a1e' : '#ffffff',
                    color: isDark ? '#ffffff' : '#111827',
                    backdrop: `rgba(0,0,0,0.75)`,
                    buttonsStyling: false,
                    confirmButtonText: 'Entendi'
                });
            } else {
                showToast('error', res.error_code || res.message); 
            }
            btn.innerHTML = originalText; btn.disabled = false; 
        }
    });
}

// -----------------------------------------------------------------------
// MOTOR LÓGICO DE AÇÕES DE STATUS SWEETALERT2
// -----------------------------------------------------------------------
function openActionModal(type, target, name, config) {
    const isDark = document.documentElement.classList.contains('dark');
    let descText = getLocalMsg(config.msgKey).replace('{name}', name);
    
    let htmlContent = `
        <div class="swal-header-custom">
            <div class="swal-icon-custom \${config.color}">\${config.svg}</div>
            <div class="swal-header-text">
                <h2 class="swal-title-custom">\${getLocalMsg(config.titleKey)}</h2>
                <p class="swal-desc-custom">\${descText}</p>
            </div>
        </div>
    `;

    Swal.fire({
        html: htmlContent,
        customClass: { 
            popup: 'swal-modal-custom', 
            confirmButton: `swal-btn-confirm \${config.btnClass}`,
            cancelButton: 'swal-btn-cancel',
            actions: 'swal2-actions'
        },
        background: isDark ? '#1a1a1e' : '#ffffff',
        color: isDark ? '#ffffff' : '#111827',
        backdrop: `rgba(0,0,0,0.75)`,
        buttonsStyling: false,
        showCancelButton: true,
        confirmButtonText: getLocalMsg(config.btnKey),
        cancelButtonText: getLocalMsg('cancel')
    }).then((result) => {
        if (result.isConfirmed) {
            executeActionRaw(type, target);
        }
    });
}

function openEstadoModal(uuid, name, status, actionType) {
    let color, svg, titleKey, msgKey, btnClass, btnKey;
    
    if (actionType === 'block') {
        color = 'danger'; btnClass = 'swal-btn-danger'; titleKey = 'block_title'; msgKey = 'block_msg'; btnKey = 'btn_block';
        svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>`;
    } else if (actionType === 'unblock') {
        color = 'success'; btnClass = 'swal-btn-success'; titleKey = 'unblock_title'; msgKey = 'unblock_msg'; btnKey = 'btn_unblock';
        svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>`;
    } else if (actionType === 'suspend') {
        color = 'orange'; btnClass = 'swal-btn-orange'; titleKey = 'suspend_title'; msgKey = 'suspend_msg'; btnKey = 'btn_suspend';
        svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>`;
    } else if (actionType === 'unsuspend') {
        color = 'info'; btnClass = 'swal-btn-primary'; titleKey = 'unsuspend_title'; msgKey = 'unsuspend_msg'; btnKey = 'btn_unsuspend';
        svg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>`;
    }

    openActionModal('change_status', JSON.stringify({uuid, status}), name, { color, svg, titleKey, msgKey, btnClass, btnKey });
}

function openLoginAsModal(uuid, name) {
    openActionModal('login_as', JSON.stringify({uuid}), name, {
        color: 'info',
        svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>`,
        titleKey: 'login_title', msgKey: 'login_msg', btnClass: 'swal-btn-primary', btnKey: 'btn_login'
    });
}

function openDeleteSingle(uuid, name) {
    openActionModal('delete', JSON.stringify({uuids: [uuid]}), name, {
        color: 'danger',
        svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        titleKey: 'delete_title', msgKey: 'delete_msg', btnClass: 'swal-btn-danger', btnKey: 'btn_delete'
    });
}

function openBulkDeleteModal() {
    const selected = Array.from(document.querySelectorAll('.user-chk:checked')).map(cb => cb.value);
    openActionModal('delete', JSON.stringify({uuids: selected}), selected.length, {
        color: 'danger',
        svg: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        titleKey: 'delete_bulk_title', msgKey: 'delete_bulk_msg', btnClass: 'swal-btn-danger', btnKey: 'btn_delete'
    });
}

function executeActionRaw(action, target) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        title: 'Procesando...',
        text: 'Aguardá um instante.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        customClass: { popup: 'swal-modal-custom' },
        background: isDark ? '#1a1a1e' : '#ffffff',
        color: isDark ? '#ffffff' : '#111827'
    });

    fetch('?action=' + action, { method: 'POST', headers: {'Content-Type':'application/json'}, body: target })
    .then(r => r.json()).then(res => {
        if(res.success) {
            if (action === 'login_as') {
                window.location = '/home';
            } else {
                Swal.close();
                showToast('success', 'toast_success');
                setTimeout(() => window.location.reload(), 400); 
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: res.error_code === 'max_days' ? getLocalMsg('max_days') : (res.error_code || 'Erro desconhecido'),
                customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm swal-btn-danger' },
                background: isDark ? '#1a1a1e' : '#ffffff',
                color: isDark ? '#ffffff' : '#111827',
                buttonsStyling: false
            });
        }
    });
}
</script>
JS;

include __DIR__ . '/../includes/layout.php';
