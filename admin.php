<?php
session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';

$db = Database::getInstance();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function admin_redirect(string $msg = '', string $type = 'success'): void {
    if ($msg !== '') {
        $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
    }
    header('Location: admin.php');
    exit;
}

function admin_require_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'] ?? '', $token)) {
        admin_redirect('Güvenlik anahtarı geçersiz. Sayfayı yenileyip tekrar deneyin.', 'error');
    }
}

function admin_plan_label(string $plan): string {
    return [
        'ucretsiz' => 'Ücretsiz',
        'standart' => 'Standart',
        'premium' => 'Premium',
        'lokal' => 'Lokal Lifetime',
    ][$plan] ?? ucfirst($plan);
}

function admin_money($value): string {
    return number_format((float)$value, 2, ',', '.') . ' ₺';
}

function admin_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . ($path === '' ? '' : $path);
}

function admin_create_reset_link(Database $db, int $firmaId, ?int $requestId = null): string {
    $firma = $db->fetchOne(
        "SELECT id, email FROM kullanicilar WHERE id=? AND deleted_at IS NULL",
        [$firmaId]
    );
    if (!$firma) {
        admin_redirect('Şifre sıfırlanacak kullanıcı bulunamadı.', 'error');
    }

    $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24);
    $tokenHash = hash('sha256', $token);

    if ($requestId) {
        $db->query(
            "UPDATE password_reset_requests
             SET firma_id=?, email=?, durum='link_gonderildi', admin_id=?, token_hash=?, expires_at=?, sent_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP
             WHERE id=?",
            [$firmaId, $firma['email'], (int)$_SESSION['admin_id'], $tokenHash, $expires, $requestId]
        );
    } else {
        $db->execute(
            "INSERT INTO password_reset_requests (firma_id, email, durum, admin_id, token_hash, expires_at, sent_at)
             VALUES (?, ?, 'link_gonderildi', ?, ?, ?, CURRENT_TIMESTAMP)",
            [$firmaId, $firma['email'], (int)$_SESSION['admin_id'], $tokenHash, $expires]
        );
    }

    return admin_base_url() . '/sifre-sifirla.php?token=' . rawurlencode($token);
}

function admin_client_ip(): string {
    foreach ([$_SERVER['HTTP_CF_CONNECTING_IP'] ?? '', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', $_SERVER['REMOTE_ADDR'] ?? ''] as $ip) {
        $ip = trim(explode(',', (string)$ip)[0]);
        if ($ip !== '') return $ip;
    }
    return '';
}

function admin_log(Database $db, string $action, string $description = '', ?int $firmaId = null): void {
    try {
        $db->execute(
            "INSERT INTO admin_activity_logs (admin_id, firma_id, action, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['admin_id'] ?? null,
                $firmaId,
                $action,
                $description,
                admin_client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]
        );
    } catch (Throwable $e) {
        // Log kaydı yönetim işlemini engellemesin.
    }
}

function admin_role(): string {
    return $_SESSION['admin_role'] ?? 'super_admin';
}

function admin_can(string $capability): bool {
    $role = admin_role();
    if ($role === 'super_admin') return true;
    $map = [
        'support' => ['support', 'finance'],
        'finance' => ['finance'],
        'view' => ['support', 'finance', 'viewer'],
    ];
    return in_array($role, $map[$capability] ?? [], true);
}

function admin_require_capability(string $capability): void {
    if (!admin_can($capability)) {
        admin_redirect('Bu işlem için admin yetkiniz yok.', 'error');
    }
}

$adminCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM admin_users");
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'setup' && $_SERVER['REQUEST_METHOD'] === 'POST' && $adminCount === 0) {
    admin_require_csrf();
    $ad = trim($_POST['ad_soyad'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $sifre = $_POST['sifre'] ?? '';
    $sifre2 = $_POST['sifre2'] ?? '';

    if (!$ad || !$email || !$sifre) {
        admin_redirect('Tüm alanları doldurun.', 'error');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        admin_redirect('Geçerli bir e-posta girin.', 'error');
    }
    if (strlen($sifre) < 8) {
        admin_redirect('Admin şifresi en az 8 karakter olmalı.', 'error');
    }
    if ($sifre !== $sifre2) {
        admin_redirect('Şifreler eşleşmiyor.', 'error');
    }

    $id = $db->execute(
        "INSERT INTO admin_users (ad_soyad, email, sifre) VALUES (?, ?, ?)",
        [$ad, $email, password_hash($sifre, PASSWORD_BCRYPT)]
    );
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_name'] = $ad;
    admin_redirect('Admin hesabı oluşturuldu.');
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $sifre = $_POST['sifre'] ?? '';
    $admin = $db->fetchOne(
        "SELECT id, ad_soyad, email, sifre, aktif, role FROM admin_users WHERE email=?",
        [$email]
    );

    if (!$admin || !password_verify($sifre, $admin['sifre'])) {
        admin_redirect('E-posta veya şifre hatalı.', 'error');
    }
    if (!(int)$admin['aktif']) {
        admin_redirect('Bu admin hesabı pasif.', 'error');
    }

    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_name'] = $admin['ad_soyad'];
    $_SESSION['admin_role'] = $admin['role'] ?: 'super_admin';
    $db->query("UPDATE admin_users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?", [$admin['id']]);
    admin_redirect('Giriş başarılı.');
}

if ($action === 'logout') {
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
    admin_redirect('Çıkış yapıldı.');
}

if ($action === 'export_users' && !empty($_SESSION['admin_id'])) {
    admin_require_capability('view');
    $rows = $db->fetchAll("
        SELECT firma_adi, ad_soyad, email, telefon, paket, abonelik_bitis, aktif, created_at
        FROM kullanicilar
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
    ");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=\"servis-takip-panel-firmalar.csv\"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Firma', 'Yetkili', 'E-posta', 'Telefon', 'Paket', 'Abonelik Bitiş', 'Aktif', 'Kayıt Tarihi'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['firma_adi'],
            $row['ad_soyad'],
            $row['email'],
            $row['telefon'],
            admin_plan_label($row['paket'] ?: 'ucretsiz'),
            $row['abonelik_bitis'],
            (int)$row['aktif'] ? 'Aktif' : 'Pasif',
            $row['created_at'],
        ], ';');
    }
    admin_log($db, 'users_exported', 'Firma listesi CSV olarak indirildi.');
    exit;
}

