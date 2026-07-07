<?php
require_once __DIR__ . '/_base.php';

if (!getenv('STP_DATA_DIR')) {
    json_err('Bu endpoint sadece masaustu modda kullanilir.', 403);
}
if (getenv('STP_LOCAL_ONLY') === '1') {
    json_err('Lokal lifetime surumde bulut senkronizasyonu kapali.', 403);
}

$db = Database::getInstance();
$pdo = $db->getConnection();

function client_state(Database $db): array {
    $state = $db->fetchOne("SELECT * FROM sync_state WHERE id=1");
    return $state ?: [
        'enabled' => 0,
        'server_url' => '',
        'token' => '',
        'last_pull_at' => null,
        'last_push_at' => null,
        'device_id' => '',
    ];
}

function client_http_json(string $url, string $method = 'GET', ?array $body = null, string $token = ''): array {
    $headers = "Content-Type: application/json\r\n";
    if ($token !== '') {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE),
        'timeout' => 20,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new InvalidArgumentException('Sunucuya baglanilamadi.');
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new InvalidArgumentException('Sunucudan gecersiz cevap geldi.');
    }
    if (!($json['success'] ?? false)) {
        throw new InvalidArgumentException($json['message'] ?? 'Sync islemi basarisiz.');
    }
    return $json['data'] ?? [];
}

function client_server_url(string $url): string {
    $url = rtrim(trim($url), '/');
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        throw new InvalidArgumentException('Gecerli bir sunucu URL girin.');
    }
    return $url;
}

function client_tables(): array {
    return [
        'musteriler', 'standart_islemler', 'periyodik_bakimlar', 'servisler',
        'servis_islemleri', 'parcalar', 'servis_parcalari', 'satislar',
        'satis_kalemleri', 'tahsilatlar', 'ayarlar', 'cihazlar',
        'musteri_cihazlari', 'taksitler', 'standart_islem_parcalar',
        'tedarikci_alimlari', 'tedarikci_alim_kalemleri', 'tedarikci_odemeleri',
    ];
}

function client_columns(PDO $pdo, string $table): array {
    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name');
}

function client_apply_pull(Database $db, PDO $pdo, array $changes): int {
    $count = 0;
    foreach (client_tables() as $table) {
        $rows = $changes[$table] ?? [];
        if (!is_array($rows)) continue;

        $cols = client_columns($pdo, $table);
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['uuid'])) continue;
            unset($row['id']);
            if (in_array('firma_id', $cols, true)) {
                $row['firma_id'] = FIRMA_ID;
            }
            $row = array_intersect_key($row, array_flip($cols));
            $existingId = $db->fetchColumn("SELECT id FROM {$table} WHERE uuid=?", [$row['uuid']]);
            if (!$existingId && $table === 'ayarlar' && isset($row['anahtar'])) {
                $existingId = $db->fetchColumn(
                    "SELECT id FROM ayarlar WHERE firma_id=? AND anahtar=?",
                    [FIRMA_ID, $row['anahtar']]
                );
            }

            if ($existingId) {
                $setCols = array_values(array_filter(array_keys($row), fn($c) => $c !== 'uuid'));
                if (!$setCols) continue;
                $set = implode(', ', array_map(fn($c) => "{$c}=?", $setCols));
                $params = array_map(fn($c) => $row[$c], $setCols);
                $params[] = $existingId;
                $db->query("UPDATE {$table} SET {$set}, synced_at=CURRENT_TIMESTAMP WHERE id=?", $params);
            } else {
                $insertCols = array_keys($row);
                $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
                $db->query(
                    "INSERT INTO {$table} (" . implode(',', $insertCols) . ", synced_at) VALUES ({$placeholders}, CURRENT_TIMESTAMP)",
                    array_map(fn($c) => $row[$c], $insertCols)
                );
            }
            $count++;
        }
    }
    return $count;
}

function client_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function client_ref_map(): array {
    return [
        'musteri_id' => 'musteriler',
        'servis_id' => 'servisler',
        'parca_id' => 'parcalar',
        'satis_id' => 'satislar',
        'taksit_id' => 'taksitler',
        'cihaz_id' => 'cihazlar',
        'islem_id' => 'standart_islemler',
        'alim_id' => 'tedarikci_alimlari',
    ];
}

function client_add_refs(Database $db, array $row): array {
    $refs = [];
    foreach (client_ref_map() as $field => $table) {
        if (empty($row[$field])) continue;
        $uuid = $db->fetchColumn("SELECT uuid FROM {$table} WHERE id=?", [(int)$row[$field]]);
        if ($uuid) {
            $refs[$field] = $uuid;
        }
    }
    if ($refs) {
        $row['__refs'] = $refs;
    }
    return $row;
}

