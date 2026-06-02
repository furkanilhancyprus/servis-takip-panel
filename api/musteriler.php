<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Musteri.php';

$m = new Musteri();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        // İstatistik özeti
        if (isset($_GET['stats'])) {
            json_ok($m->getStats());
        }
        // Tek müşteri detayı (satış + servis + taksit dahil)
        if ($id > 0) {
            $musteri = $m->getById($id);
            if (!$musteri) json_err('Müşteri bulunamadı', 404);
            json_ok($musteri);
        }
        // Liste (arama destekli)
        $search = trim($_GET['search'] ?? '');
        json_ok($m->getAll($search));

    case 'POST':
        $data = get_input();
        if (empty($data['ad']) || empty($data['soyad'])) {
            json_err('Ad ve soyad zorunludur.');
        }
        $newId = $m->create($data);
        json_ok(['id' => $newId], 'Müşteri eklendi.');

    case 'PUT':
        if (!$id) json_err('ID gerekli.');
        $data = get_input();
        if (empty($data['ad']) || empty($data['soyad'])) {
            json_err('Ad ve soyad zorunludur.');
        }
        $m->update($id, $data);
        json_ok($m->getById($id), 'Müşteri güncellendi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $result = $m->delete($id);
        if (!$result['success']) json_err($result['message']);
        json_ok(null, 'Müşteri silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
