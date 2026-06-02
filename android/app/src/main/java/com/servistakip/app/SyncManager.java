package com.servistakip.app;

import org.json.JSONArray;
import org.json.JSONObject;

class SyncManager {
    private static final String[] TABLES = {
        "musteriler", "parcalar", "servisler", "satislar", "tahsilatlar", "periyodik_bakimlar"
    };

    private final LocalDb db;

    SyncManager(LocalDb db) {
        this.db = db;
    }

    int sync() throws Exception {
        String token = db.getSetting("token");
        if (token.isEmpty()) throw new Exception("Once giris yapin.");

        int touched = 0;
        JSONObject changes = new JSONObject();
        for (String table : TABLES) {
            JSONArray rows = db.unsynced(table);
            if (rows.length() > 0) changes.put(table, rows);
        }

        if (changes.length() > 0) {
            JSONObject payload = new JSONObject();
            payload.put("changes", changes);
            JSONObject pushed = ApiClient.post("/api/sync.php?action=push", token, payload);
            JSONArray applied = pushed.getJSONObject("data").optJSONArray("applied");
            if (applied != null) {
                db.markSynced(applied);
                touched += applied.length();
            }
        }

        JSONObject pulled = ApiClient.get("/api/sync.php?action=pull", token);
        JSONObject pulledChanges = pulled.getJSONObject("data").getJSONObject("changes");
        for (String table : TABLES) {
            JSONArray rows = pulledChanges.optJSONArray(table);
            if (rows == null) continue;
            for (int i = 0; i < rows.length(); i++) {
                db.upsertPulled(table, rows.getJSONObject(i));
            }
            touched += rows.length();
        }
        db.setSetting("last_sync", db.now());
        return touched;
    }
}
