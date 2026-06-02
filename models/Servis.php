<?php
require_once __DIR__ . '/Model.php';

class Servis extends Model {

    public function getAll(array $filtre = []): array {
        $sql    = "
            SELECT s.*, m.ad || ' ' || m.soyad AS musteri_adi, m.telefon
            FROM servisler s
            JOIN musteriler m ON s.musteri_id = m.id AND m.deleted_at IS NULL
            WHERE s.firma_id = ? AND s.deleted_at IS NULL
        ";
        $params = [$this->firmaId];

        if (!empty($filtre['musteri_id']))   { $sql .= " AND s.musteri_id=?";           $params[] = $filtre['musteri_id']; }
        if (!empty($filtre['servis_tipi']))  { $sql .= " AND s.servis_tipi=?";          $params[] = $filtre['servis_tipi']; }
        if (!empty($filtre['odeme_durumu'])) { $sql .= " AND s.odeme_durumu=?";         $params[] = $filtre['odeme_durumu']; }
        if (!empty($filtre['baslangic']))    { $sql .= " AND DATE(s.tamamlanma_tarihi)>=?"; $params[] = $filtre['baslangic']; }
        if (!empty($filtre['bitis']))        { $sql .= " AND DATE(s.tamamlanma_tarihi)<=?"; $params[] = $filtre['bitis']; }
        if (!empty($filtre['search'])) {
            $sql .= " AND (m.ad LIKE ? OR m.soyad LIKE ? OR m.telefon LIKE ?)";
            $like = '%' . $filtre['search'] . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= " ORDER BY s.created_at DESC";
        if (!empty($filtre['limit'])) { $sql .= " LIMIT " . (int)$filtre['limit']; }

        return $this->db->fetchAll($sql, $params);
    }

    public function getById(int $id): array|false {
        $servis = $this->db->fetchOne("
            SELECT s.*, m.ad, m.soyad, m.telefon, m.adres, m.email
            FROM servisler s
            JOIN musteriler m ON s.musteri_id = m.id AND m.deleted_at IS NULL
            WHERE s.id=? AND s.firma_id=? AND s.deleted_at IS NULL
        ", [$id, $this->firmaId]);

        if (!$servis) return false;

        $servis['islemler']  = $this->db->fetchAll(
            "SELECT id, islem, tutar FROM servis_islemleri WHERE servis_id=? AND deleted_at IS NULL", [$id]
        );
        $servis['parcalar']  = $this->db->fetchAll("
            SELECT sp.*, p.parca_adi, p.marka
            FROM servis_parcalari sp JOIN parcalar p ON sp.parca_id=p.id AND p.deleted_at IS NULL
            WHERE sp.servis_id=? AND sp.deleted_at IS NULL
        ", [$id]);
        $servis['tahsilatlar'] = $this->db->fetchAll(
            "SELECT * FROM tahsilatlar WHERE kaynak_tip='servis' AND kaynak_id=? AND firma_id=? ORDER BY created_at DESC",
            [$id, $this->firmaId]
        );

        return $servis;
    }

    public function create(array $data): int {
        $tarih = $data['servis_tarihi'] ?? $this->today();
        $musteriId = (int)($data['musteri_id'] ?? 0);
        $this->requireMusteri($musteriId);

        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $id = $this->db->execute("
                INSERT INTO servisler (firma_id, musteri_id, servis_tipi, durum, toplam_tutar,
                                       odeme_durumu, odenen_tutar, notlar, tamamlanma_tarihi, created_at, updated_at)
                VALUES (?,?,?,'tamamlanan',?,'odenmedi',0,?,?,?,?)
            ", [
                $this->firmaId, $musteriId, $data['servis_tipi'],
                $data['toplam_tutar'] ?? 0, $data['notlar'] ?? null,
                $tarih, $tarih, $tarih,
            ]);

            if (!empty($data['islemler'])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO servis_islemleri (servis_id, islem, tutar) VALUES (?,?,?)"
                );
                foreach ($data['islemler'] as $islem) {
                    if (empty($islem['islem'])) continue;
                    $stmt->execute([$id, $islem['islem'], $islem['tutar'] ?? 0]);
                }
            }

            if (!empty($data['parcalar'])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO servis_parcalari (servis_id, parca_id, miktar, birim_fiyat) VALUES (?,?,?,?)"
                );
                $stokStmt = $pdo->prepare(
                    "UPDATE parcalar SET stok_miktari=stok_miktari-?, updated_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=? AND stok_miktari>=?"
                );
                foreach ($data['parcalar'] as $parca) {
                    if (empty($parca['parca_id'])) continue;
                    $parcaId = (int)$parca['parca_id'];
                    $miktar = max(1, (int)($parca['miktar'] ?? 1));
                    $stokStmt->execute([$miktar, $parcaId, $this->firmaId, $miktar]);
                    if ($stokStmt->rowCount() !== 1) {
                        throw new InvalidArgumentException('Stok yetersiz veya parca bu firmaya ait degil.');
                    }
                    $stmt->execute([$id, $parcaId, $miktar, $parca['birim_fiyat'] ?? 0]);
                }
            }

            if ($data['servis_tipi'] === 'periyodik_bakim') {
                $periyot = isset($data['periyot_ay']) && (int)$data['periyot_ay'] > 0
                    ? (int)$data['periyot_ay'] : null;
                $this->bakimTamamlandi($musteriId, $tarih, $periyot);
            }

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): void {
        $tarih = $data['servis_tarihi'] ?? $this->today();
        $this->requireMusteri((int)($data['musteri_id'] ?? 0));
        $this->requireServis($id);

        $this->db->query("
            UPDATE servisler
            SET musteri_id=?, servis_tipi=?, toplam_tutar=?, notlar=?,
                tamamlanma_tarihi=?, updated_at=?, synced_at=NULL
            WHERE id=? AND firma_id=? AND deleted_at IS NULL
        ", [
            $data['musteri_id'], $data['servis_tipi'],
            $data['toplam_tutar'] ?? 0, $data['notlar'] ?? null,
            $tarih, $this->now(), $id, $this->firmaId,
        ]);

