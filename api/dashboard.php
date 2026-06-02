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
]);
