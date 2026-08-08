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
        $satisRows = $this->addMonthlySequence($satisRows);

        $servisRows = $this->getServisler($baslangic, $bitis, $usdKur);
        $servisRows = $this->addMonthlySequence($servisRows);
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
            SELECT DATE(t.vade_tarihi) AS tarih, s.id AS no, t.id AS taksit_id, t.taksit_no,
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
                   0 AS tahsilat,
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
            WHERE t.firma_id=? AND t.deleted_at IS NULL
              AND s.firma_id=? AND s.odeme_turu='taksitli'
              AND DATE(t.vade_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(t.vade_tarihi) ASC, s.id ASC, t.taksit_no ASC
        ", [$usdKur, $usdKur, $this->firmaId, $this->firmaId, $baslangic, $bitis]);

        $rows = $this->applyTaksitliTahsilatlar($rows, $baslangic, $bitis, $usdKur);

        return array_map(function ($r) {
            $taksitNo = (int)($r['taksit_no'] ?? 0);
            $r['tip'] = $taksitNo === 9999 ? 'Satis tahsilati' : ($taksitNo === 0 ? 'Pesinat' : $taksitNo . '. taksit');
            return $this->normalizeRow($r);
        }, $rows);
    }

    private function applyTaksitliTahsilatlar(array $rows, string $baslangic, string $bitis, float $usdKur): array {
        $payments = $this->getTaksitliTahsilatlar($baslangic, $bitis);
        if (!$payments) {
            return $rows;
        }

        $saleRows = [];
        $taksitRows = [];
        foreach ($rows as $idx => $row) {
            $saleId = (int)($row['no'] ?? 0);
            $taksitId = (int)($row['taksit_id'] ?? 0);
            $saleRows[$saleId][] = $idx;
            if ($taksitId > 0) {
                $taksitRows[$taksitId] = $idx;
            }
        }

        $assignedBySale = [];
        foreach ($payments as $payment) {
            $saleId = (int)$payment['satis_id'];
            $taksitId = (int)($payment['taksit_id'] ?? 0);
            $amount = (float)$payment['tutar'];
            if ($amount <= 0) {
                continue;
            }

            $targetIdx = null;
            if ($taksitId > 0 && isset($taksitRows[$taksitId])) {
                $targetIdx = $taksitRows[$taksitId];
            } elseif (!empty($saleRows[$saleId])) {
                $targetIdx = $this->selectCurrentInstallmentRow($rows, $saleRows[$saleId]);
            }

            if ($targetIdx !== null) {
                $rows[$targetIdx]['tahsilat'] = (float)($rows[$targetIdx]['tahsilat'] ?? 0) + $amount;
                $assignedBySale[$saleId] = ($assignedBySale[$saleId] ?? 0) + $amount;
            }
        }

        foreach ($this->getTaksitliTahsilatOnlyRows($baslangic, $bitis, $usdKur) as $extra) {
            $saleId = (int)$extra['no'];
            $remaining = (float)$extra['tahsilat'] - (float)($assignedBySale[$saleId] ?? 0);
            if ($remaining <= 0.01) {
                continue;
            }
            $extra['tahsilat'] = $remaining;
            $rows[] = $extra;
        }

        usort($rows, function ($a, $b) {
            $cmp = strcmp((string)$a['tarih'], (string)$b['tarih']);
            return $cmp !== 0 ? $cmp : ((int)$a['no'] <=> (int)$b['no']);
        });

        return $rows;
    }

    private function selectCurrentInstallmentRow(array $rows, array $indexes): int {
        foreach ($indexes as $idx) {
            if ((int)($rows[$idx]['taksit_no'] ?? 0) > 0) {
                return $idx;
            }
        }
        return (int)$indexes[0];
    }

    private function getTaksitliTahsilatlar(string $baslangic, string $bitis): array {
        return $this->db->fetchAll("
            SELECT th.kaynak_id AS satis_id, th.taksit_id, DATE(th.tahsilat_tarihi) AS tahsilat_tarihi,
                   th.id, th.tutar
            FROM tahsilatlar th
            JOIN satislar s ON s.id=th.kaynak_id AND s.deleted_at IS NULL
            WHERE th.firma_id=? AND th.deleted_at IS NULL AND th.kaynak_tip='satis'
              AND s.firma_id=? AND s.odeme_turu='taksitli'
              AND DATE(th.tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(th.tahsilat_tarihi) ASC, th.id ASC
        ", [$this->firmaId, $this->firmaId, $baslangic, $bitis]);
    }

    private function getTaksitliTahsilatOnlyRows(string $baslangic, string $bitis, float $usdKur): array {
        return $this->db->fetchAll("
            SELECT MIN(DATE(th.tahsilat_tarihi)) AS tarih, s.id AS no, 0 AS taksit_id, 9999 AS taksit_no,
                   m.ad || ' ' || m.soyad AS musteri_adi, m.telefon,
                   COALESCE(k.kalemler, NULLIF(TRIM(COALESCE(c.marka, '') || ' ' || COALESCE(c.cihaz_adi, '')), ''), 'Satis') AS aciklama,
                   0 AS ciro,
                   0 AS maliyet,
                   COALESCE(SUM(th.tutar), 0) AS tahsilat,
                   s.notlar
            FROM tahsilatlar th
            JOIN satislar s ON s.id=th.kaynak_id AND s.deleted_at IS NULL
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON c.id=s.cihaz_id AND c.deleted_at IS NULL
            LEFT JOIN (
                SELECT satis_id, GROUP_CONCAT(urun_adi, ', ') AS kalemler
                FROM satis_kalemleri
                WHERE deleted_at IS NULL
                GROUP BY satis_id
            ) k ON k.satis_id=s.id
            WHERE th.firma_id=? AND th.deleted_at IS NULL AND th.kaynak_tip='satis'
              AND s.firma_id=? AND s.odeme_turu='taksitli'
              AND DATE(th.tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
            GROUP BY s.id
            ORDER BY MIN(DATE(th.tahsilat_tarihi)) ASC, s.id ASC
        ", [$this->firmaId, $this->firmaId, $baslangic, $bitis]);
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
            'sira_no' => (int)($r['sira_no'] ?? 0),
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

    private function addMonthlySequence(array $rows): array {
        $counters = [];
        foreach ($rows as &$row) {
            $month = !empty($row['tarih']) ? date('Y-m', strtotime((string)$row['tarih'])) : '0000-00';
            $counters[$month] = ($counters[$month] ?? 0) + 1;
            $row['sira_no'] = $counters[$month];
        }
        unset($row);
        return $rows;
    }
}
