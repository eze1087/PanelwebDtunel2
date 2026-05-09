<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Feed da Comunidade (Temas Compartilhados)
 * @description Página social para visualização, curtidas e importação de Layouts do App.
 * =======================================================================================
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbAppLayouts = __DIR__ . '/../db/app_layouts.json';
$dbCommunity  = __DIR__ . '/../db/community_themes.json';

// Checa se é Admin
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Inicializa arquivos
foreach ([$dbAppLayouts, $dbCommunity] as $file) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
        @file_put_contents($file, json_encode([]));
        @chmod($file, 0644);
    }
}

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (API DA COMUNIDADE)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    $community = json_decode(file_get_contents($dbCommunity), true) ?: [];

    // LISTAR TEMAS
    if ($action === 'list_themes') {
        // Ordenar dos mais novos pros mais velhos
        usort($community, function($a, $b) { return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0); });
        
        // Puxar Avatares dos Autores
        $usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
        $avatarMap = [];
        foreach($usuarios as $u) { $avatarMap[$u['username'] ?? ''] = $u['avatar_url'] ?? ''; }

        foreach ($community as &$theme) {
            $authorName = $theme['author'] ?? 'Usuario';
            $theme['avatar_url'] = $avatarMap[$authorName] ?? '';
            $theme['likes_count'] = count($theme['liked_by'] ?? []);
            $theme['user_liked'] = in_array($sessionEmail, $theme['liked_by'] ?? []);
            
            // Quantidade de campos
            $theme['fields_count'] = count($theme['layout_data'] ?? []);
        }
        
        echo json_encode(['success' => true, 'themes' => $community, 'is_admin' => $isAdmin]); exit;
    }

    // CURTIR / DESCURTIR TEMA
    if ($action === 'like_theme') {
        $id = strval($input['id'] ?? '');
        $found = false; $liked = false; $likesCount = 0;

        foreach ($community as &$theme) {
            if (strval($theme['id']) === $id) {
                $found = true;
                if (!isset($theme['liked_by'])) $theme['liked_by'] = [];
                
                $idx = array_search($sessionEmail, $theme['liked_by']);
                if ($idx !== false) {
                    // Descurtir
                    unset($theme['liked_by'][$idx]);
                    $theme['liked_by'] = array_values($theme['liked_by']);
                    $liked = false;
                } else {
                    // Curtir
                    $theme['liked_by'][] = $sessionEmail;
                    $liked = true;
                }
                $likesCount = count($theme['liked_by']);
                break;
            }
        }
        
        if ($found) {
            file_put_contents($dbCommunity, json_encode($community, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo json_encode(['success' => true, 'liked' => $liked, 'likes_count' => $likesCount]); exit;
        }
        echo json_encode(['success' => false, 'error' => 'Tema não encontrado.']); exit;
    }

    // IMPORTAR PARA O PERFIL DO USUÁRIO
    if ($action === 'import_theme') {
        $id = strval($input['id'] ?? '');
        $targetTheme = null;
        
        foreach ($community as &$theme) {
            if (strval($theme['id']) === $id) {
                $targetTheme = $theme;
                $theme['downloads'] = ($theme['downloads'] ?? 0) + 1;
                break;
            }
        }

        if (!$targetTheme) { echo json_encode(['success' => false, 'error' => 'Tema não encontrado.']); exit; }

        $userLayoutsDb = json_decode(file_get_contents($dbAppLayouts), true) ?: [];
        $myLayouts = array_filter($userLayoutsDb, function($l) use ($sessionEmail) { return $l['user_email'] === $sessionEmail; });
        
        if (count($myLayouts) >= 3) {
            echo json_encode(['success' => false, 'error' => 'LIMIT_REACHED', 'message' => 'Limite máximo de 3 layouts atingido na sua conta.']); exit;
        }

        $isFirst = count($myLayouts) === 0;
        $newLayout = [
            'id' => time() . rand(100, 999),
            'user_email' => $sessionEmail,
            'name' => 'Comunidade: ' . ($targetTheme['author'] ?? 'Tema'),
            'is_active' => $isFirst,
            'layout_data' => $targetTheme['layout_data'],
            'created_at' => time()
        ];
        
        array_unshift($userLayoutsDb, $newLayout);
        file_put_contents($dbAppLayouts, json_encode($userLayoutsDb, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // Salva os downloads atualizados na comunidade
        file_put_contents($dbCommunity, json_encode($community, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        echo json_encode(['success' => true]); exit;
    }

    // APAGAR TEMA (ADMIN)
    if ($action === 'delete_theme' && $isAdmin) {
        $id = strval($input['id'] ?? '');
        $filtered = array_filter($community, function($t) use ($id) { return strval($t['id']) !== $id; });
        file_put_contents($dbCommunity, json_encode(array_values($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]); exit;
    }

    // LIMPAR COMUNIDADE (ADMIN)
    if ($action === 'delete_all' && $isAdmin) {
        file_put_contents($dbCommunity, json_encode([]));
        echo json_encode(['success' => true]); exit;
    }

    // DELETAR MÚLTIPLOS (ADMIN)
    if ($action === 'delete_selected' && $isAdmin) {
        $idsToDelete = $input['ids'] ?? [];
        if(!empty($idsToDelete)) {
            $filtered = array_filter($community, function($t) use ($idsToDelete) { return !in_array(strval($t['id']), $idsToDelete); });
            file_put_contents($dbCommunity, json_encode(array_values($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        echo json_encode(['success' => true]); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação não permitida ou desconhecida.']); exit;
}

$pageTitle = 'Temas da Comunidade';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - COMUNIDADE DTUNNELMOD
   ========================================================================== */
body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) { padding-right: 0 !important; overflow-y: auto !important; }

.community-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --primary: #3b82f6; --success: #10b981; --danger: #ef4444;
    --mock-bg: #080e16c6; --mock-el: #1d242e73; --mock-border: rgba(255,255,255,0.05); --mock-text: #ffffff; --mock-icon: #ffffff;
    padding: 16px; max-width: 1200px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; height: calc(100vh - 70px);
}
:root.dark .community-wrapper, .dark .community-wrapper, body.dark .community-wrapper {
    --card-bg: #161618; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #1e1e22; 
}
.community-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; box-sizing: border-box; }

/* TOPO (Header da Comunidade) */
.comm-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;
}
.comm-badges { display: flex; gap: 10px; flex-wrap: wrap; }
.comm-badge {
    background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main);
    padding: 10px 20px; border-radius: 50px; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 8px;
}

/* MODO ADMIN - BARRA FLUTUANTE */
.admin-bar {
    background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px;
    padding: 16px 20px; display: none; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap:10px;
}
.admin-bar.show { display: flex; }
.admin-check-all { display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 0.9rem; cursor: pointer;}
.admin-check-all input { width: 18px; height: 18px; accent-color: var(--danger); cursor: pointer;}
.btn-admin-del {
    background: #ef4444; color: #fff; border: none; padding: 10px 16px; border-radius: 12px;
    font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.2s;
}
.btn-admin-del:active { transform: scale(0.95); }

/* GRID DE TEMAS */
.themes-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;
    flex: 1; overflow-y: auto; padding-bottom: 40px; scrollbar-width: none;
}
.themes-grid::-webkit-scrollbar { display: none; }

/* CARD DE TEMA INDIVIDUAL */
.theme-card {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px;
    padding: 20px; display: flex; flex-direction: column; gap: 16px; position: relative; transition: 0.2s;
}
.dark .theme-card { box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.theme-card:hover { border-color: var(--primary); }

.tc-header { display: flex; justify-content: space-between; align-items: flex-start; }
.tc-info { display: flex; flex-direction: column; gap: 2px;}
.tc-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;}
.tc-title { font-size: 1rem; font-weight: 800; color: var(--text-main); }

.tc-avatar {
    width: 44px; height: 44px; border-radius: 50%; background: var(--inner-bg); border: 2px solid var(--card-border);
    display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--text-main); font-size: 1rem; overflow: hidden; flex-shrink: 0;
}
.tc-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* CHECKBOX ADMIN NO CARD */
.card-admin-check { position: absolute; top: 20px; left: 20px; z-index: 10; display: none;}
.admin-active .card-admin-check { display: block; }
.card-admin-check input { width: 22px; height: 22px; accent-color: var(--danger); cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.5); border-radius: 6px;}

/* ÁREA DO MOCKUP (QUADRADO E PERFEITO) */
.phone-mockup-container {
    width: 100%; max-width: 260px; margin: 0 auto;
    background: var(--mock-bg); border-radius: 24px; padding: 16px;
    border: 3px solid var(--card-border); display: flex; flex-direction: column; gap: 10px;
    position: relative; background-size: cover; background-position: center; overflow: hidden;
}
.mock-field { background: var(--mock-el); border-radius: var(--mock-radius, 25px); padding: 10px 14px; display: flex; align-items: center; gap: 10px; color: var(--mock-text); font-size: 0.75rem; font-weight: 600; border: 1px solid var(--mock-border); }
.mock-field svg { width: 14px; color: var(--mock-icon); opacity: 0.8;}
.mock-select { justify-content: space-between; }
.mock-btn-row { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 4px; flex-wrap: nowrap;}
.mock-btn-main { background: var(--mock-btn); flex: 1; padding: 10px 8px; border-radius: var(--mock-btn-radius, 25px); color: var(--mock-text); font-weight: 800; font-size: 0.75rem; text-align: center; border: 1px solid var(--mock-border); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.mock-btn-circle { width: 32px; height: 32px; border-radius: 50%; background: var(--mock-btn); display: flex; align-items: center; justify-content: center; color: var(--mock-icon); border: 1px solid var(--mock-border); flex-shrink: 0; }
.mock-btn-circle svg { width: 14px; }
.mock-logger { background: var(--mock-card); height: 20px; border-radius: var(--mock-radius, 25px); margin-top: 8px; border: 1px solid var(--mock-border); opacity: 0.8;}

/* FOOTER DO CARD (Acciones e Descripción) */
.tc-desc { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; margin: 10px 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.tc-actions { display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto;}
.tc-stats { display: flex; gap: 8px; flex-wrap: wrap;}
.stat-badge { background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-muted); font-size: 0.7rem; font-weight: 800; padding: 6px 12px; border-radius: 8px;}

.tc-buttons { display: flex; align-items: center; gap: 12px; }

/* Botão de Curtir Animado */
.btn-like {
    display: flex; flex-direction: column; align-items: center; gap: 4px; background: transparent; border: none;
    color: var(--text-muted); font-weight: 800; font-size: 0.75rem; cursor: pointer; outline: none; transition: 0.2s;
}
.btn-like svg { width: 24px; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.btn-like.liked { color: #ef4444; }
.btn-like.liked svg { fill: #ef4444; stroke: #ef4444; transform: scale(1.15); }
.btn-like:active svg { transform: scale(0.8); }

/* Botão Importar */
.btn-import {
    display: flex; flex-direction: column; align-items: center; gap: 4px; background: transparent; border: none;
    color: var(--text-main); font-weight: 800; font-size: 0.75rem; cursor: pointer; outline: none; transition: 0.2s;
}
.btn-import svg { width: 24px; color: var(--text-main); }
.btn-import:active { transform: scale(0.9); }

/* VAZIO */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; grid-column: 1 / -1; height: 50vh; text-align: center; gap: 10px;}
.es-icon-wrap { display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: var(--card-bg); border: 2px solid var(--card-border); color: var(--text-muted); margin-bottom: 8px;}
.es-icon-wrap svg { width: 28px; height: 28px; display: block; margin: 0; }
.es-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.es-desc { font-size: 0.85rem; font-weight: 500; color: var(--text-subtle); margin: 0; max-width: 250px; line-height: 1.4; }

/* GIRA GIRA CARREGANDO */
.spin-anim { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* TOASTS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--success); flex-shrink: 0;}
.toast.info .toast-icon { background: var(--primary); }
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}

/* FORÇA BRUTA SWAL */
body.swal2-shown .swal2-container, .swal2-container { z-index: 999999999 !important; }
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 6px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 24px !important; text-align: left !important;}
.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important;}
.swal-btn-cancel { background: var(--inner-bg) !important; color: var(--text-main) !important; border: 1px solid var(--card-border) !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; color: #fff !important;}
</style>

<div id="toast-container"></div>

<div class="community-wrapper <?= $isAdmin ? 'admin-active' : '' ?>">
    
    <div class="comm-header">
        <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0;" data-i18n-th="th_title">Temas da comunidade</h1>
        <div class="comm-badges">
            <div class="comm-badge"><span id="total-themes">0</span> <span data-i18n-th="th_published">temas publicados</span></div>
            <div class="comm-badge" data-i18n-th="th_feed">Feed da comunidade</div>
        </div>
    </div>

    <?php if($isAdmin): ?>
    <div class="admin-bar" id="admin-bar">
        <label class="admin-check-all">
            <input type="checkbox" id="check-all" onchange="Community.toggleAll(this.checked)">
            Selecionar Todos
        </label>
        <div style="display:flex; gap:10px;">
            <button class="btn-admin-del" onclick="Community.deleteSelected()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Eliminar Selecionados
            </button>
            <button class="btn-admin-del" style="background:transparent; border:1px solid #ef4444; color:#ef4444;" onclick="Community.deleteAll()">
                Limpar Comunidade
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="themes-grid" id="themes-grid">
        </div>

</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
/**
 * DICIONÁRIO DE TRADUÇÃO DA PÁGINA DE TEMAS
 */
const thDict = {
    'pt': {
        'th_title': 'Temas da comunidade', 'th_published': 'temas publicados', 'th_feed': 'Feed da comunidade',
        'lbl_tema': 'TEMA DA COMUNIDADE', 'lbl_comunidade': 'Comunidade',
        'lbl_campos': 'campos', 'lbl_curtidas': 'curtidas', 'lbl_importar': 'Importar',
        'toast_import': 'Tema importado para sua conta!', 'toast_liked': 'Tema curtido!', 'toast_unliked': 'Curtida removida!',
        'toast_del': 'Tema(s) excluído(s) con éxito!',
        'empty_title': 'Ningún tema encontrado', 'empty_desc': 'Seja o primeiro a compartilhar um layout incrível com a comunidade!',
        'limit_msg': 'Você atingiu o limite de 3 layouts salvos. Exclua um na página Aplicación para importar novos.',
        'del_title': 'Eliminar Temas?', 'del_desc': 'Tem certeza que deseja apagar os temas selecionados da comunidade? Isso afetará todos os usuários.',
        'btn_del': 'Eliminar', 'btn_cancel': 'Cancelar'
    },
    'en': {
        'th_title': 'Community Themes', 'th_published': 'themes published', 'th_feed': 'Community Feed',
        'lbl_tema': 'COMMUNITY THEME', 'lbl_comunidade': 'Community',
        'lbl_campos': 'fields', 'lbl_curtidas': 'likes', 'lbl_importar': 'Import',
        'toast_import': 'Theme imported to your account!', 'toast_liked': 'Theme liked!', 'toast_unliked': 'Like removed!',
        'toast_del': 'Theme(s) successfully deleted!',
        'empty_title': 'No themes found', 'empty_desc': 'Be the first to share an amazing layout with the community!',
        'limit_msg': 'You have reached the limit of 3 saved layouts. Delete one on the App page to import new ones.',
        'del_title': 'Delete Themes?', 'del_desc': 'Are you sure you want to delete the selected themes from the community? This will affect all users.',
        'btn_del': 'Delete', 'btn_cancel': 'Cancel'
    },
    'es': {
        'th_title': 'Temas de la comunidad', 'th_published': 'temas publicados', 'th_feed': 'Feed de la comunidad',
        'lbl_tema': 'TEMA DE LA COMUNIDAD', 'lbl_comunidade': 'Comunidad',
        'lbl_campos': 'campos', 'lbl_curtidas': 'me gusta', 'lbl_importar': 'Importar',
        'toast_import': '¡Tema importado a tu cuenta!', 'toast_liked': '¡Me gusta!', 'toast_unliked': '¡Me gusta eliminado!',
        'toast_del': '¡Tema(s) eliminado(s) con éxito!',
        'empty_title': 'No se encontraron temas', 'empty_desc': '¡Sé el primero en compartir un diseño increíble con la comunidad!',
        'limit_msg': 'Has alcanzado el límite de 3 diseños guardados. Elimina uno en la página de Aplicación para importar nuevos.',
        'del_title': '¿Eliminar Temas?', 'del_desc': '¿Estás seguro de que deseas borrar los temas seleccionados de la comunidad? Esto afectará a todos los usuarios.',
        'btn_del': 'Eliminar', 'btn_cancel': 'Cancelar'
    }
};

function getThMsg(key) { 
    const lang = localStorage.getItem('app_language') || 'pt';
    const currentDict = thDict[lang] || thDict['pt'];
    return currentDict[key] || thDict['pt'][key] || key; 
}

function applyThI18n() { 
    document.querySelectorAll('[data-i18n-th]').forEach(el => { 
        el.innerHTML = getThMsg(el.getAttribute('data-i18n-th')); 
    }); 
}

// Escuta cliques de idiomas no Header Global
document.addEventListener('click', (e) => {
    if(e.target.closest('.lang-option')) {
        setTimeout(() => { applyThI18n(); Community.fetch(); }, 150);
    }
});

// Sobrescreve seguro da função do header caso exista
if (typeof window.selectAppLang === 'function') {
    const originalSelectAppLang = window.selectAppLang;
    window.selectAppLang = function(langCode) {
        originalSelectAppLang(langCode);
        applyThI18n();
        Community.fetch();
    };
}

function showToastRaw(text, type = 'success') {
    const container = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast \${type}`;
    let iconSvg = '<polyline points="20 6 9 17 4 12"/>';
    if (type === 'info') iconSvg = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
    if (type === 'error') iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${text}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}

const Community = (function() {
    let themesData = [];
    let isAdminUser = false;

    function getVal(arr, key, def) {
        if(!Array.isArray(arr)) return def;
        let item = arr.find(x => x.name === key);
        return item ? (item.value ?? def) : def;
    }

    // Pega iniciais pro avatar caso não tenha foto
    function getInitials(name) {
        let words = (name||'U').trim().split(' ');
        let inits = '';
        for(let w of words) { if(w) inits += w[0].toUpperCase(); if(inits.length >= 2) break; }
        return inits || 'U';
    }

    function fetchThemes() {
        fetch('?action=list_themes', {method:'POST'}).then(r=>r.json()).then(res => {
            if(res.success) {
                themesData = res.themes;
                isAdminUser = res.is_admin;
                document.getElementById('total-themes').innerText = themesData.length;
                if(isAdminUser) document.getElementById('admin-bar').classList.add('show');
                render();
            }
        });
    }

    function render() {
        const grid = document.getElementById('themes-grid');
        if(themesData.length === 0) {
            grid.innerHTML = `<div class="empty-state"><div class="es-icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div><h3 class="es-title">\${getThMsg('empty_title')}</h3><p class="es-desc">\${getThMsg('empty_desc')}</p></div>`;
            return;
        }

        let html = '';
        themesData.forEach(t => {
            const arr = t.layout_data;
            const bgType = getVal(arr, 'APP_BACKGROUND_TYPE', {selected: 'COLOR'}).selected;
            let mockBg = getVal(arr, 'APP_BACKGROUND_COLOR', '#080e16c6');
            if(bgType === 'IMAGE') { const imgUrl = getVal(arr, 'APP_BACKGROUND_IMAGE', ''); if(imgUrl) mockBg = `url('\${imgUrl}')`; }

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

            const useWebviewVal = getVal(arr, 'APP_LAYOUT_WEBVIEW_ENABLED', false);
            const isWebviewActive = (useWebviewVal === true || String(useWebviewVal) === "true" || useWebviewVal === 1);
            const htmlWebView = getVal(arr, 'APP_LAYOUT_WEBVIEW', '');

            const mockStyle = `--mock-bg: \${mockBg}; --mock-el: \${mockInput}; --mock-card: \${mockCard}; --mock-btn: \${mockBtn}; --mock-text: \${mockText}; --mock-icon: \${mockIcon}; --mock-radius: \${mockRadI}; --mock-btn-radius: \${mockRadB};`;

            let phoneInnerHtml = '';
            if(isWebviewActive && htmlWebView.trim() !== '') {
                const safeHtml = htmlWebView.replace(/"/g, '&quot;');
                phoneInnerHtml = `<iframe srcdoc="\${safeHtml}" style="width:100%; height:100%; min-height: 380px; border:none; border-radius: 20px; flex: 1; background: transparent; pointer-events:none;"></iframe>`;
            } else {
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

            const avatarHtml = t.avatar_url 
                ? `<img src="\${t.avatar_url}" alt="User">` 
                : getInitials(t.author);

            const heartClass = t.user_liked ? 'liked' : '';
            const desc = t.description ? `<p class="tc-desc">\${t.description}</p>` : '';

            // Checkbox admin se for admin
            const adminCheck = isAdminUser ? `<div class="card-admin-check"><input type="checkbox" class="chk-theme" value="\${t.id}"></div>` : '';

            html += `
                <div class="theme-card" id="tc-\${t.id}">
                    \${adminCheck}
                    <div class="tc-header">
                        <div class="tc-info">
                            <span class="tc-label">\${getThMsg('lbl_tema')}</span>
                            <span class="tc-title">\${t.author || getThMsg('lbl_comunidade')}</span>
                        </div>
                        <div class="tc-avatar">\${avatarHtml}</div>
                    </div>
                    
                    <div class="phone-mockup-container" style="\${mockStyle}; \${bgType === 'IMAGE' ? 'background-image: '+mockBg+'; background-size: cover; background-position: center;' : 'background-color: '+mockBg+';'}">
                        \${phoneInnerHtml}
                    </div>

                    \${desc}

                    <div class="tc-actions">
                        <div class="tc-stats">
                            <span class="stat-badge">\${t.fields_count} \${getThMsg('lbl_campos')}</span>
                        </div>
                        <div class="tc-buttons">
                            <button class="btn-like \${heartClass}" onclick="Community.like('\${t.id}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                <span id="lk-count-\${t.id}">\${t.likes_count}</span>
                            </button>
                            <button class="btn-import" onclick="Community.importTheme('\${t.id}')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span>\${getThMsg('lbl_importar')}</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        grid.innerHTML = html;
    }

    function like(id) {
        fetch('?action=like_theme', {method:'POST', body: JSON.stringify({id})})
        .then(r=>r.json()).then(res=>{
            if(res.success) {
                const btn = document.querySelector(`#tc-\${id} .btn-like`);
                const countSpan = document.getElementById(`lk-count-\${id}`);
                if(res.liked) {
                    btn.classList.add('liked');
                    showToastRaw(getThMsg('toast_liked'), 'success');
                } else {
                    btn.classList.remove('liked');
                }
                countSpan.innerText = res.likes_count;
            }
        });
    }

    function importTheme(id) {
        const isDark = document.documentElement.classList.contains('dark');
        Swal.fire({title:'Importando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
        fetch('?action=import_theme', {method:'POST', body: JSON.stringify({id})})
        .then(r=>r.json()).then(res=>{
            if(res.success) {
                Swal.close();
                showToastRaw(getThMsg('toast_import'), 'success');
                // Incrementa no visual pra dar a sensação imediata
                setTimeout(fetchThemes, 1000); 
            } else {
                Swal.fire('Atenção', res.message || res.error || getThMsg('limit_msg'), 'warning');
            }
        });
    }

    // ====== FUNÇÕES ADMIN ======
    function toggleAll(isChecked) {
        document.querySelectorAll('.chk-theme').forEach(cb => cb.checked = isChecked);
    }

    function deleteSelected() {
        const selected = Array.from(document.querySelectorAll('.chk-theme:checked')).map(cb => cb.value);
        if(selected.length === 0) return;

        const isDark = document.documentElement.classList.contains('dark');
        Swal.fire({
            title: getThMsg('del_title'), text: getThMsg('del_desc'), icon: 'warning',
            showCancelButton: true, confirmButtonText: getThMsg('btn_del'), cancelButtonText: getThMsg('btn_cancel'),
            customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel' },
            background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827'
        }).then(res => {
            if(res.isConfirmed) {
                Swal.fire({title:'Apagando...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
                fetch('?action=delete_selected', {method:'POST', body: JSON.stringify({ids: selected})})
                .then(r=>r.json()).then(data=>{
                    if(data.success) { Swal.close(); showToastRaw(getThMsg('toast_del')); fetchThemes(); document.getElementById('check-all').checked = false;}
                });
            }
        });
    }

    function deleteAll() {
        const isDark = document.documentElement.classList.contains('dark');
        Swal.fire({
            title: 'Limpar Comunidade?', text: 'Cuidado! Todos os temas de todos os usuários serão deletados irreversivelmente!', icon: 'error',
            showCancelButton: true, confirmButtonText: 'Sim, Apagar Tudo', cancelButtonText: getThMsg('btn_cancel'),
            customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel' },
            background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827'
        }).then(res => {
            if(res.isConfirmed) {
                Swal.fire({title:'Apagando Tudo...', didOpen:()=>{Swal.showLoading()}, allowOutsideClick:false, background: isDark ? '#1a1a1e' : '#ffffff', customClass: {popup: 'swal-modal-custom'}});
                fetch('?action=delete_all', {method:'POST'}).then(r=>r.json()).then(data=>{
                    if(data.success) { Swal.close(); showToastRaw(getThMsg('toast_del')); fetchThemes(); document.getElementById('check-all').checked = false;}
                });
            }
        });
    }

    return { fetch: fetchThemes, like, importTheme, toggleAll, deleteSelected, deleteAll };
})();

document.addEventListener('DOMContentLoaded', () => { 
    applyThI18n();
    Community.fetch(); 
});
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }
?>
