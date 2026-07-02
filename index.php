<?php
session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/remember.php';

$db = Database::getInstance();
remember_try_restore($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$desktopMod = (bool) getenv('STP_DATA_DIR');

if ($desktopMod && !isset($_SESSION['firma_id'])) {
    $kullanici = $db->fetchOne("SELECT id FROM kullanicilar LIMIT 1");

    if (!$kullanici) {
        header('Location: desktop_setup.php');
        exit;
    }

    header('Location: giris.php');
    exit;
}

if (!$desktopMod && !isset($_SESSION['firma_id'])) {
    header('Location: landing.php');
    exit;
}

if (isset($_SESSION['firma_id']) && empty($_SESSION['admin_support_mode'])) {
    $hesapDurumu = $db->fetchOne(
        "SELECT aktif, paket, abonelik_bitis FROM kullanicilar WHERE id=? AND deleted_at IS NULL",
        [(int)$_SESSION['firma_id']]
    );
    $abonelikGecmis = $hesapDurumu
        && in_array($hesapDurumu['paket'] ?? '', ['standart', 'premium'], true)
        && !empty($hesapDurumu['abonelik_bitis'])
        && strtotime((string)$hesapDurumu['abonelik_bitis']) < strtotime(date('Y-m-d'));
    if (!$hesapDurumu || !(int)$hesapDurumu['aktif'] || $abonelikGecmis) {
        session_destroy();
        header('Location: giris.php?pasif=1');
        exit;
    }
}

$page = isset($_GET['page']) ? preg_replace('/[^a-z_]/', '', trim($_GET['page'])) : 'dashboard';

$validPages = ['dashboard', 'musteriler', 'servisler', 'stok', 'bakimlar', 'raporlar', 'ayarlar', 'satislar', 'tahsilatlar'];
if (!in_array($page, $validPages, true)) {
    $page = 'dashboard';
}

$viewFile = ROOT . "/views/{$page}.php";
if (file_exists($viewFile)) {
    include $viewFile;
} else {
    http_response_code(404);
    echo '<h1>Sayfa bulunamadi</h1>';
}
