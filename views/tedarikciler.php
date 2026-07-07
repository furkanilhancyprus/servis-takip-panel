<?php
$pageTitle  = 'Tedarikçiler';
$activePage = 'tedarikciler';
include __DIR__ . '/layout/header.php';
?>

<div x-data="tedarikcilerApp()" x-init="init()" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Alım</p>
            <p class="text-3xl font-bold text-slate-800 mt-1" x-text="alimlar.length"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Borç</p>
            <p class="text-2xl font-bold text-red-600 mt-1" x-text="formatCurrency(toplamBorc)"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Ödenen</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="formatCurrency(toplamOdenen)"></p>
        </div>
        <div class="stat-card flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Stok Girişi</p>
                <p class="text-2xl font-bold text-blue-600 mt-1" x-text="toplamAdet"></p>
            </div>
            <button class="btn btn-primary" @click="openForm()">
                <i class="fas fa-plus"></i> Yeni Alım
            </button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div class="relative max-w-sm w-full">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input class="form-input pl-9" x-model="search" placeholder="Tedarikçi veya fatura ara...">
            </div>
            <button class="btn btn-primary" @click="openForm()">
                <i class="fas fa-truck-ramp-box"></i> Alım Kaydet
            </button>
        </div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tedarikçi</th>
                        <th>Fatura</th>
                        <th>Kalem</th>
                        <th>Toplam</th>
                        <th>Ödenen</th>
                        <th>Kalan</th>
                        <th>Durum</th>
                        <th class="text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="9" class="text-center py-8"><span class="spinner"></span></td></tr>
                    </template>
                    <template x-if="!loading && filteredAlimlar.length === 0">
                        <tr><td colspan="9" class="text-center py-8 text-slate-400">Alım kaydı yok.</td></tr>
                    </template>
                    <template x-for="a in filteredAlimlar" :key="a.id">
                        <tr class="hover:bg-slate-50">
                            <td x-text="formatDate(a.alim_tarihi)"></td>
                            <td class="font-semibold text-slate-700" x-text="a.tedarikci_adi"></td>
                            <td class="text-slate-500" x-text="a.fatura_no || '—'"></td>
                            <td x-text="`${a.kalem_sayisi || 0} kalem / ${a.toplam_adet || 0} adet`"></td>
                            <td class="font-semibold" x-text="formatCurrency(a.toplam_tutar)"></td>
                            <td class="text-emerald-600 font-semibold" x-text="formatCurrency(a.odenen_tutar)"></td>
                            <td class="text-red-600 font-semibold" x-text="formatCurrency(Math.max(0, a.kalan_tutar || 0))"></td>
                            <td><span class="badge" :class="odemeBadgeClass(a.odeme_durumu)" x-text="odemeBadgeText(a.odeme_durumu)"></span></td>
                            <td class="text-right">
                                <button class="btn btn-sm btn-secondary btn-icon" @click="viewAlim(a)" title="Detay"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-danger btn-icon" @click="deleteAlim(a)" title="Sil"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="showForm" x-cloak class="modal-backdrop" @click.self="showForm=false">
        <div class="modal-box max-w-3xl">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Tedarikçi Alımı</h3>
                <button @click="showForm=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveAlim()" class="modal-body space-y-4" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Tedarikçi</label>
                        <input class="form-input" x-model="form.tedarikci_adi" placeholder="Toptancı adı">
                    </div>
                    <div>
                        <label class="form-label">Fatura No</label>
                        <input class="form-input" x-model="form.fatura_no">
                    </div>
                    <div>
                        <label class="form-label">Alım Tarihi</label>
                        <input type="date" class="form-input" x-model="form.alim_tarihi">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0">Alınan Mallar</label>
                        <button type="button" class="btn btn-sm btn-secondary" @click="addKalem()"><i class="fas fa-plus text-xs"></i> Kalem Ekle</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(k, i) in form.kalemler" :key="i">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <select class="form-select col-span-12 md:col-span-6" x-model="k.parca_id">
                                    <option value="">Stoktan ürün seçin...</option>
                                    <template x-for="p in stoklar" :key="p.id">
                                        <option :value="p.id" x-text="`${p.parca_adi}${p.marka ? ' ('+p.marka+')' : ''} — Stok: ${p.stok_miktari}`"></option>
                                    </template>
                                </select>
                                <input type="number" class="form-input col-span-4 md:col-span-2 text-center" min="1" x-model="k.miktar" @input="calcTotal()" placeholder="Adet">
                                <input type="number" class="form-input col-span-6 md:col-span-3" min="0" step="100" x-model="k.birim_fiyat" @input="calcTotal()" placeholder="Alış fiyatı">
                                <button type="button" class="btn btn-danger btn-icon col-span-2 md:col-span-1" @click="form.kalemler.splice(i,1); calcTotal()"><i class="fas fa-times text-xs"></i></button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                        <p class="text-xs text-blue-500 font-semibold uppercase">Toplam</p>
                        <p class="text-2xl font-bold text-blue-700 mt-1" x-text="formatCurrency(form.toplam_tutar)"></p>
                    </div>
                    <div>
                        <label class="form-label">İlk Ödeme</label>
                        <input type="number" class="form-input" min="0" step="100" x-model="form.odenen_tutar">
                    </div>
                    <div>
                        <label class="form-label">Ödeme Yöntemi</label>
                        <select class="form-select" x-model="form.odeme_yontemi">
                            <option value="nakit">Nakit</option>
                            <option value="kart">Kart</option>
                            <option value="havale">Havale / EFT</option>
                            <option value="cek">Çek</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Not</label>
                    <textarea class="form-textarea" rows="2" x-model="form.notlar"></textarea>
                </div>

                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showForm=false">İptal</button>
                    <button class="btn btn-primary" :disabled="saving"><span x-show="saving" class="spinner w-4 h-4"></span> Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showDetail" x-cloak class="modal-backdrop" @click.self="showDetail=false">
        <div class="modal-box max-w-2xl" x-show="detail">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="detail?.tedarikci_adi"></h3>
                <button @click="showDetail=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400">Toplam</p>
                        <p class="font-bold text-slate-800" x-text="formatCurrency(detail?.toplam_tutar)"></p>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-3">
                        <p class="text-xs text-emerald-500">Ödenen</p>
                        <p class="font-bold text-emerald-700" x-text="formatCurrency(detail?.odenen_tutar)"></p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3">
                        <p class="text-xs text-red-500">Kalan</p>
                        <p class="font-bold text-red-700" x-text="formatCurrency(Math.max(0, detail?.kalan_tutar || 0))"></p>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Alınan Mallar</p>
                    <div class="space-y-1.5">
                        <template x-for="k in (detail?.kalemler || [])" :key="k.id">
                            <div class="flex justify-between items-center bg-slate-50 rounded-lg px-3 py-2 text-sm">
                                <span x-text="`${k.parca_adi}${k.marka ? ' ('+k.marka+')' : ''} × ${k.miktar}`"></span>
                                <span class="font-semibold" x-text="formatCurrency(k.birim_fiyat * k.miktar)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border border-emerald-100 bg-emerald-50 rounded-xl p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <input type="number" class="form-input md:col-span-1" min="0" step="100" x-model="odemeForm.tutar" placeholder="Tutar">
                        <select class="form-select" x-model="odemeForm.odeme_yontemi">
                            <option value="nakit">Nakit</option>
                            <option value="kart">Kart</option>
                            <option value="havale">Havale / EFT</option>
                            <option value="cek">Çek</option>
                        </select>
                        <input type="date" class="form-input" x-model="odemeForm.odeme_tarihi">
                        <button class="btn btn-success" @click="saveOdeme()" :disabled="saving"><i class="fas fa-check"></i> Ödeme Ekle</button>
                    </div>
                </div>

                <div x-show="detail?.odemeler?.length">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Ödemeler</p>
                    <div class="space-y-1.5">
                        <template x-for="o in (detail?.odemeler || [])" :key="o.id">
                            <div class="flex justify-between items-center bg-white border border-slate-100 rounded-lg px-3 py-2 text-sm">
                                <div>
                                    <span class="font-semibold text-emerald-700" x-text="formatCurrency(o.tutar)"></span>
                                    <span class="text-slate-400 ml-2" x-text="formatOdemeYontemi(o.odeme_yontemi)"></span>
                                    <span class="text-slate-400 ml-2" x-text="formatDate(o.odeme_tarihi)"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger py-0.5 px-2 text-xs" @click="deleteOdeme(o.id)">Sil</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function todayStr() { return new Date().toISOString().slice(0,10); }
