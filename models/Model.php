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

    protected function uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected function normalizeSearchText($value): string {
        $text = trim((string)$value);
        $text = strtr($text, [
            'Ç' => 'c', 'ç' => 'c',
            'Ğ' => 'g', 'ğ' => 'g',
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ö' => 'o', 'ö' => 'o',
            'Ş' => 's', 'ş' => 's',
            'Ü' => 'u', 'ü' => 'u',
            'Â' => 'a', 'â' => 'a',
            'Î' => 'i', 'î' => 'i',
            'Û' => 'u', 'û' => 'u',
        ]);
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    protected function searchMatches(array $fields, string $search): bool {
        $needle = $this->normalizeSearchText($search);
        if ($needle === '') {
            return true;
        }

        $haystack = $this->normalizeSearchText(implode(' ', array_map(fn($v) => (string)($v ?? ''), $fields)));
        return strpos($haystack, $needle) !== false;
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
