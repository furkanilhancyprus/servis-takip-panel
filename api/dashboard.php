<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Musteri.php';
require_once ROOT . '/models/Servis.php';
require_once ROOT . '/models/Parca.php';
require_once ROOT . '/models/PeriyodikBakim.php';
require_once ROOT . '/models/Tahsilat.php';
require_once ROOT . '/models/Satis.php';

$musteri  = new Musteri();
$servis   = new Servis();
$parca    = new Parca();
$bakim    = new PeriyodikBakim();
$tahsilat = new Tahsilat();
$satis    = new Satis();

function dashboard_usd_try(): float {
    if (!empty($_SESSION['dashboard_usd_try_cache']['rate']) && !empty($_SESSION['dashboard_usd_try_cache']['until'])) {
        if ((int)$_SESSION['dashboard_usd_try_cache']['until'] > time()) {
            return (float)$_SESSION['dashboard_usd_try_cache']['rate'];
        }
    }

    $fetch = function(string $url): ?string {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2,
                'header' => "User-Agent: ServisTakipPanel/1.0\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        return $raw === false ? null : $raw;
    };

    $rate = 0.0;
    $xmlText = $fetch('https://www.tcmb.gov.tr/kurlar/today.xml');
    if ($xmlText && preg_match('/<Currency[^>]+CurrencyCode="USD"[\s\S]*?<ForexSelling>([^<]+)<\/ForexSelling>/u', $xmlText, $m)) {
        $rate = (float)str_replace(',', '.', trim($m[1]));
    }

    if ($rate <= 0) {
        $json = $fetch('https://open.er-api.com/v6/latest/USD');
        $data = $json ? json_decode($json, true) : null;
        $rate = (float)($data['rates']['TRY'] ?? 0);
    }

    if ($rate > 0) {
        $_SESSION['dashboard_usd_try_cache'] = [
            'rate' => $rate,
            'until' => time() + 21600,
        ];
    }

    return $rate;
}

$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$seciliAy = $_GET['ay'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $seciliAy)) {
    $seciliAy = date('Y-m');
}
$ayBaslangic = $seciliAy . '-01';
$ayBitis = date('Y-m-t', strtotime($ayBaslangic));

$gecikenler   = $bakim->getGecikenler();
$yaklasanlar  = $bakim->getYaklasanlar(30);
$bugunServis  = $servis->getBugun();
$buAyYapilan  = $servis->getBuAyYapilan();
$kritikStok   = $parca->getKritikStoklar();
$musteriStats = $musteri->getStats();
$tahsilOzeti  = $tahsilat->getTahsilOzeti();
$usdKur       = dashboard_usd_try();

// Bu ay planlanmış bakım sayısı (gecikmiş olanlar dahil)
$_db = Database::getInstance();
$buAyPlanlanan = (int) $_db->fetchColumn(
    "SELECT COUNT(*) FROM periyodik_bakimlar pb
     JOIN musteriler m ON m.id = pb.musteri_id
     WHERE m.firma_id = ? AND pb.aktif = 1
       AND pb.sonraki_bakim_tarihi <= date('now', 'start of month', '+1 month', '-1 day')
       AND pb.sonraki_bakim_tarihi IS NOT NULL",
    [$_SESSION['firma_id']]
);

// Aylık ciro: servis + satış birleşik
$servisCiro = $servis->getAylikCiro($yil);
$satisCiro  = $satis->getAylikCiro($yil);
$aylikCiro  = [];
for ($i = 0; $i < 12; $i++) {
    $aylikCiro[] = round(($servisCiro[$i] ?? 0) + ($satisCiro[$i] ?? 0), 2);
}

// Aylık tahsilat
$aylikTahsilat = $tahsilat->getAylikTahsilat($yil);

$aySatisAdet = $satis->getGercekAdetByDateRange($ayBaslangic, $ayBitis);

$ayServis = $_db->fetchOne("
    SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
    FROM servisler
    WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);

$satisMaliyet = $satis->getMaliyetByDateRange($ayBaslangic, $ayBitis, $usdKur);

