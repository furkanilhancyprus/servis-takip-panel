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
        "SELECT id, ad_soyad, email, sifre, aktif FROM admin_users WHERE email=?",
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
    $db->query("UPDATE admin_users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?", [$admin['id']]);
    admin_redirect('Giriş başarılı.');
}

if ($action === 'logout') {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    admin_redirect('Çıkış yapıldı.');
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
    admin_redirect('Kullanıcı güncellendi.');
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
    header('Location: admin.php?chat_id=' . $conversationId . '#chat');
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

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
$supportNotes = [];
$customerDetail = false;
$customerServices = [];
$customerSales = [];
$customerCollections = [];
$customerInstallments = [];
$customerDevices = [];
$chatConversations = [];
$selectedChat = false;
$selectedChatMessages = [];
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
                <span class="text-sm text-slate-500"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
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

        <section class="grid md:grid-cols-5 gap-4 mb-8">
            <?php foreach ([
                ['Toplam Firma', $stats['toplam'], 'fa-building'],
                ['Aktif', $stats['aktif'], 'fa-circle-check'],
                ['Ücretsiz', $stats['ucretsiz'], 'fa-gift'],
                ['Standart', $stats['standart'], 'fa-layer-group'],
                ['Premium', $stats['premium'], 'fa-crown'],
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

        <section class="bg-white border border-slate-200 rounded-2xl p-4 mb-8">
            <form method="get" class="grid md:grid-cols-[1fr_180px_160px_auto] gap-3 items-end">
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
            </form>
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
