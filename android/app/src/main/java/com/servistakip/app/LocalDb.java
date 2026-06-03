package com.servistakip.app;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;

import org.json.JSONArray;
import org.json.JSONObject;

import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Date;
import java.util.Locale;
import java.util.UUID;

class LocalDb extends SQLiteOpenHelper {
    private static final String DB_NAME = "servis_takip_panel_mobile.db";
    private static final int DB_VERSION = 3;

    LocalDb(Context context) {
        super(context, DB_NAME, null, DB_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        db.execSQL("CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT)");
        db.execSQL("CREATE TABLE musteriler (uuid TEXT PRIMARY KEY, server_id INTEGER, ad TEXT NOT NULL, soyad TEXT NOT NULL, telefon TEXT, email TEXT, adres TEXT, notlar TEXT, lat REAL, lng REAL, created_at TEXT, updated_at TEXT, deleted_at TEXT, synced_at TEXT)");
        db.execSQL("CREATE TABLE parcalar (uuid TEXT PRIMARY KEY, server_id INTEGER, parca_adi TEXT NOT NULL, marka TEXT, birim_fiyat REAL, stok_miktari INTEGER, kritik_stok_seviyesi INTEGER, tedarikci TEXT, is_cihaz INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT, synced_at TEXT)");
        db.execSQL("CREATE TABLE servisler (uuid TEXT PRIMARY KEY, server_id INTEGER, musteri_uuid TEXT, servis_tipi TEXT, durum TEXT, toplam_tutar REAL, odeme_durumu TEXT, odenen_tutar REAL, notlar TEXT, tamamlanma_tarihi TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT, synced_at TEXT)");
        db.execSQL("CREATE TABLE satislar (uuid TEXT PRIMARY KEY, server_id INTEGER, musteri_uuid TEXT, toplam_tutar REAL, odeme_durumu TEXT, odenen_tutar REAL, notlar TEXT, satis_tarihi TEXT, odeme_turu TEXT, taksit_sayisi INTEGER, pesinat REAL, seri_no TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT, synced_at TEXT)");
        db.execSQL("CREATE TABLE tahsilatlar (uuid TEXT PRIMARY KEY, server_id INTEGER, musteri_uuid TEXT, kaynak_tip TEXT, kaynak_uuid TEXT, tutar REAL, odeme_yontemi TEXT, notlar TEXT, tahsilat_tarihi TEXT, created_at TEXT, deleted_at TEXT, synced_at TEXT)");
        db.execSQL("CREATE TABLE periyodik_bakimlar (uuid TEXT PRIMARY KEY, server_id INTEGER, musteri_uuid TEXT, aktif INTEGER, periyot_ay INTEGER, son_bakim_tarihi TEXT, sonraki_bakim_tarihi TEXT, hatirlatma_gun INTEGER, notlar TEXT, deleted_at TEXT, synced_at TEXT)");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        if (oldVersion < 3) {
            addColumnIfMissing(db, "musteriler", "lat", "REAL");
            addColumnIfMissing(db, "musteriler", "lng", "REAL");
            return;
        }
        db.execSQL("DROP TABLE IF EXISTS settings");
        db.execSQL("DROP TABLE IF EXISTS musteriler");
        db.execSQL("DROP TABLE IF EXISTS parcalar");
        db.execSQL("DROP TABLE IF EXISTS servisler");
        db.execSQL("DROP TABLE IF EXISTS satislar");
        db.execSQL("DROP TABLE IF EXISTS tahsilatlar");
        db.execSQL("DROP TABLE IF EXISTS periyodik_bakimlar");
        onCreate(db);
    }

    private void addColumnIfMissing(SQLiteDatabase db, String table, String column, String type) {
        Cursor c = db.rawQuery("PRAGMA table_info(" + table + ")", null);
        try {
            while (c.moveToNext()) {
                if (column.equals(c.getString(c.getColumnIndexOrThrow("name")))) return;
            }
        } finally {
            c.close();
        }
        db.execSQL("ALTER TABLE " + table + " ADD COLUMN " + column + " " + type);
    }

    String now() {
        return new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(new Date());
    }

    String today() {
        return new SimpleDateFormat("yyyy-MM-dd", Locale.US).format(new Date());
    }

    String addMonths(int months) {
        Calendar cal = Calendar.getInstance();
        cal.add(Calendar.MONTH, months);
        return new SimpleDateFormat("yyyy-MM-dd", Locale.US).format(cal.getTime());
    }

    String getSetting(String key) {
        Cursor c = getReadableDatabase().rawQuery("SELECT value FROM settings WHERE key=?", new String[]{key});
        try {
            return c.moveToFirst() ? c.getString(0) : "";
        } finally {
            c.close();
        }
    }

    void setSetting(String key, String value) {
        ContentValues cv = new ContentValues();
        cv.put("key", key);
        cv.put("value", value);
        getWritableDatabase().insertWithOnConflict("settings", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }

    void clearSession() {
        getWritableDatabase().delete("settings", "key IN (?,?,?,?,?)", new String[]{"token", "firma_adi", "email", "ad_soyad", "last_sync"});
    }

    String firstCustomerUuid() {
        Cursor c = getReadableDatabase().rawQuery("SELECT uuid FROM musteriler WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 1", null);
        try {
            return c.moveToFirst() ? c.getString(0) : "";
        } finally {
            c.close();
        }
    }

    Cursor customersForPick() {
        return getReadableDatabase().rawQuery(
            "SELECT uuid, (ad || ' ' || soyad) AS name FROM musteriler WHERE deleted_at IS NULL ORDER BY ad ASC, soyad ASC",
            null
        );
    }

    String firstServiceOrSaleUuid(String tip) {
        String table = "servis".equals(tip) ? "servisler" : "satislar";
        Cursor c = getReadableDatabase().rawQuery("SELECT uuid FROM " + table + " WHERE deleted_at IS NULL ORDER BY updated_at DESC, created_at DESC LIMIT 1", null);
        try {
            return c.moveToFirst() ? c.getString(0) : "";
        } finally {
            c.close();
        }
    }

    Cursor sourcesForPick() {
        return getReadableDatabase().rawQuery(
            "SELECT uuid, musteri_uuid, 'servis' AS tip, 'Servis - ' || toplam_tutar AS name FROM servisler WHERE deleted_at IS NULL " +
            "UNION ALL SELECT uuid, musteri_uuid, 'satis' AS tip, 'Satis - ' || toplam_tutar AS name FROM satislar WHERE deleted_at IS NULL",
            null
        );
    }

    String customerName(String uuid) {
        Cursor c = getReadableDatabase().rawQuery("SELECT ad, soyad FROM musteriler WHERE uuid=?", new String[]{uuid});
        try {
            return c.moveToFirst() ? c.getString(0) + " " + c.getString(1) : "";
        } finally {
            c.close();
        }
    }

    String[] customerContactByRowId(long rowId) {
        Cursor c = getReadableDatabase().rawQuery("SELECT telefon, lat, lng, adres FROM musteriler WHERE rowid=?", new String[]{String.valueOf(rowId)});
        try {
            if (!c.moveToFirst()) return new String[]{"", "", "", ""};
            return new String[]{safe(c, "telefon"), safe(c, "lat"), safe(c, "lng"), safe(c, "adres")};
        } finally {
            c.close();
        }
    }

    String titleByRowId(String module, long rowId) {
        Cursor c = visible(module);
        try {
            while (c.moveToNext()) {
                if (c.getLong(c.getColumnIndexOrThrow("_id")) == rowId) {
                    return c.getString(c.getColumnIndexOrThrow("title"));
                }
            }
            return "";
        } finally {
            c.close();
        }
    }

    String detailByRowId(String module, long rowId) {
        String table = tableForModule(module);
        Cursor c = getReadableDatabase().rawQuery("SELECT * FROM " + table + " WHERE rowid=?", new String[]{String.valueOf(rowId)});
        try {
            if (!c.moveToFirst()) return "";
            switch (module) {
                case "stok":
                    return "Ürün: " + safe(c, "parca_adi") +
                        "\nMarka: " + safe(c, "marka") +
                        "\nStok: " + safe(c, "stok_miktari") +
                        "\nBirim fiyat: " + safe(c, "birim_fiyat") +
                        "\nSenkron: " + syncLabel(c);
                case "servis":
                    return "Müşteri: " + customerName(safe(c, "musteri_uuid")) +
                        "\nServis tipi: " + safe(c, "servis_tipi") +
                        "\nDurum: " + safe(c, "durum") +
                        "\nTutar: " + safe(c, "toplam_tutar") +
                        "\nÖdeme: " + safe(c, "odeme_durumu") +
                        "\nNot: " + safe(c, "notlar") +
                        "\nSenkron: " + syncLabel(c);
                case "satis":
                    return "Müşteri: " + customerName(safe(c, "musteri_uuid")) +
                        "\nTutar: " + safe(c, "toplam_tutar") +
                        "\nÖdeme: " + safe(c, "odeme_durumu") +
                        "\nSatış tarihi: " + safe(c, "satis_tarihi") +
                        "\nNot: " + safe(c, "notlar") +
                        "\nSenkron: " + syncLabel(c);
                case "tahsilat":
                    return "Müşteri: " + customerName(safe(c, "musteri_uuid")) +
                        "\nKaynak: " + safe(c, "kaynak_tip") +
                        "\nTutar: " + safe(c, "tutar") +
                        "\nÖdeme yöntemi: " + safe(c, "odeme_yontemi") +
                        "\nTarih: " + safe(c, "tahsilat_tarihi") +
                        "\nSenkron: " + syncLabel(c);
                case "bakim":
                    return "Müşteri: " + customerName(safe(c, "musteri_uuid")) +
                        "\nPeriyot: " + safe(c, "periyot_ay") + " ay" +
                        "\nSon bakım: " + safe(c, "son_bakim_tarihi") +
                        "\nSonraki bakım: " + safe(c, "sonraki_bakim_tarihi") +
                        "\nHatırlatma: " + safe(c, "hatirlatma_gun") + " gün" +
                        "\nSenkron: " + syncLabel(c);
                default:
                    return "Ad soyad: " + safe(c, "ad") + " " + safe(c, "soyad") +
                        "\nTelefon: " + safe(c, "telefon") +
                        "\nE-posta: " + safe(c, "email") +
                        "\nAdres: " + safe(c, "adres") +
                        "\nKonum: " + locationLabel(c) +
                        "\nSenkron: " + syncLabel(c);
            }
        } finally {
            c.close();
        }
    }

    private String safe(Cursor c, String column) {
        int idx = c.getColumnIndex(column);
        if (idx < 0 || c.isNull(idx)) return "";
        return c.getString(idx);
    }

    private String syncLabel(Cursor c) {
        return safe(c, "synced_at").isEmpty() ? "Bekliyor" : "Senkronize";
    }

    private String locationLabel(Cursor c) {
        String lat = safe(c, "lat");
        String lng = safe(c, "lng");
        return lat.isEmpty() || lng.isEmpty() ? "Yok" : lat + ", " + lng;
    }

    void softDelete(String module, long rowId) {
        String table = tableForModule(module);
        ContentValues cv = new ContentValues();
        cv.put("deleted_at", now());
        cv.putNull("synced_at");
        if (hasColumn(table, "updated_at")) cv.put("updated_at", now());
        getWritableDatabase().update(table, cv, "rowid=?", new String[]{String.valueOf(rowId)});
    }

    int pendingCount() {
        int total = 0;
        String[] tables = {"musteriler", "parcalar", "servisler", "satislar", "tahsilatlar", "periyodik_bakimlar"};
        for (String table : tables) {
            Cursor c = getReadableDatabase().rawQuery("SELECT COUNT(*) FROM " + table + " WHERE synced_at IS NULL", null);
            try {
                if (c.moveToFirst()) total += c.getInt(0);
            } finally {
                c.close();
            }
        }
        return total;
    }

    int visibleCount(String module) {
        String table = tableForModule(module);
        Cursor c = getReadableDatabase().rawQuery("SELECT COUNT(*) FROM " + table + " WHERE deleted_at IS NULL", null);
        try {
            return c.moveToFirst() ? c.getInt(0) : 0;
        } finally {
            c.close();
        }
    }

    void addCustomer(String ad, String soyad, String telefon, String email, String adres, Double lat, Double lng) {
        ContentValues cv = baseValues();
        cv.put("ad", ad);
        cv.put("soyad", soyad);
        cv.put("telefon", telefon);
        cv.put("email", email);
        cv.put("adres", adres);
        cv.put("notlar", "");
        if (lat != null && lng != null) {
            cv.put("lat", lat);
            cv.put("lng", lng);
        }
        getWritableDatabase().insert("musteriler", null, cv);
    }

    void addStock(String name, String brand, double price, int qty) {
        ContentValues cv = baseValues();
        cv.put("parca_adi", name);
        cv.put("marka", brand);
        cv.put("birim_fiyat", price);
        cv.put("stok_miktari", qty);
        cv.put("kritik_stok_seviyesi", 5);
        cv.put("tedarikci", "");
        cv.put("is_cihaz", 0);
        getWritableDatabase().insert("parcalar", null, cv);
    }

    void addService(String musteriUuid, String tip, double total, String note) {
        ContentValues cv = baseValues();
        cv.put("musteri_uuid", musteriUuid);
        cv.put("servis_tipi", tip);
        cv.put("durum", "tamamlanan");
        cv.put("toplam_tutar", total);
        cv.put("odeme_durumu", "odenmedi");
        cv.put("odenen_tutar", 0);
        cv.put("notlar", note);
        cv.put("tamamlanma_tarihi", today());
        getWritableDatabase().insert("servisler", null, cv);
    }

    void addSale(String musteriUuid, double total, String note) {
        ContentValues cv = baseValues();
        cv.put("musteri_uuid", musteriUuid);
        cv.put("toplam_tutar", total);
        cv.put("odeme_durumu", "odenmedi");
        cv.put("odenen_tutar", 0);
        cv.put("notlar", note);
        cv.put("satis_tarihi", today());
        cv.put("odeme_turu", "pesin");
        cv.put("taksit_sayisi", 1);
        cv.put("pesinat", 0);
        cv.put("seri_no", "");
        getWritableDatabase().insert("satislar", null, cv);
    }

    void addCollection(String musteriUuid, String kaynakTip, String kaynakUuid, double amount, String method) {
        ContentValues cv = new ContentValues();
        cv.put("uuid", UUID.randomUUID().toString());
        cv.put("musteri_uuid", musteriUuid);
        cv.put("kaynak_tip", kaynakTip);
        cv.put("kaynak_uuid", kaynakUuid);
        cv.put("tutar", amount);
        cv.put("odeme_yontemi", method);
        cv.put("notlar", "");
        cv.put("tahsilat_tarihi", today());
        cv.put("created_at", now());
        cv.putNull("deleted_at");
        cv.putNull("synced_at");
        getWritableDatabase().insert("tahsilatlar", null, cv);
    }

    void addMaintenance(String musteriUuid, int months, int remindDays) {
        ContentValues cv = new ContentValues();
        cv.put("uuid", UUID.randomUUID().toString());
        cv.put("musteri_uuid", musteriUuid);
        cv.put("aktif", 1);
        cv.put("periyot_ay", months);
        cv.put("son_bakim_tarihi", today());
        cv.put("sonraki_bakim_tarihi", addMonths(months));
        cv.put("hatirlatma_gun", remindDays);
        cv.put("notlar", "");
        cv.putNull("deleted_at");
        cv.putNull("synced_at");
        getWritableDatabase().insert("periyodik_bakimlar", null, cv);
    }

    private ContentValues baseValues() {
        String now = now();
        ContentValues cv = new ContentValues();
        cv.put("uuid", UUID.randomUUID().toString());
        cv.put("created_at", now);
        cv.put("updated_at", now);
        cv.putNull("deleted_at");
        cv.putNull("synced_at");
        return cv;
    }

    Cursor visible(String module) {
        return visible(module, "");
    }

    Cursor visible(String module, String search) {
        String q = search == null ? "" : search.trim().toLowerCase(Locale.ROOT);
        String filter = q.isEmpty() ? "" : "%" + q + "%";
        String sql;
        String[] args = q.isEmpty() ? null : new String[]{filter, filter, filter, filter};
        switch (module) {
            case "stok":
                sql = "SELECT rowid AS _id, parca_adi AS title, ('Stok: ' || stok_miktari || ' - Fiyat: ' || birim_fiyat) AS subtitle, synced_at FROM parcalar WHERE deleted_at IS NULL" +
                    (q.isEmpty() ? "" : " AND (LOWER(parca_adi) LIKE ? OR LOWER(marka) LIKE ? OR LOWER(tedarikci) LIKE ? OR CAST(stok_miktari AS TEXT) LIKE ?)") +
                    " ORDER BY updated_at DESC, created_at DESC";
                break;
            case "servis":
                sql = "SELECT s.rowid AS _id, (m.ad || ' ' || m.soyad || ' - ' || s.servis_tipi) AS title, ('Tutar: ' || s.toplam_tutar || ' - ' || s.odeme_durumu) AS subtitle, s.synced_at FROM servisler s LEFT JOIN musteriler m ON m.uuid=s.musteri_uuid WHERE s.deleted_at IS NULL" +
                    (q.isEmpty() ? "" : " AND (LOWER(m.ad) LIKE ? OR LOWER(m.soyad) LIKE ? OR LOWER(s.servis_tipi) LIKE ? OR LOWER(s.notlar) LIKE ?)") +
                    " ORDER BY s.updated_at DESC, s.created_at DESC";
                break;
            case "satis":
                sql = "SELECT st.rowid AS _id, (m.ad || ' ' || m.soyad || ' - Satis') AS title, ('Tutar: ' || st.toplam_tutar || ' - ' || st.odeme_durumu) AS subtitle, st.synced_at FROM satislar st LEFT JOIN musteriler m ON m.uuid=st.musteri_uuid WHERE st.deleted_at IS NULL" +
                    (q.isEmpty() ? "" : " AND (LOWER(m.ad) LIKE ? OR LOWER(m.soyad) LIKE ? OR LOWER(st.notlar) LIKE ? OR LOWER(st.seri_no) LIKE ?)") +
                    " ORDER BY st.updated_at DESC, st.created_at DESC";
                break;
            case "tahsilat":
                sql = "SELECT t.rowid AS _id, (m.ad || ' ' || m.soyad || ' - Tahsilat') AS title, ('Tutar: ' || t.tutar || ' - ' || t.odeme_yontemi) AS subtitle, t.synced_at FROM tahsilatlar t LEFT JOIN musteriler m ON m.uuid=t.musteri_uuid WHERE t.deleted_at IS NULL" +
                    (q.isEmpty() ? "" : " AND (LOWER(m.ad) LIKE ? OR LOWER(m.soyad) LIKE ? OR LOWER(t.odeme_yontemi) LIKE ? OR LOWER(t.notlar) LIKE ?)") +
                    " ORDER BY t.created_at DESC";
                break;
            case "bakim":
                sql = "SELECT pb.rowid AS _id, (m.ad || ' ' || m.soyad || ' - Bakim') AS title, ('Periyot: ' || pb.periyot_ay || ' ay - Hatirlatma: ' || pb.hatirlatma_gun || ' gun') AS subtitle, pb.synced_at FROM periyodik_bakimlar pb LEFT JOIN musteriler m ON m.uuid=pb.musteri_uuid WHERE pb.deleted_at IS NULL" +
                    (q.isEmpty() ? "" : " AND (LOWER(m.ad) LIKE ? OR LOWER(m.soyad) LIKE ? OR LOWER(pb.notlar) LIKE ? OR CAST(pb.periyot_ay AS TEXT) LIKE ?)") +
                    " ORDER BY pb.rowid DESC";
                break;
            default:
                sql = "SELECT rowid AS _id, (ad || ' ' || soyad) AS title, (COALESCE(telefon,'') || CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN ' - konum var' ELSE '' END) AS subtitle, synced_at FROM musteriler WHERE deleted_at IS NULL" +
                    (q.isEmpty() ? "" : " AND (LOWER(ad) LIKE ? OR LOWER(soyad) LIKE ? OR LOWER(telefon) LIKE ? OR LOWER(adres) LIKE ?)") +
                    " ORDER BY updated_at DESC, created_at DESC";
        }
        return getReadableDatabase().rawQuery(sql, args);
    }

    JSONArray unsynced(String table) throws Exception {
        JSONArray arr = new JSONArray();
        Cursor c = getReadableDatabase().rawQuery("SELECT * FROM " + table + " WHERE synced_at IS NULL", null);
        try {
            while (c.moveToNext()) {
                JSONObject o = new JSONObject();
                for (int i = 0; i < c.getColumnCount(); i++) {
                    String col = c.getColumnName(i);
                    if ("server_id".equals(col) || col.endsWith("_uuid")) continue;
                    if (c.isNull(i)) o.put(col, JSONObject.NULL); else o.put(col, c.getString(i));
                }
                JSONObject refs = refsFor(table, c);
                if (refs.length() > 0) o.put("__refs", refs);
                arr.put(o);
            }
        } finally {
            c.close();
        }
        return arr;
    }

    private JSONObject refsFor(String table, Cursor c) throws Exception {
        JSONObject refs = new JSONObject();
        if (hasColumn(c, "musteri_uuid")) refs.put("musteri_id", c.getString(c.getColumnIndexOrThrow("musteri_uuid")));
        if ("tahsilatlar".equals(table) && hasColumn(c, "kaynak_uuid")) {
            String tip = c.getString(c.getColumnIndexOrThrow("kaynak_tip"));
            refs.put("kaynak_id", c.getString(c.getColumnIndexOrThrow("kaynak_uuid")));
            if ("servis".equals(tip)) refs.put("kaynak_table", "servisler");
            if ("satis".equals(tip)) refs.put("kaynak_table", "satislar");
        }
        return refs;
    }

    private boolean hasColumn(Cursor c, String name) {
        return c.getColumnIndex(name) >= 0;
    }

    private boolean hasColumn(String table, String column) {
        Cursor c = getReadableDatabase().rawQuery("PRAGMA table_info(" + table + ")", null);
        try {
            while (c.moveToNext()) {
                if (column.equals(c.getString(c.getColumnIndexOrThrow("name")))) return true;
            }
            return false;
        } finally {
            c.close();
        }
    }

    private String tableForModule(String module) {
        switch (module) {
            case "stok": return "parcalar";
            case "servis": return "servisler";
            case "satis": return "satislar";
            case "tahsilat": return "tahsilatlar";
            case "bakim": return "periyodik_bakimlar";
            default: return "musteriler";
        }
    }

    void markSynced(JSONArray applied) throws Exception {
        SQLiteDatabase w = getWritableDatabase();
        String now = now();
        for (int i = 0; i < applied.length(); i++) {
            JSONObject row = applied.getJSONObject(i);
            String table = row.optString("table");
            if (!isMobileTable(table)) continue;
            ContentValues cv = new ContentValues();
            cv.put("synced_at", now);
            w.update(table, cv, "uuid=?", new String[]{row.optString("uuid")});
        }
    }

    void upsertPulled(String table, JSONObject o) {
        if (!isMobileTable(table)) return;
        ContentValues cv = new ContentValues();
        cv.put("uuid", o.optString("uuid"));
        cv.put("server_id", o.optInt("id", 0));
        copy(o, cv, new String[]{"ad","soyad","telefon","email","adres","notlar","created_at","updated_at","deleted_at","synced_at","parca_adi","marka","tedarikci","servis_tipi","durum","odeme_durumu","tamamlanma_tarihi","satis_tarihi","odeme_turu","seri_no","kaynak_tip","odeme_yontemi","tahsilat_tarihi","son_bakim_tarihi","sonraki_bakim_tarihi"});
        copyNum(o, cv, new String[]{"birim_fiyat","stok_miktari","kritik_stok_seviyesi","is_cihaz","toplam_tutar","odenen_tutar","taksit_sayisi","pesinat","tutar","aktif","periyot_ay","hatirlatma_gun","lat","lng"});
        if (o.has("musteri_id")) cv.put("musteri_uuid", uuidByServerId("musteriler", o.optInt("musteri_id")));
        if ("tahsilatlar".equals(table)) {
            String tip = o.optString("kaynak_tip");
            if ("servis".equals(tip)) cv.put("kaynak_uuid", uuidByServerId("servisler", o.optInt("kaynak_id")));
            if ("satis".equals(tip)) cv.put("kaynak_uuid", uuidByServerId("satislar", o.optInt("kaynak_id")));
        }
        cv.put("synced_at", now());
        getWritableDatabase().insertWithOnConflict(table, null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }

    private void copy(JSONObject o, ContentValues cv, String[] cols) {
        for (String col : cols) {
            if (!o.has(col) || o.isNull(col)) continue;
            cv.put(col, o.optString(col));
        }
    }

    private void copyNum(JSONObject o, ContentValues cv, String[] cols) {
        for (String col : cols) {
            if (!o.has(col) || o.isNull(col)) continue;
            cv.put(col, o.optDouble(col));
        }
    }

    private String uuidByServerId(String table, int serverId) {
        if (serverId <= 0) return "";
        Cursor c = getReadableDatabase().rawQuery("SELECT uuid FROM " + table + " WHERE server_id=?", new String[]{String.valueOf(serverId)});
        try {
            return c.moveToFirst() ? c.getString(0) : "";
        } finally {
            c.close();
        }
    }

    boolean isMobileTable(String table) {
        return "musteriler".equals(table) || "parcalar".equals(table) || "servisler".equals(table) || "satislar".equals(table) || "tahsilatlar".equals(table) || "periyodik_bakimlar".equals(table);
    }
}
