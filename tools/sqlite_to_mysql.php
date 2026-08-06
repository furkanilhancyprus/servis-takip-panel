<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$options = getopt('', ['source::', 'fresh', 'yes']);
$sourcePath = $options['source'] ?? (__DIR__ . '/../database/musteri-takip.db');
$fresh = array_key_exists('fresh', $options);
$yes = array_key_exists('yes', $options);

if (!$yes) {
    fwrite(STDERR, "Import icin --yes parametresi gerekli. Once SQLite yedegi aldiginizdan emin olun.\n");
    exit(1);
}

if (!is_file($sourcePath)) {
    fwrite(STDERR, "SQLite kaynak dosyasi bulunamadi: {$sourcePath}\n");
    exit(1);
}

$target = Database::getInstance();
if (!$target->isMysql()) {
    fwrite(STDERR, "Hedef veritabani MySQL degil. .env icinde STP_DB_DRIVER=mysql olmali.\n");
    exit(1);
}

$targetPdo = $target->getConnection();
$sourcePdo = new PDO('sqlite:' . $sourcePath);
$sourcePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sourcePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$tables = [
    'kullanicilar',
    'admin_users',
    'musteriler',
    'standart_islemler',
    'periyodik_bakimlar',
    'parcalar',
    'cihazlar',
    'servisler',
    'servis_islemleri',
    'servis_parcalari',
    'satislar',
    'satis_kalemleri',
    'taksitler',
    'tahsilatlar',
    'musteri_cihazlari',
    'standart_islem_parcalar',
    'ayarlar',
    'rapor_log',
    'tedarikciler',
    'tedarikci_alimlari',
    'tedarikci_alim_kalemleri',
    'tedarikci_odemeleri',
    'support_conversations',
    'support_messages',
    'password_reset_requests',
    'admin_activity_logs',
    'subscription_payments',
    'sync_tokens',
    'remember_tokens',
    'sync_state',
    'sync_queue',
];

function source_columns(PDO $pdo, string $table): array {
    $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    return array_column($rows, 'name');
}

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function qident(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

$targetPdo->exec('SET FOREIGN_KEY_CHECKS=0');

if ($fresh) {
    foreach (array_reverse($tables) as $table) {
        if (!$target->columns($table)) {
            continue;
        }
        $targetPdo->exec('DELETE FROM ' . qident($table));
        $targetPdo->exec('ALTER TABLE ' . qident($table) . ' AUTO_INCREMENT = 1');
    }
}

$total = 0;
$summary = [];

foreach ($tables as $table) {
    if (!table_exists($sourcePdo, $table)) {
        continue;
    }

    $sourceCols = source_columns($sourcePdo, $table);
    $targetCols = $target->columns($table);
    $cols = array_values(array_intersect($sourceCols, $targetCols));
    if (!$cols) {
        continue;
    }

    $rows = $sourcePdo->query('SELECT ' . implode(', ', array_map(static fn($col) => qident($col), $cols)) . ' FROM ' . qident($table))->fetchAll();
    if (!$rows) {
        $summary[$table] = 0;
        continue;
    }

    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $colSql = implode(', ', array_map(static fn($col) => qident($col), $cols));
    $updates = array_values(array_filter($cols, static fn($col) => $col !== 'id'));
    $updateSql = $updates
        ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn($col) => qident($col) . '=VALUES(' . qident($col) . ')', $updates))
        : '';
    $stmt = $targetPdo->prepare('INSERT INTO ' . qident($table) . " ({$colSql}) VALUES ({$placeholders}){$updateSql}");

    foreach ($rows as $row) {
        $stmt->execute(array_map(static fn($col) => $row[$col] ?? null, $cols));
        $total++;
    }

    if (in_array('id', $cols, true)) {
        $maxId = (int)$targetPdo->query('SELECT COALESCE(MAX(id),0) FROM ' . qident($table))->fetchColumn();
        if ($maxId > 0) {
            $targetPdo->exec('ALTER TABLE ' . qident($table) . ' AUTO_INCREMENT = ' . ($maxId + 1));
        }
    }

    $summary[$table] = count($rows);
}

$targetPdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo "SQLite -> MySQL import tamamlandi.\n";
echo "Kaynak: {$sourcePath}\n";
echo "Toplam satir: {$total}\n";
foreach ($summary as $table => $count) {
    echo str_pad($table, 32) . $count . "\n";
}
