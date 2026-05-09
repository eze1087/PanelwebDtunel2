<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gestão de Sesiones Premium V4
 * @description Rastreamento cirúrgico de dispositivos, IP real, GPS e encerramento remoto.
 * =======================================================================================
 */

// Proteção da rota
if (!defined('DTUNNEL_APP')) { 
    header('Location: /404'); 
    exit; 
}

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$sessionEmail = $_SESSION['email'] ?? '';

if (empty($sessionEmail)) {
    header('Location: /login');
    exit;
}

// ======================================================================
// 1. MOTORES DE ANÁLISE PROFUNDA (IP, SO, NAVEGADOR)
// ======================================================================

function getRealClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

function getExactOS($ua) {
    $os = "Desconhecido";
    $os_array = [
        '/windows nt 10/i'      =>  'Windows 10/11',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/macintosh|mac os x/i' =>  'macOS',
        '/mac_powerpc/i'        =>  'Mac OS 9',
        '/linux/i'              =>  'Linux',
        '/ubuntu/i'             =>  'Ubuntu',
        '/iphone/i'             =>  'iPhone (iOS)',
        '/ipod/i'               =>  'iPod (iOS)',
        '/ipad/i'               =>  'iPad (iOS)',
        '/android/i'            =>  'Android',
        '/blackberry/i'         =>  'BlackBerry',
        '/webos/i'              =>  'Mobile'
    ];
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $ua)) { $os = $value; break; }
    }
    return $os;
}

function getExactBrowser($ua) {
    $browser = "Desconhecido";
    // Ordem importa: Navegadores in-app primeiro, derivados depois, originais por último
    if (preg_match('/Telegram/i', $ua)) { $browser = 'Telegram In-App'; }
    elseif (preg_match('/Instagram/i', $ua)) { $browser = 'Instagram In-App'; }
    elseif (preg_match('/FBAN|FBAV/i', $ua)) { $browser = 'Facebook In-App'; }
    elseif (preg_match('/MiuiBrowser/i', $ua)) { $browser = 'Miui Browser'; }
    elseif (preg_match('/SamsungBrowser/i', $ua)) { $browser = 'Samsung Internet'; }
    elseif (preg_match('/Edg/i', $ua)) { $browser = 'Microsoft Edge'; }
    elseif (preg_match('/OPR|Opera/i', $ua)) { $browser = 'Opera'; }
    elseif (preg_match('/CriOS/i', $ua)) { $browser = 'Chrome (iOS)'; }
    elseif (preg_match('/Chrome/i', $ua)) { $browser = 'Google Chrome'; }
    elseif (preg_match('/FxiOS/i', $ua)) { $browser = 'Firefox (iOS)'; }
    elseif (preg_match('/Firefox/i', $ua)) { $browser = 'Mozilla Firefox'; }
    elseif (preg_match('/Safari/i', $ua)) { $browser = 'Apple Safari'; }
    elseif (preg_match('/Trident\/7.0/i', $ua)) { $browser = 'Internet Explorer 11'; }
    
    return $browser;
}

// ======================================================================
// 2. GESTÃO DO BANCO DE DADOS DE SESSÕES
// ======================================================================
$dbSessoesFile = __DIR__ . '/../db/sessoes.json';
clearstatcache(true, $dbSessoesFile);

if (!file_exists($dbSessoesFile)) {
    if (!is_dir(dirname($dbSessoesFile))) mkdir(dirname($dbSessoesFile), 0755, true);
    file_put_contents($dbSessoesFile, json_encode([]));
    chmod($dbSessoesFile, 0644);
}

$sessoesGlobais = json_decode(file_get_contents($dbSessoesFile), true) ?: [];

$currentSessionId = session_id();
$currentIP = getRealClientIP();
$currentUA = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';
$sessionFound = false;

// Verifica se a sessão atual existe para atualizar o last_activity
foreach ($sessoesGlobais as &$sess) {
    if ($sess['session_id'] === $currentSessionId && $sess['email'] === $sessionEmail) {
        $sess['last_activity'] = time();
        $sessionFound = true;
        break;
    }
}
unset($sess);

