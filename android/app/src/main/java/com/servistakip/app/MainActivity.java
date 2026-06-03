package com.servistakip.app;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.location.Location;
import android.location.LocationManager;
import android.net.Uri;
import android.os.AsyncTask;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.CursorAdapter;
import android.widget.EditText;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.ProgressBar;
import android.widget.ScrollView;
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
    private TextView emptyState;
    private TextView title;
    private TextView subtitle;
    private CursorAdapter adapter;
    private Cursor listCursor;
    private String module = "dashboard";
    private String searchQuery = "";
    private static final int LOCATION_PERMISSION_REQUEST = 41;
    private String selectedCustomerUuid = "";
    private String selectedSourceUuid = "";
    private String selectedSourceTip = "";
    private String selectedSourceCustomerUuid = "";
    private String selectedStockUuid = "";
    private String selectedStockName = "";
    private double selectedStockPrice = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        db = new LocalDb(this);
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(BG);
        setContentView(root);
        if (Build.VERSION.SDK_INT >= 21) {
            getWindow().setStatusBarColor(NAVY);
            getWindow().setNavigationBarColor(Color.WHITE);
        }
        if (db.getSetting("token").isEmpty()) showLogin(); else {
            showHome();
            doSync(false);
        }
    }

    private void showLogin() {
        showAuth(false);
    }

    private void showAuth(boolean registerMode) {
        root.removeAllViews();
        root.setGravity(Gravity.NO_GRAVITY);
        root.setPadding(0, 0, 0, 0);
        root.setBackgroundColor(Color.rgb(241, 245, 249));

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        LinearLayout page = new LinearLayout(this);
        page.setOrientation(LinearLayout.VERTICAL);
        page.setPadding(dp(20), dp(22), dp(20), dp(24));
        page.setGravity(Gravity.CENTER_HORIZONTAL);
        scroll.addView(page, new ScrollView.LayoutParams(-1, -2));
        root.addView(scroll, new LinearLayout.LayoutParams(-1, -1));

        LinearLayout hero = new LinearLayout(this);
        hero.setOrientation(LinearLayout.VERTICAL);
        hero.setGravity(Gravity.CENTER_HORIZONTAL);
        hero.setPadding(dp(22), dp(24), dp(22), dp(22));
        GradientDrawable heroBg = new GradientDrawable(GradientDrawable.Orientation.TL_BR, new int[]{NAVY, Color.rgb(29, 78, 216), Color.rgb(14, 165, 233)});
        heroBg.setCornerRadius(dp(24));
        hero.setBackground(heroBg);
        hero.setElevation(dp(8));
        page.addView(hero, new LinearLayout.LayoutParams(-1, -2));

        TextView logo = label("✓", 34, Color.WHITE, Typeface.BOLD);
        logo.setGravity(Gravity.CENTER);
        logo.setBackground(round(Color.rgb(37, 99, 235), dp(22), Color.argb(90, 255, 255, 255)));
        hero.addView(logo, new LinearLayout.LayoutParams(dp(78), dp(78)));

        TextView brand = label("Servis Takip Panel", 28, Color.WHITE, Typeface.BOLD);
        brand.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams brandLp = new LinearLayout.LayoutParams(-1, -2);
        brandLp.setMargins(0, dp(16), 0, dp(6));
        hero.addView(brand, brandLp);

        TextView tagline = label("Arıtma servisleri için müşteri, bakım, stok ve tahsilat takibi.", 14, Color.rgb(219, 234, 254), Typeface.NORMAL);
        tagline.setGravity(Gravity.CENTER);
        tagline.setLineSpacing(dp(2), 1.0f);
        hero.addView(tagline, new LinearLayout.LayoutParams(-1, -2));

        LinearLayout chips = new LinearLayout(this);
        chips.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams chipsLp = new LinearLayout.LayoutParams(-1, -2);
        chipsLp.setMargins(0, dp(18), 0, 0);
        hero.addView(chips, chipsLp);
        chips.addView(chip("Offline"));
        chips.addView(chip("Senkron"));
        chips.addView(chip("Web + Mobil"));

        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(18), dp(18), dp(18), dp(18));
        card.setBackground(round(Color.WHITE, dp(22), BORDER));
        card.setElevation(dp(6));
        LinearLayout.LayoutParams cardLp = new LinearLayout.LayoutParams(-1, -2);
        cardLp.setMargins(0, dp(18), 0, 0);
        page.addView(card, cardLp);

        LinearLayout segment = new LinearLayout(this);
        segment.setPadding(dp(4), dp(4), dp(4), dp(4));
        segment.setBackground(round(Color.rgb(241, 245, 249), dp(16), BORDER));
        card.addView(segment, new LinearLayout.LayoutParams(-1, dp(54)));
        Button loginTab = segmentButton("Giriş Yap", !registerMode);
        Button registerTab = segmentButton("Kayıt Ol", registerMode);
        segment.addView(loginTab, new LinearLayout.LayoutParams(0, -1, 1));
        segment.addView(registerTab, new LinearLayout.LayoutParams(0, -1, 1));
        loginTab.setOnClickListener(v -> showAuth(false));
        registerTab.setOnClickListener(v -> showAuth(true));

        TextView formTitle = label(registerMode ? "Ücretsiz hesap oluştur" : "Hesabınıza giriş yapın", 20, TEXT, Typeface.BOLD);
        LinearLayout.LayoutParams titleLp = new LinearLayout.LayoutParams(-1, -2);
        titleLp.setMargins(0, dp(18), 0, dp(4));
        card.addView(formTitle, titleLp);
        TextView formText = label(registerMode ? "Kayıt sonrası web panel ve mobil senkron aynı hesapla çalışır." : "İnternet yokken kayıt almaya devam edin; bağlantı gelince otomatik eşitleyin.", 13, MUTED, Typeface.NORMAL);
        formText.setLineSpacing(dp(2), 1.0f);
        card.addView(formText, new LinearLayout.LayoutParams(-1, -2));

        EditText company = null;
        EditText fullName = null;
        EditText phone = null;
        EditText password2 = null;
        if (registerMode) {
            company = authField("Firma adı", false);
            fullName = authField("Ad soyad", false);
            phone = authField("Telefon", false);
            addField(card, company, dp(18));
            addField(card, fullName, dp(10));
        }

        EditText email = authField("E-posta", false);
        EditText pass = authField("Şifre", true);
        addField(card, email, registerMode ? dp(10) : dp(18));
        if (registerMode) addField(card, phone, dp(10));
        addField(card, pass, dp(10));
        if (registerMode) {
            password2 = authField("Şifre tekrar", true);
            addField(card, password2, dp(10));
        }

        Button submit = primaryButton(registerMode ? "Ücretsiz kaydı oluştur" : "Giriş yap");
        LinearLayout.LayoutParams submitLp = new LinearLayout.LayoutParams(-1, dp(54));
        submitLp.setMargins(0, dp(18), 0, 0);
        card.addView(submit, submitLp);

        progress = new ProgressBar(this);
        progress.setVisibility(View.GONE);
        LinearLayout.LayoutParams progressLp = new LinearLayout.LayoutParams(dp(42), dp(42));
        progressLp.gravity = Gravity.CENTER_HORIZONTAL;
        progressLp.setMargins(0, dp(12), 0, 0);
        card.addView(progress, progressLp);

        TextView footer = label("ServisTakipPanel.com hesabınızla masaüstü, web ve mobil birlikte çalışır.", 12, MUTED, Typeface.NORMAL);
        footer.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams footerLp = new LinearLayout.LayoutParams(-1, -2);
        footerLp.setMargins(0, dp(14), 0, 0);
        page.addView(footer, footerLp);

        EditText c = company, f = fullName, ph = phone, p2 = password2;
        submit.setOnClickListener(v -> {
            if (registerMode) {
                doRegister(val(c), val(f), val(email), val(ph), val(pass), val(p2));
            } else {
                doLogin(val(email), val(pass));
            }
        });
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
        TextView badge = label("✓", 18, Color.WHITE, Typeface.BOLD);
        badge.setGravity(Gravity.CENTER);
        badge.setBackground(round(BLUE, dp(14), Color.argb(90, 255, 255, 255)));
        top.addView(badge, new LinearLayout.LayoutParams(dp(46), dp(46)));
        LinearLayout names = new LinearLayout(this);
        names.setOrientation(LinearLayout.VERTICAL);
        names.setPadding(dp(12), 0, 0, 0);
        title = label(moduleTitle(), 23, Color.WHITE, Typeface.BOLD);
        subtitle = label(headerStatusText(), 12, Color.rgb(219, 234, 254), Typeface.NORMAL);
        names.addView(title);
        names.addView(subtitle);
        top.addView(names, new LinearLayout.LayoutParams(0, -2, 1));
        TextView cloud = label(db.pendingCount() > 0 ? "Senkron" : "Güncel", 12, Color.WHITE, Typeface.BOLD);
        cloud.setGravity(Gravity.CENTER);
        cloud.setBackground(round(Color.argb(40, 255, 255, 255), dp(14), Color.argb(95, 255, 255, 255)));
        top.addView(cloud, new LinearLayout.LayoutParams(dp(78), dp(32)));
        header.addView(top);
        header.addView(summaryStrip(), new LinearLayout.LayoutParams(-1, -2));
        root.addView(header, new LinearLayout.LayoutParams(-1, -2));

        if ("dashboard".equals(module)) {
            root.addView(dashboardView(), new LinearLayout.LayoutParams(-1, 0, 1));
            root.addView(bottomNav(), new LinearLayout.LayoutParams(-1, dp(72)));
            return;
        }

        root.addView(searchBar(), new LinearLayout.LayoutParams(-1, dp(62)));
        root.addView(actionBar(), new LinearLayout.LayoutParams(-1, dp(66)));

        list = new ListView(this);
        list.setDividerHeight(0);
        list.setDivider(null);
        list.setSelector(new android.graphics.drawable.ColorDrawable(Color.TRANSPARENT));
        list.setPadding(dp(12), dp(8), dp(12), dp(12));
        list.setClipToPadding(false);
        list.setBackgroundColor(BG);
        root.addView(list, new LinearLayout.LayoutParams(-1, 0, 1));
        emptyState = emptyState();
        root.addView(emptyState, new LinearLayout.LayoutParams(-1, 0));
        list.setEmptyView(emptyState);
        refreshList();
        list.setOnItemClickListener((parent, view, position, id) -> showDetails(id));
        list.setOnItemLongClickListener((parent, view, position, id) -> {
            confirmDelete(id);
            return true;
        });
        root.addView(bottomNav(), new LinearLayout.LayoutParams(-1, dp(72)));
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

    private View dashboardView() {
        ScrollView scroll = new ScrollView(this);
        LinearLayout page = new LinearLayout(this);
        page.setOrientation(LinearLayout.VERTICAL);
        page.setPadding(dp(14), dp(14), dp(14), dp(18));
        scroll.addView(page);

        page.addView(label("Bugunku durum", 18, TEXT, Typeface.BOLD), new LinearLayout.LayoutParams(-1, -2));

        LinearLayout row1 = dashRow();
        row1.addView(dashCard("Musteriler", String.valueOf(db.visibleCount("musteri")), "Kayitli musteri", NAVY, "musteri"));
        row1.addView(dashCard("Servisler", String.valueOf(db.visibleCount("servis")), "Saha islemleri", BLUE, "servis"));
        page.addView(row1);

        LinearLayout row2 = dashRow();
        row2.addView(dashCard("Satis", String.valueOf(db.visibleCount("satis")), "Urun ve cihaz", Color.rgb(124, 58, 237), "satis"));
        row2.addView(dashCard("Bekleyen", String.valueOf(db.pendingCount()), "Senkron kuyrugu", ORANGE, "sync"));
        page.addView(row2);

        LinearLayout quick = new LinearLayout(this);
        quick.setOrientation(LinearLayout.VERTICAL);
        quick.setPadding(dp(16), dp(16), dp(16), dp(16));
        quick.setBackground(round(Color.WHITE, dp(18), BORDER));
        LinearLayout.LayoutParams quickLp = new LinearLayout.LayoutParams(-1, -2);
        quickLp.setMargins(0, dp(14), 0, 0);
        page.addView(quick, quickLp);
        quick.addView(label("Hizli islemler", 17, TEXT, Typeface.BOLD));

        Button addCustomer = primaryButton("+ Musteri ekle");
        Button addService = outlineButton("+ Servis ekle");
        Button sync = outlineButton("Senkronize et");
        addQuickButton(quick, addCustomer);
        addQuickButton(quick, addService);
        addQuickButton(quick, sync);
        addCustomer.setOnClickListener(v -> { module = "musteri"; searchQuery = ""; showHome(); customerDialog(); });
        addService.setOnClickListener(v -> { module = "servis"; searchQuery = ""; showHome(); showAddDialog(); });
        sync.setOnClickListener(v -> doSync());
        return scroll;
    }

    private LinearLayout dashRow() {
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, -2);
        lp.setMargins(0, dp(10), 0, 0);
        row.setLayoutParams(lp);
        return row;
    }

    private TextView dashCard(String name, String value, String sub, int color, String target) {
        TextView v = label(value + "\n" + name + "\n" + sub, 13, Color.WHITE, Typeface.BOLD);
        v.setGravity(Gravity.CENTER);
        v.setLines(3);
        v.setBackground(round(color, dp(18), color));
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, dp(118), 1);
        lp.setMargins(dp(4), 0, dp(4), 0);
        v.setLayoutParams(lp);
        if (!"sync".equals(target)) v.setOnClickListener(x -> { module = target; searchQuery = ""; showHome(); });
        return v;
    }

    private void addQuickButton(LinearLayout parent, Button button) {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, dp(50));
        lp.setMargins(0, dp(10), 0, 0);
        parent.addView(button, lp);
    }

    private View searchBar() {
        LinearLayout wrap = new LinearLayout(this);
        wrap.setPadding(dp(12), dp(8), dp(12), dp(4));
        wrap.setBackgroundColor(BG);
        EditText search = authField("Ara: ad, telefon, urun, not...");
        search.setText(searchQuery);
        search.addTextChangedListener(new android.text.TextWatcher() {
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                searchQuery = s.toString();
                if (list != null) refreshList();
            }
            public void afterTextChanged(android.text.Editable s) {}
        });
        wrap.addView(search, new LinearLayout.LayoutParams(-1, dp(50)));
        return wrap;
    }

    private View bottomNav() {
        LinearLayout nav = new LinearLayout(this);
        nav.setPadding(dp(8), dp(7), dp(8), dp(7));
        nav.setBackgroundColor(Color.WHITE);
        String[][] items = {{"dashboard","Ana"},{"musteri","Musteri"},{"servis","Servis"},{"satis","Satis"},{"stok","Stok"}};
        for (String[] it : items) {
            Button b = tabButton(it[1], module.equals(it[0]));
            b.setOnClickListener(v -> {
                module = it[0];
                searchQuery = "";
                adapter = null;
                showHome();
            });
            nav.addView(b, new LinearLayout.LayoutParams(0, dp(54), 1));
        }
        return nav;
    }

    private TextView emptyState() {
        TextView v = label("Henuz kayit yok", 14, MUTED, Typeface.BOLD);
        v.setGravity(Gravity.CENTER);
        v.setLines(3);
        v.setBackgroundColor(BG);
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
        actions.setPadding(dp(12), dp(8), dp(12), dp(8));
        actions.setBackgroundColor(BG);
        Button add = primaryButton("+ Yeni Kayıt");
        Button sync = outlineButton("Senkron");
        Button logout = ghostButton("Çıkış");
        LinearLayout.LayoutParams addLp = new LinearLayout.LayoutParams(0, dp(50), 1.45f);
        addLp.setMargins(0, 0, dp(7), 0);
        LinearLayout.LayoutParams syncLp = new LinearLayout.LayoutParams(0, dp(50), 1f);
        syncLp.setMargins(dp(0), 0, dp(7), 0);
        actions.addView(add, addLp);
        actions.addView(sync, syncLp);
        actions.addView(logout, new LinearLayout.LayoutParams(0, dp(50), .8f));
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
        listCursor = db.visible(module, searchQuery);
        adapter = new RecordAdapter(this, listCursor);
        list.setAdapter(adapter);
        if (title != null) title.setText(moduleTitle());
        if (subtitle != null) subtitle.setText(headerStatusText());
        if (emptyState != null) emptyState.setText("Henuz kayit yok\n+ Yeni Kayit ile baslayin veya arama filtresini temizleyin.");
    }

    private void showDetails(long rowId) {
        String name = db.titleByRowId(module, rowId);
        String detail = db.detailByRowId(module, rowId);
        AlertDialog.Builder builder = new AlertDialog.Builder(this)
            .setTitle(name.isEmpty() ? moduleTitle() : name)
            .setMessage(detail.isEmpty() ? "Detay bulunamadi." : detail)
            .setNegativeButton("Kapat", null);
        if ("musteri".equals(module)) {
            String[] contact = db.customerContactByRowId(rowId);
            builder.setNeutralButton("Ara", (d, w) -> openDial(contact[0]));
            builder.setPositiveButton("Aksiyonlar", (d, w) -> showCustomerActions(rowId));
        } else {
            builder.setPositiveButton("Sil", (d, w) -> {
                db.softDelete(module, rowId);
                if (list == null) showHome(); else refreshList();
            });
        }
        builder.show();
    }

    private void showCustomerActions(long rowId) {
        String[] contact = db.customerContactByRowId(rowId);
        String[] items = {"Telefonla ara", "WhatsApp ac", "Haritada goster", "Kaydi sil"};
        new AlertDialog.Builder(this)
            .setTitle("Musteri aksiyonlari")
            .setItems(items, (d, which) -> {
                if (which == 0) openDial(contact[0]);
                if (which == 1) openWhatsApp(contact[0]);
                if (which == 2) openMap(contact[1], contact[2], contact[3]);
                if (which == 3) {
                    db.softDelete(module, rowId);
                    refreshList();
                }
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
                if (list == null) showHome(); else refreshList();
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
            case "servis": serviceDialogV2(); break;
            case "satis": saleDialogV2(); break;
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

    private void serviceDialogV2() {
        EditText customer = input("Musteri", false), stock = input("Kullanilan urun/parca", false), qty = input("Adet", false), total = input("Tutar", false), note = input("Not", false);
        customer.setFocusable(false);
        stock.setFocusable(false);
        selectedCustomerUuid = "";
        selectedStockUuid = "";
        selectedStockName = "";
        selectedStockPrice = 0;
        customer.setOnClickListener(v -> pickCustomer(customer));
        stock.setOnClickListener(v -> pickStock(stock));
        qty.setInputType(InputType.TYPE_CLASS_NUMBER);
        total.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        dialog("Servis ekle", form(customer, stock, qty, total, note), () -> {
            String mu = selectedCustomerUuid.isEmpty() ? db.firstCustomerUuid() : selectedCustomerUuid;
            int count = number(qty, selectedStockUuid.isEmpty() ? 0 : 1);
            double amount = money(total);
            if (amount <= 0 && selectedStockPrice > 0 && count > 0) amount = selectedStockPrice * count;
            db.addServiceWithStock(mu, "periyodik_bakim", amount, val(note), selectedStockUuid, count, selectedStockName);
            refreshList();
        });
    }

    private void saleDialogV2() {
        EditText customer = input("Musteri", false), stock = input("Satilan urun", false), qty = input("Adet", false), serial = input("Seri No", false), total = input("Tutar", false), note = input("Not", false);
        customer.setFocusable(false);
        stock.setFocusable(false);
        selectedCustomerUuid = "";
        selectedStockUuid = "";
        selectedStockName = "";
        selectedStockPrice = 0;
        customer.setOnClickListener(v -> pickCustomer(customer));
        stock.setOnClickListener(v -> pickStock(stock));
        qty.setInputType(InputType.TYPE_CLASS_NUMBER);
        total.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        dialog("Satis ekle", form(customer, stock, qty, serial, total, note), () -> {
            int count = number(qty, selectedStockUuid.isEmpty() ? 0 : 1);
            double amount = money(total);
            if (amount <= 0 && selectedStockPrice > 0 && count > 0) amount = selectedStockPrice * count;
            db.addSaleWithStock(selectedCustomerUuid.isEmpty() ? db.firstCustomerUuid() : selectedCustomerUuid, amount, val(note), selectedStockUuid, count, selectedStockName, val(serial));
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

    private void pickStock(EditText target) {
        Cursor c = db.stockForPick();
        int count = c.getCount();
        if (count <= 0) { c.close(); toast("Stokta urun yok."); return; }
        String[] names = new String[count];
        String[] uuids = new String[count];
        double[] prices = new double[count];
        int i = 0;
        while (c.moveToNext()) {
            uuids[i] = c.getString(c.getColumnIndexOrThrow("uuid"));
            names[i] = c.getString(c.getColumnIndexOrThrow("name"));
            prices[i] = c.getDouble(c.getColumnIndexOrThrow("birim_fiyat"));
            i++;
        }
        c.close();
        new AlertDialog.Builder(this)
            .setTitle("Urun/parca sec")
            .setItems(names, (d, which) -> {
                selectedStockUuid = uuids[which];
                selectedStockName = names[which];
                selectedStockPrice = prices[which];
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

    private void doRegister(String company, String fullName, String email, String phone, String password, String password2) {
        if (company.isEmpty() || fullName.isEmpty() || email.isEmpty() || password.isEmpty()) {
            toast("Firma, ad soyad, e-posta ve şifre gerekli.");
            return;
        }
        if (!password.equals(password2)) {
            toast("Şifreler eşleşmiyor.");
            return;
        }
        progress.setVisibility(View.VISIBLE);
        new AsyncTask<Void, Void, String>() {
            @Override protected String doInBackground(Void... unused) {
                try {
                    JSONObject body = new JSONObject();
                    body.put("firma_adi", company);
                    body.put("ad_soyad", fullName);
                    body.put("email", email.trim());
                    body.put("telefon", phone);
                    body.put("sifre", password);
                    body.put("sifre2", password2);
                    body.put("paket", "ucretsiz");
                    ApiClient.post("/api/auth.php?action=kayit", "", body);
                    return "";
                } catch (Exception e) { return e.getMessage(); }
            }
            @Override protected void onPostExecute(String err) {
                progress.setVisibility(View.GONE);
                if (!err.isEmpty()) { toast(err); return; }
                toast("Kayıt oluşturuldu. Senkron başlatılıyor...");
                doLogin(email, password);
            }
        }.execute();
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
                if (list == null) showHome(); else refreshList();
                if (notifyStart || count > 0) toast("Senkron tamam: " + count + " kayıt");
            }
        }.execute();
    }

    private String moduleTitle() {
        switch (module) {
            case "dashboard": return "Ana Sayfa";
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

    private void openDial(String phone) {
        if (phone == null || phone.trim().isEmpty()) { toast("Telefon numarasi yok."); return; }
        startActivity(new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + phone.trim())));
    }

    private void openWhatsApp(String phone) {
        if (phone == null || phone.trim().isEmpty()) { toast("Telefon numarasi yok."); return; }
        String clean = phone.replaceAll("[^0-9+]", "");
        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/" + clean.replace("+", "")));
        startActivity(intent);
    }

    private void openMap(String lat, String lng, String address) {
        Uri uri;
        if (lat != null && !lat.isEmpty() && lng != null && !lng.isEmpty()) {
            uri = Uri.parse("geo:" + lat + "," + lng + "?q=" + lat + "," + lng);
        } else if (address != null && !address.trim().isEmpty()) {
            uri = Uri.parse("geo:0,0?q=" + Uri.encode(address));
        } else {
            toast("Konum veya adres yok.");
            return;
        }
        startActivity(new Intent(Intent.ACTION_VIEW, uri));
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

    private EditText authField(String hint, boolean password) {
        EditText e = input(hint, password);
        e.setTextColor(TEXT);
        e.setHintTextColor(Color.rgb(148, 163, 184));
        e.setBackground(round(Color.rgb(248, 250, 252), dp(14), BORDER));
        return e;
    }

    private EditText authField(String hint) {
        return authField(hint, false);
    }

    private void addField(LinearLayout parent, EditText field, int topMargin) {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, dp(54));
        lp.setMargins(0, topMargin, 0, 0);
        parent.addView(field, lp);
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

    private Button ghostButton(String text) {
        Button b = button(text);
        b.setTextColor(MUTED);
        b.setTypeface(null, Typeface.BOLD);
        b.setBackground(round(Color.rgb(241, 245, 249), dp(10), BORDER));
        return b;
    }

    private Button tabButton(String text, boolean active) {
        Button b = button(text);
        b.setTextColor(active ? Color.WHITE : TEXT);
        b.setTypeface(null, active ? Typeface.BOLD : Typeface.NORMAL);
        b.setBackground(round(active ? NAVY : Color.WHITE, dp(22), active ? NAVY : BORDER));
        return b;
    }

    private Button segmentButton(String text, boolean active) {
        Button b = button(text);
        b.setTextColor(active ? Color.WHITE : TEXT);
        b.setTypeface(null, Typeface.BOLD);
        b.setBackground(round(active ? BLUE : Color.TRANSPARENT, dp(13), active ? BLUE : Color.TRANSPARENT));
        return b;
    }

    private TextView chip(String text) {
        TextView v = label(text, 12, Color.WHITE, Typeface.BOLD);
        v.setGravity(Gravity.CENTER);
        v.setPadding(dp(10), 0, dp(10), 0);
        v.setBackground(round(Color.argb(34, 255, 255, 255), dp(14), Color.argb(80, 255, 255, 255)));
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-2, dp(30));
        lp.setMargins(dp(3), 0, dp(3), 0);
        v.setLayoutParams(lp);
        return v;
    }

    private class RecordAdapter extends CursorAdapter {
        RecordAdapter(Context context, Cursor cursor) {
            super(context, cursor, 0);
        }

        @Override
        public View newView(Context context, Cursor cursor, ViewGroup parent) {
            LinearLayout outer = new LinearLayout(context);
            outer.setOrientation(LinearLayout.VERTICAL);
            outer.setPadding(0, 0, 0, dp(10));

            LinearLayout card = new LinearLayout(context);
            card.setOrientation(LinearLayout.HORIZONTAL);
            card.setGravity(Gravity.CENTER_VERTICAL);
            card.setPadding(dp(14), dp(14), dp(14), dp(14));
            card.setBackground(round(Color.WHITE, dp(16), BORDER));
            card.setElevation(dp(2));
            outer.addView(card, new LinearLayout.LayoutParams(-1, -2));

            TextView icon = label("", 18, Color.WHITE, Typeface.BOLD);
            icon.setId(1001);
            icon.setGravity(Gravity.CENTER);
            card.addView(icon, new LinearLayout.LayoutParams(dp(48), dp(48)));

            LinearLayout texts = new LinearLayout(context);
            texts.setOrientation(LinearLayout.VERTICAL);
            texts.setPadding(dp(12), 0, dp(8), 0);
            TextView rowTitle = label("", 15, TEXT, Typeface.BOLD);
            rowTitle.setId(1002);
            TextView rowSub = label("", 12, MUTED, Typeface.NORMAL);
            rowSub.setId(1003);
            rowSub.setSingleLine(false);
            rowSub.setPadding(0, dp(3), 0, 0);
            texts.addView(rowTitle, new LinearLayout.LayoutParams(-1, -2));
            texts.addView(rowSub, new LinearLayout.LayoutParams(-1, -2));
            card.addView(texts, new LinearLayout.LayoutParams(0, -2, 1));

            TextView badge = label("", 11, Color.WHITE, Typeface.BOLD);
            badge.setId(1004);
            badge.setGravity(Gravity.CENTER);
            badge.setPadding(dp(9), 0, dp(9), 0);
            card.addView(badge, new LinearLayout.LayoutParams(-2, dp(28)));
            return outer;
        }

        @Override
        public void bindView(View view, Context context, Cursor cursor) {
            TextView icon = view.findViewById(1001);
            TextView rowTitle = view.findViewById(1002);
            TextView rowSub = view.findViewById(1003);
            TextView badge = view.findViewById(1004);
            String synced = cursor.getString(cursor.getColumnIndexOrThrow("synced_at"));
            String subtitle = cursor.getString(cursor.getColumnIndexOrThrow("subtitle"));
            rowTitle.setText(cursor.getString(cursor.getColumnIndexOrThrow("title")));
            rowSub.setText(subtitle == null || subtitle.isEmpty() ? "Detay için dokunun" : subtitle);
            icon.setText(moduleIcon());
            icon.setBackground(round(moduleColor(), dp(14), moduleColor()));
            badge.setText(synced == null ? "Bekliyor" : "OK");
            badge.setBackground(round(synced == null ? ORANGE : GREEN, dp(14), synced == null ? ORANGE : GREEN));
        }
    }

    private String moduleIcon() {
        switch (module) {
            case "stok": return "S";
            case "servis": return "R";
            case "satis": return "₺";
            case "tahsilat": return "T";
            case "bakim": return "B";
            default: return "M";
        }
    }

    private int moduleColor() {
        switch (module) {
            case "stok": return Color.rgb(14, 165, 233);
            case "servis": return BLUE;
            case "satis": return Color.rgb(124, 58, 237);
            case "tahsilat": return GREEN;
            case "bakim": return ORANGE;
            default: return NAVY;
        }
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
