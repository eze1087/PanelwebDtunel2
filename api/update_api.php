<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Update API v2.0.1 - App Sync & Anti-Theft Protection
 * @description JSON-based API with token validation and anti-theft protection
 * =======================================================================================
 */

// Headers de Proteção
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Caminhos dos Arquivos JSON
$dbUsuarios   = __DIR__ . '/../db/usuarios.json';
$dbCategories = __DIR__ . '/../db/categories.json';
$dbConfigs    = __DIR__ . '/../db/configs.json';
$dbTexts      = __DIR__ . '/../db/texts.json';
$dbCDN        = __DIR__ . '/../db/cdn.json';
$dbLayout     = __DIR__ . '/../db/layout.json';
$dbVersion    = __DIR__ . '/../db/version.json';

// Função para retornar resposta vazia (proteção anti-roubo)
function returnEmpty() {
    echo json_encode([], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para retornar offline
function returnOffline() {
    echo json_encode(['error' => 'Sistema offline no momento', 'status' => 503], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtém o token/UUID da requisição
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$type = $_GET['type'] ?? $_POST['type'] ?? 'all';

// Se não houver token, retorna vazio (proteção)
if (empty($token)) {
    returnEmpty();
}

// Carrega usuários e valida token
if (!file_exists($dbUsuarios)) {
    returnOffline();
}

$usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
$userFound = false;
$userEmail = '';

foreach ($usuarios as $user) {
    if (($user['uuid'] ?? '') === $token) {
        $userFound = true;
        $userEmail = $user['email'] ?? '';
        // Verifica se o usuário está bloqueado
        if (!empty($user['blocked']) || $user['blocked'] === true) {
            returnEmpty();
        }
        break;
    }
}

// Se token inválido, retorna vazio
if (!$userFound || empty($userEmail)) {
    returnEmpty();
}

// Função para carregar arquivo JSON com segurança
function loadJsonFile($filepath) {
    if (!file_exists($filepath)) {
        return [];
    }
    $content = file_get_contents($filepath);
    return json_decode($content, true) ?: [];
}

// Função para filtrar configs do usuário
function getUserConfigs($email) {
    global $dbConfigs;
    $configs = loadJsonFile($dbConfigs);
    return array_filter($configs, function($c) use ($email) {
        return ($c['user_email'] ?? '') === $email && ($c['status'] ?? 'INACTIVE') === 'ACTIVE';
    });
}

// Função para filtrar categorias do usuário
function getUserCategories($email) {
    global $dbCategories;
    $categories = loadJsonFile($dbCategories);
    return array_filter($categories, function($c) use ($email) {
        return ($c['user_email'] ?? '') === $email && ($c['status'] ?? 'INACTIVE') === 'ACTIVE';
    });
}

// Função para obter versão do usuário
function getUserVersion($email) {
    global $dbVersion;
    $versions = loadJsonFile($dbVersion);
    return (int)($versions[$email] ?? 1);
}

// ==================================================
// PROCESSAMENTO POR TIPO
// ==================================================

switch ($type) {
    // CONFIGURAÇÕES
    case 'config':
        $configs = getUserConfigs($userEmail);
        $result = array_values($configs);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // CATEGORIAS
    case 'category':
        $categories = getUserCategories($userEmail);
        $result = array_values($categories);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // TEXTOS
    case 'text':
        $texts = loadJsonFile($dbTexts);
        $userTexts = array_filter($texts, function($t) use ($userEmail) {
            return ($t['user_email'] ?? '') === $userEmail;
        });
        $result = [];
        foreach ($userTexts as $text) {
            $result[$text['label'] ?? ''] = $text['text'] ?? '';
        }
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // CDN
    case 'cdn':
        $cdns = loadJsonFile($dbCDN);
        $userCDNs = array_filter($cdns, function($c) use ($userEmail) {
            return ($c['user_email'] ?? '') === $userEmail && ($c['status'] ?? 'INACTIVE') === 'ACTIVE';
        });
        $result = array_values($userCDNs);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // LAYOUT
    case 'layout':
        $layouts = loadJsonFile($dbLayout);
        $userLayout = [];
        foreach ($layouts as $layout) {
            if (($layout['user_email'] ?? '') === $userEmail) {
                $userLayout = $layout;
                break;
            }
        }
        echo json_encode($userLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // VERSÃO
    case 'version':
        $version = getUserVersion($userEmail);
        echo json_encode(['version' => $version], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // TUDO (PADRÃO)
    case 'all':
    default:
        $response = [
            'configs' => array_values(getUserConfigs($userEmail)),
            'categories' => array_values(getUserCategories($userEmail)),
            'version' => getUserVersion($userEmail),
            'texts' => (function() use ($userEmail, $dbTexts) {
                $texts = loadJsonFile($dbTexts);
                $userTexts = array_filter($texts, function($t) use ($userEmail) {
                    return ($t['user_email'] ?? '') === $userEmail;
                });
                $result = [];
                foreach ($userTexts as $text) {
                    $result[$text['label'] ?? ''] = $text['text'] ?? '';
                }
                return $result;
            })(),
            'cdn' => array_values((function() use ($userEmail, $dbCDN) {
                $cdns = loadJsonFile($dbCDN);
                return array_filter($cdns, function($c) use ($userEmail) {
                    return ($c['user_email'] ?? '') === $userEmail && ($c['status'] ?? 'INACTIVE') === 'ACTIVE';
                });
            })()),
            'layout' => (function() use ($userEmail, $dbLayout) {
                $layouts = loadJsonFile($dbLayout);
                foreach ($layouts as $layout) {
                    if (($layout['user_email'] ?? '') === $userEmail) {
                        return $layout;
                    }
                }
                return [];
            })()
        ];
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;
}

exit;
?>
