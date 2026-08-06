<?php
require_once __DIR__ . '/Model.php';

class FinansRapor extends Model {
    public function getDetay(string $baslangic, string $bitis, float $usdKur = 0): array {
        $satisRows = array_merge(
            $this->getPesinSatislar($baslangic, $bitis, $usdKur),
            $this->getTaksitliSatislar($baslangic, $bitis, $usdKur)
        );
        usort($satisRows, function ($a, $b) {
            $cmp = strcmp((string)$a['tarih'], (string)$b['tarih']);
            return $cmp !== 0 ? $cmp : ((int)$a['no'] <=> (int)$b['no']);
        });

        $servisRows = $this->getServisler($baslangic, $bitis, $usdKur);
        $totals = [
            'satis_ciro' => 0.0, 'satis_maliyet' => 0.0, 'satis_tahsilat' => 0.0,
            'servis_ciro' => 0.0, 'servis_maliyet' => 0.0, 'servis_tahsilat' => 0.0,
        ];

        foreach ($satisRows as $r) {
            $totals['satis_ciro'] += (float)$r['ciro'];
            $totals['satis_maliyet'] += (float)$r['maliyet'];
            $totals['satis_tahsilat'] += (float)$r['tahsilat'];
        }
        foreach ($servisRows as $r) {
            $totals['servis_ciro'] += (float)$r['ciro'];
            $totals['servis_maliyet'] += (float)$r['maliyet'];
            $totals['servis_tahsilat'] += (float)$r['tahsilat'];
        }

        $totals['satis_kar'] = $totals['satis_ciro'] - $totals['satis_maliyet'];
        $totals['servis_kar'] = $totals['servis_ciro'] - $totals['servis_maliyet'];
        $totals['toplam_ciro'] = $totals['satis_ciro'] + $totals['servis_ciro'];
        $totals['toplam_maliyet'] = $totals['satis_maliyet'] + $totals['servis_maliyet'];
        $totals['toplam_kar'] = $totals['toplam_ciro'] - $totals['toplam_maliyet'];
        $totals['toplam_tahsilat'] = $totals['satis_tahsilat'] + $totals['servis_tahsilat'];

        return [
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'usd_try' => $usdKur,
            'satislar' => $satisRows,
            'servisler' => $servisRows,
            'toplamlar' => $totals,
        ];
    }

