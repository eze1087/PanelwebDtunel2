<?php
/**
 * =======================================================================================
 * @author El NeNe | WA: 3455236886 | TG: @El_NeNe_Sando
 * @name Gerador de APK Dinâmico (Trem Bala V7 — Size+Configs+Sign Fixed) - UI Premium Original
 * @description Injeção de credenciais, Logs reais idênticos, Integração i18n global.
 * =======================================================================================
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!defined('DTUNNEL_APP')) { header('Location: /404'); exit; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sessionEmail = $_SESSION['email'] ?? '';
if (empty($sessionEmail)) { header('Location: /login'); exit; }

// Diretórios necessários
$dirBase = __DIR__ . '/../apk_base';
$dirDown = __DIR__ . '/../downloads';
$dbUsuarios = __DIR__ . '/../db/usuarios.json';

// Cria as pastas se não existirem
foreach ([$dirBase, $dirDown] as $dir) {
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
}

// ======================================================================
// SISTEMA DE SEGURANÇA: LIMPEZA DE APKS ANTIGOS (EXPIRAÇÃO DE 2 MINUTOS)
// ======================================================================
$files = glob($dirDown . '/*.apk');
$now = time();
foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 120) { // 120 segundos = 2 minutos
            @unlink($file);
        }
    }
}

// Carrega o UUID do usuário logado para montar a credencial
$userUuid = '---';
if (file_exists($dbUsuarios)) {
    $usuarios = json_decode(file_get_contents($dbUsuarios), true) ?: [];
    foreach ($usuarios as $u) {
        if (strtolower($u['email']) === strtolower($sessionEmail)) {
            $userUuid = $u['uuid'] ?? md5($sessionEmail);
            break;
        }
    }
}

// ----------------------------------------------------------------------
// PROCESSAMENTO AJAX (BACKEND DE GERAÇÃO)
// ----------------------------------------------------------------------

// ── Detectar tipo de APK base (SUPER_PRO o SUPER_LITE) ─────────────────────
// SUPER_PRO:  contiene libhysteria/libdnstt/libgojni → todos los protocolos VPN
// SUPER_LITE: solo libdtunnel → SSH / V2Ray / OVPN básico
if (!function_exists('dtunnel_detect_apk_type')) {
    function dtunnel_detect_apk_type(array $fileList): string {
        foreach (array_keys($fileList) as $f) {
            if (preg_match('#lib/[^/]+/lib(hysteria_v[12]|dnstt|gojni)\.so$#', $f)) {
                return 'SUPER_PRO';
            }
        }
        return 'SUPER_LITE';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar TODO output antes del JSON para evitar "Respuesta inválida"
    ob_start();
    // Límites máximos para APKs de 60MB
    @ini_set('memory_limit', '1024M');
    @ini_set('max_execution_time', '0');
    @set_time_limit(0);
    @ignore_user_abort(true);
    // Suprimir TODOS los warnings/notices/errores que romperían el JSON
    @error_reporting(0);
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '0');

    // Limpiar cualquier output previo y enviar header JSON limpio
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Para upload (multipart/form-data), php://input está vacío — usar $_GET directamente
    $isMultipart = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart') !== false;
    $input = $isMultipart ? [] : (json_decode(file_get_contents('php://input'), true) ?? []);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    // 1. LISTAR BASES DISPONÍVEIS NA PASTA apk_base
    if ($action === 'list_bases') {
        ob_start(); // evitar que warnings contaminen el JSON
        $bases = [];
        $sizes = [];
        if (is_dir($dirBase)) {
            $files = glob($dirBase . '/*.apk') ?: [];
            foreach ($files as $file) {
                $bases[] = basename($file);
                $sizes[basename($file)] = round(filesize($file)/1024/1024, 2);
            }
        }
        ob_end_clean();
        echo json_encode(['success' => true, 'bases' => $bases, 'sizes' => $sizes]);
        exit;
    }

    // ── ACTION: sign_apk — firmar un APK ya generado ────────────────
    // Permite firmar APKs sin recompilar (botón "Firmar APK" en el panel)
    if ($action === 'sign_apk') {
        ob_start();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $filename = basename($input['filename'] ?? '');
        if (empty($filename) || !preg_match('/\.apk$/', $filename)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Filename inválido']);
            exit;
        }
        $apkPath = $dirDown . '/' . $filename;
        if (!file_exists($apkPath)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'APK no encontrado: ' . $filename]);
            exit;
        }

        $signDir       = __DIR__ . '/../sign';
        $keystorePath  = $signDir . '/release.jks';
        $uberSignerJar = $signDir . '/uber-apk-signer.jar';
        $keyAlias      = 'dtunnelkey';
        $keyPass       = 'Dtunnel2024!!';

        @mkdir($signDir, 0755, true);

        // Detectar binarios
        $find = function($name) {
            foreach (['/usr/bin/', '/usr/lib/jvm/java-21-openjdk-amd64/bin/',
                      '/usr/lib/jvm/java-17-openjdk-amd64/bin/',
                      '/usr/lib/jvm/default-java/bin/'] as $base) {
                if (is_executable($base . $name)) return $base . $name;
            }
            $cmd = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
            return $cmd ? trim($cmd) : null;
        };
        $keytool   = $find('keytool');
        $jarsigner = $find('jarsigner');
        $java      = $find('java');

        if (!$java) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Java no instalado. Ejecutá: panel → [23] → [2]']);
            exit;
        }
        // Auto-keystore
        if (!file_exists($keystorePath) && $keytool) {
            @exec(escapeshellcmd($keytool)
                . ' -genkey -v -noprompt'
                . ' -keystore ' . escapeshellarg($keystorePath)
                . ' -alias '    . escapeshellarg($keyAlias)
                . ' -keyalg RSA -keysize 2048 -validity 10000'
                . ' -storepass ' . escapeshellarg($keyPass)
                . ' -keypass '   . escapeshellarg($keyPass)
                . ' -dname "CN=DTunnel,OU=DTunnel,O=DTunnel,L=BuenosAires,S=BA,C=AR" 2>&1');
        }
        // Auto-download uber-apk-signer si falta
        if (!file_exists($uberSignerJar)) {
            $url = 'https://github.com/patrickfav/uber-apk-signer/releases/download/v1.3.0/uber-apk-signer-1.3.0.jar';
            $ctx = stream_context_create(['http' => ['timeout' => 60]]);
            $jarData = @file_get_contents($url, false, $ctx);
            if ($jarData !== false && strlen($jarData) > 100000) {
                @file_put_contents($uberSignerJar, $jarData);
            }
        }

        $signed = false;
        $errorOut = null;

        // Intento 1: uber-apk-signer (V1+V2+V3 + zipalign)
        if (file_exists($keystorePath) && file_exists($uberSignerJar)) {
            $tmpDir = sys_get_temp_dir() . '/sign-' . uniqid();
            @mkdir($tmpDir, 0755, true);
            $tmpApk = $tmpDir . '/' . $filename;
            @copy($apkPath, $tmpApk);

            $cmd = escapeshellarg($java) . ' -jar ' . escapeshellarg($uberSignerJar)
                 . ' --apks '    . escapeshellarg($tmpApk)
                 . ' --out '     . escapeshellarg($tmpDir)
                 . ' --ks '      . escapeshellarg($keystorePath)
                 . ' --ksAlias ' . escapeshellarg($keyAlias)
                 . ' --ksPass '  . escapeshellarg($keyPass)
                 . ' --ksKeyPass '. escapeshellarg($keyPass)
                 . ' --allowResign --overwrite 2>&1';
            $out = []; $rc = 1;
            @exec($cmd, $out, $rc);

            if ($rc === 0) {
                $found = array_merge(
                    glob($tmpDir . '/*-aligned-signed.apk') ?: [],
                    glob($tmpDir . '/*-signed.apk') ?: [],
                    [$tmpApk]
                );
                foreach ($found as $f) {
                    if (file_exists($f) && filesize($f) > 100000) {
                        @unlink($apkPath);
                        @copy($f, $apkPath);
                        @chmod($apkPath, 0644);
                        $signed = true;
                        break;
                    }
                }
            }
            if (!$signed) $errorOut = 'uber-apk-signer: ' . implode(' | ', array_slice($out, -3));
            foreach (glob($tmpDir . '/*') as $tmp) @unlink($tmp);
            @rmdir($tmpDir);
        }

        // Fallback: jarsigner V1
        if (!$signed && $jarsigner && file_exists($keystorePath)) {
            $cmd = escapeshellcmd($jarsigner)
                 . ' -sigalg SHA256withRSA -digestalg SHA-256'
                 . ' -keystore ' . escapeshellarg($keystorePath)
                 . ' -storepass ' . escapeshellarg($keyPass)
                 . ' -keypass '   . escapeshellarg($keyPass)
                 . ' ' . escapeshellarg($apkPath)
                 . ' ' . escapeshellarg($keyAlias) . ' 2>&1';
            $out = []; $rc = 1;
            @exec($cmd, $out, $rc);
            if ($rc === 0) {
                $signed = true;
                $errorOut = null;
            } else {
                $errorOut = 'jarsigner: ' . implode(' | ', array_slice($out, -3));
            }
        }

        ob_end_clean();
        echo json_encode([
            'success' => $signed,
            'error'   => $errorOut,
            'method'  => $signed ? (file_exists($uberSignerJar) ? 'uber-apk-signer (V1+V2+V3)' : 'jarsigner (V1)') : null,
            'size'    => $signed ? filesize($apkPath) : 0,
        ]);
        exit;
    }

    // 4. UPLOAD APK BASE
    if ($action === 'upload_base') {
        // Garantir que a pasta existe e tem permissão de escrita
        if (!is_dir($dirBase)) { @mkdir($dirBase, 0775, true); }
        if (!is_writable($dirBase)) { @chmod($dirBase, 0775); }

        // Detectar se o POST excedeu post_max_size (PHP esvazia $_FILES silenciosamente)
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxBytes  = (int)(ini_get('post_max_size')) * 1024 * 1024;
        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            echo json_encode([
                'success' => false,
                'error'   => 'El archivo supera el límite del servidor (' . ini_get('post_max_size') . '). Ejecutá en la VPS: systemctl restart php*-fpm && systemctl restart apache2 — luego intentá de nuevo. O subí el APK desde dtpanel opción [20].'
            ]); exit;
        }

        if (!isset($_FILES['apk_file'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'Archivo no recibido (límite actual: ' . ini_get('upload_max_filesize') . '). Ejecutá en la VPS: systemctl restart php*-fpm && systemctl restart apache2 — o subí el APK desde dtpanel opción [20].'
            ]); exit;
        }

        $uploadError = $_FILES['apk_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errMsgs = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize del servidor. Subí el APK desde la opción [20] del script dtpanel.',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente. Intentá de nuevo.',
                UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco. Verificá permisos con: chmod 775 /var/www/html/apk_base',
                UPLOAD_ERR_EXTENSION  => 'Una extensión PHP bloqueó la subida.',
            ];
            $msg = $errMsgs[$uploadError] ?? "Error de upload (código $uploadError).";
            echo json_encode(['success' => false, 'error' => $msg]); exit;
        }

        $file = $_FILES['apk_file'];
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'apk') {
            echo json_encode(['success' => false, 'error' => 'Solo se permiten archivos .apk']); exit;
        }
        if ($file['size'] > 200 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Archivo demasiado grande. Máximo: 200MB']); exit;
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $destPath = $dirBase . '/' . $safeName;
        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            @chmod($destPath, 0644);
            echo json_encode(['success' => true, 'filename' => $safeName, 'size' => round($file['size']/1024/1024, 2)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al guardar. Ejecutá en la VPS: chmod 775 /var/www/html/apk_base && chown www-data:www-data /var/www/html/apk_base']);
        }
        exit;
    }

    // 5. DELETE APK BASE
    if ($action === 'delete_base') {
        $filename = $input['filename'] ?? '';
        $filepath = $dirBase . '/' . basename($filename);
        if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'apk') {
            @unlink($filepath);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Archivo no encontrado.']);
        }
        exit;
    }

    // 2. EXCLUIR APK GERADO
    if ($action === 'delete_apk') {
        $filename = $input['filename'] ?? '';
        $filepath = $dirDown . '/' . basename($filename);
        if (file_exists($filepath) && strpos($filepath, '.apk') !== false) {
            @unlink($filepath);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Arquivo não encontrado ou já expirado.']);
        }
        exit;
    }

    // UPLOAD DE LOGO PARA EL APK
    if ($action === 'upload_logo') {
        $logoDir = __DIR__ . '/../assets/img/logos';
        if (!is_dir($logoDir)) { @mkdir($logoDir, 0775, true); }
        if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error al recibir el archivo. Máximo 2MB.']); exit;
        }
        $file  = $_FILES['logo_file'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/png','image/jpeg','image/gif','image/webp'])) {
            echo json_encode(['success' => false, 'error' => 'Solo PNG, JPG, GIF o WEBP.']); exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Máximo 2MB.']); exit;
        }
        $safeName = 'logo_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        if (move_uploaded_file($file['tmp_name'], $logoDir . '/' . $safeName)) {
            @chmod($logoDir . '/' . $safeName, 0644);
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $domain   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            echo json_encode(['success' => true, 'url' => "{$protocol}://{$domain}/assets/img/logos/{$safeName}", 'filename' => $safeName]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al guardar. Verificá permisos del servidor.']);
        }
        exit;
    }

    // 3. O CORAÇÃO: GERAR APK E INJETAR JSON
    if ($action === 'build_apk') {
        // APK grandes (60MB+) necesitan más tiempo y memoria
        @set_time_limit(0);              // sin límite de tiempo
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
        @ignore_user_abort(true);        // continuar aunque el browser desconecte

        $baseFile    = $input['base'] ?? '';
        $appName     = trim($input['name'] ?? 'DTunnelApp');
        $appNameSafe = preg_replace('/[^a-zA-Z0-9]/', '', $appName);
        if ($appNameSafe === '') $appNameSafe = 'DTunnelApp';
        $packageName = trim($input['packageName'] ?? 'com.dtunnel.lite');
        $versionName = preg_replace('/[^a-zA-Z0-9._-]/', '', $input['versionName'] ?? '1.0');
        $versionCode = intval($input['versionCode'] ?? 1);
        $logoUrl     = trim($input['logoUrl'] ?? '');

        if (empty($baseFile)) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Ningúna base selecionada.']); exit;
        }

        $sourcePath = $dirBase . '/' . basename($baseFile);
        if (!file_exists($sourcePath)) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Arquivo base não encontrado no servidor.']); exit;
        }

        if (!is_dir($dirDown)) { @mkdir($dirDown, 0775, true); }
        if (!is_writable($dirDown)) { @chmod($dirDown, 0775); }

        $uniqueId      = substr(md5(uniqid(mt_rand(), true)), 0, 6);
        $cleanFilename = $appNameSafe . '_v' . $versionName . '.apk';
        $finalFilename = $uniqueId . '_' . $cleanFilename;
        $destPath      = $dirDown . '/' . $finalFilename;

        // Para APKs grandes, usar stream copy en vez de copy() directo
        if (filesize($sourcePath) > 30 * 1024 * 1024) {
            // Copia por chunks para APKs > 30MB
            $in  = fopen($sourcePath, 'rb');
            $out = fopen($destPath, 'wb');
            if (!$in || !$out) {
                echo json_encode(['success' => false, 'error' => 'No se pudo abrir archivo base para copiar.']); exit;
            }
            while (!feof($in)) {
                fwrite($out, fread($in, 8 * 1024 * 1024)); // chunks de 8MB
            }
            fclose($in);
            fclose($out);
        } elseif (!@copy($sourcePath, $destPath)) {
            ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Falha ao copiar arquivo base.']); exit;
        }

        $protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $domain      = $_SERVER['HTTP_HOST'] ?? '149.50.134.137';
        $apiEndpoint = "{$protocol}://{$domain}/api/update.php";

        // ── UUID del usuario logado ──────────────────────────────
        // Fix: '---' es truthy en PHP, por eso se verifica explícitamente
        $userUuidFinal = ($userUuid !== '---') ? $userUuid : md5($sessionEmail);

        // ── dtunnelmod.json: IDÉNTICO al JSON de "Copiar Credenciales" del perfil ─────
        // Este archivo es lo que la app lee para saber a qué servidor conectarse
        $dtJson = [
            'cdn'        => "{$apiEndpoint}?type=cdn&uuid={$userUuidFinal}",
            'category'   => "{$apiEndpoint}?type=category&uuid={$userUuidFinal}",
            'app_config' => "{$apiEndpoint}?type=config&uuid={$userUuidFinal}",
            'app_layout' => "{$apiEndpoint}?type=layout&uuid={$userUuidFinal}",
            'app_text'   => "{$apiEndpoint}?type=text&uuid={$userUuidFinal}",
            'credits'    => '@El_NeNe_Sando',
            'channel'    => '@El_NeNe_Sando',
            'group'      => '@El_NeNe_Sando',
        ];
        $dtJsonStr = json_encode($dtJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // ── Cargar datos actuales del panel para embeberlos ──────
        $dbDir = __DIR__ . '/../db';

        // Configs del panel → app_config.json del APK
        $dbConfigs = json_decode(@file_get_contents($dbDir . '/configs.json') ?: '[]', true) ?: [];
        $userConfigs = array_values(array_filter($dbConfigs, function($c) use ($sessionEmail) {
            return strtolower($c['user_email'] ?? '') === strtolower($sessionEmail) && strtoupper($c['status'] ?? 'ACTIVE') === 'ACTIVE';
        }));
        foreach ($userConfigs as &$c) { unset($c['user_email']); }
        unset($c);

        // Categorías → category.json del APK
        $dbCats = json_decode(@file_get_contents($dbDir . '/categories.json') ?: '[]', true) ?: [];
        $userCats = array_values(array_filter($dbCats, function($c) use ($sessionEmail) {
            return strtolower($c['user_email'] ?? '') === strtolower($sessionEmail) && strtoupper($c['status'] ?? 'ACTIVE') === 'ACTIVE';
        }));
        foreach ($userCats as &$c) { unset($c['user_email']); }
        unset($c);

        // CDN → cdn.json del APK
        $dbCdn = json_decode(@file_get_contents($dbDir . '/cdn.json') ?: '[]', true) ?: [];
        $userCdn = array_values(array_filter($dbCdn, function($c) use ($sessionEmail) {
            return strtolower($c['user_email'] ?? '') === strtolower($sessionEmail);
        }));
        foreach ($userCdn as &$c) { unset($c['user_email']); }
        unset($c);

        // Textos → app_text.json del APK (textos personalizados de la interfaz)
        // FIX: usar textos.json (consistente con update.php), fallback a app_texts.json
        $dbTexts = json_decode(@file_get_contents($dbDir . '/textos.json') ?: '[]', true) ?: [];
        if (empty($dbTexts)) {
            $dbTexts = json_decode(@file_get_contents($dbDir . '/app_texts.json') ?: '[]', true) ?: [];
        }
        // Soportar formato mapa-por-email o array-plano-con-user_email
        if (isset($dbTexts[$sessionEmail]) && is_array($dbTexts[$sessionEmail])) {
            $userTexts = $dbTexts[$sessionEmail];
        } else {
            $userTexts = array_values(array_filter($dbTexts, function($t) use ($sessionEmail) {
                return strtolower($t['user_email'] ?? '') === strtolower($sessionEmail);
            }));
            foreach ($userTexts as &$t) { unset($t['user_email']); }
            unset($t);
        }

        // Layout activo → app_config.json del APK (formato [{name,value,type}])
        $dbLayouts = json_decode(@file_get_contents($dbDir . '/app_layouts.json') ?: '[]', true) ?: [];
        $activeLayout = [];
        foreach ($dbLayouts as $l) {
            if (($l['user_email'] ?? '') === $sessionEmail && !empty($l['is_active'])) {
                $activeLayout = $l['layout_data'] ?? [];
                break;
            }
        }
        if (empty($activeLayout) && !empty($dbLayouts)) {
            foreach ($dbLayouts as $l) {
                if (($l['user_email'] ?? '') === $sessionEmail) {
                    $activeLayout = $l['layout_data'] ?? [];
                    break;
                }
            }
        }

        // ── Descargar icono si se proporcionó URL ────────────────
        $iconData = null;
        // Intentar cargar icono: primero URL del usuario, luego icono por defecto del panel
        $defaultIconPath = __DIR__ . '/../assets/img/icono_app_final11.png';
        if (!empty($logoUrl) && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            // Detectar si es URL local del panel → leer archivo directo (más rápido y confiable)
            $localLogoDir = __DIR__ . '/../assets/img/logos/';
            $logoFilename = basename(parse_url($logoUrl, PHP_URL_PATH));
            $localLogoPath = $localLogoDir . $logoFilename;
            if (file_exists($localLogoPath) && is_readable($localLogoPath)) {
                // URL local — leer directo del disco
                $iconData = file_get_contents($localLogoPath);
            } else {
                // URL externa — descargar
                $ctx      = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0']]);
                $iconData = @file_get_contents($logoUrl, false, $ctx);
            }
            if ($iconData && strlen($iconData) > 100) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_buffer($finfo, $iconData);
                finfo_close($finfo);
                if (!in_array($mime, ['image/png','image/jpeg','image/gif','image/webp'])) {
                    $iconData = null;
                }
                // La conversión a PNG 512x512 se hace una sola vez en el bloque de abajo
            } else {
                $iconData = null;
            }
        }
        // Si no hay URL o falló la descarga, usar el icono por defecto del panel
        if ($iconData === null && file_exists($defaultIconPath)) {
            $iconData = file_get_contents($defaultIconPath);
        }

        // ── Convertir icono a PNG 512x512 con GD (evita "pantalla negra" por JPEG/WebP inyectado)
        if ($iconData !== null && extension_loaded('gd')) {
            $imgSrc = @imagecreatefromstring($iconData);
            if ($imgSrc !== false) {
                $canvas = imagecreatetruecolor(512, 512);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefill($canvas, 0, 0, $transparent);
                imagecopyresampled($canvas, $imgSrc, 0, 0, 0, 0, 512, 512, imagesx($imgSrc), imagesy($imgSrc));
                imagedestroy($imgSrc);
                ob_start();
                imagepng($canvas, null, 6);
                $iconData = ob_get_clean();
                imagedestroy($canvas);
            }
        }

// ══════════════════════════════════════════════════════════════════════════
// MÉTODO IN-MEMORY: Lee TODO el APK base en RAM, modifica y reescribe.
// Evita corrupción de ZipArchive append en APKs grandes (>30MB).
// Soporta SUPER_PRO y SUPER_LITE únicamente (ElnenePro / ElneneLite).
// Firma automática con uber-apk-signer (V1+V2+V3 + zipalign integrado).
// ══════════════════════════════════════════════════════════════════════════
        $zipOk   = false;
        $iconOk  = false;
        $signed  = false;
        $apkType = 'SUPER_LITE';

        if (class_exists('ZipArchive')) {

            // ── PASO 0: Leer TODOS los archivos del APK base en memoria ──────
            $zipRead = new ZipArchive();
            if ($zipRead->open($sourcePath) !== true) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No se pudo abrir el APK base.']);
                exit;
            }
            $allFiles     = [];
            $origCompress = [];
            for ($ri = 0; $ri < $zipRead->numFiles; $ri++) {
                $stat = $zipRead->statIndex($ri);
                if ($stat === false) continue;
                $fname = $stat['name'];
                if (preg_match('#^META-INF/#', $fname)) continue;
                $allFiles[$fname]     = $zipRead->getFromIndex($ri);
                $origCompress[$fname] = $stat['comp_method'] ?? 0;
            }
            $zipRead->close();
            unset($zipRead);
            gc_collect_cycles();

            // ── Detectar tipo: SUPER_PRO o SUPER_LITE ──────────────────────
            $apkType = dtunnel_detect_apk_type($allFiles);

            // ── PASO 1: configs del usuario + sync categorías ───────────────
            $configsFile = __DIR__ . '/../db/configs.json';
            $userConfigsLocal = [];
            if (file_exists($configsFile)) {
                $allConfigsLocal = json_decode(file_get_contents($configsFile), true) ?: [];
                $userConfigsLocal = array_values(array_filter($allConfigsLocal, function($c) use ($sessionEmail) {
                    return strtolower($c['user_email'] ?? '') === strtolower($sessionEmail)
                        && strtoupper($c['status'] ?? 'ACTIVE') === 'ACTIVE';
                }));
                foreach ($userConfigsLocal as &$cf) { unset($cf['user_email']); }
                unset($cf);
            }

            // Expandir category_id → objeto category (formato esperado por la app)
            $catFile = __DIR__ . '/../db/categories.json';
            $allCatsLocal = file_exists($catFile) ? (json_decode(file_get_contents($catFile), true) ?: []) : [];
            $userCatsMap = [];
            foreach ($allCatsLocal as $cat) {
                if (strtolower($cat['user_email'] ?? '') === strtolower($sessionEmail)) {
                    $userCatsMap[$cat['id'] ?? 0] = $cat;
                }
            }
            foreach ($userConfigsLocal as &$cfgLocal) {
                if (!isset($cfgLocal['category']) || !is_array($cfgLocal['category']) || !isset($cfgLocal['category']['name'])) {
                    $cid = $cfgLocal['category_id'] ?? ($cfgLocal['category'] ?? null);
                    if ($cid !== null && isset($userCatsMap[$cid])) {
                        $catObj = $userCatsMap[$cid];
                        $cfgLocal['category'] = [
                            'id'     => (int)($catObj['id'] ?? 0),
                            'name'   => $catObj['name'] ?? 'Default',
                            'color'  => $catObj['color'] ?? '#FF808080',
                            'sorter' => (int)($catObj['sorter'] ?? 1),
                            'status' => 'ACTIVE',
                        ];
                    } elseif (!isset($cfgLocal['category']) || !is_array($cfgLocal['category'])) {
                        $cfgLocal['category'] = ['id' => 0, 'name' => 'Default', 'color' => '#FF808080', 'sorter' => 1, 'status' => 'ACTIVE'];
                    }
                }
                unset($cfgLocal['category_id']);
            }
            unset($cfgLocal);

            // ── SYNC categorías: registrar todas las embebidas + rellenar id+status ──
            // CRÍTICO: la app DTunnel matchea config.category.id con category.json[].id.
            // Sin id, los configs son rechazados → "Nenhuma configuração encontrada".
            $catByName = [];
            foreach ($userCats ?: [] as $catItem) {
                if (!empty($catItem['name'])) {
                    $catItem['status'] = $catItem['status'] ?? 'ACTIVE';
                    $catByName[strtolower($catItem['name'])] = $catItem;
                }
            }
            // Pasada 1: registrar categorías embebidas en configs
            foreach ($userConfigsLocal as $cfg) {
                $embCat = $cfg['category'] ?? null;
                if (!is_array($embCat) || empty($embCat['name'])) continue;
                $key = strtolower($embCat['name']);
                if (!isset($catByName[$key])) {
                    $genId = (int)hexdec(substr(md5($embCat['name']), 0, 7));
                    $catByName[$key] = [
                        'id'     => $genId,
                        'name'   => $embCat['name'],
                        'color'  => $embCat['color']  ?? '#FF808080',
                        'sorter' => (int)($embCat['sorter'] ?? 1),
                        'status' => 'ACTIVE',
                    ];
                }
            }
            // Pasada 2: rellenar id+status en category de cada config (CRÍTICO)
            foreach ($userConfigsLocal as &$cfgRef) {
                $embCat = $cfgRef['category'] ?? null;
                if (!is_array($embCat) || empty($embCat['name'])) continue;
                $key = strtolower($embCat['name']);
                $registered = $catByName[$key] ?? null;
                if ($registered) {
                    $cfgRef['category'] = [
                        'id'     => (int)($embCat['id']     ?? $registered['id']),
                        'name'   => $embCat['name'],
                        'color'  => $embCat['color']  ?? $registered['color'],
                        'sorter' => (int)($embCat['sorter'] ?? $registered['sorter']),
                        'status' => $embCat['status'] ?? 'ACTIVE',
                    ];
                }
            }
            unset($cfgRef);
            $finalCats = array_values($catByName);

            // ── PASO 2: AHORA SÍ escribir config.json (con category.id rellenado) ──
            // RAW ARRAY (mismo formato que endpoint app_config del SDK)
            $allFiles['assets/config.json'] = json_encode(
                array_values($userConfigsLocal ?: []),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            // ── PASO 3: dtunnelmod.json (URLs panel para updates online) ────
            $allFiles['assets/dtunnelmod.json'] = $dtJsonStr;

            // ── PASO 4: user_id.txt — NUNCA MODIFICAR ───────────────────────
            // Token binario interno del APK base. Se preserva tal cual estaba.

            // ── PASO 5: category.json + cdn.json (RAW ARRAY) ────────────────
            $allFiles['assets/category.json'] = json_encode(
                $finalCats, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $allFiles['assets/cdn.json'] = json_encode(
                array_values($userCdn ?: []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $defaultTexts = [
                ['label'=>'LBL_BTN_START','text'=>'INICIAR'],['label'=>'LBL_BTN_STOPPING','text'=>'PARANDO'],
                ['label'=>'LBL_BTN_STOP','text'=>'PARAR'],['label'=>'LBL_BTN_RECONNECT','text'=>'RECONECTAR'],
                ['label'=>'LBL_DISCONNECTED','text'=>'<b>Desconectado</b>'],['label'=>'LBL_RECORD','text'=>'REGISTRO'],
                ['label'=>'LBL_CHOOSE_CONFIG','text'=>'ESCOLHA UMA CONFIGURAÇÃO'],['label'=>'LBL_UUID','text'=>'UUID V2Ray'],
                ['label'=>'LBL_USERNAME','text'=>'Nome de usuário'],['label'=>'LBL_PASSWORD','text'=>'Senha'],
                ['label'=>'LBL_UUID_INVALID','text'=>'UUID inválido'],['label'=>'LBL_USERNAME_INVALID','text'=>'Nome de usuário inválido'],
                ['label'=>'LBL_PASSWORD_INVALID','text'=>'Senha inválida'],
                ['label'=>'LBL_USERNAME_PASSWORD_INVALID','text'=>'Por favor, preencha o usuário e senha'],
                ['label'=>'LBL_CONFIG_TITLE','text'=>'Configuração'],['label'=>'LBL_INITIALIZING_APP','text'=>'Inicializando aplicação'],
                ['label'=>'LBL_CONFIG_LOADED','text'=>'Configuração carregada'],
                ['label'=>'LBL_SEARCHING_FOR_UPDATES','text'=>'Procurando atualizações'],
                ['label'=>'LBL_CONFIG_UPDATED','text'=>'Configurações atualizadas com sucesso'],
                ['label'=>'LBL_APP_CONFIG_UPDATED','text'=>'Configurações do app atualizadas com sucesso'],
                ['label'=>'LBL_APP_TEXT_UPDATED','text'=>'Textos do app atualizados com sucesso'],
                ['label'=>'LBL_CONFIG_NOT_SUPPORTED','text'=>'Parece que essa configuração não é suportada neste aplicativo'],
                ['label'=>'LBL_ERROR_ESTABLISHING_CONNECTION_SSH','text'=>'<b>Erro ao estabelecer conexão SSH</b>'],
                ['label'=>'LBL_RECONNECTION_PROCESS','text'=>'Processo de reconexão'],['label'=>'LBL_RECONNECTING_IN','text'=>'Reconectando em: %ss'],
                ['label'=>'LBL_RECONNECTING','text'=>'Reconectando...'],['label'=>'LBL_CONNECTING','text'=>'Conectando...'],
                ['label'=>'LBL_STOPPING','text'=>'Parando...'],['label'=>'LBL_LOCAL_IP','text'=>'IP Local: %s'],
                ['label'=>'LBL_DISPLAY_LOCAL_IP','text'=>'{NETWORK}: {IP}'],
                ['label'=>'LBL_LOCAL_IP_INFO','text'=>'IPv4 Local: %1$s/%2$d MTU: %3$d'],
                ['label'=>'LBL_DNS_SERVER_INFO','text'=>'Servidor DNS: %s'],['label'=>'LBL_ROUTES_INFO_INCL','text'=>'Rotas: %s'],
                ['label'=>'LBL_ROUTES_INFO_EXCL','text'=>'Rotas excluídas: %s'],
                ['label'=>'LBL_INVALID_CONFIG_OVPN','text'=>'Configuração OVPN inválida'],['label'=>'LBL_ERROR','text'=>'Erro: %s'],
                ['label'=>'LBL_CONFIG_NOT_FOUND_TITLE','text'=>'Configuração não encontrada'],
                ['label'=>'LBL_CONFIG_NOT_FOUND_TEXT','text'=>'Nenhuma configuração encontrada'],
                ['label'=>'LBL_STOP_APPLICATION','text'=>'Para continuar, para a aplicação'],
                ['label'=>'LBL_FINGERPRINT','text'=>'<b>Impressão digital: %s</b>'],['label'=>'LBL_AUTHENTICATING','text'=>'Autenticando...'],
                ['label'=>'LBL_AUTHENTICATION_SUCCESS','text'=>'<b>Autenticação realizada com sucesso</>'],
                ['label'=>'LBL_AUTHENTICATION_FAILED','text'=>'Falha na autenticação'],
                ['label'=>'LBL_AUTHENTICATION_FAILED_TEXT','text'=>'Não foi possível autenticar com servidor.'],
                ['label'=>'LBL_STATE_CONNECTED','text'=>'Conectado'],['label'=>'LBL_STATE_DISCONNECTED','text'=>'Desconectado'],
                ['label'=>'LBL_STATE_CONNECTING','text'=>'Conectando'],['label'=>'LBL_STATE_STOPPING','text'=>'Parando'],
                ['label'=>'LBL_STATE_NO_NETWORK','text'=>'Sem acesso à internet'],['label'=>'LBL_STATE_AUTH','text'=>'Autenticando'],
                ['label'=>'LBL_STATE_AUTH_FAILED','text'=>'Falha na autenticação'],['label'=>'LBL_STATE_UNKNOWN','text'=>'Desconhecido'],
                ['label'=>'LBL_STATE_ASSIGN_IP','text'=>'Atribuindo IP'],['label'=>'LBL_STATE_ADD_ROUTES','text'=>'Adicionando rotas'],
                ['label'=>'LBL_STATE_RECONNECTING','text'=>'Reconectando'],['label'=>'LBL_STATE_EXITING','text'=>'Saindo'],
                ['label'=>'LBL_STATE_RESOLVE','text'=>'Resolvendo'],['label'=>'LBL_STATE_TCP_CONNECT','text'=>'Conectando (TCP)'],
                ['label'=>'LBL_STATE_VPN_GENERATE_CONFIG','text'=>'Gerando configuração'],['label'=>'LBL_STATE_WAIT','text'=>'Aguardando'],
                ['label'=>'LBL_STATE_GET_CONFIG','text'=>'Obtendo configuração'],['label'=>'LBL_VPN_ESTABLISHED','text'=>'<b>VPN estabelecido</b>'],
                ['label'=>'LBL_APP_VERSION','text'=>'<b>%s %s %s</b>'],['label'=>'LBL_MOBILE_INFO','text'=>'<b>%s | %s | %s | %s</b>'],
                ['label'=>'LBL_CHECKING_USER','text'=>'Verificando usuário...'],['label'=>'LBL_CHECKING_USER_FAILED','text'=>'Falha ao verificar usuário'],
                ['label'=>'LBL_CONFIG_NOT_SELECTED','text'=>'Nenhuma configuração selecionada'],
                ['label'=>'LBL_CONFIG_NOT_ACTIVE','text'=>'Parece que a configuração selecionada não está ativa'],
                ['label'=>'LBL_CLEAR_APP_TITLE','text'=>'LIMPAR APLICATIVO'],
                ['label'=>'LBL_CLEAR_APP_MESSAGE','text'=>'VOCÊ TEM CERTEZA QUE QUER LIMPAR O APLICATIVO?'],
                ['label'=>'LBL_VPN_PERMISSION_DENIED','text'=>'ERRO AO ESTABELECER CONEXÃO VPN'],
                ['label'=>'LBL_YES','text'=>'Sim'],['label'=>'LBL_NO','text'=>'Não'],
                ['label'=>'LBL_CDN_COUNT','text'=>'CDNs: %02d'],
            ];
            if (!empty($userTexts)) {
                $textMap = [];
                foreach ($defaultTexts as $dt) { $textMap[$dt['label']] = $dt; }
                foreach ($userTexts as $ut) { if (!empty($ut['label'])) $textMap[$ut['label']] = $ut; }
                $finalTexts = array_values($textMap);
            } else {
                $finalTexts = $defaultTexts;
            }
            // FORMATO {"content": [array]} — match con app_config.json (layout)
            $allFiles['assets/app_text.json'] = json_encode(
                ['content' => $finalTexts],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            // ── PASO 5: app_config.json (layout) ──────────────────────────
            $defaultTemplate = '[{"name":"APP_LOGO","value":null,"type":"IMAGE"},{"name":"APP_BACKGROUND_IMAGE","value":null,"type":"IMAGE"},{"name":"APP_BACKGROUND_TYPE","value":{"options":[{"label":"Imagem","value":"IMAGE"},{"label":"Color","value":"COLOR"}],"selected":"COLOR"},"type":"SELECT"},{"name":"APP_BACKGROUND_COLOR","value":"#FF0e16c6","type":"COLOR"},{"name":"APP_CARD_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_CARD_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_CARD_STATUS_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_CARD_STATUS_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_CARD_CONFIG_COLOR","value":"#0E171EC9","type":"COLOR"},{"name":"APP_DIALOG_BACKGROUND_COLOR","value":"#050C5AE4","type":"COLOR"},{"name":"APP_DIALOG_LOGGER_COLOR","value":"#080e16c7","type":"COLOR"},{"name":"APP_BORDER_COLOR","value":"#1d242e00","type":"COLOR"},{"name":"APP_INPUT_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_INPUT_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_TEXT_COLOR","value":"#FFFFFFFF","type":"COLOR"},{"name":"APP_BUTTON_COLOR","value":"#1d242e73","type":"COLOR"},{"name":"APP_BUTTON_RADIUS","value":25,"type":"INTEGER"},{"name":"APP_ICON_COLOR","value":"#FFFFFFFF","type":"COLOR"},{"name":"APP_SHOW_CONNECTION_MODE","value":true,"type":"BOOLEAN"},{"name":"APP_CONFIG_AUTO_UPDATE","value":false,"type":"BOOLEAN"},{"name":"APP_CONNECTION_LIMITER","value":false,"type":"BOOLEAN"},{"name":"APP_BTN_UPDATE_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_BTN_LOGGER_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_BTN_PAGE_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_BTN_MENU_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_UPDATE_LAST_SEEN_ENABLED","value":false,"type":"BOOLEAN"},{"name":"APP_CONFIG_LOCATION_PERMISSION","value":true,"type":"BOOLEAN"},{"name":"APP_DIALOG_ERROR_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_CHECKUSER_DIALOG_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_SUCCESS_TOAST_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_ERROR_TOAST_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_LOCAL_IP_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_CONFIG_FILTER_ENABLED","value":false,"type":"BOOLEAN"},{"name":"APP_PING_SERVICE_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_CDN_COUNT_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_AIRPLANE_MODE","value":true,"type":"BOOLEAN"},{"name":"APP_AIRPLANE_MODE_TIMEOUT","value":1,"type":"INTEGER"},{"name":"APP_ALERT_SOUND_ENABLED","value":true,"type":"BOOLEAN"},{"name":"APP_LAYOUT_WEBVIEW_ENABLED","value":false,"type":"BOOLEAN"},{"name":"APP_MESSAGE","value":null,"type":"TEXT"},{"name":"APP_MESSAGE_TYPE","value":{"options":[{"label":"Alerta","value":"ALERT"},{"label":"Info","value":"INFO"},{"label":"Bienvenida","value":"WELCOME"},{"label":"Sin mensaje","value":"NONE"}],"selected":"NONE"},"type":"SELECT"},{"name":"APP_LAYOUT_WEBVIEW","value":null,"type":"HTML"},{"name":"APP_SUPPORT_BUTTON","value":null,"type":"HTML"},{"name":"APP_WEB_VIEW","value":null,"type":"HTML"}]';
            $mergedConfig = [];
            foreach (json_decode($defaultTemplate, true) as $item) {
                if (!empty($item['name'])) $mergedConfig[$item['name']] = $item;
            }
            $apkKnownFields = array_keys($mergedConfig);
            $rawBaseConfig = isset($allFiles['assets/app_config.json']) ? $allFiles['assets/app_config.json'] : false;
            if ($rawBaseConfig !== false) {
                $parsedBase = json_decode($rawBaseConfig, true);
                if (!empty($parsedBase['content']) && is_array($parsedBase['content'])) {
                    foreach ($parsedBase['content'] as $item) {
                        if (!empty($item['name'])) $apkKnownFields[] = $item['name'];
                    }
                    $apkKnownFields = array_unique($apkKnownFields);
                    foreach ($parsedBase['content'] as $item) {
                        if (empty($item['name']) || $item['value'] === null) continue;
                        if ($item['name'] === 'APP_LAYOUT_WEBVIEW_ENABLED' && $item['value'] === false
                            && isset($mergedConfig[$item['name']]) && $mergedConfig[$item['name']]['value'] === true) continue;
                        $mergedConfig[$item['name']] = $item;
                    }
                }
            }
            $noNullFields = ['APP_LAYOUT_WEBVIEW','APP_LAYOUT_WEBVIEW_ENABLED','APP_SUPPORT_BUTTON','APP_WEB_VIEW'];
            if (!empty($activeLayout)) {
                foreach ($activeLayout as $item) {
                    unset($item['label']);
                    $name = $item['name'] ?? '';
                    if (empty($name)) continue;
                    if (in_array($name, $noNullFields)) {
                        $baseVal = $mergedConfig[$name]['value'] ?? null;
                        $userVal = $item['value'];
                        if ($userVal === null || ($userVal === false && $baseVal === true)) continue;
                    }
                    $mergedConfig[$name] = $item;
                }
            }
            // Sanitizar HTML: si es muy largo o tiene <!DOCTYPE>, anular
            $htmlFieldsToSanitize = ['APP_SUPPORT_BUTTON', 'APP_LAYOUT_WEBVIEW', 'APP_WEB_VIEW'];
            foreach ($htmlFieldsToSanitize as $hf) {
                if (isset($mergedConfig[$hf]) && is_string($mergedConfig[$hf]['value'] ?? null)) {
                    $htmlVal = trim($mergedConfig[$hf]['value']);
                    if (strlen($htmlVal) > 2048 || stripos($htmlVal, '<!DOCTYPE') !== false || preg_match('#^\s*<html[\s>]#i', $htmlVal)) {
                        $mergedConfig[$hf]['value'] = null;
                    }
                }
            }
            // Si APP_LAYOUT_WEBVIEW es null, forzar ENABLED=false (evita pantalla negra)
            if (isset($mergedConfig['APP_LAYOUT_WEBVIEW']) && $mergedConfig['APP_LAYOUT_WEBVIEW']['value'] === null) {
                if (isset($mergedConfig['APP_LAYOUT_WEBVIEW_ENABLED'])) {
                    $mergedConfig['APP_LAYOUT_WEBVIEW_ENABLED']['value'] = false;
                }
            }
            // CRÍTICO: APP_CONFIG_AUTO_UPDATE=false → primer launch usa configs offline embebidas
            $mergedConfig['APP_CONFIG_AUTO_UPDATE'] = ['name' => 'APP_CONFIG_AUTO_UPDATE', 'value' => false, 'type' => 'BOOLEAN'];
            // Campos extra que algunas versiones de SUPER esperan
            $extraAppFields = [
                ['name' => 'APP_CURRENT_VERSION',             'value' => null,  'type' => 'STRING'],
                ['name' => 'APP_DOWNLOAD_URL',                'value' => null,  'type' => 'STRING'],
                ['name' => 'APP_CONNECTIVITY_CHECK_ENABLED',  'value' => false, 'type' => 'BOOLEAN'],
                ['name' => 'APP_DIALOG_NOTIFICATION_ENABLED', 'value' => true,  'type' => 'BOOLEAN'],
            ];
            foreach ($extraAppFields as $ef) {
                if (!isset($mergedConfig[$ef['name']])) $mergedConfig[$ef['name']] = $ef;
                $apkKnownFields[] = $ef['name'];
            }
            $apkKnownFields = array_unique($apkKnownFields);
            $filteredConfig = !empty($apkKnownFields)
                ? array_filter($mergedConfig, function($i) use ($apkKnownFields) {
                    return in_array($i['name'] ?? '', $apkKnownFields);
                  })
                : $mergedConfig;
            // FORMATO {"content": [array]} — el LAYOUT funcionaba con este wrapper.
            // El nombre del archivo y el parser de la app son distintos al config.json.
            $allFiles['assets/app_config.json'] = json_encode(
                ['content' => array_values($filteredConfig)],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            // ── PASO 6: Reemplazar icono ──────────────────────────────────
            if ($iconData !== null) {
                $iconPaths = [
                    'res/drawable/ic_launcher.png',
                    'res/drawable-mdpi/ic_launcher.png',   'res/drawable-hdpi/ic_launcher.png',
                    'res/drawable-xhdpi/ic_launcher.png',  'res/drawable-xxhdpi/ic_launcher.png',
                    'res/drawable-xxxhdpi/ic_launcher.png',
                    'res/mipmap-mdpi-v4/ic_launcher.png',  'res/mipmap-hdpi-v4/ic_launcher.png',
                    'res/mipmap-xhdpi-v4/ic_launcher.png', 'res/mipmap-xxhdpi-v4/ic_launcher.png',
                    'res/mipmap-xxxhdpi-v4/ic_launcher.png',
                    'res/mipmap-mdpi-v4/ic_launcher_round.png',  'res/mipmap-hdpi-v4/ic_launcher_round.png',
                    'res/mipmap-xhdpi-v4/ic_launcher_round.png', 'res/mipmap-xxhdpi-v4/ic_launcher_round.png',
                    'res/mipmap-xxxhdpi-v4/ic_launcher_round.png',
                ];
                $replaced = 0;
                foreach ($iconPaths as $path) {
                    if (isset($allFiles[$path])) {
                        $allFiles[$path] = $iconData;
                        $origCompress[$path] = 0; // STORE para imágenes
                        $replaced++;
                    }
                }
                $iconOk = ($replaced > 0);
            }

            // ── PASO 7: Escribir nuevo APK desde cero ────────────────────
            // STORE solo para resources.arsc (Android exige) e imágenes (ya comprimidas).
            // NUNCA forzar STORE en lib/ — eso descomprime las .so nativas (60MB → 160MB!)
            // Para todo lo demás, preservamos la compresión original del APK base.
            $storePatterns = [
                '#^resources\.arsc$#',
                '#^res/.*\.(png|jpg|gif|webp|9\.png)$#',
            ];
            if (file_exists($destPath)) @unlink($destPath);
            $zipWrite = new ZipArchive();
            if ($zipWrite->open($destPath, ZipArchive::CREATE) === true) {
                foreach ($allFiles as $fname => $content) {
                    $useStore = false;
                    foreach ($storePatterns as $pat) {
                        if (preg_match($pat, $fname)) { $useStore = true; break; }
                    }
                    if (!$useStore && isset($origCompress[$fname]) && $origCompress[$fname] === 0) {
                        $useStore = true;
                    }
                    $zipWrite->addFromString($fname, $content);
                    if (method_exists($zipWrite, 'setCompressionName')) {
                        $zipWrite->setCompressionName($fname, $useStore ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
                    }
                }
                $zipWrite->close();
                unset($zipWrite);
                gc_collect_cycles();
                $zipOk = file_exists($destPath) && filesize($destPath) > 100000;
            }
            unset($allFiles);
            gc_collect_cycles();

            // ── PASO 8: Firma automática con autosetup ─────────────────────
            // Si falta uber-apk-signer, intenta descargarlo. Si falta keystore, lo crea.
            // Reporta el motivo exacto si la firma falla, para diagnóstico.
            $signError = null;
            if ($zipOk) {
                $signDir       = __DIR__ . '/../sign';
                $keystorePath  = $signDir . '/release.jks';
                $uberSignerJar = $signDir . '/uber-apk-signer.jar';
                $keyAlias      = 'dtunnelkey';
                $keyPass       = 'Dtunnel2024!!';

                @mkdir($signDir, 0755, true);

                // Detectar keytool/jarsigner/java
                $find = function($name) {
                    foreach (['/usr/bin/', '/usr/lib/jvm/java-21-openjdk-amd64/bin/',
                              '/usr/lib/jvm/java-17-openjdk-amd64/bin/',
                              '/usr/lib/jvm/default-java/bin/'] as $base) {
                        if (is_executable($base . $name)) return $base . $name;
                    }
                    $cmd = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
                    return $cmd ? trim($cmd) : null;
                };
                $keytool   = $find('keytool');
                $jarsigner = $find('jarsigner');
                $java      = $find('java');

                if (!$java) {
                    $signError = 'Java JDK no instalado. Ejecutá en VPS: panel → [23] Setup Firma APK';
                }

                // Auto-generar keystore en primer uso
                if (!file_exists($keystorePath) && $keytool) {
                    @exec(escapeshellcmd($keytool)
                        . ' -genkey -v -noprompt'
                        . ' -keystore ' . escapeshellarg($keystorePath)
                        . ' -alias '    . escapeshellarg($keyAlias)
                        . ' -keyalg RSA -keysize 2048 -validity 10000'
                        . ' -storepass ' . escapeshellarg($keyPass)
                        . ' -keypass '   . escapeshellarg($keyPass)
                        . ' -dname "CN=DTunnel,OU=DTunnel,O=DTunnel,L=BuenosAires,S=BA,C=AR" 2>&1');
                    @chmod($keystorePath, 0640);
                }

                // Auto-descargar uber-apk-signer si falta y hay Java
                if (!file_exists($uberSignerJar) && $java) {
                    $url = 'https://github.com/patrickfav/uber-apk-signer/releases/download/v1.3.0/uber-apk-signer-1.3.0.jar';
                    $ctx = stream_context_create(['http' => ['timeout' => 60]]);
                    $jarData = @file_get_contents($url, false, $ctx);
                    if ($jarData !== false && strlen($jarData) > 100000) {
                        @file_put_contents($uberSignerJar, $jarData);
                        @chmod($uberSignerJar, 0644);
                    }
                }

                // Intento principal: uber-apk-signer (V1+V2+V3 + zipalign)
                if ($java && file_exists($keystorePath) && file_exists($uberSignerJar)) {
                    $tmpDir = sys_get_temp_dir() . '/dtunnel-sign-' . uniqid();
                    @mkdir($tmpDir, 0755, true);
                    $tmpApk = $tmpDir . '/' . basename($destPath);
                    @copy($destPath, $tmpApk);

                    $cmd = escapeshellarg($java) . ' -jar ' . escapeshellarg($uberSignerJar)
                         . ' --apks '       . escapeshellarg($tmpApk)
                         . ' --out '        . escapeshellarg($tmpDir)
                         . ' --ks '         . escapeshellarg($keystorePath)
                         . ' --ksAlias '    . escapeshellarg($keyAlias)
                         . ' --ksPass '     . escapeshellarg($keyPass)
                         . ' --ksKeyPass '  . escapeshellarg($keyPass)
                         . ' --allowResign --overwrite 2>&1';
                    $signOut = []; $signRc = 1;
                    @exec($cmd, $signOut, $signRc);

                    if ($signRc === 0) {
                        $candidates = array_merge(
                            glob($tmpDir . '/*-aligned-signed.apk') ?: [],
                            glob($tmpDir . '/*-signed.apk') ?: [],
                            [$tmpApk]
                        );
                        foreach ($candidates as $cand) {
                            if (file_exists($cand) && filesize($cand) > 100000) {
                                @unlink($destPath);
                                @copy($cand, $destPath);
                                $signed = true;
                                break;
                            }
                        }
                    }
                    if (!$signed) {
                        $signError = 'uber-apk-signer falló: ' . implode(' | ', array_slice($signOut, -3));
                    }
                    foreach (glob($tmpDir . '/*') as $tmp) @unlink($tmp);
                    @rmdir($tmpDir);
                }

                // Fallback: jarsigner V1 only
                if (!$signed && $jarsigner && file_exists($keystorePath)) {
                    $cmd = escapeshellcmd($jarsigner)
                         . ' -sigalg SHA256withRSA -digestalg SHA-256'
                         . ' -keystore ' . escapeshellarg($keystorePath)
                         . ' -storepass ' . escapeshellarg($keyPass)
                         . ' -keypass '   . escapeshellarg($keyPass)
                         . ' ' . escapeshellarg($destPath)
                         . ' ' . escapeshellarg($keyAlias) . ' 2>&1';
                    $signOut = []; $signRc = 1;
                    @exec($cmd, $signOut, $signRc);
                    if ($signRc === 0) {
                        $signed = true;
                        $signError = null;
                    } else {
                        if (!$signError) $signError = 'jarsigner falló: ' . implode(' | ', array_slice($signOut, -3));
                    }
                }

                if (!$signed && !$signError) {
                    $signError = 'Setup de firma incompleto. Ejecutá: panel → [23] Setup Firma APK';
                }
            }
        }

                // Verificar que el APK final existe
        if (!$zipOk || !file_exists($destPath) || filesize($destPath) < 10000) {
            ob_end_clean();
            $errDetail = !class_exists('ZipArchive') ? ' [ZipArchive no disponible en PHP]' :
                         (!$zipOk ? ' [ZipArchive no pudo abrir el APK]' : ' [archivo inválido]');
            echo json_encode(['success' => false, 'error' => 'Error generando APK.' . $errDetail]);
            exit;
        }

        $downloadUrl = "{$protocol}://{$domain}/downloads/{$finalFilename}";

        ob_end_clean(); // limpiar cualquier output acumulado
        echo json_encode([
            'success'       => true,
            'download_url'  => $downloadUrl,
            'real_url'      => $downloadUrl,
            'filename'      => $finalFilename,
            'clean_name'    => $cleanFilename,
            'uuid'          => $uniqueId,
            'date'          => date('d/m/Y, H:i'),
            'zip_injected'  => $zipOk,
            'icon_replaced' => $iconOk,
            'signed'        => $signed,
            'sign_error'    => $signError ?? null,
            'apk_type'      => $apkType ?? 'SUPER_LITE',
            'app_name'      => $appName,
            'package_name'  => $packageName,
        ]);
        exit;
    }

    // DEBUG: Verificar qué se inyectaría sin generar APK
    if ($action === 'debug_apk') {
        $protocol2    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $domain2      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $apiEndpoint2 = "{$protocol2}://{$domain2}/api/update.php";
        $uuid2        = ($userUuid !== '---') ? $userUuid : md5($sessionEmail);
        echo json_encode([
            'success'       => true,
            'uuid_injected' => $uuid2,
            'email'         => $sessionEmail,
            'dtunnelmod'    => [
                'cdn'        => "{$apiEndpoint2}?type=cdn&uuid={$uuid2}",
                'category'   => "{$apiEndpoint2}?type=category&uuid={$uuid2}",
                'app_config' => "{$apiEndpoint2}?type=config&uuid={$uuid2}",
                'app_layout' => "{$apiEndpoint2}?type=layout&uuid={$uuid2}",
                'app_text'   => "{$apiEndpoint2}?type=text&uuid={$uuid2}",
            ],
            'user_id_txt'   => $uuid2 . ':' . md5($sessionEmail),
            'note'          => 'Este es el contenido exacto que se inyecta en la APK',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']); exit;
}

$pageTitle = 'Generar APK';
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ==========================================================================
   ESTILOS PREMIUM - GERADOR DE APK (IDÊNTICO AO ORIGINAL)
   ========================================================================== */
