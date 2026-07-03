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
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                <p class="text-xs text-slate-500 font-semibold uppercase">Toplam Ciro</p>
                <p class="text-lg font-bold text-slate-800 mt-1" x-text="formatCurrency(trendOzet.toplam_ciro)"></p>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-100 p-3">
                <p class="text-xs text-blue-500 font-semibold uppercase">Tahsilat</p>
                <p class="text-lg font-bold text-blue-700 mt-1" x-text="formatCurrency(trendOzet.tahsilat)"></p>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-100 p-3">
                <p class="text-xs text-amber-600 font-semibold uppercase">Net Kâr</p>
                <p class="text-lg font-bold text-amber-700 mt-1" x-text="formatCurrency(trendOzet.net_kar)"></p>
            </div>
            <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-3">
                <p class="text-xs text-indigo-500 font-semibold uppercase">Satış</p>
                <p class="text-2xl font-bold text-indigo-700 mt-1" x-text="trendOzet.satis_adet"></p>
            </div>
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-3">
                <p class="text-xs text-emerald-600 font-semibold uppercase">Servis</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1" x-text="trendOzet.servis_adet"></p>
            </div>
        </div>
        <div class="relative h-72">
            <canvas id="trendChart"></canvas>
        </div>
        <div class="overflow-x-auto mt-5">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Ay</th>
                        <th>Satış</th>
                        <th>Servis</th>
                        <th>Ciro</th>
                        <th>Tahsilat</th>
                        <th>Maliyet</th>
                        <th>Net Kâr</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in trendData" :key="row.ay">
                        <tr>
                            <td class="font-medium text-slate-700" x-text="row.label"></td>
                            <td x-text="row.satis_adet"></td>
                            <td x-text="row.servis_adet"></td>
                            <td class="font-semibold" x-text="formatCurrency(row.toplam_ciro)"></td>
                            <td class="text-blue-700 font-medium" x-text="formatCurrency(row.tahsilat)"></td>
                            <td x-text="formatCurrency(row.toplam_maliyet)"></td>
                            <td class="font-semibold" :class="row.net_kar >= 0 ? 'text-emerald-700' : 'text-red-600'" x-text="formatCurrency(row.net_kar)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function raporlarApp() {
    return {
        stats: {},
        doviz: { usd_try: 0 },
        karOzet: {},
        trendData: [],
        trendYil: '<?= date('Y') ?>',
        trendChart: null,
        servisFiltre: { baslangic: '<?= date('Y-m-01') ?>', bitis: '<?= date('Y-m-d') ?>' },
        bakimAy: '<?= date('Y-m') ?>',
        karFiltre: { baslangic: '<?= date('Y-m-01') ?>', bitis: '<?= date('Y-m-d') ?>' },
        finansFiltre: { baslangic: '<?= date('Y-m-01') ?>', bitis: '<?= date('Y-m-d') ?>' },

        async init() {
            await Promise.all([this.loadStats(), this.loadDoviz()]);
            await Promise.all([this.loadKarOzet(), this.loadTrend()]);
        },

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
            } catch(e) {}
        },

        async loadTrend() {
            try {
                const p = new URLSearchParams({
                    tip: 'aylik_trend',
                    yil: this.trendYil,
                    usd_try: this.doviz.usd_try || 0,
                });
                const d = await api(`api/raporlar.php?${p}`);
                this.trendData = d.aylar || [];
                this.$nextTick(() => this.renderTrend());
            } catch(e) {}
        },

        get trendOzet() {
            return this.trendData.reduce((acc, row) => {
                acc.toplam_ciro += Number(row.toplam_ciro || 0);
                acc.tahsilat += Number(row.tahsilat || 0);
                acc.net_kar += Number(row.net_kar || 0);
                acc.satis_adet += Number(row.satis_adet || 0);
                acc.servis_adet += Number(row.servis_adet || 0);
                return acc;
            }, { toplam_ciro: 0, tahsilat: 0, net_kar: 0, satis_adet: 0, servis_adet: 0 });
        },

        renderTrend() {
            const ctx = document.getElementById('trendChart');
            if (!ctx) return;
            if (this.trendChart) this.trendChart.destroy();
            const data = this.trendData.map(row => row.toplam_ciro || 0);
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
            this.trendChart.data.labels = this.trendData.map(row => row.label);
            this.trendChart.data.datasets = [
                {
                    type: 'bar',
                    label: 'Ciro',
                    data: this.trendData.map(row => row.toplam_ciro || 0),
                    backgroundColor: '#2563eb',
                    borderRadius: 6,
                    maxBarThickness: 34,
                },
                {
                    type: 'line',
                    label: 'Tahsilat',
                    data: this.trendData.map(row => row.tahsilat || 0),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5,150,105,.12)',
                    tension: 0.35,
                    fill: false,
                    pointRadius: 3,
                },
                {
                    type: 'line',
                    label: 'Net Kâr',
                    data: this.trendData.map(row => row.net_kar || 0),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,.12)',
                    tension: 0.35,
                    fill: false,
                    pointRadius: 3,
                }
            ];
            this.trendChart.options.plugins.legend.display = true;
            this.trendChart.options.interaction = { mode: 'index', intersect: false };
            this.trendChart.options.plugins.tooltip = {
                callbacks: {
                    title: items => {
                        const row = this.trendData[items[0].dataIndex] || {};
                        return `${row.label} ${this.trendYil}`;
                    },
                    label: item => `${item.dataset.label}: ${formatCurrency(item.raw)}`,
                    afterBody: items => {
                        const row = this.trendData[items[0].dataIndex] || {};
                        return [
                            `Satış cirosu: ${formatCurrency(row.satis_ciro || 0)}`,
                            `Servis cirosu: ${formatCurrency(row.servis_ciro || 0)}`,
                            `Satış adedi: ${row.satis_adet || 0}`,
                            `Servis adedi: ${row.servis_adet || 0}`,
                        ];
                    },
                },
            };
            this.trendChart.options.scales.y.ticks.callback = value => Number(value || 0).toLocaleString('tr-TR') + ' ₺';
            this.trendChart.update();
        },

        formatCurrency,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
