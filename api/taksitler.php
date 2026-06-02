<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Taksit.php';

$m  = new Taksit();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if (!empty($_GET['ozet'])) {
            json_ok($m->getOzet());
        }
        if (!empty($_GET['satis_id'])) {
            json_ok($m->getBySatisId((int)$_GET['satis_id']));
        }
        // Bekleyen taksitler
        json_ok($m->getBekleyenler());

    case 'POST':
        // Taksit öde
        $data = get_input();
        $taksitId = (int)($data['id'] ?? $id);
        if (!$taksitId) json_err('Taksit ID gerekli.');

        $sonuc = $m->odemeYap(
            $taksitId,
            $data['odeme_yontemi'] ?? 'nakit',
            $data['odeme_tarihi']  ?? null
        );
        if (!$sonuc) json_err('Taksit bulunamadı veya zaten ödendi.');
        json_ok(null, 'Taksit ödendi.');

    case 'DELETE':
        // Ödemeyi geri al
        if (!$id) json_err('ID gerekli.');
        $sonuc = $m->odemeGeriAl($id);
        if (!$sonuc) json_err('İşlem yapılamadı.');
        json_ok(null, 'Ödeme geri alındı.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
