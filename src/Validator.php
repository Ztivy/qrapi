<?php
// src/Validator.php

class Validator {

    public static function tamano(mixed $v): int {
        $n = (int)$v;
        if ($n < 100 || $n > 1000) {
            self::error(400, "El tamaño debe estar entre 100 y 1000 píxeles.");
        }
        return $n;
    }

    public static function correccion(mixed $v): string {
        $v = strtoupper(trim((string)$v));
        if (!in_array($v, ['L','M','Q','H'])) {
            self::error(400, "Nivel de corrección inválido. Valores permitidos: L, M, Q, H.");
        }
        return $v;
    }

    public static function texto(mixed $v): string {
        $v = trim((string)$v);
        if ($v === '') {
            self::error(400, "El campo 'texto' no puede estar vacío.");
        }
        if (strlen($v) > 2953) {
            self::error(413, "El contenido excede la capacidad máxima del código QR (2953 caracteres).");
        }
        return $v;
    }

    public static function url(mixed $v): string {
        $v = trim((string)$v);
        if (!filter_var($v, FILTER_VALIDATE_URL)) {
            self::error(400, "URL inválida. Incluye el protocolo: http:// o https://");
        }
        if (strlen($v) > 2953) {
            self::error(413, "La URL excede la capacidad máxima del código QR.");
        }
        return $v;
    }

    public static function wifi(array $data): array {
        $ssid = trim($data['ssid'] ?? '');
        if ($ssid === '') {
            self::error(400, "El campo 'ssid' es obligatorio para QR WiFi.");
        }
        if (strlen($ssid) > 32) {
            self::error(400, "El SSID no puede superar 32 caracteres.");
        }

        $enc = strtoupper(trim($data['encriptacion'] ?? 'WPA2'));
        if (!in_array($enc, ['WPA','WPA2','WEP','NOPASS'])) {
            self::error(400, "Tipo de encriptación inválido. Valores: WPA, WPA2, WEP, nopass.");
        }

        $password = trim($data['password'] ?? '');
        if ($enc !== 'NOPASS' && $password === '') {
            self::error(400, "Se requiere contraseña para el tipo de encriptación '{$enc}'.");
        }
        if (strlen($password) > 63) {
            self::error(400, "La contraseña WiFi no puede superar 63 caracteres.");
        }

        return [
            'ssid'        => $ssid,
            'password'    => $password,
            'encriptacion'=> $enc,
        ];
    }

    public static function geo(mixed $lat, mixed $lng): array {
        if (!is_numeric($lat) || (float)$lat < -90 || (float)$lat > 90) {
            self::error(400, "Latitud inválida. Debe ser un número entre -90 y 90.");
        }
        if (!is_numeric($lng) || (float)$lng < -180 || (float)$lng > 180) {
            self::error(400, "Longitud inválida. Debe ser un número entre -180 y 180.");
        }
        return [
            'latitud'  => (float)$lat,
            'longitud' => (float)$lng,
        ];
    }

    public static function error(int $code, string $mensaje): never {
        http_response_code($code);
        echo json_encode([
            'error'   => true,
            'codigo'  => $code,
            'mensaje' => $mensaje,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}