if ($action === 'create_admin_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    admin_require_capability('super_admin');
    $ad = trim($_POST['ad_soyad'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $sifre = $_POST['sifre'] ?? '';
    $role = $_POST['role'] ?? 'support';
    if (!$ad || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($sifre) < 8) {
        admin_redirect('Admin eklemek için ad, geçerli e-posta ve en az 8 karakter şifre gerekli.', 'error');
    }
    if (!in_array($role, ['super_admin', 'support', 'finance', 'viewer'], true)) {
        $role = 'support';
    }
    $exists = $db->fetchColumn("SELECT COUNT(*) FROM admin_users WHERE email=?", [$email]);
    if ($exists) {
        admin_redirect('Bu admin e-postası zaten kayıtlı.', 'error');
    }
    $db->execute(
        "INSERT INTO admin_users (ad_soyad, email, sifre, role, aktif) VALUES (?, ?, ?, ?, 1)",
        [$ad, $email, password_hash($sifre, PASSWORD_BCRYPT), $role]
    );
    admin_log($db, 'admin_user_created', "{$email} admin kullanıcısı oluşturuldu.");
    admin_redirect('Admin kullanıcısı oluşturuldu.');
}

if ($action === 'update_admin_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    admin_require_capability('super_admin');
    $adminId = (int)($_POST['admin_user_id'] ?? 0);
    $role = $_POST['role'] ?? 'support';
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    if ($adminId <= 0 || !in_array($role, ['super_admin', 'support', 'finance', 'viewer'], true)) {
        admin_redirect('Admin güncellemesi geçersiz.', 'error');
    }
    if ($adminId === (int)$_SESSION['admin_id'] && !$aktif) {
        admin_redirect('Kendi admin hesabınızı pasife alamazsınız.', 'error');
    }
    $db->query("UPDATE admin_users SET role=?, aktif=? WHERE id=?", [$role, $aktif, $adminId]);
    admin_log($db, 'admin_user_updated', "Admin #{$adminId} rol={$role}, aktif={$aktif}");
    admin_redirect('Admin kullanıcısı güncellendi.');
}

if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $paket = $_POST['paket'] ?? 'ucretsiz';
    $abonelikBitis = trim($_POST['abonelik_bitis'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $allowed = ['ucretsiz', 'standart', 'premium', 'lokal'];
    if ($id <= 0 || !in_array($paket, $allowed, true)) {
        admin_redirect('Geçersiz kullanıcı güncellemesi.', 'error');
    }
    if ($abonelikBitis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $abonelikBitis)) {
        admin_redirect('Abonelik bitiş tarihi geçersiz.', 'error');
    }
    $db->query(
        "UPDATE kullanicilar SET paket=?, abonelik_bitis=?, aktif=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
        [$paket, $abonelikBitis ?: null, $aktif, $id]
    );
    admin_log($db, 'user_updated', "Paket: {$paket}, aktif: {$aktif}, bitis: " . ($abonelikBitis ?: '-'), $id);
    admin_redirect('Kullanıcı güncellendi.');
}

if ($action === 'create_reset_link' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $firmaId = (int)($_POST['firma_id'] ?? 0);
    $requestId = (int)($_POST['request_id'] ?? 0);
    if ($firmaId <= 0) {
        admin_redirect('Şifre linki için kullanıcı seçin.', 'error');
    }
    $link = admin_create_reset_link($db, $firmaId, $requestId > 0 ? $requestId : null);
    admin_log($db, 'password_reset_link_created', 'Şifre sıfırlama linki oluşturuldu.', $firmaId);
    $_SESSION['admin_reset_link'] = $link;
    admin_redirect('Şifre sıfırlama linki oluşturuldu. Linki müşteriye iletebilirsiniz.');
}

if ($action === 'add_subscription_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    admin_require_capability('finance');
    $firmaId = (int)($_POST['firma_id'] ?? 0);
    $paket = trim($_POST['paket'] ?? '');
    $tutar = (float)str_replace(',', '.', (string)($_POST['tutar'] ?? '0'));
    $paraBirimi = strtoupper(trim($_POST['para_birimi'] ?? 'TRY')) ?: 'TRY';
    $odemeYontemi = trim($_POST['odeme_yontemi'] ?? '');
    $donemBaslangic = trim($_POST['donem_baslangic'] ?? '');
    $donemBitis = trim($_POST['donem_bitis'] ?? '');
    $notlar = trim($_POST['notlar'] ?? '');

    if ($firmaId <= 0 || $tutar <= 0) {
        admin_redirect('Ödeme kaydı için firma ve tutar zorunlu.', 'error');
    }
    foreach ([$donemBaslangic, $donemBitis] as $dateValue) {
        if ($dateValue !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            admin_redirect('Dönem tarihleri geçersiz.', 'error');
        }
    }

    $firma = $db->fetchOne("SELECT id, paket FROM kullanicilar WHERE id=? AND deleted_at IS NULL", [$firmaId]);
    if (!$firma) {
        admin_redirect('Ödeme eklenecek firma bulunamadı.', 'error');
    }
    if ($paket === '') {
        $paket = $firma['paket'] ?: 'ucretsiz';
    }

    $db->execute(
        "INSERT INTO subscription_payments (firma_id, admin_id, paket, tutar, para_birimi, odeme_yontemi, donem_baslangic, donem_bitis, notlar)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$firmaId, (int)$_SESSION['admin_id'], $paket, $tutar, $paraBirimi, $odemeYontemi, $donemBaslangic ?: null, $donemBitis ?: null, $notlar]
    );
    if ($donemBitis !== '') {
        $db->query(
            "UPDATE kullanicilar SET paket=?, abonelik_bitis=?, aktif=1, updated_at=CURRENT_TIMESTAMP WHERE id=?",
            [$paket, $donemBitis, $firmaId]
        );
    }
    admin_log($db, 'subscription_payment_added', admin_money($tutar) . " {$paraBirimi} abonelik ödemesi eklendi.", $firmaId);
    header('Location: admin.php?firma_id=' . $firmaId);
    exit;
}

if ($action === 'revoke_device' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $firmaId = (int)($_POST['firma_id'] ?? 0);
    $tokenId = (int)($_POST['token_id'] ?? 0);
    if ($firmaId <= 0 || $tokenId <= 0) {
        admin_redirect('Geçersiz cihaz işlemi.', 'error');
    }
    $db->query(
        "UPDATE sync_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE id=? AND firma_id=?",
        [$tokenId, $firmaId]
    );
    admin_log($db, 'device_revoked', "Cihaz token #{$tokenId} iptal edildi.", $firmaId);
    header('Location: admin.php?firma_id=' . $firmaId);
    exit;
}

if ($action === 'support_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $firmaId = (int)($_POST['firma_id'] ?? 0);
    $firma = $db->fetchOne(
        "SELECT id, firma_adi, ad_soyad, email, aktif FROM kullanicilar WHERE id=? AND deleted_at IS NULL",
        [$firmaId]
    );
    if (!$firma) {
        admin_redirect('Destek girişi yapılacak firma bulunamadı.', 'error');
    }

    $_SESSION['firma_id'] = (int)$firma['id'];
    $_SESSION['firma_adi'] = $firma['firma_adi'];
    $_SESSION['ad_soyad'] = $firma['ad_soyad'];
    $_SESSION['email'] = $firma['email'];
    $_SESSION['admin_support_mode'] = 1;
    $_SESSION['admin_support_firma_id'] = (int)$firma['id'];
    $_SESSION['admin_support_firma_adi'] = $firma['firma_adi'];
    $_SESSION['admin_support_started_at'] = date('Y-m-d H:i:s');
    admin_log($db, 'support_login', 'Admin destek modu ile firma paneline girdi.', (int)$firma['id']);

    header('Location: index.php');
    exit;
}

if ($action === 'update_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $firmaId = (int)($_POST['firma_id'] ?? 0);
    $musteriId = (int)($_POST['musteri_id'] ?? 0);
    $ad = trim($_POST['ad'] ?? '');
    $soyad = trim($_POST['soyad'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adres = trim($_POST['adres'] ?? '');
    $notlar = trim($_POST['notlar'] ?? '');

    if ($firmaId <= 0 || $musteriId <= 0 || $ad === '' || $soyad === '') {
        admin_redirect('Müşteri güncellemesi için ad, soyad ve firma zorunlu.', 'error');
    }

    $exists = $db->fetchColumn(
        "SELECT COUNT(*) FROM musteriler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
        [$musteriId, $firmaId]
    );
    if (!$exists) {
        admin_redirect('Müşteri bulunamadı.', 'error');
    }

    $db->query(
        "UPDATE musteriler
         SET ad=?, soyad=?, telefon=?, email=?, adres=?, notlar=?, updated_at=CURRENT_TIMESTAMP, synced_at=NULL
         WHERE id=? AND firma_id=?",
        [$ad, $soyad, $telefon, $email, $adres, $notlar, $musteriId, $firmaId]
    );
    admin_log($db, 'customer_updated', "Müşteri #{$musteriId} güncellendi.", $firmaId);
    header('Location: admin.php?firma_id=' . $firmaId . '&musteri_id=' . $musteriId);
    exit;
}

if ($action === 'add_support_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $firmaId = (int)($_POST['firma_id'] ?? 0);
    $musteriId = (int)($_POST['musteri_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($firmaId <= 0 || $note === '') {
        admin_redirect('Destek notu için firma ve not alanı zorunlu.', 'error');
    }

    $musteriParam = $musteriId > 0 ? $musteriId : null;
    if ($musteriParam) {
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM musteriler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
            [$musteriParam, $firmaId]
        );
        if (!$exists) {
            admin_redirect('Not eklenecek müşteri bulunamadı.', 'error');
        }
    }

    $db->query(
        "INSERT INTO admin_support_notes (admin_id, firma_id, musteri_id, note) VALUES (?, ?, ?, ?)",
        [(int)$_SESSION['admin_id'], $firmaId, $musteriParam, $note]
    );
    admin_log($db, 'support_note_added', $musteriParam ? "Müşteri #{$musteriParam} için destek notu eklendi." : 'Firma destek notu eklendi.', $firmaId);
    header('Location: admin.php?firma_id=' . $firmaId . ($musteriParam ? '&musteri_id=' . $musteriParam : ''));
    exit;
}

if ($action === 'reply_chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($conversationId <= 0 || $message === '') {
        admin_redirect('Cevap yazmak için konuşma ve mesaj zorunlu.', 'error');
    }
    $exists = $db->fetchColumn("SELECT COUNT(*) FROM support_conversations WHERE id=?", [$conversationId]);
    if (!$exists) {
        admin_redirect('Konuşma bulunamadı.', 'error');
    }
    $db->query(
        "INSERT INTO support_messages (conversation_id, sender_type, admin_id, message) VALUES (?, 'admin', ?, ?)",
        [$conversationId, (int)$_SESSION['admin_id'], $message]
    );
    $db->query(
        "UPDATE support_conversations SET durum='yanitlandi', last_message_at=CURRENT_TIMESTAMP WHERE id=?",
        [$conversationId]
    );
    admin_log($db, 'chat_replied', "Sohbet #{$conversationId} yanıtlandı.");
    header('Location: admin.php?chat_id=' . $conversationId . '#chat');
    exit;
}

if ($action === 'close_chat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['admin_id'])) {
        admin_redirect('Önce admin girişi yapın.', 'error');
    }
    admin_require_csrf();
    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        admin_redirect('Konuşma bulunamadı.', 'error');
    }
    $db->query(
        "UPDATE support_conversations SET durum='kapali', closed_at=CURRENT_TIMESTAMP WHERE id=?",
        [$conversationId]
    );
    admin_log($db, 'chat_closed', "Sohbet #{$conversationId} kapatıldı.");
    header('Location: admin.php?chat_id=' . $conversationId . '#chat');
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
$generatedResetLink = $_SESSION['admin_reset_link'] ?? '';
unset($_SESSION['admin_reset_link']);

$isLogged = !empty($_SESSION['admin_id']);
$users = [];
$selectedFirmaId = $isLogged ? max(0, (int)($_GET['firma_id'] ?? 0)) : 0;
$selectedMusteriId = $isLogged ? max(0, (int)($_GET['musteri_id'] ?? 0)) : 0;
$search = $isLogged ? trim($_GET['q'] ?? '') : '';
$customerSearch = $isLogged ? trim($_GET['customer_q'] ?? '') : '';
$planFilter = $isLogged ? trim($_GET['plan'] ?? '') : '';
$statusFilter = $isLogged ? trim($_GET['status'] ?? '') : '';
$selectedChatId = $isLogged ? max(0, (int)($_GET['chat_id'] ?? 0)) : 0;
$selectedFirma = false;
$firmCustomers = [];
$firmSummary = [];
$firmDevices = [];
$firmRecentActivity = [];
$firmSubscriptionPayments = [];
$supportNotes = [];
$customerDetail = false;
$customerServices = [];
$customerSales = [];
$customerCollections = [];
$customerInstallments = [];
$customerDevices = [];
$chatConversations = [];
$passwordResetRequests = [];
$adminUsers = [];
$selectedChat = false;
$selectedChatMessages = [];
$adminMetrics = [
    'open_chats' => 0,
    'unread_chat_messages' => 0,
    'pending_password_resets' => 0,
    'expiring_subscriptions' => 0,
    'monthly_collections' => 0,
    'monthly_signups' => 0,
    'connected_devices' => 0,
    'overdue_accounts' => 0,
];
$recentAdminLogs = [];
$systemHealth = [
    'db_size_mb' => 0,
    'last_sync_at' => '',
    'revoked_devices' => 0,
    'remember_tokens' => 0,
];
$stats = [
    'toplam' => 0,
    'aktif' => 0,
    'ucretsiz' => 0,
    'standart' => 0,
    'premium' => 0,
    'lokal' => 0,
];

if ($isLogged) {
    $userWhere = ["k.deleted_at IS NULL"];
    $userParams = [];
    if ($search !== '') {
        $userWhere[] = "(k.firma_adi LIKE ? OR k.ad_soyad LIKE ? OR k.email LIKE ? OR k.telefon LIKE ?)";
        $like = '%' . $search . '%';
        array_push($userParams, $like, $like, $like, $like);
    }
    if (in_array($planFilter, ['ucretsiz', 'standart', 'premium', 'lokal'], true)) {
        $userWhere[] = "COALESCE(k.paket, 'ucretsiz') = ?";
        $userParams[] = $planFilter;
    }
    if ($statusFilter === 'aktif' || $statusFilter === 'pasif') {
        $userWhere[] = "k.aktif = ?";
        $userParams[] = $statusFilter === 'aktif' ? 1 : 0;
    }

    $users = $db->fetchAll("
        SELECT
            k.id, k.firma_adi, k.ad_soyad, k.email, k.telefon, k.paket, k.abonelik_bitis, k.aktif,
            k.created_at, k.updated_at,
            (SELECT COUNT(*) FROM musteriler m WHERE m.firma_id=k.id AND m.deleted_at IS NULL) AS musteri_sayisi,
            (SELECT COUNT(*) FROM servisler s WHERE s.firma_id=k.id AND s.deleted_at IS NULL) AS servis_sayisi,
            (SELECT COUNT(*) FROM satislar sa WHERE sa.firma_id=k.id AND sa.deleted_at IS NULL) AS satis_sayisi,
            (SELECT COUNT(*) FROM sync_tokens st WHERE st.firma_id=k.id AND st.revoked_at IS NULL) AS cihaz_sayisi,
            (SELECT MAX(last_seen_at) FROM sync_tokens st2 WHERE st2.firma_id=k.id AND st2.revoked_at IS NULL) AS son_senkron
        FROM kullanicilar k
        WHERE " . implode(' AND ', $userWhere) . "
        ORDER BY k.created_at DESC
    ", $userParams);

    $stats['toplam'] = count($users);
    foreach ($users as $u) {
        if ((int)$u['aktif']) $stats['aktif']++;
        $plan = $u['paket'] ?: 'ucretsiz';
        if (isset($stats[$plan])) $stats[$plan]++;
    }

    $adminMetrics = [
        'open_chats' => (int)$db->fetchColumn("SELECT COUNT(*) FROM support_conversations WHERE durum!='kapali'"),
        'unread_chat_messages' => (int)$db->fetchColumn("SELECT COUNT(*) FROM support_messages WHERE sender_type='visitor' AND read_at IS NULL"),
        'pending_password_resets' => (int)$db->fetchColumn("SELECT COUNT(*) FROM password_reset_requests WHERE durum='bekliyor'"),
        'expiring_subscriptions' => (int)$db->fetchColumn("SELECT COUNT(*) FROM kullanicilar WHERE deleted_at IS NULL AND aktif=1 AND abonelik_bitis IS NOT NULL AND abonelik_bitis BETWEEN date('now') AND date('now', '+14 days')"),
        'monthly_collections' => (float)$db->fetchColumn("SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE deleted_at IS NULL AND tahsilat_tarihi BETWEEN date('now','start of month') AND date('now','start of month','+1 month','-1 day')"),
        'monthly_signups' => (int)$db->fetchColumn("SELECT COUNT(*) FROM kullanicilar WHERE deleted_at IS NULL AND created_at >= datetime('now','start of month')"),
        'connected_devices' => (int)$db->fetchColumn("SELECT COUNT(*) FROM sync_tokens WHERE revoked_at IS NULL"),
        'overdue_accounts' => (int)$db->fetchColumn("SELECT COUNT(*) FROM kullanicilar WHERE deleted_at IS NULL AND aktif=1 AND paket IN ('standart','premium') AND abonelik_bitis IS NOT NULL AND abonelik_bitis < date('now')"),
    ];

    $recentAdminLogs = $db->fetchAll("
        SELECT l.*, a.ad_soyad AS admin_adi, k.firma_adi
        FROM admin_activity_logs l
        LEFT JOIN admin_users a ON a.id=l.admin_id
        LEFT JOIN kullanicilar k ON k.id=l.firma_id
        ORDER BY l.created_at DESC
        LIMIT 12
    ");

    $adminUsers = $db->fetchAll("
        SELECT id, ad_soyad, email, role, aktif, created_at, last_login_at
        FROM admin_users
        ORDER BY created_at DESC
    ");

    $dbFile = __DIR__ . '/database/musteri-takip.db';
    $systemHealth = [
        'db_size_mb' => is_file($dbFile) ? round(filesize($dbFile) / 1024 / 1024, 2) : 0,
        'last_sync_at' => (string)$db->fetchColumn("SELECT MAX(last_seen_at) FROM sync_tokens WHERE revoked_at IS NULL"),
        'revoked_devices' => (int)$db->fetchColumn("SELECT COUNT(*) FROM sync_tokens WHERE revoked_at IS NOT NULL"),
        'remember_tokens' => (int)$db->fetchColumn("SELECT COUNT(*) FROM remember_tokens WHERE revoked_at IS NULL AND expires_at >= datetime('now')"),
    ];

    $passwordResetRequests = $db->fetchAll("
        SELECT pr.*, k.firma_adi, k.ad_soyad, k.telefon, au.ad_soyad AS admin_adi
        FROM password_reset_requests pr
        LEFT JOIN kullanicilar k ON k.id=pr.firma_id
        LEFT JOIN admin_users au ON au.id=pr.admin_id
        WHERE pr.durum IN ('bekliyor', 'link_gonderildi')
           OR (pr.created_at >= datetime('now', '-7 days'))
        ORDER BY
            CASE pr.durum
                WHEN 'bekliyor' THEN 0
                WHEN 'link_gonderildi' THEN 1
                ELSE 2
            END,
            pr.created_at DESC
        LIMIT 30
    ");

    $chatConversations = $db->fetchAll("
        SELECT c.*,
            (SELECT message FROM support_messages sm WHERE sm.conversation_id=c.id ORDER BY sm.id DESC LIMIT 1) AS son_mesaj,
            (SELECT COUNT(*) FROM support_messages sm WHERE sm.conversation_id=c.id AND sm.sender_type='visitor' AND sm.read_at IS NULL) AS okunmamis
        FROM support_conversations c
        ORDER BY c.last_message_at DESC, c.created_at DESC
        LIMIT 50
    ");

    if ($selectedChatId > 0) {
        $selectedChat = $db->fetchOne("SELECT * FROM support_conversations WHERE id=?", [$selectedChatId]);
        if ($selectedChat) {
            $selectedChatMessages = $db->fetchAll("
                SELECT sm.*, au.ad_soyad AS admin_adi
                FROM support_messages sm
                LEFT JOIN admin_users au ON au.id=sm.admin_id
                WHERE sm.conversation_id=?
                ORDER BY sm.id ASC
            ", [$selectedChatId]);
            $db->query(
                "UPDATE support_messages SET read_at=CURRENT_TIMESTAMP WHERE conversation_id=? AND sender_type='visitor' AND read_at IS NULL",
                [$selectedChatId]
            );
        }
    }

    if ($selectedFirmaId > 0) {
        $selectedFirma = $db->fetchOne(
            "SELECT id, firma_adi, ad_soyad, email, telefon, paket, abonelik_bitis, aktif, created_at, updated_at
             FROM kullanicilar
             WHERE id=? AND deleted_at IS NULL",
            [$selectedFirmaId]
        );

        if ($selectedFirma) {
            $firmSummary = [
                'musteri' => (int)$db->fetchColumn("SELECT COUNT(*) FROM musteriler WHERE firma_id=? AND deleted_at IS NULL", [$selectedFirmaId]),
                'servis' => (int)$db->fetchColumn("SELECT COUNT(*) FROM servisler WHERE firma_id=? AND deleted_at IS NULL", [$selectedFirmaId]),
                'satis' => (int)$db->fetchColumn("SELECT COUNT(*) FROM satislar WHERE firma_id=? AND deleted_at IS NULL", [$selectedFirmaId]),
                'tahsilat' => (float)$db->fetchColumn("SELECT COALESCE(SUM(tutar),0) FROM tahsilatlar WHERE firma_id=? AND deleted_at IS NULL", [$selectedFirmaId]),
                'cihaz' => (int)$db->fetchColumn("SELECT COUNT(*) FROM sync_tokens WHERE firma_id=? AND revoked_at IS NULL", [$selectedFirmaId]),
                'mobil_cihaz' => (int)$db->fetchColumn("SELECT COUNT(*) FROM sync_tokens WHERE firma_id=? AND revoked_at IS NULL AND device_type='mobile'", [$selectedFirmaId]),
                'masaustu_cihaz' => (int)$db->fetchColumn("SELECT COUNT(*) FROM sync_tokens WHERE firma_id=? AND revoked_at IS NULL AND device_type='desktop'", [$selectedFirmaId]),
                'servis_bakiye' => (float)$db->fetchColumn("SELECT COALESCE(SUM(toplam_tutar - odenen_tutar),0) FROM servisler WHERE firma_id=? AND deleted_at IS NULL AND odeme_durumu!='odendi'", [$selectedFirmaId]),
                'satis_bakiye' => (float)$db->fetchColumn("SELECT COALESCE(SUM(toplam_tutar - odenen_tutar),0) FROM satislar WHERE firma_id=? AND deleted_at IS NULL AND odeme_durumu!='odendi'", [$selectedFirmaId]),
                'geciken_taksit' => (int)$db->fetchColumn("SELECT COUNT(*) FROM taksitler WHERE firma_id=? AND deleted_at IS NULL AND odendi=0 AND vade_tarihi < date('now')", [$selectedFirmaId]),
            ];

            $customerWhere = ["m.firma_id=?", "m.deleted_at IS NULL"];
            $customerParams = [$selectedFirmaId];
            if ($customerSearch !== '') {
                $customerWhere[] = "(m.ad LIKE ? OR m.soyad LIKE ? OR m.telefon LIKE ? OR m.email LIKE ? OR m.adres LIKE ?)";
                $like = '%' . $customerSearch . '%';
                array_push($customerParams, $like, $like, $like, $like, $like);
            }

            $firmCustomers = $db->fetchAll("
                SELECT
                    m.id, m.ad, m.soyad, m.telefon, m.email, m.adres, m.updated_at,
                    (SELECT COUNT(*) FROM servisler s WHERE s.musteri_id=m.id AND s.firma_id=m.firma_id AND s.deleted_at IS NULL) AS servis_sayisi,
                    (SELECT COUNT(*) FROM satislar sa WHERE sa.musteri_id=m.id AND sa.firma_id=m.firma_id AND sa.deleted_at IS NULL) AS satis_sayisi,
                    (SELECT COALESCE(SUM(s.toplam_tutar - s.odenen_tutar),0) FROM servisler s WHERE s.musteri_id=m.id AND s.firma_id=m.firma_id AND s.deleted_at IS NULL AND s.odeme_durumu!='odendi') AS servis_bakiye,
                    (SELECT COALESCE(SUM(sa.toplam_tutar - sa.odenen_tutar),0) FROM satislar sa WHERE sa.musteri_id=m.id AND sa.firma_id=m.firma_id AND sa.deleted_at IS NULL AND sa.odeme_durumu!='odendi') AS satis_bakiye,
                    (SELECT COALESCE(SUM(t.tutar),0) FROM tahsilatlar t WHERE t.musteri_id=m.id AND t.firma_id=m.firma_id AND t.deleted_at IS NULL) AS toplam_tahsilat,
                    (SELECT MAX(created_at) FROM servisler s2 WHERE s2.musteri_id=m.id AND s2.firma_id=m.firma_id AND s2.deleted_at IS NULL) AS son_servis
                FROM musteriler m
                WHERE " . implode(' AND ', $customerWhere) . "
                ORDER BY m.updated_at DESC, m.created_at DESC
                LIMIT 200
            ", $customerParams);

            $firmDevices = $db->fetchAll("
                SELECT id, device_name, device_id, device_type, ip_address, user_agent, created_at, last_seen_at
                FROM sync_tokens
                WHERE firma_id=? AND revoked_at IS NULL
                ORDER BY COALESCE(last_seen_at, created_at) DESC
                LIMIT 10
            ", [$selectedFirmaId]);

            $firmRecentActivity = $db->fetchAll("
                SELECT 'Servis' AS tip, id, created_at AS tarih, toplam_tutar AS tutar, durum AS aciklama FROM servisler WHERE firma_id=? AND deleted_at IS NULL
                UNION ALL
                SELECT 'Satış' AS tip, id, created_at AS tarih, toplam_tutar AS tutar, odeme_durumu AS aciklama FROM satislar WHERE firma_id=? AND deleted_at IS NULL
                UNION ALL
                SELECT 'Tahsilat' AS tip, id, created_at AS tarih, tutar AS tutar, odeme_yontemi AS aciklama FROM tahsilatlar WHERE firma_id=? AND deleted_at IS NULL
                ORDER BY tarih DESC
                LIMIT 12
            ", [$selectedFirmaId, $selectedFirmaId, $selectedFirmaId]);

            $firmSubscriptionPayments = $db->fetchAll("
                SELECT sp.*, au.ad_soyad AS admin_adi
                FROM subscription_payments sp
                LEFT JOIN admin_users au ON au.id=sp.admin_id
                WHERE sp.firma_id=?
                ORDER BY sp.created_at DESC
                LIMIT 20
            ", [$selectedFirmaId]);

            $supportNotes = $db->fetchAll("
                SELECT n.id, n.note, n.created_at, a.ad_soyad AS admin_adi, m.ad, m.soyad
                FROM admin_support_notes n
                LEFT JOIN admin_users a ON a.id=n.admin_id
                LEFT JOIN musteriler m ON m.id=n.musteri_id
                WHERE n.firma_id=? AND (?=0 OR n.musteri_id=?)
                ORDER BY n.created_at DESC
                LIMIT 20
            ", [$selectedFirmaId, $selectedMusteriId, $selectedMusteriId]);

            if ($selectedMusteriId > 0) {
                $customerDetail = $db->fetchOne(
                    "SELECT * FROM musteriler WHERE id=? AND firma_id=? AND deleted_at IS NULL",
                    [$selectedMusteriId, $selectedFirmaId]
                );

                if ($customerDetail) {
                    $customerServices = $db->fetchAll("
                        SELECT id, servis_tipi, durum, oncelik, toplam_tutar, odeme_durumu, odenen_tutar, tamamlanma_tarihi, created_at, notlar
                        FROM servisler
                        WHERE firma_id=? AND musteri_id=? AND deleted_at IS NULL
                        ORDER BY created_at DESC
                        LIMIT 50
                    ", [$selectedFirmaId, $selectedMusteriId]);

                    $customerSales = $db->fetchAll("
                        SELECT id, toplam_tutar, odeme_durumu, odenen_tutar, satis_tarihi, odeme_turu, taksit_sayisi, seri_no, notlar
                        FROM satislar
                        WHERE firma_id=? AND musteri_id=? AND deleted_at IS NULL
                        ORDER BY created_at DESC
                        LIMIT 50
                    ", [$selectedFirmaId, $selectedMusteriId]);

                    $customerCollections = $db->fetchAll("
                        SELECT id, kaynak_tip, kaynak_id, tutar, odeme_yontemi, tahsilat_tarihi, notlar
                        FROM tahsilatlar
                        WHERE firma_id=? AND musteri_id=? AND deleted_at IS NULL
                        ORDER BY tahsilat_tarihi DESC, created_at DESC
                        LIMIT 50
                    ", [$selectedFirmaId, $selectedMusteriId]);

                    $customerInstallments = $db->fetchAll("
                        SELECT id, satis_id, taksit_no, tutar, vade_tarihi, odeme_tarihi, odendi, odeme_yontemi, notlar
                        FROM taksitler
                        WHERE firma_id=? AND musteri_id=? AND deleted_at IS NULL
                        ORDER BY odendi ASC, vade_tarihi ASC
                        LIMIT 50
                    ", [$selectedFirmaId, $selectedMusteriId]);

                    $customerDevices = $db->fetchAll("
                        SELECT mc.id, COALESCE(c.cihaz_adi, 'Cihaz') AS cihaz_adi, c.marka, c.model, mc.seri_no, mc.kurulum_tarihi, mc.notlar
                        FROM musteri_cihazlari mc
                        LEFT JOIN cihazlar c ON c.id=mc.cihaz_id
                        WHERE mc.firma_id=? AND mc.musteri_id=? AND mc.deleted_at IS NULL
                        ORDER BY mc.created_at DESC
                        LIMIT 50
                    ", [$selectedFirmaId, $selectedMusteriId]);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - Servis Takip Panel</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<?php if (!$isLogged): ?>
    <main class="min-h-screen grid place-items-center p-4">
        <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-sm p-7">
            <div class="w-12 h-12 bg-blue-600 rounded-xl grid place-items-center text-white mb-5">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h1 class="text-2xl font-bold mb-1">
                <?= $adminCount === 0 ? 'İlk Admin Hesabı' : 'Admin Girişi' ?>
            </h1>
            <p class="text-sm text-slate-500 mb-6">
                <?= $adminCount === 0
                    ? 'Canlıya almadan önce yönetici hesabını burada oluşturun.'
                    : 'Kullanıcıları, paketleri ve hesap durumlarını yönetin.' ?>
            </p>

            <?php if ($flash): ?>
                <div class="mb-4 rounded-xl px-4 py-3 text-sm <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                    <?= htmlspecialchars($flash['msg']) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="<?= $adminCount === 0 ? 'setup' : 'login' ?>">

                <?php if ($adminCount === 0): ?>
                    <label class="block">
                        <span class="text-sm font-semibold">Ad Soyad</span>
                        <input name="ad_soyad" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                    </label>
                <?php endif; ?>

                <label class="block">
                    <span class="text-sm font-semibold">E-posta</span>
                    <input type="email" name="email" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold">Şifre</span>
                    <input type="password" name="sifre" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                </label>

                <?php if ($adminCount === 0): ?>
                    <label class="block">
                        <span class="text-sm font-semibold">Şifre Tekrar</span>
                        <input type="password" name="sifre2" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                    </label>
                <?php endif; ?>

                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl py-3 transition">
                    <?= $adminCount === 0 ? 'Admin Hesabı Oluştur' : 'Giriş Yap' ?>
                </button>
            </form>
        </div>
    </main>
<?php else: ?>
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-blue-600 font-bold">Servis Takip Panel</div>
                <h1 class="text-xl font-bold">Admin Paneli</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-500"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?> · <?= htmlspecialchars(admin_role()) ?></span>
                <a href="admin.php?action=logout" class="text-sm font-semibold text-red-600 hover:text-red-700">
                    Çıkış
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($flash): ?>
            <div class="mb-5 rounded-xl px-4 py-3 text-sm <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <?php if ($generatedResetLink): ?>
            <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-blue-900">Şifre sıfırlama linki hazır</div>
                        <input id="resetLinkInput" readonly value="<?= htmlspecialchars($generatedResetLink) ?>"
                               class="mt-2 w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm text-slate-700">
                        <p class="text-xs text-blue-700 mt-1">Bu link 24 saat geçerlidir ve tek kullanımlıktır.</p>
                    </div>
                    <button type="button" onclick="navigator.clipboard?.writeText(document.getElementById('resetLinkInput').value)"
                            class="rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-semibold">
                        Linki Kopyala
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <section class="grid md:grid-cols-4 xl:grid-cols-8 gap-4 mb-8">
            <?php foreach ([
                ['Toplam Firma', $stats['toplam'], 'fa-building'],
                ['Aktif', $stats['aktif'], 'fa-circle-check'],
                ['Açık Sohbet', $adminMetrics['open_chats'], 'fa-comments'],
                ['Okunmamış', $adminMetrics['unread_chat_messages'], 'fa-bell'],
                ['Şifre Talebi', $adminMetrics['pending_password_resets'], 'fa-key'],
                ['Biten Abonelik', $adminMetrics['expiring_subscriptions'], 'fa-hourglass-half'],
                ['Bağlı Cihaz', $adminMetrics['connected_devices'], 'fa-mobile-screen-button'],
                ['Bu Ay Kayıt', $adminMetrics['monthly_signups'], 'fa-user-plus'],
            ] as [$label, $value, $icon]): ?>
                <div class="bg-white border border-slate-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-slate-500"><?= htmlspecialchars($label) ?></span>
                        <i class="fas <?= htmlspecialchars($icon) ?> text-blue-500"></i>
                    </div>
                    <div class="text-2xl font-extrabold"><?= (int)$value ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="grid lg:grid-cols-[1.1fr_.9fr] gap-4 mb-8">
            <div class="bg-slate-900 text-white rounded-2xl p-5 overflow-hidden">
                <div class="text-xs uppercase tracking-wide text-blue-200 font-bold mb-2">Yönetim Özeti</div>
                <h2 class="text-2xl font-extrabold mb-4">Bu ay <?= admin_money($adminMetrics['monthly_collections']) ?> tahsilat görünüyor.</h2>
                <div class="grid sm:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-xl bg-white/10 p-3">
                        <div class="text-blue-100">Ücretsiz</div>
                        <div class="text-xl font-bold"><?= (int)$stats['ucretsiz'] ?></div>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3">
                        <div class="text-blue-100">Standart</div>
                        <div class="text-xl font-bold"><?= (int)$stats['standart'] ?></div>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3">
                        <div class="text-blue-100">Premium</div>
                        <div class="text-xl font-bold"><?= (int)$stats['premium'] ?></div>
                    </div>
                    <div class="rounded-xl bg-white/10 p-3">
                        <div class="text-blue-100">Lokal</div>
                        <div class="text-xl font-bold"><?= (int)$stats['lokal'] ?></div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold">Dikkat Gerektirenler</h2>
                    <i class="fas fa-triangle-exclamation text-amber-500"></i>
                </div>
                <div class="space-y-3 text-sm">
                    <a href="#chat" class="flex items-center justify-between rounded-xl border border-slate-100 hover:border-blue-200 px-4 py-3">
                        <span>Açık destek konuşmaları</span>
                        <strong><?= (int)$adminMetrics['open_chats'] ?></strong>
                    </a>
                    <a href="#password-resets" class="flex items-center justify-between rounded-xl border border-slate-100 hover:border-blue-200 px-4 py-3">
                        <span>Bekleyen şifre talepleri</span>
                        <strong><?= (int)$adminMetrics['pending_password_resets'] ?></strong>
                    </a>
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3">
                        <span>14 gün içinde bitecek abonelik</span>
                        <strong><?= (int)$adminMetrics['expiring_subscriptions'] ?></strong>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3">
                        <span>Süresi geçmiş ücretli hesap</span>
                        <strong class="text-red-600"><?= (int)$adminMetrics['overdue_accounts'] ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl p-4 mb-8">
            <form method="get" class="grid md:grid-cols-[1fr_180px_160px_auto_auto] gap-3 items-end">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-500">Firma / yetkili / e-posta ara</span>
                    <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Örn. firma adı, mail, telefon"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-500">Paket</span>
                    <select name="plan" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Tümü</option>
                        <?php foreach (['ucretsiz', 'standart', 'premium', 'lokal'] as $p): ?>
                            <option value="<?= $p ?>" <?= $planFilter === $p ? 'selected' : '' ?>><?= admin_plan_label($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-500">Durum</span>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Tümü</option>
                        <option value="aktif" <?= $statusFilter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="pasif" <?= $statusFilter === 'pasif' ? 'selected' : '' ?>>Pasif</option>
                    </select>
                </label>
                <button class="bg-slate-900 hover:bg-slate-800 text-white rounded-xl px-5 py-2.5 font-semibold text-sm">
                    Filtrele
                </button>
                <a href="admin.php?action=export_users"
                   class="bg-white border border-slate-300 hover:border-blue-400 text-slate-700 rounded-xl px-5 py-2.5 font-semibold text-sm text-center">
                    CSV
                </a>
            </form>
        </section>

        <section id="password-resets" class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold">Şifre Sıfırlama Talepleri</h2>
                    <p class="text-xs text-slate-500 mt-1">Kullanıcıların “Şifremi unuttum” talepleri ve admin tarafından oluşturulan linkler.</p>
                </div>
                <span class="text-xs text-slate-400"><?= count($passwordResetRequests) ?> kayıt</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3">Hesap</th>
                            <th class="text-left px-5 py-3">Talep</th>
                            <th class="text-left px-5 py-3">Durum</th>
                            <th class="text-left px-5 py-3">Link</th>
                            <th class="text-right px-5 py-3">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($passwordResetRequests as $r): ?>
                        <?php
                            $statusClass = $r['durum'] === 'bekliyor'
                                ? 'bg-amber-50 text-amber-700 border-amber-200'
                                : ($r['durum'] === 'kullanildi' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200');
                            $canCreate = !empty($r['firma_id']) && $r['durum'] !== 'kullanildi';
                        ?>
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold"><?= htmlspecialchars($r['firma_adi'] ?: 'Eşleşmeyen e-posta') ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($r['email']) ?></div>
                                <?php if (!empty($r['telefon'])): ?><div class="text-xs text-slate-400"><?= htmlspecialchars($r['telefon']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div><?= htmlspecialchars($r['created_at']) ?></div>
                                <div class="text-xs text-slate-400">IP: <?= htmlspecialchars($r['requested_ip'] ?: '-') ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold <?= $statusClass ?>">
                                    <?= htmlspecialchars($r['durum']) ?>
                                </span>
                                <?php if (!empty($r['admin_adi'])): ?>
                                    <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($r['admin_adi']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-500">
                                <?php if (!empty($r['expires_at'])): ?>
                                    Geçerlilik: <?= htmlspecialchars($r['expires_at']) ?>
                                    <?php if (!empty($r['used_at'])): ?><br>Kullanım: <?= htmlspecialchars($r['used_at']) ?><?php endif; ?>
                                <?php else: ?>
                                    Henüz link oluşturulmadı
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <?php if ($canCreate): ?>
                                    <form method="post" class="inline-block">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                        <input type="hidden" name="action" value="create_reset_link">
                                        <input type="hidden" name="firma_id" value="<?= (int)$r['firma_id'] ?>">
                                        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                        <button class="rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 font-semibold">
                                            Link Oluştur
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Kullanıcı bulunamadı</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$passwordResetRequests): ?>
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Henüz şifre talebi yok.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="chat" class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold">Canlı Sohbet</h2>
                    <p class="text-xs text-slate-500 mt-1">Landing sayfasından hesap oluşturmadan gelen destek konuşmaları.</p>
                </div>
                <span class="text-xs text-slate-400"><?= count($chatConversations) ?> konuşma</span>
            </div>
            <div class="grid lg:grid-cols-[360px_1fr] min-h-[420px]">
                <div class="border-r border-slate-100 divide-y divide-slate-100 max-h-[520px] overflow-y-auto">
                    <?php foreach ($chatConversations as $c): ?>
                        <a href="admin.php?chat_id=<?= (int)$c['id'] ?>#chat"
                           class="block px-5 py-4 hover:bg-blue-50 <?= $selectedChatId === (int)$c['id'] ? 'bg-blue-50' : '' ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-semibold truncate"><?= htmlspecialchars($c['ad_soyad'] ?: 'Ziyaretçi') ?></div>
                                    <div class="text-xs text-slate-500 truncate"><?= htmlspecialchars($c['email'] ?: ($c['telefon'] ?: 'İletişim yok')) ?></div>
                                    <div class="text-xs text-slate-400 truncate mt-1"><?= htmlspecialchars($c['son_mesaj'] ?: '-') ?></div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <?php if ((int)$c['okunmamis'] > 0): ?>
                                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-xs font-bold"><?= (int)$c['okunmamis'] ?></span>
                                    <?php endif; ?>
                                    <div class="text-[10px] text-slate-400 mt-1"><?= htmlspecialchars($c['durum']) ?></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$chatConversations): ?>
                        <div class="px-5 py-12 text-center text-slate-400 text-sm">Henüz sohbet yok.</div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col min-h-[420px]">
                    <?php if ($selectedChat): ?>
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <div class="font-bold"><?= htmlspecialchars($selectedChat['ad_soyad'] ?: 'Ziyaretçi') ?></div>
                                <div class="text-xs text-slate-500">
                                    <?= htmlspecialchars($selectedChat['email'] ?: '-') ?> ·
                                    <?= htmlspecialchars($selectedChat['telefon'] ?: '-') ?> ·
                                    <?= htmlspecialchars($selectedChat['konu'] ?: 'Destek') ?>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                <input type="hidden" name="action" value="close_chat">
                                <input type="hidden" name="conversation_id" value="<?= (int)$selectedChat['id'] ?>">
                                <button class="border border-slate-300 hover:border-red-400 text-slate-700 hover:text-red-600 rounded-lg px-3 py-1.5 text-sm font-semibold">
                                    Konuşmayı Kapat
                                </button>
                            </form>
                        </div>

                        <div class="flex-1 p-5 space-y-3 bg-slate-50/60 max-h-[360px] overflow-y-auto">
                            <?php foreach ($selectedChatMessages as $m): ?>
                                <?php $isAdminMsg = $m['sender_type'] === 'admin'; ?>
                                <div class="flex <?= $isAdminMsg ? 'justify-end' : 'justify-start' ?>">
                                    <div class="max-w-[78%] rounded-2xl px-4 py-3 text-sm <?= $isAdminMsg ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">
                                        <div><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                                        <div class="text-[10px] mt-1 <?= $isAdminMsg ? 'text-blue-100' : 'text-slate-400' ?>">
                                            <?= htmlspecialchars($m['created_at']) ?>
                                            <?= $isAdminMsg && $m['admin_adi'] ? ' · ' . htmlspecialchars($m['admin_adi']) : '' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (($selectedChat['durum'] ?? '') !== 'kapali'): ?>
                            <form method="post" class="p-4 border-t border-slate-100 bg-white flex gap-3">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                <input type="hidden" name="action" value="reply_chat">
                                <input type="hidden" name="conversation_id" value="<?= (int)$selectedChat['id'] ?>">
                                <textarea name="message" rows="2" required placeholder="Cevabınızı yazın..."
                                          class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                                <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-5 font-semibold text-sm">Gönder</button>
                            </form>
                        <?php else: ?>
                            <div class="p-4 border-t border-slate-100 text-center text-sm text-slate-400">Bu konuşma kapalı.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="flex-1 grid place-items-center text-center p-8 text-slate-400">
                            <div>
                                <i class="fas fa-comments text-3xl mb-3"></i>
                                <div class="font-semibold text-slate-500">Bir konuşma seçin</div>
                                <p class="text-sm mt-1">Ziyaretçi mesajları ve cevap alanı burada görünecek.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($selectedFirma): ?>
        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-wide text-blue-600 font-bold">Firma Detayı</div>
                    <h2 class="font-bold text-lg"><?= htmlspecialchars($selectedFirma['firma_adi']) ?></h2>
                    <p class="text-sm text-slate-500">
                        <?= htmlspecialchars($selectedFirma['ad_soyad']) ?> · <?= htmlspecialchars($selectedFirma['email']) ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                        <input type="hidden" name="action" value="support_login">
                        <input type="hidden" name="firma_id" value="<?= (int)$selectedFirma['id'] ?>">
                        <button class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-semibold"
                                onclick="return confirm('Bu firmanın paneline destek modu ile girilsin mi?')">
                            <i class="fas fa-headset"></i> Destek Olarak Gir
                        </button>
                    </form>
                    <a href="admin.php" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Listeye dön</a>
                </div>
            </div>

            <div class="grid md:grid-cols-5 gap-4 p-5 border-b border-slate-100">
                <?php foreach ([
                    ['Müşteri', $firmSummary['musteri'] ?? 0],
                    ['Servis', $firmSummary['servis'] ?? 0],
                    ['Satış', $firmSummary['satis'] ?? 0],
                    ['Tahsilat', admin_money($firmSummary['tahsilat'] ?? 0)],
                    ['Açık Bakiye', admin_money(($firmSummary['servis_bakiye'] ?? 0) + ($firmSummary['satis_bakiye'] ?? 0))],
                ] as [$label, $value]): ?>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                        <div class="text-xs text-slate-500 mb-1"><?= htmlspecialchars($label) ?></div>
                        <div class="text-xl font-extrabold"><?= htmlspecialchars((string)$value) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid lg:grid-cols-3 gap-4 p-5 border-b border-slate-100 bg-slate-50/60">
                <div class="rounded-xl bg-white border border-slate-200 p-4">
                    <div class="text-xs text-slate-500 mb-1">Geciken Taksit</div>
                    <div class="text-xl font-extrabold text-red-600"><?= (int)($firmSummary['geciken_taksit'] ?? 0) ?></div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200 p-4">
                    <div class="text-xs text-slate-500 mb-1">Bağlı Cihaz</div>
                    <div class="text-xl font-extrabold"><?= (int)($firmSummary['cihaz'] ?? 0) ?></div>
                </div>
                <div class="rounded-xl bg-white border border-slate-200 p-4">
                    <div class="text-xs text-slate-500 mb-1">Paket / Durum</div>
                    <div class="text-xl font-extrabold"><?= admin_plan_label($selectedFirma['paket'] ?: 'ucretsiz') ?> · <?= (int)$selectedFirma['aktif'] ? 'Aktif' : 'Pasif' ?></div>
                    <div class="text-xs text-slate-500 mt-1">Bitiş: <?= htmlspecialchars($selectedFirma['abonelik_bitis'] ?: 'Belirtilmemiş') ?></div>
                </div>
            </div>

            <div class="grid lg:grid-cols-[.9fr_1.1fr] gap-4 p-5 border-b border-slate-100">
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="font-bold text-sm mb-3">Abonelik Ödemesi Ekle</div>
                    <form method="post" class="grid sm:grid-cols-2 gap-3">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                        <input type="hidden" name="action" value="add_subscription_payment">
                        <input type="hidden" name="firma_id" value="<?= (int)$selectedFirma['id'] ?>">
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Paket</span>
                            <select name="paket" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <?php foreach (['ucretsiz', 'standart', 'premium', 'lokal'] as $p): ?>
                                    <option value="<?= $p ?>" <?= ($selectedFirma['paket'] ?: 'ucretsiz') === $p ? 'selected' : '' ?>><?= admin_plan_label($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Tutar</span>
                            <input name="tutar" type="number" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Para Birimi</span>
                            <select name="para_birimi" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="TRY">TRY</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Yöntem</span>
                            <input name="odeme_yontemi" placeholder="Kart, havale..." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Dönem Başlangıç</span>
                            <input name="donem_baslangic" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-500">Dönem Bitiş</span>
                            <input name="donem_bitis" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold text-slate-500">Not</span>
                            <textarea name="notlar" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </label>
                        <div class="sm:col-span-2 text-right">
                            <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">
                                Ödemeyi Kaydet
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 font-bold text-sm">Abonelik Ödeme Geçmişi</div>
                    <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                        <?php foreach ($firmSubscriptionPayments as $p): ?>
                            <div class="px-4 py-3 text-sm flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold"><?= htmlspecialchars(admin_plan_label($p['paket'] ?: 'ucretsiz')) ?> · <?= htmlspecialchars(number_format((float)$p['tutar'], 2, ',', '.')) ?> <?= htmlspecialchars($p['para_birimi'] ?: 'TRY') ?></div>
                                    <div class="text-xs text-slate-500">
                                        <?= htmlspecialchars($p['donem_baslangic'] ?: '-') ?> / <?= htmlspecialchars($p['donem_bitis'] ?: '-') ?>
                                        · <?= htmlspecialchars($p['odeme_yontemi'] ?: 'Yöntem yok') ?>
                                    </div>
                                    <?php if (!empty($p['notlar'])): ?><div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($p['notlar']) ?></div><?php endif; ?>
                                </div>
                                <div class="text-right text-xs text-slate-400">
                                    <?= htmlspecialchars($p['created_at']) ?><br>
                                    <?= htmlspecialchars($p['admin_adi'] ?: 'Admin') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$firmSubscriptionPayments): ?>
                            <div class="px-4 py-8 text-center text-slate-400 text-sm">Henüz abonelik ödemesi yok.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-0">
                <div class="border-r border-slate-100">
                    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
                        <div class="font-bold text-sm mb-3">Müşteriler</div>
                        <form method="get" class="flex gap-2">
                            <input type="hidden" name="firma_id" value="<?= (int)$selectedFirma['id'] ?>">
                            <input name="customer_q" value="<?= htmlspecialchars($customerSearch) ?>"
                                   placeholder="Müşteri adı, telefon, e-posta veya adres ara"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-2 text-sm font-semibold">Ara</button>
                        </form>
                    </div>
                    <div class="max-h-[520px] overflow-y-auto divide-y divide-slate-100">
                        <?php foreach ($firmCustomers as $m): ?>
                            <a href="admin.php?firma_id=<?= (int)$selectedFirma['id'] ?>&musteri_id=<?= (int)$m['id'] ?>"
                               class="block px-5 py-4 hover:bg-blue-50 <?= $selectedMusteriId === (int)$m['id'] ? 'bg-blue-50' : '' ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold"><?= htmlspecialchars($m['ad'] . ' ' . $m['soyad']) ?></div>
                                        <div class="text-xs text-slate-500 mt-1">
                                            <?= htmlspecialchars($m['telefon'] ?: 'Telefon yok') ?>
                                            <?= $m['email'] ? ' · ' . htmlspecialchars($m['email']) : '' ?>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-1">
                                            <?= (int)$m['servis_sayisi'] ?> servis ·
                                            <?= (int)$m['satis_sayisi'] ?> satış ·
                                            <?= admin_money($m['toplam_tahsilat']) ?> tahsilat
                                        </div>
                                        <?php $mBakiye = (float)$m['servis_bakiye'] + (float)$m['satis_bakiye']; ?>
                                        <?php if ($mBakiye > 0): ?>
                                            <div class="inline-flex mt-2 rounded-full bg-red-50 text-red-700 border border-red-100 px-2 py-0.5 text-xs font-semibold">
                                                Açık bakiye: <?= admin_money($mBakiye) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-slate-400 whitespace-nowrap">#<?= (int)$m['id'] ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$firmCustomers): ?>
                            <div class="px-5 py-10 text-center text-slate-400 text-sm">Bu firmada müşteri kaydı yok.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <?php if ($customerDetail): ?>
                        <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm">
                            Müşteri Dosyası: <?= htmlspecialchars($customerDetail['ad'] . ' ' . $customerDetail['soyad']) ?>
                        </div>
                        <div class="p-5 space-y-5">
                            <div class="grid lg:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                    <div class="font-bold text-sm text-amber-900 mb-2">Destek Notu Ekle</div>
                                    <form method="post" class="space-y-3">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                        <input type="hidden" name="action" value="add_support_note">
                                        <input type="hidden" name="firma_id" value="<?= (int)$selectedFirma['id'] ?>">
                                        <input type="hidden" name="musteri_id" value="<?= (int)$customerDetail['id'] ?>">
                                        <textarea name="note" rows="3" required
                                                  placeholder="Örn. Müşteri şifre sıfırlama için aradı, telefon bilgisi doğrulandı."
                                                  class="w-full rounded-lg border border-amber-200 px-3 py-2 text-sm"></textarea>
                                        <button class="bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">
                                            Notu Kaydet
                                        </button>
                                    </form>
                                </div>
                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Son Destek Notları</div>
                                    <div class="divide-y divide-slate-100 max-h-44 overflow-y-auto">
                                        <?php foreach ($supportNotes as $n): ?>
                                            <div class="px-4 py-3 text-sm">
                                                <div class="text-slate-700"><?= htmlspecialchars($n['note']) ?></div>
                                                <div class="text-xs text-slate-400 mt-1">
                                                    <?= htmlspecialchars($n['created_at']) ?> · <?= htmlspecialchars($n['admin_adi'] ?? 'Admin') ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!$supportNotes): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Henüz destek notu yok.</div><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <form method="post" class="grid md:grid-cols-2 gap-3">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                <input type="hidden" name="action" value="update_customer">
                                <input type="hidden" name="firma_id" value="<?= (int)$selectedFirma['id'] ?>">
                                <input type="hidden" name="musteri_id" value="<?= (int)$customerDetail['id'] ?>">
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-500">Ad</span>
                                    <input name="ad" value="<?= htmlspecialchars($customerDetail['ad']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-500">Soyad</span>
                                    <input name="soyad" value="<?= htmlspecialchars($customerDetail['soyad']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-500">Telefon</span>
                                    <input name="telefon" value="<?= htmlspecialchars($customerDetail['telefon'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-500">E-posta</span>
                                    <input name="email" value="<?= htmlspecialchars($customerDetail['email'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-xs font-semibold text-slate-500">Adres</span>
                                    <input name="adres" value="<?= htmlspecialchars($customerDetail['adres'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="text-xs font-semibold text-slate-500">Notlar</span>
                                    <textarea name="notlar" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars($customerDetail['notlar'] ?? '') ?></textarea>
                                </label>
                                <div class="md:col-span-2 text-right">
                                    <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 font-semibold text-sm">
                                        Müşteriyi Güncelle
                                    </button>
                                </div>
                            </form>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Servisler</div>
                                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                                        <?php foreach ($customerServices as $s): ?>
                                            <div class="px-4 py-3 text-sm">
                                                <div class="font-semibold">#<?= (int)$s['id'] ?> · <?= htmlspecialchars($s['durum']) ?> · <?= admin_money($s['toplam_tutar']) ?></div>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars($s['created_at']) ?> · Ödeme: <?= htmlspecialchars($s['odeme_durumu']) ?></div>
                                                <?php if ($s['notlar']): ?><div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($s['notlar']) ?></div><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!$customerServices): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Servis yok.</div><?php endif; ?>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Satışlar</div>
                                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                                        <?php foreach ($customerSales as $s): ?>
                                            <div class="px-4 py-3 text-sm">
                                                <div class="font-semibold">#<?= (int)$s['id'] ?> · <?= admin_money($s['toplam_tutar']) ?></div>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars($s['satis_tarihi']) ?> · <?= htmlspecialchars($s['odeme_durumu']) ?></div>
                                                <?php if ($s['seri_no']): ?><div class="text-xs text-slate-500 mt-1">Seri no: <?= htmlspecialchars($s['seri_no']) ?></div><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!$customerSales): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Satış yok.</div><?php endif; ?>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Tahsilatlar</div>
                                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                                        <?php foreach ($customerCollections as $t): ?>
                                            <div class="px-4 py-3 text-sm">
                                                <div class="font-semibold"><?= admin_money($t['tutar']) ?> · <?= htmlspecialchars($t['odeme_yontemi']) ?></div>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars($t['tahsilat_tarihi']) ?> · <?= htmlspecialchars($t['kaynak_tip']) ?> #<?= (int)$t['kaynak_id'] ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!$customerCollections): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Tahsilat yok.</div><?php endif; ?>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Taksitler ve Cihazlar</div>
                                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                                        <?php foreach ($customerInstallments as $t): ?>
                                            <div class="px-4 py-3 text-sm">
                                                <div class="font-semibold">Taksit <?= (int)$t['taksit_no'] ?> · <?= admin_money($t['tutar']) ?></div>
                                                <div class="text-xs text-slate-500"><?= (int)$t['odendi'] ? 'Ödendi' : 'Bekliyor' ?> · Vade: <?= htmlspecialchars($t['vade_tarihi'] ?? '') ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php foreach ($customerDevices as $d): ?>
                                            <div class="px-4 py-3 text-sm">
                                                <div class="font-semibold"><?= htmlspecialchars($d['cihaz_adi']) ?></div>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars(trim(($d['marka'] ?? '') . ' ' . ($d['model'] ?? ''))) ?> · Seri: <?= htmlspecialchars($d['seri_no'] ?? '-') ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!$customerInstallments && !$customerDevices): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Taksit veya cihaz yok.</div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="h-full min-h-[320px] grid place-items-center text-center p-8 text-slate-400">
                            <div>
                                <i class="fas fa-address-card text-3xl mb-3"></i>
                                <div class="font-semibold text-slate-500">Detay için bir müşteri seçin</div>
                                <p class="text-sm mt-1">Servis, satış, tahsilat, taksit ve cihaz kayıtları burada görünecek.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-4 p-5 border-t border-slate-100 bg-white">
                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Son Hareketler</div>
                    <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                        <?php foreach ($firmRecentActivity as $a): ?>
                            <div class="px-4 py-3 text-sm flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold"><?= htmlspecialchars($a['tip']) ?> #<?= (int)$a['id'] ?> · <?= htmlspecialchars($a['aciklama'] ?? '') ?></div>
                                    <div class="text-xs text-slate-400"><?= htmlspecialchars($a['tarih']) ?></div>
                                </div>
                                <div class="font-bold text-slate-700 whitespace-nowrap"><?= admin_money($a['tutar']) ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$firmRecentActivity): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Henüz hareket yok.</div><?php endif; ?>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2 bg-slate-50 font-bold text-sm">Cihaz ve Senkron</div>
                    <div class="px-4 py-3 border-b border-slate-100 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-slate-50 py-2"><div class="font-extrabold text-slate-800"><?= (int)($firmSummary['cihaz'] ?? 0) ?></div><div class="text-slate-400">Toplam</div></div>
                        <div class="rounded-lg bg-blue-50 py-2"><div class="font-extrabold text-blue-700"><?= (int)($firmSummary['mobil_cihaz'] ?? 0) ?></div><div class="text-blue-500">Mobil</div></div>
                        <div class="rounded-lg bg-emerald-50 py-2"><div class="font-extrabold text-emerald-700"><?= (int)($firmSummary['masaustu_cihaz'] ?? 0) ?></div><div class="text-emerald-500">Masaüstü</div></div>
                    </div>
                    <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                        <?php foreach ($firmDevices as $d): ?>
                            <div class="px-4 py-3 text-sm">
                                <?php
                                    $deviceType = $d['device_type'] ?: 'unknown';
                                    $typeLabel = $deviceType === 'mobile' ? 'Mobil' : ($deviceType === 'desktop' ? 'Masaüstü' : 'Bilinmiyor');
                                    $typeClass = $deviceType === 'mobile' ? 'bg-blue-50 text-blue-700' : ($deviceType === 'desktop' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600');
                                ?>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold"><?= htmlspecialchars($d['device_name'] ?: 'Adsız cihaz') ?></div>
                                        <div class="text-xs text-slate-500">Cihaz ID: <?= htmlspecialchars($d['device_id'] ?: '-') ?></div>
                                    </div>
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full <?= $typeClass ?>"><?= $typeLabel ?></span>
                                </div>
                                <div class="text-xs text-slate-400 mt-1">
                                    Oluşturma: <?= htmlspecialchars($d['created_at']) ?> ·
                                    Son bağlantı: <?= htmlspecialchars($d['last_seen_at'] ?: 'Henüz yok') ?>
                                </div>
                                <div class="text-xs text-slate-400 mt-1">IP: <?= htmlspecialchars($d['ip_address'] ?: '-') ?></div>
                                <?php if (!empty($d['user_agent'])): ?>
                                    <div class="text-[11px] text-slate-400 mt-1 truncate" title="<?= htmlspecialchars($d['user_agent']) ?>"><?= htmlspecialchars($d['user_agent']) ?></div>
                                <?php endif; ?>
                                <form method="post" class="mt-2" onsubmit="return confirm('Bu cihazın senkron erişimi iptal edilsin mi?')">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                    <input type="hidden" name="action" value="revoke_device">
                                    <input type="hidden" name="firma_id" value="<?= (int)$selectedFirma['id'] ?>">
                                    <input type="hidden" name="token_id" value="<?= (int)$d['id'] ?>">
                                    <button class="text-xs font-semibold text-red-600 hover:text-red-700">Cihaz erişimini iptal et</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$firmDevices): ?><div class="px-4 py-6 text-center text-slate-400 text-sm">Bağlı cihaz yok.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php elseif ($selectedFirmaId > 0): ?>
            <div class="mb-8 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                Seçilen firma bulunamadı.
            </div>
        <?php endif; ?>

        <section class="grid md:grid-cols-4 gap-4 mb-8">
            <?php foreach ([
                ['Veritabanı', $systemHealth['db_size_mb'] . ' MB', 'fa-database'],
                ['Son Senkron', $systemHealth['last_sync_at'] ?: 'Yok', 'fa-rotate'],
                ['İptal Cihaz', $systemHealth['revoked_devices'], 'fa-ban'],
                ['Hatırlanan Oturum', $systemHealth['remember_tokens'], 'fa-cookie-bite'],
            ] as [$label, $value, $icon]): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-slate-500"><?= htmlspecialchars($label) ?></span>
                        <i class="fas <?= htmlspecialchars($icon) ?> text-slate-400"></i>
                    </div>
                    <div class="font-extrabold text-slate-800 truncate"><?= htmlspecialchars((string)$value) ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if (admin_can('super_admin')): ?>
        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-bold">Admin Ekibi ve Yetkiler</h2>
                <p class="text-xs text-slate-500 mt-1">Destek, finans ve görüntüleme rollerini buradan yönetin.</p>
            </div>
            <div class="grid lg:grid-cols-[.9fr_1.1fr] gap-0">
                <form method="post" class="p-5 border-r border-slate-100 grid sm:grid-cols-2 gap-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                    <input type="hidden" name="action" value="create_admin_user">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold text-slate-500">Ad Soyad</span>
                        <input name="ad_soyad" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold text-slate-500">E-posta</span>
                        <input name="email" type="email" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-500">Şifre</span>
                        <input name="sifre" type="password" minlength="8" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-500">Rol</span>
                        <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="support">Destek</option>
                            <option value="finance">Finans</option>
                            <option value="viewer">Sadece Görüntüleme</option>
                            <option value="super_admin">Süper Admin</option>
                        </select>
                    </label>
                    <div class="sm:col-span-2 text-right">
                        <button class="bg-slate-900 hover:bg-slate-800 text-white rounded-lg px-4 py-2 text-sm font-semibold">Admin Ekle</button>
                    </div>
                </form>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($adminUsers as $au): ?>
                        <form method="post" class="px-5 py-4 flex flex-col md:flex-row md:items-center gap-3">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                            <input type="hidden" name="action" value="update_admin_user">
                            <input type="hidden" name="admin_user_id" value="<?= (int)$au['id'] ?>">
                            <div class="flex-1">
                                <div class="font-semibold"><?= htmlspecialchars($au['ad_soyad']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($au['email']) ?> · Son giriş: <?= htmlspecialchars($au['last_login_at'] ?: '-') ?></div>
                            </div>
                            <select name="role" class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                <?php foreach (['super_admin' => 'Süper Admin', 'support' => 'Destek', 'finance' => 'Finans', 'viewer' => 'Görüntüleme'] as $role => $label): ?>
                                    <option value="<?= $role ?>" <?= ($au['role'] ?: 'super_admin') === $role ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="aktif" value="1" <?= (int)$au['aktif'] ? 'checked' : '' ?>>
                                Aktif
                            </label>
                            <button class="rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 text-sm font-semibold">Kaydet</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold">Admin İşlem Geçmişi</h2>
                    <p class="text-xs text-slate-500 mt-1">Paket, destek, şifre, cihaz ve sohbet işlemlerinin son kayıtları.</p>
                </div>
                <span class="text-xs text-slate-400"><?= count($recentAdminLogs) ?> hareket</span>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recentAdminLogs as $log): ?>
                    <div class="px-5 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2 text-sm">
                        <div>
                            <div class="font-semibold text-slate-800">
                                <?= htmlspecialchars($log['description'] ?: $log['action']) ?>
                            </div>
                            <div class="text-xs text-slate-400 mt-1">
                                <?= htmlspecialchars($log['admin_adi'] ?: 'Admin') ?>
                                <?php if (!empty($log['firma_adi'])): ?>
                                    · <?= htmlspecialchars($log['firma_adi']) ?>
                                <?php endif; ?>
                                · <?= htmlspecialchars($log['ip_address'] ?: '-') ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-100 text-slate-600 px-2.5 py-1 text-xs font-bold"><?= htmlspecialchars($log['action']) ?></span>
                            <span class="text-xs text-slate-400 whitespace-nowrap"><?= htmlspecialchars($log['created_at']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentAdminLogs): ?>
                    <div class="px-5 py-10 text-center text-slate-400 text-sm">Henüz admin hareketi yok.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-bold">Kullanıcılar / Firmalar</h2>
                <span class="text-xs text-slate-400"><?= date('d.m.Y H:i') ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3">Firma</th>
                            <th class="text-left px-5 py-3">Yetkili</th>
                            <th class="text-left px-5 py-3">Paket</th>
                            <th class="text-left px-5 py-3">Abonelik</th>
                            <th class="text-left px-5 py-3">Kayıtlar</th>
                            <th class="text-left px-5 py-3">Senkron</th>
                            <th class="text-left px-5 py-3">Durum</th>
                            <th class="text-right px-5 py-3">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold"><?= htmlspecialchars($u['firma_adi']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($u['created_at']) ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <div><?= htmlspecialchars($u['ad_soyad']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <form method="post" class="flex items-center gap-2 justify-end">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="firma_id" value="<?= (int)$u['id'] ?>">
                                    <select name="paket" class="rounded-lg border border-slate-300 px-2 py-1">
                                        <?php foreach (['ucretsiz', 'standart', 'premium', 'lokal'] as $p): ?>
                                            <option value="<?= $p ?>" <?= ($u['paket'] ?: 'ucretsiz') === $p ? 'selected' : '' ?>>
                                                <?= admin_plan_label($p) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>
                            <td class="px-5 py-4">
                                    <input type="date" name="abonelik_bitis" value="<?= htmlspecialchars($u['abonelik_bitis'] ?? '') ?>"
                                           class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                    <?php if (($u['abonelik_bitis'] ?? '') && strtotime((string)$u['abonelik_bitis']) < strtotime(date('Y-m-d'))): ?>
                                        <div class="text-xs text-red-600 font-semibold mt-1">Süresi dolmuş</div>
                                    <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <?= (int)$u['musteri_sayisi'] ?> müşteri /
                                <?= (int)$u['servis_sayisi'] ?> servis /
                                <?= (int)$u['satis_sayisi'] ?> satış
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold"><?= (int)$u['cihaz_sayisi'] ?> cihaz</div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($u['son_senkron'] ?: 'Son bağlantı yok') ?></div>
                            </td>
                            <td class="px-5 py-4">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="aktif" value="1" <?= (int)$u['aktif'] ? 'checked' : '' ?>>
                                    <span><?= (int)$u['aktif'] ? 'Aktif' : 'Pasif' ?></span>
                                </label>
                            </td>
                            <td class="px-5 py-4 text-right">
                                    <a href="admin.php?firma_id=<?= (int)$u['id'] ?>"
                                       class="inline-flex items-center justify-center border border-slate-300 hover:border-blue-400 text-slate-700 rounded-lg px-3 py-1.5 font-semibold mr-2">
                                        Detay
                                    </a>
                                    <button name="action" value="create_reset_link"
                                            class="border border-amber-300 hover:border-amber-500 text-amber-700 rounded-lg px-3 py-1.5 font-semibold mr-2"
                                            onclick="return confirm('Bu kullanıcı için şifre sıfırlama linki oluşturulsun mu?')">
                                        Şifre Linki
                                    </button>
                                    <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-1.5 font-semibold">
                                        Kaydet
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-slate-400">Henüz kullanıcı yok.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
<?php endif; ?>
</body>
</html>
