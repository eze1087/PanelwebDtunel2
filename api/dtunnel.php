<?php
/**
 * DTunnel Public API
 * Endpoint público para o aplicativo By Elnene Panel WEB2
 * Compatível com o formato esperado pelo app
 */
define('DTUNNEL_APP', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$action = $_GET['action'] ?? 'config';

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'token_required', 'message' => 'Token é obrigatório']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM users WHERE token=?');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_token', 'message' => 'Token inválido']);
    exit;
}

if ($user['is_blocked']) {
    http_response_code(403);
    echo json_encode(['error' => 'user_blocked', 'message' => 'Cuenta bloqueada. Contactá: WA 3455236886 / TG @El_NeNe_Sando']);
    exit;
}

switch ($action) {
    case 'config':
        outputAppConfig($pdo, $user);
        break;
    case 'text':
        outputAppText($pdo, $user);
        break;
    case 'version':
        outputVersion();
        break;
    default:
        outputAppConfig($pdo, $user);
}

function outputAppConfig($pdo, $user) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id=? AND status="ACTIVE" ORDER BY sorter ASC');
    $stmt->execute([$user['id']]);
    $categories = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM cdn WHERE user_id=? AND status="ACTIVE"');
    $stmt->execute([$user['id']]);
    $cdns = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM app_configs WHERE user_id=? AND status="ACTIVE" ORDER BY sorter ASC, id ASC');
    $stmt->execute([$user['id']]);
    $configs = $stmt->fetchAll();

    $response = [
        'version' => (int)$user['app_config_version'],
        'categories' => array_values(array_map(function($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'color' => $c['color'],
                'sorter' => (int)$c['sorter']
            ];
        }, $categories)),
        'cdns' => array_values(array_map(function($c) {
            return ['name' => $c['name'], 'url' => $c['url']];
        }, $cdns)),
        'configs' => array_values(array_map('formatDTunnelConfig', $configs))
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function formatDTunnelConfig($cfg) {
    $base = [
        'id' => (int)$cfg['id'],
        'name' => $cfg['name'],
        'description' => $cfg['description'] ?? '',
        'mode' => $cfg['mode'],
        'icon' => $cfg['icon'] ?? 'DEFAULT',
        'sorter' => (int)$cfg['sorter'],
        'category_id' => $cfg['category_id'] ? (int)$cfg['category_id'] : null,
        'url_check_user' => $cfg['url_check_user'] ?? '',
        'server_host' => $cfg['server_host'] ?? '',
        'server_port' => $cfg['server_port'] ? (int)$cfg['server_port'] : null,
        'proxy_host' => $cfg['proxy_host'] ?? '',
        'proxy_port' => $cfg['proxy_port'] ? (int)$cfg['proxy_port'] : null,
        'udp_ports' => $cfg['udp_ports'] ?? '7300',
        'tls_version' => $cfg['tls_version'] ?? null,
        'auth_username' => $cfg['auth_username'] ?? '',
        'auth_password' => $cfg['auth_password'] ?? '',
        'auth_v2ray_uuid' => $cfg['auth_v2ray_uuid'] ?? '',
        'dns_server_dns1' => $cfg['dns_server_dns1'] ?? '8.8.8.8',
        'dns_server_dns2' => $cfg['dns_server_dns2'] ?? '8.8.4.4',
    ];

    switch ($cfg['mode']) {
        case 'SSH_WEBSOCKET':
            $base['config_payload_payload'] = $cfg['config_payload_payload'] ?? '';
            $base['config_payload_sni'] = $cfg['config_payload_sni'] ?? '';
            break;
        case 'V2RAY':
            $base['config_v2ray'] = $cfg['config_v2ray'] ?? '';
            break;
        case 'OPENVPN':
            $base['config_openvpn'] = $cfg['config_openvpn'] ?? '';
            break;
        case 'DNSTT':
            $base['dnstt_server'] = $cfg['dnstt_server'] ?? '';
            $base['dnstt_name_server'] = $cfg['dnstt_name_server'] ?? '';
            $base['dnstt_key'] = $cfg['dnstt_key'] ?? '';
            break;
        case 'HYSTERIA':
            $base['hy_port'] = $cfg['hy_port'] ?? '13375';
            $base['hy_version'] = (int)($cfg['hy_version'] ?? 1);
            $base['hy_up_mbps'] = (int)($cfg['hy_up_mbps'] ?? 100);
            $base['hy_down_mbps'] = (int)($cfg['hy_down_mbps'] ?? 150);
            $base['hy_obfs'] = $cfg['hy_obfs'] ?? '';
            $base['hy_insecure'] = (bool)($cfg['hy_insecure'] ?? 1);
            break;
    }

    return $base;
}

function outputAppText($pdo, $user) {
    $stmt = $pdo->prepare('SELECT label, text FROM app_texts WHERE user_id=?');
    $stmt->execute([$user['id']]);
    $texts = $stmt->fetchAll();

    $textMap = [];
    foreach ($texts as $t) {
        $textMap[$t['label']] = $t['text'];
    }

    echo json_encode([
        'version' => (int)$user['app_text_version'],
        'texts' => $textMap
    ], JSON_UNESCAPED_UNICODE);
}

function outputVersion() {
    echo json_encode([
        'version' => '4.5.7',
        'build' => 457,
        'url' => '',
        'changelog' => 'By Elnene Panel WEB2 v4.5.7'
    ]);
}
