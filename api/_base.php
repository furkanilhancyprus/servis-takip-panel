<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
header('X-Content-Type-Options: nosniff');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin) {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($originHost && $host && strcasecmp($originHost, preg_replace('/:\d+$/', '', $host)) === 0) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Vary: Origin');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';

if (!isset($_SESSION['firma_id'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $tokenHash = hash('sha256', trim($m[1]));
        $dbForAuth = Database::getInstance();
        $tokenRow = $dbForAuth->fetchOne(
            "SELECT st.firma_id, k.firma_adi, k.ad_soyad, k.email
             FROM sync_tokens st
             JOIN kullanicilar k ON k.id=st.firma_id
             WHERE st.token_hash=? AND st.revoked_at IS NULL AND k.aktif=1",
            [$tokenHash]
        );
        if ($tokenRow) {
            $_SESSION['firma_id'] = (int)$tokenRow['firma_id'];
            $_SESSION['firma_adi'] = $tokenRow['firma_adi'];
            $_SESSION['ad_soyad'] = $tokenRow['ad_soyad'];
            $_SESSION['email'] = $tokenRow['email'];
            $dbForAuth->query("UPDATE sync_tokens SET last_seen_at=CURRENT_TIMESTAMP WHERE token_hash=?", [$tokenHash]);
        }
    }

    if (!isset($_SESSION['firma_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Oturum acmaniz gerekiyor.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

define('FIRMA_ID', (int) $_SESSION['firma_id']);
$dbForStatus = Database::getInstance();
$hesapDurumu = $dbForStatus->fetchOne(
    "SELECT aktif, paket, abonelik_bitis FROM kullanicilar WHERE id=? AND deleted_at IS NULL",
    [FIRMA_ID]
);
$abonelikGecmis = $hesapDurumu
    && in_array($hesapDurumu['paket'] ?? '', ['standart', 'premium'], true)
    && !empty($hesapDurumu['abonelik_bitis'])
    && strtotime((string)$hesapDurumu['abonelik_bitis']) < strtotime(date('Y-m-d'));
if ((!$hesapDurumu || !(int)$hesapDurumu['aktif'] || $abonelikGecmis) && empty($_SESSION['admin_support_mode'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hesabiniz aktif degil veya aboneliginiz sona ermis. Lutfen destek ile iletisime gecin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$unsafeMethod = !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'], true);
$hasBearer = isset($authHeader) && preg_match('/Bearer\s+(.+)/i', $authHeader);
if ($unsafeMethod && !getenv('STP_DATA_DIR') && !$hasBearer) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Gecersiz guvenlik anahtari. Sayfayi yenileyip tekrar deneyin.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

set_exception_handler(function (Throwable $e): void {
    $code = $e instanceof InvalidArgumentException ? 400 : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => ($code === 400 || getenv('STP_DEBUG')) ? $e->getMessage() : 'Sunucu hatasi olustu.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

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

function method(): string {
    return $_SERVER['REQUEST_METHOD'];
}
