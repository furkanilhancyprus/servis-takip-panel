<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';

function chat_ok($data = null, string $message = ''): void {
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function chat_err(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function chat_input(): array {
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    return is_array($json) ? $json : ($_POST ?: []);
}

function chat_visitor_id(): string {
    if (empty($_SESSION['support_visitor_id'])) {
        $_SESSION['support_visitor_id'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['support_visitor_id'];
}

function chat_conversation(Database $db, int $id, string $visitorId) {
    return $db->fetchOne(
        "SELECT * FROM support_conversations WHERE id=? AND visitor_id=?",
        [$id, $visitorId]
    );
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$visitorId = chat_visitor_id();

if ($action === 'status') {
    chat_ok([
        'visitor_id' => $visitorId,
        'conversation_id' => $_SESSION['support_conversation_id'] ?? null,
    ]);
}

if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = chat_input();
    $name = trim($input['ad_soyad'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['telefon'] ?? '');
    $subject = trim($input['konu'] ?? 'Destek');
    $message = trim($input['message'] ?? '');

    if ($name === '' || $message === '') {
        chat_err('Ad soyad ve mesaj alanı zorunludur.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        chat_err('Geçerli bir e-posta adresi girin.');
    }

    $conversationId = $db->execute(
        "INSERT INTO support_conversations (visitor_id, ad_soyad, email, telefon, konu) VALUES (?, ?, ?, ?, ?)",
        [$visitorId, $name, $email, $phone, $subject]
    );
    $db->query(
        "INSERT INTO support_messages (conversation_id, sender_type, message) VALUES (?, 'visitor', ?)",
        [$conversationId, $message]
    );
    $_SESSION['support_conversation_id'] = $conversationId;

    chat_ok(['conversation_id' => $conversationId], 'Mesajınız destek ekibine iletildi.');
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = chat_input();
    $conversationId = (int)($input['conversation_id'] ?? ($_SESSION['support_conversation_id'] ?? 0));
    $message = trim($input['message'] ?? '');
    if ($conversationId <= 0 || $message === '') {
        chat_err('Mesaj alanı zorunludur.');
    }
    $conversation = chat_conversation($db, $conversationId, $visitorId);
    if (!$conversation) {
        chat_err('Konuşma bulunamadı.', 404);
    }
    if (($conversation['durum'] ?? '') === 'kapali') {
        chat_err('Bu konuşma kapatılmıştır.');
    }

    $db->query(
        "INSERT INTO support_messages (conversation_id, sender_type, message) VALUES (?, 'visitor', ?)",
        [$conversationId, $message]
    );
    $db->query(
        "UPDATE support_conversations SET durum='acik', last_message_at=CURRENT_TIMESTAMP WHERE id=?",
        [$conversationId]
    );
    chat_ok(['conversation_id' => $conversationId], 'Mesaj gönderildi.');
}

if ($action === 'messages') {
    $conversationId = (int)($_GET['conversation_id'] ?? ($_SESSION['support_conversation_id'] ?? 0));
    if ($conversationId <= 0) {
        chat_ok(['conversation_id' => null, 'messages' => []]);
    }
    $conversation = chat_conversation($db, $conversationId, $visitorId);
    if (!$conversation) {
        chat_err('Konuşma bulunamadı.', 404);
    }
    $messages = $db->fetchAll(
        "SELECT id, sender_type, message, created_at FROM support_messages WHERE conversation_id=? ORDER BY id ASC",
        [$conversationId]
    );
    $db->query(
        "UPDATE support_messages SET read_at=CURRENT_TIMESTAMP WHERE conversation_id=? AND sender_type='admin' AND read_at IS NULL",
        [$conversationId]
    );
    chat_ok([
        'conversation' => $conversation,
        'messages' => $messages,
    ]);
}

chat_err('Geçersiz işlem.', 404);
