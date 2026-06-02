<?php
$pageTitle    = 'Tahsilatlar';
$activePage   = 'tahsilatlar';
require_once ROOT . '/views/layout/header.php';
?>

<div x-data="tahsilatApp()" x-init="init()" x-cloak>

    <!-- Ozet Kartlar -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gecikmiş Taksit</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1" x-text="(ozet.gecikme_adet ?? 0) + ' adet'"></p>
                    <p class="text-xs text-slate-400 mt-0.5" x-text="fmt(ozet.bekleyen_tutar ?? 0) + ' bekleyen'"></p>
                </div>
                <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-triangle-exclamation text-orange-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekmeler -->
    <div class="card mb-6">
        <div class="flex border-b border-slate-100 px-1 pt-1">
            <button @click="aktifSekme='taksitler'"
                :class="aktifSekme==='taksitler' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors flex items-center gap-2">
                <i class="fas fa-credit-card"></i> Taksitler
                <span x-show="(ozet.bekleyen_adet ?? 0) > 0" class="bg-blue-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" x-text="ozet.bekleyen_adet"></span>
            </button>
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
            <button @click="aktifSekme='gecmis'"
                :class="aktifSekme==='gecmis' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors flex items-center gap-2">
                <i class="fas fa-history"></i> Geçmiş
            </button>
        </div>

        <!-- TAKSiTLER -->
        <div x-show="aktifSekme==='taksitler'" class="overflow-x-auto">
            <div class="p-4 border-b border-slate-100 flex items-center gap-3">
                <select x-model="taksiFiltre" class="form-select w-40 text-sm">
                    <option value="tumu">Tümü</option>
                    <option value="gecikmi">Gecikmiş</option>
                    <option value="bu_ay">Bu Ay</option>
                    <option value="gelecek">Gelecek</option>
                </select>
                <input x-model="taksiArama" type="text" placeholder="Müşteri ara..." class="form-input w-48 text-sm">
                <span class="text-sm text-slate-500 ml-auto" x-text="filtreliTaksitler.length + ' taksit'"></span>
            </div>
            <table class="data-table">
                <thead><tr><th>Müşteri</th><th>Cihaz</th><th>Taksit No</th><th>Vade Tarihi</th><th>Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
                <tbody>
                    <template x-if="filtreliTaksitler.length === 0">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400"><i class="fas fa-check-circle text-2xl mb-2 block text-green-400"></i>Bekleyen taksit yok!</td></tr>
                    </template>
                    <template x-for="t in filtreliTaksitler" :key="t.id">
                        <tr :class="t.gecikme_gun > 0 ? 'bg-red-50' : ''">
                            <td>
                                <div class="font-medium text-slate-800" x-text="t.musteri_adi"></div>
                                <div class="text-xs text-slate-400" x-text="t.telefon ?? ''"></div>
                            </td>
                            <td>
                                <span x-show="t.cihaz_adi" class="text-sm text-slate-600" x-text="(t.marka ? t.marka+' ' : '') + (t.cihaz_adi ?? '')"></span>
                                <span x-show="!t.cihaz_adi" class="text-slate-300 text-sm">—</span>
                            </td>
                            <td><span class="badge badge-blue text-xs" x-text="t.taksit_no + '. Taksit'"></span></td>
                            <td>
                                <span :class="t.gecikme_gun > 0 ? 'text-red-600 font-semibold' : 'text-slate-700'" x-text="formatTarih(t.vade_tarihi)"></span>
                                <span x-show="t.gecikme_gun > 0" class="ml-1 text-xs text-red-500" x-text="'(' + t.gecikme_gun + ' gün geç)'"></span>
                            </td>
                            <td class="font-semibold text-slate-800" x-text="fmt(t.tutar)"></td>
                            <td>
                                <span x-show="t.gecikme_gun > 0" class="badge badge-red">Gecikmiş</span>
                                <span x-show="t.gecikme_gun <= 0 && t.gecikme_gun > -7" class="badge badge-yellow">Bu Hafta</span>
                                <span x-show="t.gecikme_gun <= -7" class="badge badge-gray">Bekliyor</span>
                            </td>
                            <td><button @click="acTaksitOde(t)" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Ödendi</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div x-show="filtreliTaksitler.length > 0" class="p-4 bg-slate-50 border-t flex items-center justify-between">
                <span class="text-sm text-slate-500">Toplam bekleyen</span>
                <span class="font-bold text-slate-800" x-text="fmt(filtreliTaksitler.reduce((s,t)=>s+parseFloat(t.tutar),0))"></span>
            </div>
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
                        <tr>
                            <td><div class="font-medium text-slate-800" x-text="s.musteri_adi"></div><div class="text-xs text-slate-400" x-text="s.telefon ?? ''"></div></td>
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

        <!-- GECMiS -->
        <div x-show="aktifSekme==='gecmis'">
            <div class="p-4 border-b flex items-center gap-3">
                <input x-model="gecmisArama" type="text" placeholder="Müşteri ara..." class="form-input w-48 text-sm">
                <select x-model="gecmisFiltre" @change="loadGecmis()" class="form-select w-36 text-sm">
                    <option value="">Tümü</option>
                    <option value="servis">Servis</option>
                    <option value="satis">Satış</option>
                </select>
                <button @click="loadGecmis()" class="btn btn-secondary btn-sm"><i class="fas fa-refresh"></i></button>
            </div>
            <table class="data-table">
                <thead><tr><th>Müşteri</th><th>Tür</th><th>Tutar</th><th>Ödeme Yöntemi</th><th>Tarih</th><th>Notlar</th><th></th></tr></thead>
                <tbody>
                    <template x-if="filtreliGecmis.length === 0">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400">Kayıt bulunamadı</td></tr>
                    </template>
                    <template x-for="t in filtreliGecmis" :key="t.id">
                        <tr>
                            <td class="font-medium text-slate-800" x-text="t.musteri_adi"></td>
                            <td>
                                <span x-show="t.kaynak_tip==='servis'" class="badge badge-blue">Servis</span>
                                <span x-show="t.kaynak_tip==='satis'" class="badge badge-purple">Satış</span>
                            </td>
                            <td class="font-semibold text-green-700" x-text="fmt(t.tutar)"></td>
                            <td class="text-sm text-slate-600" x-text="odemeYontemiLabel(t.odeme_yontemi)"></td>
                            <td class="text-sm text-slate-600" x-text="formatTarih(t.tahsilat_tarihi)"></td>
                            <td class="text-xs text-slate-400" x-text="t.notlar ?? ''"></td>
                            <td><button @click="silTahsilat(t.id)" class="btn btn-danger btn-sm btn-icon" title="Sil"><i class="fas fa-trash"></i></button></td>
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

    <!-- MODAL: Taksit Ode -->
    <div x-show="taksitModal" class="modal-backdrop" @click.self="taksitModal=false">
        <div class="modal-box max-w-md">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Taksit Öde</h3>
                <button @click="taksitModal=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body space-y-4">
                <div class="bg-slate-50 rounded-lg p-3 text-sm">
                    <div class="flex justify-between mb-1"><span class="text-slate-500">Müşteri</span><span class="font-medium" x-text="taksitForm.musteri_adi"></span></div>
                    <div class="flex justify-between mb-1"><span class="text-slate-500">Taksit No</span><span class="font-medium" x-text="taksitForm.taksit_no + '. Taksit'"></span></div>
                    <div class="flex justify-between mb-1"><span class="text-slate-500">Vade</span><span :class="taksitForm.gecikme_gun > 0 ? 'text-red-600 font-semibold' : 'font-medium'" x-text="formatTarih(taksitForm.vade_tarihi)"></span></div>
                    <div class="flex justify-between font-semibold border-t pt-1 mt-1"><span>Tutar</span><span class="text-blue-600" x-text="fmt(taksitForm.tutar)"></span></div>
                </div>
                <div>
                    <label class="form-label">Ödeme Yöntemi</label>
                    <select x-model="taksitForm.odeme_yontemi" class="form-select">
                        <option value="nakit">Nakit</option><option value="kart">Kredi/Banka Kartı</option>
                        <option value="havale">Havale / EFT</option><option value="cek">Çek</option>
                    </select>
                </div>
                <div><label class="form-label">Ödeme Tarihi</label><input x-model="taksitForm.odeme_tarihi" type="date" class="form-input"></div>
            </div>
            <div class="modal-footer">
                <button @click="taksitModal=false" class="btn btn-secondary">İptal</button>
                <button @click="saveTaksitOde()" :disabled="saving" class="btn btn-success">
                    <span x-show="saving"><i class="fas fa-spinner fa-spin"></i></span>
                    <span x-show="!saving"><i class="fas fa-check"></i> Ödendi Olarak İşaretle</span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function tahsilatApp() {
    return {
        aktifSekme: 'taksitler',
        ozet: {}, taksitler: [], servisOdemeleri: [], satisOdemeleri: [], gecmisTahsilatlar: [],
        taksiFiltre: 'tumu', taksiArama: '', gecmisArama: '', gecmisFiltre: '',
        tahsilatModal: false, taksitModal: false, saving: false,
        tahsilatForm: { musteri_id:0, kaynak_tip:'', kaynak_id:0, musteri_adi:'', toplam_tutar:0, odenen_tutar:0, kalan:0, tutar:0, odeme_yontemi:'nakit', tarih:todayStr(), notlar:'' },
        taksitForm:   { id:0, musteri_adi:'', taksit_no:0, vade_tarihi:'', tutar:0, gecikme_gun:0, odeme_yontemi:'nakit', odeme_tarihi:todayStr() },

        csrfHeaders() {
            return {
                'Content-Type':'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };
        },

        async init() { await Promise.all([this.loadOzet(), this.loadTaksitler(), this.loadOdenmemisler(), this.loadGecmis()]); },

        async loadOzet() {
            const r = await fetch('api/tahsilatlar.php?ozet=1');
            const d = await r.json(); this.ozet = d.data ?? {};
        },
        async loadTaksitler() {
            const r = await fetch('api/tahsilatlar.php?taksitler=1');
            const d = await r.json(); this.taksitler = d.data ?? [];
        },
        async loadOdenmemisler() {
            const r = await fetch('api/tahsilatlar.php?odenmemisler=1');
            const d = await r.json(); const all = d.data ?? [];
            this.servisOdemeleri = all.filter(x => x.tip === 'servis');
            this.satisOdemeleri  = all.filter(x => x.tip === 'satis');
        },
        async loadGecmis() {
            let url = 'api/tahsilatlar.php?limit=200';
            if (this.gecmisFiltre) url += '&kaynak_tip=' + this.gecmisFiltre;
            const r = await fetch(url); const d = await r.json();
            this.gecmisTahsilatlar = d.data ?? [];
        },

        get filtreliTaksitler() {
            let list = [...this.taksitler];
            const bugun = new Date(); bugun.setHours(0,0,0,0);
            const aySon = new Date(bugun.getFullYear(), bugun.getMonth()+1, 0);
            if (this.taksiFiltre === 'gecikmi') list = list.filter(t => parseInt(t.gecikme_gun) > 0);
            else if (this.taksiFiltre === 'bu_ay') list = list.filter(t => { const v=new Date(t.vade_tarihi); return v >= bugun && v <= aySon; });
            else if (this.taksiFiltre === 'gelecek') list = list.filter(t => new Date(t.vade_tarihi) > aySon);
            if (this.taksiArama) { const s=this.taksiArama.toLowerCase(); list=list.filter(t=>t.musteri_adi.toLowerCase().includes(s)); }
            return list;
        },
        get filtreliGecmis() {
            if (!this.gecmisArama) return this.gecmisTahsilatlar;
            const s = this.gecmisArama.toLowerCase();
            return this.gecmisTahsilatlar.filter(t => t.musteri_adi.toLowerCase().includes(s));
        },

        acTahsilat(item) {
            this.tahsilatForm = { musteri_id:item.musteri_id, kaynak_tip:item.tip, kaynak_id:item.id, musteri_adi:item.musteri_adi, toplam_tutar:parseFloat(item.toplam_tutar), odenen_tutar:parseFloat(item.odenen_tutar), kalan:parseFloat(item.kalan), tutar:parseFloat(item.kalan), odeme_yontemi:'nakit', tarih:todayStr(), notlar:'' };
            this.tahsilatModal = true;
        },
        acTaksitOde(t) {
            this.taksitForm = { id:t.id, musteri_adi:t.musteri_adi, taksit_no:t.taksit_no, vade_tarihi:t.vade_tarihi, tutar:parseFloat(t.tutar), gecikme_gun:parseInt(t.gecikme_gun), odeme_yontemi:'nakit', odeme_tarihi:todayStr() };
            this.taksitModal = true;
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

        async saveTaksitOde() {
            this.saving = true;
            try {
                const r = await fetch('api/tahsilatlar.php', { method:'POST', headers:this.csrfHeaders(), body:JSON.stringify({ taksit_id:this.taksitForm.id, odeme_yontemi:this.taksitForm.odeme_yontemi, odeme_tarihi:this.taksitForm.odeme_tarihi }) });
                const d = await r.json();
                if (d.success) { showToast('Taksit ödendi!', 'success'); this.taksitModal=false; await this.init(); }
                else showToast(d.message ?? 'Hata!', 'error');
            } finally { this.saving=false; }
        },

        async silTahsilat(id) {
            if (!confirm('Bu tahsilat kaydı silinecek. Emin misiniz?')) return;
            const r = await fetch('api/tahsilatlar.php?id='+id, { method:'DELETE', headers:this.csrfHeaders() });
            const d = await r.json();
            if (d.success) { showToast('Silindi.', 'success'); await this.init(); }
            else showToast(d.message ?? 'Hata!', 'error');
        },

        fmt(v) { return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',minimumFractionDigits:2}).format(parseFloat(v)||0); },
        formatTarih(d) { if(!d) return '—'; const [y,m,g]=d.split('-'); return g+'.'+m+'.'+y; },
        odemeYontemiLabel(y) { return {nakit:'Nakit',kart:'Kart',havale:'Havale/EFT',cek:'Çek'}[y] ?? y; },
    };
}
function todayStr() { return new Date().toISOString().slice(0,10); }
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
