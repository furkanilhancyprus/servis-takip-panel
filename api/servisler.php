<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Servis.php';

$s  = new Servis();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if ($id > 0) {
            $servis = $s->getById($id);
            if (!$servis) json_err('Servis bulunamadı', 404);
            json_ok($servis);
        }
        $filtre = [
            'musteri_id'    => $_GET['musteri_id']    ?? null,
            'servis_tipi'   => $_GET['servis_tipi']   ?? null,
            'odeme_durumu'  => $_GET['odeme_durumu']  ?? null,
            'baslangic'     => $_GET['baslangic']     ?? null,
            'bitis'         => $_GET['bitis']          ?? null,
            'search'        => $_GET['search']         ?? null,
            'limit'         => $_GET['limit']          ?? 50,
        ];
        json_ok($s->getAll(array_filter($filtre)));

    case 'POST':
        $data = get_input();
        if (empty($data['musteri_id']) || empty($data['servis_tipi'])) {
            json_err('Müşteri ve servis tipi zorunludur.');
        }
        $newId = $s->create($data);
        json_ok(['id' => $newId], 'Servis kaydedildi.');

    case 'PUT':
        if (!$id) json_err('ID gerekli.');
        $data = get_input();
        $s->update($id, $data);
        json_ok(['id' => $id], 'Servis güncellendi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $s->delete($id);
        json_ok(null, 'Servis silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
