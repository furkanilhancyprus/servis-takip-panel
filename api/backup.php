<?php
require_once __DIR__ . '/_base.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$tables = [
    'ayarlar',
    'parcalar',
    'cihazlar',
    'musteriler',
    'standart_islemler',
    'standart_islem_parcalar',
    'periyodik_bakimlar',
    'servisler',
    'servis_islemleri',
    'servis_parcalari',
    'satislar',
    'satis_kalemleri',
    'taksitler',
    'tahsilatlar',
    'musteri_cihazlari',
];

$referenceMap = [
    'cihazlar' => ['parca_id' => 'parcalar'],
    'standart_islem_parcalar' => ['islem_id' => 'standart_islemler', 'parca_id' => 'parcalar'],
    'periyodik_bakimlar' => ['musteri_id' => 'musteriler'],
    'servisler' => ['musteri_id' => 'musteriler'],
    'servis_islemleri' => ['servis_id' => 'servisler'],
    'servis_parcalari' => ['servis_id' => 'servisler', 'parca_id' => 'parcalar'],
    'satislar' => ['musteri_id' => 'musteriler', 'cihaz_id' => 'cihazlar'],
    'satis_kalemleri' => ['satis_id' => 'satislar', 'parca_id' => 'parcalar'],
    'taksitler' => ['satis_id' => 'satislar', 'musteri_id' => 'musteriler'],
    'tahsilatlar' => ['musteri_id' => 'musteriler'],
    'musteri_cihazlari' => ['musteri_id' => 'musteriler', 'cihaz_id' => 'cihazlar', 'satis_id' => 'satislar'],
];

function backup_columns(PDO $pdo, string $table): array {
    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name');
}

function backup_has_column(PDO $pdo, string $table, string $column): bool {
    return in_array($column, backup_columns($pdo, $table), true);
}

function backup_rows(Database $db, PDO $pdo, string $table, int $firmaId): array {
    $cols = backup_columns($pdo, $table);
    if (!$cols) return [];

    $where = [];
    $params = [];

    if (in_array('firma_id', $cols, true)) {
        $where[] = 'firma_id=?';
        $params[] = $firmaId;
    } elseif ($table === 'servis_islemleri') {
        $where[] = 'servis_id IN (SELECT id FROM servisler WHERE firma_id=?)';
        $params[] = $firmaId;
    } elseif ($table === 'servis_parcalari') {
        $where[] = 'servis_id IN (SELECT id FROM servisler WHERE firma_id=?)';
        $params[] = $firmaId;
    } elseif ($table === 'standart_islem_parcalar') {
        $where[] = 'islem_id IN (SELECT id FROM standart_islemler WHERE firma_id=?)';
        $params[] = $firmaId;
    } elseif ($table === 'satis_kalemleri') {
        $where[] = 'satis_id IN (SELECT id FROM satislar WHERE firma_id=?)';
        $params[] = $firmaId;
    }

    if (in_array('deleted_at', $cols, true)) {
        $where[] = 'deleted_at IS NULL';
    }

    $sql = "SELECT * FROM {$table}";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id ASC';
    return $db->fetchAll($sql, $params);
}

function backup_strip_import_row(array $row, array $cols, int $firmaId): array {
    unset($row['id']);
    if (in_array('firma_id', $cols, true)) $row['firma_id'] = $firmaId;
    if (in_array('synced_at', $cols, true)) $row['synced_at'] = null;
    return array_intersect_key($row, array_flip($cols));
}

