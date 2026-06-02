<?php
session_start();

if (!empty($_SESSION['admin_support_mode']) && !empty($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
    $adminName = $_SESSION['admin_name'] ?? '';
    $adminCsrf = $_SESSION['admin_csrf'] ?? bin2hex(random_bytes(32));

    $_SESSION = [
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'admin_csrf' => $adminCsrf,
        'admin_flash' => ['type' => 'success', 'msg' => 'Destek modundan çıkıldı.'],
    ];

    header('Location: admin.php');
    exit;
}

session_destroy();
header('Location: giris.php');
exit;
