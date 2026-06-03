<?php
if (!getenv('STP_DATA_DIR')) {
    header('Location: landing.php');
    exit;
}

session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';

$db = Database::getInstance();
$localOnly = getenv('STP_LOCAL_ONLY') === '1';
$mevcut = $db->fetchOne("SELECT id FROM kullanicilar LIMIT 1");
if ($mevcut) {
    header('Location: giris.php');
    exit;
}

$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firmaAdi = trim($_POST['firma_adi'] ?? '');
    $adSoyad = trim($_POST['ad_soyad'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $sifre = $_POST['sifre'] ?? '';
    $sifre2 = $_POST['sifre2'] ?? '';

    if (!$firmaAdi || !$adSoyad || !$email || !$sifre) {
        $hata = 'Tum zorunlu alanlari doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Gecerli bir e-posta adresi girin.';
    } elseif (strlen($sifre) < 6) {
        $hata = 'Sifre en az 6 karakter olmalidir.';
    } elseif ($sifre !== $sifre2) {
        $hata = 'Sifreler eslesmiyor.';
    } else {
        $firmaId = $db->execute(
            "INSERT INTO kullanicilar (firma_adi, ad_soyad, email, sifre, aktif) VALUES (?, ?, ?, ?, 1)",
            [$firmaAdi, $adSoyad, $email, password_hash($sifre, PASSWORD_DEFAULT)]
        );
        $db->seedFirmaDefaults($firmaId);
        $db->execute("UPDATE ayarlar SET deger=? WHERE firma_id=? AND anahtar='firma_adi'", [$firmaAdi, $firmaId]);
        $db->execute("INSERT OR IGNORE INTO ayarlar (firma_id, anahtar, deger) VALUES (?,?,?)", [$firmaId, 'sync_enabled', '0']);
        $db->execute("INSERT OR IGNORE INTO ayarlar (firma_id, anahtar, deger) VALUES (?,?,?)", [$firmaId, 'sync_server_url', '']);

        $_SESSION['firma_id'] = $firmaId;
        $_SESSION['firma_adi'] = $firmaAdi;
        $_SESSION['ad_soyad'] = $adSoyad;
        $_SESSION['email'] = $email;
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servis Takip Panel - Masaustu Kurulum</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); min-height: 100vh; }
        .card { background: white; border-radius: 1.25rem; box-shadow: 0 25px 50px rgba(0,0,0,.25); }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    </style>
</head>
<body class="flex items-center justify-center p-4">
<div class="card w-full max-w-md p-8">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
             style="background: linear-gradient(135deg, #2563eb, #0f766e);">
            <svg viewBox="0 0 24 24" fill="white" class="w-8 h-8">
                <path d="M12 2C12 2 4 9.5 4 14.5C4 18.64 7.58 22 12 22C16.42 22 20 18.64 20 14.5C20 9.5 12 2 12 2Z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800"><?= $localOnly ? 'Servis Takip Panel Lokal' : 'Servis Takip Panel' ?></h1>
        <p class="text-slate-500 text-sm mt-1">
            <?= $localOnly ? 'Lifetime lokal kullanim icin ilk kullaniciyi olusturun' : 'Masaustu icin ilk kullanici olusturun' ?>
        </p>
    </div>

    <?php if ($hata): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm">
        <?= htmlspecialchars($hata, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Firma / Isletme Adi</label>
            <input type="text" name="firma_adi" value="<?= htmlspecialchars($_POST['firma_adi'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800" required>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ad Soyad</label>
            <input type="text" name="ad_soyad" value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800" required>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">E-posta</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800" required>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sifre</label>
                <input type="password" name="sifre" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Sifre Tekrar</label>
                <input type="password" name="sifre2" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800" required>
            </div>
        </div>
        <button type="submit" class="w-full py-3 rounded-xl font-semibold text-white"
                style="background: linear-gradient(135deg, #2563eb, #0f766e);">
            Kurulumu Tamamla
        </button>
    </form>

    <p class="text-xs text-slate-400 text-center mt-6">
        <?= $localOnly
            ? 'Bu lokal surum web, mobil ve bulut senkron kullanmadan sadece bu bilgisayarda calisir.'
            : 'Bu hesap internet yokken de bu bilgisayarda oturum acmak icin kullanilir.' ?>
    </p>
</div>
</body>
</html>
