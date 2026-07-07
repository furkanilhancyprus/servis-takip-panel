<?php
require_once __DIR__ . '/_base.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$tables = [
    'kullanicilar',
    'musteriler',
    'standart_islemler',
    'periyodik_bakimlar',
    'servisler',
    'servis_islemleri',
    'servis_parcalari',
    'parcalar',
    'satislar',
    'satis_kalemleri',
    'tahsilatlar',
    'ayarlar',
    'cihazlar',
    'musteri_cihazlari',
    'taksitler',
    'standart_islem_parcalar',
    'tedarikci_alimlari',
    'tedarikci_alim_kalemleri',
    'tedarikci_odemeleri',
];

function sync_table_allowed(string $table, array $tables): bool {
    return in_array($table, $tables, true);
}

function sync_columns(PDO $pdo, string $table): array {
    $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    return array_column($cols, 'name');
}

function sync_has_column(PDO $pdo, string $table, string $column): bool {
    return in_array($column, sync_columns($pdo, $table), true);
}

function sync_ref_map(): array {
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

function sync_resolve_refs(Database $db, string $table, array $row, array $refs, int $firmaId): array {
    if ($table === 'tahsilatlar' && !empty($refs['kaynak_id']) && !empty($refs['kaynak_table'])) {
        $refTable = (string)$refs['kaynak_table'];
        if (in_array($refTable, ['servisler', 'satislar'], true)) {
            $mapped = $db->fetchColumn(
                "SELECT id FROM {$refTable} WHERE uuid=? AND firma_id=?",
                [$refs['kaynak_id'], $firmaId]
            );
            if ($mapped) {
                $row['kaynak_id'] = (int)$mapped;
            }
        }
    }

    foreach (sync_ref_map() as $field => $refTable) {
        if (empty($refs[$field])) continue;

        $hasFirma = in_array('firma_id', sync_columns($db->getConnection(), $refTable), true);
        $sql = "SELECT id FROM {$refTable} WHERE uuid=?";
        $params = [$refs[$field]];
        if ($hasFirma) {
            $sql .= " AND firma_id=?";
            $params[] = $firmaId;
        }
        $mapped = $db->fetchColumn($sql, $params);
        if ($mapped) {
            $row[$field] = (int)$mapped;
        }
    }
    return $row;
}

function sync_pull_scope(string $table): ?array {
    return match ($table) {
        'periyodik_bakimlar' => ['JOIN musteriler scope_m ON scope_m.id=periyodik_bakimlar.musteri_id', 'scope_m.firma_id'],
        'servis_islemleri' => ['JOIN servisler scope_s ON scope_s.id=servis_islemleri.servis_id', 'scope_s.firma_id'],
        'servis_parcalari' => ['JOIN servisler scope_s ON scope_s.id=servis_parcalari.servis_id', 'scope_s.firma_id'],
        'satis_kalemleri' => ['JOIN satislar scope_st ON scope_st.id=satis_kalemleri.satis_id', 'scope_st.firma_id'],
        'standart_islem_parcalar' => ['JOIN standart_islemler scope_si ON scope_si.id=standart_islem_parcalar.islem_id', 'scope_si.firma_id'],
        'tedarikci_alim_kalemleri' => ['JOIN tedarikci_alimlari scope_ta ON scope_ta.id=tedarikci_alim_kalemleri.alim_id', 'scope_ta.firma_id'],
        'tedarikci_odemeleri' => ['JOIN tedarikci_alimlari scope_ta ON scope_ta.id=tedarikci_odemeleri.alim_id', 'scope_ta.firma_id'],
        default => null,
    };
}

$action = $_GET['action'] ?? 'status';

if (method() === 'GET' && $action === 'status') {
    json_ok([
        'server_time' => date('c'),
        'firma_id' => FIRMA_ID,
        'tables' => $tables,
    ]);
}

if (method() === 'GET' && $action === 'pull') {
    $since = trim($_GET['since'] ?? '');
    $changes = [];

    foreach ($tables as $table) {
        $hasFirma = sync_has_column($pdo, $table, 'firma_id');
        $hasUpdated = sync_has_column($pdo, $table, 'updated_at');
        $hasCreated = sync_has_column($pdo, $table, 'created_at');
        $dateExpr = $hasUpdated ? 'updated_at' : ($hasCreated ? 'created_at' : null);

        $scope = sync_pull_scope($table);
        $sql = "SELECT {$table}.* FROM {$table}";
        if ($scope) {
            $sql .= " {$scope[0]}";
        }
        $sql .= " WHERE 1=1";
        $params = [];

        if ($hasFirma) {
            $sql .= " AND {$table}.firma_id=?";
            $params[] = FIRMA_ID;
        } elseif ($table === 'kullanicilar') {
            $sql .= " AND {$table}.id=?";
            $params[] = FIRMA_ID;
        } elseif ($scope) {
            $sql .= " AND {$scope[1]}=?";
            $params[] = FIRMA_ID;
        }

        if ($since !== '' && $dateExpr !== null) {
            $sql .= " AND ({$table}.{$dateExpr} > ? OR {$table}.deleted_at > ?)";
            $params[] = $since;
            $params[] = $since;
        }

        $changes[$table] = $db->fetchAll($sql, $params);
    }

    json_ok([
        'server_time' => date('c'),
        'changes' => $changes,
    ]);
}

if (method() === 'POST' && $action === 'push') {
    $input = get_input();
    $changes = $input['changes'] ?? [];
    if (!is_array($changes)) {
        json_err('Gecersiz senkron paketi.');
    }

    $applied = [];
    $pdo->beginTransaction();
    try {
        foreach ($changes as $table => $rows) {
            if (!sync_table_allowed((string)$table, $tables) || !is_array($rows)) {
                continue;
            }

            $cols = sync_columns($pdo, $table);
            if (!in_array('uuid', $cols, true)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['uuid'])) {
                    continue;
                }

                $refs = is_array($row['__refs'] ?? null) ? $row['__refs'] : [];
                unset($row['__refs']);
                unset($row['id']);
                unset($row['synced_at']);
                if (in_array('firma_id', $cols, true)) {
                    $row['firma_id'] = FIRMA_ID;
                } elseif ($table === 'kullanicilar') {
                    continue;
                }

                $row = sync_resolve_refs($db, (string)$table, $row, $refs, FIRMA_ID);
                $row = array_intersect_key($row, array_flip($cols));
                $existingId = null;
                if (in_array('firma_id', $cols, true)) {
                    $existingId = $db->fetchColumn("SELECT id FROM {$table} WHERE uuid=? AND firma_id=?", [$row['uuid'], FIRMA_ID]);
                } else {
                    $existingId = $db->fetchColumn("SELECT id FROM {$table} WHERE uuid=?", [$row['uuid']]);
                }
                if (!$existingId && $table === 'ayarlar' && isset($row['anahtar'])) {
                    $existingId = $db->fetchColumn(
                        "SELECT id FROM ayarlar WHERE firma_id=? AND anahtar=?",
                        [FIRMA_ID, $row['anahtar']]
                    );
                }

                if ($existingId) {
                    $setCols = array_keys($row);
                    $setCols = array_values(array_filter($setCols, fn($c) => $c !== 'uuid'));
                    if (!$setCols) continue;
                    $set = implode(', ', array_map(fn($c) => "{$c}=?", $setCols));
                    $params = array_map(fn($c) => $row[$c], $setCols);
                    $params[] = $existingId;
                    $db->query("UPDATE {$table} SET {$set}, synced_at=CURRENT_TIMESTAMP WHERE id=?", $params);
                } else {
                    $insertCols = array_keys($row);
                    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
                    $colSql = implode(',', $insertCols);
                    $params = array_map(fn($c) => $row[$c], $insertCols);
                    $db->query("INSERT INTO {$table} ({$colSql}, synced_at) VALUES ({$placeholders}, CURRENT_TIMESTAMP)", $params);
                }

                $applied[] = ['table' => $table, 'uuid' => $row['uuid']];
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_ok([
        'server_time' => date('c'),
        'applied' => $applied,
    ]);
}

json_err('Desteklenmeyen sync islemi.', 405);