.apk-wrapper {
    --card-bg: #ffffff; --card-border: #e2e8f0; --text-main: #0f172a; --text-muted: #64748b; --text-subtle: #94a3b8;
    --inner-bg: #f8fafc; --primary: #10b981; --primary-light: #d1fae5; --accent: #3b82f6; --success: #10b981; --danger: #ef4444; 
    --log-bg: #0f172a; --log-text: #cbd5e1; --log-card-border: #1e293b;
    padding: 16px; max-width: 900px; margin: 0 auto; font-family: 'Manrope', system-ui, sans-serif;
    display: flex; flex-direction: column; gap: 24px;
}

:root.dark .apk-wrapper, body.dark .apk-wrapper {
    --card-bg: #1e1e24; --card-border: #2d2d35; --text-main: #f8fafc; --text-muted: #94a3b8; --text-subtle: #64748b;
    --inner-bg: #18181b; --log-bg: #0b0f19; --log-text: #cbd5e1; --log-card-border: #1e293b;
    --primary: #10b981; --primary-light: rgba(16, 185, 129, 0.1);
}

.apk-wrapper * { outline: none; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

h1.main-title { font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin: 0; }

/* CARTÕES PADRÃO */
.apk-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px; padding: 24px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.015);}
.card-header-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 10px; }
.card-desc { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); margin: -10px 0 0 0; line-height: 1.5; }

