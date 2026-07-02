<?php
$pageTitle  = 'Raporlar';
$activePage = 'raporlar';
include __DIR__ . '/layout/header.php';
?>

<div x-data="raporlarApp()" x-init="init()" class="space-y-6">

    <!-- Stats overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Müşteri</p>
            <p class="text-3xl font-bold text-slate-800 mt-1" x-text="stats.toplamMusteri || '—'"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Servis</p>
            <p class="text-3xl font-bold text-blue-600 mt-1" x-text="stats.toplamServis || '—'"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Ciro</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="formatCurrency(stats.buAyCiro)"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Stok Değeri</p>
            <p class="text-2xl font-bold text-purple-600 mt-1" x-text="formatCurrency(stats.stokDegeri)"></p>
        </div>
    </div>

    <!-- Ciro / Maliyet / Kar Ozeti -->
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
            <div>
                <h3 class="font-semibold text-slate-800">Ciro / Maliyet / Net Kâr</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Satış ve servis cirosunu, stok maliyetlerini ve net kârı hesaplar.
                    <span x-show="doviz.usd_try" x-text="` USD kuru: ${doviz.usd_try.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 4 })} ₺`"></span>
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="form-label">Başlangıç</label>
                    <input type="date" class="form-input" x-model="karFiltre.baslangic">
                </div>
                <div>
                    <label class="form-label">Bitiş</label>
                    <input type="date" class="form-input" x-model="karFiltre.bitis">
                </div>
                <button type="button" class="btn btn-primary" @click="loadKarOzet()">
                    <i class="fas fa-calculator"></i> Hesapla
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-4">
                <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Toplam Ciro</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1" x-text="formatCurrency(karOzet.toplam_ciro || 0)"></p>
                <p class="text-xs text-emerald-600 mt-1" x-text="`${karOzet.satis_adet || 0} satış, ${karOzet.servis_adet || 0} servis`"></p>
            </div>
            <div class="rounded-lg bg-orange-50 border border-orange-100 p-4">
                <p class="text-xs font-semibold text-orange-600 uppercase tracking-wide">Toplam Maliyet</p>
                <p class="text-2xl font-bold text-orange-700 mt-1" x-text="formatCurrency(karOzet.toplam_maliyet || 0)"></p>
                <p class="text-xs text-orange-600 mt-1">Stok maliyetlerinden hesaplanır</p>
            </div>
            <div class="rounded-lg border p-4"
                 :class="(karOzet.net_kar || 0) >= 0 ? 'bg-blue-50 border-blue-100' : 'bg-red-50 border-red-100'">
                <p class="text-xs font-semibold uppercase tracking-wide"
                   :class="(karOzet.net_kar || 0) >= 0 ? 'text-blue-600' : 'text-red-600'">Net Kâr</p>
                <p class="text-2xl font-bold mt-1"
                   :class="(karOzet.net_kar || 0) >= 0 ? 'text-blue-700' : 'text-red-700'"
                   x-text="formatCurrency(karOzet.net_kar || 0)"></p>
                <p class="text-xs mt-1"
                   :class="(karOzet.net_kar || 0) >= 0 ? 'text-blue-600' : 'text-red-600'"
                   x-text="`Kâr oranı: %${karOzet.kar_orani || 0}`"></p>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-4 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Satış Cirosu</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.satis_ciro || 0)"></strong>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Servis Cirosu</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.servis_ciro || 0)"></strong>
                </div>
                <div class="flex justify-between gap-3 border-t border-slate-200 pt-2">
                    <span class="text-slate-500">Satış Maliyeti</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.satis_maliyet || 0)"></strong>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Servis Maliyeti</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.servis_maliyet || 0)"></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Rapor Kartları -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Müşteri Raporu -->
        <div class="card p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-users text-blue-600 text-lg"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 mb-1">Müşteri Raporu</h3>
                    <p class="text-sm text-slate-500 mb-4">Tüm müşterileri, bakım durumlarını ve servis sayılarını içerir.</p>
                    <a :href="`api/raporlar.php?tip=musteri`"
                       class="btn btn-primary btn-sm inline-flex"
                       target="_blank">
                        <i class="fas fa-file-excel text-xs"></i> Excel İndir
                    </a>
                </div>
            </div>
        </div>

        <!-- Servis Raporu -->
        <div class="card p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-wrench text-green-600 text-lg"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 mb-1">Servis Raporu</h3>
                    <p class="text-sm text-slate-500 mb-3">Belirtilen tarih aralığındaki tüm servis kayıtları.</p>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div>
                            <label class="form-label">Başlangıç</label>
                            <input type="date" class="form-input" x-model="servisFiltre.baslangic">
                        </div>
                        <div>
                            <label class="form-label">Bitiş</label>
                            <input type="date" class="form-input" x-model="servisFiltre.bitis">
                        </div>
                    </div>
                    <a :href="`api/raporlar.php?tip=servis&baslangic=${servisFiltre.baslangic}&bitis=${servisFiltre.bitis}`"
                       class="btn btn-success btn-sm inline-flex"
                       target="_blank">
                        <i class="fas fa-file-excel text-xs"></i> Excel İndir
                    </a>
                </div>
            </div>
        </div>

        <!-- Stok Raporu -->
        <div class="card p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-boxes-stacked text-orange-500 text-lg"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 mb-1">Stok Raporu</h3>
                    <p class="text-sm text-slate-500 mb-4">Tüm parçaları, stok miktarlarını ve kritik seviyeleri içerir.</p>
                    <a :href="`api/raporlar.php?tip=stok`"
                       class="btn btn-sm inline-flex"
                       style="background:#f97316;color:#fff;"
                       target="_blank">
                        <i class="fas fa-file-excel text-xs"></i> Excel İndir
                    </a>
                </div>
            </div>
        </div>

        <!-- Finans Raporu -->
        <div class="card p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-chart-line text-purple-600 text-lg"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 mb-1">Finans Raporu</h3>
                    <p class="text-sm text-slate-500 mb-3">Ciro ve servis tutarlarını tarihe göre filtreler.</p>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div>
                            <label class="form-label">Başlangıç</label>
                            <input type="date" class="form-input" x-model="finansFiltre.baslangic">
                        </div>
                        <div>
                            <label class="form-label">Bitiş</label>
                            <input type="date" class="form-input" x-model="finansFiltre.bitis">
                        </div>
                    </div>
                    <a :href="`api/raporlar.php?tip=finans&baslangic=${finansFiltre.baslangic}&bitis=${finansFiltre.bitis}`"
                       class="btn btn-sm inline-flex"
                       style="background:#7c3aed;color:#fff;"
                       target="_blank">
                        <i class="fas fa-file-excel text-xs"></i> Excel İndir
                    </a>
                </div>
            </div>
        </div>
        <!-- Planlanan Bakım Raporu -->
        <div class="card p-6 md:col-span-2" style="border-left: 4px solid #0891b2;">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#ecfeff;">
                    <i class="fas fa-calendar-check text-lg" style="color:#0891b2;"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-slate-800 mb-1">Planlanan Bakım Raporu</h3>
                    <p class="text-sm text-slate-500 mb-4">Seçtiğiniz ayda bakımı planlanan müşterileri, son bakım tarihini ve değişen parçaları listeler.</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="form-label">Ay Seç</label>
                            <input type="month" class="form-input" x-model="bakimAy" style="min-width:160px;">
                        </div>
                        <a :href="`api/raporlar.php?tip=planlanan_bakim&ay=${bakimAy}`"
                           class="btn btn-sm inline-flex items-center gap-1.5"
                           style="background:#0891b2;color:#fff;"
                           target="_blank">
                            <i class="fas fa-file-excel text-xs"></i> Excel İndir
                        </a>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Excel'de şu bilgiler yer alır: müşteri adı, telefon, adres, planlanan bakım tarihi, son bakım tarihi, bakım periyodu, son serviste yapılan işlemler ve kullanılan parçalar.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- Servis Trend Grafiği -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-slate-800">Aylık Servis & Ciro Trendi</h3>
            <select class="form-select w-24 text-xs py-1.5" x-model="trendYil" @change="loadTrend()">
                <option value="2026">2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
            </select>
        </div>
        <div class="relative h-64">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</div>

