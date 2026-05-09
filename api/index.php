<?php
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação para endpoints protegidos
$publicEndpoints = ['/api/app-config', '/api/app-text', '/api/version'];
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$isPublic = false;
foreach ($publicEndpoints as $ep) {
    if (strpos($requestPath, $ep) === 0) {
        $isPublic = true;
        break;
    }
}

if (!$isPublic && !isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $requestPath;

// Parse request body
$body = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody, true) ?? [];
}

// Route API requests
if (preg_match('#^/api/categories/(\d+)$#', $path, $m)) {
    handleCategories($method, $m[1], $body);
} elseif ($path === '/api/categories') {
    handleCategories($method, null, $body);
} elseif (preg_match('#^/api/cdn/(\d+)$#', $path, $m)) {
    handleCdn($method, $m[1], $body);
} elseif ($path === '/api/cdn') {
    handleCdn($method, null, $body);
} elseif (preg_match('#^/api/configs/export$#', $path)) {
    handleConfigsExport();
} elseif (preg_match('#^/api/configs/import$#', $path)) {
    handleConfigsImport($body);
} elseif (preg_match('#^/api/configs/(\d+)$#', $path, $m)) {
    handleConfigs($method, $m[1], $body);
} elseif ($path === '/api/configs') {
    handleConfigs($method, null, $body);
} elseif ($path === '/api/texts' && $method === 'PUT') {
    handleTextsUpdate($body);
} elseif ($path === '/api/texts/reset' && $method === 'POST') {
    handleTextsReset();
} elseif (preg_match('#^/api/users/(\d+)/block$#', $path, $m)) {
    handleUserBlock($m[1], $body);
} elseif (preg_match('#^/api/users/(\d+)$#', $path, $m)) {
    handleUsers($method, $m[1], $body);
} elseif ($path === '/api/users') {
    handleUsers($method, null, $body);
} elseif (preg_match('#^/api/app-config$#', $path)) {
    handleAppConfig($method, $body);
} elseif (preg_match('#^/api/app-text$#', $path)) {
    handleAppText($method, $body);
} elseif ($path === '/api/version') {
    handleVersion();
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Endpoint não encontrado']);
}

