<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/PeriyodikBakim.php';

$b          = new PeriyodikBakim();
$musteriId  = isset($_GET['musteri_id']) ? (int)$_GET['musteri_id'] : 0;

switch (method()) {
    case 'GET':
        if (isset($_GET['liste'])) {
            json_ok($b->getTumListe());
        }
        if (isset($_GET['gecikenler'])) {
            json_ok($b->getGecikenler());
        }
        if (isset($_GET['yaklasanlar'])) {
            $gun = isset($_GET['gun']) ? (int)$_GET['gun'] : 30;
            json_ok($b->getYaklasanlar($gun));
        }
        if ($musteriId > 0) {
            $bakim = $b->getByMusteriId($musteriId);
            json_ok($bakim ?: (object)[]);
        }
        json_ok($b->getTumListe());

    case 'POST':
        $data = get_input();
        if (isset($data['tamamlandi']) && $musteriId > 0) {
            if (!$b->tamamlandi($musteriId)) json_err('Bakim kaydi bulunamadi.');
            json_ok(null, 'Bakım tamamlandı olarak işaretlendi.');
        }
        if (!$musteriId) json_err('musteri_id gerekli.');
        $b->update($musteriId, $data);
        json_ok($b->getByMusteriId($musteriId), 'Bakım ayarları güncellendi.');

    case 'PUT':
        if (!$musteriId) json_err('musteri_id gerekli.');
        $data = get_input();
        $b->update($musteriId, $data);
        json_ok($b->getByMusteriId($musteriId), 'Bakım ayarları güncellendi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