// Se não existir, faz a varredura real de GPS via IP
if (!$sessionFound) {
    $loc = ['city' => 'Desconhecida', 'region' => 'Local', 'country' => 'Desconhecido', 'lat' => 0, 'lon' => 0];
    
    // API de Geolocalização (Apenas em produção com IP real)
    if ($currentIP !== '127.0.0.1' && $currentIP !== '::1') {
        $ch = curl_init("http://ip-api.com/json/{$currentIP}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout de 3s para não travar a página
        $ipData = curl_exec($ch);
        curl_close($ch);
        
        if ($ipData) {
            $j = json_decode($ipData, true);
            if (isset($j['status']) && $j['status'] === 'success') {
                $loc = [
                    'city' => $j['city'], 
                    'region' => $j['regionName'], 
                    'country' => $j['country'], 
                    'lat' => $j['lat'], 
                    'lon' => $j['lon']
                ];
            }
        }
    }

    $sessoesGlobais[] = [
        'session_id' => $currentSessionId,
        'email' => $sessionEmail,
        'ip' => $currentIP,
        'os' => getExactOS($currentUA),
        'browser' => getExactBrowser($currentUA),
        'user_agent' => $currentUA,
        'location' => $loc['country'] . ', ' . $loc['city'] . '/' . $loc['region'],
        'lat' => $loc['lat'],
        'lon' => $loc['lon'],
        'created_at' => time(),
        'last_activity' => time()
    ];
    file_put_contents($dbSessoesFile, json_encode($sessoesGlobais, JSON_PRETTY_PRINT));
}

// ======================================================================
// 3. AÇÃO AJAX: ENCERRAR SESSÃO REMOTA (MATA A SESSÃO DO OUTRO)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax_terminate'])) {
    header('Content-Type: application/json');
    $targetId = json_decode(file_get_contents('php://input'), true)['session_id'] ?? '';
    
    $newSessoes = [];
    $terminated = false;
    
    // Varre o DB. Se achar a sessão alvo, NÃO adiciona ao novo array (exclui)
    foreach ($sessoesGlobais as $s) {
        if ($s['session_id'] === $targetId && $s['email'] === $sessionEmail) {
            $terminated = true;
        } else {
            $newSessoes[] = $s;
        }
    }
    
    if ($terminated) {
        // Ao salvar o DB sem o session_id do invasor/aparelho secundário,
        // O sistema "Trem Bala" do index/home daquele aparelho vai detectar e deslogar ele na hora!
        file_put_contents($dbSessoesFile, json_encode($newSessoes, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Sesión não encontrada ou já encerrada.']);
    }
    exit;
}

// Filtra e organiza para a Tela
$minhasSessoes = array_filter($sessoesGlobais, function($s) use ($sessionEmail) {
    return $s['email'] === $sessionEmail;
});

// A sessão em uso fica sempre no topo
usort($minhasSessoes, function($a, $b) use ($currentSessionId) {
    if ($a['session_id'] === $currentSessionId) return -1;
    if ($b['session_id'] === $currentSessionId) return 1;
    return $b['last_activity'] <=> $a['last_activity']; // As mais recentes depois
});

$totalAtivas = count($minhasSessoes);
$pageTitle = 'Sesiones';
ob_start();
?>

<!-- Importação SweetAlert2 Oficial -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM DA PÁGINA
   ========================================================================== */
.sessions-wrapper {
    --card-bg: #ffffff; --card-border: #e5e7eb; --text-main: #111827; --text-muted: #6b7280; --text-subtle: #9ca3af;
    --inner-bg: #f9fafb; --icon-bg: #f3f4f6; --primary: #3b82f6; --success: #10b981; --danger: #ef4444;
    padding: 16px; max-width: 800px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
}

:root.dark .sessions-wrapper, .dark .sessions-wrapper, body.dark .sessions-wrapper {
    --card-bg: #1a1a1e; --card-border: #27272a; --text-main: #f9fafb; --text-muted: #a1a1aa; --text-subtle: #71717a;
    --inner-bg: #121214; --icon-bg: rgba(255, 255, 255, 0.03);
}

.sessions-wrapper * { -webkit-tap-highlight-color: transparent !important; outline: none; }

/* Cabeçalho Animado */
.sess-header { margin-bottom: 24px; animation: slideDown 0.4s ease-out; }
.sess-header h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0 0 16px 0; }

.stats-bar { display: flex; gap: 12px; }
.stat-pill { 
    flex: 1; background: var(--card-bg); border: 1px solid var(--card-border); 
    padding: 14px; border-radius: 16px; font-size: 0.9rem; font-weight: 700; color: var(--text-muted); 
    display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}
.stat-pill.active { color: var(--success); border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05); }

@keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

.list-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 16px; }

/* Lista de Sesiones e Scroll Interno */
.sessions-list {
    display: flex; flex-direction: column; gap: 16px;
    max-height: calc(100vh - 230px); overflow-y: auto; scrollbar-width: none; padding-bottom: 40px;
}
.sessions-list::-webkit-scrollbar { display: none; }

