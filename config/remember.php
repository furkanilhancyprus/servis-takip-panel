<?php

const REMEMBER_COOKIE = 'stp_remember';
const REMEMBER_DAYS = 30;

function remember_cookie_options(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_set_login(Database $db, array $kullanici): void {
    $selector = bin2hex(random_bytes(12));
    $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

    $db->execute(
        "INSERT INTO remember_tokens (firma_id, selector, token_hash, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)",
        [
            (int)$kullanici['id'],
            $selector,
            hash('sha256', $token),
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]
    );

    setcookie(REMEMBER_COOKIE, $selector . ':' . $token, remember_cookie_options(time() + REMEMBER_DAYS * 86400));
}

function remember_clear(Database $db): void {
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (is_string($cookie) && strpos($cookie, ':') !== false) {
        [$selector] = explode(':', $cookie, 2);
        if ($selector !== '') {
            $db->query("UPDATE remember_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE selector=?", [$selector]);
        }
    }
    setcookie(REMEMBER_COOKIE, '', remember_cookie_options(time() - 3600));
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function remember_try_restore(Database $db): bool {
    if (!empty($_SESSION['firma_id'])) {
        return true;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!is_string($cookie) || strpos($cookie, ':') === false) {
        return false;
    }

    [$selector, $token] = explode(':', $cookie, 2);
    if ($selector === '' || $token === '') {
        return false;
    }

    $row = $db->fetchOne(
        "SELECT rt.id AS token_id, rt.token_hash, rt.expires_at,
                k.id, k.firma_adi, k.ad_soyad, k.email, k.aktif, k.paket, k.abonelik_bitis
         FROM remember_tokens rt
         JOIN kullanicilar k ON k.id=rt.firma_id AND k.deleted_at IS NULL
         WHERE rt.selector=? AND rt.revoked_at IS NULL",
        [$selector]
    );

    if (!$row || strtotime((string)$row['expires_at']) < time()) {
        remember_clear($db);
        return false;
    }

    if (!hash_equals((string)$row['token_hash'], hash('sha256', $token))) {
        remember_clear($db);
        return false;
    }

    $abonelikGecmis = in_array($row['paket'] ?? '', ['standart', 'premium'], true)
        && !empty($row['abonelik_bitis'])
        && strtotime((string)$row['abonelik_bitis']) < strtotime(date('Y-m-d'));
    if (!(int)$row['aktif'] || $abonelikGecmis) {
        remember_clear($db);
        return false;
    }

    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['firma_id'] = (int)$row['id'];
    $_SESSION['firma_adi'] = $row['firma_adi'];
    $_SESSION['ad_soyad'] = $row['ad_soyad'];
    $_SESSION['email'] = $row['email'];
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $db->query("UPDATE remember_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE id=?", [(int)$row['token_id']]);
    return true;
}
