<?php
require_once __DIR__ . '/Model.php';

class Tedarikci extends Model {
    public function getSuppliers(): array {
        return $this->db->fetchAll("
            SELECT t.*,
                   COUNT(a.id) AS alim_sayisi,
                   COALESCE(SUM(a.toplam_tutar),0) AS toplam_alim,
                   COALESCE(SUM(a.odenen_tutar),0) AS toplam_odenen,
                   COALESCE(SUM(a.toplam_tutar - a.odenen_tutar),0) AS kalan_borc,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')='USD' THEN a.toplam_tutar ELSE 0 END),0) AS toplam_alim_usd,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')='USD' THEN a.odenen_tutar ELSE 0 END),0) AS toplam_odenen_usd,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')='USD' THEN a.toplam_tutar - a.odenen_tutar ELSE 0 END),0) AS kalan_borc_usd,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')='USD' THEN a.toplam_tutar * COALESCE(a.usd_kur,0) ELSE 0 END),0) AS toplam_alim_usd_tl,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')='USD' THEN a.odenen_tutar * COALESCE(a.usd_kur,0) ELSE 0 END),0) AS toplam_odenen_usd_tl,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')='USD' THEN (a.toplam_tutar - a.odenen_tutar) * COALESCE(a.usd_kur,0) ELSE 0 END),0) AS kalan_borc_usd_tl,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')<>'USD' THEN a.toplam_tutar ELSE 0 END),0) AS toplam_alim_try,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')<>'USD' THEN a.odenen_tutar ELSE 0 END),0) AS toplam_odenen_try,
                   COALESCE(SUM(CASE WHEN COALESCE(a.para_birimi,'TRY')<>'USD' THEN a.toplam_tutar - a.odenen_tutar ELSE 0 END),0) AS kalan_borc_try
            FROM tedarikciler t
            LEFT JOIN tedarikci_alimlari a
              ON a.firma_id=t.firma_id
             AND a.deleted_at IS NULL
             AND LOWER(a.tedarikci_adi)=LOWER(t.ad)
            WHERE t.firma_id=? AND t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY t.ad ASC
        ", [$this->firmaId]);
    }

    public function createSupplier(array $data): int {
        $ad = trim((string)($data['ad'] ?? ''));
        if ($ad === '') {
            throw new InvalidArgumentException('Tedarikci adi zorunludur.');
        }
        return $this->db->execute("
            INSERT INTO tedarikciler (firma_id, ad, yetkili, telefon, email, adres, notlar, uuid)
            VALUES (?,?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $ad,
            trim((string)($data['yetkili'] ?? '')) ?: null,
            trim((string)($data['telefon'] ?? '')) ?: null,
            trim((string)($data['email'] ?? '')) ?: null,
            trim((string)($data['adres'] ?? '')) ?: null,
            trim((string)($data['notlar'] ?? '')) ?: null,
            $this->uuid(),
        ]);
    }

    public function updateSupplier(int $id, array $data): void {
        $ad = trim((string)($data['ad'] ?? ''));
        if ($ad === '') {
            throw new InvalidArgumentException('Tedarikci adi zorunludur.');
        }
        $this->db->query("
            UPDATE tedarikciler
            SET ad=?, yetkili=?, telefon=?, email=?, adres=?, notlar=?, updated_at=?, synced_at=NULL
            WHERE id=? AND firma_id=? AND deleted_at IS NULL
        ", [
            $ad,
            trim((string)($data['yetkili'] ?? '')) ?: null,
            trim((string)($data['telefon'] ?? '')) ?: null,
            trim((string)($data['email'] ?? '')) ?: null,
            trim((string)($data['adres'] ?? '')) ?: null,
            trim((string)($data['notlar'] ?? '')) ?: null,
            $this->now(),
            $id,
            $this->firmaId,
        ]);
    }

    public function deleteSupplier(int $id): void {
        $this->db->query("
            UPDATE tedarikciler
            SET deleted_at=?, updated_at=?, synced_at=NULL
            WHERE id=? AND firma_id=? AND deleted_at IS NULL
        ", [$this->now(), $this->now(), $id, $this->firmaId]);
    }

    public function getAll(): array {
        return $this->db->fetchAll("
            SELECT a.*,
                   COUNT(k.id) AS kalem_sayisi,
                   COALESCE(SUM(k.miktar),0) AS toplam_adet,
                   (a.toplam_tutar - a.odenen_tutar) AS kalan_tutar
            FROM tedarikci_alimlari a
            LEFT JOIN tedarikci_alim_kalemleri k ON k.alim_id=a.id AND k.deleted_at IS NULL
            WHERE a.firma_id=? AND a.deleted_at IS NULL
            GROUP BY a.id
            ORDER BY DATE(a.alim_tarihi) DESC, a.id DESC
        ", [$this->firmaId]);
    }

    public function getById(int $id) {
        $alim = $this->db->fetchOne(
            "SELECT *, (toplam_tutar - odenen_tutar) AS kalan_tutar
             FROM tedarikci_alimlari
             WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$alim) return false;

        $alim['kalemler'] = $this->db->fetchAll("
            SELECT k.*, p.parca_adi, p.marka, p.stok_miktari
            FROM tedarikci_alim_kalemleri k
            JOIN parcalar p ON p.id=k.parca_id AND p.deleted_at IS NULL
            WHERE k.alim_id=? AND k.deleted_at IS NULL
            ORDER BY k.id ASC
        ", [$id]);

        $alim['odemeler'] = $this->db->fetchAll("
            SELECT *
            FROM tedarikci_odemeleri
            WHERE alim_id=? AND firma_id=? AND deleted_at IS NULL
            ORDER BY DATE(odeme_tarihi) DESC, id DESC
        ", [$id, $this->firmaId]);

        return $alim;
    }

    public function create(array $data): int {
        $tedarikciAdi = trim((string)($data['tedarikci_adi'] ?? ''));
        if ($tedarikciAdi === '') {
            throw new InvalidArgumentException('Tedarikci adi zorunludur.');
        }
        $kalemler = array_values(array_filter((array)($data['kalemler'] ?? []), fn($k) => !empty($k['parca_id'])));
        if (!$kalemler) {
            throw new InvalidArgumentException('En az bir urun secilmelidir.');
        }
        $paraBirimi = strtoupper(trim((string)($data['para_birimi'] ?? 'TRY')));
        if (!in_array($paraBirimi, ['TRY', 'USD'], true)) {
            $paraBirimi = 'TRY';
        }
        $usdKur = max(0, (float)($data['usd_kur'] ?? 0));
        if ($paraBirimi === 'USD' && $usdKur <= 0) {
            throw new InvalidArgumentException('USD alimi icin kur girilmelidir.');
        }

        $toplam = 0.0;
        foreach ($kalemler as $kalem) {
            $miktar = max(1, (int)($kalem['miktar'] ?? 1));
            $birim = max(0, (float)($kalem['birim_fiyat'] ?? 0));
            $toplam += $miktar * $birim;
        }

        $pesinOdeme = max(0, min($toplam, (float)($data['odenen_tutar'] ?? 0)));
        $durum = $toplam <= 0 || $pesinOdeme >= $toplam ? 'odendi' : ($pesinOdeme > 0 ? 'kismi' : 'odenmedi');
        $tarih = $data['alim_tarihi'] ?? date('Y-m-d');

        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $id = $this->db->execute("
                INSERT INTO tedarikci_alimlari
                    (firma_id, tedarikci_adi, fatura_no, alim_tarihi, para_birimi, usd_kur, toplam_tutar, odenen_tutar, odeme_durumu, notlar, uuid)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ", [
                $this->firmaId,
                $tedarikciAdi,
                $data['fatura_no'] ?? null,
                $tarih,
                $paraBirimi,
                $usdKur,
                $toplam,
                $pesinOdeme,
                $durum,
                $data['notlar'] ?? null,
                $this->uuid(),
            ]);

            $insertKalem = $pdo->prepare("
                INSERT INTO tedarikci_alim_kalemleri (alim_id, parca_id, miktar, birim_fiyat, uuid)
                VALUES (?,?,?,?,?)
            ");
            $stokArtir = $pdo->prepare("
                UPDATE parcalar
                SET stok_miktari=stok_miktari+?,
                    maliyet_usd=CASE WHEN ? > 0 THEN ? ELSE maliyet_usd END,
                    tedarikci=?,
                    updated_at=CURRENT_TIMESTAMP,
                    synced_at=NULL
                WHERE id=? AND firma_id=? AND deleted_at IS NULL
            ");
            foreach ($kalemler as $kalem) {
                $parcaId = (int)$kalem['parca_id'];
                $miktar = max(1, (int)($kalem['miktar'] ?? 1));
                $birim = max(0, (float)($kalem['birim_fiyat'] ?? 0));
                $maliyetUsd = $this->birimMaliyetUsd($birim, $paraBirimi, $usdKur);
                $insertKalem->execute([$id, $parcaId, $miktar, $birim, $this->uuid()]);
                $stokArtir->execute([$miktar, $maliyetUsd, $maliyetUsd, $tedarikciAdi, $parcaId, $this->firmaId]);
                if ($stokArtir->rowCount() !== 1) {
                    throw new InvalidArgumentException('Stok kaydi bulunamadi veya bu firmaya ait degil.');
                }
            }

            if ($pesinOdeme > 0) {
                $this->db->execute("
                    INSERT INTO tedarikci_odemeleri (firma_id, alim_id, tutar, para_birimi, usd_kur, odeme_yontemi, odeme_tarihi, notlar, uuid)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ", [
                    $this->firmaId,
                    $id,
                    $pesinOdeme,
                    $paraBirimi,
                    $usdKur,
                    $data['odeme_yontemi'] ?? 'nakit',
                    $tarih,
                    'Alim kaydi sirasinda odendi',
                    $this->uuid(),
                ]);
            }

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function odemeEkle(int $alimId, array $data): int {
        $alim = $this->requireAlim($alimId);
        $tutar = (float)($data['tutar'] ?? 0);
        if ($tutar < 0) {
            throw new InvalidArgumentException('Gecersiz odeme tutari.');
        }
        $id = $this->db->execute("
            INSERT INTO tedarikci_odemeleri (firma_id, alim_id, tutar, para_birimi, usd_kur, odeme_yontemi, odeme_tarihi, notlar, uuid)
            VALUES (?,?,?,?,?,?,?,?,?)
        ", [
            $this->firmaId,
            $alimId,
            $tutar,
            $alim['para_birimi'] ?? 'TRY',
            $alim['usd_kur'] ?? 0,
            $data['odeme_yontemi'] ?? 'nakit',
            $data['odeme_tarihi'] ?? date('Y-m-d'),
            $data['notlar'] ?? null,
            $this->uuid(),
        ]);
        $this->updateOdemeDurumu($alimId);
        return $id;
    }

    public function odemeSil(int $id): void {
        $row = $this->db->fetchOne(
            "SELECT alim_id FROM tedarikci_odemeleri WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$row) return;
        $this->db->query(
            "UPDATE tedarikci_odemeleri SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=?",
            [$id, $this->firmaId]
        );
        $this->updateOdemeDurumu((int)$row['alim_id']);
    }

    public function delete(int $id): void {
        $alim = $this->getById($id);
        if (!$alim) return;
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $stokDusSql = $this->db->isMysql()
                ? "UPDATE parcalar
                   SET stok_miktari=GREATEST(0, stok_miktari-?), updated_at=CURRENT_TIMESTAMP, synced_at=NULL
                   WHERE id=? AND firma_id=? AND deleted_at IS NULL"
                : "UPDATE parcalar
                   SET stok_miktari=MAX(0, stok_miktari-?), updated_at=CURRENT_TIMESTAMP, synced_at=NULL
                   WHERE id=? AND firma_id=? AND deleted_at IS NULL";
            $stokDus = $pdo->prepare("
                {$stokDusSql}
            ");
            foreach ($alim['kalemler'] as $kalem) {
                $stokDus->execute([(int)$kalem['miktar'], (int)$kalem['parca_id'], $this->firmaId]);
            }
            $this->db->query("UPDATE tedarikci_odemeleri SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE alim_id=? AND firma_id=?", [$id, $this->firmaId]);
            $this->db->query("UPDATE tedarikci_alim_kalemleri SET deleted_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE alim_id=?", [$id]);
            $this->db->query("UPDATE tedarikci_alimlari SET deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP, synced_at=NULL WHERE id=? AND firma_id=?", [$id, $this->firmaId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function updateOdemeDurumu(int $alimId): void {
        $toplam = (float)$this->db->fetchColumn(
            "SELECT toplam_tutar FROM tedarikci_alimlari WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$alimId, $this->firmaId]
        );
        $odenen = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(tutar),0) FROM tedarikci_odemeleri WHERE alim_id=? AND firma_id=? AND deleted_at IS NULL",
            [$alimId, $this->firmaId]
        );
        $durum = $toplam <= 0 || $odenen >= $toplam ? 'odendi' : ($odenen > 0 ? 'kismi' : 'odenmedi');
        $this->db->query(
            "UPDATE tedarikci_alimlari SET odenen_tutar=?, odeme_durumu=?, updated_at=?, synced_at=NULL WHERE id=? AND firma_id=?",
            [min($odenen, $toplam), $durum, $this->now(), $alimId, $this->firmaId]
        );
    }

    private function requireAlim(int $id): array {
        $row = $this->db->fetchOne(
            "SELECT id, para_birimi, usd_kur FROM tedarikci_alimlari WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if (!$row) {
            throw new InvalidArgumentException('Tedarikci alimi bulunamadi.');
        }
        return $row;
    }

    private function birimMaliyetUsd(float $birim, string $paraBirimi, float $usdKur): float {
        if ($birim <= 0) {
            return 0.0;
        }
        if ($paraBirimi === 'USD') {
            return round($birim, 4);
        }
        return $usdKur > 0 ? round($birim / $usdKur, 4) : 0.0;
    }
}
