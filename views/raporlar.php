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
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Tahakkuk</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="formatCurrency(karOzet.tahakkuk_ciro || karOzet.toplam_ciro || stats.buAyCiro)"></p>
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
                <h3 class="font-semibold text-slate-800">İş Hacmi / Tahakkuk / Net Kâr</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Yapılan işi satış tarihine göre, tahakkuku ise taksit ve servis vade ayına göre hesaplar. Maliyet taksitlere oranlı dağıtılır.
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

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="rounded-lg bg-purple-50 border border-purple-100 p-4">
                <p class="text-xs font-semibold text-purple-600 uppercase tracking-wide">Yapılan İş</p>
                <p class="text-2xl font-bold text-purple-700 mt-1" x-text="formatCurrency(karOzet.islem_hacmi || 0)"></p>
                <p class="text-xs text-emerald-600 mt-1" x-text="`${karOzet.satis_adet || 0} satış, ${karOzet.servis_adet || 0} servis`"></p>
            </div>
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-4">
                <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Tahakkuk Ciro</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1" x-text="formatCurrency(karOzet.tahakkuk_ciro || karOzet.toplam_ciro || 0)"></p>
                <p class="text-xs text-emerald-600 mt-1" x-text="'Beklenen: ' + formatCurrency(karOzet.beklenen_tahsilat || 0)"></p>
            </div>
            <div class="rounded-lg bg-orange-50 border border-orange-100 p-4">
                <p class="text-xs font-semibold text-orange-600 uppercase tracking-wide">Tahakkuk Maliyet</p>
                <p class="text-2xl font-bold text-orange-700 mt-1" x-text="formatCurrency(karOzet.toplam_maliyet || 0)"></p>
                <p class="text-xs text-orange-600 mt-1">Taksit vadelerine dağıtılır</p>
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
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-4 space-y-2 text-sm md:col-span-5">
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Satış Hacmi</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.satis_hacmi || 0)"></strong>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Satış Tahakkuku</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.satis_tahakkuk || karOzet.satis_ciro || 0)"></strong>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Servis Cirosu</span>
                    <strong class="text-slate-800" x-text="formatCurrency(karOzet.servis_ciro || 0)"></strong>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500">Tahsilat</span>
                    <strong class="text-blue-700" x-text="formatCurrency(karOzet.gercek_tahsilat || karOzet.tahsilat || 0)"></strong>
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
                    <a :href="`index.php?page=finans_raporu&baslangic=${finansFiltre.baslangic}&bitis=${finansFiltre.bitis}&usd_try=${doviz.usd_try || 0}`"
                       class="btn btn-sm inline-flex mr-2"
                       style="background:#0f766e;color:#fff;"
                       target="_blank">
                        <i class="fas fa-table-list text-xs"></i> Detaylı Görüntüle
                    </a>
                    <a :href="`api/raporlar.php?tip=finans&baslangic=${finansFiltre.baslangic}&bitis=${finansFiltre.bitis}&usd_try=${doviz.usd_try || 0}`"
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
    <div class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="font-semibold text-slate-800" x-text="trendMode === 'aylik' ? 'Günlük Servis & Ciro Trendi' : 'Aylık Servis & Ciro Trendi'"></h3>
            <div class="flex flex-wrap items-center gap-2">
                <select class="form-select text-xs py-1.5 w-28" x-model="trendMode" @change="loadTrend()">
                    <option value="yillik">Yıllık</option>
                    <option value="aylik">Aylık</option>
                </select>
                <select x-show="trendMode === 'yillik'" class="form-select w-24 text-xs py-1.5" x-model="trendYil" @change="loadTrend()">
                    <option value="2026">2026</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
                <input x-show="trendMode === 'aylik'" type="month" class="form-input text-xs py-1.5 w-36" x-model="trendAy" @change="loadTrend()">
            </div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
            <div class="rounded-lg bg-purple-50 border border-purple-100 p-3">
                <p class="text-xs text-purple-500 font-semibold uppercase">Yapılan İş</p>
                <p class="text-lg font-bold text-purple-700 mt-1" x-text="formatCurrency(trendOzet.islem_hacmi || 0)"></p>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                <p class="text-xs text-slate-500 font-semibold uppercase">Tahakkuk Ciro</p>
                <p class="text-lg font-bold text-slate-800 mt-1" x-text="formatCurrency(trendOzet.tahakkuk_ciro || trendOzet.toplam_ciro || 0)"></p>
            </div>
            <div class="rounded-lg bg-cyan-50 border border-cyan-100 p-3">
                <p class="text-xs text-cyan-600 font-semibold uppercase">Beklenen</p>
                <p class="text-lg font-bold text-cyan-700 mt-1" x-text="formatCurrency(trendOzet.beklenen_tahsilat || 0)"></p>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-100 p-3">
                <p class="text-xs text-blue-500 font-semibold uppercase">Tahsilat</p>
                <p class="text-lg font-bold text-blue-700 mt-1" x-text="formatCurrency(trendOzet.gercek_tahsilat || trendOzet.tahsilat || 0)"></p>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-100 p-3">
                <p class="text-xs text-amber-600 font-semibold uppercase">Net Kâr</p>
                <p class="text-lg font-bold text-amber-700 mt-1" x-text="formatCurrency(trendOzet.net_kar)"></p>
            </div>
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-3">
                <p class="text-xs text-emerald-600 font-semibold uppercase">İşlem</p>
                <p class="text-lg font-bold text-emerald-700 mt-1" x-text="`${trendOzet.satis_adet || 0} / ${trendOzet.servis_adet || 0}`"></p>
            </div>
        </div>
        <div class="relative h-64 min-h-64">
            <canvas id="trendChart" class="block w-full h-full"></canvas>
            <div x-show="trendChartError"
                 class="absolute inset-0 flex items-center justify-center text-sm text-red-500 bg-white/80 pointer-events-none"
                 x-text="trendChartError"></div>
        </div>
        <div class="overflow-x-auto mt-4">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th x-text="trendMode === 'aylik' ? 'Gün' : 'Ay'"></th>
                        <th>Satış</th>
                        <th>Servis</th>
                        <th>Yapılan İş</th>
                        <th>Tahakkuk</th>
                        <th>Beklenen</th>
                        <th>Tahsilat</th>
                        <th>Maliyet</th>
                        <th>Net Kâr</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in trendData" :key="row.key || row.ay">
                        <tr>
                            <td class="font-medium text-slate-700" x-text="row.label"></td>
                            <td x-text="row.satis_adet"></td>
                            <td x-text="row.servis_adet"></td>
                            <td class="font-semibold text-purple-700" x-text="formatCurrency(row.islem_hacmi || 0)"></td>
                            <td class="font-semibold" x-text="formatCurrency(row.tahakkuk_ciro || row.toplam_ciro || 0)"></td>
                            <td class="text-cyan-700 font-medium" x-text="formatCurrency(row.beklenen_tahsilat || 0)"></td>
                            <td class="text-blue-700 font-medium" x-text="formatCurrency(row.gercek_tahsilat || row.tahsilat || 0)"></td>
                            <td x-text="formatCurrency(row.tahakkuk_maliyet || row.toplam_maliyet || 0)"></td>
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
        trendSummary: null,
        trendMode: 'yillik',
        trendYil: '<?= date('Y') ?>',
        trendAy: '<?= date('Y-m') ?>',
        trendChart: null,
        trendChartError: '',
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
                    tip: this.trendMode === 'aylik' ? 'gunluk_trend' : 'aylik_trend',
                    usd_try: this.doviz.usd_try || 0,
                });
                if (this.trendMode === 'aylik') {
                    p.set('ay', this.trendAy);
                } else {
                    p.set('yil', this.trendYil);
                }
                const d = await api(`api/raporlar.php?${p}`);
                this.trendData = this.trendMode === 'aylik' ? (d.gunler || []) : (d.aylar || []);
                this.trendSummary = d.ozet || null;
                this.$nextTick(() => this.renderTrend());
            } catch(e) {}
        },

        trendTooltipTitle(row) {
            return this.trendMode === 'aylik'
                ? `${row.label} ${this.formatMonthLabel(this.trendAy)}`
                : `${row.label} ${this.trendYil}`;
        },

        formatMonthLabel(value) {
            if (!value) return '';
            const [year, month] = value.split('-');
            const monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
            const index = Number(month) - 1;
            return `${monthNames[index] || month} ${year}`;
        },

        get trendOzet() {
            if (this.trendSummary) {
                return this.trendSummary;
            }
            return this.trendData.reduce((acc, row) => {
                acc.islem_hacmi += Number(row.islem_hacmi || 0);
                acc.tahakkuk_ciro += Number(row.tahakkuk_ciro || row.toplam_ciro || 0);
                acc.toplam_ciro += Number(row.tahakkuk_ciro || row.toplam_ciro || 0);
                acc.beklenen_tahsilat += Number(row.beklenen_tahsilat || 0);
                acc.tahsilat += Number(row.gercek_tahsilat || row.tahsilat || 0);
                acc.gercek_tahsilat += Number(row.gercek_tahsilat || row.tahsilat || 0);
                acc.net_kar += Number(row.net_kar || 0);
                acc.satis_adet += Number(row.satis_adet || 0);
                acc.servis_adet += Number(row.servis_adet || 0);
                return acc;
            }, { islem_hacmi: 0, tahakkuk_ciro: 0, toplam_ciro: 0, beklenen_tahsilat: 0, tahsilat: 0, gercek_tahsilat: 0, net_kar: 0, satis_adet: 0, servis_adet: 0 });
        },

        renderTrend() {
            const ctx = document.getElementById('trendChart');
            if (!ctx) return;
            this.trendChartError = '';
            if (typeof Chart === 'undefined') {
                this.trendChartError = 'Grafik kutuphanesi yuklenemedi.';
                return;
            }
            if (this.trendChart) this.trendChart.destroy();
            this.trendChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.trendData.map(row => row.label),
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Tahakkuk Ciro',
                            data: this.trendData.map(row => Number(row.tahakkuk_ciro || row.toplam_ciro || 0)),
                            backgroundColor: '#2563eb',
                            borderRadius: 7,
                            maxBarThickness: 34,
                        },
                        {
                            type: 'bar',
                            label: 'Yapılan İş',
                            data: this.trendData.map(row => Number(row.islem_hacmi || 0)),
                            backgroundColor: '#7c3aed',
                            borderRadius: 7,
                            maxBarThickness: 34,
                        },
                        {
                            type: 'line',
                            label: 'Tahsilat',
                            data: this.trendData.map(row => Number(row.gercek_tahsilat || row.tahsilat || 0)),
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5,150,105,.12)',
                            tension: 0.35,
                            fill: false,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        },
                        {
                            type: 'line',
                            label: 'Net Kar',
                            data: this.trendData.map(row => Number(row.net_kar || 0)),
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245,158,11,.12)',
                            tension: 0.35,
                            fill: false,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            labels: { usePointStyle: true, boxWidth: 8, color: '#475569', font: { size: 11 } },
                        },
                        tooltip: {
                            callbacks: {
                                title: items => {
                                    const row = this.trendData[items[0].dataIndex] || {};
                                    return this.trendTooltipTitle(row);
                                },
                                label: item => `${item.dataset.label}: ${formatCurrency(item.raw)}`,
                                afterBody: items => {
                                    const row = this.trendData[items[0].dataIndex] || {};
                                    return [
                                        `Satış hacmi: ${formatCurrency(row.satis_hacmi || 0)}`,
                                        `Satış tahakkuku: ${formatCurrency(row.satis_tahakkuk || row.satis_ciro || 0)}`,
                                        `Servis cirosu: ${formatCurrency(row.servis_ciro || 0)}`,
                                        `Beklenen tahsilat: ${formatCurrency(row.beklenen_tahsilat || 0)}`,
                                        `Satis adedi: ${row.satis_adet || 0}`,
                                        `Servis adedi: ${row.servis_adet || 0}`,
                                    ];
                                },
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#eef2f7' },
                            border: { display: false },
                            ticks: {
                                color: '#64748b',
                                font: { size: 11 },
                                callback: value => Number(value || 0).toLocaleString('tr-TR') + ' TL',
                            },
                        },
                    },
                },
            });
            setTimeout(() => this.trendChart && this.trendChart.resize(), 0);
            return;
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
                        return this.trendTooltipTitle(row);
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
