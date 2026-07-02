<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Servis Takip Panel') ?> — Servis Takip Panel</title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="assets/css/tailwind.css">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Sidebar */
        .sidebar-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 1rem; border-radius: 0.5rem;
            color: #94a3b8; font-size: 0.875rem; font-weight: 500;
            transition: all 0.15s; cursor: pointer; text-decoration: none;
        }
        .sidebar-link:hover { background: rgba(255,255,255,.07); color: #e2e8f0; }
        .sidebar-link.active { background: #2563eb; color: #fff; }
        .sidebar-link .icon { width: 1.125rem; text-align: center; }

        /* Cards */
        .card {
            background: #fff; border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        /* Stat cards */
        .stat-card {
            background: #fff; border-radius: 0.875rem;
            border: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
            transition: all .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .stat-card:hover {
            box-shadow: 0 4px 20px rgba(37,99,235,.12);
            border-color: #bfdbfe;
            transform: translateY(-2px);
        }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: .2rem .6rem; border-radius: 9999px; font-size: .75rem; font-weight: 500; }
        .badge-green  { background: #dcfce7; color: #15803d; }
        .badge-red    { background: #fee2e2; color: #dc2626; }
        .badge-yellow { background: #fef9c3; color: #a16207; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .badge-gray   { background: #f1f5f9; color: #475569; }
        .badge-purple { background: #f3e8ff; color: #7c3aed; }
        .badge-orange { background: #fff7ed; color: #c2410c; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: .375rem; padding: .5rem 1rem; border-radius: .5rem; font-size: .875rem; font-weight: 500; transition: all .15s; cursor: pointer; border: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .btn-success { background: #dcfce7; color: #15803d; }
        .btn-success:hover { background: #bbf7d0; }
        .btn-warning { background: #fef9c3; color: #a16207; }
        .btn-warning:hover { background: #fef08a; }
        .btn-sm { padding: .375rem .75rem; font-size: .8rem; }
        .btn-icon { padding: .5rem; border-radius: .5rem; }

        /* Form inputs */
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: .5rem .75rem;
            border: 1px solid #e2e8f0; border-radius: .5rem;
            font-size: .875rem; color: #1e293b;
            background: #fff; transition: border-color .15s, box-shadow .15s;
            outline: none;
        }
        .form-input.pl-9 { padding-left: 2.25rem; }
        .relative > .fa-search.absolute { pointer-events: none; }
        .form-select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 2.25rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 1rem;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }
        .form-label { display: block; font-size: .8rem; font-weight: 600; color: #475569; margin-bottom: .35rem; }

        /* Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
        .data-table td { padding: .875rem 1rem; font-size: .875rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .data-table tbody tr:hover td { background: #f8fafc; }
        .data-table tbody tr:last-child td { border-bottom: none; }

        /* Modal */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(15,23,42,.45);
            backdrop-filter: blur(2px); z-index: 50;
            display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .modal-box {
            background: #fff; border-radius: 1rem; width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,.18);
            max-height: 90vh; overflow-y: auto;
        }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: .5rem; }

        /* Toast */
        .toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999; display: flex; flex-direction: column; gap: .5rem; }
        .toast { padding: .875rem 1.25rem; border-radius: .75rem; font-size: .875rem; font-weight: 500; color: #fff; box-shadow: 0 10px 25px rgba(0,0,0,.15); display: flex; align-items: center; gap: .5rem; min-width: 280px; }
        .toast-success { background: #059669; }
        .toast-error   { background: #dc2626; }
        .toast-warning { background: #d97706; }

        /* Page header */
        .page-header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; position: sticky; top: 0; z-index: 10; }

        /* Loading spinner */
        .spinner { width: 1.25rem; height: 1.25rem; border: 2px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Sidebar logo area */
        .sidebar-logo { padding: 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,.07); }

        /* Ödeme durumu renkleri */
        .odeme-odenmedi { background: #fee2e2; color: #dc2626; }
        .odeme-kismi    { background: #fef9c3; color: #a16207; }
        .odeme-odendi   { background: #dcfce7; color: #15803d; }

        .mobile-bottom-nav { display: none; }

        @media (max-width: 768px) {
            body { background: #f8fafc; }

            .app-shell {
                height: auto;
                min-height: 100vh;
                overflow: visible;
                display: block;
            }

            .desktop-sidebar { display: none !important; }

            .app-main {
                min-height: 100vh;
                overflow: visible;
                padding-bottom: 5.25rem;
            }

            .page-header {
                padding: .75rem 1rem;
                gap: .75rem;
                align-items: flex-start;
            }
            .page-header h1 { font-size: 1rem; line-height: 1.25rem; }
            .page-header > div:first-child { min-width: 0; flex: 1; }
            .page-header > div:last-child { gap: .35rem; flex-wrap: wrap; justify-content: flex-end; }
            .page-header .rounded-lg.px-3 { padding-left: .5rem; padding-right: .5rem; }

            main.flex-1 {
                padding: .875rem !important;
                overflow: visible;
            }

            .stat-card {
                padding: .875rem;
                border-radius: .75rem;
            }
            .stat-card:hover { transform: none; }
            .stat-card p.text-3xl { font-size: 1.5rem; line-height: 2rem; }
            .stat-card p.text-2xl { font-size: 1.25rem; line-height: 1.75rem; }

            .card {
                border-radius: .75rem;
                box-shadow: 0 1px 2px rgba(0,0,0,.03);
            }
            .card.p-6 { padding: 1rem !important; }

            .grid.grid-cols-2,
            .grid.grid-cols-3,
            .grid.grid-cols-4,
            .grid.md\:grid-cols-2,
            .grid.md\:grid-cols-4,
            .grid.lg\:grid-cols-2,
            .grid.lg\:grid-cols-3,
            .grid.xl\:grid-cols-4 {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            .modal-body .grid.grid-cols-2,
            .modal-body .grid.grid-cols-3,
            form .grid.grid-cols-2,
            form .grid.grid-cols-3 {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            .flex.flex-wrap.items-center.justify-between,
            .flex.flex-wrap.items-start.justify-between {
                align-items: stretch;
            }

            .form-input,
            .form-select,
            .form-textarea {
                min-height: 2.6rem;
                font-size: 16px;
            }

            .w-24, .w-28, .w-32, .w-36, .w-40, .w-44, .w-48, .w-52, .w-56, .w-72 {
                width: 100% !important;
            }

            .btn {
                min-height: 2.5rem;
                justify-content: center;
                white-space: nowrap;
            }
            .btn-icon {
                width: 2.5rem;
                min-width: 2.5rem;
                padding: .5rem;
            }
            .btn-sm { min-height: 2.25rem; }

            .modal-backdrop {
                align-items: flex-end;
                padding: .5rem;
            }
            .modal-box {
                max-width: 100% !important;
                max-height: 94vh !important;
                border-radius: 1rem 1rem .75rem .75rem;
            }
            .modal-header {
                padding: 1rem;
                position: sticky;
                top: 0;
                background: #fff;
                z-index: 2;
            }
            .modal-body { padding: 1rem; }
            .modal-footer {
                padding: .875rem 1rem;
                position: sticky;
                bottom: 0;
                background: #fff;
                flex-direction: column-reverse;
            }
            .modal-footer .btn { width: 100%; }

            .modal-body .flex.gap-2,
            form .flex.gap-2 {
                flex-wrap: wrap;
            }
            .modal-body .flex.gap-2 > .flex-1,
            form .flex.gap-2 > .flex-1 {
                flex-basis: 100%;
            }
            .modal-body .flex.gap-2 > input,
            .modal-body .flex.gap-2 > select,
            form .flex.gap-2 > input,
            form .flex.gap-2 > select {
                flex: 1 1 8rem;
            }

            .overflow-x-auto {
                margin-left: -.875rem;
                margin-right: -.875rem;
                padding-left: .875rem;
                padding-right: .875rem;
                -webkit-overflow-scrolling: touch;
            }
            .data-table {
                min-width: 720px;
            }
            .data-table th,
            .data-table td {
                padding: .625rem .75rem;
                font-size: .8rem;
            }

            #formMap { height: 240px !important; }
            #detailMap { height: 220px !important; }

            .toast-container {
                left: .75rem;
                right: .75rem;
                bottom: 5rem;
            }
            .toast {
                min-width: 0;
                width: 100%;
                padding: .75rem .875rem;
            }

            .mobile-bottom-nav {
                position: fixed;
                left: .75rem;
                right: .75rem;
                bottom: .75rem;
                z-index: 60;
                display: flex;
                gap: .25rem;
                background: rgba(15, 23, 42, .96);
                border: 1px solid rgba(255,255,255,.08);
                border-radius: 1rem;
                padding: .35rem;
                box-shadow: 0 18px 45px rgba(15,23,42,.22);
                backdrop-filter: blur(12px);
                overflow-x: auto;
                scrollbar-width: none;
            }
            .mobile-bottom-nav::-webkit-scrollbar { display: none; }
            .mobile-bottom-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .18rem;
                flex: 0 0 4rem;
                min-height: 3.15rem;
                border-radius: .75rem;
                color: #94a3b8;
                font-size: .66rem;
                font-weight: 600;
                text-decoration: none;
            }
            .mobile-bottom-nav a i { font-size: .95rem; }
            .mobile-bottom-nav a.active {
                background: #2563eb;
                color: #fff;
            }
        }
    </style>
</head>
<body>
<div class="app-shell flex h-screen overflow-hidden">

    <!-- ========= SIDEBAR ========= -->
    <aside class="desktop-sidebar w-60 flex-shrink-0 flex flex-col" style="background:#0f172a; overflow-y:auto;">

        <!-- Logo -->
        <?php
        $_headerLogo  = '';
        $_headerFirma = 'Servis Takip Panel';
        if (isset($_SESSION['firma_id'])) {
            try {
                $_db = Database::getInstance();
                $_logoRow = $_db->fetchOne(
                    "SELECT deger FROM ayarlar WHERE firma_id=? AND anahtar='fatura_logo'",
                    [$_SESSION['firma_id']]
                );
                $_firmaRow = $_db->fetchOne(
                    "SELECT deger FROM ayarlar WHERE firma_id=? AND anahtar='firma_adi'",
                    [$_SESSION['firma_id']]
                );
                if ($_logoRow && !empty($_logoRow['deger'])) $_headerLogo = $_logoRow['deger'];
                if ($_firmaRow && !empty($_firmaRow['deger'])) $_headerFirma = $_firmaRow['deger'];
            } catch(Exception $e) {}
        }
        ?>
        <div class="sidebar-logo flex items-center gap-3">
            <?php if ($_headerLogo): ?>
                <img src="<?= htmlspecialchars($_headerLogo) ?>"
                     alt="Logo"
                     class="h-9 w-9 object-contain rounded-xl flex-shrink-0"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="w-9 h-9 bg-blue-600 rounded-xl items-center justify-center flex-shrink-0 hidden">
                    <i class="fas fa-clipboard-check text-white text-sm"></i>
                </div>
            <?php else: ?>
                <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clipboard-check text-white text-sm"></i>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <div class="text-white font-bold text-sm leading-tight truncate" title="<?= htmlspecialchars($_headerFirma) ?>"><?= htmlspecialchars($_headerFirma) ?></div>
                <div class="text-slate-400 text-xs">Servis Takip Panel</div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 p-3 space-y-0.5">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-3 py-2 mt-1">Ana Menü</div>

            <a href="?page=dashboard" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie icon"></i> Kontrol Paneli
            </a>
            <a href="?page=musteriler" class="sidebar-link <?= ($activePage ?? '') === 'musteriler' ? 'active' : '' ?>">
                <i class="fas fa-users icon"></i> Müşteriler
            </a>
            <a href="?page=servisler" class="sidebar-link <?= ($activePage ?? '') === 'servisler' ? 'active' : '' ?>">
                <i class="fas fa-wrench icon"></i> Servisler
            </a>
            <a href="?page=satislar" class="sidebar-link <?= ($activePage ?? '') === 'satislar' ? 'active' : '' ?>">
                <i class="fas fa-cart-shopping icon"></i> Satışlar
            </a>

            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-3 py-2 mt-3">Finans</div>

            <a href="?page=tahsilatlar" class="sidebar-link <?= ($activePage ?? '') === 'tahsilatlar' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave icon"></i> Tahsilat
            </a>

            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-3 py-2 mt-3">Yönetim</div>

            <a href="?page=bakimlar" class="sidebar-link <?= ($activePage ?? '') === 'bakimlar' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check icon"></i> Bakım Takibi
            </a>
            <a href="?page=stok" class="sidebar-link <?= ($activePage ?? '') === 'stok' ? 'active' : '' ?>">
                <i class="fas fa-boxes-stacked icon"></i> Stok
            </a>
            <a href="?page=raporlar" class="sidebar-link <?= ($activePage ?? '') === 'raporlar' ? 'active' : '' ?>">
                <i class="fas fa-file-chart-column icon"></i> Raporlar
            </a>

            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-3 py-2 mt-3">Sistem</div>

            <a href="?page=ayarlar" class="sidebar-link <?= ($activePage ?? '') === 'ayarlar' ? 'active' : '' ?>">
                <i class="fas fa-gear icon"></i> Ayarlar
            </a>
        </nav>

        <!-- Bottom: Kullanıcı + Çıkış -->
        <?php $_desktopMod = (bool) getenv('STP_DATA_DIR'); ?>
        <div class="p-3 border-t border-white/5">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-xs font-bold"><?= strtoupper(substr($_SESSION['firma_adi'] ?? 'S', 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white text-xs font-semibold truncate"><?= htmlspecialchars($_headerFirma) ?></div>
                    <?php if (!$_desktopMod): ?>
                    <div class="text-slate-500 text-xs truncate"><?= htmlspecialchars($_SESSION['firma_adi'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!$_desktopMod): ?>
                <a href="cikis.php" title="Çıkış Yap"
                   class="text-slate-500 hover:text-red-400 transition p-1 rounded-lg hover:bg-white/5 flex-shrink-0"
                   onclick="return confirm('Çıkış yapmak istediğinize emin misiniz?')">
                    <i class="fas fa-right-from-bracket text-sm"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="text-xs text-slate-600 text-center">
                <?= date('d.m.Y') ?> — <?= date('H:i') ?>
            </div>
        </div>
    </aside>

    <nav class="mobile-bottom-nav" aria-label="Mobil menü">
        <a href="?page=dashboard" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i><span>Panel</span>
        </a>
        <a href="?page=musteriler" class="<?= ($activePage ?? '') === 'musteriler' ? 'active' : '' ?>">
            <i class="fas fa-users"></i><span>Müşteri</span>
        </a>
        <a href="?page=servisler" class="<?= ($activePage ?? '') === 'servisler' ? 'active' : '' ?>">
            <i class="fas fa-wrench"></i><span>Servis</span>
        </a>
        <a href="?page=satislar" class="<?= ($activePage ?? '') === 'satislar' ? 'active' : '' ?>">
            <i class="fas fa-cart-shopping"></i><span>Satış</span>
        </a>
        <a href="?page=tahsilatlar" class="<?= ($activePage ?? '') === 'tahsilatlar' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i><span>Tahsilat</span>
        </a>
        <a href="?page=bakimlar" class="<?= ($activePage ?? '') === 'bakimlar' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i><span>Bakım</span>
        </a>
        <a href="?page=stok" class="<?= ($activePage ?? '') === 'stok' ? 'active' : '' ?>">
            <i class="fas fa-boxes-stacked"></i><span>Stok</span>
        </a>
        <a href="?page=raporlar" class="<?= ($activePage ?? '') === 'raporlar' ? 'active' : '' ?>">
            <i class="fas fa-file-chart-column"></i><span>Rapor</span>
        </a>
        <a href="?page=ayarlar" class="<?= ($activePage ?? '') === 'ayarlar' ? 'active' : '' ?>">
            <i class="fas fa-gear"></i><span>Ayar</span>
        </a>
    </nav>

    <!-- ========= MAIN CONTENT ========= -->
    <div class="app-main flex-1 flex flex-col overflow-hidden">

        <!-- Page Header -->
        <header class="page-header flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
                <?php if (!empty($pageBreadcrumb)): ?>
                    <span class="text-slate-300">/</span>
                    <span class="text-sm text-slate-500"><?= htmlspecialchars($pageBreadcrumb) ?></span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <?php if (!empty($_SESSION['admin_support_mode'])): ?>
                <span class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <i class="fas fa-headset text-xs"></i> Destek Modu
                </span>
                <a href="cikis.php" class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-1.5 rounded-full"
                   onclick="return confirm('Destek modundan çıkıp admin panele dönmek istiyor musunuz?')">
                    <i class="fas fa-arrow-left text-xs"></i> Admin Panele Dön
                </a>
                <?php endif; ?>
                <?php if ($_desktopMod): ?>
                <span class="inline-flex items-center gap-1.5 bg-blue-50 border border-blue-200 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <i class="fas fa-computer text-xs"></i> Masaüstü
                </span>
                <?php if (getenv('STP_LOCAL_ONLY') === '1'): ?>
                <span class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-full hidden sm:inline-flex">
                    <i class="fas fa-lock text-xs"></i> Lokal Lifetime
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold px-3 py-1.5 rounded-full hidden sm:inline-flex">
                    <i class="fas fa-wifi text-xs"></i> Otomatik Senkron
                </span>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-xs text-slate-400 hidden sm:inline">
                    <i class="fas fa-circle text-green-400 text-xs mr-1"></i>Sistem Aktif
                </span>
                <?php endif; ?>
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5">
                    <div class="w-5 h-5 bg-blue-600 rounded-md flex items-center justify-center">
                        <span class="text-white text-xs font-bold"><?= strtoupper(substr($_headerFirma, 0, 1)) ?></span>
                    </div>
                    <span class="text-xs font-medium text-slate-700 hidden sm:inline"><?= htmlspecialchars($_headerFirma) ?></span>
                    <?php if (!$_desktopMod): ?>
                    <a href="cikis.php" title="Çıkış" class="text-slate-400 hover:text-red-500 transition ml-1"
                       onclick="return confirm('Çıkış yapmak istiyor musunuz?')">
                        <i class="fas fa-right-from-bracket text-xs"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Toast Container -->
        <div id="toastContainer" class="toast-container"></div>

        <!-- Scrollable content area -->
        <main class="flex-1 overflow-y-auto p-6">