/* BLOCO TOP: Resumo da Build */
.build-summary-box { display: flex; align-items: center; gap: 16px; background: transparent; border: 1px solid var(--card-border); border-radius: 20px; padding: 16px 20px; }
.bsb-icon { width: 46px; height: 46px; border-radius: 14px; background: var(--inner-bg); border: 1px solid var(--card-border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); flex-shrink: 0; }
.bsb-icon svg { width: 22px; stroke-width: 2.2px; }
.bsb-info { display: flex; flex-direction: column; flex: 1; }
.bsb-lbl { font-size: 0.72rem; font-weight: 800; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;}
.bsb-val { font-size: 1.05rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;}

.summary-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }

/* CAIXA DE LINKS DA SESSÃO */
.session-links-header { display: flex; gap: 10px; margin-bottom: 5px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none;}
.session-links-header::-webkit-scrollbar { display: none; }
.sl-tab { padding: 8px 20px; border-radius: 50px; font-size: 0.85rem; font-weight: 800; border: 1px solid var(--card-border); background: transparent; color: var(--text-muted); cursor: pointer; transition: 0.2s; white-space: nowrap;}
.sl-tab.active { background: var(--inner-bg); color: var(--text-main); border-color: var(--card-border); }
.empty-links { border: 1px dashed var(--card-border); border-radius: 20px; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: var(--inner-bg); }
.empty-links svg { width: 32px; color: var(--text-muted); margin-bottom: 12px; }
.empty-links span { font-size: 1rem; font-weight: 800; color: var(--text-main); }
.empty-links p { font-size: 0.85rem; color: var(--text-subtle); margin: 4px 0 0 0; }

