<?php
require_once __DIR__ . '/Model.php';

class PeriyodikBakim extends Model {

    public function getByMusteriId(int $musteriId) {
        return $this->db->fetchOne("
            SELECT pb.*, m.ad, m.soyad, m.telefon, m.email
            FROM periyodik_bakimlar pb JOIN musteriler m ON pb.musteri_id=m.id
            WHERE pb.musteri_id=? AND m.firma_id=? AND m.deleted_at IS NULL AND pb.deleted_at IS NULL
        ", [$musteriId, $this->firmaId]);
    }

    public function update(int $musteriId, array $data): bool {
        // Müşteri bu firmaya ait mi kontrol et
        $ok = $this->db->fetchColumn("SELECT id FROM musteriler WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$musteriId, $this->firmaId]);
        if (!$ok) return false;

        $sonraki = null;
        if (!empty($data['son_bakim_tarihi']) && !empty($data['periyot_ay'])) {
            $sonraki = date('Y-m-d', strtotime($data['son_bakim_tarihi'] . " +" . (int)$data['periyot_ay'] . " months"));
        }

        $exists = $this->db->fetchColumn("SELECT id FROM periyodik_bakimlar WHERE musteri_id=? AND deleted_at IS NULL", [$musteriId]);

        if ($exists) {
            $this->db->query("
                UPDATE periyodik_bakimlar
                SET aktif=?, periyot_ay=?, son_bakim_tarihi=?, sonraki_bakim_tarihi=?, hatirlatma_gun=?, notlar=?, synced_at=NULL
                WHERE musteri_id=?
            ", [
                $data['aktif'] ?? 1, $data['periyot_ay'] ?? 6,
                $data['son_bakim_tarihi'] ?? null, $sonraki,
                $data['hatirlatma_gun'] ?? 7, $data['notlar'] ?? null,
                $musteriId,
            ]);
        } else {
            $this->db->execute("
                INSERT INTO periyodik_bakimlar (musteri_id, aktif, periyot_ay, son_bakim_tarihi, sonraki_bakim_tarihi, hatirlatma_gun, notlar)
                VALUES (?,?,?,?,?,?,?)
            ", [
                $musteriId, $data['aktif'] ?? 1, $data['periyot_ay'] ?? 6,
                $data['son_bakim_tarihi'] ?? null, $sonraki,
                $data['hatirlatma_gun'] ?? 7, $data['notlar'] ?? null,
            ]);
        }

        return true;
    }

    public function getGecikenler(): array {
        return $this->db->fetchAll("
            SELECT pb.*, m.ad, m.soyad, m.telefon,
                   CAST(julianday('now') - julianday(pb.sonraki_bakim_tarihi) AS INTEGER) AS gecikme_gun
            FROM periyodik_bakimlar pb JOIN musteriler m ON pb.musteri_id=m.id
            WHERE m.firma_id=? AND m.deleted_at IS NULL AND pb.deleted_at IS NULL AND pb.aktif=1
              AND pb.sonraki_bakim_tarihi < DATE('now') AND pb.sonraki_bakim_tarihi IS NOT NULL
            ORDER BY pb.sonraki_bakim_tarihi ASC
        ", [$this->firmaId]);
    }

    public function getYaklasanlar(int $gun = 30): array {
        return $this->db->fetchAll("
            SELECT pb.*, m.ad, m.soyad, m.telefon,
                   CAST(julianday(pb.sonraki_bakim_tarihi) - julianday('now') AS INTEGER) AS kalan_gun
            FROM periyodik_bakimlar pb JOIN musteriler m ON pb.musteri_id=m.id
            WHERE m.firma_id=? AND m.deleted_at IS NULL AND pb.deleted_at IS NULL AND pb.aktif=1
              AND pb.sonraki_bakim_tarihi BETWEEN DATE('now') AND DATE('now', '+{$gun} days')
              AND pb.sonraki_bakim_tarihi IS NOT NULL
            ORDER BY pb.sonraki_bakim_tarihi ASC
        ", [$this->firmaId]);
    }

    public function getTumListe(): array {
        return $this->db->fetchAll("
            SELECT pb.*, m.ad, m.soyad, m.telefon, m.email,
                   CASE
                     WHEN pb.sonraki_bakim_tarihi IS NULL THEN 'ayarsiz'
                     WHEN pb.sonraki_bakim_tarihi < DATE('now') THEN 'gecikmis'
                     WHEN pb.sonraki_bakim_tarihi <= DATE('now', '+' || pb.hatirlatma_gun || ' days') THEN 'yakin'
                     ELSE 'normal'
                   END AS bakim_durumu,
                   CAST(julianday(pb.sonraki_bakim_tarihi) - julianday('now') AS INTEGER) AS kalan_gun
            FROM periyodik_bakimlar pb JOIN musteriler m ON pb.musteri_id=m.id
            WHERE m.firma_id=? AND m.deleted_at IS NULL AND pb.deleted_at IS NULL AND pb.aktif=1
            ORDER BY pb.sonraki_bakim_tarihi ASC
        ", [$this->firmaId]);
    }

    // Bakım tamamlandı: bugünü son bakım olarak işaretle, sonraki tarihi hesapla
    public function tamamlandi(int $musteriId): bool {
        $ok = $this->db->fetchColumn(
            "SELECT id FROM musteriler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$musteriId, $this->firmaId]
        );
        if (!$ok) return false;

        $pb = $this->db->fetchOne(
            "SELECT * FROM periyodik_bakimlar WHERE musteri_id=? AND deleted_at IS NULL",
            [$musteriId]
        );
        if (!$pb) return false;

        $bugun  = date('Y-m-d');
        $periyot = (int)($pb['periyot_ay'] ?? 6);
        $sonraki = date('Y-m-d', strtotime($bugun . " +{$periyot} months"));

        $this->db->query(
            "UPDATE periyodik_bakimlar SET son_bakim_tarihi=?, sonraki_bakim_tarihi=?, synced_at=NULL WHERE musteri_id=? AND deleted_at IS NULL",
            [$bugun, $sonraki, $musteriId]
        );
        return true;
    }

}