// ===========================
// CATEGORIES
// ===========================
function handleCategories($method, $id, $body) {
    $pdo = db();
    $userId = $_SESSION['user_id'];

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $cat = $stmt->fetch();
        echo json_encode($cat ?: ['success' => false, 'message' => 'No encontrado']);
        return;
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id = ? ORDER BY sorter ASC');
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetchAll());
        return;
    }

    if ($method === 'POST') {
        $stmt = $pdo->prepare('INSERT INTO categories (name, color, sorter, status, user_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$body['name'], $body['color'] ?? '#4A90D9', $body['sorter'] ?? 0, $body['status'] ?? 'ACTIVE', $userId]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        return;
    }

    if ($method === 'PUT' && $id) {
        $stmt = $pdo->prepare('UPDATE categories SET name=?, color=?, sorter=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
        $stmt->execute([$body['name'], $body['color'] ?? '#4A90D9', $body['sorter'] ?? 0, $body['status'] ?? 'ACTIVE', $id, $userId]);
        echo json_encode(['success' => true]);
        return;
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        return;
    }

    echo json_encode(['success' => false, 'message' => 'Método não suportado']);
}

// ===========================
// CDN
// ===========================
function handleCdn($method, $id, $body) {
    $pdo = db();
    $userId = $_SESSION['user_id'];

    if ($method === 'GET' && !$id) {
        $stmt = $pdo->prepare('SELECT * FROM cdn WHERE user_id = ? ORDER BY id ASC');
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetchAll());
        return;
    }

    if ($method === 'POST') {
        $stmt = $pdo->prepare('INSERT INTO cdn (name, url, status, user_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$body['name'], $body['url'], $body['status'] ?? 'ACTIVE', $userId]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        return;
    }

    if ($method === 'PUT' && $id) {
        $stmt = $pdo->prepare('UPDATE cdn SET name=?, url=?, status=? WHERE id=? AND user_id=?');
        $stmt->execute([$body['name'], $body['url'], $body['status'] ?? 'ACTIVE', $id, $userId]);
        echo json_encode(['success' => true]);
        return;
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM cdn WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        return;
    }

    echo json_encode(['success' => false, 'message' => 'Método não suportado']);
}

// ===========================
// CONFIGS
// ===========================
function handleConfigs($method, $id, $body) {
    $pdo = db();
    $userId = $_SESSION['user_id'];

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM app_configs WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
        $cfg = $stmt->fetch();
        echo json_encode($cfg ?: ['success' => false, 'message' => 'No encontrado']);
        return;
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT ac.*, c.name as category_name FROM app_configs ac LEFT JOIN categories c ON ac.category_id=c.id WHERE ac.user_id=? ORDER BY ac.sorter ASC');
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetchAll());
        return;
    }

    $fields = ['name','description','mode','category_id','sorter','status','url_check_user',
               'server_host','server_port','proxy_host','proxy_port','udp_ports','tls_version',
               'config_payload_payload','config_payload_sni','config_v2ray','config_openvpn',
               'auth_username','auth_password','auth_v2ray_uuid','dns_server_dns1','dns_server_dns2',
               'dnstt_server','dnstt_name_server','dnstt_key','hy_port','hy_version',
               'hy_up_mbps','hy_down_mbps','hy_obfs','hy_insecure'];

    if ($method === 'POST') {
        $cols = implode(',', $fields);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $values = array_map(function($f) use ($body) { return isset($body[$f]) ? $body[$f] : null; }, $fields);
        $values[] = $userId;
        $stmt = $pdo->prepare("INSERT INTO app_configs ($cols, user_id) VALUES ($placeholders, ?)");
        $stmt->execute($values);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        return;
    }

    if ($method === 'PUT' && $id) {
        $sets = implode(',', array_map(function($f) { return "$f=?"; }, $fields));
        $values = array_map(function($f) use ($body) { return isset($body[$f]) ? $body[$f] : null; }, $fields);
        $values[] = date('Y-m-d H:i:s');
        $values[] = $id;
        $values[] = $userId;
        $stmt = $pdo->prepare("UPDATE app_configs SET $sets, updated_at=? WHERE id=? AND user_id=?");
        $stmt->execute($values);
        echo json_encode(['success' => true]);
        return;
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare('DELETE FROM app_configs WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        return;
    }

    echo json_encode(['success' => false, 'message' => 'Método não suportado']);
}

function handleConfigsExport() {
    $pdo = db();
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT * FROM app_configs WHERE user_id=?');
    $stmt->execute([$userId]);
    $configs = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id=?');
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll();
    echo json_encode(['configs' => $configs, 'categories' => $categories, 'exported_at' => date('c')]);
}