/* FORMULÁRIO DE COMPILAÇÃO */
.form-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-label { font-size: 0.85rem; font-weight: 800; color: var(--text-main); }
.form-input, .form-select { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px 18px; font-size: 0.95rem; font-weight: 600; color: var(--text-main); outline: none; transition: border 0.2s; appearance: none; }
.form-select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; background-size: 18px; padding-right: 40px; cursor: pointer; }
.form-input:focus, .form-select:focus { border-color: var(--text-muted); }

/* PACOTE OFFLINE */
.offline-grid { display: flex; flex-direction: column; gap: 12px; }
.checkbox-item { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; cursor: pointer; transition: 0.2s; }
.checkbox-item:active { transform: scale(0.98); }
.custom-cb { width: 24px; height: 24px; border-radius: 8px; border: 2px solid var(--card-border); background: transparent; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.checkbox-item input:checked + .custom-cb { background: var(--accent); border-color: var(--accent); }
.custom-cb svg { width: 14px; color: white; opacity: 0; transform: scale(0.5); transition: 0.2s; stroke-width: 3.5px; }
.checkbox-item input:checked + .custom-cb svg { opacity: 1; transform: scale(1); }
.cb-label { font-size: 0.95rem; font-weight: 800; color: var(--text-main); }

.btn-master-build { width: 100%; background: var(--inner-bg); border: 1px solid var(--card-border); color: var(--text-main); padding: 18px; border-radius: 18px; font-size: 1rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: 0.2s; margin-top: 10px; }
.btn-master-build:active { transform: scale(0.96); background: var(--card-border); }
.btn-master-build svg { width: 20px; stroke-width: 2.2px; }

/* ==========================================================================
   TELA DE COMPILAÇÃO EM ANDAMENTO (Terminal Premium Colorrigido)
   ========================================================================== */
#build-progress-screen { display: none; flex-direction: column; gap: 20px; animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.prog-status-row { display: flex; align-items: center; gap: 16px; background: transparent; border: 1px solid var(--card-border); border-radius: 20px; padding: 16px 20px; }
.prog-icon-pulse { width: 46px; height: 46px; border-radius: 50%; background: transparent; border: 1px solid var(--card-border); color: var(--text-main); display: flex; align-items: center; justify-content: center; }
.prog-icon-pulse svg { width: 22px; color: var(--text-muted); stroke-width: 2.2px; }

.prog-bar-container { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.prog-bar-header { display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; font-weight: 800; color: var(--text-main); }
.prog-bar-bg { width: 100%; height: 8px; background: var(--inner-bg); border-radius: 10px; overflow: hidden; position: relative;}
.prog-bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #0ea5e9, #10b981); border-radius: 10px; transition: width 0.3s ease-out; position: relative;}

/* TERMINAL DE LOGS PERFEITO DA IMAGEM */
.terminal-card { background: var(--log-bg); border-radius: 24px; overflow: hidden; border: 1px solid var(--log-card-border); display: flex; flex-direction: column; margin-top: 10px;}
.terminal-header { background: transparent; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 20px 20px 16px 20px; display: flex; justify-content: space-between; align-items: flex-start; }
.th-title-wrap { display: flex; gap: 12px; align-items: flex-start; }
.th-title { font-size: 0.95rem; font-weight: 800; color: #fff; line-height: 1.3; }
.th-subtitle { font-size: 0.75rem; color: var(--text-subtle); font-weight: 500; }
.th-icon { color: #f8fafc; margin-top: 2px; }
.th-live { background: rgba(59, 130, 246, 0.15); color: #60a5fa; padding: 4px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 900; letter-spacing: 1px; border: 1px solid rgba(59, 130, 246, 0.3); animation: blink 1.5s infinite;}
@keyframes blink { 50% { opacity: 0.6; } }

.terminal-body { padding: 16px; height: 350px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; font-family: 'Space Grotesk', monospace; scrollbar-width: thin; scrollbar-color: #334155 transparent;}
.terminal-body::-webkit-scrollbar { width: 6px; }
.terminal-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }

/* Estrutura do Log IDÊNTICA ao print enviado */
.log-entry { display: flex; align-items: flex-start; gap: 14px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 16px; padding: 16px; animation: slideUpLog 0.2s ease-out; }
.log-icon-left { width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0; }
.log-icon-left svg { width: 16px; stroke-width: 2.2px; }
.log-content { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.log-top-row { display: flex; align-items: center; gap: 10px; }
.log-num { font-size: 0.8rem; font-weight: 800; color: #94a3b8; letter-spacing: 0.5px;}
.log-badge-label { background: rgba(59, 130, 246, 0.15); color: #60a5fa; padding: 2px 8px; border-radius: 6px; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.5px; border: 1px solid rgba(59, 130, 246, 0.3); }
.log-text { font-size: 0.85rem; color: var(--log-text); line-height: 1.5; word-break: break-all; }

/* ==========================================================================
   TELA DE SUCESSO E HISTÓRICO 
   ========================================================================== */
#build-success-screen { display: none; flex-direction: column; gap: 24px; animation: fadeIn 0.4s ease-out; }

/* Item do Histórico */
.history-item { background: transparent; border: 1px solid var(--card-border); border-radius: 20px; padding: 16px; display: flex; flex-direction: column; gap: 14px; margin-bottom: 12px; }
.hi-top { display: flex; align-items: center; gap: 12px; }
.hi-icon { width: 46px; height: 46px; border-radius: 14px; background: var(--inner-bg); border: 1px solid var(--card-border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); flex-shrink: 0; }
.hi-info { display: flex; flex-direction: column; }
.hi-title { font-size: 1.05rem; font-weight: 800; color: var(--text-main); }
.hi-sub { font-size: 0.75rem; font-weight: 700; color: var(--text-subtle); text-transform: uppercase; margin-top: 2px; line-height: 1.4;}

/* Rolagem perfeita para o link sem cortar */
.hi-link-wrapper { background: var(--inner-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 12px 14px; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;}
.hi-link-wrapper::-webkit-scrollbar { display: none; }
.hi-link-text { font-family: monospace; font-size: 0.85rem; color: var(--text-main); white-space: nowrap; font-weight: 600;}

/* Botões do histórico adaptáveis para mobile */
.hi-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; width: 100%;}
.btn-hi { background: transparent; border: 1px solid var(--card-border); border-radius: 14px; padding: 12px 0; display: flex; justify-content: center; align-items: center; color: var(--text-main); cursor: pointer; transition: 0.2s; width: 100%;}
.btn-hi:active { transform: scale(0.95); background: var(--inner-bg); }
.btn-hi.trash { color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }
.btn-hi svg { width: 20px; stroke-width: 2.2px; }

.btn-clear-history { width: 100%; background: transparent; border: 1px solid var(--card-border); color: var(--text-muted); padding: 14px; border-radius: 16px; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: center; cursor: pointer; margin-bottom: 16px; }

/* Card APK Pronto (Verde) */
.apk-pronto-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 24px; padding: 24px; display: flex; flex-direction: column; gap: 16px; position: relative; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.015);}
.apk-pronto-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--primary); }

.ap-header { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0; }
.ap-desc { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin: -10px 0 6px 0;}

.ap-status-box { background: transparent; border: 1px solid var(--card-border); border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 14px; }
.ap-status-icon { width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--card-border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
.ap-status-icon svg { width: 18px; stroke-width: 2.5px; }
.ap-status-icon.check { color: var(--primary); border-color: var(--primary-light); background: var(--primary-light); }

.final-link-container { border: 1px solid var(--primary); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 8px; background: var(--primary-light); margin-top: 10px; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;}
.final-link-container::-webkit-scrollbar { display: none; }
.flc-label { font-size: 0.7rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }
.flc-input { font-family: monospace; font-size: 0.85rem; color: var(--text-main); white-space: nowrap; font-weight: 600; }

.ap-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px; width: 100%;}
.btn-ap { background: transparent; border: 1px solid var(--card-border); border-radius: 14px; padding: 14px 0; font-weight: 800; font-size: 0.8rem; color: var(--text-main); display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: 0.2s; white-space: nowrap;}
.btn-ap:active { transform: scale(0.95); background: var(--inner-bg); }
.btn-ap.btn-sign { color: #60a5fa; border-color: rgba(96, 165, 250, 0.3); }
.btn-ap.signed-ok { color: var(--primary); border-color: var(--primary-light); background: var(--primary-light); }
.btn-ap.btn-sign[disabled] { opacity: 0.7; cursor: wait; }
.btn-ap svg.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-ap svg { width: 18px; stroke-width: 2.2px; }

@media (max-width: 480px) {
    .ap-buttons, .hi-actions { display: flex; flex-direction: column; }
    .btn-ap, .btn-hi { width: 100%; padding: 14px; }
}

/* TOASTS GLOBAIS */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 1000000; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 16px 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; width: auto; min-width: 250px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast.show { transform: translateX(0); }
.toast-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; background: var(--primary); flex-shrink: 0;}
.toast.error .toast-icon { background: var(--danger); }
.toast-msg { font-size: 0.95rem; font-weight: 800; color: var(--text-main); line-height: 1.3;}

/* SWAL CUSTOM */
.swal-modal-custom { background: var(--card-bg) !important; border: 1px solid var(--card-border) !important; border-radius: 24px !important; padding: 24px !important; width: 90% !important; max-width: 400px !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important; }
.swal-title-custom { font-size: 1.3rem !important; font-weight: 800 !important; color: var(--text-main) !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 6px !important; text-align: left !important;}
.swal-desc-custom { font-size: 0.85rem !important; color: var(--text-muted) !important; font-weight: 500 !important; font-family: 'Manrope', sans-serif !important; margin-bottom: 24px !important; text-align: left !important;}
.swal2-actions { width: 100% !important; display: flex !important; gap: 12px !important; margin-top: 10px !important;}
.swal-btn-cancel, .swal-btn-confirm { flex: 1 !important; border-radius: 14px !important; padding: 16px !important; font-weight: 800 !important; border: none !important; cursor: pointer !important; font-size: 0.95rem !important; transition: transform 0.15s !important; outline: none !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;}
.swal-btn-cancel:active, .swal-btn-confirm:active { transform: scale(0.95) !important; }
.swal-btn-cancel { background: var(--inner-bg) !important; color: var(--text-main) !important; border: 1px solid var(--card-border) !important; }
.swal-btn-confirm.danger { background: #ef4444 !important; color: white !important;}

/* ── UPLOAD BASE APK ─────────────────────────────────────────── */
.upload-base-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:24px;padding:24px;display:flex;flex-direction:column;gap:16px;box-shadow:0 4px 20px rgba(0,0,0,0.015);}
.upload-drop-zone{border:2px dashed var(--card-border);border-radius:16px;padding:28px 20px;text-align:center;cursor:pointer;transition:all .2s;background:var(--inner-bg);}
.upload-drop-zone:hover,.upload-drop-zone.dragover{border-color:var(--primary);background:var(--primary-light);}
.upload-drop-zone svg{width:36px;height:36px;color:var(--text-muted);margin-bottom:8px;}
.upload-drop-lbl{font-size:.9rem;font-weight:700;color:var(--text-main);}
.upload-drop-sub{font-size:.78rem;color:var(--text-muted);margin-top:4px;}
.upload-prog-area{display:none;}
.upload-prog-bar{height:6px;background:var(--card-border);border-radius:3px;overflow:hidden;margin-top:6px;}
.upload-prog-fill{height:100%;background:var(--primary);border-radius:3px;transition:width .3s;}
.base-list-wrap{display:flex;flex-direction:column;gap:8px;}
.base-item{display:flex;align-items:center;gap:10px;background:var(--inner-bg);border:1px solid var(--card-border);border-radius:12px;padding:10px 14px;}
.base-item-ico{width:34px;height:34px;background:var(--primary-light);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--primary);flex-shrink:0;}
.base-item-name{flex:1;font-size:.88rem;font-weight:700;color:var(--text-main);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.base-item-size{font-size:.76rem;color:var(--text-muted);white-space:nowrap;}
.base-item-del{width:28px;height:28px;border-radius:7px;background:transparent;border:1px solid var(--card-border);color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0;}
.base-item-del:hover{background:#fee2e2;border-color:#ef4444;color:#ef4444;}
.no-bases-msg{text-align:center;color:var(--text-muted);font-size:.83rem;padding:14px;}
</style>

<div id="toast-container"></div>

<div class="apk-wrapper">

    <h1 class="main-title" data-i18n="gerar_apk_title">Generar APK</h1>

    <div id="build-form-screen" style="display:flex; flex-direction:column; gap:24px;">
        
        <div class="apk-card">
            <h2 class="card-header-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;color:var(--text-muted);"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg> <span data-i18n="config_build">Configuración de build</span></h2>
            <p class="card-desc" data-i18n="config_build_desc">Defina os parâmetros e inicie uma nova compilação.</p>
            <div style="display:flex; gap:10px; margin-top:-5px; margin-bottom: 5px;">
                <span style="background:transparent; border:1px solid var(--card-border); padding:4px 12px; border-radius:20px; font-size:0.7rem; font-weight:800; color:var(--text-muted);" data-i18n="pronto">PRONTO</span>
                <span style="background:transparent; border:1px solid var(--card-border); padding:4px 12px; border-radius:20px; font-size:0.7rem; font-weight:800; color:var(--text-muted);" data-i18n="auto_update">ATUALIZAÇÃO AUTOMÁTICA</span>
            </div>

            <div class="summary-grid">
                <div class="build-summary-box">
                    <div class="bsb-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></div>
                    <div class="bsb-info">
                        <span class="bsb-lbl" data-i18n="base_selecionada">BASE SELECIONADA</span>
                        <span class="bsb-val" id="lbl-top-base">DTunnel Lite</span>
                    </div>
                </div>
                <div class="build-summary-box">
                    <div class="bsb-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg></div>
                    <div class="bsb-info">
                        <span class="bsb-lbl" data-i18n="versao">VERSÃO</span>
                        <span class="bsb-val" id="lbl-top-version">4.5.12 (22)</span>
                    </div>
                </div>
                <div class="build-summary-box" style="cursor:pointer;" onclick="scrollToLinks()">
                    <div class="bsb-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                    <div class="bsb-info">
                        <span class="bsb-lbl" data-i18n="links_sessao_lbl">LINKS DESTA SESSÃO</span>
                        <span class="bsb-val"><span id="lbl-top-links-count">0</span> <span data-i18n="registros">registros</span></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="apk-card" id="session-links-card">
            <h2 class="card-header-title" data-i18n="links_sessao">Links desta sessão</h2>
            <p class="card-desc" data-i18n="links_desc">Histórico dos APKs gerados durante esta sessão.</p>
            
            <div class="session-links-header">
                <button class="sl-tab active" id="tab-0-links" data-i18n="zero_links">0 links</button>
                <button class="sl-tab" data-i18n="sessao_atual">Sesión atual</button>
            </div>
            
            <div class="empty-links" id="empty-links-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <span data-i18n="nenhum_link">Ningún link nesta sessão</span>
                <p data-i18n="gere_um_apk">Gere um APK para que os links apareçam aqui.</p>
            </div>

            <div id="filled-links-state" style="display:none; flex-direction:column;">
                <button class="btn-clear-history" onclick="clearAllLinks()" data-i18n="limpar_hist">Limpar histórico</button>
                <div id="history-list" style="display:flex; flex-direction:column;"></div>
            </div>
        </div>


        <!-- ══ APK BASE UPLOAD ══════════════════════════════════════ -->
        <div class="upload-base-card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h2 class="card-header-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:20px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    APK Base
                </h2>
                <span style="font-size:.75rem;color:var(--text-muted);font-weight:600;">Solo admin &middot; Max 150 MB</span>
            </div>
            <p class="card-desc" style="margin:0;">Sube el APK base aqui. Quedara disponible en el selector de compilacion.</p>
            <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:12px;padding:10px 14px;font-size:.78rem;color:#d97706;font-weight:600;">
                ⚠ Si el upload falla por tamaño, ejecutá en la VPS:<br>
                <code style="font-size:.75rem;background:rgba(0,0,0,0.15);padding:2px 6px;border-radius:5px;display:inline-block;margin-top:4px;">systemctl restart php*-fpm &amp;&amp; systemctl restart apache2</code><br>
                <span style="font-weight:400;margin-top:4px;display:block;">O subí el APK desde la VPS con <b>dtpanel → opción [20]</b></span>
            </div>
            <div class="upload-drop-zone" id="upldz" onclick="document.getElementById('apk-file-inp').click()" ondragover="upDragOver(event)" ondragleave="upDragLeave(event)" ondrop="upDrop(event)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div class="upload-drop-lbl">Arrasta el .apk aqui o hace clic</div>
                <div class="upload-drop-sub">Archivos .apk unicamente &middot; Maximo 150 MB</div>
                <input type="file" id="apk-file-inp" accept=".apk" style="display:none" onchange="upFileSelect(this)">
            </div>
            <div class="upload-prog-area" id="upld-prog">
                <div style="display:flex;justify-content:space-between;"><span id="upld-fname" style="font-size:.83rem;font-weight:700;color:var(--text-main);"></span><span id="upld-pct" style="font-size:.83rem;color:var(--text-muted);">0%</span></div>
                <div class="upload-prog-bar"><div class="upload-prog-fill" id="upld-pfill" style="width:0%"></div></div>
            </div>
            <div>
                <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-subtle);margin-bottom:8px;">Bases disponibles</div>
                <div class="base-list-wrap" id="base-list-wrap"><div class="no-bases-msg" id="no-bases-msg">Cargando...</div></div>
            </div>
        </div>

        <div class="apk-card">
            <h2 class="card-header-title" data-i18n="param_comp">Parâmetros da compilação</h2>
            <div style="width:100%; height:1px; background:var(--card-border); margin:10px 0;"></div>
            
            <div style="display:flex; justify-content:center; margin-bottom:10px;">
                <span style="background:transparent; border:1px solid var(--card-border); padding:6px 20px; border-radius:20px; font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">BUILD PROFILE</span>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" data-i18n="lbl_base">Versión base</label>
                    <select class="form-select" id="inp-base" onchange="updateTopLabels()">
                        <option value="">Cargando bases...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="lbl_nome">Nombre do APK</label>
                    <input type="text" class="form-input" id="inp-name" value="ElNene Lite">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="lbl_pacote">Nombre pacote</label>
                    <input type="text" class="form-input" id="inp-package" value="com.elnene.lite">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="lbl_ver_name">Nombre da versão</label>
                    <input type="text" class="form-input" id="inp-ver-name" value="4.5.12" oninput="updateTopLabels()">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="lbl_ver_code">Código da versão</label>
                    <input type="text" class="form-input" id="inp-ver-code" value="22" oninput="updateTopLabels()">
                </div>

                <div class="form-group">
                    <label class="form-label" data-i18n="lbl_logo">URL da logo</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" class="form-input" id="inp-logo" placeholder="https://exemplo.com/icon.png" style="flex:1;">
                        <input type="file" id="logo-file-inp" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none" onchange="uploadLogoFile(this)">
                        <button type="button" id="logo-upload-btn" onclick="document.getElementById('logo-file-inp').click()"
                            style="background:var(--inner-bg);border:1px solid var(--card-border);border-radius:12px;padding:14px 16px;font-weight:800;font-size:0.8rem;color:var(--text-main);cursor:pointer;white-space:nowrap;transition:0.2s;flex-shrink:0;">
                            📁 Subir
                        </button>
                    </div>
                    <div id="logo-preview-wrap" style="display:none;margin-top:8px;display:flex;align-items:center;gap:10px;">
                        <img id="logo-preview-img" style="width:48px;height:48px;border-radius:10px;object-fit:contain;border:1px solid var(--card-border);background:var(--inner-bg);">
                        <span id="logo-preview-name" style="font-size:0.8rem;color:var(--text-muted);"></span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <h3 style="font-size:1rem; font-weight:800; color:var(--text-main); margin:0 0 5px 0;" data-i18n="pacote_off">Pacote offline</h3>
                <p class="card-desc" style="margin-bottom:15px;" data-i18n="pacote_desc">Escolha quais dados vão embarcados no APK gerado.</p>
                
                <div class="offline-grid">
                    <label class="checkbox-item"><input type="checkbox" style="display:none;" checked><div class="custom-cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div><span class="cb-label" data-i18n="cb_tema">Tema</span></label>
                    <label class="checkbox-item"><input type="checkbox" style="display:none;" checked><div class="custom-cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div><span class="cb-label" data-i18n="cb_textos">Textos</span></label>
                    <label class="checkbox-item"><input type="checkbox" style="display:none;" checked><div class="custom-cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div><span class="cb-label" data-i18n="cb_cdns">CDNs</span></label>
                    <label class="checkbox-item"><input type="checkbox" style="display:none;"><div class="custom-cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div><span class="cb-label" data-i18n="cb_config">Configuraciones</span></label>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                <button class="btn-master-build" style="width:auto; padding: 14px 24px;" onclick="startBuildProcess()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span data-i18n="btn_gerar">Generar APK</span>
                </button>
            </div>
        </div>
    </div>

    <!-- TELA PROGRESSO (Ritmo e UI idênticos ao print) -->
    <div id="build-progress-screen">
        <div class="apk-card" style="padding: 24px; padding-bottom: 10px;">
            <div style="display:flex; align-items:center; gap: 16px; margin-bottom: 10px;">
                <div class="bsb-icon" style="color: var(--primary);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></div>
                <div>
                    <h1 class="main-title" style="font-size: 1.3rem;" id="title-gerando" data-i18n="gerando">Gerando...</h1>
                    <p class="card-desc" style="margin-top:2px;" id="desc-gerando" data-i18n="gerando_desc">Acompanhe o progresso e os logs retornados pelo compilador.</p>
                </div>
            </div>
            
            <div style="display:flex; gap:10px; margin-bottom: 20px;">
                <span id="badge-pct-top" style="background:var(--primary-light); color:var(--primary); padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:800;">0%</span>
                <span style="background:transparent; border:1px solid var(--card-border); padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:800; color:var(--text-muted);" data-i18n="auto_update">ATUALIZAÇÃO AUTOMÁTICA</span>
            </div>

            <div class="summary-grid" style="margin-bottom: 10px;">
                <div class="prog-status-row">
                    <div class="prog-icon-pulse"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
                    <div style="flex:1;">
                        <div class="prog-info-lbl" data-i18n="status">STATUS</div>
                        <div class="prog-info-val" id="lbl-status-build" data-i18n="em_processo">Em processamento</div>
                    </div>
                </div>

                <div class="prog-status-row">
                    <div class="prog-icon-pulse"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
                    <div style="flex:1;">
                        <div class="prog-info-lbl" data-i18n="progresso">PROGRESSO</div>
                        <div class="prog-info-val" id="lbl-pct-build">0%</div>
                    </div>
                </div>

                <div class="prog-status-row">
                    <div class="prog-icon-pulse"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>
                    <div style="flex:1;">
                        <div class="prog-info-lbl" data-i18n="eventos">EVENTOS</div>
                        <div class="prog-info-val"><span id="lbl-logs-count">0</span> <span data-i18n="registros">registros</span></div>
                    </div>
                </div>
            </div>

            <div class="prog-bar-container">
                <div class="prog-bar-header">
                    <span data-i18n="progresso_comp">Progresso da compilação</span>
                    <span id="lbl-bar-pct" style="background:var(--inner-bg); padding:4px 10px; border-radius:10px; font-size:0.75rem; border:1px solid var(--card-border);">0%</span>
                </div>
                <div class="prog-bar-bg">
                    <div class="prog-bar-fill" id="bar-fill-build"></div>
                </div>
            </div>
            
            <div class="terminal-card">
                <div class="terminal-header">
                    <div class="th-title-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;" class="th-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <div>
                            <div class="th-title" data-i18n="logs_comp">Logs da compilação</div>
                            <div class="th-subtitle" data-i18n="saida_tempo_real">Saída em tempo real do build</div>
                        </div>
                    </div>
                    <span class="th-live">LIVE</span>
                </div>
                <div class="terminal-body" id="terminal-body"></div>
            </div>
        </div>
    </div>

    <!-- TELA SUCESSO (Botões Responsivos e Link com Rolagem Perfeita) -->
    <div id="build-success-screen">
        
        <div class="apk-card">
            <h2 class="card-header-title" data-i18n="links_sessao">Links desta sessão</h2>
            <p class="card-desc" data-i18n="links_desc">Histórico dos APKs gerados durante esta sessão.</p>
            
            <div class="session-links-header">
                <button class="sl-tab active" id="tab-0-links-success">1 links</button>
                <button class="sl-tab" data-i18n="sessao_atual">Sesión atual</button>
            </div>
            
            <div id="filled-links-state-success" style="display:flex; flex-direction:column; margin-top:10px;">
                <button class="btn-clear-history" onclick="clearAllLinks()" data-i18n="limpar_hist">Limpar histórico</button>
                <div id="history-list-success" style="display:flex; flex-direction:column;"></div>
            </div>
        </div>

        <div class="apk-pronto-card">
            <h2 class="ap-header" data-i18n="apk_pronto">APK pronto</h2>
            <p class="ap-desc" data-i18n="apk_pronto_desc">A compilação terminou e o arquivo já esta disponível para download.</p>

            <div class="ap-status-box">
                <div class="ap-status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></div>
                <div style="flex:1;">
                    <div style="font-size:0.7rem; font-weight:800; color:var(--text-subtle); text-transform:uppercase;" data-i18n="base">BASE</div>
                    <div style="font-size:0.95rem; font-weight:800; color:var(--text-main);" id="ap-base-name">base.mod.pro</div>
                </div>
            </div>

            <div class="ap-status-box">
                <div class="ap-status-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></div>
                <div style="flex:1;">
                    <div style="font-size:0.7rem; font-weight:800; color:var(--text-subtle); text-transform:uppercase;" data-i18n="versao">VERSÃO</div>
                    <div style="font-size:0.95rem; font-weight:800; color:var(--text-main);" id="ap-ver-name">4.5.12</div>
                </div>
            </div>

            <div class="ap-status-box">
                <div class="ap-status-icon check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div style="flex:1;">
                    <div style="font-size:0.7rem; font-weight:800; color:var(--text-subtle); text-transform:uppercase;" data-i18n="status">STATUS</div>
                    <div style="font-size:0.95rem; font-weight:800; color:var(--text-main);" data-i18n="concluido">Concluído</div>
                </div>
            </div>

            <div class="final-link-container">
                <span class="flc-label" data-i18n="link_final">LINK FINAL DE DOWNLOAD</span>
                <div class="flc-input" id="final-apk-link" style="overflow-x:auto; -webkit-overflow-scrolling: touch; padding-bottom: 2px;">https://...</div>
            </div>

            <div class="ap-buttons">
                <button class="btn-ap btn-sign" id="btn-sign-apk" onclick="signCurrentApk()" title="Firmar APK"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg> <span>Firmar APK</span></button>
                <button class="btn-ap" onclick="copyFinalLink()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> <span data-i18n="copiar_link">Copiar link</span></button>
                <button class="btn-ap" onclick="downloadFinalApk()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> <span data-i18n="baixar_apk">Descargar APK</span></button>
                <button class="btn-ap" onclick="resetToForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg> <span data-i18n="gerar_outro">Generar outro</span></button>
            </div>
        </div>

    </div>

</div>

<?php
$pageContent = ob_get_clean();

$extraJs = <<<JS
<script>
// ======================================================================
// INTEGRAÇÃO i18n GLOBAL (Trabalha em conjunto com o header.php)
// ======================================================================
const localTranslations = {
    'pt': {
        'gerar_apk_title': 'Generar APK', 'config_build': 'Configuración de build', 'config_build_desc': 'Defina os parâmetros e inicie uma nova compilação.',
        'pronto': 'PRONTO', 'auto_update': 'ATUALIZAÇÃO AUTOMÁTICA', 'base_selecionada': 'BASE SELECIONADA', 'versao': 'VERSÃO',
        'links_sessao_lbl': 'LINKS DESTA SESSÃO', 'registros': 'registros', 'links_sessao': 'Links desta sessão', 'links_desc': 'Histórico dos APKs gerados durante esta sessão.',
        'zero_links': '0 links', 'sessao_atual': 'Sesión atual', 'nenhum_link': 'Ningún link nesta sessão', 'gere_um_apk': 'Gere um APK para que os links apareçam aqui.',
        'param_comp': 'Parâmetros da compilação', 'lbl_base': 'Versión base', 'lbl_nome': 'Nombre do APK', 'lbl_pacote': 'Nombre pacote',
        'lbl_ver_name': 'Nombre da versão', 'lbl_ver_code': 'Código da versão', 'lbl_logo': 'URL da logo',
        'pacote_off': 'Pacote offline', 'pacote_desc': 'Escolha quais dados vão embarcados no APK gerado.', 
        'cb_tema': 'Tema', 'cb_textos': 'Textos', 'cb_cdns': 'CDNs', 'cb_config': 'Configuraciones', 'btn_gerar': 'Generar APK',
        'gerando': 'Gerando...', 'gerando_desc': 'Acompanhe o progresso e os logs retornados pelo compilador.', 'compilando': 'Compilação em andamento', 'compilando_desc': 'Build ativo com 89% concluído e logs em tempo real.',
        'status': 'STATUS', 'em_processo': 'Em processamento', 'progresso': 'PROGRESSO', 'eventos': 'EVENTOS',
        'progresso_comp': 'Progresso da compilação', 'logs_comp': 'Logs da compilação', 'saida_tempo_real': 'Saída em tempo real do build',
        'limpar_hist': 'Limpar histórico', 'apk_pronto': 'APK pronto', 'apk_pronto_desc': 'A compilação terminou e o arquivo já esta disponível para download.',
        'base': 'BASE', 'concluido': 'Concluído', 'link_final': 'LINK FINAL DE DOWNLOAD',
        'copiar_link': 'Copiar link', 'baixar_apk': 'Descargar APK', 'gerar_outro': 'Generar outro',
        'toast_copied': 'Link copiado!', 'toast_deleted': 'APK removido do servidor.'
    },
    'en': {
        'gerar_apk_title': 'Generate APK', 'config_build': 'Build Configuration', 'config_build_desc': 'Set parameters and start a new build.',
        'pronto': 'READY', 'auto_update': 'AUTO UPDATE', 'base_selecionada': 'SELECTED BASE', 'versao': 'VERSION',
        'links_sessao_lbl': 'LINKS THIS SESSION', 'registros': 'records', 'links_sessao': 'Links of this session', 'links_desc': 'History of APKs generated during this session.',
        'zero_links': '0 links', 'sessao_atual': 'Current session', 'nenhum_link': 'No link in this session', 'gere_um_apk': 'Generate an APK for links to appear here.',
        'param_comp': 'Build Parameters', 'lbl_base': 'Base Version', 'lbl_nome': 'APK Name', 'lbl_pacote': 'Package Name',
        'lbl_ver_name': 'Version Name', 'lbl_ver_code': 'Version Code', 'lbl_logo': 'Logo URL',
        'pacote_off': 'Offline Package', 'pacote_desc': 'Choose which data goes embedded in generated APK.', 
        'cb_tema': 'Theme', 'cb_textos': 'Texts', 'cb_cdns': 'CDNs', 'cb_config': 'Settings', 'btn_gerar': 'Generate APK',
        'gerando': 'Generating...', 'gerando_desc': 'Track progress and logs returned by the compiler.', 'compilando': 'Compilation in progress', 'compilando_desc': 'Active build 89% complete with real-time logs.',
        'status': 'STATUS', 'em_processo': 'Processing', 'progresso': 'PROGRESS', 'eventos': 'EVENTS',
        'progresso_comp': 'Build Progress', 'logs_comp': 'Compilation Logs', 'saida_tempo_real': 'Real-time build output',
        'limpar_hist': 'Clear history', 'apk_pronto': 'APK ready', 'apk_pronto_desc': 'Compilation has finished and the file is ready for download.',
        'base': 'BASE', 'concluido': 'Completed', 'link_final': 'FINAL DOWNLOAD LINK',
        'copiar_link': 'Copy link', 'baixar_apk': 'Download APK', 'gerar_outro': 'Generate another',
        'toast_copied': 'Link copied!', 'toast_deleted': 'APK removed from server.'
    },
    'es': {
        'gerar_apk_title': 'Generar APK', 'config_build': 'Configuración de build', 'config_build_desc': 'Defina parámetros e inicie una nueva compilación.',
        'pronto': 'LISTO', 'auto_update': 'ACTUALIZACIÓN AUTOMÁTICA', 'base_selecionada': 'BASE SELECCIONADA', 'versao': 'VERSIÓN',
        'links_sessao_lbl': 'ENLACES DE ESTA SESIÓN', 'registros': 'registros', 'links_sessao': 'Enlaces de esta sesión', 'links_desc': 'Historial de APKs generados en esta sesión.',
        'zero_links': '0 enlaces', 'sessao_atual': 'Sesión actual', 'nenhum_link': 'Ningún enlace en esta sesión', 'gere_um_apk': 'Genere un APK para que los enlaces aparezcan aquí.',
        'param_comp': 'Parámetros de compilación', 'lbl_base': 'Versión base', 'lbl_nome': 'Nombre del APK', 'lbl_pacote': 'Nombre paquete',
        'lbl_ver_name': 'Nombre de versión', 'lbl_ver_code': 'Código de versión', 'lbl_logo': 'URL del logo',
        'pacote_off': 'Paquete offline', 'pacote_desc': 'Elija qué datos van incrustados en el APK generado.', 
        'cb_tema': 'Tema', 'cb_textos': 'Textos', 'cb_cdns': 'CDNs', 'cb_config': 'Ajustes', 'btn_gerar': 'Generar APK',
        'gerando': 'Generando...', 'gerando_desc': 'Siga el progreso y los registros devueltos por el compilador.', 'compilando': 'Compilación en progreso', 'compilando_desc': 'Build activo al 89% con registros en tiempo real.',
        'status': 'ESTADO', 'em_processo': 'En proceso', 'progresso': 'PROGRESO', 'eventos': 'EVENTOS',
        'progresso_comp': 'Progreso de compilación', 'logs_comp': 'Registros de compilación', 'saida_tempo_real': 'Salida en tiempo real del build',
        'limpar_hist': 'Limpiar historial', 'apk_pronto': 'APK listo', 'apk_pronto_desc': 'La compilación ha terminado y el archivo está listo para su descarga.',
        'base': 'BASE', 'concluido': 'Completado', 'link_final': 'ENLACE FINAL DE DESCARGA',
        'copiar_link': 'Copiar enlace', 'baixar_apk': 'Descargar APK', 'gerar_outro': 'Generar otro',
        'toast_copied': '¡Enlace copiado!', 'toast_deleted': 'APK eliminado del servidor.'
    }
};

// Injeta as traduções locais no motor global do header
if (typeof window.globalTranslations !== 'undefined') {
    Object.keys(localTranslations).forEach(lang => {
        if (!window.globalTranslations[lang]) window.globalTranslations[lang] = {};
        Object.assign(window.globalTranslations[lang], localTranslations[lang]);
    });
}

function getMsg(key) { 
    const lang = localStorage.getItem('app_language') || 'pt'; 
    return (window.globalTranslations && window.globalTranslations[lang] && window.globalTranslations[lang][key]) 
        ? window.globalTranslations[lang][key] 
        : (localTranslations['pt'][key] || key); 
}

function showToastRaw(text, type = 'success') {
    const container = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast \${type}`;
    let iconSvg = '<polyline points="20 6 9 17 4 12"/>';
    if (type === 'error') iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    t.innerHTML = `<div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;">\${iconSvg}</svg></div><div class="toast-msg">\${text}</div>`;
    container.appendChild(t); requestAnimationFrame(()=>t.classList.add('show'));
    setTimeout(()=>{t.classList.remove('show'); setTimeout(()=>t.remove(), 300)}, 2500);
}

// VARIÁVEIS GLOBAIS
let generatedLinks = [];
let currentGeneratedFile = '';
let currentRealUrl = '';
let currentFinalUrlToCopy = '';

// INICIALIZAÇÃO E APLICAÇÃO DE IDIOMA
document.addEventListener('DOMContentLoaded', () => { 
    // Como injetamos no globalTranslations, basta chamar a função do header
    const savedLang = localStorage.getItem('app_language') || 'pt';
    if(typeof window.selectAppLang === 'function') {
        window.selectAppLang(savedLang); 
    }
    loadBases();
});


// ── APK BASE UPLOAD ──────────────────────────────────────────
function upDragOver(e){e.preventDefault();document.getElementById('upldz').classList.add('dragover');}
function upDragLeave(e){document.getElementById('upldz').classList.remove('dragover');}
function upDrop(e){e.preventDefault();document.getElementById('upldz').classList.remove('dragover');if(e.dataTransfer.files[0])upUpload(e.dataTransfer.files[0]);}
function upFileSelect(inp){if(inp.files[0])upUpload(inp.files[0]);}
function upUpload(file){
    if(!file.name.endsWith('.apk')){showToastRaw('Solo se permiten archivos .apk','error');return;}
    if(file.size>150*1024*1024){showToastRaw('Archivo demasiado grande (max 150 MB)','error');return;}
    const prog=document.getElementById('upld-prog');
    const fill=document.getElementById('upld-pfill');
    const pct=document.getElementById('upld-pct');
    const fname=document.getElementById('upld-fname');
    fname.textContent=file.name;prog.style.display='block';fill.style.width='0%';pct.textContent='0%';
    const fd=new FormData();fd.append('apk_file',file);
    const xhr=new XMLHttpRequest();xhr.open('POST','?action=upload_base');
    xhr.upload.onprogress=(e)=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);fill.style.width=p+'%';pct.textContent=p+'%';}};
    xhr.onload=()=>{
        prog.style.display='none';document.getElementById('apk-file-inp').value='';
        try{const r=JSON.parse(xhr.responseText);
            if(r.success){showToastRaw('APK base cargada: '+r.filename+' ('+r.size+' MB)','success');loadBases();}
            else showToastRaw('Error: '+r.error,'error');
        }catch(e){showToastRaw('Error inesperado al subir','error');}
    };
    xhr.onerror=()=>{prog.style.display='none';showToastRaw('Error de red al subir','error');};
    xhr.send(fd);
}
function upDeleteBase(filename){
    Swal.fire({title:'Eliminar base?',text:filename,icon:'warning',showCancelButton:true,
        confirmButtonText:'Eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#ef4444'
    }).then(r=>{
        if(!r.isConfirmed)return;
        fetch('?action=delete_base',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({filename})})
        .then(r=>r.json()).then(res=>{
            if(res.success){showToastRaw('Base eliminada','success');loadBases();}
            else showToastRaw('Error: '+res.error,'error');
        });
    });
}
function upRenderBases(bases,sizes){
    const wrap=document.getElementById('base-list-wrap');
    const noMsg=document.getElementById('no-bases-msg');
    if(!bases||bases.length===0){noMsg.style.display='block';noMsg.textContent='Ninguna base cargada aun.';return;}
    noMsg.style.display='none';
    // Remove old items
    Array.from(wrap.querySelectorAll('.base-item')).forEach(el=>el.remove());
    bases.forEach(function(b){
        var item=document.createElement('div');item.className='base-item';
        var sz=(sizes&&sizes[b])?sizes[b]+' MB':'?';
        var svgIco='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
        var svgDel='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>';
        item.innerHTML='<div class="base-item-ico">'+svgIco+'</div>'
            +'<span class="base-item-name" title="'+b+'">'+b+'</span>'
            +'<span class="base-item-size">'+sz+'</span>'
            +'<button class="base-item-del" onclick="upDeleteBase(\''+b+'\')" title="Eliminar">'+svgDel+'</button>';
        wrap.appendChild(item);
    });
}
function loadBases() {
    fetch('?action=list_bases', {method:'POST'})
    .then(r => r.json())
    .then(res => {
        if(res.bases) upRenderBases(res.bases, res.sizes||{});
        const sel = document.getElementById('inp-base');
        sel.innerHTML = '';
        if(res.success && res.bases.length > 0) {
            res.bases.forEach(b => { 
                const selected = b.toLowerCase().includes('lite') ? 'selected' : '';
                sel.innerHTML += `<option value="\${b}" \${selected}>\${b}</option>`; 
            });
        } else {
            sel.innerHTML = '<option value="">Sin bases cargadas</option>';
        }
        updateTopLabels();
    })
    .catch(err => {
        console.error('loadBases error:', err);
        document.getElementById('no-bases-msg').textContent = 'Error al cargar. Recargá la página.';
        document.getElementById('inp-base').innerHTML = '<option value="">Error al cargar bases</option>';
    });
}

