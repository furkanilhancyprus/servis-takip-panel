<?php
require_once __DIR__ . '/Model.php';

class StandartIslem extends Model {

    public function getAll(): array {
        $islemler = $this->db->fetchAll(
            "SELECT * FROM standart_islemler WHERE firma_id=? AND deleted_at IS NULL ORDER BY islem_adi ASC",
            [$this->firmaId]
        );
        foreach ($islemler as &$islem) {
            $islem['parcalar'] = $this->db->fetchAll(
                "SELECT sip.id, sip.parca_id, sip.miktar, p.parca_adi, p.marka, p.birim_fiyat
                 FROM standart_islem_parcalar sip
                 JOIN parcalar p ON p.id = sip.parca_id AND p.deleted_at IS NULL
                 WHERE sip.islem_id = ? AND sip.deleted_at IS NULL
                 ORDER BY p.parca_adi ASC",
                [$islem['id']]
            );
        }
        return $islemler;
    }

    public function create(string $islemAdi, float $fiyat = 0): int {
        return $this->db->execute(
            "INSERT INTO standart_islemler (firma_id, islem_adi, varsayilan_fiyat) VALUES (?,?,?)",
            [$this->firmaId, $islemAdi, $fiyat]
        );
    }

    public function update(int $id, string $islemAdi, float $fiyat): bool {
        $this->requireIslem($id);
        $this->db->query(
            "UPDATE standart_islemler SET islem_adi=?, varsayilan_fiyat=?, synced_at=NULL WHERE id=? AND firma_id=?",
            [$islemAdi, $fiyat, $id, $this->firmaId]
        );
        return true;
    }

    public function setParcalar(int $islemId, array $parcalar): void {
        $this->requireIslem($islemId);
        $this->db->query("UPDATE standart_islem_parcalar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE islem_id=?", [$islemId]);
        foreach ($parcalar as $p) {
            $parcaId = (int)($p['parca_id'] ?? 0);
            $miktar  = max(1, (int)($p['miktar'] ?? 1));
            if ($parcaId > 0) {
                $this->requireParca($parcaId);
                $this->db->execute(
                    "INSERT INTO standart_islem_parcalar (islem_id, parca_id, miktar) VALUES (?,?,?)",
                    [$islemId, $parcaId, $miktar]
                );
            }
        }
    }

    public function delete(int $id): void {
        $this->requireIslem($id);
        $this->db->query("UPDATE standart_islemler SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=?", [$id, $this->firmaId]);
    }

    private function requireIslem(int $id): void {
        $ok = $this->db->fetchColumn(
            "SELECT id FROM standart_islemler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$ok) {
            throw new InvalidArgumentException('Standart islem bulunamadi veya bu firmaya ait degil.');
        }
    }
}
