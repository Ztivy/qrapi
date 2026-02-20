<?php

define('QR_CACHEABLE',    false);
define('QR_CACHE_DIR',    false);
define('QR_LOG_DIR',      false);
define('QR_FIND_BEST_MASK',     true);
define('QR_FIND_FROM_RANDOM',   2);
define('QR_DEFAULT_MASK',       2);
define('QR_PNG_MAXIMUM_SIZE',   1024);

require_once __DIR__ . '/../lib/phpqrcode/qrlib.php';

class QRGenerator {

    private string $storageDir;

    private array $niveles = [
        'L' => QR_ECLEVEL_L,
        'M' => QR_ECLEVEL_M,
        'Q' => QR_ECLEVEL_Q,
        'H' => QR_ECLEVEL_H,
    ];

    public function __construct(string $storageDir) {
        $this->storageDir = rtrim($storageDir, '/');
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function generarTexto(string $texto, int $tamano, string $correccion): string {
        return $this->generar($texto, $tamano, $correccion);
    }

    public function generarURL(string $url, int $tamano, string $correccion): string {
        return $this->generar($url, $tamano, $correccion);
    }

    public function generarWifi(string $ssid, string $password, string $encriptacion, int $tamano, string $correccion): string {
        $enc       = strtoupper($encriptacion) === 'NOPASS' ? 'nopass' : strtoupper($encriptacion);
        $contenido = "WIFI:T:{$enc};S:{$ssid};P:{$password};;";
        return $this->generar($contenido, $tamano, $correccion);
    }

    public function generarGeo(float $latitud, float $longitud, int $tamano, string $correccion): string {
        $contenido = "geo:{$latitud},{$longitud}";
        return $this->generar($contenido, $tamano, $correccion);
    }

    private function generar(string $contenido, int $tamano, string $correccion): string {
        $nivel     = $this->niveles[strtoupper($correccion)] ?? QR_ECLEVEL_M;
        // phpqrcode usa pixelSize (tamaño de cada módulo en px), no dimensión total
        // Con pixelSize=10 y margen=1 obtenemos aprox el tamaño solicitado
        $pixelSize = max(2, (int)round($tamano / 37));
        $margen    = 1;
        $filename  = uniqid('qr_', true) . '.png';
        $filepath  = $this->storageDir . '/' . $filename;

        // QRcode::png(data, outfile, level, size, margin)
        QRcode::png($contenido, $filepath, $nivel, $pixelSize, $margen);

        if (!file_exists($filepath)) {
            throw new RuntimeException("Error al generar el archivo QR.");
        }

        return $filename;
    }
}