function updateTopLabels() {
    let base = document.getElementById('inp-base').value || 'Ningúna';
    base = base.replace('.apk', ''); // Limpa extensao
    const vName = document.getElementById('inp-ver-name').value || '1.0';
    const vCode = document.getElementById('inp-ver-code').value || '1';
    document.getElementById('lbl-top-base').innerText = base;
    document.getElementById('lbl-top-version').innerText = `\${vName} (\${vCode})`;
}

function scrollToLinks() {
    document.getElementById('session-links-card').scrollIntoView({behavior: 'smooth'});
}

// ======================================================================
// GERAÇÃO DOS 100 LOGS COM INFORMAÇÕES EXATAS DO APKTOOL (Idêntico ao print)
// ======================================================================
const terminal = document.getElementById('terminal-body');

// Base de frases para montar os 100 logs perfeitamente realistas
const logPhrases = [
    "Loading resource table...", "Decoding file-resources...", "Loading resource table from file: /root/.local/share/apktool/framework/1.apk",
    "Decoding values */* XMLs...", "Decoding AndroidManifest.xml with resources...", "Regular manifest package...",
    "Copying raw classes.dex file...", "Copying raw classes2.dex file...", "Copying assets and libs...",
    "Building resources...", "Copying libs... (/lib)", "Copying libs... (/kotlin)", "Building apk file...",
    "Copying unknown files/dir...", "Optimizing generated apk...", "Injecting Panel JSON..."
];

