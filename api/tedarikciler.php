<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Tedarikci.php';

$tedarikci = new Tedarikci();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    switch (method()) {
        case 'GET':
            if ($id > 0) {
                $row = $tedarikci->getById($id);
                if (!$row) json_err('Alim kaydi bulunamadi.', 404);
                json_ok($row);
            }
            json_ok($tedarikci->getAll());

        case 'POST':
            $data = get_input();
            if (!empty($_GET['odeme'])) {
                if (!$id) json_err('Alim ID gerekli.');
                $newId = $tedarikci->odemeEkle($id, $data);
                json_ok(['id' => $newId], 'Odeme kaydedildi.');
            }
            $newId = $tedarikci->create($data);
            json_ok(['id' => $newId], 'Tedarikci alimi kaydedildi.');

        case 'DELETE':
            if (!empty($_GET['odeme'])) {
                if (!$id) json_err('Odeme ID gerekli.');
                $tedarikci->odemeSil($id);
                json_ok(null, 'Odeme silindi.');
            }
            if (!$id) json_err('ID gerekli.');
            $tedarikci->delete($id);
            json_ok(null, 'Alim kaydi silindi.');

        default:
            json_err('Desteklenmeyen metod.', 405);
    }
} catch (InvalidArgumentException $e) {
    json_err($e->getMessage(), 422);
}
