<?php
require_once __DIR__ . '/Model.php';

class Ayarlar extends Model {
    public function getAll(): array {
        $rows = $this->db->fetchAll("SELECT anahtar, deger FROM ayarlar WHERE firma_id=?", [$this->firmaId]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['anahtar']] = $row['deger'];
        }
        return $result;
    }

    public function get(string $anahtar, $default = null) {
        $val = $this->db->fetchColumn("SELECT deger FROM ayarlar WHERE firma_id=? AND anahtar=?", [$this->firmaId, $anahtar]);
        return $val !== false ? $val : $default;
    }

    public function set(string $anahtar, $deger): void {
        $this->db->query("
            INSERT INTO ayarlar (firma_id, anahtar, deger) VALUES (?,?,?)
            ON CONFLICT(firma_id, anahtar) DO UPDATE SET deger=excluded.deger, updated_at=CURRENT_TIMESTAMP
        ", [$this->firmaId, $anahtar, $deger]);
    }

    public function setMultiple(array $data): void {
        foreach ($data as $anahtar => $deger) {
            $this->set($anahtar, $deger);
        }
    }
}
