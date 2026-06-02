<?php
require_once __DIR__ . '/../config/database.php';

abstract class Model {
    protected Database $db;
    protected string $table = '';
    protected int $firmaId = 0;

    public function __construct() {
        $this->db = Database::getInstance();

        // Oturum açıksa firma_id'yi al
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->firmaId = (int) ($_SESSION['firma_id'] ?? 0);
    }

    protected function now(): string {
        return date('Y-m-d H:i:s');
    }

    protected function today(): string {
        return date('Y-m-d');
    }

    protected function requireMusteri(int $musteriId): void {
        $ok = $this->db->fetchColumn(
            "SELECT id FROM musteriler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$musteriId, $this->firmaId]
        );
        if (!$ok) {
            throw new InvalidArgumentException('Musteri bulunamadi veya bu firmaya ait degil.');
        }
    }

    protected function requireParca(int $parcaId): void {
        $ok = $this->db->fetchColumn(
            "SELECT id FROM parcalar WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$parcaId, $this->firmaId]
        );
        if (!$ok) {
            throw new InvalidArgumentException('Stok kaydi bulunamadi veya bu firmaya ait degil.');
        }
    }

    protected function requireCihaz(int $cihazId): void {
        $ok = $this->db->fetchColumn(
            "SELECT id FROM cihazlar WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$cihazId, $this->firmaId]
        );
        if (!$ok) {
            throw new InvalidArgumentException('Cihaz bulunamadi veya bu firmaya ait degil.');
        }
    }
}
