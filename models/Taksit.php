<?php
require_once __DIR__ . '/Model.php';

class Taksit extends Model {
    public function olustur(int $satisId, int $musteriId, float $toplam, float $pesinat, int $taksitSayisi, string $ilkTarih, ?string $pesinatTarihi = null): void {
        $this->db->query(
            "UPDATE taksitler SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE satis_id=? AND firma_id=?",
            [$satisId, $this->firmaId]
        );

        $kalanTutar = $toplam - $pesinat;
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO taksitler (firma_id, satis_id, musteri_id, taksit_no, tutar, odenen_tutar, vade_tarihi, odendi, odeme_tarihi)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");

        if ($pesinat > 0) {
            $pesinatTarihi = $pesinatTarihi ?: $ilkTarih;
            $stmt->execute([$this->firmaId, $satisId, $musteriId, 0, $pesinat, $pesinat, $pesinatTarihi, 1, $pesinatTarihi]);
        }

        if ($kalanTutar <= 0 || $taksitSayisi <= 0) {
            return;
        }

        $taksitTutari = round($kalanTutar / $taksitSayisi, 2);
        $fark = round($kalanTutar - ($taksitTutari * $taksitSayisi), 2);

        for ($i = 1; $i <= $taksitSayisi; $i++) {
            $tutar = $taksitTutari + ($i === $taksitSayisi ? $fark : 0);
            $vadeTarihi = date('Y-m-d', strtotime("$ilkTarih +" . ($i - 1) . " months"));
            $stmt->execute([$this->firmaId, $satisId, $musteriId, $i, $tutar, 0, $vadeTarihi, 0, null]);
        }
    }

