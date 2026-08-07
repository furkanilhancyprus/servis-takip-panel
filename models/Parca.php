<?php
require_once __DIR__ . '/Model.php';

class Parca extends Model {
    public function getAll(): array {
        return $this->db->fetchAll("
            SELECT * FROM parcalar
            WHERE firma_id=? AND deleted_at IS NULL
            ORDER BY is_cihaz ASC,
                     CASE WHEN sira_no IS NULL OR sira_no=0 THEN 1 ELSE 0 END ASC,
                     sira_no ASC,
                     parca_adi ASC
        ", [$this->firmaId]);
    }

    public function getById(int $id) {
        return $this->db->fetchOne("SELECT * FROM parcalar WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$id, $this->firmaId]);
    }

    public function create(array $data): int {
        $id = $this->db->execute("
            INSERT INTO parcalar (firma_id, parca_adi, marka, birim_fiyat, maliyet_usd, stok_miktari, kritik_stok_seviyesi, tedarikci, is_cihaz, sira_no, uuid)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $data['parca_adi'], $data['marka'] ?? null,
            $data['birim_fiyat'] ?? 0, $data['maliyet_usd'] ?? 0, $data['stok_miktari'] ?? 0,
            $data['kritik_stok_seviyesi'] ?? 5, $data['tedarikci'] ?? null,
            isset($data['is_cihaz']) ? (int)(bool)$data['is_cihaz'] : 0,
            $this->nextOrderNo(isset($data['is_cihaz']) ? (int)(bool)$data['is_cihaz'] : 0),
            $this->uuid(),
        ]);

        if (!empty($data['is_cihaz'])) {
            $this->upsertCihazKatalogu($id, $data);
        }

        return $id;
    }

    public function update(int $id, array $data): bool {
        if (isset($data['stok_artis'])) {
            $this->db->query(
                "UPDATE parcalar SET stok_miktari=stok_miktari+?, updated_at=?, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL",
                [(int)$data['stok_artis'], $this->now(), $id, $this->firmaId]
            );
        } else {
            $this->db->query("
                UPDATE parcalar SET parca_adi=?, marka=?, birim_fiyat=?, maliyet_usd=?, stok_miktari=?,
                    kritik_stok_seviyesi=?, tedarikci=?, is_cihaz=?, updated_at=?, synced_at=NULL
                WHERE id=? AND firma_id=? AND deleted_at IS NULL
            ", [
                $data['parca_adi'], $data['marka'] ?? null,
                $data['birim_fiyat'] ?? 0, $data['maliyet_usd'] ?? 0, $data['stok_miktari'] ?? 0,
                $data['kritik_stok_seviyesi'] ?? 5, $data['tedarikci'] ?? null,
                isset($data['is_cihaz']) ? (int)(bool)$data['is_cihaz'] : 0,
                $this->now(), $id, $this->firmaId,
            ]);

            if (!empty($data['is_cihaz'])) {
                $this->upsertCihazKatalogu($id, $data);
            } else {
                $this->db->query(
                    "UPDATE cihazlar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE parca_id=? AND firma_id=? AND deleted_at IS NULL",
                    [$id, $this->firmaId]
                );
            }
        }
        return true;
    }

    public function updateOrder(array $ids): void {
        $order = 1;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $this->db->query(
                "UPDATE parcalar SET sira_no=?, updated_at=?, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL",
                [$order++, $this->now(), $id, $this->firmaId]
            );
        }
    }

    public function delete(int $id): void {
        $this->db->query("UPDATE parcalar SET deleted_at=?, updated_at=?, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$this->now(), $this->now(), $id, $this->firmaId]);
        $this->db->query(
            "UPDATE cihazlar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE parca_id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
    }

    private function upsertCihazKatalogu(int $parcaId, array $data): void {
        $existing = $this->db->fetchOne(
            "SELECT id FROM cihazlar WHERE parca_id=? AND firma_id=? AND deleted_at IS NULL",
            [$parcaId, $this->firmaId]
        );

        if ($existing) {
            $this->db->query("
                UPDATE cihazlar SET cihaz_adi=?, marka=?, varsayilan_fiyat=?, aciklama=?, synced_at=NULL
                WHERE id=? AND firma_id=? AND deleted_at IS NULL
            ", [
                $data['parca_adi'],
                $data['marka'] ?? null,
                $data['birim_fiyat'] ?? 0,
                $data['tedarikci'] ?? null,
                $existing['id'],
                $this->firmaId,
            ]);
            return;
        }

        $this->db->execute("
            INSERT INTO cihazlar (firma_id, parca_id, cihaz_adi, marka, varsayilan_fiyat, aciklama, uuid)
            VALUES (?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $parcaId,
            $data['parca_adi'],
            $data['marka'] ?? null,
            $data['birim_fiyat'] ?? 0,
            $data['tedarikci'] ?? null,
            $this->uuid(),
        ]);
    }

    private function nextOrderNo(int $isCihaz): int {
        return (int)$this->db->fetchColumn(
            "SELECT COALESCE(MAX(sira_no),0)+1 FROM parcalar WHERE firma_id=? AND deleted_at IS NULL AND is_cihaz=?",
            [$this->firmaId, $isCihaz]
        );
    }

    public function getKritikStoklar(): array {
        return $this->db->fetchAll(
            "SELECT * FROM parcalar WHERE firma_id=? AND deleted_at IS NULL AND stok_miktari<=kritik_stok_seviyesi ORDER BY stok_miktari ASC",
            [$this->firmaId]
        );
    }

    public function getTotalValue(): float {
        return (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(stok_miktari*birim_fiyat),0) FROM parcalar WHERE firma_id=? AND deleted_at IS NULL",
            [$this->firmaId]
        );
    }
}