function client_collect_push(Database $db, PDO $pdo): array {
    $changes = [];
    foreach (client_tables() as $table) {
        $cols = client_columns($pdo, $table);
        if (!in_array('uuid', $cols, true) || !in_array('synced_at', $cols, true)) {
            continue;
        }

        $dateCol = in_array('updated_at', $cols, true) ? 'updated_at' : (in_array('created_at', $cols, true) ? 'created_at' : null);
        $where = "synced_at IS NULL";
        if ($dateCol) {
            $where .= " OR ({$dateCol} IS NOT NULL AND synced_at IS NOT NULL AND datetime({$dateCol}) > datetime(synced_at))";
        }

        $rows = $db->fetchAll("SELECT * FROM {$table} WHERE {$where}");
        foreach ($rows as $row) {
            if (empty($row['uuid'])) {
                $row['uuid'] = client_uuid();
                $db->query("UPDATE {$table} SET uuid=? WHERE id=?", [$row['uuid'], $row['id']]);
            }
            unset($row['synced_at']);
            $changes[$table][] = client_add_refs($db, $row);
        }
    }
    return $changes;
}

function client_mark_pushed(Database $db, array $applied): void {
    foreach ($applied as $item) {
        $table = $item['table'] ?? '';
        $uuid = $item['uuid'] ?? '';
        if (!in_array($table, client_tables(), true) || $uuid === '') {
            continue;
        }
        $db->query("UPDATE {$table} SET synced_at=CURRENT_TIMESTAMP WHERE uuid=?", [$uuid]);
    }
}

$action = $_GET['action'] ?? 'status';

if (method() === 'GET' && $action === 'status') {
    $state = client_state($db);
    json_ok([
        'enabled' => (int)($state['enabled'] ?? 0),
        'server_url' => $state['server_url'] ?? '',
        'has_token' => !empty($state['token']),
        'last_pull_at' => $state['last_pull_at'] ?? null,
        'last_push_at' => $state['last_push_at'] ?? null,
        'pending' => (int)$db->fetchColumn("SELECT COUNT(*) FROM sync_queue WHERE firma_id=? AND synced_at IS NULL", [FIRMA_ID]),
    ]);
}

if (method() === 'POST' && $action === 'connect') {
    $input = get_input();
    $serverUrl = client_server_url($input['server_url'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    if (!$email || !$password) {
        json_err('E-posta ve sifre gerekli.');
    }

    $state = client_state($db);
    $deviceId = $state['device_id'] ?: bin2hex(random_bytes(16));
    $data = client_http_json($serverUrl . '/api/auth.php?action=desktop_login', 'POST', [
        'email' => $email,
        'sifre' => $password,
        'device_name' => gethostname() ?: 'Desktop',
        'device_id' => $deviceId,
    ]);

    $db->query(
        "INSERT INTO sync_state (id, device_id, server_url, token, enabled, updated_at)
         VALUES (1, ?, ?, ?, 1, CURRENT_TIMESTAMP)
         ON CONFLICT(id) DO UPDATE SET device_id=excluded.device_id, server_url=excluded.server_url,
             token=excluded.token, enabled=1, updated_at=CURRENT_TIMESTAMP",
        [$deviceId, $serverUrl, $data['token']]
    );

    json_ok([
        'server_url' => $serverUrl,
        'firma_adi' => $data['firma_adi'] ?? '',
        'email' => $data['email'] ?? $email,
    ], 'Senkron baglantisi kuruldu.');
}

if (method() === 'POST' && $action === 'run') {
    $state = client_state($db);
    if (empty($state['enabled']) || empty($state['server_url']) || empty($state['token'])) {
        json_err('Once senkron baglantisi kurun.');
    }

    $serverUrl = rtrim($state['server_url'], '/');
    $pushChanges = client_collect_push($db, $pdo);
    $pushCount = 0;
    if ($pushChanges) {
        $pushed = client_http_json($serverUrl . '/api/sync.php?action=push', 'POST', ['changes' => $pushChanges], $state['token']);
        client_mark_pushed($db, $pushed['applied'] ?? []);
        $pushCount = count($pushed['applied'] ?? []);
    }

    $since = $state['last_pull_at'] ? ('&since=' . rawurlencode($state['last_pull_at'])) : '';
    $pulled = client_http_json($serverUrl . '/api/sync.php?action=pull' . $since, 'GET', null, $state['token']);
    $pullCount = client_apply_pull($db, $pdo, $pulled['changes'] ?? []);

    $db->query(
        "UPDATE sync_state SET last_pull_at=?, last_push_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=1",
        [$pulled['server_time'] ?? date('c')]
    );

    json_ok([
        'pulled' => $pullCount,
        'pushed' => $pushCount,
        'server_time' => $pulled['server_time'] ?? null,
    ], 'Senkron tamamlandi.');
}

if (method() === 'POST' && $action === 'disconnect') {
    $db->query("UPDATE sync_state SET enabled=0, token=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=1");
    json_ok(null, 'Senkron baglantisi kapatildi.');
}

json_err('Desteklenmeyen sync client islemi.', 405);
