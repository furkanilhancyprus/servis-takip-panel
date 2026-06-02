<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Parca.php';

$p  = new Parca();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if (isset($_GET['kritik'])) {
            json_ok($p->getKritikStoklar());
        }
        if ($id > 0) {
            $parca = $p->getById($id);
            if (!$parca) json_err('Parça bulunamadı', 404);
            json_ok($parca);
        }
        json_ok($p->getAll());

    case 'POST':
        $data = get_input();
        if (empty($data['parca_adi'])) json_err('Parça adı zorunludur.');
        $newId = $p->create($data);
        json_ok(['id' => $newId], 'Parça eklendi.');

    case 'PUT':
        if (!$id) json_err('ID gerekli.');
        $data = get_input();
        $p->update($id, $data);
        json_ok($p->getById($id), 'Parça güncellendi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $p->delete($id);
        json_ok(null, 'Parça silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
