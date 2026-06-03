package com.servistakip.app;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.location.Location;
import android.location.LocationManager;
import android.os.AsyncTask;
import android.os.Bundle;
import android.provider.Settings;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.ProgressBar;
import android.widget.SimpleCursorAdapter;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONObject;

public class MainActivity extends Activity {
    private static final int NAVY = Color.rgb(15, 23, 42);
    private static final int BLUE = Color.rgb(37, 99, 235);
    private static final int GREEN = Color.rgb(22, 163, 74);
    private static final int ORANGE = Color.rgb(234, 88, 12);
    private static final int BG = Color.rgb(248, 250, 252);
    private static final int BORDER = Color.rgb(226, 232, 240);
    private static final int TEXT = Color.rgb(15, 23, 42);
    private static final int MUTED = Color.rgb(100, 116, 139);

    private LocalDb db;
    private LinearLayout root;
    private ProgressBar progress;
    private ListView list;
    private TextView title;
    private TextView subtitle;
    private SimpleCursorAdapter adapter;
    private Cursor listCursor;
    private String module = "musteri";
    private static final int LOCATION_PERMISSION_REQUEST = 41;
    private String selectedCustomerUuid = "";
    private String selectedSourceUuid = "";
    private String selectedSourceTip = "";
    private String selectedSourceCustomerUuid = "";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        db = new LocalDb(this);
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(BG);
        setContentView(root);
        if (db.getSetting("token").isEmpty()) showLogin(); else {
            showHome();
            doSync(false);
        }
    }

    private void showLogin() {
        root.removeAllViews();
        root.setGravity(Gravity.CENTER);
        root.setPadding(dp(24), dp(28), dp(24), dp(28));

        TextView logo = label("SP", 24, Color.WHITE, Typeface.BOLD);
        logo.setGravity(Gravity.CENTER);
        logo.setBackgroundResource(R.drawable.logo_badge);
        root.addView(logo, new LinearLayout.LayoutParams(dp(76), dp(76)));

        TextView h = label("Servis Takip Panel", 25, TEXT, Typeface.BOLD);
        h.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams hp = new LinearLayout.LayoutParams(-1, -2);
        hp.setMargins(0, dp(18), 0, dp(4));
        root.addView(h, hp);

        TextView p = label("Müşteri, servis, satış ve tahsilat kayıtlarınızı telefondan offline yönetin.", 14, MUTED, Typeface.NORMAL);
        p.setGravity(Gravity.CENTER);
        root.addView(p, new LinearLayout.LayoutParams(-1, -2));

        EditText email = input("E-posta", false);
        EditText pass = input("Şifre", true);
        LinearLayout.LayoutParams ip = new LinearLayout.LayoutParams(-1, dp(52));
        ip.setMargins(0, dp(24), 0, dp(10));
        root.addView(email, ip);
        LinearLayout.LayoutParams pp = new LinearLayout.LayoutParams(-1, dp(52));
        pp.setMargins(0, 0, 0, 0);
        root.addView(pass, pp);

        Button login = primaryButton("Giriş yap ve senkronize et");
        LinearLayout.LayoutParams bp = new LinearLayout.LayoutParams(-1, dp(52));
        bp.setMargins(0, dp(18), 0, 0);
        root.addView(login, bp);

        TextView offline = label("İnternet yokken kayıt alır, bağlantı geldiğinde web panelle eşitler.", 12, MUTED, Typeface.NORMAL);
        offline.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams op = new LinearLayout.LayoutParams(-1, -2);
        op.setMargins(0, dp(14), 0, 0);
        root.addView(offline, op);

        progress = new ProgressBar(this);
        progress.setVisibility(View.GONE);
        root.addView(progress, new LinearLayout.LayoutParams(dp(42), dp(42)));
        login.setOnClickListener(v -> doLogin(email.getText().toString(), pass.getText().toString()));
    }

    private void showHome() {
        root.removeAllViews();
        root.setGravity(Gravity.NO_GRAVITY);
        root.setPadding(0, 0, 0, 0);

        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.VERTICAL);
        header.setPadding(dp(16), dp(18), dp(16), dp(14));
        header.setBackgroundColor(NAVY);

        LinearLayout top = new LinearLayout(this);
        top.setGravity(Gravity.CENTER_VERTICAL);
        TextView badge = label("SP", 16, Color.WHITE, Typeface.BOLD);
        badge.setGravity(Gravity.CENTER);
        badge.setBackgroundResource(R.drawable.logo_badge);
        top.addView(badge, new LinearLayout.LayoutParams(dp(44), dp(44)));
        LinearLayout names = new LinearLayout(this);
        names.setOrientation(LinearLayout.VERTICAL);
        names.setPadding(dp(12), 0, 0, 0);
        title = label(moduleTitle(), 22, Color.WHITE, Typeface.BOLD);
        subtitle = label(headerStatusText(), 12, Color.rgb(203, 213, 225), Typeface.NORMAL);
        names.addView(title);
        names.addView(subtitle);
        top.addView(names, new LinearLayout.LayoutParams(0, -2, 1));
        header.addView(top);
        header.addView(summaryStrip(), new LinearLayout.LayoutParams(-1, -2));
        root.addView(header, new LinearLayout.LayoutParams(-1, -2));

        root.addView(tabBar(), new LinearLayout.LayoutParams(-1, dp(58)));
        root.addView(actionBar(), new LinearLayout.LayoutParams(-1, dp(66)));

        list = new ListView(this);
        list.setDividerHeight(1);
        list.setDivider(new android.graphics.drawable.ColorDrawable(BORDER));
        list.setPadding(dp(8), dp(4), dp(8), dp(8));
        list.setClipToPadding(false);
        root.addView(list, new LinearLayout.LayoutParams(-1, 0, 1));
        refreshList();
        list.setOnItemClickListener((parent, view, position, id) -> showDetails(id));
        list.setOnItemLongClickListener((parent, view, position, id) -> {
            confirmDelete(id);
            return true;
        });
    }

    private View summaryStrip() {
        LinearLayout wrap = new LinearLayout(this);
        wrap.setOrientation(LinearLayout.HORIZONTAL);
        LinearLayout.LayoutParams wp = new LinearLayout.LayoutParams(-1, -2);
        wp.setMargins(0, dp(16), 0, 0);
        wrap.setLayoutParams(wp);
        wrap.addView(statCard(String.valueOf(db.visibleCount("musteri")), "Müşteri"), new LinearLayout.LayoutParams(0, dp(72), 1));
        wrap.addView(statCard(String.valueOf(db.pendingCount()), "Bekleyen"), new LinearLayout.LayoutParams(0, dp(72), 1));
        wrap.addView(statCard(lastSyncShort(), "Senkron"), new LinearLayout.LayoutParams(0, dp(72), 1));
        return wrap;
    }

    private TextView statCard(String value, String label) {
        TextView v = label(value + "\n" + label, 13, Color.WHITE, Typeface.BOLD);
        v.setGravity(Gravity.CENTER);
        v.setLines(2);
        v.setBackground(round(Color.rgb(30, 41, 59), dp(12), Color.rgb(51, 65, 85)));
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, dp(72), 1);
        lp.setMargins(dp(3), 0, dp(3), 0);
        v.setLayoutParams(lp);
        return v;
    }

    private View tabBar() {
        HorizontalScrollView scroll = new HorizontalScrollView(this);
        scroll.setHorizontalScrollBarEnabled(false);
        LinearLayout tabs = new LinearLayout(this);
        tabs.setPadding(dp(8), dp(7), dp(8), dp(7));
        String[][] items = {{"musteri","Müşteri"},{"stok","Stok"},{"servis","Servis"},{"satis","Satış"},{"tahsilat","Tahsilat"},{"bakim","Bakım"}};
        for (String[] it : items) {
            Button b = tabButton(it[1], module.equals(it[0]));
            b.setOnClickListener(v -> {
                module = it[0];
                adapter = null;
                showHome();
            });
            tabs.addView(b, new LinearLayout.LayoutParams(dp(105), dp(44)));
        }
        scroll.addView(tabs);
        return scroll;
    }

    private View actionBar() {
        LinearLayout actions = new LinearLayout(this);
        actions.setPadding(dp(10), dp(8), dp(10), dp(6));
        Button add = primaryButton("+ Ekle");
        Button sync = outlineButton("Senkron");
        Button logout = outlineButton("Çıkış");
        actions.addView(add, new LinearLayout.LayoutParams(0, dp(48), 1));
        actions.addView(sync, new LinearLayout.LayoutParams(0, dp(48), 1));
        actions.addView(logout, new LinearLayout.LayoutParams(0, dp(48), 1));
        add.setOnClickListener(v -> showAddDialog());
        sync.setOnClickListener(v -> doSync());
        logout.setOnClickListener(v -> {
            db.clearSession();
            showLogin();
        });
        return actions;
    }

    private void refreshList() {
        if (listCursor != null) listCursor.close();
        listCursor = db.visible(module);
        adapter = new SimpleCursorAdapter(
            this,
            android.R.layout.simple_list_item_2,
            listCursor,
            new String[]{"title", "subtitle"},
            new int[]{android.R.id.text1, android.R.id.text2},
            0
        );
        adapter.setViewBinder((view, cursor, columnIndex) -> {
            if (view.getId() == android.R.id.text2) {
                TextView tv = (TextView)view;
                tv.setTextColor(MUTED);
                tv.setTextSize(12);
                String base = cursor.getString(cursor.getColumnIndexOrThrow("subtitle"));
                String synced = cursor.getString(cursor.getColumnIndexOrThrow("synced_at"));
                tv.setText(base + (synced == null ? "  - bekleyen senkron" : "  - senkronize"));
                return true;
            }
            if (view.getId() == android.R.id.text1) {
                TextView tv = (TextView)view;
                tv.setTextColor(TEXT);
                tv.setTextSize(15);
                tv.setTypeface(null, Typeface.BOLD);
                return true;
            }
            return false;
        });
        list.setAdapter(adapter);
        if (title != null) title.setText(moduleTitle());
        if (subtitle != null) subtitle.setText(headerStatusText());
    }

    private void showDetails(long rowId) {
        String name = db.titleByRowId(module, rowId);
        String detail = db.detailByRowId(module, rowId);
        new AlertDialog.Builder(this)
            .setTitle(name.isEmpty() ? moduleTitle() : name)
            .setMessage(detail.isEmpty() ? "Detay bulunamadı." : detail)
            .setNegativeButton("Kapat", null)
            .setPositiveButton("Sil", (d, w) -> {
                db.softDelete(module, rowId);
                refreshList();
            })
            .show();
    }

    private void confirmDelete(long rowId) {
        String name = db.titleByRowId(module, rowId);
        new AlertDialog.Builder(this)
            .setTitle("Kaydı sil")
            .setMessage((name.isEmpty() ? "Bu kayıt" : name) + " silinsin mi? Silme işlemi internet gelince web panelle senkronize edilir.")
            .setNegativeButton("Vazgeç", null)
            .setPositiveButton("Sil", (d, w) -> {
                db.softDelete(module, rowId);
                refreshList();
            })
            .show();
    }

    private void showAddDialog() {
        if (!"musteri".equals(module) && db.firstCustomerUuid().isEmpty()) {
            toast("Önce bir müşteri ekleyin.");
            return;
        }
        switch (module) {
            case "stok": stockDialog(); break;
            case "servis": serviceDialog(); break;
            case "satis": saleDialog(); break;
            case "tahsilat": collectionDialog(); break;
            case "bakim": maintenanceDialog(); break;
            default: customerDialog();
        }
    }

    private void customerDialog() {
        EditText ad = input("Ad", false), soyad = input("Soyad", false), tel = input("Telefon", false), email = input("E-posta", false), adres = input("Adres", false);
        double[] location = {Double.NaN, Double.NaN};
        TextView locationStatus = label("Konum seçilmedi", 12, MUTED, Typeface.NORMAL);
        Button locationButton = outlineButton("Konumu al");
        LinearLayout customerForm = form(ad, soyad, tel, email, adres);
        customerForm.addView(locationButton, new LinearLayout.LayoutParams(-1, dp(48)));
        customerForm.addView(locationStatus, new LinearLayout.LayoutParams(-1, dp(34)));
        locationButton.setOnClickListener(v -> captureCurrentLocation(locationStatus, location));

        dialog("Müşteri ekle", customerForm, () -> {
            if (ad.getText().toString().trim().isEmpty() || soyad.getText().toString().trim().isEmpty()) { toast("Ad ve soyad zorunlu."); return; }
            Double lat = Double.isNaN(location[0]) ? null : location[0];
            Double lng = Double.isNaN(location[1]) ? null : location[1];
            db.addCustomer(val(ad), val(soyad), val(tel), val(email), val(adres), lat, lng);
            refreshList();
        });
    }

    private void captureCurrentLocation(TextView target, double[] out) {
        boolean fine = checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED;
        boolean coarse = checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
        if (!fine && !coarse) {
            requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, LOCATION_PERMISSION_REQUEST);
            toast("Konum izni istendi. İzin verdikten sonra tekrar Konumu al'a basın.");
            return;
        }

        try {
            LocationManager lm = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
            Location best = null;
            for (String provider : lm.getProviders(true)) {
                Location loc = lm.getLastKnownLocation(provider);
                if (loc == null) continue;
                if (best == null || loc.getAccuracy() < best.getAccuracy()) best = loc;
            }
            if (best == null) {
                toast("Konum alınamadı. GPS'i açıp tekrar deneyin.");
                return;
            }
            out[0] = best.getLatitude();
            out[1] = best.getLongitude();
            target.setText("Konum: " + String.format(java.util.Locale.US, "%.5f, %.5f", out[0], out[1]));
            toast("Konum kayda eklendi.");
        } catch (SecurityException e) {
            toast("Konum izni gerekli.");
        }
    }

    private void stockDialog() {
        EditText name = input("Parça / ürün adı", false), brand = input("Marka", false), price = input("Birim fiyat", false), qty = input("Stok miktarı", false);
        price.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        qty.setInputType(InputType.TYPE_CLASS_NUMBER);
        dialog("Stok ekle", form(name, brand, price, qty), () -> {
            if (val(name).isEmpty()) { toast("Ürün adı zorunlu."); return; }
            db.addStock(val(name), val(brand), money(price), number(qty, 0));
            refreshList();
        });
    }

    private void serviceDialog() {
        EditText customer = input("Müşteri", false), total = input("Tutar", false), note = input("Not", false);
        customer.setFocusable(false);
        selectedCustomerUuid = "";
        customer.setOnClickListener(v -> pickCustomer(customer));
        total.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        dialog("Servis ekle", form(customer, total, note), () -> {
            String mu = selectedCustomerUuid.isEmpty() ? db.firstCustomerUuid() : selectedCustomerUuid;
            db.addService(mu, "periyodik_bakim", money(total), val(note));
            refreshList();
        });
    }

    private void saleDialog() {
        EditText customer = input("Müşteri", false), total = input("Tutar", false), note = input("Not", false);
        customer.setFocusable(false);
        selectedCustomerUuid = "";
        customer.setOnClickListener(v -> pickCustomer(customer));
        total.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        dialog("Satış ekle", form(customer, total, note), () -> {
            db.addSale(selectedCustomerUuid.isEmpty() ? db.firstCustomerUuid() : selectedCustomerUuid, money(total), val(note));
            refreshList();
        });
    }

    private void collectionDialog() {
        EditText source = input("Servis / satış", false), amount = input("Tutar", false), method = input("Ödeme yöntemi", false);
        source.setFocusable(false);
        selectedSourceUuid = "";
        selectedSourceTip = "";
        selectedSourceCustomerUuid = "";
        source.setOnClickListener(v -> pickSource(source));
        amount.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        dialog("Tahsilat ekle", form(source, amount, method), () -> {
            if (selectedSourceUuid.isEmpty()) {
                String service = db.firstServiceOrSaleUuid("servis");
                String sale = db.firstServiceOrSaleUuid("satis");
                selectedSourceTip = service.isEmpty() ? "satis" : "servis";
                selectedSourceUuid = service.isEmpty() ? sale : service;
            }
            if (selectedSourceUuid.isEmpty()) { toast("Önce servis veya satış ekleyin."); return; }
            db.addCollection(selectedSourceCustomerUuid.isEmpty() ? db.firstCustomerUuid() : selectedSourceCustomerUuid, selectedSourceTip, selectedSourceUuid, money(amount), val(method).isEmpty() ? "nakit" : val(method));
            refreshList();
        });
    }

    private void maintenanceDialog() {
        EditText customer = input("Müşteri", false), months = input("Periyot ay", false), days = input("Hatırlatma gün", false);
        customer.setFocusable(false);
        selectedCustomerUuid = "";
        customer.setOnClickListener(v -> pickCustomer(customer));
        months.setInputType(InputType.TYPE_CLASS_NUMBER);
        days.setInputType(InputType.TYPE_CLASS_NUMBER);
        dialog("Bakım planı ekle", form(customer, months, days), () -> {
            db.addMaintenance(selectedCustomerUuid.isEmpty() ? db.firstCustomerUuid() : selectedCustomerUuid, number(months, 6), number(days, 7));
            refreshList();
        });
    }

    private void pickCustomer(EditText target) {
        Cursor c = db.customersForPick();
        int count = c.getCount();
        if (count <= 0) { c.close(); toast("Müşteri yok."); return; }
        String[] names = new String[count];
        String[] uuids = new String[count];
        int i = 0;
        while (c.moveToNext()) {
            uuids[i] = c.getString(c.getColumnIndexOrThrow("uuid"));
            names[i] = c.getString(c.getColumnIndexOrThrow("name"));
            i++;
        }
        c.close();
        new AlertDialog.Builder(this)
            .setTitle("Müşteri seç")
            .setItems(names, (d, which) -> {
                selectedCustomerUuid = uuids[which];
                target.setText(names[which]);
            })
            .show();
    }

    private void pickSource(EditText target) {
        Cursor c = db.sourcesForPick();
        int count = c.getCount();
        if (count <= 0) { c.close(); toast("Servis veya satış yok."); return; }
        String[] names = new String[count];
        String[] uuids = new String[count];
        String[] tips = new String[count];
        String[] customerUuids = new String[count];
        int i = 0;
        while (c.moveToNext()) {
            uuids[i] = c.getString(c.getColumnIndexOrThrow("uuid"));
            customerUuids[i] = c.getString(c.getColumnIndexOrThrow("musteri_uuid"));
            tips[i] = c.getString(c.getColumnIndexOrThrow("tip"));
            names[i] = c.getString(c.getColumnIndexOrThrow("name"));
            i++;
        }
        c.close();
        new AlertDialog.Builder(this)
            .setTitle("Kaynak seç")
            .setItems(names, (d, which) -> {
                selectedSourceUuid = uuids[which];
                selectedSourceTip = tips[which];
                selectedSourceCustomerUuid = customerUuids[which];
                target.setText(names[which]);
            })
            .show();
    }

    private LinearLayout form(EditText... fields) {
        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL);
        form.setPadding(dp(16), 0, dp(16), 0);
        for (EditText e : fields) form.addView(e, new LinearLayout.LayoutParams(-1, dp(54)));
        return form;
    }

    private void dialog(String title, View view, Runnable save) {
        new AlertDialog.Builder(this)
            .setTitle(title)
            .setView(view)
            .setNegativeButton("Vazgeç", null)
            .setPositiveButton("Kaydet", (d, w) -> save.run())
            .show();
    }

    private void doLogin(String email, String password) {
        if (email.trim().isEmpty() || password.isEmpty()) { toast("E-posta ve şifre gerekli."); return; }
        progress.setVisibility(View.VISIBLE);
        new AsyncTask<Void, Void, String>() {
            JSONObject data;
            @Override protected String doInBackground(Void... unused) {
                try {
                    JSONObject body = new JSONObject();
                    body.put("email", email.trim());
                    body.put("sifre", password);
                    body.put("device_name", "Android");
                    body.put("device_id", Settings.Secure.getString(getContentResolver(), Settings.Secure.ANDROID_ID));
                    data = ApiClient.post("/api/auth.php?action=mobile_login", "", body).getJSONObject("data");
                    return "";
                } catch (Exception e) { return e.getMessage(); }
            }
            @Override protected void onPostExecute(String err) {
                progress.setVisibility(View.GONE);
                if (!err.isEmpty()) { toast(err); return; }
                db.setSetting("token", data.optString("token"));
                db.setSetting("firma_adi", data.optString("firma_adi"));
                db.setSetting("email", data.optString("email"));
                db.setSetting("ad_soyad", data.optString("ad_soyad"));
                showHome();
                doSync(true);
            }
        }.execute();
    }

    private void doSync() {
        doSync(true);
    }

    private void doSync(boolean notifyStart) {
        if (notifyStart) toast("Senkron başladı...");
        new AsyncTask<Void, Void, String>() {
            int count;
            @Override protected String doInBackground(Void... unused) {
                try { count = new SyncManager(db).sync(); return ""; }
                catch (Exception e) { return e.getMessage(); }
            }
            @Override protected void onPostExecute(String err) {
                if (!err.isEmpty()) { toast("Senkron olmadı: " + err); return; }
                refreshList();
                if (notifyStart || count > 0) toast("Senkron tamam: " + count + " kayıt");
            }
        }.execute();
    }

    private String moduleTitle() {
        switch (module) {
            case "stok": return "Stok";
            case "servis": return "Servisler";
            case "satis": return "Satışlar";
            case "tahsilat": return "Tahsilatlar";
            case "bakim": return "Bakım";
            default: return "Müşteriler";
        }
    }

    private String headerStatusText() {
        String firma = db.getSetting("firma_adi");
        String last = db.getSetting("last_sync");
        return (firma.isEmpty() ? "Mobil offline mod" : firma) +
            " - " + db.visibleCount(module) + " kayıt" +
            " - bekleyen: " + db.pendingCount() +
            (last.isEmpty() ? " - henüz senkron yok" : " - son senkron: " + last);
    }

    private String lastSyncShort() {
        String last = db.getSetting("last_sync");
        if (last.isEmpty()) return "Yok";
        return last.length() >= 16 ? last.substring(11, 16) : last;
    }

    private TextView label(String text, int sp, int color, int style) {
        TextView v = new TextView(this);
        v.setText(text);
        v.setTextSize(sp);
        v.setTextColor(color);
        v.setGravity(Gravity.CENTER_VERTICAL);
        v.setTypeface(null, style);
        return v;
    }

    private EditText input(String hint, boolean password) {
        EditText e = new EditText(this);
        e.setHint(hint);
        e.setSingleLine(true);
        e.setTextSize(15);
        e.setPadding(dp(12), 0, dp(12), 0);
        e.setBackground(round(Color.WHITE, dp(10), BORDER));
        if (password) e.setInputType(0x00000081);
        return e;
    }

    private Button button(String text) {
        Button b = new Button(this);
        b.setText(text);
        b.setAllCaps(false);
        return b;
    }

    private Button primaryButton(String text) {
        Button b = button(text);
        b.setTextColor(Color.WHITE);
        b.setTypeface(null, Typeface.BOLD);
        b.setBackground(round(BLUE, dp(10), BLUE));
        return b;
    }

    private Button outlineButton(String text) {
        Button b = button(text);
        b.setTextColor(TEXT);
        b.setTypeface(null, Typeface.BOLD);
        b.setBackground(round(Color.WHITE, dp(10), BORDER));
        return b;
    }

    private Button tabButton(String text, boolean active) {
        Button b = button(text);
        b.setTextColor(active ? Color.WHITE : TEXT);
        b.setTypeface(null, active ? Typeface.BOLD : Typeface.NORMAL);
        b.setBackground(round(active ? NAVY : Color.WHITE, dp(22), active ? NAVY : BORDER));
        return b;
    }

    private GradientDrawable round(int fill, int radius, int stroke) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(fill);
        d.setCornerRadius(radius);
        d.setStroke(dp(1), stroke);
        return d;
    }

    private String val(EditText e) { return e.getText().toString().trim(); }
    private double money(EditText e) { try { return Double.parseDouble(val(e)); } catch(Exception ex) { return 0; } }
    private int number(EditText e, int def) { try { return Integer.parseInt(val(e)); } catch(Exception ex) { return def; } }
    private void toast(String msg) { Toast.makeText(this, msg, Toast.LENGTH_LONG).show(); }
    private int dp(int value) { return (int)(value * getResources().getDisplayMetrics().density + 0.5f); }

    @Override
    protected void onDestroy() {
        if (listCursor != null) listCursor.close();
        super.onDestroy();
    }
}
