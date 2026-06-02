<?php
class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        // Masaüstü uygulamada DB kullanıcının AppData klasöründe tutulur
        // Web'de ise proje içindeki database/ klasöründe
        $envDataDir = getenv('STP_DATA_DIR');
        if ($envDataDir && is_dir($envDataDir)) {
            $dbDir  = rtrim($envDataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'database';
        } else {
            $dbDir  = __DIR__ . '/../database';
        }
        $dbPath = $dbDir . '/musteri-takip.db';

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->pdo = new PDO("sqlite:$dbPath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');

        $this->initTables();
        $this->runMigrations();
        $this->ensureSyncMetadata();
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): array|false {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn(string $sql, array $params = []): mixed {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function execute(string $sql, array $params = []): int {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    private function initTables(): void {
        // ── Kullanıcılar (Firmalar / Tenants) ──────────────────────────────
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS kullanicilar (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_adi   TEXT NOT NULL,
                ad_soyad    TEXT NOT NULL,
                email       TEXT UNIQUE NOT NULL,
                sifre       TEXT NOT NULL,
                telefon     TEXT,
                paket       TEXT DEFAULT 'ucretsiz',
                abonelik_durumu TEXT DEFAULT 'aktif',
                abonelik_bitis DATE,
                aktif       INTEGER DEFAULT 1,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                ad_soyad    TEXT NOT NULL,
                email       TEXT UNIQUE NOT NULL,
                sifre       TEXT NOT NULL,
                aktif       INTEGER DEFAULT 1,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login_at DATETIME
            );

            CREATE TABLE IF NOT EXISTS admin_support_notes (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id    INTEGER,
                firma_id    INTEGER NOT NULL,
                musteri_id  INTEGER,
                note        TEXT NOT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_id) REFERENCES admin_users(id),
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS support_conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                visitor_id  TEXT NOT NULL,
                ad_soyad    TEXT,
                email       TEXT,
                telefon     TEXT,
                konu        TEXT,
                durum       TEXT DEFAULT 'acik',
                last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                closed_at   DATETIME
            );

            CREATE TABLE IF NOT EXISTS support_messages (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                sender_type     TEXT NOT NULL CHECK(sender_type IN ('visitor','admin','system')),
                admin_id        INTEGER,
                message         TEXT NOT NULL,
                read_at         DATETIME,
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES admin_users(id)
            );
        ");

        // ── Ana tablolar (firma_id ile) ─────────────────────────────────────
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS musteriler (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id    INTEGER NOT NULL DEFAULT 0,
                ad          TEXT NOT NULL,
                soyad       TEXT NOT NULL,
                telefon     TEXT,
                email       TEXT,
                adres       TEXT,
                notlar      TEXT,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS standart_islemler (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id        INTEGER NOT NULL DEFAULT 0,
                islem_adi       TEXT NOT NULL,
                varsayilan_fiyat REAL DEFAULT 0,
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS periyodik_bakimlar (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                musteri_id          INTEGER,
                aktif               INTEGER DEFAULT 1,
                periyot_ay          INTEGER DEFAULT 6,
                son_bakim_tarihi    DATE,
                sonraki_bakim_tarihi DATE,
                hatirlatma_gun      INTEGER DEFAULT 7,
                notlar              TEXT,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS servisler (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id            INTEGER NOT NULL DEFAULT 0,
                musteri_id          INTEGER,
                servis_tipi         TEXT CHECK(servis_tipi IN ('ariza','periyodik_bakim')),
                durum               TEXT DEFAULT 'tamamlanan',
                oncelik             TEXT DEFAULT 'normal',
                toplam_tutar        REAL DEFAULT 0,
                odeme_durumu        TEXT DEFAULT 'odenmedi',
                odenen_tutar        REAL DEFAULT 0,
                notlar              TEXT,
                created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
                tamamlanma_tarihi   DATE,
                FOREIGN KEY (firma_id)   REFERENCES kullanicilar(id) ON DELETE CASCADE,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id)
            );

            CREATE TABLE IF NOT EXISTS servis_islemleri (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                servis_id   INTEGER,
                islem       TEXT NOT NULL,
                tutar       REAL DEFAULT 0,
                FOREIGN KEY (servis_id) REFERENCES servisler(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS parcalar (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id              INTEGER NOT NULL DEFAULT 0,
                parca_adi             TEXT NOT NULL,
                marka                 TEXT,
                birim_fiyat           REAL DEFAULT 0,
                stok_miktari          INTEGER DEFAULT 0,
                kritik_stok_seviyesi  INTEGER DEFAULT 5,
                tedarikci             TEXT,
                created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS servis_parcalari (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                servis_id   INTEGER,
                parca_id    INTEGER,
                miktar      INTEGER DEFAULT 1,
                birim_fiyat REAL DEFAULT 0,
                FOREIGN KEY (servis_id) REFERENCES servisler(id) ON DELETE CASCADE,
                FOREIGN KEY (parca_id)  REFERENCES parcalar(id)
            );

            CREATE TABLE IF NOT EXISTS satislar (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id        INTEGER NOT NULL DEFAULT 0,
                musteri_id      INTEGER NOT NULL,
                toplam_tutar    REAL DEFAULT 0,
                odeme_durumu    TEXT DEFAULT 'odenmedi',
                odenen_tutar    REAL DEFAULT 0,
                notlar          TEXT,
                satis_tarihi    DATE DEFAULT (date('now')),
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id)   REFERENCES kullanicilar(id) ON DELETE CASCADE,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id)
            );

            CREATE TABLE IF NOT EXISTS satis_kalemleri (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                satis_id    INTEGER NOT NULL,
                urun_adi    TEXT NOT NULL,
                miktar      INTEGER DEFAULT 1,
                birim_fiyat REAL DEFAULT 0,
                parca_id    INTEGER,
                FOREIGN KEY (satis_id) REFERENCES satislar(id) ON DELETE CASCADE,
                FOREIGN KEY (parca_id) REFERENCES parcalar(id)
            );

            CREATE TABLE IF NOT EXISTS tahsilatlar (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id          INTEGER NOT NULL DEFAULT 0,
                musteri_id        INTEGER NOT NULL,
                kaynak_tip        TEXT NOT NULL,
                kaynak_id         INTEGER NOT NULL,
                tutar             REAL NOT NULL DEFAULT 0,
                odeme_yontemi     TEXT DEFAULT 'nakit',
                notlar            TEXT,
                tahsilat_tarihi   DATE DEFAULT (date('now')),
                created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id)   REFERENCES kullanicilar(id) ON DELETE CASCADE,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id)
            );

            CREATE TABLE IF NOT EXISTS ayarlar (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id    INTEGER NOT NULL DEFAULT 0,
                anahtar     TEXT NOT NULL,
                deger       TEXT,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(firma_id, anahtar),
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS rapor_log (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id            INTEGER NOT NULL DEFAULT 0,
                rapor_tipi          TEXT NOT NULL,
                dosya_adi           TEXT,
                olusturma_tarihi    DATETIME DEFAULT CURRENT_TIMESTAMP,
                filtre_bilgisi      TEXT,
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS cihazlar (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id            INTEGER NOT NULL DEFAULT 0,
                parca_id            INTEGER,
                cihaz_adi           TEXT NOT NULL,
                marka               TEXT,
                model               TEXT,
                varsayilan_fiyat    REAL DEFAULT 0,
                aciklama            TEXT,
                created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS musteri_cihazlari (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id            INTEGER NOT NULL DEFAULT 0,
                musteri_id          INTEGER NOT NULL,
                cihaz_id            INTEGER,
                satis_id            INTEGER,
                seri_no             TEXT,
                kurulum_tarihi      DATE,
                notlar              TEXT,
                created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id)   REFERENCES kullanicilar(id) ON DELETE CASCADE,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE CASCADE,
                FOREIGN KEY (cihaz_id)   REFERENCES cihazlar(id),
                FOREIGN KEY (satis_id)   REFERENCES satislar(id)
            );

            CREATE TABLE IF NOT EXISTS taksitler (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id            INTEGER NOT NULL DEFAULT 0,
                satis_id            INTEGER NOT NULL,
                musteri_id          INTEGER NOT NULL,
                taksit_no           INTEGER NOT NULL,
                tutar               REAL NOT NULL DEFAULT 0,
                vade_tarihi         DATE,
                odeme_tarihi        DATE,
                odendi              INTEGER DEFAULT 0,
                odeme_yontemi       TEXT DEFAULT 'nakit',
                notlar              TEXT,
                created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (firma_id)   REFERENCES kullanicilar(id) ON DELETE CASCADE,
                FOREIGN KEY (satis_id)   REFERENCES satislar(id) ON DELETE CASCADE,
                FOREIGN KEY (musteri_id) REFERENCES musteriler(id)
            );

            CREATE TABLE IF NOT EXISTS standart_islem_parcalar (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                islem_id    INTEGER NOT NULL,
                parca_id    INTEGER NOT NULL,
                miktar      INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (islem_id) REFERENCES standart_islemler(id) ON DELETE CASCADE,
                FOREIGN KEY (parca_id) REFERENCES parcalar(id) ON DELETE CASCADE
            );
        ");
    }

    private function runMigrations(): void {
        // Tablolara firma_id / yeni kolonlar ekle (eski kurulumlar için)
        $migrations = [
            ['musteriler',        'firma_id',     "ALTER TABLE musteriler ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['servisler',         'firma_id',     "ALTER TABLE servisler ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['servisler',         'odeme_durumu', "ALTER TABLE servisler ADD COLUMN odeme_durumu TEXT DEFAULT 'odenmedi'"],
            ['servisler',         'odenen_tutar', "ALTER TABLE servisler ADD COLUMN odenen_tutar REAL DEFAULT 0"],
            ['parcalar',          'firma_id',     "ALTER TABLE parcalar ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['satislar',          'firma_id',     "ALTER TABLE satislar ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['tahsilatlar',       'firma_id',     "ALTER TABLE tahsilatlar ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['ayarlar',           'firma_id',     "ALTER TABLE ayarlar ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['rapor_log',         'firma_id',     "ALTER TABLE rapor_log ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
            ['standart_islemler', 'firma_id',     "ALTER TABLE standart_islemler ADD COLUMN firma_id INTEGER NOT NULL DEFAULT 0"],
        ];

        // Yeni kolonlar: satislar tablosuna taksit + cihaz alanları
        $extraMigrations = [
            ['satislar', 'odeme_turu',    "ALTER TABLE satislar ADD COLUMN odeme_turu TEXT DEFAULT 'pesin'"],
            ['satislar', 'taksit_sayisi', "ALTER TABLE satislar ADD COLUMN taksit_sayisi INTEGER DEFAULT 1"],
            ['satislar', 'pesinat',       "ALTER TABLE satislar ADD COLUMN pesinat REAL DEFAULT 0"],
            ['satislar', 'cihaz_id',      "ALTER TABLE satislar ADD COLUMN cihaz_id INTEGER"],
            ['satislar', 'seri_no',       "ALTER TABLE satislar ADD COLUMN seri_no TEXT"],
            ['parcalar',   'is_cihaz', "ALTER TABLE parcalar ADD COLUMN is_cihaz INTEGER DEFAULT 0"],
            ['musteriler', 'lat',      "ALTER TABLE musteriler ADD COLUMN lat REAL"],
            ['musteriler', 'lng',      "ALTER TABLE musteriler ADD COLUMN lng REAL"],
            ['cihazlar', 'parca_id', "ALTER TABLE cihazlar ADD COLUMN parca_id INTEGER"],
            ['kullanicilar', 'abonelik_durumu', "ALTER TABLE kullanicilar ADD COLUMN abonelik_durumu TEXT DEFAULT 'aktif'"],
            ['kullanicilar', 'abonelik_bitis', "ALTER TABLE kullanicilar ADD COLUMN abonelik_bitis DATE"],
        ];
        $syncTables = [
            'kullanicilar', 'musteriler', 'standart_islemler', 'periyodik_bakimlar',
            'servisler', 'servis_islemleri', 'parcalar', 'servis_parcalari',
            'satislar', 'satis_kalemleri', 'tahsilatlar', 'ayarlar',
            'cihazlar', 'musteri_cihazlari', 'taksitler', 'standart_islem_parcalar',
        ];
        foreach ($syncTables as $table) {
            $extraMigrations[] = [$table, 'uuid', "ALTER TABLE {$table} ADD COLUMN uuid TEXT"];
            $extraMigrations[] = [$table, 'deleted_at', "ALTER TABLE {$table} ADD COLUMN deleted_at DATETIME"];
            $extraMigrations[] = [$table, 'synced_at', "ALTER TABLE {$table} ADD COLUMN synced_at DATETIME"];
            $extraMigrations[] = [$table, 'sync_version', "ALTER TABLE {$table} ADD COLUMN sync_version INTEGER DEFAULT 1"];
        }
        $migrations = array_merge($migrations, $extraMigrations);

        foreach ($migrations as [$tablo, $kolon, $sql]) {
            $cols = $this->pdo->query("PRAGMA table_info({$tablo})")->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($cols, 'name');
            if (!in_array($kolon, $colNames)) {
                $this->pdo->exec($sql);
            }
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                table_name TEXT NOT NULL,
                row_uuid TEXT NOT NULL,
                action TEXT NOT NULL CHECK(action IN ('upsert','delete')),
                payload TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                synced_at DATETIME,
                error TEXT
            );

            CREATE TABLE IF NOT EXISTS sync_state (
                id INTEGER PRIMARY KEY CHECK(id = 1),
                device_id TEXT,
                server_url TEXT,
                token TEXT,
                last_pull_at DATETIME,
                last_push_at DATETIME,
                enabled INTEGER DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS sync_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                token_hash TEXT UNIQUE NOT NULL,
                device_name TEXT,
                device_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME,
                revoked_at DATETIME,
                FOREIGN KEY (firma_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
            );
        ");

        $syncStateCols = array_column($this->pdo->query("PRAGMA table_info(sync_state)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('token', $syncStateCols, true)) {
            $this->pdo->exec("ALTER TABLE sync_state ADD COLUMN token TEXT");
        }

        $this->syncCihazKatalogu();
    }

    private function syncCihazKatalogu(): void {
        $cihazCols = array_column($this->pdo->query("PRAGMA table_info(cihazlar)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        $parcaCols = array_column($this->pdo->query("PRAGMA table_info(parcalar)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('parca_id', $cihazCols, true) || !in_array('is_cihaz', $parcaCols, true)) return;

        $cihazStmt = $this->pdo->prepare("
            INSERT INTO cihazlar (firma_id, parca_id, cihaz_adi, marka, varsayilan_fiyat, aciklama, uuid)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $linkedStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM cihazlar
            WHERE firma_id=? AND parca_id=? AND deleted_at IS NULL
        ");
        $stokCihazlar = $this->pdo->query("
            SELECT * FROM parcalar
            WHERE is_cihaz=1 AND deleted_at IS NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($stokCihazlar as $parca) {
            $linkedStmt->execute([$parca['firma_id'] ?? 0, $parca['id']]);
            if ((int)$linkedStmt->fetchColumn() > 0) continue;
            $cihazStmt->execute([
                $parca['firma_id'] ?? 0,
                $parca['id'],
                $parca['parca_adi'],
                $parca['marka'] ?? null,
                $parca['birim_fiyat'] ?? 0,
                $parca['tedarikci'] ?? null,
                $this->uuid(),
            ]);
        }

        $parcaStmt = $this->pdo->prepare("
            INSERT INTO parcalar (firma_id, parca_adi, marka, birim_fiyat, stok_miktari, kritik_stok_seviyesi, tedarikci, is_cihaz, uuid)
            VALUES (?, ?, ?, ?, 0, 1, ?, 1, ?)
        ");
        $updateCihazStmt = $this->pdo->prepare("UPDATE cihazlar SET parca_id=?, synced_at=NULL WHERE id=?");
        $eslesmemisCihazlar = $this->pdo->query("
            SELECT c.* FROM cihazlar c
            LEFT JOIN parcalar p ON p.id=c.parca_id AND p.deleted_at IS NULL
            WHERE c.deleted_at IS NULL AND p.id IS NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($eslesmemisCihazlar as $cihaz) {
            $parcaStmt->execute([
                $cihaz['firma_id'] ?? 0,
                $cihaz['cihaz_adi'],
                $cihaz['marka'] ?? null,
                $cihaz['varsayilan_fiyat'] ?? 0,
                $cihaz['aciklama'] ?? null,
                $this->uuid(),
            ]);
            $updateCihazStmt->execute([(int)$this->pdo->lastInsertId(), $cihaz['id']]);
        }
    }

    private function ensureSyncMetadata(): void {
        $tables = [
            'kullanicilar', 'musteriler', 'standart_islemler', 'periyodik_bakimlar',
            'servisler', 'servis_islemleri', 'parcalar', 'servis_parcalari',
            'satislar', 'satis_kalemleri', 'tahsilatlar', 'ayarlar',
            'cihazlar', 'musteri_cihazlari', 'taksitler', 'standart_islem_parcalar',
        ];

        foreach ($tables as $table) {
            $rows = $this->pdo->query("SELECT id FROM {$table} WHERE uuid IS NULL OR uuid = ''")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) continue;

            $stmt = $this->pdo->prepare("UPDATE {$table} SET uuid=? WHERE id=?");
            foreach ($rows as $row) {
                $stmt->execute([$this->uuid(), $row['id']]);
            }
        }

        $exists = $this->fetchColumn("SELECT COUNT(*) FROM sync_state WHERE id=1");
        if (!$exists) {
            $this->query(
                "INSERT INTO sync_state (id, device_id, enabled) VALUES (1, ?, 0)",
                [$this->uuid()]
            );
        }
    }

    private function uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ── Yeni kayıt olan firma için varsayılan verileri oluştur ────────────
    public function seedFirmaDefaults(int $firmaId): void {
        // Varsayılan standart işlemler
        $items = [
            'Full Servis', 'Tank Değişimi', 'Membran Değişimi',
            '5 Mikron Filtre Değişimi', "4'lü Filtre Değişimi", 'Adaptör Değişimi',
        ];
        $stmt = $this->pdo->prepare(
            "INSERT OR IGNORE INTO standart_islemler (firma_id, islem_adi, varsayilan_fiyat) VALUES (?, ?, 0)"
        );
        foreach ($items as $ad) {
            $stmt->execute([$firmaId, $ad]);
        }

        // Varsayılan ayarlar
        $defaults = [
            ['varsayilan_bakim_periyodu', '6'],
            ['varsayilan_hatirlatma_gun', '7'],
            ['firma_adi',      'Servis Takip Panel'],
            ['firma_telefon',  ''],
            ['firma_adres',    ''],
            ['firma_email',    ''],
            ['para_birimi',    '₺'],
            ['firma_vergi_no', ''],
            ['firma_iban',     ''],
            ['fatura_notu',    'Ödeme için teşekkür ederiz.'],
            ['fatura_logo',    ''],
        ];
        $stmt = $this->pdo->prepare(
            "INSERT OR IGNORE INTO ayarlar (firma_id, anahtar, deger) VALUES (?, ?, ?)"
        );
        foreach ($defaults as [$k, $v]) {
            $stmt->execute([$firmaId, $k, $v]);
        }
    }
}
