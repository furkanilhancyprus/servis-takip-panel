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
$buAySatis    = $satis->getBuAyToplam();

$buAyCiro     = array_sum(array_column($buAyYapilan, 'toplam_tutar')) + $buAySatis;

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

$aySatis = $_db->fetchOne("
    SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
    FROM satislar
    WHERE firma_id=? AND deleted_at IS NULL AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);

$ayServis = $_db->fetchOne("
    SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
    FROM servisler
    WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);

$satisMaliyet = (float)$_db->fetchColumn("
    SELECT COALESCE(SUM(
        sk.miktar
        * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
        * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE 0 END
    ),0)
    FROM satis_kalemleri sk
    JOIN satislar s ON s.id=sk.satis_id AND s.deleted_at IS NULL
    LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
    WHERE s.firma_id=? AND sk.deleted_at IS NULL AND DATE(s.satis_tarihi) BETWEEN DATE(?) AND DATE(?)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);

$servisMaliyet = (float)$_db->fetchColumn("
    SELECT COALESCE(SUM(
        sp.miktar
        * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
        * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE 0 END
    ),0)
    FROM servis_parcalari sp
    JOIN servisler s ON s.id=sp.servis_id AND s.deleted_at IS NULL
    LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
    WHERE s.firma_id=? AND sp.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);

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

$gunlukSatis = $_db->fetchAll("
    SELECT DATE(satis_tarihi) AS tarih, COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
    FROM satislar
    WHERE firma_id=? AND deleted_at IS NULL AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
    GROUP BY DATE(satis_tarihi)
", [$_SESSION['firma_id'], $ayBaslangic, $ayBitis]);
foreach ($gunlukSatis as $row) {
    $tarih = $row['tarih'];
    if (!isset($gunlukMap[$tarih])) continue;
    $gunlukMap[$tarih]['satis_adet'] = (int)$row['adet'];
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

$aySatisCiro = (float)($aySatis['ciro'] ?? 0);
$ayServisCiro = (float)($ayServis['ciro'] ?? 0);
$ayToplamCiro = $aySatisCiro + $ayServisCiro;
$ayToplamMaliyet = $satisMaliyet + $servisMaliyet;

json_ok([
    'toplamMusteri'    => $musteriStats['toplam'],
    'gecikenBakim'     => count($gecikenler),
    'yaklasanBakim'    => count($yaklasanlar),
    'bugunServis'      => count($bugunServis),
    'buAyYapilan'      => count($buAyYapilan),
    'buAyCiro'         => $buAyCiro,
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
    'seciliAy'         => $seciliAy,
    'ayOzeti'          => [
        'baslangic' => $ayBaslangic,
        'bitis' => $ayBitis,
        'satis_adet' => (int)($aySatis['adet'] ?? 0),
        'servis_adet' => (int)($ayServis['adet'] ?? 0),
        'satis_ciro' => $aySatisCiro,
        'servis_ciro' => $ayServisCiro,
        'toplam_ciro' => $ayToplamCiro,
        'toplam_maliyet' => $ayToplamMaliyet,
        'net_kar' => $ayToplamCiro - $ayToplamMaliyet,
    ],
    'gunlukAyCiro'     => array_values($gunlukMap),
]);