function backup_upsert_row(Database $db, PDO $pdo, string $table, array $row, int $oldId, int $firmaId): int {
    $cols = backup_columns($pdo, $table);
    $row = backup_strip_import_row($row, $cols, $firmaId);

    $existingId = null;
    if (!empty($row['uuid']) && in_array('uuid', $cols, true)) {
        $existingId = $db->fetchColumn("SELECT id FROM {$table} WHERE uuid=?", [$row['uuid']]);
    }
    if (!$existingId && $table === 'ayarlar' && isset($row['anahtar']) && in_array('firma_id', $cols, true)) {
        $existingId = $db->fetchColumn("SELECT id FROM ayarlar WHERE firma_id=? AND anahtar=?", [$firmaId, $row['anahtar']]);
    }

    if ($existingId) {
        $setCols = array_values(array_filter(array_keys($row), fn($c) => $c !== 'id'));
        if ($setCols) {
            $set = implode(', ', array_map(fn($c) => "{$c}=?", $setCols));
            $params = array_map(fn($c) => $row[$c], $setCols);
            $params[] = $existingId;
            $db->query("UPDATE {$table} SET {$set} WHERE id=?", $params);
        }
        return (int)$existingId;
    }

    $insertCols = array_keys($row);
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $params = array_map(fn($c) => $row[$c], $insertCols);
    return $db->execute("INSERT INTO {$table} (" . implode(',', $insertCols) . ") VALUES ({$placeholders})", $params);
}

$action = $_GET['action'] ?? 'export';

if ($action === 'export' && method() === 'GET') {
    $firma = $db->fetchOne(
        "SELECT id, firma_adi, ad_soyad, email, telefon, paket, created_at FROM kullanicilar WHERE id=?",
        [FIRMA_ID]
    );
    $payload = [
        'type' => 'servis_takip_panel_backup',
        'version' => 1,
        'created_at' => date('c'),
        'source' => getenv('STP_LOCAL_ONLY') === '1' ? 'local' : 'web',
        'firma' => $firma ?: ['id' => FIRMA_ID],
        'tables' => [],
    ];
    foreach ($tables as $table) {
        $payload['tables'][$table] = backup_rows($db, $pdo, $table, FIRMA_ID);
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $safeName = preg_replace('/[^a-z0-9_-]+/i', '-', (string)($firma['firma_adi'] ?? 'servis-takip-panel'));
    header_remove('Content-Type');
    header('Content-Type: application/vnd.servistakippanel.backup+json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . trim($safeName, '-') . '-' . date('Ymd-His') . '.stpbackup"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

if ($action === 'import' && method() === 'POST') {
    $input = get_input();
    $payload = null;
    if (!empty($input['backup'])) {
        $payload = is_array($input['backup']) ? $input['backup'] : json_decode((string)$input['backup'], true);
    } else {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
    }

    if (!is_array($payload) || ($payload['type'] ?? '') !== 'servis_takip_panel_backup') {
        json_err('Geçersiz yedek dosyası.');
    }
    if (empty($payload['tables']) || !is_array($payload['tables'])) {
        json_err('Yedek dosyasında aktarılacak veri bulunamadı.');
    }

    $idMap = [];
    $stats = ['inserted_or_updated' => 0, 'tables' => []];
    $pdo->beginTransaction();
    try {
        foreach ($tables as $table) {
            $stats['tables'][$table] = 0;
            $rows = $payload['tables'][$table] ?? [];
            if (!is_array($rows)) continue;

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $oldId = (int)($row['id'] ?? 0);

                foreach (($referenceMap[$table] ?? []) as $field => $refTable) {
                    if (!empty($row[$field]) && isset($idMap[$refTable][(int)$row[$field]])) {
                        $row[$field] = $idMap[$refTable][(int)$row[$field]];
                    }
                }

                if ($table === 'tahsilatlar' && !empty($row['kaynak_id'])) {
                    $sourceTable = ($row['kaynak_tip'] ?? '') === 'servis' ? 'servisler' : (($row['kaynak_tip'] ?? '') === 'satis' ? 'satislar' : '');
                    if ($sourceTable && isset($idMap[$sourceTable][(int)$row['kaynak_id']])) {
                        $row['kaynak_id'] = $idMap[$sourceTable][(int)$row['kaynak_id']];
                    }
                }

                $newId = backup_upsert_row($db, $pdo, $table, $row, $oldId, FIRMA_ID);
                if ($oldId > 0) $idMap[$table][$oldId] = $newId;
                $stats['inserted_or_updated']++;
                $stats['tables'][$table]++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_ok($stats, 'Yedek içe aktarıldı.');
}

json_err('Desteklenmeyen yedek işlemi.', 405);
