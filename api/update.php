<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name API Mestra de Sincronização - By Elnene Panel WEB2
 * @description Retorna arrays planos (formato idéntico al panel original DTunnelMod).
 * =======================================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$type = $_GET['type'] ?? '';
$uuid = $_GET['uuid'] ?? ($_GET['user'] ?? '');

if (empty($type) || empty($uuid)) {
    echo json_encode([]);
    exit;
}

$dbDir        = __DIR__ . '/../db';
$usuariosFile = $dbDir   . '/usuarios.json';

if (!file_exists($usuariosFile)) {
    echo json_encode([]);
    exit;
}

$usuarios   = json_decode(file_get_contents($usuariosFile), true) ?: [];
$userEmail  = '';
$userEstado = '';
$userBlocked = false;

foreach ($usuarios as $u) {
    $matchUuid  = isset($u['uuid']) && strtolower($u['uuid']) === strtolower($uuid);
    $matchMd5   = md5($u['email'] ?? '') === $uuid;
    if ($matchUuid || $matchMd5) {
        $userEmail   = $u['email'] ?? '';
        $userEstado  = strtolower($u['status'] ?? 'active');
        $userBlocked = !empty($u['is_blocked']);
        break;
    }
}

if (empty($userEmail) || $userEstado !== 'active' || $userBlocked) {
    echo json_encode([]);
    exit;
}

foreach ($usuarios as $u) {
    if (strtolower($u['email'] ?? '') === strtolower($userEmail)) {
        $exp = $u['expires_at'] ?? '';
        if ($exp && strpos($exp, '2099') === false) {
            try {
                $expDt = new DateTime($exp);
                $now   = new DateTime();
                if ($expDt < $now) {
                    echo json_encode([]);
                    exit;
                }
            } catch (Exception $e) {}
        }
        break;
    }
}

function cleanItems($items) {
    $out = [];
    foreach ($items as $item) {
        unset($item['user_email']);
        unset($item['category_id']);
        $out[] = $item;
    }
    return $out;
}

function loadJson($path) {
    return file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
}

switch ($type) {

    case 'config':
    case 'app_config':
        $data     = loadJson($dbDir . '/configs.json');
        $filtered = array_values(array_filter($data, function($c) use ($userEmail) {
            return strtolower($c['user_email'] ?? '') === strtolower($userEmail)
                && strtoupper($c['status'] ?? 'ACTIVE') === 'ACTIVE';
        }));
        usort($filtered, function($a, $b) {
            return ((int)($a['sorter'] ?? 1)) - ((int)($b['sorter'] ?? 1));
        });

        // Expandir category_id → objeto category
        $catData = loadJson($dbDir . '/categories.json');
        $userCatsMap = [];
        foreach ($catData as $cat) {
            if (strtolower($cat['user_email'] ?? '') === strtolower($userEmail)) {
                $userCatsMap[$cat['id'] ?? 0] = $cat;
            }
        }
        foreach ($filtered as &$cfg) {
            if (isset($cfg['category']) && is_array($cfg['category']) && isset($cfg['category']['name'])) {
                unset($cfg['category_id']);
                continue;
            }
            $catId = $cfg['category_id'] ?? ($cfg['category'] ?? null);
            if ($catId !== null && isset($userCatsMap[$catId])) {
                $cat = $userCatsMap[$catId];
                $cfg['category'] = [
                    'name'   => $cat['name'] ?? 'Default',
                    'color'  => $cat['color'] ?? '#FF808080',
                    'sorter' => (int)($cat['sorter'] ?? 1),
                ];
            } elseif (!isset($cfg['category']) || !is_array($cfg['category'])) {
                $cfg['category'] = ['name' => 'Default', 'color' => '#FF808080', 'sorter' => 1];
            }
            unset($cfg['category_id']);
        }
        unset($cfg);

        echo json_encode(cleanItems($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'category':
        $data     = loadJson($dbDir . '/categories.json');
        $filtered = array_values(array_filter($data, function($c) use ($userEmail) {
            return strtolower($c['user_email'] ?? '') === strtolower($userEmail)
                && strtoupper($c['status'] ?? 'ACTIVE') === 'ACTIVE';
        }));
        usort($filtered, function($a, $b) {
            return ((int)($a['sorter'] ?? 1)) - ((int)($b['sorter'] ?? 1));
        });
        echo json_encode(cleanItems($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'cdn':
        $data     = loadJson($dbDir . '/cdn.json');
        $filtered = array_values(array_filter($data, function($c) use ($userEmail) {
            return strtolower($c['user_email'] ?? '') === strtolower($userEmail)
                && strtoupper($c['status'] ?? 'ACTIVE') === 'ACTIVE';
        }));
        echo json_encode(cleanItems($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'layout':
    case 'app_layout':
        $data        = loadJson($dbDir . '/app_layouts.json');
        $layoutData  = [];
        foreach ($data as $layout) {
            if (strtolower($layout['user_email'] ?? '') === strtolower($userEmail) && !empty($layout['is_active'])) {
                $layoutData = $layout['layout_data'] ?? [];
                break;
            }
        }
        if (empty($layoutData)) {
            foreach ($data as $layout) {
                if (strtolower($layout['user_email'] ?? '') === strtolower($userEmail)) {
                    $layoutData = $layout['layout_data'] ?? [];
                    break;
                }
            }
        }
        echo json_encode($layoutData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'text':
    case 'app_text':
        $data = loadJson($dbDir . '/textos.json');
        if (empty($data)) { $data = loadJson($dbDir . '/app_texts.json'); }

        if (isset($data[$userEmail]) && is_array($data[$userEmail])) {
            $userTexts = $data[$userEmail];
        } else {
            $userTexts = array_values(array_filter($data, function($t) use ($userEmail) {
                return strtolower($t['user_email'] ?? '') === strtolower($userEmail);
            }));
            foreach ($userTexts as &$t) { unset($t['user_email']); }
            unset($t);
        }

        if (!empty($userTexts) && isset($userTexts[0]['label'])) {
            echo json_encode($userTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $arr = [];
            foreach ($userTexts as $label => $text) {
                $arr[] = ['label' => $label, 'text' => $text];
            }
            echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'version':
        $data = loadJson($dbDir . '/version.json');
        echo json_encode(['version' => (int)($data['version'] ?? 100)], JSON_UNESCAPED_SLASHES);
        break;

    default:
        echo json_encode([]);
        break;
}
