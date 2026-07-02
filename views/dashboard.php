<?php
$pageTitle  = 'Kontrol Paneli';
$activePage = 'dashboard';
include __DIR__ . '/layout/header.php';
?>

<div x-data="dashboardApp()" x-init="init()" class="space-y-6">

    <!-- Stat Cards - Row 1 -->
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-4 gap-4">

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Müşteri</p>
                    <p class="text-3xl font-bold text-slate-800 mt-1" x-text="stats.toplamMusteri ?? '—'"></p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Servis</p>
                    <p class="text-3xl font-bold text-emerald-600 mt-1" x-text="stats.buAyYapilan ?? '—'"></p>
                </div>
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Ciro</p>
                    <p class="text-xl font-bold text-purple-600 mt-1" x-text="formatCurrency(stats.buAyCiro)"></p>
                    <p class="text-xs text-slate-400 mt-0.5">Servis + Satış</p>
                </div>
                <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-turkish-lira-sign text-purple-500"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Kritik Stok</p>
                    <p class="text-3xl font-bold text-orange-600 mt-1" x-text="stats.kritikStok ?? '—'"></p>
                </div>
                <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-triangle-exclamation text-orange-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards - Row 2: Finans -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        <div class="stat-card border-l-4 border-l-cyan-400">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Planlanan Bakım</p>
                    <p class="text-3xl font-bold text-cyan-600 mt-1" x-text="stats.buAyPlanlanan ?? '—'"></p>
                    <p class="text-xs text-slate-400 mt-0.5">Gecikmiş dahil</p>
                </div>
                <div class="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-check text-cyan-500"></i>
                </div>
            </div>
            <a href="?page=bakimlar" class="text-xs text-cyan-600 hover:underline mt-2 inline-block">Listeye git →</a>
        </div>

        <div class="stat-card border-l-4 border-l-blue-400">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Tahsilat</p>
                    <p class="text-xl font-bold text-blue-600 mt-1" x-text="formatCurrency(stats.buAyTahsilat)"></p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-blue-500"></i>
                </div>
            </div>
        </div>

        <div class="stat-card border-l-4 border-l-red-400">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tahsil Edilmemiş</p>
                    <p class="text-xl font-bold text-red-600 mt-1" x-text="formatCurrency(stats.toplamBekleyen)"></p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock-rotate-left text-red-500"></i>
                </div>
            </div>
            <a href="?page=tahsilatlar" class="text-xs text-red-500 hover:underline mt-2 inline-block">Takip et →</a>
        </div>

        <div class="stat-card border-l-4 border-l-amber-400">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Geciken Bakım</p>
                    <p class="text-3xl font-bold text-red-600 mt-1" x-text="stats.gecikenBakim ?? '—'"></p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Geciken Bakımlar -->
        <div class="card">
            <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i> Geciken Bakımlar
                </h3>
                <span class="badge badge-red" x-text="stats.gecikenBakim ?? 0"></span>
            </div>
            <div class="p-4 space-y-2 max-h-64 overflow-y-auto">
                <template x-if="gecikenler.length === 0">
                    <div class="text-center py-6 text-slate-400 text-sm">
                        <i class="fas fa-check-circle text-2xl text-emerald-300 mb-2 block"></i>
                        Geciken bakım yok
                    </div>
                </template>
                <template x-for="b in gecikenler" :key="b.musteri_id">
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-red-100 bg-red-50">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-red-500 text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate" x-text="`${b.ad} ${b.soyad}`"></p>
                            <p class="text-xs text-red-500" x-text="`${b.gecikme_gun || 0} gün gecikmiş`"></p>
                        </div>
                        <a href="?page=bakimlar" class="text-xs text-blue-600 hover:underline">Detay</a>
                    </div>
                </template>
            </div>
        </div>

        <!-- Yaklaşan Bakımlar -->
        <div class="card">
            <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-clock text-amber-500"></i> Yaklaşan Bakımlar
                </h3>
                <span class="badge badge-yellow" x-text="stats.yaklasanBakim ?? 0"></span>
            </div>
            <div class="p-4 space-y-2 max-h-64 overflow-y-auto">
                <template x-if="yaklasanlar.length === 0">
                    <div class="text-center py-6 text-slate-400 text-sm">
                        <i class="fas fa-calendar-check text-2xl text-slate-200 mb-2 block"></i>
                        Yaklaşan bakım yok
                    </div>
                </template>
                <template x-for="b in yaklasanlar" :key="b.musteri_id">
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-amber-100 bg-amber-50">
                        <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-amber-500 text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate" x-text="`${b.ad} ${b.soyad}`"></p>
                            <p class="text-xs text-amber-600" x-text="`${b.kalan_gun || 0} gün kaldı`"></p>
                        </div>
                        <span class="text-xs text-slate-400" x-text="formatDate(b.sonraki_bakim_tarihi)"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Hızlı İşlemler -->
        <div class="card" style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); border: none;">
            <div class="p-5">
                <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-bolt"></i> Hızlı İşlemler
                </h3>
                <div class="space-y-2.5">
                    <a href="?page=servisler" class="flex items-center gap-3 bg-white/15 hover:bg-white/25 transition text-white rounded-lg px-4 py-3 text-sm font-medium">
                        <i class="fas fa-wrench w-4"></i> Yeni Servis Kaydı
                    </a>
                    <a href="?page=satislar" class="flex items-center gap-3 bg-white/15 hover:bg-white/25 transition text-white rounded-lg px-4 py-3 text-sm font-medium">
                        <i class="fas fa-cart-shopping w-4"></i> Yeni Satış
                    </a>
                    <a href="?page=tahsilatlar" class="flex items-center gap-3 bg-white/15 hover:bg-white/25 transition text-white rounded-lg px-4 py-3 text-sm font-medium">
                        <i class="fas fa-money-bill-wave w-4"></i> Tahsilat Al
                    </a>
                    <a href="?page=musteriler" class="flex items-center gap-3 bg-white/15 hover:bg-white/25 transition text-white rounded-lg px-4 py-3 text-sm font-medium">
                        <i class="fas fa-user-plus w-4"></i> Müşteri Ekle
                    </a>
                    <a href="?page=stok" class="flex items-center gap-3 bg-white/15 hover:bg-white/25 transition text-white rounded-lg px-4 py-3 text-sm font-medium">
                        <i class="fas fa-boxes-stacked w-4"></i> Stok Girişi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Aylık Ciro & Kar -->
        <div class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div>
                    <h3 class="font-semibold text-slate-800">Aylık Ciro & Kar</h3>
                    <p class="text-xs text-slate-400 mt-0.5" x-text="ayLabel + ' özeti'"></p>
                </div>
                <input type="month" class="form-input w-40 text-xs py-1.5" x-model="seciliAy" @change="loadDashboard()">
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <div class="rounded-lg bg-blue-50 border border-blue-100 p-3">
                    <p class="text-xs text-blue-500 font-semibold uppercase">Satış</p>
                    <p class="text-2xl font-bold text-blue-700 mt-1" x-text="ayOzeti.satis_adet || 0"></p>
                </div>
                <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-3">
                    <p class="text-xs text-emerald-600 font-semibold uppercase">Servis</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1" x-text="ayOzeti.servis_adet || 0"></p>
                </div>
                <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                    <p class="text-xs text-slate-500 font-semibold uppercase">Toplam Ciro</p>
                    <p class="text-lg font-bold text-slate-800 mt-1" x-text="formatCurrency(ayOzeti.toplam_ciro || 0)"></p>
                </div>
                <div class="rounded-lg bg-amber-50 border border-amber-100 p-3">
                    <p class="text-xs text-amber-600 font-semibold uppercase">Net Kar</p>
                    <p class="text-lg font-bold text-amber-700 mt-1" x-text="formatCurrency(ayOzeti.net_kar || 0)"></p>
                </div>
            </div>
            <div class="h-56 flex items-end gap-1 border-l border-b border-slate-200 px-2 pt-4 pb-2 overflow-x-auto">
                <template x-for="g in gunlukAyCiro" :key="g.tarih">
                    <div class="h-full flex flex-col justify-end items-center gap-1 min-w-[18px]" :title="`${g.tarih}: ${formatCurrency(g.ciro)} · ${g.satis_adet} satış, ${g.servis_adet} servis`">
                        <div class="w-3 rounded-t bg-blue-500 hover:bg-blue-600 transition"
                             :style="`height:${barHeight(g.ciro)}%`"></div>
                        <span class="text-[10px] text-slate-400" x-text="g.gun"></span>
                    </div>
                </template>
                <template x-if="gunlukAyCiro.length === 0 || maxGunlukCiro === 0">
                    <div class="w-full h-full flex items-center justify-center text-sm text-slate-400">
                        Bu ay için ciro kaydı yok
                    </div>
                </template>
            </div>
        </div>

        <!-- Son Servisler -->
        <div class="card">
            <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800">Son Servisler</h3>
                <a href="?page=servisler" class="text-xs text-blue-600 hover:underline">Tümünü Gör →</a>
            </div>
            <div class="divide-y divide-slate-50 max-h-64 overflow-y-auto">
                <template x-if="sonServisler.length === 0">
                    <div class="text-center py-8 text-slate-400 text-sm">Henüz servis yok</div>
                </template>
                <template x-for="s in sonServisler" :key="s.id">
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             :class="s.servis_tipi === 'ariza' ? 'bg-red-50' : 'bg-blue-50'">
                            <i class="text-xs"
                               :class="s.servis_tipi === 'ariza' ? 'fas fa-wrench text-red-400' : 'fas fa-calendar-check text-blue-400'"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate" x-text="s.musteri_adi"></p>
                            <p class="text-xs text-slate-400" x-text="formatTip(s.servis_tipi)"></p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-semibold text-slate-700" x-text="formatCurrency(s.toplam_tutar)"></p>
                            <span class="badge text-xs"
                                  :class="odemeBadgeClass(s.odeme_durumu)"
                                  x-text="odemeBadgeText(s.odeme_durumu)"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function dashboardApp() {
    return {
        stats: {}, gecikenler: [], yaklasanlar: [], sonServisler: [],
        seciliAy: '<?= date('Y-m') ?>',
        ayOzeti: {},
        gunlukAyCiro: [],

        async init() {
            await this.loadDashboard();
        },

        async loadDashboard() {
            try {
                const d = await api(`api/dashboard.php?ay=${this.seciliAy}`);
                this.stats        = d;
                this.gecikenler   = d.gecikenler   || [];
                this.yaklasanlar  = d.yaklasanlar  || [];
                this.sonServisler = d.sonServisler  || [];
                this.ayOzeti      = d.ayOzeti || {};
                this.gunlukAyCiro = d.gunlukAyCiro || [];
            } catch(e) {}
        },

        get ayLabel() {
            if (!this.seciliAy) return '';
            const [y, m] = this.seciliAy.split('-').map(Number);
            return new Date(y, m - 1, 1).toLocaleDateString('tr-TR', { month: 'long', year: 'numeric' });
        },

        get maxGunlukCiro() {
            return Math.max(0, ...this.gunlukAyCiro.map(g => Number(g.ciro || 0)));
        },

        barHeight(value) {
            if (!this.maxGunlukCiro) return 2;
            return Math.max(4, Math.round((Number(value || 0) / this.maxGunlukCiro) * 100));
        },

        odemeBadgeClass(d) {
            return d === 'odendi' ? 'badge-green' : d === 'kismi' ? 'badge-yellow' : 'badge-red';
        },
        odemeBadgeText(d) {
            return d === 'odendi' ? 'Ödendi' : d === 'kismi' ? 'Kısmi' : 'Ödenmedi';
        },

        formatCurrency, formatDate, formatTip,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