function tedarikcilerApp() {
    return {
        alimlar: [], stoklar: [], loading: false, saving: false, search: '',
        showForm: false, showDetail: false, detail: null,
        form: { tedarikci_adi:'', fatura_no:'', alim_tarihi: todayStr(), kalemler:[], toplam_tutar:0, odenen_tutar:0, odeme_yontemi:'nakit', notlar:'' },
        odemeForm: { tutar:0, odeme_yontemi:'nakit', odeme_tarihi: todayStr(), notlar:'' },
        async init() { await Promise.all([this.loadAlimlar(), this.loadStoklar()]); },
        get filteredAlimlar() {
            const q = this.search.trim().toLocaleLowerCase('tr-TR');
            if (!q) return this.alimlar;
            return this.alimlar.filter(a => `${a.tedarikci_adi || ''} ${a.fatura_no || ''}`.toLocaleLowerCase('tr-TR').includes(q));
        },
        get toplamBorc() { return this.alimlar.reduce((s,a)=>s+(+a.kalan_tutar||0),0); },
        get toplamOdenen() { return this.alimlar.reduce((s,a)=>s+(+a.odenen_tutar||0),0); },
        get toplamAdet() { return this.alimlar.reduce((s,a)=>s+(+a.toplam_adet||0),0); },
        async loadAlimlar() { this.loading = true; try { this.alimlar = await api('api/tedarikciler.php'); } catch(e) {} finally { this.loading = false; } },
        async loadStoklar() { try { this.stoklar = await api('api/stok.php'); } catch(e) {} },
        openForm() {
            this.form = { tedarikci_adi:'', fatura_no:'', alim_tarihi: todayStr(), kalemler:[{ parca_id:'', miktar:1, birim_fiyat:0 }], toplam_tutar:0, odenen_tutar:0, odeme_yontemi:'nakit', notlar:'' };
            this.showForm = true;
        },
        addKalem() { this.form.kalemler.push({ parca_id:'', miktar:1, birim_fiyat:0 }); },
        calcTotal() {
            this.form.toplam_tutar = +this.form.kalemler.reduce((s,k)=>s+(parseInt(k.miktar)||1)*(parseFloat(k.birim_fiyat)||0),0).toFixed(2);
            if ((parseFloat(this.form.odenen_tutar)||0) > this.form.toplam_tutar) this.form.odenen_tutar = this.form.toplam_tutar;
        },
        async saveAlim() {
            this.calcTotal();
            if (!this.form.tedarikci_adi.trim()) { showToast('Tedarikçi adı girin.', 'error'); return; }
            if (!this.form.kalemler.some(k => k.parca_id)) { showToast('En az bir stok ürünü seçin.', 'error'); return; }
            this.saving = true;
            try {
                await api('api/tedarikciler.php', { method:'POST', body:this.form });
                showToast('Alım kaydedildi, stoklar güncellendi.', 'success');
                this.showForm = false;
                await Promise.all([this.loadAlimlar(), this.loadStoklar()]);
            } catch(e) {} finally { this.saving = false; }
        },
        async viewAlim(a) {
            this.detail = await api(`api/tedarikciler.php?id=${a.id}`);
            this.odemeForm = { tutar: Math.max(0, +(this.detail.kalan_tutar || 0)), odeme_yontemi:'nakit', odeme_tarihi: todayStr(), notlar:'' };
            this.showDetail = true;
        },
        async saveOdeme() {
            if (!this.detail) return;
            if (parseFloat(this.odemeForm.tutar) < 0) { showToast('Geçerli ödeme tutarı girin.', 'error'); return; }
            this.saving = true;
            try {
                await api(`api/tedarikciler.php?id=${this.detail.id}&odeme=1`, { method:'POST', body:this.odemeForm });
                showToast('Ödeme kaydedildi.', 'success');
                await this.viewAlim(this.detail);
                await this.loadAlimlar();
            } catch(e) {} finally { this.saving = false; }
        },
        async deleteOdeme(id) {
            if (!confirm('Bu ödeme silinsin mi?')) return;
            await api(`api/tedarikciler.php?id=${id}&odeme=1`, { method:'DELETE' });
            showToast('Ödeme silindi.', 'success');
            await this.viewAlim(this.detail);
            await this.loadAlimlar();
        },
        async deleteAlim(a) {
            if (!confirm('Bu alım kaydı silinsin mi? Stok miktarları geri alınacak.')) return;
            await api(`api/tedarikciler.php?id=${a.id}`, { method:'DELETE' });
            showToast('Alım silindi, stoklar güncellendi.', 'success');
            await Promise.all([this.loadAlimlar(), this.loadStoklar()]);
        },
        odemeBadgeClass(d) { return d === 'odendi' ? 'badge-green' : d === 'kismi' ? 'badge-yellow' : 'badge-red'; },
        odemeBadgeText(d) { return d === 'odendi' ? 'Ödendi' : d === 'kismi' ? 'Kısmi' : 'Ödenmedi'; },
        formatOdemeYontemi(y) { return ({ nakit:'Nakit', kart:'Kart', havale:'Havale / EFT', cek:'Çek' })[y] || y || '—'; },
    };
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
