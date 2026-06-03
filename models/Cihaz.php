<?php
require_once __DIR__ . '/Model.php';

class Cihaz extends Model {

    public function getAll(): array {
        return $this->db->fetchAll(
            "SELECT c.*, p.stok_miktari, p.kritik_stok_seviyesi
             FROM cihazlar c
             LEFT JOIN parcalar p ON p.id=c.parca_id AND p.deleted_at IS NULL
             WHERE c.firma_id=? AND c.deleted_at IS NULL
             ORDER BY c.cihaz_adi ASC",
            [$this->firmaId]
        );
    }

    public function getById(int $id) {
        return $this->db->fetchOne(
            "SELECT c.*, p.stok_miktari, p.kritik_stok_seviyesi
             FROM cihazlar c
             LEFT JOIN parcalar p ON p.id=c.parca_id AND p.deleted_at IS NULL
             WHERE c.id=? AND c.firma_id=? AND c.deleted_at IS NULL",
            [$id, $this->firmaId]
        );
    }

    public function create(array $data): int {
        $parcaId = $this->db->execute("
            INSERT INTO parcalar (firma_id, parca_adi, marka, birim_fiyat, stok_miktari, kritik_stok_seviyesi, tedarikci, is_cihaz)
            VALUES (?,?,?,?,0,1,?,1)
        ", [
            $this->firmaId,
            $data['cihaz_adi'],
            $data['marka'] ?? null,
            $data['varsayilan_fiyat'] ?? 0,
            $data['aciklama'] ?? null,
        ]);

        return $this->db->execute("
            INSERT INTO cihazlar (firma_id, parca_id, cihaz_adi, marka, model, varsayilan_fiyat, aciklama)
            VALUES (?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $parcaId,
            $data['cihaz_adi'],
            $data['marka']            ?? null,
            $data['model']            ?? null,
            $data['varsayilan_fiyat'] ?? 0,
            $data['aciklama']         ?? null,
        ]);
    }

    public function update(int $id, array $data): bool {
        $existing = $this->getById($id);
        if (!$existing) return false;

        $this->db->query("
            UPDATE cihazlar SET cihaz_adi=?, marka=?, model=?, varsayilan_fiyat=?, aciklama=?, synced_at=NULL
            WHERE id=? AND firma_id=? AND deleted_at IS NULL
        ", [
            $data['cihaz_adi'],
            $data['marka']            ?? null,
            $data['model']            ?? null,
            $data['varsayilan_fiyat'] ?? 0,
            $data['aciklama']         ?? null,
            $id, $this->firmaId,
        ]);

        if (!empty($existing['parca_id'])) {
            $this->db->query("
                UPDATE parcalar SET parca_adi=?, marka=?, birim_fiyat=?, tedarikci=?, is_cihaz=1, updated_at=?, synced_at=NULL
                WHERE id=? AND firma_id=? AND deleted_at IS NULL
            ", [
                $data['cihaz_adi'],
                $data['marka'] ?? null,
                $data['varsayilan_fiyat'] ?? 0,
                $data['aciklama'] ?? null,
                $this->now(),
                $existing['parca_id'],
                $this->firmaId,
            ]);
        } else {
            $parcaId = $this->db->execute("
                INSERT INTO parcalar (firma_id, parca_adi, marka, birim_fiyat, stok_miktari, kritik_stok_seviyesi, tedarikci, is_cihaz)
                VALUES (?,?,?,?,0,1,?,1)
            ", [
                $this->firmaId,
                $data['cihaz_adi'],
                $data['marka'] ?? null,
                $data['varsayilan_fiyat'] ?? 0,
                $data['aciklama'] ?? null,
            ]);
            $this->db->query("UPDATE cihazlar SET parca_id=?, synced_at=NULL WHERE id=? AND firma_id=?", [$parcaId, $id, $this->firmaId]);
        }
        return true;
    }

    public function delete(int $id): void {
        $existing = $this->getById($id);
        $this->db->query(
            "UPDATE cihazlar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!empty($existing['parca_id'])) {
            $this->db->query(
                "UPDATE parcalar SET deleted_at=CURRENT_TIMESTAMP, updated_at=?, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL",
                [$this->now(), $existing['parca_id'], $this->firmaId]
            );
        }
    }

    // Müşteriye ait cihazlar
    public function getMusteriCihazlari(int $musteriId): array {
        return $this->db->fetchAll("
            SELECT mc.*, c.cihaz_adi, c.marka, c.model,
                   s.satis_tarihi, s.toplam_tutar AS satis_tutari
            FROM musteri_cihazlari mc
            LEFT JOIN cihazlar c  ON mc.cihaz_id = c.id AND c.deleted_at IS NULL
            LEFT JOIN satislar s  ON mc.satis_id  = s.id AND s.deleted_at IS NULL
            WHERE mc.musteri_id=? AND mc.firma_id=? AND mc.deleted_at IS NULL
            ORDER BY mc.created_at DESC
        ", [$musteriId, $this->firmaId]);
    }

    // Satışa cihaz bağla
    public function linkToSatis(int $musteriId, int $satisId, ?int $cihazId, array $data = []): int {
        return $this->db->execute("
            INSERT INTO musteri_cihazlari (firma_id, musteri_id, cihaz_id, satis_id, seri_no, kurulum_tarihi, notlar)
            VALUES (?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $musteriId,
            $cihazId,
            $satisId,
            $data['seri_no']         ?? null,
            $data['kurulum_tarihi']  ?? date('Y-m-d'),
            $data['notlar']          ?? null,
        ]);
    }
}
