<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/QRGenerator.php';
require_once __DIR__ . '/../src/QRRepository.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

define('STORAGE_DIR', __DIR__ . '/../storage/qr_codes');
define('BASE_URL',
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/qrapi/public'
);
define('QR_URL_BASE', BASE_URL . '/storage/qr_codes/');

$base = '/qrapi/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri  = '/' . trim(str_replace($base, '', $path), '/');
$method = $_SERVER['REQUEST_METHOD'];

$body = [];
$raw  = file_get_contents('php://input');
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        Validator::error(415, 'El cuerpo de la petición debe ser JSON válido.');
    }
    $body = $decoded ?? [];
}
$params = array_merge($_GET, $body);

try {

    if ($method === 'POST' && $uri === '/api/qr') {
        // Endpoint principal — delega según "type"
        $tipo = strtolower(trim($params['type'] ?? ($params['tipo'] ?? '')));
        match ($tipo) {
            'text','texto'           => handleTexto($params),
            'url'                    => handleUrl($params),
            'wifi'                   => handleWifi($params),
            'geo','geolocalizacion'  => handleGeo($params),
            default                  => Validator::error(400, "Tipo inválido. Opciones: text, url, wifi, geo.")
        };

    } elseif ($method === 'POST' && $uri === '/api/qr/texto') {
        handleTexto($params);

    } elseif ($method === 'POST' && $uri === '/api/qr/url') {
        handleUrl($params);

    } elseif ($method === 'POST' && $uri === '/api/qr/wifi') {
        handleWifi($params);

    } elseif ($method === 'POST' && $uri === '/api/qr/geo') {
        handleGeo($params);

    } elseif ($method === 'GET' && $uri === '/api/qr') {
        handleListar($params);

    } elseif ($method === 'GET' && preg_match('#^/api/qr/(\d+)/download$#', $uri, $m)) {
        handleDescargar((int)$m[1]);

    } elseif ($method === 'GET' && preg_match('#^/api/qr/(\d+)$#', $uri, $m)) {
        handleObtener((int)$m[1]);

    } else {
        Validator::error(404, "Ruta no encontrada: {$method} {$uri}");
    }

} catch (PDOException $e) {
    Validator::error(500, 'Error de base de datos: ' . $e->getMessage());
} catch (Throwable $e) {
    Validator::error(500, 'Error interno: ' . $e->getMessage());
}


function handleTexto(array $p): void {
    $content          = Validator::texto($p['texto'] ?? ($p['content'] ?? ''));
    $size             = Validator::tamano($p['size'] ?? ($p['tamano'] ?? 300));
    $error_correction = Validator::correccion($p['error_correction'] ?? ($p['correccion'] ?? 'M'));

    $gen      = new QRGenerator(STORAGE_DIR);
    $file     = $gen->generarTexto($content, $size, $error_correction);

    $repo = new QRRepository();
    $id   = $repo->guardar('text', $content, $size, $error_correction, $file);

    responder(201, respuesta($id, $file, 'text', $size, $error_correction));
}

function handleUrl(array $p): void {
    $content          = Validator::url($p['url'] ?? ($p['content'] ?? ''));
    $size             = Validator::tamano($p['size'] ?? ($p['tamano'] ?? 300));
    $error_correction = Validator::correccion($p['error_correction'] ?? ($p['correccion'] ?? 'M'));

    $gen  = new QRGenerator(STORAGE_DIR);
    $file = $gen->generarURL($content, $size, $error_correction);

    $repo = new QRRepository();
    $id   = $repo->guardar('url', $content, $size, $error_correction, $file);

    responder(201, respuesta($id, $file, 'url', $size, $error_correction));
}

function handleWifi(array $p): void {
    $wifi             = Validator::wifi($p);
    $size             = Validator::tamano($p['size'] ?? ($p['tamano'] ?? 300));
    $error_correction = Validator::correccion($p['error_correction'] ?? ($p['correccion'] ?? 'M'));

    $content = "WIFI:T:{$wifi['encriptacion']};S:{$wifi['ssid']};P:{$wifi['password']};;";

    $gen  = new QRGenerator(STORAGE_DIR);
    $file = $gen->generarWifi($wifi['ssid'], $wifi['password'], $wifi['encriptacion'], $size, $error_correction);

    $repo = new QRRepository();
    $id   = $repo->guardar('wifi', $content, $size, $error_correction, $file);

    responder(201, respuesta($id, $file, 'wifi', $size, $error_correction, [
        'ssid'        => $wifi['ssid'],
        'encriptacion'=> $wifi['encriptacion'],
    ]));
}

function handleGeo(array $p): void {
    $coords           = Validator::geo($p['latitud'] ?? ($p['lat'] ?? null), $p['longitud'] ?? ($p['lng'] ?? null));
    $size             = Validator::tamano($p['size'] ?? ($p['tamano'] ?? 300));
    $error_correction = Validator::correccion($p['error_correction'] ?? ($p['correccion'] ?? 'M'));

    $content = "geo:{$coords['latitud']},{$coords['longitud']}";

    $gen  = new QRGenerator(STORAGE_DIR);
    $file = $gen->generarGeo($coords['latitud'], $coords['longitud'], $size, $error_correction);

    $repo = new QRRepository();
    $id   = $repo->guardar('geo', $content, $size, $error_correction, $file);

    responder(201, respuesta($id, $file, 'geo', $size, $error_correction, $coords));
}

function handleObtener(int $id): void {
    $repo = new QRRepository();
    $qr   = $repo->buscarPorId($id);
    if (!$qr) Validator::error(404, "No existe ningún QR con ese ID.");

    $qr['url_imagen']   = QR_URL_BASE . basename($qr['file_path']);
    $qr['url_descarga'] = BASE_URL . '/api/qr/' . $id . '/download';
    $qr['total_scans']  = $repo->contarScans($id);
    responder(200, $qr);
}

function handleDescargar(int $id): void {
    $repo = new QRRepository();
    $qr   = $repo->buscarPorId($id);
    if (!$qr) Validator::error(404, "No existe ningún QR con ese ID.");

    $filepath = STORAGE_DIR . '/' . basename($qr['file_path']);
    if (!file_exists($filepath)) Validator::error(404, "El archivo de imagen no existe en el servidor.");

    // Registrar el escaneo
    $repo->registrarScan($id, clientIP(), $_SERVER['HTTP_USER_AGENT'] ?? '');

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="qr_' . $id . '.png"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache');
    readfile($filepath);
    exit;
}

function handleListar(array $p): void {
    $type  = $p['type']  ?? ($p['tipo'] ?? null);
    $limit = min((int)($p['limit'] ?? ($p['limite'] ?? 20)), 100);

    $repo  = new QRRepository();
    $lista = $repo->listar($type, $limit);

    foreach ($lista as &$qr) {
        $qr['url_imagen']   = QR_URL_BASE . basename($qr['file_path']);
        $qr['url_descarga'] = BASE_URL . '/api/qr/' . $qr['id'] . '/download';
    }

    responder(200, ['total' => count($lista), 'registros' => $lista]);
}

function respuesta(int $id, string $archivo, string $tipo, int $size, string $ec, array $extra = []): array {
    return array_merge([
        'exito'            => true,
        'id'               => $id,
        'type'             => $tipo,
        'size'             => $size,
        'error_correction' => $ec,
        'url_imagen'       => QR_URL_BASE . $archivo,
        'url_descarga'     => BASE_URL . '/api/qr/' . $id . '/download',
        'expires_at'       => date('c', strtotime('+24 hours')),
    ], $extra);
}

function responder(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function clientIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}