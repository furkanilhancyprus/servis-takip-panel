<?php
session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/remember.php';

remember_try_restore(Database::getInstance());

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$stpLoggedIn = !empty($_SESSION['firma_id']);
$stpAccountLabel = trim((string)($_SESSION['firma_adi'] ?? $_SESSION['ad_soyad'] ?? 'Hesabım'));
if ($stpAccountLabel === '') {
    $stpAccountLabel = 'Hesabım';
}

function stp_app_href(string $guestHref): string {
    global $stpLoggedIn;
    return $stpLoggedIn ? 'index.php' : $guestHref;
}

function stp_app_label(string $guestLabel): string {
    global $stpLoggedIn;
    return $stpLoggedIn ? 'Panele Git' : $guestLabel;
}

function stp_account_label(): string {
    global $stpAccountLabel;
    return $stpAccountLabel;
}

function stp_account_initial(): string {
    $label = stp_account_label();
    if (preg_match('/^\s*(.)/u', $label, $m)) {
        return strtoupper($m[1]);
    }
    return 'H';
}

function stp_download_exists(string $relativePath): bool {
    return is_file(__DIR__ . '/' . ltrim($relativePath, '/'));
}

function stp_download_button(string $relativePath, string $label, string $iconClass, string $classes, string $fallbackMessage): void {
    if (stp_download_exists($relativePath)) {
        echo '<a href="' . htmlspecialchars($relativePath) . '" class="' . htmlspecialchars($classes) . '"><i class="' . htmlspecialchars($iconClass) . '"></i> ' . htmlspecialchars($label) . '</a>';
        return;
    }
    echo '<button type="button" data-chat-message="' . htmlspecialchars($fallbackMessage) . '" class="' . htmlspecialchars($classes) . '"><i class="fas fa-clock"></i> Yükleniyor - Talep Et</button>';
}
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servis Takip Panel — Su Arıtma Servis Yönetim Sistemi</title>
    <meta name="description" content="Su arıtma firmanızı dijitalleştirin. Müşteri takibi, periyodik bakım yönetimi, stok ve tahsilat — tek platformda.">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }

        .hero-gradient {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 50%, #4f46e5 100%);
        }

        .feature-card {
            transition: transform .2s, box-shadow .2s;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(37,99,235,.12);
        }

        .stat-num {
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .step-line::after {
            content: '';
            position: absolute;
            top: 24px;
            left: calc(50% + 24px);
            width: calc(100% - 48px);
            height: 2px;
            background: linear-gradient(90deg, #bfdbfe, #e0f2fe);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .float-anim { animation: float 4s ease-in-out infinite; }

        .nav-link { transition: color .15s; }
        .nav-link:hover { color: #2563eb; }

        /* Smooth scroll */
        html { scroll-behavior: smooth; }

        @media (max-width: 420px) {
            .landing-brand-text { display: none; }
            .landing-nav-cta { gap: .5rem; }
        }
    </style>
</head>
<body class="bg-white text-slate-800">

<!-- ═══════════════════════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════════════════════ -->
<nav id="navbar" class="fixed top-0 w-full z-50 transition-all duration-300" style="background: rgba(255,255,255,0.92); backdrop-filter: blur(12px); border-bottom: 1px solid transparent;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center shadow-md">
                    <i class="fas fa-clipboard-check text-white"></i>
                </div>
                <div class="landing-brand-text">
                    <span class="font-bold text-slate-900 text-lg leading-none">Servis Takip</span>
                    <span class="text-blue-600 font-bold text-lg leading-none"> Panel</span>
                </div>
            </div>

            <!-- Nav links (desktop) -->
            <div class="hidden md:flex items-center gap-7">
                <a href="#ozellikler" class="text-sm text-slate-600 nav-link font-medium">Özellikler</a>
                <a href="#nasil-calisir" class="text-sm text-slate-600 nav-link font-medium">Nasıl Çalışır</a>
                <a href="#fiyatlar" class="text-sm text-slate-600 nav-link font-medium">Fiyatlar</a>
                <a href="#iletisim" class="text-sm text-slate-600 nav-link font-medium">İletişim</a>
            </div>

            <!-- CTA -->
            <div class="landing-nav-cta flex items-center gap-3">
                <?php if ($stpLoggedIn): ?>
                    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-semibold bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg transition max-w-[190px]">
                        <span class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold shrink-0">
                            <?= htmlspecialchars(stp_account_initial()) ?>
                        </span>
                        <span class="truncate"><?= htmlspecialchars(stp_account_label()) ?></span>
                    </a>
                <?php else: ?>
                    <a href="giris.php" class="text-sm font-semibold text-slate-700 hover:text-blue-600 transition whitespace-nowrap">Giriş Yap</a>
                    <a href="kayit.php?paket=ucretsiz" class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-3 sm:px-4 py-2 rounded-lg transition whitespace-nowrap">
                        Ücretsiz Başla
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════════ -->
<section class="hero-gradient pt-32 pb-24 px-4 relative overflow-hidden">

    <!-- Dekoratif daireler -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
    <div class="absolute top-1/2 left-1/4 w-32 h-32 bg-white/5 rounded-full"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        <div class="grid lg:grid-cols-2 gap-12 items-center">

            <!-- Sol: Metin -->
            <div>
                <div class="inline-flex items-center gap-2 bg-white/15 text-white px-4 py-1.5 rounded-full text-sm font-medium mb-6 border border-white/20">
                    <i class="fas fa-clipboard-check text-cyan-300 text-xs"></i>
                    Su Arıtma Sektörüne Özel Yazılım
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6">
                    Servisinizi<br>
                    <span class="text-cyan-300">Dijitalleştirin</span>
                </h1>

                <p class="text-lg text-blue-100 mb-8 leading-relaxed max-w-xl">
                    Müşteri takibinden periyodik bakım yönetimine, stok kontrolünden tahsilata kadar tüm iş süreçlerinizi tek platformda yönetin. Kağıtsız, hatasız, verimli.
                </p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="<?= htmlspecialchars(stp_app_href('kayit.php?paket=ucretsiz')) ?>"
                        class="inline-flex items-center justify-center gap-2 bg-white text-blue-700 hover:bg-blue-50 font-bold px-7 py-4 rounded-xl transition text-base shadow-lg shadow-blue-900/20">
                        <i class="fas fa-rocket"></i>
                        <?= htmlspecialchars(stp_app_label('Ücretsiz Başla')) ?>
                    </a>
                    <a href="#indir"
                        class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 text-white border border-white/25 font-semibold px-7 py-4 rounded-xl transition text-base">
                        <i class="fas fa-download"></i>
                        Uygulama İndir
                    </a>
                </div>

                <div class="flex items-center gap-6 mt-8">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full bg-cyan-400 border-2 border-white flex items-center justify-center text-xs font-bold text-white">A</div>
                        <div class="w-8 h-8 rounded-full bg-indigo-400 border-2 border-white flex items-center justify-center text-xs font-bold text-white">M</div>
                        <div class="w-8 h-8 rounded-full bg-emerald-400 border-2 border-white flex items-center justify-center text-xs font-bold text-white">K</div>
                        <div class="w-8 h-8 rounded-full bg-orange-400 border-2 border-white flex items-center justify-center text-xs font-bold text-white">+</div>
                    </div>
                    <div class="text-blue-100 text-sm">
                        <span class="font-semibold text-white">200+</span> firma zaten kullanıyor
                    </div>
                    <div class="flex items-center gap-1 text-yellow-300 text-sm">
                        <i class="fas fa-star text-xs"></i><i class="fas fa-star text-xs"></i><i class="fas fa-star text-xs"></i><i class="fas fa-star text-xs"></i><i class="fas fa-star text-xs"></i>
                        <span class="text-blue-100 ml-1">4.9</span>
                    </div>
                </div>
            </div>

            <!-- Sağ: Dashboard Mockup -->
            <div class="hidden lg:block float-anim">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/30 max-w-lg mx-auto">
                    <!-- Mockup header -->
                    <div class="bg-slate-800 px-4 py-3 flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        <div class="flex-1 bg-slate-700 rounded-md h-5 ml-2 flex items-center px-3">
                            <span class="text-slate-400 text-xs">servistakippanel.com/dashboard</span>
                        </div>
                    </div>
                    <!-- Mockup içerik -->
                    <div class="p-5 bg-slate-50">
                        <!-- Stat cards -->
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-100">
                                <div class="text-xs text-slate-400 mb-1">Müşteriler</div>
                                <div class="text-2xl font-bold text-slate-800">248</div>
                                <div class="text-xs text-green-500 mt-0.5"><i class="fas fa-arrow-up text-xs"></i> +12</div>
                            </div>
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-100">
                                <div class="text-xs text-slate-400 mb-1">Bu Ay Servis</div>
                                <div class="text-2xl font-bold text-slate-800">34</div>
                                <div class="text-xs text-green-500 mt-0.5"><i class="fas fa-arrow-up text-xs"></i> +8</div>
                            </div>
                            <div class="bg-white rounded-xl p-3 shadow-sm border border-slate-100">
                                <div class="text-xs text-slate-400 mb-1">Tahsilat</div>
                                <div class="text-xl font-bold text-slate-800">8.4K₺</div>
                                <div class="text-xs text-green-500 mt-0.5"><i class="fas fa-arrow-up text-xs"></i> %23</div>
                            </div>
                        </div>
                        <!-- Bakım listesi -->
                        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-3">
                            <div class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Yaklaşan Bakımlar</div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between py-1.5 border-b border-slate-50">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-droplet text-blue-600 text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-slate-700">Ahmet Yılmaz</div>
                                            <div class="text-xs text-slate-400">Full Servis</div>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">2 gün</span>
                                </div>
                                <div class="flex items-center justify-between py-1.5 border-b border-slate-50">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-droplet text-emerald-600 text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-slate-700">Fatma Kaya</div>
                                            <div class="text-xs text-slate-400">Membran Değişimi</div>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">5 gün</span>
                                </div>
                                <div class="flex items-center justify-between py-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-droplet text-red-500 text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-slate-700">Mehmet Demir</div>
                                            <div class="text-xs text-slate-400">4'lü Filtre</div>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Gecikti!</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dalgalı alt kenar -->
    <div class="absolute bottom-0 left-0 right-0">
        <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 60L60 50C120 40 240 20 360 15C480 10 600 20 720 25C840 30 960 30 1080 25C1200 20 1320 10 1380 5L1440 0V60H1380C1320 60 1200 60 1080 60C960 60 840 60 720 60C600 60 480 60 360 60C240 60 120 60 60 60H0Z" fill="white"/>
        </svg>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     İSTATİSTİKLER
════════════════════════════════════════════════════════════ -->
<section class="py-16 px-4 border-b border-slate-100">
    <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div>
                <div class="text-4xl font-extrabold stat-num mb-1">200+</div>
                <div class="text-slate-500 text-sm">Aktif Firma</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold stat-num mb-1">50K+</div>
                <div class="text-slate-500 text-sm">Yönetilen Müşteri</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold stat-num mb-1">%99.9</div>
                <div class="text-slate-500 text-sm">Sistem Erişilebilirliği</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold stat-num mb-1">7/24</div>
                <div class="text-slate-500 text-sm">Teknik Destek</div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     ÖZELLİKLER
════════════════════════════════════════════════════════════ -->
<section id="ozellikler" class="py-24 px-4 bg-white">
    <div class="max-w-7xl mx-auto">

        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
                <i class="fas fa-star text-xs"></i> Özellikler
            </div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
                Su arıtma servisine özel<br>her şey burada
            </h2>
            <p class="text-slate-500 text-lg max-w-2xl mx-auto">
                Sektörün ihtiyaçlarına göre tasarlanmış, kullanımı kolay araçlar.
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Kart 1 -->
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Müşteri Yönetimi</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Tüm müşterilerinizi kayıt altına alın. İletişim bilgileri, adres, notlar ve servis geçmişine tek ekrandan ulaşın.
                </p>
                <ul class="mt-4 space-y-1.5">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Hızlı arama ve filtreleme</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Servis geçmişi takibi</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Özel müşteri notları</li>
                </ul>
            </div>

            <!-- Kart 2 -->
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-12 h-12 bg-cyan-100 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-calendar-check text-cyan-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Periyodik Bakım Takibi</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Müşterilerinizin bakım tarihlerini otomatik takip edin. Geciken ve yaklaşan bakımları anında görün, hiçbirini kaçırmayın.
                </p>
                <ul class="mt-4 space-y-1.5">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Otomatik tarih hesaplama</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Gecikme uyarıları</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Özelleştirilebilir periyot</li>
                </ul>
            </div>

            <!-- Kart 3 -->
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-wrench text-indigo-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Servis Takibi</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Arıza ve bakım servislerini kayıt altına alın. Yapılan işlemler, kullanılan parçalar ve ücretleri detaylıca belirtin.
                </p>
                <ul class="mt-4 space-y-1.5">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Arıza & periyodik bakım</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>İşlem ve parça takibi</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Ödeme durumu izleme</li>
                </ul>
            </div>

            <!-- Kart 4 -->
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-boxes-stacked text-emerald-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Stok Yönetimi</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Membran, filtre, tank ve diğer parçalarınızın stok durumunu takip edin. Kritik seviye uyarılarıyla hiçbir zaman stoksuz kalmayın.
                </p>
                <ul class="mt-4 space-y-1.5">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Anlık stok durumu</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Kritik stok uyarısı</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Servis ile entegre</li>
                </ul>
            </div>

            <!-- Kart 5 -->
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-12 h-12 bg-violet-100 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-money-bill-wave text-violet-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Tahsilat & Finans</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Ödeme takibini kolayca yapın. Nakit, kart, havale — tüm tahsilatlarınızı kayıt altına alın, bakiyeyi görün.
                </p>
                <ul class="mt-4 space-y-1.5">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Çoklu ödeme yöntemi</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Bekleyen tahsilatlar</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Finansal özet</li>
                </ul>
            </div>

            <!-- Kart 6 -->
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center mb-4">
                    <i class="fas fa-chart-pie text-orange-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Raporlar & Dashboard</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Firmanızın performansını gerçek zamanlı grafikler ve raporlarla takip edin. Doğru kararlar için doğru veriler.
                </p>
                <ul class="mt-4 space-y-1.5">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Gelir & gider grafikleri</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Satış & servis raporları</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 text-xs w-4"></i>Excel/PDF export</li>
                </ul>
            </div>

        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     NASIL ÇALIŞIR
════════════════════════════════════════════════════════════ -->
<section id="nasil-calisir" class="py-24 px-4 bg-slate-50">
    <div class="max-w-6xl mx-auto">

        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
                <i class="fas fa-map-signs text-xs"></i> Nasıl Çalışır
            </div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
                3 adımda başlayın
            </h2>
            <p class="text-slate-500 text-lg max-w-xl mx-auto">
                Kurulum yok, teknik bilgi gerekmez. Bugün kayıt olun, bugün kullanmaya başlayın.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 relative">

            <div class="text-center relative">
                <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-blue-200">
                    <span class="text-white font-bold text-xl">1</span>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Kayıt Olun</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Firma adınız, e-posta adresiniz ve şifrenizle dakikalar içinde ücretsiz hesabınızı oluşturun.
                </p>
                <!-- Bağlantı oku (masaüstü) -->
                <div class="hidden md:block absolute top-7 left-[calc(50%+36px)] w-[calc(100%-72px)] h-0.5 bg-gradient-to-r from-blue-200 to-cyan-200"></div>
            </div>

            <div class="text-center relative">
                <div class="w-14 h-14 bg-cyan-500 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-cyan-200">
                    <span class="text-white font-bold text-xl">2</span>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Müşterilerinizi Ekleyin</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Mevcut müşteri listenizi sisteme girin. Her müşteri için bakım periyodunu ayarlayın.
                </p>
                <div class="hidden md:block absolute top-7 left-[calc(50%+36px)] w-[calc(100%-72px)] h-0.5 bg-gradient-to-r from-cyan-200 to-indigo-200"></div>
            </div>

            <div class="text-center">
                <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-indigo-200">
                    <span class="text-white font-bold text-xl">3</span>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Yönetmeye Başlayın</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Servis kaydedin, stok takip edin, tahsilat yapın. Dashboard'unuzu her gün kontrol edin.
                </p>
            </div>

        </div>

        <div class="text-center mt-12">
            <a href="<?= htmlspecialchars(stp_app_href('kayit.php?paket=ucretsiz')) ?>" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-8 py-4 rounded-xl transition shadow-lg shadow-blue-200 text-base">
                <i class="fas fa-rocket"></i> <?= $stpLoggedIn ? 'Panele Git' : 'Hemen Başla — Ücretsiz' ?>
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FİYATLANDIRMA
════════════════════════════════════════════════════════════ -->
<section id="fiyatlar" class="py-24 px-4 bg-white">
    <div class="max-w-5xl mx-auto">

        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
                <i class="fas fa-tag text-xs"></i> Fiyatlar
            </div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">
                Şeffaf ve uygun fiyatlar
            </h2>
            <p class="text-slate-500 text-lg">Bulut aboneliği ya da tamamen lokal kullanım. İhtiyacınıza göre seçin.</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">

            <!-- Free -->
            <div class="border border-slate-200 rounded-2xl p-7 hover:border-blue-200 transition bg-white">
                <div class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Ücretsiz</div>
                <div class="text-4xl font-extrabold text-slate-900 mb-1">₺0</div>
                <div class="text-slate-400 text-sm mb-6">başlangıç için</div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Web paneliyle deneme kullanımı</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Temel müşteri ve servis takibi</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Sistemi risksiz keşfetme</li>
                    <li class="flex items-center gap-2 text-sm text-slate-400"><i class="fas fa-times text-slate-300 w-4"></i>Gelişmiş senkron ve ekip özellikleri hariç</li>
                </ul>
                <a href="<?= htmlspecialchars(stp_app_href('kayit.php?paket=ucretsiz')) ?>" class="block text-center py-2.5 border-2 border-slate-200 hover:border-blue-400 text-slate-700 font-semibold rounded-xl transition text-sm">
                    <?= htmlspecialchars(stp_app_label('Ücretsiz Başla')) ?>
                </a>
            </div>

            <!-- Standard -->
            <div class="border-2 border-blue-600 rounded-2xl p-7 relative shadow-xl shadow-blue-50 bg-white">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-bold px-4 py-1 rounded-full">
                    En Popüler
                </div>
                <div class="text-sm font-semibold text-blue-600 uppercase tracking-wide mb-3">Standart</div>
                <div class="text-4xl font-extrabold text-slate-900 mb-1">₺250</div>
                <div class="text-slate-400 text-sm mb-6">/ aylık</div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Web paneli</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Masaüstü uygulaması</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Mobil uygulama</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Offline kullanım ve otomatik senkron</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Raporlar, stok ve tahsilat takibi</li>
                </ul>
                <a href="<?= htmlspecialchars(stp_app_href('kayit.php?paket=standart')) ?>" class="block text-center py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition text-sm shadow-lg shadow-blue-200">
                    <?= $stpLoggedIn ? 'Panele Git' : 'Standart ile Başla' ?>
                </a>
            </div>

            <!-- Premium -->
            <div class="border border-slate-200 rounded-2xl p-7 hover:border-blue-200 transition bg-white">
                <div class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Premium</div>
                <div class="text-4xl font-extrabold text-slate-900 mb-1">İletişime geçin</div>
                <div class="text-slate-400 text-sm mb-6">çok kullanıcılı ekipler için</div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Standart paketteki her şey</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Öncelikli destek</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Kurulum ve geçiş desteği</li>
                    <li class="flex items-center gap-2 text-sm text-slate-600"><i class="fas fa-check text-green-500 w-4"></i>Özel ihtiyaçlara göre planlama</li>
                </ul>
                <a href="#supportChat" data-chat-message="Premium paket hakkında bilgi almak istiyorum."
                   class="block text-center py-2.5 border-2 border-slate-200 hover:border-blue-400 text-slate-700 font-semibold rounded-xl transition text-sm">
                    Premium İçin Görüşelim
                </a>
            </div>

        </div>

        <div class="mt-6 border border-slate-200 rounded-2xl p-6 bg-slate-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-sm font-bold text-slate-900 mb-1">Lokal Lifetime</div>
                <p class="text-sm text-slate-600">
                    Sadece Windows masaüstünde, internetsiz ve limitsiz çalışacak lokal kullanım isteyen firmalar için ayrıca teklif hazırlanır.
                </p>
            </div>
            <a href="#supportChat" data-chat-message="Lokal Lifetime paket için teklif almak istiyorum."
               class="inline-flex justify-center items-center gap-2 px-5 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-semibold text-sm transition">
                <i class="fas fa-envelope"></i> Lokal Paket İçin İletişime Geçin
            </a>
        </div>

        <div class="mt-8 bg-slate-50 border border-slate-200 rounded-2xl p-5 text-center">
            <p class="text-sm text-slate-600">
                Aylık abonelikle web, masaüstü ve mobil senkron kullanabilirsiniz. Tamamen lokal masaüstü kullanım için ayrıca bizimle iletişime geçebilirsiniz.
            </p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     SSS
════════════════════════════════════════════════════════════ -->
<section class="py-20 px-4 bg-slate-50">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-extrabold text-slate-900 mb-3">Sık Sorulan Sorular</h2>
        </div>
        <div class="space-y-4" id="faq">
            <?php
            $faqs = [
                ['Teknik bilgi gerekiyor mu?', 'Hayır. Servis Takip Panel, teknik bilgi gerektirmeden kullanılabilecek şekilde tasarlanmıştır. İnternet bağlantısı olan her cihazdan erişebilirsiniz.'],
                ['Verilerim güvende mi?', 'Tüm verileriniz şifrelenmiş sunucularda saklanır. Başka firmaların verilerine asla erişilemez. Her firma tamamen izole bir ortamda çalışır.'],
                ['Mevcut müşteri listem varsa ne yapabilirim?', 'Müşterilerinizi tek tek ekleyebilirsiniz. İlerleyen sürümlerde Excel ile toplu içe aktarma özelliği de eklenecektir.'],
                ['İnternet olmadan çalışıyor mu?', 'Evet. SaaS paketinde masaüstü uygulaması internetsiz çalışır ve bağlantı geldiğinde web hesabınızla senkronize olur. Lokal lifetime pakette ise sistem tamamen bilgisayarınızda çalışır; web, mobil ve bulut senkron kullanılmaz.'],
                ['Lokal lifetime paket nedir?', 'Tek seferlik ödeme ile yalnızca Windows masaüstü uygulamasını kullanırsınız. Veriler bilgisayarınızda kalır, aylık ödeme yoktur; web paneli, mobil uygulama ve bulut senkronizasyon dahil değildir.'],
                ['Mobil uygulama var mı?', 'Evet. Android uygulaması native olarak hazırlanmıştır; offline kayıt alabilir ve internet bağlantısı geldiğinde web hesabıyla senkronize olur.'],
            ];
            foreach ($faqs as $i => [$q, $a]): ?>
            <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
                <button onclick="toggleFaq(<?= $i ?>)"
                    class="w-full flex items-center justify-between p-5 text-left">
                    <span class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($q) ?></span>
                    <i id="faq-icon-<?= $i ?>" class="fas fa-plus text-slate-400 text-sm transition flex-shrink-0 ml-4"></i>
                </button>
                <div id="faq-body-<?= $i ?>" class="hidden px-5 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-50 pt-3">
                    <?= htmlspecialchars($a) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     İLETİŞİM / CTA
════════════════════════════════════════════════════════════ -->

<!-- ═══════════════════════════════════════════════ İNDİR ══ -->
<section id="indir" class="py-24 px-4 bg-slate-50">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-14">
            <span class="inline-block bg-blue-100 text-blue-700 text-sm font-semibold px-4 py-1.5 rounded-full mb-4">📱 Uygulama İndir</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mb-4">Her Cihazda Yanınızda</h2>
            <p class="text-slate-500 text-lg max-w-xl mx-auto">Web, masaüstü ve mobil — istediğiniz cihazdan kullanın.</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">

            <!-- Windows Masaüstü -->
            <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                <div class="flex items-start gap-5">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center flex-shrink-0 text-2xl">🖥️</div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-slate-900 mb-1">Windows Masaüstü</h3>
                        <p class="text-slate-500 text-sm mb-1">İnternet gerektirmez. Tek seferlik kurulum.</p>
                        <div class="flex items-center gap-2 text-xs text-slate-400 mb-5">
                            <span>Windows 10/11</span>
                            <span>•</span>
                            <span>64-bit</span>
                            <span>•</span>
                            <span>~85 MB</span>
                        </div>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5 text-xs text-amber-700">
                            <strong>Web hesabınızla giriş yapın</strong> — İnternetsiz çalışır, bağlantı gelince otomatik senkronize olur.
                        </div>
                        <?php stp_download_button(
                            'downloads/ServisTakipPanel-Kurulum.exe',
                            'Setup İndir (.exe)',
                            'fas fa-download',
                            'inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl transition shadow text-sm',
                            'Windows masaüstü setup dosyasını indirmek istiyorum.'
                        ); ?>
                    </div>
                </div>
            </div>

            <!-- Lokal Lifetime -->
            <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                <div class="flex items-start gap-5">
                    <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center flex-shrink-0 text-2xl">🔒</div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-slate-900 mb-1">Lokal Lifetime</h3>
                        <p class="text-slate-500 text-sm mb-1">Bulutsuz, sadece bu bilgisayarda çalışan sürüm.</p>
                        <div class="flex items-center gap-2 text-xs text-slate-400 mb-5">
                            <span>Windows 10/11</span>
                            <span>•</span>
                            <span>Senkron yok</span>
                            <span>•</span>
                            <span>Tek seferlik teklif</span>
                        </div>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5 text-xs text-amber-700">
                            Web panel, mobil uygulama ve bulut senkron dahil değildir. Teklif ve kurulum bilgisi için iletişime geçin.
                        </div>
                        <?php stp_download_button(
                            'downloads/ServisTakipPanel-Lokal-Lifetime-Kurulum.exe',
                            'Lokal Kurulum Dosyası',
                            'fas fa-download',
                            'inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold px-6 py-3 rounded-xl transition shadow text-sm',
                            'Lokal Lifetime kurulum dosyasını indirmek istiyorum.'
                        ); ?>
                    </div>
                </div>
            </div>

            <!-- Android Mobil -->
            <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                <div class="flex items-start gap-5">
                    <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center flex-shrink-0 text-2xl">📱</div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-slate-900 mb-1">Android Uygulaması</h3>
                        <p class="text-slate-500 text-sm mb-1">Mobilde takip, bildirim ve hızlı erişim.</p>
                        <div class="flex items-center gap-2 text-xs text-slate-400 mb-5">
                            <span>Android 7.0+</span>
                            <span>•</span>
                            <span>Native uygulama</span>
                            <span>•</span>
                            <span>~5 MB</span>
                        </div>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 mb-5 text-xs text-emerald-700">
                            Native Android uygulaması offline kayıt tutar; internet bağlantısı geldiğinde web panelle senkronize olur.
                        </div>
                        <?php stp_download_button(
                            'downloads/ServisTakipPanel.apk',
                            'APK İndir',
                            'fab fa-android',
                            'inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-3 rounded-xl transition shadow text-sm',
                            'Android APK dosyasını indirmek istiyorum.'
                        ); ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Bilgi Al -->
        <div class="mt-10 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-8 text-white text-center">
            <h3 class="text-xl font-bold mb-2">Masaüstü Uygulamasını Kullanmak İstiyor musunuz?</h3>
            <p class="text-blue-100 text-sm mb-5">Web hesabınızla giriş yapın; masaüstünde offline çalışın, internete bağlanınca verileriniz senkronize olsun.</p>
            <a href="#supportChat" data-chat-message="Masaüstü uygulaması hakkında bilgi almak istiyorum."
               class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold px-7 py-3 rounded-xl transition hover:bg-blue-50 text-sm">
                <i class="fas fa-envelope"></i> Bilgi Al
            </a>
        </div>
    </div>
</section>

<section id="iletisim" class="py-24 px-4 hero-gradient relative overflow-hidden">
    <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
    <div class="max-w-3xl mx-auto text-center relative z-10">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">
            Servisinizi bir üst seviyeye taşıyın
        </h2>
        <p class="text-blue-100 text-lg mb-8 leading-relaxed">
            Bugün kaydolun, dakikalar içinde kullanmaya başlayın.<br>Kredi kartı gerekmez.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?= htmlspecialchars(stp_app_href('kayit.php?paket=ucretsiz')) ?>"
                class="inline-flex items-center justify-center gap-2 bg-white text-blue-700 hover:bg-blue-50 font-bold px-8 py-4 rounded-xl transition text-base shadow-xl">
                <i class="fas fa-rocket"></i> <?= htmlspecialchars(stp_app_label('Ücretsiz Başla')) ?>
            </a>
            <a href="#supportChat" data-chat-message="Merhaba, destek almak istiyorum."
                class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 text-white border border-white/25 font-semibold px-8 py-4 rounded-xl transition text-base">
                <i class="fas fa-envelope"></i> Bize Yazın
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════════════ -->
<footer class="bg-slate-900 text-slate-400 py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="grid md:grid-cols-4 gap-8 mb-10">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                    <span class="font-bold text-white text-lg">Servis Takip Panel</span>
                </div>
                <p class="text-sm leading-relaxed max-w-xs">
                    Su arıtma servisi firmalarına özel, bulut tabanlı yönetim yazılımı.
                </p>
            </div>
            <div>
                <div class="text-white font-semibold text-sm mb-4">Ürün</div>
                <ul class="space-y-2 text-sm">
                    <li><a href="#ozellikler" class="hover:text-white transition">Özellikler</a></li>
                    <li><a href="#fiyatlar" class="hover:text-white transition">Fiyatlar</a></li>
                    <li><a href="<?= htmlspecialchars(stp_app_href('kayit.php?paket=ucretsiz')) ?>" class="hover:text-white transition"><?= $stpLoggedIn ? 'Panel' : 'Kayıt Ol' ?></a></li>
                    <li><a href="<?= $stpLoggedIn ? 'index.php' : 'giris.php' ?>" class="hover:text-white transition"><?= $stpLoggedIn ? 'Panele Git' : 'Giriş Yap' ?></a></li>
                </ul>
            </div>
            <div>
                <div class="text-white font-semibold text-sm mb-4">İletişim</div>
                <ul class="space-y-2 text-sm">
                    <li><a href="#supportChat" data-chat-message="Merhaba, destek almak istiyorum." class="hover:text-white transition"><i class="fas fa-envelope mr-2"></i>destek@servistakippanel.com</a></li>
                    <li><span><i class="fas fa-phone mr-2"></i>0850 XXX XX XX</span></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 pt-6 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs">
            <span>© <?= date('Y') ?> Servis Takip Panel. Tüm hakları saklıdır.</span>
            <div class="flex gap-4">
                <a href="#" class="hover:text-white transition">Gizlilik Politikası</a>
                <a href="#" class="hover:text-white transition">Kullanım Koşulları</a>
            </div>
        </div>
    </div>
</footer>

<div id="supportChat" class="fixed bottom-5 right-5 z-50 font-sans">
    <button id="chatToggle"
            class="w-14 h-14 rounded-full bg-blue-600 hover:bg-blue-700 text-white shadow-xl grid place-items-center text-xl">
        <i class="fas fa-comments"></i>
    </button>
    <div id="chatPanel" class="hidden absolute bottom-16 right-0 w-[min(360px,calc(100vw-2rem))] bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-slate-900 text-white px-4 py-3 flex items-center justify-between">
            <div>
                <div class="font-bold text-sm">Servis Takip Panel Destek</div>
                <div class="text-xs text-slate-300">Genelde kısa sürede yanıtlarız</div>
            </div>
            <button id="chatClose" class="text-slate-300 hover:text-white"><i class="fas fa-xmark"></i></button>
        </div>

        <div id="chatMessages" class="h-72 overflow-y-auto bg-slate-50 p-4 space-y-3 text-sm">
            <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 text-slate-600">
                Merhaba, nasıl yardımcı olabiliriz?
            </div>
        </div>

        <form id="chatStartForm" class="p-4 space-y-3 border-t border-slate-100">
            <input id="chatName" placeholder="Ad soyad"
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
            <input id="chatEmail" type="email" placeholder="E-posta (opsiyonel)"
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
            <input id="chatPhone" placeholder="Telefon (opsiyonel)"
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
            <textarea id="chatFirstMessage" rows="3" placeholder="Mesajınızı yazın"
                      class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2.5 font-semibold text-sm">
                Destek Mesajı Gönder
            </button>
        </form>

        <form id="chatSendForm" class="hidden p-3 border-t border-slate-100 flex gap-2">
            <input id="chatMessageInput" placeholder="Mesaj yazın..."
                   class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
            <button class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-4 text-sm font-semibold">Gönder</button>
        </form>
    </div>
</div>

<script>
// Navbar scroll efekti
window.addEventListener('scroll', () => {
    const nav = document.getElementById('navbar');
    if (window.scrollY > 20) {
        nav.style.borderBottomColor = '#e2e8f0';
        nav.style.boxShadow = '0 1px 20px rgba(0,0,0,.06)';
    } else {
        nav.style.borderBottomColor = 'transparent';
        nav.style.boxShadow = 'none';
    }
});

// FAQ toggle
function toggleFaq(i) {
    const body = document.getElementById('faq-body-' + i);
    const icon = document.getElementById('faq-icon-' + i);
    const isOpen = !body.classList.contains('hidden');
    // Tümünü kapat
    document.querySelectorAll('[id^="faq-body-"]').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('[id^="faq-icon-"]').forEach(el => { el.className = 'fas fa-plus text-slate-400 text-sm transition flex-shrink-0 ml-4'; });
    if (!isOpen) {
        body.classList.remove('hidden');
        icon.className = 'fas fa-minus text-blue-500 text-sm transition flex-shrink-0 ml-4';
    }
}

const chatState = { conversationId: localStorage.getItem('stp_chat_conversation_id') || '', timer: null };
const chatPanel = document.getElementById('chatPanel');
const chatMessages = document.getElementById('chatMessages');
const chatStartForm = document.getElementById('chatStartForm');
const chatSendForm = document.getElementById('chatSendForm');

function openSupportChat(prefill = '') {
    chatPanel.classList.remove('hidden');
    if (prefill) {
        const firstMessage = document.getElementById('chatFirstMessage');
        const followupMessage = document.getElementById('chatMessageInput');
        if (chatStartForm && !chatStartForm.classList.contains('hidden') && firstMessage) {
            firstMessage.value = prefill;
            firstMessage.focus();
        } else if (followupMessage) {
            followupMessage.value = prefill;
            followupMessage.focus();
        }
    }
    chatLoadMessages();
    chatStartPolling();
}

document.querySelectorAll('[data-chat-message]').forEach(el => {
    el.addEventListener('click', e => {
        e.preventDefault();
        openSupportChat(el.dataset.chatMessage || '');
    });
});

document.getElementById('chatToggle').addEventListener('click', () => {
    chatPanel.classList.toggle('hidden');
    if (!chatPanel.classList.contains('hidden')) {
        chatLoadMessages();
        chatStartPolling();
    }
});
document.getElementById('chatClose').addEventListener('click', () => chatPanel.classList.add('hidden'));

function chatRender(messages) {
    if (!messages || !messages.length) return;
    chatMessages.innerHTML = '';
    messages.forEach(m => {
        const wrap = document.createElement('div');
        const admin = m.sender_type === 'admin';
        wrap.className = 'flex ' + (admin ? 'justify-start' : 'justify-end');
        wrap.innerHTML = `<div class="max-w-[82%] rounded-2xl px-4 py-3 ${admin ? 'bg-white border border-slate-200 text-slate-700' : 'bg-blue-600 text-white'}">
            <div>${chatEscape(m.message).replace(/\n/g, '<br>')}</div>
            <div class="text-[10px] mt-1 ${admin ? 'text-slate-400' : 'text-blue-100'}">${chatEscape(m.created_at || '')}</div>
        </div>`;
        chatMessages.appendChild(wrap);
    });
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function chatEscape(text) {
    return String(text || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}

async function chatLoadMessages() {
    if (!chatState.conversationId) return;
    try {
        const r = await fetch(`api/support_chat.php?action=messages&conversation_id=${encodeURIComponent(chatState.conversationId)}`);
        const d = await r.json();
        if (d.success) {
            chatStartForm.classList.add('hidden');
            chatSendForm.classList.remove('hidden');
            chatRender(d.data.messages || []);
        }
    } catch (e) {}
}

function chatStartPolling() {
    if (chatState.timer) return;
    chatState.timer = setInterval(chatLoadMessages, 6000);
}

chatStartForm.addEventListener('submit', async e => {
    e.preventDefault();
    const body = {
        ad_soyad: document.getElementById('chatName').value,
        email: document.getElementById('chatEmail').value,
        telefon: document.getElementById('chatPhone').value,
        konu: 'Landing destek',
        message: document.getElementById('chatFirstMessage').value,
    };
    const r = await fetch('api/support_chat.php?action=start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Mesaj gönderilemedi.'); return; }
    chatState.conversationId = d.data.conversation_id;
    localStorage.setItem('stp_chat_conversation_id', chatState.conversationId);
    chatStartForm.classList.add('hidden');
    chatSendForm.classList.remove('hidden');
    await chatLoadMessages();
    chatStartPolling();
});

chatSendForm.addEventListener('submit', async e => {
    e.preventDefault();
    const input = document.getElementById('chatMessageInput');
    const message = input.value.trim();
    if (!message) return;
    input.value = '';
    const r = await fetch('api/support_chat.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: chatState.conversationId, message }),
    });
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Mesaj gönderilemedi.'); return; }
    await chatLoadMessages();
});

if (chatState.conversationId) {
    chatStartForm.classList.add('hidden');
    chatSendForm.classList.remove('hidden');
}
</script>
</body>
</html>



