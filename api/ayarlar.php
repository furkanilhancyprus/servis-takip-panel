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
        if (array_key_exists('firma_adi', $data)) {
            $firmaAdi = trim((string)$data['firma_adi']);
            if ($firmaAdi !== '') {
                Database::getInstance()->query(
                    "UPDATE kullanicilar SET firma_adi=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL",
                    [$firmaAdi, FIRMA_ID]
                );
                $_SESSION['firma_adi'] = $firmaAdi;
            }
        }
        json_ok($a->getAll(), 'Ayarlar kaydedildi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
