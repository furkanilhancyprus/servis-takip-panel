<?php
require_once __DIR__ . '/Model.php';

class Taksit extends Model {

    // Satış için taksit planı oluştur
    public function olustur(int $satisId, int $musteriId, float $toplam, float $pesinat, int $taksitSayisi, string $ilkTarih): void {
        // Önce eski taksitleri sil
        $this->db->query("UPDATE taksitler SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE satis_id=? AND firma_id=?", [$satisId, $this->firmaId]);

        $kalanTutar   = $toplam - $pesinat;
        if ($kalanTutar <= 0 || $taksitSayisi <= 0) return;

        $taksitTutari = round($kalanTutar / $taksitSayisi, 2);
        $fark         = round($kalanTutar - ($taksitTutari * $taksitSayisi), 2);

        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO taksitler (firma_id, satis_id, musteri_id, taksit_no, tutar, vade_tarihi)
            VALUES (?,?,?,?,?,?)
        ");

        for ($i = 1; $i <= $taksitSayisi; $i++) {
            $tutar      = $taksitTutari;
            if ($i === $taksitSayisi) $tutar += $fark; // son taksit fark düzeltmesi
            $vadeTarihi = date('Y-m-d', strtotime("$ilkTarih +" . ($i - 1) . " months"));
            $stmt->execute([$this->firmaId, $satisId, $musteriId, $i, $tutar, $vadeTarihi]);
        }

        // Peşinat varsa 0 numaralı taksit olarak kaydet (ödendi)
        if ($pesinat > 0) {
            $this->db->execute("
                INSERT INTO taksitler (firma_id, satis_id, musteri_id, taksit_no, tutar, vade_tarihi, odendi, odeme_tarihi)
                VALUES (?,?,?,0,?,?,1,?)
            ", [$this->firmaId, $satisId, $musteriId, $pesinat, $ilkTarih, $ilkTarih]);
        }
    }

    // Satışın tüm taksitlerini getir
    public function getBySatisId(int $satisId): array {
        return $this->db->fetchAll("
            SELECT t.*, m.ad || ' ' || m.soyad AS musteri_adi
            FROM taksitler t
            JOIN musteriler m ON t.musteri_id = m.id
            WHERE t.satis_id=? AND t.firma_id=? AND t.deleted_at IS NULL
            ORDER BY t.taksit_no ASC
        ", [$satisId, $this->firmaId]);
    }

    // Tüm bekleyen taksitler (tahsilat ekranı için)
    public function getBekleyenler(): array {
        return $this->db->fetchAll("
            SELECT t.*, m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   c.cihaz_adi, c.marka,
                   s.toplam_tutar AS satis_toplam,
                   CAST(julianday('now') - julianday(t.vade_tarihi) AS INTEGER) AS gecikme_gun
            FROM taksitler t
            JOIN musteriler m ON t.musteri_id = m.id AND m.deleted_at IS NULL
            JOIN satislar   s ON t.satis_id   = s.id AND s.deleted_at IS NULL
            LEFT JOIN cihazlar c ON s.cihaz_id = c.id AND c.deleted_at IS NULL
            WHERE t.firma_id=? AND t.deleted_at IS NULL AND t.odendi=0 AND t.taksit_no > 0
            ORDER BY t.vade_tarihi ASC
        ", [$this->firmaId]);
    }

    // Taksiti öde
    public function odemeYap(int $id, string $odemeYontemi = 'nakit', ?string $odemeTarihi = null): bool {
        $tarih = $odemeTarihi ?? date('Y-m-d');

        $taksit = $this->db->fetchOne(
            "SELECT * FROM taksitler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$taksit) return false;

        $this->db->query("
            UPDATE taksitler SET odendi=1, odeme_tarihi=?, odeme_yontemi=?, synced_at=NULL
            WHERE id=? AND firma_id=?
        ", [$tarih, $odemeYontemi, $id, $this->firmaId]);

        // Satışın ödenen tutarını güncelle
        $this->updateSatisOdeme((int)$taksit['satis_id']);

        return true;
    }

    // Taksit ödemesini geri al
    public function odemeGeriAl(int $id): bool {
        $taksit = $this->db->fetchOne(
            "SELECT * FROM taksitler WHERE id=? AND firma_id=? AND taksit_no > 0 AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$taksit) return false;

        $this->db->query(
            "UPDATE taksitler SET odendi=0, odeme_tarihi=NULL, synced_at=NULL WHERE id=? AND firma_id=?",
            [$id, $this->firmaId]
        );

        $this->updateSatisOdeme((int)$taksit['satis_id']);

        return true;
    }

    private function updateSatisOdeme(int $satisId): void {
        $odenen = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM taksitler WHERE satis_id=? AND firma_id=? AND odendi=1 AND deleted_at IS NULL",
            [$satisId, $this->firmaId]
        );
        $toplam = (float) $this->db->fetchColumn(
            "SELECT toplam_tutar FROM satislar WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$satisId, $this->firmaId]
        );

        $durum  = $odenen <= 0 ? 'odenmedi' : ($odenen >= $toplam ? 'odendi' : 'kismi');

        $this->db->query(
            "UPDATE satislar SET odenen_tutar=?, odeme_durumu=?, synced_at=NULL WHERE id=? AND firma_id=?",
            [min($odenen, $toplam), $durum, $satisId, $this->firmaId]
        );
    }

    public function getOzet(): array {
        $fid = $this->firmaId;
        return [
            'bekleyen_adet'  => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND taksit_no>0", [$fid]
            ),
            'bekleyen_tutar' => (float) $this->db->fetchColumn(
                "SELECT COALESCE(SUM(tutar),0) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND taksit_no>0", [$fid]
            ),
            'gecikme_adet'   => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND taksit_no>0 AND vade_tarihi < date('now')", [$fid]
            ),
            'bu_ay_odenen'   => (float) $this->db->fetchColumn(
                "SELECT COALESCE(SUM(tutar),0) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=1 AND strftime('%Y-%m',odeme_tarihi)=strftime('%Y-%m','now')", [$fid]
            ),
        ];
    }
}