$servisMaliyet = (float)$_db->fetchColumn("
    SELECT COALESCE(SUM(
        sp.miktar
        * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
        * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END
    ),0)
    FROM servis_parcalari sp
    JOIN servisler s ON s.id=sp.servis_id AND s.deleted_at IS NULL
    LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
    WHERE s.firma_id=? AND sp.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
", [$usdKur, $_SESSION['firma_id'], $ayBaslangic, $ayBitis]);

$gunSayisi = (int)date('t', strtotime($ayBaslangic));
$gunlukMap = [];
for ($gun = 1; $gun <= $gunSayisi; $gun++) {
    $tarih = $seciliAy . '-' . str_pad((string)$gun, 2, '0', STR_PAD_LEFT);
    $gunlukMap[$tarih] = [
        'gun' => $gun,
        'tarih' => $tarih,
        'satis_adet' => 0,
        'servis_adet' => 0,
        'ciro' => 0.0,
    ];
}

$gunlukSatisAdet = $_db->fetchAll("
    SELECT DATE(satis_tarihi) AS tarih, COUNT(*) AS adet
    FROM satislar
    WHERE firma_id=? AND deleted_at IS NULL
      AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
    GROUP BY DATE(satis_tarihi)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);
foreach ($gunlukSatisAdet as $row) {
    $tarih = $row['tarih'];
    if (!isset($gunlukMap[$tarih])) continue;
    $gunlukMap[$tarih]['satis_adet'] = (int)$row['adet'];
}

$gunlukSatisCiro = $_db->fetchAll("
    SELECT tarih, COALESCE(SUM(ciro),0) AS ciro
    FROM (
        SELECT DATE(satis_tarihi) AS tarih, COALESCE(SUM(toplam_tutar),0) AS ciro
        FROM satislar
        WHERE firma_id=? AND deleted_at IS NULL AND odeme_turu <> 'taksitli'
          AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
        GROUP BY DATE(satis_tarihi)
        UNION ALL
        SELECT DATE(t.vade_tarihi) AS tarih, COALESCE(SUM(t.tutar),0) AS ciro
        FROM taksitler t
        JOIN satislar s ON s.id=t.satis_id AND s.deleted_at IS NULL
        WHERE s.firma_id=? AND t.firma_id=? AND t.deleted_at IS NULL
          AND s.odeme_turu='taksitli'
          AND DATE(t.vade_tarihi) BETWEEN DATE(?) AND DATE(?)
        GROUP BY DATE(t.vade_tarihi)
    )
    GROUP BY tarih
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis, $_SESSION['firma_id'], $_SESSION['firma_id'], $ayBaslangic, $ayBitis]);
foreach ($gunlukSatisCiro as $row) {
    $tarih = $row['tarih'];
    if (!isset($gunlukMap[$tarih])) continue;
    $gunlukMap[$tarih]['ciro'] += (float)$row['ciro'];
}

$gunlukServis = $_db->fetchAll("
    SELECT DATE(tamamlanma_tarihi) AS tarih, COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
    FROM servisler
    WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
    GROUP BY DATE(tamamlanma_tarihi)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);
foreach ($gunlukServis as $row) {
    $tarih = $row['tarih'];
    if (!isset($gunlukMap[$tarih])) continue;
    $gunlukMap[$tarih]['servis_adet'] = (int)$row['adet'];
    $gunlukMap[$tarih]['ciro'] += (float)$row['ciro'];
}

$aySatisCiro = $satis->getCiroByDateRange($ayBaslangic, $ayBitis);
$aySatisHacmi = $satis->getSatisHacmiByDateRange($ayBaslangic, $ayBitis);
$ayServisCiro = (float)($ayServis['ciro'] ?? 0);
$ayTahakkukCiro = $aySatisCiro + $ayServisCiro;
$ayIslemHacmi = $aySatisHacmi + $ayServisCiro;
$ayToplamCiro = $ayTahakkukCiro;
$ayToplamMaliyet = $satisMaliyet + $servisMaliyet;

json_ok([
    'toplamMusteri'    => $musteriStats['toplam'],
    'gecikenBakim'     => count($gecikenler),
    'yaklasanBakim'    => count($yaklasanlar),
    'bugunServis'      => count($bugunServis),
    'buAyYapilan'      => count($buAyYapilan),
    'buAyCiro'         => $ayTahakkukCiro,
    'buAyTahakkukCiro' => $ayTahakkukCiro,
    'buAySatisHacmi'   => $aySatisHacmi,
    'buAyIslemHacmi'   => $ayIslemHacmi,
    'kritikStok'       => count($kritikStok),
    'stokDegeri'       => $parca->getTotalValue(),
    'buAyPlanlanan'    => $buAyPlanlanan,
    'buAyTahsilat'     => $tahsilOzeti['buay_tahsilat'],
    'toplamBekleyen'   => $tahsilOzeti['toplam_bekleyen'],
    'gecikenler'       => array_slice($gecikenler, 0, 8),
    'yaklasanlar'      => array_slice($yaklasanlar, 0, 8),
    'sonServisler'     => $servis->getAll(['limit' => 8]),
    'haftalikCiro'     => $servis->getHaftalikCiro(),
    'aylikCiro'        => $aylikCiro,
    'aylikTahsilat'    => $aylikTahsilat,
    'usd_try'          => $usdKur,
    'seciliAy'         => $seciliAy,
    'ayOzeti'          => [
        'baslangic' => $ayBaslangic,
        'bitis' => $ayBitis,
        'satis_adet' => $aySatisAdet,
        'servis_adet' => (int)($ayServis['adet'] ?? 0),
        'satis_ciro' => $aySatisCiro,
        'satis_hacmi' => $aySatisHacmi,
        'servis_ciro' => $ayServisCiro,
        'tahakkuk_ciro' => $ayTahakkukCiro,
        'islem_hacmi' => $ayIslemHacmi,
        'toplam_ciro' => $ayTahakkukCiro,
        'toplam_maliyet' => $ayToplamMaliyet,
        'net_kar' => $ayTahakkukCiro - $ayToplamMaliyet,
        'usd_try' => $usdKur,
    ],
    'gunlukAyCiro'     => array_values($gunlukMap),
]);