/* Card de Sesión (Efeito Impulso) */
.session-card {
    background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px;
    padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeIn 0.5s ease-out forwards; opacity: 0;
}
.dark .session-card { box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
@keyframes fadeIn { to { opacity: 1; } }

/* Topo do Card */
.sc-top { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.sc-icon {
    width: 54px; height: 54px; border-radius: 14px; background: var(--icon-bg); border: 1px solid var(--card-border);
    display: flex; align-items: center; justify-content: center; color: var(--text-main); flex-shrink: 0;
}
.sc-os-info { flex: 1; }
.sc-os-name { font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px; }
.sc-browser { font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }

.badge-current {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 50px;
    background: rgba(16, 185, 129, 0.1); color: var(--success); font-size: 0.7rem; font-weight: 800;
    border: 1px solid rgba(16, 185, 129, 0.3); text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 16px;
}

/* Caixas de Informação */
.field-box {
    background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 14px;
    padding: 16px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px;
    text-align: left; transition: border-color 0.3s;
}
.field-box:hover { border-color: var(--primary); }
.field-label { font-size: 0.75rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 1px; }
.field-value { font-size: 0.95rem; color: var(--text-main); font-weight: 700; word-break: break-all; }
.field-value.mono { font-family: 'Space Grotesk', monospace; font-size: 0.85rem; color: var(--text-muted); }

/* Mapa GPS Imersivo do Google/OSM */
.map-container {
    width: 100%; height: 160px; border-radius: 12px; overflow: hidden; margin-top: 10px;
    border: 1px solid var(--card-border); background: var(--icon-bg); position: relative;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
}
.map-container iframe { width: 100%; height: 100%; border: none; filter: brightness(0.95); transition: filter 0.3s; }
.dark .map-container iframe { filter: brightness(0.75) invert(1) hue-rotate(180deg) saturate(150%); } /* Deixa o mapa com aspecto noturno top */

/* Caixa de Token com Rolagem Horizontal */
.token-wrap { display: flex; flex-direction: column; gap: 10px; }
.btn-copy-token {
    width: 100%; background: transparent; border: 1px dashed var(--text-subtle); color: var(--text-main);
    padding: 14px; border-radius: 12px; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px;
    cursor: pointer; transition: all 0.2s; outline: none;
}
.btn-copy-token:active { transform: scale(0.96); background: var(--icon-bg); }
.token-scroll-box {
    background: var(--card-bg); padding: 12px; border-radius: 10px; border: 1px solid var(--card-border);
    overflow-x: auto; white-space: nowrap; font-family: 'Space Grotesk', monospace; font-size: 0.85rem; color: var(--text-muted);
    scrollbar-width: none; /* Esconde scrollbar nativo */
}
.token-scroll-box::-webkit-scrollbar { display: none; }

/* Botões Finais de Ação */
.btn-action-master {
    width: 100%; padding: 18px; border-radius: 16px; font-weight: 800; font-size: 1rem;
    display: flex; align-items: center; justify-content: center; gap: 10px; border: none; outline: none;
    transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), filter 0.2s, box-shadow 0.2s; margin-top: 20px; font-family: 'Manrope', sans-serif;
}
.btn-action-master:active { transform: scale(0.95); }
.btn-current { background: var(--inner-bg); color: var(--text-muted); border: 1px dashed var(--card-border); cursor: not-allowed; }
.btn-terminate { background: var(--danger); color: #fff; cursor: pointer; box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3); }
.btn-terminate:hover { filter: brightness(1.1); }

/* SWEETALERT2 STYLES (Clean/Square Premium) */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin: 0 !important; }
.swal-desc-custom { font-size: 0.95rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; }
.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 24px !important;}
.swal-btn-confirm, .swal-btn-cancel { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; font-size: 1rem !important; border: none !important; cursor: pointer !important; transition: transform 0.15s !important; font-family: 'Manrope', sans-serif !important; }
.swal-btn-confirm:active, .swal-btn-cancel:active { transform: scale(0.95) !important; }
.swal-btn-confirm { background: #ef4444 !important; color: #fff !important; }
.swal-btn-cancel { background: var(--inner-bg) !important; border: 1px solid var(--card-border) !important; color: var(--text-main) !important; }

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

<div class="sessions-wrapper">
    <div class="sess-header">
        <h1 data-i18n="sessions">Sesiones</h1>
        <div class="stats-bar">
            <div class="stat-pill">
                <span id="stat-total"><?= $totalAtivas ?></span> <span data-i18n="active_sessions">sessões ativas</span>
            </div>
            <div class="stat-pill active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span data-i18n="current_session">1 atual</span>
            </div>
        </div>
    </div>

    <h2 class="list-title" data-i18n="sessions_list">Lista de sessões</h2>

    <div class="sessions-list">
        <?php foreach($minhasSessoes as $index => $sess): 
            $isCurrent = ($sess['session_id'] === $currentSessionId);
            // Define o ícone de acordo com o SO
            if (strpos(strtolower($sess['os']), 'android') !== false || strpos(strtolower($sess['os']), 'iphone') !== false || strpos(strtolower($sess['os']), 'mobile') !== false) {
                $icon = '<rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>';
            } else {
                $icon = '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>';
            }
            
            // Lógica do Mapa GPS (Bbox ampliado para visualização melhor)
            $lat = $sess['lat'] ?? 0;
            $lon = $sess['lon'] ?? 0;
            $bbox = ($lon - 0.08) . ',' . ($lat - 0.08) . ',' . ($lon + 0.08) . ',' . ($lat + 0.08);
            $mapUrl = "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$lat},{$lon}";
        ?>
        <div class="session-card" id="card-<?= htmlspecialchars($sess['session_id']) ?>" style="animation-delay: <?= $index * 0.1 ?>s;">
            
            <div class="sc-top">
                <div class="sc-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:26px;"><?= $icon ?></svg>
                </div>
                <div class="sc-os-info">
                    <div class="sc-os-name"><?= htmlspecialchars($sess['os']) ?></div>
                    <div class="sc-browser"><?= htmlspecialchars($sess['browser']) ?></div>
                </div>
            </div>

            <?php if($isCurrent): ?>
                <div class="badge-current">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:12px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span data-i18n="this_session">Sesión atual</span>
                </div>
            <?php endif; ?>

            <div class="field-box">
                <span class="field-label">IP</span>
                <span class="field-value"><?= htmlspecialchars($sess['ip']) ?></span>
            </div>

            <div class="field-box">
                <span class="field-label" data-i18n="browser">NAVEGADOR</span>
                <span class="field-value"><?= htmlspecialchars($sess['browser']) ?></span>
            </div>

            <div class="field-box">
                <span class="field-label" data-i18n="location">LOCALIZAÇÃO</span>
                <span class="field-value" style="font-size:0.9rem;"><?= htmlspecialchars($sess['location']) ?></span>
                
                <?php if ($lat != 0): ?>
                <a href="https://www.openstreetmap.org/?mlat=<?= $lat ?>&mlon=<?= $lon ?>#map=14/<?= $lat ?>/<?= $lon ?>" target="_blank" class="map-container" title="Abrir no Mapa Completo">
                    <iframe src="<?= $mapUrl ?>"></iframe>
                </a>
                <?php endif; ?>
            </div>

            <div class="field-box">
                <span class="field-label">USER-AGENT REAL</span>
                <span class="field-value mono"><?= htmlspecialchars($sess['user_agent']) ?></span>
            </div>

            <div class="field-box token-wrap">
                <span class="field-label">TOKEN (SESSION ID)</span>
                <button class="btn-copy-token" onclick="copyToken('<?= htmlspecialchars($sess['session_id']) ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span data-i18n="copy">Copiar</span>
                </button>
                <div class="token-scroll-box">
                    <?= htmlspecialchars($sess['session_id']) ?>
                </div>
            </div>

            <?php if($isCurrent): ?>
                <button class="btn-action-master btn-current" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span data-i18n="this_session">Sesión atual</span>
                </button>
            <?php else: ?>
                <button class="btn-action-master btn-terminate" onclick="confirmTerminate('<?= htmlspecialchars($sess['session_id']) ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    <span data-i18n="end_session">Encerrar sessão</span>
                </button>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

// ==========================================================================
// JAVASCRIPT: I18N, TOASTS, SWEETALERT2
// ==========================================================================
$extraJs = <<<JS
<script>
const sessDict = {
    'pt': {
        'sessions': 'Sesiones', 'active_sessions': 'sessões ativas', 'current_session': '1 atual', 'sessions_list': 'Lista de sessões',
        'this_session': 'Sesión atual', 'browser': 'NAVEGADOR', 'location': 'LOCALIZAÇÃO', 'copy': 'Copiar', 'end_session': 'Encerrar sessão',
        'toast_copied': 'Token copiado con éxito!', 'modal_title': 'Encerrar Sesión', 
        'modal_desc': 'Tem certeza que deseja encerrar esta sessão? O usuário será desconectado imediatamente daquele dispositivo.',
        'cancel': 'Cancelar', 'toast_terminated': 'Sesión encerrada con éxito!', 'error_term': 'Error al encerrar a sessão.'
    },
    'en': {
        'sessions': 'Sessions', 'active_sessions': 'active sessions', 'current_session': '1 current', 'sessions_list': 'Sessions list',
        'this_session': 'Current session', 'browser': 'BROWSER', 'location': 'LOCATION', 'copy': 'Copy', 'end_session': 'End session',
        'toast_copied': 'Token copied successfully!', 'modal_title': 'End Session', 
        'modal_desc': 'Are you sure you want to end this session? The user will be disconnected immediately from that device.',
        'cancel': 'Cancel', 'toast_terminated': 'Session ended successfully!', 'error_term': 'Error ending session.'
    },
    'es': {
        'sessions': 'Sesiones', 'active_sessions': 'sesiones activas', 'current_session': '1 actual', 'sessions_list': 'Lista de sesiones',
        'this_session': 'Sesión actual', 'browser': 'NAVEGADOR', 'location': 'UBICACIÓN', 'copy': 'Copiar', 'end_session': 'Cerrar sesión',
        'toast_copied': '¡Token copiado con éxito!', 'modal_title': 'Cerrar Sesión', 
        'modal_desc': '¿Estás seguro de que deseas cerrar esta sesión? El usuario será desconectado inmediatamente de ese dispositivo.',
        'cancel': 'Cancelar', 'toast_terminated': '¡Sesión terminada con éxito!', 'error_term': 'Error al cerrar la sesión.'
    }
};

function getLocalMsg(key) {
    const lang = localStorage.getItem('app_language') || 'pt';
    return sessDict[lang] && sessDict[lang][key] ? sessDict[lang][key] : key;
}

function applySessI18n() {
    const lang = localStorage.getItem('app_language') || 'pt';
    const dict = sessDict[lang] || sessDict['pt'];

    if (window.globalTranslations) {
        for (let langKey in sessDict) {
            if (!window.globalTranslations[langKey]) window.globalTranslations[langKey] = {};
            Object.assign(window.globalTranslations[langKey], sessDict[langKey]);
        }
    }

    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) el.textContent = dict[key];
    });
}