    private function getPesinSatislar(string $baslangic, string $bitis, float $usdKur): array {
        $rows = $this->db->fetchAll("
            SELECT DATE(s.satis_tarihi) AS tarih, s.id AS no,
                   m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   COALESCE(k.kalemler, NULLIF(TRIM(COALESCE(c.marka, '') || ' ' || COALESCE(c.cihaz_adi, '')), ''), 'Satis') AS aciklama,
                   CASE WHEN s.odeme_turu='pesin' THEN 'Pesin satis' ELSE 'Satis' END AS tip,
                   s.toplam_tutar AS ciro,
                   CASE WHEN COALESCE(lc.line_cost, 0) > 0 THEN lc.line_cost ELSE COALESCE(dc.device_cost, 0) END AS maliyet,
                   COALESCE(th.tahsilat, 0) AS tahsilat,
                   s.notlar
            FROM satislar s
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON c.id=s.cihaz_id AND c.deleted_at IS NULL
            LEFT JOIN (
                SELECT satis_id, GROUP_CONCAT(urun_adi, ', ') AS kalemler
                FROM satis_kalemleri
                WHERE deleted_at IS NULL
                GROUP BY satis_id
            ) k ON k.satis_id=s.id
            LEFT JOIN (
                SELECT sk.satis_id,
                       COALESCE(SUM(sk.miktar * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE ? END),0) AS line_cost
                FROM satis_kalemleri sk
                LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
                WHERE sk.deleted_at IS NULL
                GROUP BY sk.satis_id
            ) lc ON lc.satis_id=s.id
            LEFT JOIN (
                SELECT c2.id AS cihaz_id, COALESCE(p2.maliyet_usd, 0) * ? AS device_cost
                FROM cihazlar c2
                LEFT JOIN parcalar p2 ON p2.id=c2.parca_id AND p2.deleted_at IS NULL
                WHERE c2.deleted_at IS NULL
            ) dc ON dc.cihaz_id=s.cihaz_id
            LEFT JOIN (
                SELECT kaynak_id, COALESCE(SUM(tutar),0) AS tahsilat
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='satis'
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY kaynak_id
            ) th ON th.kaynak_id=s.id
            WHERE s.firma_id=? AND s.deleted_at IS NULL
              AND s.odeme_turu <> 'taksitli'
              AND DATE(s.satis_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(s.satis_tarihi) ASC, s.id ASC
        ", [$usdKur, $usdKur, $this->firmaId, $baslangic, $bitis, $this->firmaId, $baslangic, $bitis]);

        return array_map(fn($r) => $this->normalizeRow($r), $rows);
    }

    private function getTaksitliSatislar(string $baslangic, string $bitis, float $usdKur): array {
        $rows = $this->db->fetchAll("
            SELECT DATE(t.vade_tarihi) AS tarih, s.id AS no, t.taksit_no,
                   m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   COALESCE(k.kalemler, NULLIF(TRIM(COALESCE(c.marka, '') || ' ' || COALESCE(c.cihaz_adi, '')), ''), 'Satis') AS aciklama,
                   t.tutar AS ciro,
                   CASE
                       WHEN DATE(t.vade_tarihi) = (
                           SELECT MIN(DATE(t2.vade_tarihi))
                           FROM taksitler t2
                           WHERE t2.satis_id=s.id AND t2.firma_id=s.firma_id AND t2.deleted_at IS NULL
                       )
                       THEN CASE WHEN COALESCE(lc.line_cost, 0) > 0 THEN lc.line_cost ELSE COALESCE(dc.device_cost, 0) END
                       ELSE 0
                   END AS maliyet,
                   COALESCE(th.tahsilat, 0) AS tahsilat,
                   s.notlar
            FROM taksitler t
            JOIN satislar s ON s.id=t.satis_id AND s.deleted_at IS NULL
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON c.id=s.cihaz_id AND c.deleted_at IS NULL
            LEFT JOIN (
                SELECT satis_id, GROUP_CONCAT(urun_adi, ', ') AS kalemler
                FROM satis_kalemleri
                WHERE deleted_at IS NULL
                GROUP BY satis_id
            ) k ON k.satis_id=s.id
            LEFT JOIN (
                SELECT sk.satis_id,
                       COALESCE(SUM(sk.miktar * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE ? END),0) AS line_cost
                FROM satis_kalemleri sk
                LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
                WHERE sk.deleted_at IS NULL
                GROUP BY sk.satis_id
            ) lc ON lc.satis_id=s.id
            LEFT JOIN (
                SELECT c2.id AS cihaz_id, COALESCE(p2.maliyet_usd, 0) * ? AS device_cost
                FROM cihazlar c2
                LEFT JOIN parcalar p2 ON p2.id=c2.parca_id AND p2.deleted_at IS NULL
                WHERE c2.deleted_at IS NULL
            ) dc ON dc.cihaz_id=s.cihaz_id
            LEFT JOIN (
                SELECT taksit_id, COALESCE(SUM(tutar),0) AS tahsilat
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='satis'
                  AND taksit_id IS NOT NULL
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY taksit_id
            ) th ON th.taksit_id=t.id
            WHERE t.firma_id=? AND t.deleted_at IS NULL
              AND s.firma_id=? AND s.odeme_turu='taksitli'
              AND DATE(t.vade_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(t.vade_tarihi) ASC, s.id ASC, t.taksit_no ASC
        ", [$usdKur, $usdKur, $this->firmaId, $baslangic, $bitis, $this->firmaId, $this->firmaId, $baslangic, $bitis]);

        return array_map(function ($r) {
            $r['tip'] = ((int)($r['taksit_no'] ?? 0)) === 0 ? 'Pesinat' : ((int)$r['taksit_no']) . '. taksit';
            return $this->normalizeRow($r);
        }, $rows);
    }

    private function getServisler(string $baslangic, string $bitis, float $usdKur): array {
        $rows = $this->db->fetchAll("
            SELECT DATE(s.tamamlanma_tarihi) AS tarih, s.id AS no,
                   m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   COALESCE(i.islemler, CASE WHEN s.servis_tipi='ariza' THEN 'Ariza' ELSE 'Periyodik bakim' END) AS aciklama,
                   CASE WHEN s.servis_tipi='ariza' THEN 'Ariza' ELSE 'Periyodik bakim' END AS tip,
                   s.toplam_tutar AS ciro,
                   COALESCE(pc.maliyet, 0) AS maliyet,
                   COALESCE(th.tahsilat, 0) AS tahsilat,
                   s.notlar
            FROM servisler s
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN (
                SELECT servis_id, GROUP_CONCAT(islem, ', ') AS islemler
                FROM servis_islemleri
                WHERE deleted_at IS NULL
                GROUP BY servis_id
            ) i ON i.servis_id=s.id
            LEFT JOIN (
                SELECT sp.servis_id,
                       COALESCE(SUM(sp.miktar * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END),0) AS maliyet
                FROM servis_parcalari sp
                LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
                WHERE sp.deleted_at IS NULL
                GROUP BY sp.servis_id
            ) pc ON pc.servis_id=s.id
            LEFT JOIN (
                SELECT kaynak_id, COALESCE(SUM(tutar),0) AS tahsilat
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='servis'
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY kaynak_id
            ) th ON th.kaynak_id=s.id
            WHERE s.firma_id=? AND s.deleted_at IS NULL
              AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(s.tamamlanma_tarihi) ASC, s.id ASC
        ", [$usdKur, $this->firmaId, $baslangic, $bitis, $this->firmaId, $baslangic, $bitis]);

        return array_map(fn($r) => $this->normalizeRow($r), $rows);
    }

    private function normalizeRow(array $r): array {
        $ciro = (float)($r['ciro'] ?? 0);
        $maliyet = (float)($r['maliyet'] ?? 0);
        return [
            'tarih' => $r['tarih'] ?? null,
            'no' => (int)($r['no'] ?? 0),
            'musteri_adi' => $r['musteri_adi'] ?? '-',
            'telefon' => $r['telefon'] ?? '-',
            'aciklama' => $r['aciklama'] ?? '-',
            'tip' => $r['tip'] ?? '-',
            'ciro' => $ciro,
            'maliyet' => $maliyet,
            'net_kar' => $ciro - $maliyet,
            'tahsilat' => (float)($r['tahsilat'] ?? 0),
            'notlar' => $r['notlar'] ?? '',
        ];
    }
}