function handleConfigsImport($body) {
    $pdo = db();
    $userId = $_SESSION['user_id'];
    $imported = 0;
    if (!empty($body['configs'])) {
        foreach ($body['configs'] as $cfg) {
            $stmt = $pdo->prepare('INSERT INTO app_configs (name, mode, status, user_id, server_host, server_port, description, url_check_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$cfg['name'] ?? 'Importado', $cfg['mode'] ?? 'SSH_DIRECT', 'ACTIVE', $userId, $cfg['server_host'] ?? null, $cfg['server_port'] ?? null, $cfg['description'] ?? null, $cfg['url_check_user'] ?? '']);
            $imported++;
        }
    }
    echo json_encode(['success' => true, 'message' => "$imported configuração(ões) importada(s)."]);
}

// ===========================
// TEXTS
// ===========================
function handleTextsUpdate($body) {
    $pdo = db();
    $userId = $_SESSION['user_id'];
    if (!empty($body['texts'])) {
        $stmt = $pdo->prepare('UPDATE app_texts SET text=? WHERE id=? AND user_id=?');
        foreach ($body['texts'] as $t) {
            $stmt->execute([$t['text'], $t['id'], $userId]);
        }
    }
    // Increment version
    $pdo->prepare('UPDATE users SET app_text_version = app_text_version + 1 WHERE id=?')->execute([$userId]);
    echo json_encode(['success' => true]);
}

function handleTextsReset() {
    $pdo = db();
    $userId = $_SESSION['user_id'];
    $pdo->prepare('DELETE FROM app_texts WHERE user_id=?')->execute([$userId]);
    createDefaultAppTexts($userId);
    echo json_encode(['success' => true]);
}

// ===========================
// USERS
// ===========================
function handleUsers($method, $id, $body) {
    $pdo = db();
    $currentUserId = $_SESSION['user_id'];

    if ($method === 'GET' && !$id) {
        $stmt = $pdo->prepare('SELECT id, username, email, created_at, expires_at, is_blocked FROM users ORDER BY created_at DESC');
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
        return;
    }

    if ($method === 'PUT' && $id) {
        if ($id == $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Use a página de perfil para editar sua própria conta.']);
            return;
        }
        $updates = [];
        $values = [];
        if (!empty($body['username'])) { $updates[] = 'username=?'; $values[] = $body['username']; }
        if (!empty($body['email'])) { $updates[] = 'email=?'; $values[] = $body['email']; }
        if (!empty($body['password'])) { $updates[] = 'password=?'; $values[] = hashPassword($body['password']); }
        if (isset($body['expires_at'])) {
            $updates[] = 'expires_at=?';
            $values[] = !empty($body['expires_at']) ? date('Y-m-d H:i:s', strtotime($body['expires_at'])) : null;
        }
        if (empty($updates)) { echo json_encode(['success' => false, 'message' => 'Nada para atualizar.']); return; }
        $values[] = $id;
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(',', $updates) . ' WHERE id=?');
        $stmt->execute($values);
        echo json_encode(['success' => true]);
        return;
    }

    if ($method === 'DELETE' && $id) {
        if ($id == $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Você não pode excluir sua própria conta.']);
            return;
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        return;
    }

    echo json_encode(['success' => false, 'message' => 'Método não suportado']);
}

function handleUserBlock($id, $body) {
    $pdo = db();
    $currentUserId = $_SESSION['user_id'];
    if ($id == $currentUserId) {
        echo json_encode(['success' => false, 'message' => 'Você não pode bloquear sua própria conta.']);
        return;
    }
    $block = isset($body['block']) ? (int)$body['block'] : 1;
    $stmt = $pdo->prepare('UPDATE users SET is_blocked=? WHERE id=?');
    $stmt->execute([$block, $id]);
    $msg = $block ? 'Usuario bloqueado con éxito.' : 'Usuario desbloqueado con éxito.';
    echo json_encode(['success' => true, 'message' => $msg]);
}

// ===========================
// PUBLIC API - App Config (DTunnel App)
// ===========================
function handleAppConfig($method, $body) {
    $pdo = db();
    $token = $_GET['token'] ?? $body['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token obrigatório']);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE token=? AND is_blocked=0');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido ou usuário bloqueado']);
        return;
    }

    // Buscar configs ativas
    $stmt = $pdo->prepare('SELECT ac.*, c.name as category_name, c.color as category_color FROM app_configs ac LEFT JOIN categories c ON ac.category_id=c.id WHERE ac.user_id=? AND ac.status="ACTIVE" ORDER BY ac.sorter ASC');
    $stmt->execute([$user['id']]);
    $configs = $stmt->fetchAll();

    // Buscar CDNs ativos
    $stmt = $pdo->prepare('SELECT * FROM cdn WHERE user_id=? AND status="ACTIVE"');
    $stmt->execute([$user['id']]);
    $cdns = $stmt->fetchAll();

    // Buscar categorias ativas
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id=? AND status="ACTIVE" ORDER BY sorter ASC');
    $stmt->execute([$user['id']]);
    $categories = $stmt->fetchAll();

    // Formatar resposta no formato DTunnel
    $response = [
        'version' => (int)$user['app_config_version'],
        'categories' => array_map(function($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'color' => $c['color'],
                'sorter' => (int)$c['sorter'],
                'status' => $c['status']
            ];
        }, $categories),
        'cdns' => array_map(function($c) {
            return ['name' => $c['name'], 'url' => $c['url']];
        }, $cdns),
        'configs' => array_map('formatConfig', $configs)
    ];

    echo json_encode($response);
}

