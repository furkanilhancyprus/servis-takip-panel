<?php
$pageTitle    = 'Tahsilatlar';
$activePage   = 'tahsilatlar';
require_once ROOT . '/views/layout/header.php';
?>

<div x-data="tahsilatApp()" x-init="init()" x-cloak>

    <!-- Ozet Kartlar -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bugün Tahsilat</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1" x-text="fmt(ozet.bugun_tahsilat)"></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bu Ay Tahsilat</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1" x-text="fmt(ozet.buay_tahsilat)"></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-check text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Toplam Bekleyen</p>
                    <p class="text-2xl font-bold text-red-600 mt-1" x-text="fmt(ozet.toplam_bekleyen)"></p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-red-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekmeler -->
    <div class="card mb-6">
        <div class="flex border-b border-slate-100 px-1 pt-1">
            <button @click="aktifSekme='servis'"
                :class="aktifSekme==='servis' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors flex items-center gap-2">
                <i class="fas fa-wrench"></i> Servis Ödemeleri
                <span x-show="servisOdemeleri.length > 0" class="bg-orange-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" x-text="servisOdemeleri.length"></span>
            </button>
            <button @click="aktifSekme='satis'"
                :class="aktifSekme==='satis' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors flex items-center gap-2">
                <i class="fas fa-cart-shopping"></i> Satış Ödemeleri
                <span x-show="satisOdemeleri.length > 0" class="bg-orange-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" x-text="satisOdemeleri.length"></span>
            </button>
        </div>

        <!-- SERViS OEDEMELERi -->
        <div x-show="aktifSekme==='servis'" class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Müşteri</th><th>Tür</th><th>Tarih</th><th>Toplam</th><th>Ödenen</th><th>Kalan</th><th>Durum</th><th>İşlem</th></tr></thead>
                <tbody>
                    <template x-if="servisOdemeleri.length === 0">
                        <tr><td colspan="8" class="text-center py-10 text-slate-400"><i class="fas fa-check-circle text-2xl mb-2 block text-green-400"></i>Bekleyen servis ödemesi yok!</td></tr>
                    </template>
                    <template x-for="s in servisOdemeleri" :key="'sv-'+s.id">
                        <tr>
                            <td><div class="font-medium text-slate-800" x-text="s.musteri_adi"></div><div class="text-xs text-slate-400" x-text="s.telefon ?? ''"></div></td>
                            <td>
                                <span x-show="s.alt_tip==='ariza'" class="badge badge-red">Arıza</span>
                                <span x-show="s.alt_tip==='periyodik_bakim'" class="badge badge-blue">Bakım</span>
                            </td>
                            <td class="text-sm text-slate-600" x-text="formatTarih(s.tarih)"></td>
                            <td class="text-sm font-medium" x-text="fmt(s.toplam_tutar)"></td>
                            <td class="text-sm text-green-600" x-text="fmt(s.odenen_tutar)"></td>
                            <td class="text-sm font-semibold text-red-600" x-text="fmt(s.kalan)"></td>
                            <td>
                                <span x-show="s.odeme_durumu==='odenmedi'" class="badge badge-red">Ödenmedi</span>
                                <span x-show="s.odeme_durumu==='kismi'" class="badge badge-yellow">Kısmi</span>
                            </td>
                            <td><button @click="acTahsilat(s)" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tahsilat Al</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- SATIS OEDEMELERi -->
        <div x-show="aktifSekme==='satis'" class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Müşteri</th><th>Tarih</th><th>Toplam</th><th>Ödenen</th><th>Kalan</th><th>Durum</th><th>İşlem</th></tr></thead>
                <tbody>
                    <template x-if="satisOdemeleri.length === 0">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400"><i class="fas fa-check-circle text-2xl mb-2 block text-green-400"></i>Bekleyen satış ödemesi yok!</td></tr>
                    </template>
                    <template x-for="s in satisOdemeleri" :key="'st-'+s.id">
                        <tr class="cursor-pointer hover:bg-blue-50/50 transition-colors" @click="openSatisDetay(s)" title="Taksit detaylarını görüntüle">
                            <td><div class="font-medium text-slate-800" x-text="s.musteri_adi"></div><div class="text-xs text-slate-400" x-text="s.telefon ?? ''"></div></td>
                            <td class="text-sm text-slate-600" x-text="formatTarih(s.tarih)"></td>
                            <td class="text-sm font-medium" x-text="fmt(s.toplam_tutar)"></td>
                            <td class="text-sm text-green-600" x-text="fmt(s.odenen_tutar)"></td>
                            <td class="text-sm font-semibold text-red-600" x-text="fmt(s.kalan)"></td>
                            <td>
                                <span x-show="s.odeme_durumu==='odenmedi'" class="badge badge-red">Ödenmedi</span>
                                <span x-show="s.odeme_durumu==='kismi'" class="badge badge-yellow">Kısmi</span>
                            </td>
                            <td><button @click.stop="acTahsilat(s)" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tahsilat Al</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

    </div>

    <!-- MODAL: Tahsilat Al -->
    <div x-show="tahsilatModal" class="modal-backdrop" @click.self="tahsilatModal=false">
        <div class="modal-box max-w-md">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Tahsilat Al</h3>
                <button @click="tahsilatModal=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body space-y-4">
                <div class="bg-slate-50 rounded-lg p-3 text-sm">
                    <div class="flex justify-between mb-1"><span class="text-slate-500">Müşteri</span><span class="font-medium" x-text="tahsilatForm.musteri_adi"></span></div>
                    <div class="flex justify-between mb-1"><span class="text-slate-500">Toplam</span><span class="font-medium" x-text="fmt(tahsilatForm.toplam_tutar)"></span></div>
                    <div class="flex justify-between mb-1"><span class="text-slate-500">Ödenen</span><span class="text-green-600 font-medium" x-text="fmt(tahsilatForm.odenen_tutar)"></span></div>
                    <div class="flex justify-between font-semibold border-t pt-1 mt-1"><span class="text-red-600">Kalan</span><span class="text-red-600" x-text="fmt(tahsilatForm.kalan)"></span></div>
                </div>
                <div>
                    <label class="form-label">Tahsilat Tutarı *</label>
                    <input x-model="tahsilatForm.tutar" type="number" step="0.01" min="0.01" :max="tahsilatForm.kalan" class="form-input" placeholder="0.00">
                    <button @click="tahsilatForm.tutar=tahsilatForm.kalan" class="text-xs text-blue-600 hover:underline mt-1 cursor-pointer">Tamamını al (<span x-text="fmt(tahsilatForm.kalan)"></span>)</button>
                </div>
                <div>
                    <label class="form-label">Ödeme Yöntemi</label>
                    <select x-model="tahsilatForm.odeme_yontemi" class="form-select">
                        <option value="nakit">Nakit</option><option value="kart">Kredi/Banka Kartı</option>
                        <option value="havale">Havale / EFT</option><option value="cek">Çek</option>
                    </select>
                </div>
                <div><label class="form-label">Tarih</label><input x-model="tahsilatForm.tarih" type="date" class="form-input"></div>
                <div><label class="form-label">Notlar</label><textarea x-model="tahsilatForm.notlar" rows="2" class="form-textarea" placeholder="İsteğe bağlı..."></textarea></div>
            </div>
            <div class="modal-footer">
                <button @click="tahsilatModal=false" class="btn btn-secondary">İptal</button>
                <button @click="saveTahsilat()" :disabled="saving" class="btn btn-primary">
                    <span x-show="saving"><i class="fas fa-spinner fa-spin"></i></span>
                    <span x-show="!saving"><i class="fas fa-check"></i> Kaydet</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: Satış Taksit Detayı -->
    <div x-show="satisDetayModal" x-cloak class="modal-backdrop" @click.self="satisDetayModal=false">
        <div class="modal-box max-w-2xl">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Satış Ödeme Detayı</h3>
                <button @click="satisDetayModal=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body space-y-4">
                <template x-if="satisDetayLoading">
                    <div class="py-10 text-center text-slate-400">
                        <div class="spinner mx-auto mb-2"></div>Detay yükleniyor...
                    </div>
                </template>
                <template x-if="!satisDetayLoading && satisDetay">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="bg-slate-50 rounded-xl p-3">
                                <p class="text-xs text-slate-400 font-semibold uppercase">Müşteri</p>
                                <p class="font-semibold text-slate-800 mt-1" x-text="satisDetay.musteri_adi"></p>
                                <p class="text-xs text-slate-400" x-text="satisDetay.telefon || ''"></p>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-3">
                                <p class="text-xs text-slate-400 font-semibold uppercase">Satış Tarihi</p>
                                <p class="font-semibold text-slate-800 mt-1" x-text="formatTarih(satisDetay.satis_tarihi)"></p>
                                <p class="text-xs text-slate-400" x-text="satisDetay.odeme_turu === 'taksitli' ? `${satisDetay.taksit_sayisi} taksit` : 'Peşin ödeme'"></p>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-3">
                                <p class="text-xs text-slate-400 font-semibold uppercase">Kalan</p>
                                <p class="font-semibold text-red-600 mt-1" x-text="fmt(Math.max(0,(+satisDetay.toplam_tutar||0)-(+satisDetay.odenen_tutar||0)))"></p>
                                <p class="text-xs text-slate-400">Toplam: <span x-text="fmt(satisDetay.toplam_tutar)"></span></p>
                            </div>
                        </div>

                        <div class="border border-slate-100 rounded-xl overflow-hidden" x-show="(satisDetay.kalemler || []).length || satisDetay.cihaz_adi">
                            <div class="px-4 py-3 bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">Satılan ürün / kalemler</div>
                            <div class="divide-y divide-slate-100">
                                <div x-show="satisDetay.cihaz_adi" class="px-4 py-3 flex justify-between gap-3 text-sm">
                                    <span>
                                        <span class="font-medium text-slate-800" x-text="satisDetay.cihaz_adi"></span>
                                        <span class="text-slate-400" x-text="satisDetay.cihaz_marka ? ` · ${satisDetay.cihaz_marka}` : ''"></span>
                                    </span>
                                    <span class="font-semibold text-slate-700" x-text="fmt(satisDetay.toplam_tutar)"></span>
                                </div>
                                <template x-for="k in (satisDetay.kalemler || [])" :key="k.id || k.urun_adi">
                                    <div class="px-4 py-3 flex justify-between gap-3 text-sm">
                                        <span class="font-medium text-slate-800" x-text="`${k.urun_adi} x ${k.miktar}`"></span>
                                        <span class="font-semibold text-slate-700" x-text="fmt((+k.miktar || 1) * (+k.birim_fiyat || 0))"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="border border-slate-100 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">Taksit planı</div>
                            <div class="divide-y divide-slate-100">
                                <template x-if="!(satisDetay.taksitler || []).length">
                                    <div class="px-4 py-6 text-center text-sm text-slate-400">Bu satış için taksit planı yok.</div>
                                </template>
                                <template x-for="tk in (satisDetay.taksitler || [])" :key="tk.id">
                                    <div class="px-4 py-3 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-slate-800" x-text="tk.taksit_no === 0 ? 'Peşinat' : `${tk.taksit_no}. Taksit`"></p>
                                            <p class="text-xs text-slate-400">
                                                Vade: <span x-text="formatTarih(tk.vade_tarihi)"></span>
                                                <span x-show="Number(tk.odendi) === 1"> · Ödeme: <span x-text="formatTarih(tk.odeme_tarihi)"></span></span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-slate-800" x-text="fmt(tk.tutar)"></p>
                                            <span class="badge" :class="Number(tk.odendi) === 1 ? 'badge-green' : 'badge-yellow'" x-text="Number(tk.odendi) === 1 ? 'Ödendi' : 'Bekliyor'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="modal-footer">
                <button @click="satisDetayModal=false" class="btn btn-secondary">Kapat</button>
                <button x-show="satisDetay && Math.max(0,(+satisDetay.toplam_tutar||0)-(+satisDetay.odenen_tutar||0)) > 0"
                        @click="satisDetayModal=false; acTahsilat({ id:satisDetay.id, tip:'satis', musteri_id:satisDetay.musteri_id, musteri_adi:satisDetay.musteri_adi, toplam_tutar:satisDetay.toplam_tutar, odenen_tutar:satisDetay.odenen_tutar, kalan:Math.max(0,(+satisDetay.toplam_tutar||0)-(+satisDetay.odenen_tutar||0)) })"
                        class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tahsilat Al
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function tahsilatApp() {
    return {
        aktifSekme: 'servis',
        ozet: {}, servisOdemeleri: [], satisOdemeleri: [],
        tahsilatModal: false, saving: false,
        satisDetayModal: false, satisDetayLoading: false, satisDetay: null,
        tahsilatForm: { musteri_id:0, kaynak_tip:'', kaynak_id:0, musteri_adi:'', toplam_tutar:0, odenen_tutar:0, kalan:0, tutar:0, odeme_yontemi:'nakit', tarih:todayStr(), notlar:'' },

        csrfHeaders() {
            return {
                'Content-Type':'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };
        },

        async init() { await Promise.all([this.loadOzet(), this.loadOdenmemisler()]); },

        async loadOzet() {
            const r = await fetch('api/tahsilatlar.php?ozet=1');
            const d = await r.json(); this.ozet = d.data ?? {};
        },
        async loadOdenmemisler() {
            const r = await fetch('api/tahsilatlar.php?odenmemisler=1');
            const d = await r.json(); const all = d.data ?? [];
            this.servisOdemeleri = all.filter(x => x.tip === 'servis');
            this.satisOdemeleri  = all.filter(x => x.tip === 'satis');
        },

        acTahsilat(item) {
            this.tahsilatForm = { musteri_id:item.musteri_id, kaynak_tip:item.tip, kaynak_id:item.id, musteri_adi:item.musteri_adi, toplam_tutar:parseFloat(item.toplam_tutar), odenen_tutar:parseFloat(item.odenen_tutar), kalan:parseFloat(item.kalan), tutar:parseFloat(item.kalan), odeme_yontemi:'nakit', tarih:todayStr(), notlar:'' };
            this.tahsilatModal = true;
        },

        async openSatisDetay(item) {
            this.satisDetay = null;
            this.satisDetayModal = true;
            this.satisDetayLoading = true;
            try {
                const r = await fetch(`api/satislar.php?id=${item.id}`);
                const d = await r.json();
                if (!d.success) throw new Error(d.message || 'Satış detayı alınamadı.');
                this.satisDetay = d.data;
            } catch (e) {
                showToast(e.message || 'Satış detayı alınamadı.', 'error');
                this.satisDetayModal = false;
            } finally {
                this.satisDetayLoading = false;
            }
        },

        async saveTahsilat() {
            if (!this.tahsilatForm.tutar || parseFloat(this.tahsilatForm.tutar) <= 0) { showToast('Geçerli bir tutar girin.', 'error'); return; }
            this.saving = true;
            try {
                const r = await fetch('api/tahsilatlar.php', { method:'POST', headers:this.csrfHeaders(), body:JSON.stringify({ musteri_id:this.tahsilatForm.musteri_id, kaynak_tip:this.tahsilatForm.kaynak_tip, kaynak_id:this.tahsilatForm.kaynak_id, tutar:this.tahsilatForm.tutar, odeme_yontemi:this.tahsilatForm.odeme_yontemi, tahsilat_tarihi:this.tahsilatForm.tarih, notlar:this.tahsilatForm.notlar }) });
                const d = await r.json();
                if (d.success) { showToast(d.message ?? 'Tahsilat kaydedildi.', 'success'); this.tahsilatModal=false; await this.init(); }
                else showToast(d.message ?? 'Hata!', 'error');
            } finally { this.saving=false; }
        },

        fmt(v) { return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',minimumFractionDigits:2}).format(parseFloat(v)||0); },
        formatTarih(d) { if(!d) return '—'; const [y,m,g]=d.split('-'); return g+'.'+m+'.'+y; },
    };
}
function todayStr() { return new Date().toISOString().slice(0,10); }
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
