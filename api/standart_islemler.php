<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/StandartIslem.php';

$si = new StandartIslem();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        json_ok($si->getAll());

    case 'POST':
        $data = get_input();
        if (empty($data['islem_adi'])) json_err('İşlem adı zorunludur.');
        $newId = $si->create($data['islem_adi'], (float)($data['varsayilan_fiyat'] ?? 0));
        if (!empty($data['parcalar']) && is_array($data['parcalar'])) {
            $si->setParcalar($newId, $data['parcalar']);
        }
        json_ok(['id' => $newId], 'İşlem eklendi.');

    case 'PUT':
        if (!$id) json_err('ID gerekli.');
        $data = get_input();
        if (empty($data['islem_adi'])) json_err('İşlem adı zorunludur.');
        $si->update($id, $data['islem_adi'], (float)($data['varsayilan_fiyat'] ?? 0));
        if (isset($data['parcalar']) && is_array($data['parcalar'])) {
            $si->setParcalar($id, $data['parcalar']);
        }
        json_ok(null, 'İşlem güncellendi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $si->delete($id);
        json_ok(null, 'İşlem silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