function formatConfig($cfg) {
    $result = [
        'id' => (int)$cfg['id'],
        'name' => $cfg['name'],
        'description' => $cfg['description'],
        'mode' => $cfg['mode'],
        'icon' => $cfg['icon'] ?? 'DEFAULT',
        'sorter' => (int)$cfg['sorter'],
        'status' => $cfg['status'],
        'category_id' => $cfg['category_id'] ? (int)$cfg['category_id'] : null,
        'url_check_user' => $cfg['url_check_user'] ?? '',
        'server' => [
            'host' => $cfg['server_host'],
            'port' => $cfg['server_port'] ? (int)$cfg['server_port'] : null
        ],
        'proxy' => [
            'host' => $cfg['proxy_host'],
            'port' => $cfg['proxy_port'] ? (int)$cfg['proxy_port'] : null
        ],
        'udp_ports' => $cfg['udp_ports'] ?? '7300',
        'tls_version' => $cfg['tls_version'],
        'auth' => [
            'username' => $cfg['auth_username'],
            'password' => $cfg['auth_password'],
            'v2ray_uuid' => $cfg['auth_v2ray_uuid']
        ],
        'dns' => [
            'dns1' => $cfg['dns_server_dns1'],
            'dns2' => $cfg['dns_server_dns2']
        ]
    ];

    if ($cfg['mode'] === 'SSH_WEBSOCKET') {
        $result['payload'] = [
            'payload' => $cfg['config_payload_payload'],
            'sni' => $cfg['config_payload_sni']
        ];
    }

    if ($cfg['mode'] === 'V2RAY') {
        $result['v2ray_config'] = $cfg['config_v2ray'];
    }

    if ($cfg['mode'] === 'OPENVPN') {
        $result['openvpn_config'] = $cfg['config_openvpn'];
    }

    if ($cfg['mode'] === 'DNSTT') {
        $result['dnstt'] = [
            'server' => $cfg['dnstt_server'],
            'name_server' => $cfg['dnstt_name_server'],
            'key' => $cfg['dnstt_key']
        ];
    }

    if ($cfg['mode'] === 'HYSTERIA') {
        $result['hysteria'] = [
            'port' => $cfg['hy_port'] ?? '13375',
            'version' => (int)($cfg['hy_version'] ?? 1),
            'up_mbps' => (int)($cfg['hy_up_mbps'] ?? 100),
            'down_mbps' => (int)($cfg['hy_down_mbps'] ?? 150),
            'obfs' => $cfg['hy_obfs'],
            'insecure' => (bool)($cfg['hy_insecure'] ?? 1)
        ];
    }

    return $result;
}

// ===========================
// PUBLIC API - App Text
// ===========================
function handleAppText($method, $body) {
    $pdo = db();
    $token = $_GET['token'] ?? $body['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token obrigatório']);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE token=? AND is_blocked=0');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido']);
        return;
    }

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
    ]);
}

// ===========================
// VERSION
// ===========================
function handleVersion() {
    echo json_encode([
        'version' => '4.5.7',
        'build' => 457,
        'url' => '',
        'changelog' => 'By Elnene Panel WEB2 v4.5.7'
    ]);
}
