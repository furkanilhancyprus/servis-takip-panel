<?php
require_once __DIR__ . '/Model.php';

class Satis extends Model {

    public function getAll(array $filtre = []): array {
        $sql    = "
            SELECT st.*, m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   c.cihaz_adi, c.marka AS cihaz_marka,
                   (SELECT COUNT(*) FROM satis_kalemleri sk WHERE sk.satis_id=st.id AND sk.deleted_at IS NULL) AS kalem_sayisi,
                   (SELECT COUNT(*) FROM taksitler t WHERE t.satis_id=st.id AND t.deleted_at IS NULL AND t.odendi=0 AND t.taksit_no>0) AS bekleyen_taksit
            FROM satislar st
            JOIN musteriler m ON st.musteri_id=m.id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON st.cihaz_id=c.id AND c.deleted_at IS NULL
            WHERE st.firma_id=? AND st.deleted_at IS NULL
        ";
        $params = [$this->firmaId];

        if (!empty($filtre['musteri_id']))   { $sql .= " AND st.musteri_id=?";    $params[] = $filtre['musteri_id']; }
        if (!empty($filtre['odeme_durumu'])) { $sql .= " AND st.odeme_durumu=?";  $params[] = $filtre['odeme_durumu']; }
        if (!empty($filtre['baslangic']))    { $sql .= " AND st.satis_tarihi>=?"; $params[] = $filtre['baslangic']; }
        if (!empty($filtre['bitis']))        { $sql .= " AND st.satis_tarihi<=?"; $params[] = $filtre['bitis']; }
        if (!empty($filtre['search'])) {
            $sql .= " AND (m.ad LIKE ? OR m.soyad LIKE ? OR m.telefon LIKE ?)";
            $like = '%' . $filtre['search'] . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= " ORDER BY st.created_at DESC";
        if (!empty($filtre['limit'])) { $sql .= " LIMIT " . (int)$filtre['limit']; }

        return $this->db->fetchAll($sql, $params);
    }

    public function getById(int $id) {
        $satis = $this->db->fetchOne("
            SELECT st.*, m.ad, m.soyad, m.telefon, m.adres,
                   c.cihaz_adi, c.marka AS cihaz_marka, c.model AS cihaz_model
            FROM satislar st
            JOIN musteriler m ON st.musteri_id=m.id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON st.cihaz_id=c.id AND c.deleted_at IS NULL
            WHERE st.id=? AND st.firma_id=? AND st.deleted_at IS NULL
        ", [$id, $this->firmaId]);

        if (!$satis) return false;

        $satis['kalemler'] = $this->db->fetchAll(
            "SELECT sk.*, p.parca_adi AS stok_adi FROM satis_kalemleri sk
             LEFT JOIN parcalar p ON sk.parca_id=p.id AND p.deleted_at IS NULL
             WHERE sk.satis_id=? AND sk.deleted_at IS NULL", [$id]
        );
        $satis['tahsilatlar'] = $this->db->fetchAll(
            "SELECT * FROM tahsilatlar WHERE kaynak_tip='satis' AND kaynak_id=? AND firma_id=? ORDER BY created_at DESC",
            [$id, $this->firmaId]
        );
        $satis['taksitler'] = $this->db->fetchAll(
            "SELECT *, CAST(odendi AS INTEGER) AS odendi FROM taksitler WHERE satis_id=? AND firma_id=? AND deleted_at IS NULL ORDER BY taksit_no ASC",
            [$id, $this->firmaId]
        );
        foreach ($satis['taksitler'] as &$taksit) {
            $taksit['odendi'] = (int)($taksit['odendi'] ?? 0);
        }
        unset($taksit);

        return $satis;
    }

    public function create(array $data): int {
        $tarih      = $data['satis_tarihi'] ?? date('Y-m-d');
        $odemeTuru  = $data['odeme_turu']   ?? 'pesin';
        $taksitSay  = max(1, (int)($data['taksit_sayisi'] ?? 1));
        $pesinat    = (float)($data['pesinat'] ?? 0);
        $cihazId    = !empty($data['cihaz_id']) ? (int)$data['cihaz_id'] : null;
        $seriNo     = $data['seri_no'] ?? null;
        $musteriId  = (int)($data['musteri_id'] ?? 0);
        $this->requireMusteri($musteriId);
        if ($cihazId) {
            $this->requireCihaz($cihazId);
        }

        // Toplam hesapla
        $toplam = 0;
        $kalemler = $data['kalemler'] ?? [];
        foreach ($kalemler as $k) {
            $toplam += ($k['miktar'] ?? 1) * ($k['birim_fiyat'] ?? 0);
        }
        if (empty($kalemler)) $toplam = (float)($data['toplam_tutar'] ?? 0);

        // Peşinli satışta ilk ödeme durumu
        $odenenBaslangic = 0;
        $odemeDurum = 'odenmedi';
        if ($odemeTuru === 'pesin') {
            $odenenBaslangic = $toplam;
            $odemeDurum = 'odendi';
        } elseif ($pesinat > 0) {
            $odenenBaslangic = $pesinat;
            $odemeDurum = $pesinat >= $toplam ? 'odendi' : 'kismi';
        }

        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
        $id = $this->db->execute("
            INSERT INTO satislar (firma_id, musteri_id, toplam_tutar, odeme_durumu, odenen_tutar,
                                  notlar, satis_tarihi, odeme_turu, taksit_sayisi, pesinat, cihaz_id, seri_no)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ", [
            $this->firmaId, $musteriId, $toplam, $odemeDurum, $odenenBaslangic,
            $data['notlar'] ?? null, $tarih,
            $odemeTuru, $taksitSay, $pesinat, $cihazId, $seriNo,
        ]);

        // Kalemler
        if (!empty($kalemler)) {
            $stmt     = $pdo->prepare(
                "INSERT INTO satis_kalemleri (satis_id, urun_adi, miktar, birim_fiyat, parca_id, birim_maliyet_usd, usd_kur) VALUES (?,?,?,?,?,?,?)"
            );
            $stokStmt = $pdo->prepare(
                    "UPDATE parcalar SET stok_miktari=stok_miktari-?, updated_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=? AND stok_miktari>=?"
            );
            $maliyetStmt = $pdo->prepare(
                "SELECT maliyet_usd FROM parcalar WHERE id=? AND firma_id=? AND deleted_at IS NULL"
            );
            foreach ($kalemler as $k) {
                $parcaId = !empty($k['parca_id']) ? (int)$k['parca_id'] : null;
                $miktar = max(1, (int)($k['miktar'] ?? 1));
                $birimMaliyetUsd = 0;
                if ($parcaId) {
                    $maliyetStmt->execute([$parcaId, $this->firmaId]);
                    $birimMaliyetUsd = (float)($maliyetStmt->fetchColumn() ?: 0);
                    $stokStmt->execute([$miktar, $parcaId, $this->firmaId, $miktar]);
                    if ($stokStmt->rowCount() !== 1) {
                        throw new InvalidArgumentException('Stok yetersiz veya parca bu firmaya ait degil.');
                    }
                }
                $stmt->execute([$id, $k['urun_adi'], $miktar, $k['birim_fiyat'] ?? 0, $parcaId, $birimMaliyetUsd, $k['usd_kur'] ?? 0]);
            }
        }

        // Taksit planı oluştur
        if ($odemeTuru === 'taksitli') {
            require_once __DIR__ . '/Taksit.php';
            $taksitModel = new Taksit();
            $taksitModel->olustur($id, $musteriId, $toplam, $pesinat, $taksitSay, $tarih);
        }

        // Cihazı müşteriye bağla
        if ($cihazId) {
            $this->db->execute("
                INSERT INTO musteri_cihazlari (firma_id, musteri_id, cihaz_id, satis_id, seri_no, kurulum_tarihi, uuid)
                VALUES (?,?,?,?,?,?,?)
            ", [$this->firmaId, $musteriId, $cihazId, $id, $seriNo, $tarih, $this->uuid()]);
        }

        $this->ensureBakimTakibi($musteriId, $tarih);

        $pdo->commit();
        return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void {
        $this->db->query("UPDATE taksitler SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE satis_id=? AND firma_id=?", [$id, $this->firmaId]);
        $this->db->query("UPDATE tahsilatlar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE kaynak_tip='satis' AND kaynak_id=? AND firma_id=?", [$id, $this->firmaId]);
        $this->db->query("UPDATE musteri_cihazlari SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE satis_id=? AND firma_id=?", [$id, $this->firmaId]);
        $this->db->query("UPDATE satislar SET deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$id, $this->firmaId]);
    }

    public function getBuAyToplam(): float {
        return (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(toplam_tutar),0) FROM satislar
             WHERE firma_id=? AND deleted_at IS NULL AND strftime('%Y-%m',satis_tarihi)=strftime('%Y-%m','now')",
            [$this->firmaId]
        );
    }

    public function getAylikCiro(int $yil): array {
        $ciro = [];
        for ($ay = 1; $ay <= 12; $ay++) {
            $total = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(toplam_tutar),0) FROM satislar
                 WHERE firma_id=? AND deleted_at IS NULL AND strftime('%Y',satis_tarihi)=? AND strftime('%m',satis_tarihi)=?",
                [$this->firmaId, (string)$yil, str_pad((string)$ay, 2, '0', STR_PAD_LEFT)]
            );
            $ciro[] = (float)$total;
        }
        return $ciro;
    }

    private function ensureBakimTakibi(int $musteriId, string $satisTarihi): void {
        $bakim = $this->db->fetchOne(
            "SELECT id, periyot_ay FROM periyodik_bakimlar WHERE musteri_id=? AND deleted_at IS NULL",
            [$musteriId]
        );

        $periyot = $bakim ? (int)($bakim['periyot_ay'] ?? 0) : 0;
        if ($periyot <= 0) {
            $periyot = (int)$this->db->fetchColumn(
                "SELECT deger FROM ayarlar WHERE firma_id=? AND anahtar='varsayilan_bakim_periyodu'",
                [$this->firmaId]
            );
        }
        if ($periyot <= 0) {
            $periyot = 6;
        }

        $sonraki = date('Y-m-d', strtotime($satisTarihi . " +{$periyot} months"));

        if ($bakim) {
            $this->db->query(
                "UPDATE periyodik_bakimlar
                 SET aktif=1, periyot_ay=?, son_bakim_tarihi=?, sonraki_bakim_tarihi=?, synced_at=NULL
                 WHERE id=?",
                [$periyot, $satisTarihi, $sonraki, (int)$bakim['id']]
            );
            return;
        }

        $this->db->execute(
            "INSERT INTO periyodik_bakimlar (musteri_id, aktif, periyot_ay, son_bakim_tarihi, sonraki_bakim_tarihi, uuid)
             VALUES (?, 1, ?, ?, ?, ?)",
            [$musteriId, $periyot, $satisTarihi, $sonraki, $this->uuid()]
        );
    }
}
