<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Satis.php';

$m  = new Satis();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if ($id > 0) {
            $s = $m->getById($id);
            if (!$s) json_err('Satış bulunamadı', 404);
            json_ok($s);
        }
        $filtre = [
            'search'       => $_GET['search']       ?? '',
            'musteri_id'   => $_GET['musteri_id']   ?? '',
            'odeme_durumu' => $_GET['odeme_durumu'] ?? '',
            'baslangic'    => $_GET['baslangic']    ?? '',
            'bitis'        => $_GET['bitis']        ?? '',
            'limit'        => $_GET['limit']        ?? '',
        ];
        json_ok($m->getAll(array_filter($filtre)));

    case 'POST':
        $data = get_input();
        if (empty($data['musteri_id'])) json_err('Müşteri seçilmedi.');
        $newId = $m->create($data);
        json_ok(['id' => $newId], 'Satış kaydedildi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $m->delete($id);
        json_ok(null, 'Satış silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
