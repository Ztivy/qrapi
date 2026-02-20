<?php

require_once __DIR__ . '/../config/database.php';

class QRRepository {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function guardar(
        string  $type,
        string  $content,
        int     $size,
        string  $error_correction,
        string  $file_path,
        ?int    $user_id = null
    ): int {
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->db->prepare("
            INSERT INTO qr_codes (user_id, type, content, size, error_correction, file_path, expires_at)
            VALUES (:user_id, :type, :content, :size, :ec, :file_path, :expires_at)
        ");
        $stmt->execute([
            ':user_id'    => $user_id,
            ':type'       => $type,
            ':content'    => $content,
            ':size'       => $size,
            ':ec'         => $error_correction,
            ':file_path'  => $file_path,
            ':expires_at' => $expires_at,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM qr_codes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listar(?string $type, int $limit): array {
        if ($type) {
            $stmt = $this->db->prepare("
                SELECT * FROM qr_codes WHERE type = :type
                ORDER BY created_at DESC LIMIT :lim
            ");
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM qr_codes ORDER BY created_at DESC LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function registrarScan(int $qr_id, string $ip, string $user_agent): void {
        $stmt = $this->db->prepare("
            INSERT INTO qr_scans (qr_id, ip_address, user_agent)
            VALUES (:qr_id, :ip, :ua)
        ");
        $stmt->execute([':qr_id' => $qr_id, ':ip' => $ip, ':ua' => $user_agent]);
    }

    public function contarScans(int $qr_id): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM qr_scans WHERE qr_id = :id");
        $stmt->execute([':id' => $qr_id]);
        return (int)$stmt->fetchColumn();
    }
}