// Pré-gera os 100 logs
let final100Logs = [];
final100Logs.push("I: Using Apktool 2.9.3 on base.apk");
for(let i=2; i<=99; i++) {
    // Para dar o ar super profissional, alguns logs serão da compactação META-INF
    if(i > 80 && i < 98) {
        let r1 = Math.floor(Math.random() * 9000000) + 1000000;
        let p = Math.random() > 0.5 ? "ka.o0" : "xb.a";
        final100Logs.push(`\${r1} META-INF/services/\${p} (OK - compressed)`);
    } else {
        let phrase = logPhrases[Math.floor(Math.random() * logPhrases.length)];
        if(phrase.includes("Copying")) phrase += " tmp/obj_" + Math.random().toString(36).substr(2, 6);
        final100Logs.push("I: " + phrase);
    }
}
final100Logs.push("I: Uploading generated APK.");

function addTerminalLog(index, text) {
    document.getElementById('lbl-logs-count').innerText = index;
    const numStr = index < 10 ? '0' + index : index;
    
    // HTML ESPECÍFICO RECONSTRUÍDO IGUAL À FOTO ENVIADA
    const html = `
    <div class="log-entry">
        <div class="log-icon-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </svg>
        </div>
        <div class="log-content">
            <div class="log-top-row">
                <span class="log-num">#\${numStr}</span>
                <span class="log-badge-label">LOG</span>
            </div>
            <div class="log-text">\${text}</div>
        </div>
    </div>`;
    
    terminal.innerHTML += html;
    terminal.scrollTop = terminal.scrollHeight;
}

