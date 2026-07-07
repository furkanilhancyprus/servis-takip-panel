<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Tahsilat.php';
require_once ROOT . '/models/Taksit.php';

$t   = new Tahsilat();
$tk  = new Taksit();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if (isset($_GET['ozet'])) {
            $tahOzet = $t->getTahsilOzeti();
            $takOzet = $tk->getOzet();
            json_ok(array_merge($tahOzet, $takOzet));
        }
        if (isset($_GET['odenmemisler'])) {
            json_ok($t->getOdenmemisler());
        }
        if (isset($_GET['taksitler'])) {
            json_ok($tk->getBekleyenler());
        }
        $filtre = array_filter([
            'musteri_id'  => $_GET['musteri_id'] ?? null,
            'kaynak_tip'  => $_GET['kaynak_tip'] ?? null,
            'baslangic'   => $_GET['baslangic']  ?? null,
            'bitis'       => $_GET['bitis']       ?? null,
            'limit'       => $_GET['limit']       ?? 100,
        ]);
        json_ok($t->getAll($filtre));

    case 'POST':
        $data = get_input();
        // Taksit ödemesi mi?
        if (!empty($data['taksit_id'])) {
            $sonuc = $tk->odemeYap(
                (int)$data['taksit_id'],
                $data['odeme_yontemi'] ?? 'nakit',
                $data['odeme_tarihi']  ?? null,
                isset($data['tutar']) ? (float)$data['tutar'] : null
            );
            if (!$sonuc) json_err('Taksit bulunamadı.');
            json_ok(null, 'Taksit ödendi.');
        }
        // Normal tahsilat
        if (empty($data['musteri_id']) || empty($data['kaynak_tip']) || empty($data['kaynak_id']) || !isset($data['tutar']) || $data['tutar'] === '') {
            json_err('Zorunlu alanlar eksik.');
        }
        if ((float)$data['tutar'] < 0 || ($data['kaynak_tip'] !== 'servis' && (float)$data['tutar'] <= 0)) json_err('Geçerli bir tutar girin.');
        $newId = $t->create($data);
        json_ok(['id' => $newId], 'Tahsilat kaydedildi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        // Taksit iptali mi?
        if (!empty($_GET['taksit'])) {
            $sonuc = $tk->odemeGeriAl($id);
            if (!$sonuc) json_err('İşlem yapılamadı.');
            json_ok(null, 'Ödeme geri alındı.');
        }
        $t->delete($id);
        json_ok(null, 'Tahsilat silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