<script>
function raporlarApp() {
    return {
        stats: {},
        doviz: { usd_try: 0 },
        karOzet: {},
        trendYil: '<?= date('Y') ?>',
        trendChart: null,
        servisFiltre: { baslangic: '<?= date('Y-m-01') ?>', bitis: '<?= date('Y-m-d') ?>' },
        bakimAy: '<?= date('Y-m') ?>',
        karFiltre: { baslangic: '<?= date('Y-m-01') ?>', bitis: '<?= date('Y-m-d') ?>' },
        finansFiltre: { baslangic: '<?= date('Y-m-01') ?>', bitis: '<?= date('Y-m-d') ?>' },

        async init() { await Promise.all([this.loadStats(), this.loadDoviz()]); await this.loadKarOzet(); },

        async loadDoviz() {
            try { this.doviz = await api('api/doviz.php'); } catch(e) { this.doviz = { usd_try: 0 }; }
        },

        async loadKarOzet() {
            try {
                const p = new URLSearchParams({
                    tip: 'kar_ozet',
                    baslangic: this.karFiltre.baslangic,
                    bitis: this.karFiltre.bitis,
                    usd_try: this.doviz.usd_try || 0,
                });
                this.karOzet = await api(`api/raporlar.php?${p}`);
            } catch(e) {}
        },

        async loadStats() {
            try {
                const d = await api('api/dashboard.php');
                this.stats = {
                    toplamMusteri: d.toplamMusteri,
                    toplamServis: (d.sonServisler || []).length,
                    buAyCiro: d.buAyCiro,
                    stokDegeri: d.stokDegeri,
                };
                this.$nextTick(() => this.renderTrend(d.aylikCiro || []));
            } catch(e) {}
        },

        async loadTrend() {
            try {
                const d = await api(`api/dashboard.php?yil=${this.trendYil}`);
                if (this.trendChart) {
                    this.trendChart.data.datasets[0].data = d.aylikCiro || [];
                    this.trendChart.update();
                }
            } catch(e) {}
        },

        renderTrend(data) {
            const ctx = document.getElementById('trendChart');
            if (!ctx) return;
            if (this.trendChart) this.trendChart.destroy();
            this.trendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'],
                    datasets: [{
                        label: 'Ciro (₺)',
                        data,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.08)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#2563eb',
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, callback: v => '₺' + v.toLocaleString('tr-TR') } }
                    }
                }
            });
        },

        formatCurrency,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
