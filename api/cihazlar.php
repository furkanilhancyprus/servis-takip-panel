<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Cihaz.php';

$m  = new Cihaz();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if (!empty($_GET['musteri_id'])) {
            json_ok($m->getMusteriCihazlari((int)$_GET['musteri_id']));
        }
        if ($id > 0) {
            $c = $m->getById($id);
            if (!$c) json_err('Bulunamadı', 404);
            json_ok($c);
        }
        json_ok($m->getAll());

    case 'POST':
        $data = get_input();
        if (empty($data['cihaz_adi'])) json_err('Cihaz adı zorunludur.');
        $newId = $m->create($data);
        json_ok(['id' => $newId], 'Cihaz eklendi.');

    case 'PUT':
        if (!$id) json_err('ID gerekli.');
        $data = get_input();
        if (empty($data['cihaz_adi'])) json_err('Cihaz adı zorunludur.');
        $m->update($id, $data);
        json_ok($m->getById($id), 'Cihaz güncellendi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $m->delete($id);
        json_ok(null, 'Cihaz silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
