<?php
$pageTitle  = 'Tedarikçiler';
$activePage = 'tedarikciler';
include __DIR__ . '/layout/header.php';
?>

<div x-data="tedarikcilerApp()" x-init="init()" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tedarikçi</p>
            <p class="text-3xl font-bold text-slate-800 mt-1" x-text="suppliers.length"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Borç</p>
            <p class="text-2xl font-bold text-red-600 mt-1" x-text="formatCurrency(toplamBorcTl)"></p>
            <p class="text-xs text-slate-400 mt-1" x-show="toplamBorcUsd > 0" x-text="formatUsdAmount(toplamBorcUsd) + ' dahil'"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Ödenen</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="formatCurrency(toplamOdenenTl)"></p>
            <p class="text-xs text-slate-400 mt-1" x-show="doviz.usd_try" x-text="'USD kuru: ' + Number(doviz.usd_try).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + ' TL'"></p>
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
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fas fa-address-book text-blue-500"></i> Tedarikçi Rehberi
            </h3>
            <button class="btn btn-secondary" @click="openSupplierForm()">
                <i class="fas fa-user-plus"></i> Tedarikçi Ekle
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table min-w-[860px]">
                <thead>
                    <tr>
                        <th>Tedarikçi</th>
                        <th>Yetkili</th>
                        <th>Telefon</th>
                        <th>Toplam Alım</th>
                        <th>Kalan Borç</th>
                        <th>Not</th>
                        <th class="text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="!loading && filteredSuppliers.length === 0">
                        <tr><td colspan="7" class="text-center py-8 text-slate-400">Tedarikçi kaydı yok.</td></tr>
                    </template>
                    <template x-for="s in filteredSuppliers" :key="s.id">
                        <tr>
                            <td class="font-semibold text-slate-800" x-text="s.ad"></td>
                            <td class="text-slate-600" x-text="s.yetkili || '—'"></td>
                            <td class="text-slate-600" x-text="s.telefon || '—'"></td>
                            <td class="font-semibold text-slate-700" x-text="formatCurrency(supplierTotalTl(s))"></td>
                            <td>
                                <p class="font-semibold" :class="supplierDebtTl(s) > 0 ? 'text-red-600' : 'text-emerald-600'" x-text="formatCurrency(supplierDebtTl(s))"></p>
                                <p class="text-xs text-slate-400" x-show="Number(s.kalan_borc_usd || 0) > 0" x-text="formatUsdAmount(s.kalan_borc_usd)"></p>
                            </td>
                            <td class="text-slate-500 text-sm" x-text="s.notlar || '—'"></td>
                            <td class="text-right">
                                <button class="btn btn-sm btn-secondary btn-icon" @click="editSupplier(s)" title="Düzenle"><i class="fas fa-pen"></i></button>
                                <button class="btn btn-sm btn-danger btn-icon" @click="deleteSupplier(s)" title="Sil"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
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
            <table class="data-table min-w-[1080px]">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tedarikçi</th>
                        <th>Fatura</th>
                        <th>Kalem</th>
                        <th>Para</th>
                        <th>Toplam</th>
                        <th>Ödenen</th>
                        <th>Kalan</th>
                        <th>Güncel TL Borç</th>
                        <th>Durum</th>
                        <th class="text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="11" class="text-center py-8"><span class="spinner"></span></td></tr>
                    </template>
                    <template x-if="!loading && filteredAlimlar.length === 0">
                        <tr><td colspan="11" class="text-center py-8 text-slate-400">Alım kaydı yok.</td></tr>
                    </template>
                    <template x-for="a in filteredAlimlar" :key="a.id">
                        <tr class="hover:bg-slate-50">
                            <td x-text="formatDate(a.alim_tarihi)"></td>
                            <td class="font-semibold text-slate-700" x-text="a.tedarikci_adi"></td>
                            <td class="text-slate-500" x-text="a.fatura_no || '—'"></td>
                            <td>
                                <p class="font-semibold text-slate-700" x-text="`${a.kalem_sayisi || 0} kalem`"></p>
                                <p class="text-xs text-slate-400" x-text="`${a.toplam_adet || 0} adet`"></p>
                            </td>
                            <td><span class="badge" :class="currencyOf(a) === 'USD' ? 'badge-blue' : 'badge-gray'" x-text="currencyOf(a)"></span></td>
                            <td class="font-semibold" x-text="formatMoney(a.toplam_tutar, currencyOf(a))"></td>
                            <td class="text-emerald-600 font-semibold" x-text="formatMoney(a.odenen_tutar, currencyOf(a))"></td>
                            <td class="text-red-600 font-semibold" x-text="formatMoney(Math.max(0, a.kalan_tutar || 0), currencyOf(a))"></td>
                            <td>
                                <p class="font-semibold text-slate-800" x-text="formatCurrency(rowDebtTl(a))"></p>
                                <p class="text-xs text-slate-400" x-show="currencyOf(a) === 'USD'" x-text="'Kur: ' + rowUsdRate(a).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 4 })"></p>
                            </td>
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
                        <input class="form-input" x-model="form.tedarikci_adi" list="tedarikciList" placeholder="Toptancı adı">
                        <datalist id="tedarikciList">
                            <template x-for="s in suppliers" :key="s.id">
                                <option :value="s.ad"></option>
                            </template>
                        </datalist>
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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Para Birimi</label>
                        <select class="form-select" x-model="form.para_birimi" @change="syncKur()">
                            <option value="TRY">TL</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">USD Kuru</label>
                        <input type="number" class="form-input" min="0" step="0.0001" x-model="form.usd_kur" :disabled="form.para_birimi !== 'USD' && !doviz.usd_try">
                        <p class="text-xs text-slate-400 mt-1" x-show="form.para_birimi === 'USD'">Güncel TL borç bu kurla gösterilir.</p>
                    </div>
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                        <p class="text-xs text-slate-500 font-semibold uppercase">TL Karşılığı</p>
                        <p class="text-xl font-bold text-slate-800 mt-1" x-text="formatCurrency(formTotalTl())"></p>
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
                                <input type="number" class="form-input col-span-6 md:col-span-3" min="0" :step="form.para_birimi === 'USD' ? '0.01' : '100'" x-model="k.birim_fiyat" @input="calcTotal()" :placeholder="form.para_birimi === 'USD' ? 'Alış fiyatı ($)' : 'Alış fiyatı (TL)'">
                                <button type="button" class="btn btn-danger btn-icon col-span-2 md:col-span-1" @click="form.kalemler.splice(i,1); calcTotal()"><i class="fas fa-times text-xs"></i></button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                        <p class="text-xs text-blue-500 font-semibold uppercase">Toplam</p>
                        <p class="text-2xl font-bold text-blue-700 mt-1" x-text="formatMoney(form.toplam_tutar, form.para_birimi)"></p>
                    </div>
                    <div>
                        <label class="form-label">İlk Ödeme</label>
                        <input type="number" class="form-input" min="0" :step="form.para_birimi === 'USD' ? '0.01' : '100'" x-model="form.odenen_tutar">
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

    <div x-show="showSupplierForm" x-cloak class="modal-backdrop" @click.self="showSupplierForm=false">
        <div class="modal-box max-w-lg">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="supplierEditId ? 'Tedarikçi Düzenle' : 'Tedarikçi Ekle'"></h3>
                <button @click="showSupplierForm=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveSupplier()" class="modal-body space-y-4" novalidate>
                <div>
                    <label class="form-label">Tedarikçi Adı <span class="text-red-500">*</span></label>
                    <input class="form-input" x-model="supplierForm.ad" placeholder="Toptancı / firma adı">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Yetkili</label>
                        <input class="form-input" x-model="supplierForm.yetkili">
                    </div>
                    <div>
                        <label class="form-label">Telefon</label>
                        <input class="form-input" x-model="supplierForm.telefon">
                    </div>
                </div>
                <div>
                    <label class="form-label">E-posta</label>
                    <input type="email" class="form-input" x-model="supplierForm.email">
                </div>
                <div>
                    <label class="form-label">Adres</label>
                    <input class="form-input" x-model="supplierForm.adres">
                </div>
                <div>
                    <label class="form-label">Not</label>
                    <textarea class="form-textarea" rows="2" x-model="supplierForm.notlar"></textarea>
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showSupplierForm=false">İptal</button>
                    <button class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        Kaydet
                    </button>
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
                        <p class="font-bold text-slate-800" x-text="formatMoney(detail?.toplam_tutar, currencyOf(detail))"></p>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-3">
                        <p class="text-xs text-emerald-500">Ödenen</p>
                        <p class="font-bold text-emerald-700" x-text="formatMoney(detail?.odenen_tutar, currencyOf(detail))"></p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3">
                        <p class="text-xs text-red-500">Kalan</p>
                        <p class="font-bold text-red-700" x-text="formatMoney(Math.max(0, detail?.kalan_tutar || 0), currencyOf(detail))"></p>
                        <p class="text-xs text-red-400 mt-1" x-show="currencyOf(detail) === 'USD'" x-text="formatCurrency(rowDebtTl(detail || {}))"></p>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Alınan Mallar</p>
                    <div class="space-y-1.5">
                        <template x-for="k in (detail?.kalemler || [])" :key="k.id">
                            <div class="flex justify-between items-center bg-slate-50 rounded-lg px-3 py-2 text-sm">
                                <span x-text="`${k.parca_adi}${k.marka ? ' ('+k.marka+')' : ''} × ${k.miktar}`"></span>
                                <span class="font-semibold" x-text="formatMoney(k.birim_fiyat * k.miktar, currencyOf(detail))"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border border-emerald-100 bg-emerald-50 rounded-xl p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <input type="number" class="form-input md:col-span-1" min="0" :step="currencyOf(detail) === 'USD' ? '0.01' : '100'" x-model="odemeForm.tutar" :placeholder="currencyOf(detail) === 'USD' ? 'Tutar ($)' : 'Tutar (TL)'">
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
                                    <span class="font-semibold text-emerald-700" x-text="formatMoney(o.tutar, currencyOf(detail))"></span>
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
        alimlar: [], stoklar: [], suppliers: [], loading: false, saving: false, search: '',
        doviz: { usd_try: 0 },
        showForm: false, showDetail: false, showSupplierForm: false, detail: null, supplierEditId: null,
        form: { tedarikci_adi:'', fatura_no:'', alim_tarihi: todayStr(), para_birimi:'TRY', usd_kur:0, kalemler:[], toplam_tutar:0, odenen_tutar:0, odeme_yontemi:'nakit', notlar:'' },
        supplierForm: { ad:'', yetkili:'', telefon:'', email:'', adres:'', notlar:'' },
        odemeForm: { tutar:0, odeme_yontemi:'nakit', odeme_tarihi: todayStr(), notlar:'' },
        async init() { await Promise.all([this.loadDoviz(), this.loadAlimlar(), this.loadStoklar(), this.loadSuppliers()]); },
        get filteredSuppliers() {
            const q = this.search.trim().toLocaleLowerCase('tr-TR');
            if (!q) return this.suppliers;
            return this.suppliers.filter(s => `${s.ad || ''} ${s.yetkili || ''} ${s.telefon || ''}`.toLocaleLowerCase('tr-TR').includes(q));
        },
        get filteredAlimlar() {
            const q = this.search.trim().toLocaleLowerCase('tr-TR');
            if (!q) return this.alimlar;
            return this.alimlar.filter(a => `${a.tedarikci_adi || ''} ${a.fatura_no || ''}`.toLocaleLowerCase('tr-TR').includes(q));
        },
        get toplamBorcTl() { return this.alimlar.reduce((s,a)=>s+this.rowDebtTl(a),0); },
        get toplamBorcUsd() { return this.alimlar.reduce((s,a)=>s+(this.currencyOf(a) === 'USD' ? (+a.kalan_tutar || 0) : 0),0); },
        get toplamOdenenTl() { return this.alimlar.reduce((s,a)=>s+this.rowAmountTl(a, +a.odenen_tutar || 0),0); },
        get toplamAdet() { return this.alimlar.reduce((s,a)=>s+(+a.toplam_adet||0),0); },
        async loadDoviz() { try { this.doviz = await api('api/doviz.php'); } catch(e) { this.doviz = { usd_try: 0 }; } },
        async loadAlimlar() { this.loading = true; try { this.alimlar = await api('api/tedarikciler.php'); } catch(e) {} finally { this.loading = false; } },
        async loadSuppliers() { try { this.suppliers = await api('api/tedarikciler.php?suppliers=1'); } catch(e) { this.suppliers = []; } },
        async loadStoklar() { try { this.stoklar = await api('api/stok.php'); } catch(e) {} },
        openSupplierForm() {
            this.supplierEditId = null;
            this.supplierForm = { ad:'', yetkili:'', telefon:'', email:'', adres:'', notlar:'' };
            this.showSupplierForm = true;
        },
        editSupplier(s) {
            this.supplierEditId = s.id;
            this.supplierForm = { ad:s.ad || '', yetkili:s.yetkili || '', telefon:s.telefon || '', email:s.email || '', adres:s.adres || '', notlar:s.notlar || '' };
            this.showSupplierForm = true;
        },
        async saveSupplier() {
            if (!this.supplierForm.ad.trim()) { showToast('Tedarikçi adı girin.', 'error'); return; }
            this.saving = true;
            try {
                const url = this.supplierEditId ? `api/tedarikciler.php?suppliers=1&id=${this.supplierEditId}` : 'api/tedarikciler.php?suppliers=1';
                await api(url, { method: this.supplierEditId ? 'PUT' : 'POST', body: this.supplierForm });
                showToast(this.supplierEditId ? 'Tedarikçi güncellendi.' : 'Tedarikçi eklendi.', 'success');
                this.showSupplierForm = false;
                await this.loadSuppliers();
            } catch(e) {} finally { this.saving = false; }
        },
        async deleteSupplier(s) {
            if (!confirm(`"${s.ad}" tedarikçisi silinsin mi? Alım kayıtları silinmez.`)) return;
            await api(`api/tedarikciler.php?suppliers=1&id=${s.id}`, { method:'DELETE' });
            showToast('Tedarikçi silindi.', 'success');
            await this.loadSuppliers();
        },
        openForm() {
            this.form = { tedarikci_adi:'', fatura_no:'', alim_tarihi: todayStr(), para_birimi:'TRY', usd_kur:this.doviz.usd_try || 0, kalemler:[{ parca_id:'', miktar:1, birim_fiyat:0 }], toplam_tutar:0, odenen_tutar:0, odeme_yontemi:'nakit', notlar:'' };
            this.showForm = true;
        },
        addKalem() { this.form.kalemler.push({ parca_id:'', miktar:1, birim_fiyat:0 }); },
        calcTotal() {
            this.form.toplam_tutar = +this.form.kalemler.reduce((s,k)=>s+(parseInt(k.miktar)||1)*(parseFloat(k.birim_fiyat)||0),0).toFixed(2);
            if ((parseFloat(this.form.odenen_tutar)||0) > this.form.toplam_tutar) this.form.odenen_tutar = this.form.toplam_tutar;
        },
        syncKur() {
            if (this.form.para_birimi === 'USD' && !(parseFloat(this.form.usd_kur) > 0)) {
                this.form.usd_kur = this.doviz.usd_try || 0;
            }
            this.calcTotal();
        },
        formTotalTl() { return this.rowAmountTl(this.form, +this.form.toplam_tutar || 0); },
        async saveAlim() {
            this.calcTotal();
            if (!this.form.tedarikci_adi.trim()) { showToast('Tedarikçi adı girin.', 'error'); return; }
            if (!this.form.kalemler.some(k => k.parca_id)) { showToast('En az bir stok ürünü seçin.', 'error'); return; }
            if (this.form.para_birimi === 'USD' && !(parseFloat(this.form.usd_kur) > 0)) { showToast('USD alımı için kur girin.', 'error'); return; }
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
        currencyOf(row) { return (row?.para_birimi || 'TRY').toUpperCase() === 'USD' ? 'USD' : 'TRY'; },
        rowUsdRate(row) {
            const stored = +(row?.usd_kur || 0);
            const current = +(this.doviz.usd_try || 0);
            return row?.id ? (current || stored) : (stored || current);
        },
        rowAmountTl(row, amount) { return this.currencyOf(row) === 'USD' ? amount * this.rowUsdRate(row) : amount; },
        rowDebtTl(row) { return this.rowAmountTl(row, Math.max(0, +(row?.kalan_tutar || 0))); },
        supplierTotalTl(s) { return (+s.toplam_alim_try || 0) + (this.doviz.usd_try ? (+s.toplam_alim_usd || 0) * (+this.doviz.usd_try || 0) : (+s.toplam_alim_usd_tl || 0)); },
        supplierDebtTl(s) { return (+s.kalan_borc_try || 0) + (this.doviz.usd_try ? (+s.kalan_borc_usd || 0) * (+this.doviz.usd_try || 0) : (+s.kalan_borc_usd_tl || 0)); },
        formatUsdAmount(v) { return '$' + Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        formatMoney(v, currency) { return currency === 'USD' ? this.formatUsdAmount(v) : formatCurrency(v || 0); },
    };
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