        $this->db->query("UPDATE servis_islemleri SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE servis_id=? AND EXISTS (SELECT 1 FROM servisler s WHERE s.id=servis_islemleri.servis_id AND s.firma_id=?)", [$id, $this->firmaId]);
        if (!empty($data['islemler'])) {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO servis_islemleri (servis_id, islem, tutar) VALUES (?,?,?)"
            );
            foreach ($data['islemler'] as $islem) {
                if (empty($islem['islem'])) continue;
                $stmt->execute([$id, $islem['islem'], $islem['tutar'] ?? 0]);
            }
        }

        $toplam = (float)($data['toplam_tutar'] ?? 0);
        $odenen = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE kaynak_tip='servis' AND kaynak_id=? AND firma_id=?",
            [$id, $this->firmaId]
        );
        $durum = $odenen <= 0 ? 'odenmedi' : ($odenen >= $toplam ? 'odendi' : 'kismi');
        $this->db->query(
            "UPDATE servisler SET odeme_durumu=?, odenen_tutar=?, synced_at=NULL WHERE id=? AND firma_id=?",
            [$durum, min($odenen, $toplam), $id, $this->firmaId]
        );
    }

    public function delete(int $id): void {
        $this->db->query("UPDATE tahsilatlar SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE kaynak_tip='servis' AND kaynak_id=? AND firma_id=?", [$id, $this->firmaId]);
        $this->db->query("UPDATE servisler SET deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$id, $this->firmaId]);
    }

    public function getBuAyYapilan(): array {
        return $this->db->fetchAll("
            SELECT s.*, m.ad || ' ' || m.soyad AS musteri_adi
            FROM servisler s JOIN musteriler m ON s.musteri_id=m.id AND m.deleted_at IS NULL
            WHERE s.firma_id=? AND s.deleted_at IS NULL AND strftime('%Y-%m', s.tamamlanma_tarihi)=strftime('%Y-%m','now')
            ORDER BY s.tamamlanma_tarihi DESC
        ", [$this->firmaId]);
    }

    public function getAylikCiro(int $yil): array {
        $ciro = [];
        for ($ay = 1; $ay <= 12; $ay++) {
            $total = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(toplam_tutar),0) FROM servisler
                 WHERE firma_id=? AND deleted_at IS NULL AND strftime('%Y',tamamlanma_tarihi)=? AND strftime('%m',tamamlanma_tarihi)=?",
                [$this->firmaId, (string)$yil, str_pad((string)$ay, 2, '0', STR_PAD_LEFT)]
            );
            $ciro[] = (float)$total;
        }
        return $ciro;
    }


    public function getBugun(): array {
        return $this->db->fetchAll("
            SELECT s.*, m.ad || ' ' || m.soyad AS musteri_adi
            FROM servisler s JOIN musteriler m ON s.musteri_id=m.id AND m.deleted_at IS NULL
            WHERE s.firma_id=? AND s.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi)=DATE('now')
            ORDER BY s.created_at DESC
        ", [$this->firmaId]);
    }

    public function getHaftalikCiro(): array {
        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $tarih = date('Y-m-d', strtotime("-{$i} days"));
            $total = (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(toplam_tutar),0) FROM servisler
                 WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi)=?",
                [$this->firmaId, $tarih]
            );
            $result[] = ['tarih' => $tarih, 'toplam' => $total];
        }
        return $result;
    }

    private function bakimTamamlandi(int $musteriId, string $tarih, ?int $periyot = null): void {
        $this->requireMusteri($musteriId);
        $bakim = $this->db->fetchOne(
            "SELECT periyot_ay FROM periyodik_bakimlar WHERE musteri_id=? AND deleted_at IS NULL", [$musteriId]
        );
        if ($periyot === null) {
            $periyot = $bakim ? (int)$bakim['periyot_ay'] : 6;
        }
        $sonraki = date('Y-m-d', strtotime("$tarih +{$periyot} months"));

        if ($bakim) {
            $this->db->query(
                "UPDATE periyodik_bakimlar SET son_bakim_tarihi=?, sonraki_bakim_tarihi=?, periyot_ay=?, synced_at=NULL WHERE musteri_id=? AND deleted_at IS NULL",
                [$tarih, $sonraki, $periyot, $musteriId]
            );
        } else {
            $this->db->execute(
                "INSERT INTO periyodik_bakimlar (musteri_id, son_bakim_tarihi, sonraki_bakim_tarihi, periyot_ay) VALUES (?,?,?,?)",
                [$musteriId, $tarih, $sonraki, $periyot]
            );
        }
    }

    private function requireServis(int $id): void {
        $ok = $this->db->fetchColumn(
            "SELECT id FROM servisler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$ok) {
            throw new InvalidArgumentException('Servis bulunamadi veya bu firmaya ait degil.');
        }
    }
}