const originalSelectLang = window.selectAppLang;
window.selectAppLang = function(langCode) {
    if(originalSelectLang) originalSelectLang(langCode);
    applySessI18n();
};

function showToast(type, msgKey) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast \${type}`;
    const icon = type === 'error' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;"><path d="M18 6 6 18M6 6l12 12"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;"><polyline points="20 6 9 17 4 12"/></svg>';
    
    toast.innerHTML = `<div class="toast-icon">\${icon}</div><div class="toast-msg">\${getLocalMsg(msgKey)}</div><div class="toast-progress"></div>`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
}

function copyToken(token) {
    navigator.clipboard.writeText(token).then(() => {
        showToast('success', 'toast_copied');
    });
}

function confirmTerminate(sessionId) {
    const isDark = document.documentElement.classList.contains('dark');
    
    Swal.fire({
        html: `
            <div style="text-align:left; margin-bottom:20px; display:flex; align-items:center; gap:16px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;border:1px solid rgba(239,68,68,0.2);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h2 class="swal-title-custom">\${getLocalMsg('modal_title')}</h2>
            </div>
            <p class="swal-desc-custom" style="text-align:left;">\${getLocalMsg('modal_desc')}</p>
        `,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff',
        color: isDark ? '#ffffff' : '#111827',
        backdrop: `rgba(0,0,0,0.8)`,
        buttonsStyling: false,
        showCancelButton: true,
        confirmButtonText: getLocalMsg('end_session'),
        cancelButtonText: getLocalMsg('cancel')
    }).then((result) => {
        if (result.isConfirmed) {
            terminateSession(sessionId);
        }
    });
}

function terminateSession(sessionId) {
    fetch('?ajax_terminate=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId })
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            showToast('success', 'toast_terminated');
            const card = document.getElementById('card-' + sessionId);
            if(card) {
                card.style.transform = 'scale(0.8)';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    let total = parseInt(document.getElementById('stat-total').innerText);
                    if(total > 0) document.getElementById('stat-total').innerText = total - 1;
                }, 300);
            }
        } else {
            showToast('error', 'error_term');
        }
    });
}

document.addEventListener('DOMContentLoaded', applySessI18n);
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) {
    include $layoutFile;
} else if (file_exists(__DIR__ . '/layout.php')) {
    include __DIR__ . '/layout.php';
} else {
    echo "<!DOCTYPE html><html><head><title>{$pageTitle}</title></head><body style='background:#121214; margin:0;'>";
    echo $pageContent . $extraJs;
    echo "</body></html>";
}
?>