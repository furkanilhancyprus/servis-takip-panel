<?php
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/Cihaz.php';

class Musteri extends Model {

    public function getAll(string $search = ''): array {
        $params = [$this->firmaId];
        $where  = "WHERE m.firma_id = ? AND m.deleted_at IS NULL";

        $rows = $this->db->fetchAll("
            SELECT m.*,
                   COUNT(DISTINCT s.id) as toplam_servis,
                   MAX(s.tamamlanma_tarihi) as son_servis_tarihi,
                   pb.periyot_ay, pb.aktif as bakim_aktif,
                   pb.son_bakim_tarihi, pb.sonraki_bakim_tarihi, pb.hatirlatma_gun
            FROM musteriler m
            LEFT JOIN servisler s  ON m.id = s.musteri_id AND s.deleted_at IS NULL
            LEFT JOIN periyodik_bakimlar pb ON m.id = pb.musteri_id AND pb.deleted_at IS NULL
            $where
            GROUP BY m.id
            ORDER BY m.ad COLLATE NOCASE ASC, m.soyad COLLATE NOCASE ASC, m.created_at DESC
        ", $params);

        if ($search !== '') {
            $rows = array_values(array_filter($rows, fn($m) => $this->searchMatches([
                $m['ad'] ?? '',
                $m['soyad'] ?? '',
                trim(($m['ad'] ?? '') . ' ' . ($m['soyad'] ?? '')),
                $m['telefon'] ?? '',
                $m['adres'] ?? '',
            ], $search)));
        }

        usort($rows, fn($a, $b) => strcmp(
            $this->normalizeSearchText(trim(($a['ad'] ?? '') . ' ' . ($a['soyad'] ?? ''))),
            $this->normalizeSearchText(trim(($b['ad'] ?? '') . ' ' . ($b['soyad'] ?? '')))
        ));

        foreach ($rows as &$m) {
            $m['bakim_durumu'] = $this->calcBakimDurumu($m);
        }

        return $rows;
    }

    public function getById(int $id) {
        $musteri = $this->db->fetchOne("
            SELECT m.*, pb.aktif as bakim_aktif, pb.periyot_ay,
                   pb.son_bakim_tarihi, pb.sonraki_bakim_tarihi,
                   pb.hatirlatma_gun, pb.notlar as bakim_notlari
            FROM musteriler m
            LEFT JOIN periyodik_bakimlar pb ON m.id = pb.musteri_id
            WHERE m.id = ? AND m.firma_id = ? AND m.deleted_at IS NULL
        ", [$id, $this->firmaId]);

        if (!$musteri) return false;

        $musteri['servisler'] = $this->db->fetchAll("
            SELECT s.id, s.servis_tipi, s.durum, s.toplam_tutar,
                   s.created_at, s.tamamlanma_tarihi, s.notlar
            FROM servisler s
            WHERE s.musteri_id = ? AND s.firma_id = ? AND s.deleted_at IS NULL
            ORDER BY s.created_at DESC
        ", [$id, $this->firmaId]);

        foreach ($musteri['servisler'] as &$servis) {
            $servis['islemler'] = $this->db->fetchAll(
                "SELECT islem, tutar FROM servis_islemleri WHERE servis_id = ? AND deleted_at IS NULL",
                [$servis['id']]
            );
            $islemAdlari = array_values(array_filter(array_map(
                fn($i) => trim((string)($i['islem'] ?? '')),
                $servis['islemler']
            )));
            $servis['servis_ozeti'] = $islemAdlari
                ? implode(', ', array_slice($islemAdlari, 0, 4))
                : trim((string)($servis['notlar'] ?? ''));
        }

        // Satışlar + cihaz bilgisi + taksit özeti
        $musteri['satislar'] = $this->db->fetchAll("
            SELECT sa.id, sa.created_at, sa.satis_tarihi, sa.toplam_tutar, sa.odeme_turu,
                   sa.taksit_sayisi, sa.pesinat, sa.seri_no,
                   p.parca_adi as cihaz_adi, p.marka as cihaz_marka,
                   (SELECT COUNT(*) FROM taksitler t WHERE t.satis_id = sa.id AND t.deleted_at IS NULL AND t.taksit_no > 0) as toplam_taksit,
                   (SELECT COUNT(*) FROM taksitler t WHERE t.satis_id = sa.id AND t.deleted_at IS NULL AND t.taksit_no > 0 AND t.odendi = 1) as odenen_taksit,
                   (SELECT COALESCE(SUM(t.tutar),0) FROM taksitler t WHERE t.satis_id = sa.id AND t.deleted_at IS NULL AND t.odendi = 0 AND t.taksit_no > 0) as kalan_tutar
            FROM satislar sa
            LEFT JOIN parcalar p ON sa.cihaz_id = p.id AND p.deleted_at IS NULL
            WHERE sa.musteri_id = ? AND sa.firma_id = ? AND sa.deleted_at IS NULL
            ORDER BY sa.satis_tarihi DESC, sa.id DESC
        ", [$id, $this->firmaId]);

        foreach ($musteri['satislar'] as &$satis) {
            $satis['kalemler'] = $this->db->fetchAll(
                "SELECT urun_adi, miktar, birim_fiyat FROM satis_kalemleri WHERE satis_id = ? ORDER BY id ASC",
                [$satis['id']]
            );
            $kalemAdlari = array_values(array_filter(array_map(function ($k) {
                $ad = trim((string)($k['urun_adi'] ?? ''));
                if ($ad === '') return '';
                $miktar = (int)($k['miktar'] ?? 1);
                return $miktar > 1 ? "{$ad} x{$miktar}" : $ad;
            }, $satis['kalemler'])));
            $satis['satis_ozeti'] = $kalemAdlari
                ? implode(', ', array_slice($kalemAdlari, 0, 4))
                : trim((string)($satis['cihaz_marka'] ? $satis['cihaz_marka'] . ' ' : '') . (string)($satis['cihaz_adi'] ?? ''));

            $satis['taksitler'] = $this->db->fetchAll(
                "SELECT id, taksit_no, tutar, vade_tarihi, CAST(odendi AS INTEGER) AS odendi, odeme_tarihi
                 FROM taksitler WHERE satis_id = ? AND deleted_at IS NULL ORDER BY taksit_no ASC",
                [$satis['id']]
            );
            foreach ($satis['taksitler'] as &$taksit) {
                $taksit['odendi'] = (int)($taksit['odendi'] ?? 0);
            }
            unset($taksit);
        }

        $musteri['cihazlar'] = $this->db->fetchAll("
            SELECT mc.id, mc.satis_id, mc.seri_no, mc.kurulum_tarihi, mc.notlar,
                   c.cihaz_adi, c.marka, c.model,
                   s.satis_tarihi, s.toplam_tutar AS satis_tutari
            FROM musteri_cihazlari mc
            LEFT JOIN cihazlar c ON c.id = mc.cihaz_id AND c.deleted_at IS NULL
            LEFT JOIN satislar s ON s.id = mc.satis_id AND s.deleted_at IS NULL
            WHERE mc.musteri_id = ? AND mc.firma_id = ? AND mc.deleted_at IS NULL
            ORDER BY COALESCE(mc.kurulum_tarihi, mc.created_at) DESC
        ", [$id, $this->firmaId]);

        return $musteri;
    }

    public function create(array $data): int {
        $id = $this->db->execute("
            INSERT INTO musteriler (firma_id, ad, soyad, telefon, email, adres, notlar, lat, lng, uuid)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $this->firmaId,
            $data['ad'], $data['soyad'], $data['telefon'] ?? null,
            null, $data['adres'] ?? null, $data['notlar'] ?? null,
            isset($data['lat']) && $data['lat'] !== '' ? (float)$data['lat'] : null,
            isset($data['lng']) && $data['lng'] !== '' ? (float)$data['lng'] : null,
            $this->uuid(),
        ]);

        $periyot = $this->db->fetchColumn(
            "SELECT deger FROM ayarlar WHERE anahtar='varsayilan_bakim_periyodu' AND firma_id=?",
            [$this->firmaId]
        ) ?: 6;

        $this->db->execute(
            "INSERT INTO periyodik_bakimlar (musteri_id, periyot_ay, uuid) VALUES (?, ?, ?)",
            [$id, $periyot, $this->uuid()]
        );

        $mevcutCihaz = $data['mevcut_cihaz'] ?? null;
        if (is_array($mevcutCihaz) && !empty($mevcutCihaz['aktif'])) {
            (new Cihaz())->linkExistingToMusteri($id, $mevcutCihaz);
        }

        return $id;
    }

    public function update(int $id, array $data): bool {
        $this->db->query("
            UPDATE musteriler SET ad=?, soyad=?, telefon=?, email=?, adres=?, notlar=?, lat=?, lng=?, updated_at=?, synced_at=NULL
            WHERE id=? AND firma_id=? AND deleted_at IS NULL
        ", [
            $data['ad'], $data['soyad'], $data['telefon'] ?? null,
            null, $data['adres'] ?? null, $data['notlar'] ?? null,
            isset($data['lat']) && $data['lat'] !== '' ? (float)$data['lat'] : null,
            isset($data['lng']) && $data['lng'] !== '' ? (float)$data['lng'] : null,
            $this->now(), $id, $this->firmaId,
        ]);

        $mevcutCihaz = $data['mevcut_cihaz'] ?? null;
        if (is_array($mevcutCihaz) && !empty($mevcutCihaz['aktif'])) {
            (new Cihaz())->linkExistingToMusteri($id, $mevcutCihaz);
        }
        return true;
    }

    public function delete(int $id): array {
        $hasServis = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM servisler WHERE musteri_id=? AND firma_id=? AND deleted_at IS NULL",
            [$id, $this->firmaId]
        );
        if ($hasServis > 0) {
            return ['success' => false, 'message' => 'Bu müşterinin servis kayıtları var. Önce servisleri silin.'];
        }
        $this->db->query("UPDATE musteriler SET deleted_at=?, updated_at=?, synced_at=NULL WHERE id=? AND firma_id=? AND deleted_at IS NULL", [$this->now(), $this->now(), $id, $this->firmaId]);
        return ['success' => true];
    }

    public function getStats(): array {
        $fid = $this->firmaId;
        return [
            'toplam'   => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM musteriler WHERE firma_id=? AND deleted_at IS NULL", [$fid]
            ),
            'geciken'  => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM periyodik_bakimlar pb JOIN musteriler m ON m.id=pb.musteri_id
                 WHERE m.firma_id=? AND m.deleted_at IS NULL AND pb.deleted_at IS NULL AND pb.aktif=1
                   AND pb.sonraki_bakim_tarihi < date('now') AND pb.sonraki_bakim_tarihi IS NOT NULL",
                [$fid]
            ),
            'yaklasan' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM periyodik_bakimlar pb JOIN musteriler m ON m.id=pb.musteri_id
                 WHERE m.firma_id=? AND m.deleted_at IS NULL AND pb.deleted_at IS NULL AND pb.aktif=1
                   AND pb.sonraki_bakim_tarihi BETWEEN date('now') AND date('now','+30 days')",
                [$fid]
            ),
        ];
    }

    private function calcBakimDurumu(array $m): string {
        if (empty($m['sonraki_bakim_tarihi'])) return 'ayarsiz';
        $diff = (int) round((strtotime($m['sonraki_bakim_tarihi']) - time()) / 86400);
        $hatirlatma = (int)($m['hatirlatma_gun'] ?? 7);
        if ($diff < 0)            return 'gecikmis';
        if ($diff <= $hatirlatma) return 'yakin';
        return 'normal';
    }
}
