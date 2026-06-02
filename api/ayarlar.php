<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Ayarlar.php';

$a = new Ayarlar();

switch (method()) {
    case 'GET':
        json_ok($a->getAll());

    case 'POST':
        $data = get_input();
        if (empty($data)) json_err('Veri bulunamadı.');
        $a->setMultiple($data);
        json_ok($a->getAll(), 'Ayarlar kaydedildi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
