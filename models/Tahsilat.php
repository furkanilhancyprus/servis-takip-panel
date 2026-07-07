<?php
require_once __DIR__ . '/Model.php';

class Tahsilat extends Model {

    public function getAll(array $filtre = []): array {
        $sql    = "
            SELECT t.*, m.ad || ' ' || m.soyad AS musteri_adi, m.telefon
            FROM tahsilatlar t JOIN musteriler m ON t.musteri_id=m.id AND m.deleted_at IS NULL
            WHERE t.firma_id=? AND t.deleted_at IS NULL
        ";
        $params = [$this->firmaId];

        if (!empty($filtre['musteri_id'])) { $sql .= " AND t.musteri_id=?";     $params[] = $filtre['musteri_id']; }
        if (!empty($filtre['kaynak_tip'])) { $sql .= " AND t.kaynak_tip=?";     $params[] = $filtre['kaynak_tip']; }
        if (!empty($filtre['baslangic'])) { $sql .= " AND t.tahsilat_tarihi>=?"; $params[] = $filtre['baslangic']; }
        if (!empty($filtre['bitis']))     { $sql .= " AND t.tahsilat_tarihi<=?"; $params[] = $filtre['bitis']; }

        $sql .= " ORDER BY t.created_at DESC";
        if (!empty($filtre['limit'])) { $sql .= " LIMIT " . (int)$filtre['limit']; }

        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): int {
        $musteriId = (int)($data['musteri_id'] ?? 0);
        $kaynakTip = $data['kaynak_tip'] ?? '';
        $kaynakId = (int)($data['kaynak_id'] ?? 0);
        $this->requireKaynak($kaynakTip, $kaynakId, $musteriId);

        $id = $this->db->execute("
            INSERT INTO tahsilatlar (firma_id, musteri_id, kaynak_tip, kaynak_id, taksit_id, tutar, odeme_yontemi, notlar, tahsilat_tarihi)
            VALUES (?,?,?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $musteriId, $kaynakTip, $kaynakId,
            $data['taksit_id'] ?? null,
            $data['tutar'], $data['odeme_yontemi'] ?? 'nakit',
            $data['notlar'] ?? null, $data['tahsilat_tarihi'] ?? date('Y-m-d'),
        ]);

        $this->updateOdemeDurumu($kaynakTip, $kaynakId);

        return $id;
    }

    public function delete(int $id): void {
        $t = $this->db->fetchOne("SELECT kaynak_tip, kaynak_id FROM tahsilatlar WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$id, $this->firmaId]);
        $this->db->query("UPDATE tahsilatlar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=?", [$id, $this->firmaId]);
        if ($t) {
            $this->updateOdemeDurumu($t['kaynak_tip'], (int)$t['kaynak_id']);
        }
    }

    private function updateOdemeDurumu(string $tip, int $kaynakId): void {
        if ($tip === 'satis') {
            require_once __DIR__ . '/Taksit.php';
            (new Taksit())->updateSatisOdeme($kaynakId);
            return;
        }

        $table = $tip === 'servis' ? 'servisler' : 'satislar';

        $toplam = (float) $this->db->fetchColumn("SELECT toplam_tutar FROM $table WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$kaynakId, $this->firmaId]);
        $odenen = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE kaynak_tip=? AND kaynak_id=? AND firma_id=? AND deleted_at IS NULL",
            [$tip, $kaynakId, $this->firmaId]
        );

        $durum = $toplam <= 0 ? 'odendi' : ($odenen <= 0 ? 'odenmedi' : ($odenen >= $toplam ? 'odendi' : 'kismi'));
        $odenen = min($odenen, $toplam);

        $this->db->query(
            "UPDATE $table SET odeme_durumu=?, odenen_tutar=?, synced_at=NULL WHERE id=? AND firma_id=?",
            [$durum, $odenen, $kaynakId, $this->firmaId]
        );
    }

    private function requireKaynak(string $tip, int $kaynakId, int $musteriId): void {
        if (!in_array($tip, ['servis', 'satis'], true)) {
            throw new InvalidArgumentException('Gecersiz tahsilat tipi.');
        }
        $table = $tip === 'servis' ? 'servisler' : 'satislar';
        $ok = $this->db->fetchColumn(
            "SELECT id FROM $table WHERE id=? AND musteri_id=? AND firma_id=? AND deleted_at IS NULL",
            [$kaynakId, $musteriId, $this->firmaId]
        );
        if (!$ok) {
            throw new InvalidArgumentException('Tahsilat kaynagi bulunamadi veya bu firmaya ait degil.');
        }
    }

    public function getOdenmemisler(): array {
        $servisler = $this->db->fetchAll("
            SELECT 'servis' AS tip, s.id, s.musteri_id,
                   m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   s.toplam_tutar, s.odenen_tutar,
                   (s.toplam_tutar - s.odenen_tutar) AS kalan,
                   s.odeme_durumu, s.tamamlanma_tarihi AS tarih, s.servis_tipi AS alt_tip
            FROM servisler s JOIN musteriler m ON s.musteri_id=m.id AND m.deleted_at IS NULL
            WHERE s.firma_id=? AND s.deleted_at IS NULL AND s.odeme_durumu IN ('odenmedi','kismi') AND s.toplam_tutar>0
            ORDER BY s.tamamlanma_tarihi ASC
        ", [$this->firmaId]);

        $satislar = $this->db->fetchAll("
            SELECT 'satis' AS tip, st.id, st.musteri_id,
                   m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   st.toplam_tutar, st.odenen_tutar,
                   (st.toplam_tutar - st.odenen_tutar) AS kalan,
                   st.odeme_durumu, st.satis_tarihi AS tarih, 'satis' AS alt_tip
            FROM satislar st JOIN musteriler m ON st.musteri_id=m.id AND m.deleted_at IS NULL
            WHERE st.firma_id=? AND st.deleted_at IS NULL AND st.odeme_durumu IN ('odenmedi','kismi') AND st.toplam_tutar>0
            ORDER BY st.satis_tarihi ASC
        ", [$this->firmaId]);

        return array_merge($servisler, $satislar);
    }

    public function getTahsilOzeti(): array {
        $fid = $this->firmaId;
        $bugun = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE firma_id=? AND deleted_at IS NULL AND tahsilat_tarihi=date('now')", [$fid]
        );
        $buay = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE firma_id=? AND deleted_at IS NULL AND strftime('%Y-%m',tahsilat_tarihi)=strftime('%Y-%m','now')", [$fid]
        );
        $bekleyen  = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(toplam_tutar-odenen_tutar),0) FROM servisler WHERE firma_id=? AND deleted_at IS NULL AND odeme_durumu IN ('odenmedi','kismi')", [$fid]
        );
        $bekleyen += (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(toplam_tutar-odenen_tutar),0) FROM satislar WHERE firma_id=? AND deleted_at IS NULL AND odeme_durumu IN ('odenmedi','kismi')", [$fid]
        );

        return ['bugun_tahsilat' => $bugun, 'buay_tahsilat' => $buay, 'toplam_bekleyen' => $bekleyen];
    }

    public function getAylikTahsilat(int $yil): array {
        $aylik = [];
        for ($ay = 1; $ay <= 12; $ay++) {
            $total = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar
                 WHERE firma_id=? AND deleted_at IS NULL AND strftime('%Y',tahsilat_tarihi)=? AND strftime('%m',tahsilat_tarihi)=?",
                [$this->firmaId, (string)$yil, str_pad((string)$ay, 2, '0', STR_PAD_LEFT)]
            );
            $aylik[] = (float)$total;
        }
        return $aylik;
    }
}