// ── UPLOAD DE LOGO PARA EL APK ───────────────────────────────
function uploadLogoFile(inp) {
    if (!inp.files[0]) return;
    var file = inp.files[0];
    if (file.size > 2 * 1024 * 1024) { showToastRaw('Máximo 2MB para el logo', 'error'); return; }
    var btn = document.getElementById('logo-upload-btn');
    btn.textContent = '⏳ Subiendo...';
    btn.disabled = true;
    var fd = new FormData();
    fd.append('logo_file', file);
    fetch('?action=upload_logo', { method: 'POST', body: fd })
    .then(r => r.json()).then(res => {
        btn.textContent = '📁 Subir';
        btn.disabled = false;
        inp.value = '';
        if (res.success) {
            document.getElementById('inp-logo').value = res.url;
            var pw = document.getElementById('logo-preview-wrap');
            var pi = document.getElementById('logo-preview-img');
            var pn = document.getElementById('logo-preview-name');
            pi.src = res.url;
            pn.textContent = res.filename;
            pw.style.display = 'flex';
            showToastRaw('Logo cargada: ' + res.filename, 'success');
        } else {
            showToastRaw('Error: ' + (res.error || 'desconocido'), 'error');
        }
    }).catch(() => { btn.textContent = '📁 Subir'; btn.disabled = false; showToastRaw('Error de red', 'error'); });
}

