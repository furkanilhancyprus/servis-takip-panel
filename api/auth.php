<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';

function json_ok($data, string $message = ''): void {
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function get_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return $_POST ?: [];
}

$action = $_GET['action'] ?? '';
$db     = Database::getInstance();

function hesap_kullanilabilir(array $kullanici): bool {
    if (!(int)($kullanici['aktif'] ?? 0)) {
        return false;
    }
    $paket = $kullanici['paket'] ?? '';
    $bitis = $kullanici['abonelik_bitis'] ?? '';
    return !in_array($paket, ['standart', 'premium'], true)
        || $bitis === ''
        || strtotime((string)$bitis) >= strtotime(date('Y-m-d'));
}

function request_ip(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $ip) {
        $ip = trim(explode(',', (string)$ip)[0]);
        if ($ip !== '') return $ip;
    }
    return '';
}

function create_sync_token(Database $db, int $firmaId, string $deviceName = '', string $deviceId = '', string $deviceType = ''): string {
    $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $db->execute(
        "INSERT INTO sync_tokens (firma_id, token_hash, device_name, device_id, device_type, ip_address, user_agent, last_seen_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)",
        [$firmaId, hash('sha256', $token), $deviceName, $deviceId, $deviceType, request_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '']
    );
    return $token;
}

// ── KAYIT ───────────────────────────────────────────────────────────────────
if ($action === 'kayit') {
    $input     = get_input();
    $firmaAdi  = trim($input['firma_adi']  ?? '');
    $adSoyad   = trim($input['ad_soyad']   ?? '');
    $email     = strtolower(trim($input['email']  ?? ''));
    $sifre     = $input['sifre']    ?? '';
    $sifre2    = $input['sifre2']   ?? '';
    $telefon   = trim($input['telefon']  ?? '');
    $paket     = $input['paket'] ?? 'ucretsiz';
    $allowedPackages = ['ucretsiz', 'standart', 'premium'];
    if (!in_array($paket, $allowedPackages, true)) {
        $paket = 'ucretsiz';
    }

    if (!$firmaAdi || !$adSoyad || !$email || !$sifre) {
        json_err('Tüm zorunlu alanları doldurun.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err('Geçerli bir e-posta adresi girin.');
    }
    if (strlen($sifre) < 6) {
        json_err('Şifre en az 6 karakter olmalıdır.');
    }
    if ($sifre !== $sifre2) {
        json_err('Şifreler eşleşmiyor.');
    }

    // E-posta kullanımda mı?
    $mevcut = $db->fetchOne("SELECT id FROM kullanicilar WHERE email = ?", [$email]);
    if ($mevcut) {
        json_err('Bu e-posta adresi zaten kayıtlı.');
    }

    $hash    = password_hash($sifre, PASSWORD_BCRYPT);
    $firmaId = $db->execute(
        "INSERT INTO kullanicilar (firma_adi, ad_soyad, email, sifre, telefon, paket, abonelik_durumu) VALUES (?, ?, ?, ?, ?, ?, 'aktif')",
        [$firmaAdi, $adSoyad, $email, $hash, $telefon, $paket]
    );

    // Varsayılan verileri oluştur
    $db->seedFirmaDefaults($firmaId);

    // Oturumu başlat
    $_SESSION['firma_id']  = $firmaId;
    $_SESSION['firma_adi'] = $firmaAdi;
    $_SESSION['ad_soyad']  = $adSoyad;
    $_SESSION['email']     = $email;

    json_ok(['firma_id' => $firmaId], 'Kayıt başarılı! Hoş geldiniz.');
}

// ── GİRİŞ ───────────────────────────────────────────────────────────────────
if ($action === 'giris') {
    $input = get_input();
    $email = strtolower(trim($input['email'] ?? ''));
    $sifre = $input['sifre'] ?? '';

    if (!$email || !$sifre) {
        json_err('E-posta ve şifre zorunludur.');
    }

    $kullanici = $db->fetchOne(
        "SELECT id, firma_adi, ad_soyad, email, sifre, aktif, paket, abonelik_bitis FROM kullanicilar WHERE email = ?",
        [$email]
    );

    if (!$kullanici) {
        json_err('E-posta veya şifre hatalı.');
    }
    if (!hesap_kullanilabilir($kullanici)) {
        json_err('Hesabınız pasif veya aboneliğiniz sona ermiştir. Lütfen destek ile iletişime geçin.');
    }
    if (!password_verify($sifre, $kullanici['sifre'])) {
        json_err('E-posta veya şifre hatalı.');
    }

    $_SESSION['firma_id']  = $kullanici['id'];
    $_SESSION['firma_adi'] = $kullanici['firma_adi'];
    $_SESSION['ad_soyad']  = $kullanici['ad_soyad'];
    $_SESSION['email']     = $kullanici['email'];

    json_ok(['firma_id' => $kullanici['id']], 'Giriş başarılı.');
}

if ($action === 'desktop_login' || $action === 'mobile_login') {
    $input = get_input();
    $email = strtolower(trim($input['email'] ?? ''));
    $sifre = $input['sifre'] ?? '';
    $deviceName = trim($input['device_name'] ?? '');
    $deviceId = trim($input['device_id'] ?? '');

    if (!$email || !$sifre) {
        json_err('E-posta ve sifre zorunludur.');
    }

    $kullanici = $db->fetchOne(
        "SELECT id, firma_adi, ad_soyad, email, sifre, aktif, paket, abonelik_bitis FROM kullanicilar WHERE email = ?",
        [$email]
    );

    if (!$kullanici || !password_verify($sifre, $kullanici['sifre'])) {
        json_err('E-posta veya sifre hatali.', 401);
    }
    if (!hesap_kullanilabilir($kullanici)) {
        json_err('Hesabiniz pasif veya aboneliginiz sona ermistir.', 403);
    }

    $token = create_sync_token($db, (int)$kullanici['id'], $deviceName, $deviceId, $action === 'mobile_login' ? 'mobile' : 'desktop');
    json_ok([
        'token' => $token,
        'firma_id' => (int)$kullanici['id'],
        'firma_adi' => $kullanici['firma_adi'],
        'ad_soyad' => $kullanici['ad_soyad'],
        'email' => $kullanici['email'],
        'server_time' => date('c'),
    ], $action === 'mobile_login' ? 'Mobil baglantisi basarili.' : 'Masaustu baglantisi basarili.');
}

// ── ÇIKIŞ ───────────────────────────────────────────────────────────────────
if ($action === 'cikis') {
    session_destroy();
    json_ok(null, 'Çıkış yapıldı.');
}

json_err('Geçersiz işlem.', 404);