    public function getBySatisId(int $satisId): array {
        return $this->db->fetchAll("
            SELECT t.*, m.ad || ' ' || m.soyad AS musteri_adi,
                   MAX(0, t.tutar - COALESCE(t.odenen_tutar,0)) AS kalan_tutar
            FROM taksitler t
            JOIN musteriler m ON t.musteri_id = m.id
            WHERE t.satis_id=? AND t.firma_id=? AND t.deleted_at IS NULL
            ORDER BY t.taksit_no ASC
        ", [$satisId, $this->firmaId]);
    }

    public function getBekleyenler(): array {
        return $this->db->fetchAll("
            SELECT t.*, m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   c.cihaz_adi, c.marka,
                   s.toplam_tutar AS satis_toplam,
                   MAX(0, t.tutar - COALESCE(t.odenen_tutar,0)) AS kalan_tutar,
                   CAST(julianday('now') - julianday(t.vade_tarihi) AS INTEGER) AS gecikme_gun
            FROM taksitler t
            JOIN musteriler m ON t.musteri_id = m.id AND m.deleted_at IS NULL
            JOIN satislar s ON t.satis_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN cihazlar c ON s.cihaz_id = c.id AND c.deleted_at IS NULL
            WHERE t.firma_id=? AND t.deleted_at IS NULL AND t.odendi=0 AND t.taksit_no > 0
            ORDER BY t.vade_tarihi ASC
        ", [$this->firmaId]);
    }

    public function odemeYap(int $id, string $odemeYontemi = 'nakit', ?string $odemeTarihi = null, ?float $tutar = null): bool {
        $tarih = $odemeTarihi ?? date('Y-m-d');
        $taksit = $this->db->fetchOne(
            "SELECT * FROM taksitler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$taksit) return false;

        $kalan = max(0, (float)$taksit['tutar'] - (float)($taksit['odenen_tutar'] ?? 0));
        $tutar = $tutar === null ? $kalan : max(0, min($kalan, $tutar));
        if ($tutar <= 0) return false;

        require_once __DIR__ . '/Tahsilat.php';
        (new Tahsilat())->create([
            'musteri_id' => (int)$taksit['musteri_id'],
            'kaynak_tip' => 'satis',
            'kaynak_id' => (int)$taksit['satis_id'],
            'taksit_id' => $id,
            'tutar' => $tutar,
            'odeme_yontemi' => $odemeYontemi,
            'tahsilat_tarihi' => $tarih,
            'notlar' => ((int)$taksit['taksit_no']) . '. taksit odemesi',
        ]);

        return true;
    }

    public function odemeGeriAl(int $id): bool {
        $taksit = $this->db->fetchOne(
            "SELECT * FROM taksitler WHERE id=? AND firma_id=? AND taksit_no > 0 AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$taksit) return false;

        $tahsilatId = $this->db->fetchColumn(
            "SELECT id FROM tahsilatlar WHERE taksit_id=? AND firma_id=? AND deleted_at IS NULL ORDER BY tahsilat_tarihi DESC, id DESC LIMIT 1",
            [$id, $this->firmaId]
        );
        if (!$tahsilatId) {
            $tahsilatId = $this->db->fetchColumn(
                "SELECT id FROM tahsilatlar WHERE kaynak_tip='satis' AND kaynak_id=? AND firma_id=? AND deleted_at IS NULL ORDER BY tahsilat_tarihi DESC, id DESC LIMIT 1",
                [(int)$taksit['satis_id'], $this->firmaId]
            );
        }
        if ($tahsilatId) {
            require_once __DIR__ . '/Tahsilat.php';
            (new Tahsilat())->delete((int)$tahsilatId);
        } else {
            $this->db->query(
                "UPDATE taksitler SET odendi=0, odenen_tutar=0, odeme_tarihi=NULL, synced_at=NULL WHERE id=? AND firma_id=?",
                [$id, $this->firmaId]
            );
            $this->updateSatisOdeme((int)$taksit['satis_id']);
        }
        return true;
    }

    public function updateSatisOdeme(int $satisId): void {
        $pesinat = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM taksitler WHERE satis_id=? AND firma_id=? AND taksit_no=0 AND odendi=1 AND deleted_at IS NULL",
            [$satisId, $this->firmaId]
        );
        $tahsilat = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE kaynak_tip='satis' AND kaynak_id=? AND firma_id=? AND deleted_at IS NULL",
            [$satisId, $this->firmaId]
        );
        $toplam = (float)$this->db->fetchColumn(
            "SELECT toplam_tutar FROM satislar WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$satisId, $this->firmaId]
        );

        $this->rebuildTaksitOdemeleri($satisId, $tahsilat);

        $odenen = min($toplam, $pesinat + $tahsilat);
        $durum = $odenen <= 0 ? 'odenmedi' : ($odenen >= $toplam ? 'odendi' : 'kismi');
        $this->db->query(
            "UPDATE satislar SET odenen_tutar=?, odeme_durumu=?, synced_at=NULL WHERE id=? AND firma_id=?",
            [$odenen, $durum, $satisId, $this->firmaId]
        );
    }

    private function rebuildTaksitOdemeleri(int $satisId, float $odenenTaksitTutari): void {
        $rows = $this->db->fetchAll(
            "SELECT id, tutar FROM taksitler WHERE satis_id=? AND firma_id=? AND taksit_no>0 AND deleted_at IS NULL ORDER BY taksit_no ASC",
            [$satisId, $this->firmaId]
        );
        foreach ($rows as $row) {
            $tutar = (float)$row['tutar'];
            $pay = min($tutar, max(0, $odenenTaksitTutari));
            $odenenTaksitTutari -= $pay;
            $odendi = $tutar <= 0 || $pay >= $tutar ? 1 : 0;
            $this->db->query(
                "UPDATE taksitler
                 SET odenen_tutar=?, odendi=?, odeme_tarihi=CASE WHEN ?=1 THEN COALESCE(odeme_tarihi, date('now')) ELSE NULL END, synced_at=NULL
                 WHERE id=? AND firma_id=?",
                [$pay, $odendi, $odendi, (int)$row['id'], $this->firmaId]
            );
        }
    }

    public function getOzet(): array {
        $fid = $this->firmaId;
        return [
            'bekleyen_adet' => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND taksit_no>0",
                [$fid]
            ),
            'bekleyen_tutar' => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(MAX(0, tutar-COALESCE(odenen_tutar,0))),0) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND taksit_no>0",
                [$fid]
            ),
            'gecikme_adet' => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND taksit_no>0 AND vade_tarihi < date('now')",
                [$fid]
            ),
            'bu_ay_odenen' => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='satis' AND strftime('%Y-%m',tahsilat_tarihi)=strftime('%Y-%m','now')",
                [$fid]
            ),
        ];
    }
}