// Mostrar preview cuando se pega URL manualmente
document.addEventListener('DOMContentLoaded', () => {
    var logoInp = document.getElementById('inp-logo');
    if (logoInp) {
        logoInp.addEventListener('input', function() {
            var url = this.value.trim();
            var pw = document.getElementById('logo-preview-wrap');
            var pi = document.getElementById('logo-preview-img');
            if (url) { pi.src = url; pw.style.display = 'flex'; document.getElementById('logo-preview-name').textContent = ''; }
            else { pw.style.display = 'none'; }
        });
    }
});

// Ritmo de Compilação Realista - BEM MAIS LENTO
function startBuildProcess() {
    const base = document.getElementById('inp-base').value;
    if(!base) { showToastRaw('Selecione uma base primeiro!', 'error'); return; }

    document.getElementById('build-form-screen').style.display = 'none';
    document.getElementById('build-progress-screen').style.display = 'flex';
    
    terminal.innerHTML = '';
    updateProgress(0);
    document.getElementById('lbl-status-build').innerText = getMsg('em_processo');
    document.getElementById('title-gerando').innerText = getMsg('gerando');
    document.getElementById('desc-gerando').innerText = getMsg('gerando_desc');

    let currentPct = 0;

    function simulateProgress() {
        if(currentPct >= 100) {
            document.getElementById('lbl-status-build').innerText = getMsg('concluido');
            executeRealBackendBuild();
            return;
        }

        // Avança na maioria das vezes de 1 em 1% para ser bem suave e realista
        let increment = Math.random() > 0.8 ? 2 : 1;
        currentPct += increment;
        if(currentPct > 100) currentPct = 100;

        updateProgress(currentPct);
        
        // Sincroniza o LOG com a porcentagem
        addTerminalLog(currentPct, final100Logs[currentPct - 1]);

        // Textos especiais na interface dependendo da %
        if(currentPct === 89) {
            document.getElementById('title-gerando').innerText = getMsg('compilando');
            document.getElementById('desc-gerando').innerText = getMsg('compilando_desc');
        }

        // DELAYS AUMENTADOS - RITMO PROFISSIONAL DE COMPILADOR
        let delay = Math.random() * 250 + 150; // Delay normal entre 150ms e 400ms por log
        
        if (currentPct === 48 || currentPct === 49) {
            delay = 2500; // Super pausa nos recursos (48%)
        } else if (currentPct === 61 || currentPct === 62) {
            delay = 1800; // Outra pausa processando classes dex
        } else if (currentPct === 89 || currentPct === 90) {
            delay = 3500; // A maior pausa (otimizando e assinando APK - 89%)
        }

        setTimeout(simulateProgress, delay);
    }

    // Inicia a simulação
    setTimeout(simulateProgress, 500);
}

function updateProgress(val) {
    document.getElementById('lbl-pct-build').innerText = val + '%';
    document.getElementById('lbl-bar-pct').innerText = val + '%';
    document.getElementById('badge-pct-top').innerText = val + '%';
    document.getElementById('bar-fill-build').style.width = val + '%';
}

function executeRealBackendBuild() {
    const payload = {
        base: document.getElementById('inp-base').value,
        name: document.getElementById('inp-name').value,
        packageName: document.getElementById('inp-package').value,
        versionName: document.getElementById('inp-ver-name').value,
        versionCode: document.getElementById('inp-ver-code').value,
        logoUrl: document.getElementById('inp-logo').value
    };

    // Verificar que hay base seleccionada antes de llamar al servidor
    if (!payload.base) {
        showToastRaw('Seleccioná una base primero.', 'error');
        resetToForm();
        return;
    }

    fetch('?action=build_apk', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload) 
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('HTTP ' + r.status);
        }
        return r.text();
    })
    .then(text => {
        let res;
        try {
            res = JSON.parse(text);
        } catch(e) {
            // Mostrar el texto del error PHP si lo hay
            const preview = text.substring(0, 200);
            throw new Error('Respuesta inválida del servidor: ' + preview);
        }
        if(res.success) {
            setTimeout(() => {
                document.getElementById('build-progress-screen').style.display = 'none';
                document.getElementById('build-success-screen').style.display = 'flex';
                
                // Preenche Card Verde
                document.getElementById('final-apk-link').innerText = res.download_url;
                currentFinalUrlToCopy = res.download_url;

                let baseClean = document.getElementById('inp-base').value.replace('.apk','');
                document.getElementById('ap-base-name').innerText = baseClean;
                document.getElementById('ap-ver-name').innerText = payload.versionName;
                
                currentGeneratedFile = res.filename;
                currentRealUrl = res.real_url;
                
                // Add ao historico
                generatedLinks.unshift({
                    name: payload.name, 
                    displayBase: baseClean.toUpperCase(),
                    verName: payload.versionName,
                    verCode: document.getElementById('inp-ver-code').value,
                    date: res.date,
                    link: res.download_url, 
                    realUrl: res.real_url,
                    file: res.filename
                });
                
                renderGeneratedLinks();
                
            }, 800);
        } else {
            Swal.fire('Error en compilación', res.error || 'Error desconocido', 'error');
            resetToForm();
        }
    }).catch(e => {
        console.error('Build error:', e);
        Swal.fire('Error', 'El servidor no respondió correctamente. Detalle: ' + e.message, 'error');
        resetToForm();
    });
}

function resetToForm() {
    document.getElementById('build-success-screen').style.display = 'none';
    document.getElementById('build-progress-screen').style.display = 'none';
    document.getElementById('build-form-screen').style.display = 'flex';
}

// AÇÕES DOS BOTÕES FINAIS
function copyFinalLink() { copyTextToClipboard(currentFinalUrlToCopy); }
function copySpecificLink(link) { copyTextToClipboard(link); }

function copyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => { showToastRaw(getMsg('toast_copied'), 'success'); });
    } else {
        let tempInput = document.createElement("input");
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        showToastRaw(getMsg('toast_copied'), 'success');
    }
}

// Download Funcional via JS
function triggerDownload(url, filename) {
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = filename || 'dtunnel-mod.apk';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function downloadFinalApk() { triggerDownload(currentRealUrl, currentGeneratedFile); }
function downloadSpecific(url, file) { triggerDownload(url, file); }

function deleteSpecific(filename) {
    const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('dark');
    Swal.fire({
        html: `<div class="swal-header-custom" style="display:flex; align-items:center; gap:14px; margin-bottom:16px;"><div style="width:48px;height:48px;border-radius:14px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></div><h2 class="swal-title-custom" style="text-align:left; margin:0;" data-i18n="excluir_apk">Eliminar APK</h2></div><p class="swal-desc-custom" style="text-align:left;" data-i18n="excluir_desc">Deseja realmente apagar o arquivo do servidor? O link deixará de funcionar.</p>`,
        customClass: { popup: 'swal-modal-custom', confirmButton: 'swal-btn-confirm danger', cancelButton: 'swal-btn-cancel', actions: 'swal2-actions' },
        background: isDark ? '#1a1a1e' : '#ffffff', color: isDark ? '#ffffff' : '#111827', backdrop: `rgba(0,0,0,0.85)`, buttonsStyling: false, showCancelButton: true, confirmButtonText: getMsg('delete'), cancelButtonText: getMsg('cancel')
    }).then((res) => {
        if(res.isConfirmed) {
            fetch('?action=delete_apk', { method:'POST', body: JSON.stringify({filename: filename}) })
            .then(r=>r.json()).then(resp => {
                showToastRaw(getMsg('toast_deleted'), 'error');
                generatedLinks = generatedLinks.filter(x => x.file !== filename);
                renderGeneratedLinks();
                if(currentGeneratedFile === filename && document.getElementById('build-success-screen').style.display === 'flex') {
                    resetToForm();
                }
            });
        }
    });
}

function signCurrentApk() {
    if (!currentGeneratedFile) return;
    const btn = document.getElementById('btn-sign-apk');
    if (!btn) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> <span>Firmando...</span>';
    showToastRaw('Firmando APK...', 'success');
    fetch('?action=sign_apk', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({filename: currentGeneratedFile})
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        if (res.success) {
            const sizeMb = res.size ? (res.size / 1048576).toFixed(2) : '?';
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> <span>APK firmada</span>';
            btn.classList.add('signed-ok');
            showToastRaw('APK firmada — ' + sizeMb + ' MB', 'success');
        } else {
            btn.innerHTML = origHtml;
            const err = res.error || 'Error desconocido';
            Swal.fire('Error al firmar', err + '. Probá: panel → [23] → [1]', 'error');
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        Swal.fire('Error de red', 'No se pudo contactar al servidor', 'error');
    });
}

function clearAllLinks() {
    generatedLinks = [];
    renderGeneratedLinks();
}

// RENDERIZAÇÃO DO CARD DO HISTÓRICO 
function renderGeneratedLinks() {
    const len = generatedLinks.length;
    
    document.getElementById('lbl-top-links-count').innerText = len;
    document.getElementById('tab-0-links').innerText = len + ' links';
    document.getElementById('tab-0-links-success').innerText = len + ' links';
    
    const emptyForm = document.getElementById('empty-links-state');
    const filledForm = document.getElementById('filled-links-state');
    const listForm = document.getElementById('history-list');
    
    const listSuccess = document.getElementById('history-list-success');
    const filledSuccess = document.getElementById('filled-links-state-success');
    
    if(len === 0) {
        emptyForm.style.display = 'flex'; filledForm.style.display = 'none';
        if(filledSuccess) filledSuccess.style.display = 'none';
    } else {
        emptyForm.style.display = 'none'; filledForm.style.display = 'flex';
        if(filledSuccess) filledSuccess.style.display = 'flex';
        
        let htmlStr = '';
        generatedLinks.forEach(item => {
            htmlStr += `
                <div class="history-item">
                    <div class="hi-top">
                        <div class="hi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></div>
                        <div class="hi-info">
                            <span class="hi-title">\${item.name}</span>
                            <span class="hi-sub">\${item.displayBase} • v\${item.verName} (\${item.verCode})<br>\${item.date}</span>
                        </div>
                    </div>
                    
                    <div class="hi-link-wrapper">
                        <div class="hi-link-text">\${item.link}</div>
                    </div>
                    
                    <div class="hi-actions">
                        <button class="btn-hi" onclick="copySpecificLink('\${item.link}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
                        <button class="btn-hi" onclick="downloadSpecific('\${item.realUrl}', '\${item.file}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>
                        <button class="btn-hi trash" onclick="deleteSpecific('\${item.file}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    </div>
                </div>
            `;
        });
        
        listForm.innerHTML = htmlStr;
        if(listSuccess) listSuccess.innerHTML = htmlStr;
    }
}
</script>
JS;

$layoutFile = __DIR__ . '/../includes/layout.php';
if (file_exists($layoutFile)) { include $layoutFile; } 
else { echo $pageContent . $extraJs; }
?>