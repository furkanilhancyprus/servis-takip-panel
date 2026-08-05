<?php
require_once __DIR__ . '/_base.php';

$db = Database::getInstance();

switch (method()) {
    case 'GET':
        $row = $db->fetchOne(
            "SELECT firma_adi, ad_soyad, email, telefon FROM kullanicilar WHERE id=? AND deleted_at IS NULL",
            [FIRMA_ID]
        );
        if (!$row) json_err('Hesap bulunamadi.', 404);
        json_ok($row);

    case 'POST':
        $data = get_input();
        $adSoyad = trim((string)($data['ad_soyad'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $mevcutSifre = (string)($data['mevcut_sifre'] ?? '');
        $yeniSifre = (string)($data['yeni_sifre'] ?? '');
        $yeniSifre2 = (string)($data['yeni_sifre2'] ?? '');

        if ($adSoyad === '') json_err('Ad soyad zorunludur.');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('Gecerli bir e-posta girin.');

        $mevcut = $db->fetchOne(
            "SELECT id, firma_adi, ad_soyad, email, sifre FROM kullanicilar WHERE id=? AND deleted_at IS NULL",
            [FIRMA_ID]
        );
        if (!$mevcut) json_err('Hesap bulunamadi.', 404);

        $emailDegisti = strcasecmp($email, (string)$mevcut['email']) !== 0;
        $sifreDegisti = $yeniSifre !== '' || $yeniSifre2 !== '';
        if (($emailDegisti || $sifreDegisti) && !password_verify($mevcutSifre, (string)$mevcut['sifre'])) {
            json_err('E-posta veya sifre degisikligi icin mevcut sifrenizi girin.');
        }

        if ($emailDegisti) {
            $var = $db->fetchColumn(
                "SELECT id FROM kullanicilar WHERE email=? AND id<>? AND deleted_at IS NULL",
                [$email, FIRMA_ID]
            );
            if ($var) json_err('Bu e-posta adresi baska bir hesapta kullaniliyor.');
        }

        if ($sifreDegisti) {
            if (strlen($yeniSifre) < 6) json_err('Yeni sifre en az 6 karakter olmali.');
            if ($yeniSifre !== $yeniSifre2) json_err('Yeni sifreler eslesmiyor.');
            $db->query(
                "UPDATE kullanicilar SET ad_soyad=?, email=?, sifre=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL",
                [$adSoyad, $email, password_hash($yeniSifre, PASSWORD_BCRYPT), FIRMA_ID]
            );
            $db->query("UPDATE remember_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE firma_id=? AND revoked_at IS NULL", [FIRMA_ID]);
        } else {
            $db->query(
                "UPDATE kullanicilar SET ad_soyad=?, email=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL",
                [$adSoyad, $email, FIRMA_ID]
            );
        }

        $_SESSION['ad_soyad'] = $adSoyad;
        $_SESSION['email'] = $email;

        json_ok([
            'firma_adi' => $mevcut['firma_adi'],
            'ad_soyad' => $adSoyad,
            'email' => $email,
        ], 'Hesap bilgileri guncellendi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
