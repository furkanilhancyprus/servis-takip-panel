<?php
$pageTitle    = 'Ayarlar';
$activePage   = 'ayarlar';
require_once ROOT . '/views/layout/header.php';
?>

<div x-data="ayarlarApp()" x-init="init()" x-cloak class="max-w-4xl space-y-6">

    <?php if (getenv('STP_DATA_DIR') && getenv('STP_LOCAL_ONLY') === '1'): ?>
    <div class="card p-6 border-emerald-200 bg-emerald-50">
        <h3 class="font-semibold text-emerald-900 mb-2 flex items-center gap-2">
            <i class="fas fa-computer text-emerald-600"></i> Lokal Lifetime Modu
        </h3>
        <p class="text-sm text-emerald-800">
            Bu sürüm tamamen bu bilgisayarda çalışır. Web panel, mobil uygulama ve bulut senkronizasyon kullanılmaz; veriler yerel veritabanında saklanır.
        </p>
    </div>
    <?php endif; ?>

    <?php if (getenv('STP_DATA_DIR') && getenv('STP_LOCAL_ONLY') !== '1'): ?>
    <!-- Senkronizasyon -->
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-rotate text-emerald-500"></i> Senkronizasyon
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="form-label">Web Sunucu Adresi</label>
                <input type="url" class="form-input" x-model="syncForm.server_url" placeholder="https://siteadresiniz.com">
            </div>
            <div>
                <label class="form-label">Web E-posta</label>
                <input type="email" class="form-input" x-model="syncForm.email">
            </div>
            <div>
                <label class="form-label">Web Şifre</label>
                <input type="password" class="form-input" x-model="syncForm.password">
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-4">
            <button class="btn btn-primary" @click="connectSync()" :disabled="syncBusy">
                <span x-show="syncBusy" class="spinner w-4 h-4"></span>
                <i x-show="!syncBusy" class="fas fa-link"></i> Bağlan
            </button>
            <button class="btn btn-secondary" @click="runSync()" :disabled="syncBusy || !syncStatus.has_token">
                <i class="fas fa-rotate"></i> Şimdi Senkronize Et
            </button>
            <button class="btn btn-danger" @click="disconnectSync()" :disabled="syncBusy || !syncStatus.has_token">
                <i class="fas fa-link-slash"></i> Bağlantıyı Kes
            </button>
        </div>
        <div class="mt-4 text-xs text-slate-500 space-y-1">
            <div>Durum: <span class="font-semibold" x-text="syncStatus.has_token ? 'Bağlı' : 'Bağlı değil'"></span></div>
            <div x-show="syncStatus.server_url">Sunucu: <span x-text="syncStatus.server_url"></span></div>
            <div x-show="syncStatus.last_pull_at">Son çekme: <span x-text="syncStatus.last_pull_at"></span></div>
            <div>Bekleyen yerel işlem: <span x-text="syncStatus.pending ?? 0"></span></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Firma Bilgileri -->
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-building text-blue-500"></i> Firma Bilgileri
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Firma Adı</label><input type="text" class="form-input" x-model="ayarlar.firma_adi"></div>
            <div><label class="form-label">Telefon</label><input type="text" class="form-input" x-model="ayarlar.firma_telefon"></div>
            <div><label class="form-label">E-posta</label><input type="email" class="form-input" x-model="ayarlar.firma_email"></div>
            <div>
                <label class="form-label">Para Birimi</label>
                <select class="form-select" x-model="ayarlar.para_birimi">
                    <option value="₺">₺ Türk Lirası</option>
                    <option value="$">$ Dolar</option>
                    <option value="€">€ Euro</option>
                    <option value="£">£ Sterlin</option>
                </select>
            </div>
            <div class="md:col-span-2"><label class="form-label">Adres</label><textarea class="form-textarea" rows="2" x-model="ayarlar.firma_adres"></textarea></div>
        </div>
    </div>

    <!-- Fatura Ayarlari -->
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-file-invoice text-indigo-500"></i> Fatura / Teklif Ayarları
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Vergi Numarası</label><input type="text" class="form-input" x-model="ayarlar.firma_vergi_no" placeholder="1234567890"></div>
            <div><label class="form-label">IBAN</label><input type="text" class="form-input" x-model="ayarlar.firma_iban" placeholder="TR00 0000 0000 0000 0000 0000 00"></div>
            <div class="md:col-span-2"><label class="form-label">Fatura Alt Notu</label><textarea class="form-textarea" rows="2" x-model="ayarlar.fatura_notu" placeholder="Ödeme için teşekkür ederiz."></textarea></div>
            <div class="md:col-span-2">
                <label class="form-label">Logo URL <span class="text-slate-400 font-normal">(https:// ile başlayan tam URL)</span></label>
                <input type="url" class="form-input" x-model="ayarlar.fatura_logo" placeholder="https://firma.com/logo.png">
                <div x-show="ayarlar.fatura_logo" class="mt-2">
                    <img :src="ayarlar.fatura_logo" class="h-12 object-contain border border-slate-200 rounded p-1" @error="$el.style.display='none'">
                </div>
            </div>
        </div>
    </div>

    <!-- Bakim Ayarlari -->
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-calendar-check text-emerald-500"></i> Varsayılan Bakım Ayarları
        </h3>
        <div class="grid grid-cols-2 gap-4 max-w-sm">
            <div><label class="form-label">Bakım Periyodu (Ay)</label><input type="number" class="form-input" min="1" max="60" x-model="ayarlar.varsayilan_bakim_periyodu"></div>
            <div><label class="form-label">Hatırlatma (Gün Önce)</label><input type="number" class="form-input" min="1" max="90" x-model="ayarlar.varsayilan_hatirlatma_gun"></div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Bu ayarlar yeni eklenen müşterilere otomatik uygulanır.</p>
    </div>

    <!-- Kaydet -->
    <div class="flex justify-end">
        <button class="btn btn-primary px-8" @click="saveAyarlar()" :disabled="saving">
            <span x-show="saving" class="spinner w-4 h-4"></span>
            <i x-show="!saving" class="fas fa-save"></i>
            Ayarları Kaydet
        </button>
    </div>

    <!-- Cihaz Katalogu -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fas fa-droplet text-cyan-500"></i> Ürün / Cihaz Kataloğu
            </h3>
            <button class="btn btn-primary btn-sm" @click="openCihazModal()">
                <i class="fas fa-plus text-xs"></i> Yeni Cihaz
            </button>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="data-table">
                <thead><tr><th>Cihaz Adı</th><th>Marka</th><th>Model</th><th>Stok</th><th>Varsayılan Fiyat</th><th class="text-right">İşlemler</th></tr></thead>
                <tbody>
                    <template x-if="cihazlar.length === 0">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400">
                            <i class="fas fa-droplet text-2xl mb-2 block text-slate-200"></i>Henüz cihaz eklenmemiş
                        </td></tr>
                    </template>
                    <template x-for="c in cihazlar" :key="c.id">
                        <tr>
                            <td class="font-medium text-slate-800" x-text="c.cihaz_adi"></td>
                            <td class="text-slate-600 text-sm" x-text="c.marka ?? '—'"></td>
                            <td class="text-slate-600 text-sm" x-text="c.model ?? '—'"></td>
                            <td class="text-slate-600 text-sm" x-text="c.stok_miktari ?? 0"></td>
                            <td class="text-slate-700 text-sm" x-text="formatCurrency(c.varsayilan_fiyat)"></td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="editCihaz(c)"><i class="fas fa-pen text-blue-500 text-xs"></i></button>
                                    <button class="btn btn-sm btn-danger btn-icon" @click="deleteCihaz(c)"><i class="fas fa-trash text-xs"></i></button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Standart Islemler -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fas fa-list-check text-purple-500"></i> Standart İşlemler
            </h3>
            <button class="btn btn-primary btn-sm" @click="openIslemModal()">
                <i class="fas fa-plus text-xs"></i> Ekle
            </button>
        </div>
        <p class="text-xs text-slate-400 mb-3">
            <i class="fas fa-info-circle mr-1"></i>
            Her işleme varsayılan parçalar ekleyebilirsiniz. Servis kaydında bu işlem seçilince parçalar otomatik gelir, ancak fiyatı toplama eklenmez.
        </p>
        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="data-table">
                <thead><tr><th>İşlem Adı</th><th>Varsayılan Fiyat</th><th>Dahil Parçalar</th><th class="text-right">İşlemler</th></tr></thead>
                <tbody>
                    <template x-if="islemler.length === 0">
                        <tr><td colspan="4" class="text-center py-8 text-slate-400">Kayıt yok</td></tr>
                    </template>
                    <template x-for="ism in islemler" :key="ism.id">
                        <tr>
                            <td class="font-medium text-slate-800" x-text="ism.islem_adi"></td>
                            <td class="text-slate-600" x-text="formatCurrency(ism.varsayilan_fiyat)"></td>
                            <td>
                                <template x-if="ism.parcalar && ism.parcalar.length > 0">
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="p in ism.parcalar" :key="p.id">
                                            <span class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">
                                                <span x-text="p.parca_adi"></span>
                                                <span class="text-slate-400" x-text="'×'+p.miktar"></span>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!ism.parcalar || ism.parcalar.length === 0">
                                    <span class="text-slate-300 text-xs">—</span>
                                </template>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="editIslem(ism)"><i class="fas fa-pen text-blue-500 text-xs"></i></button>
                                    <button class="btn btn-sm btn-danger btn-icon" @click="deleteIslem(ism)"><i class="fas fa-trash text-xs"></i></button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: Cihaz -->
    <div x-show="showCihazModal" x-cloak class="modal-backdrop" @click.self="showCihazModal=false">
        <div class="modal-box max-w-md">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="cihazEditId ? 'Cihaz Düzenle' : 'Yeni Cihaz Ekle'"></h3>
                <button @click="showCihazModal=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveCihaz()" class="modal-body space-y-4">
                <div><label class="form-label">Cihaz Adı <span class="text-red-500">*</span></label><input type="text" class="form-input" x-model="cihazForm.cihaz_adi" required></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Marka</label><input type="text" class="form-input" x-model="cihazForm.marka"></div>
                    <div><label class="form-label">Model</label><input type="text" class="form-input" x-model="cihazForm.model"></div>
                </div>
                <div><label class="form-label">Varsayılan Fiyat (₺)</label><input type="number" class="form-input" step="0.01" min="0" x-model="cihazForm.varsayilan_fiyat"></div>
                <div><label class="form-label">Açıklama</label><textarea class="form-textarea" rows="2" x-model="cihazForm.aciklama"></textarea></div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showCihazModal=false">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <span x-text="cihazEditId ? 'Güncelle' : 'Kaydet'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Standart Islem (genişletilmiş) -->
    <div x-show="showIslemModal" x-cloak class="modal-backdrop" @click.self="showIslemModal=false">
        <div class="modal-box max-w-lg">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="islemEditId ? 'İşlem Düzenle' : 'Yeni İşlem Ekle'"></h3>
                <button @click="showIslemModal=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveIslem()" class="modal-body space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="form-label">İşlem Adı <span class="text-red-500">*</span></label>
                        <input type="text" class="form-input" x-model="islemForm.islem_adi" required placeholder="Örn: Full Servis">
                    </div>
                    <div>
                        <label class="form-label">Varsayılan Fiyat (₺)</label>
                        <input type="number" class="form-input" step="0.01" min="0" x-model="islemForm.varsayilan_fiyat">
                    </div>
                </div>

                <!-- Dahil Parçalar -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <label class="form-label mb-0">Bu İşlemde Kullanılan Parçalar</label>
                            <p class="text-xs text-slate-400 mt-0.5">Servis kaydında bu işlem seçilince otomatik eklenir, ücrete dahil değildir.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" @click="addIslemParca()">
                            <i class="fas fa-plus text-xs"></i> Parça Ekle
                        </button>
                    </div>

                    <template x-if="islemForm.parcalar.length === 0">
                        <div class="text-center py-4 text-slate-400 text-sm bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            <i class="fas fa-box-open text-xl mb-1 block text-slate-200"></i>
                            Henüz parça eklenmedi
                        </div>
                    </template>

                    <div class="space-y-2">
                        <template x-for="(p, i) in islemForm.parcalar" :key="i">
                            <div class="flex gap-2 items-center">
                                <select class="form-select flex-1" x-model="p.parca_id">
                                    <option value="">Parça seçiniz...</option>
                                    <template x-for="pk in stoklar" :key="pk.id">
                                        <option :value="pk.id" x-text="pk.parca_adi + (pk.marka ? ' ('+pk.marka+')' : '')"></option>
                                    </template>
                                </select>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-slate-400">×</span>
                                    <input type="number" class="form-input w-16 text-center" min="1" x-model="p.miktar" placeholder="Adet">
                                </div>
                                <button type="button" class="btn btn-danger btn-icon btn-sm"
                                        @click="islemForm.parcalar.splice(i,1)">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showIslemModal=false">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <span x-text="islemEditId ? 'Güncelle' : 'Kaydet'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function ayarlarApp() {
    return {
        ayarlar: { firma_adi:'', firma_telefon:'', firma_adres:'', firma_email:'', para_birimi:'₺',
                   varsayilan_bakim_periyodu:6, varsayilan_hatirlatma_gun:7,
                   firma_vergi_no:'', firma_iban:'', fatura_notu:'', fatura_logo:'' },
        islemler: [], cihazlar: [], stoklar: [], saving: false,
        showIslemModal: false, islemEditId: null,
        islemForm: { islem_adi:'', varsayilan_fiyat:0, parcalar:[] },
        showCihazModal: false, cihazEditId: null,
        cihazForm: { cihaz_adi:'', marka:'', model:'', varsayilan_fiyat:0, aciklama:'' },
        syncBusy: false,
        syncStatus: {},
        syncForm: { server_url:'', email:'', password:'' },

        csrfHeaders() {
            return {
                'Content-Type':'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };
        },

        async init() {
            await Promise.all([this.loadAyarlar(), this.loadIslemler(), this.loadCihazlar(), this.loadStoklar(), this.loadSyncStatus()]);
        },

        async loadSyncStatus() {
            try {
                const r = await fetch('api/sync_client.php?action=status');
                const d = await r.json();
                if (d.success) {
                    this.syncStatus = d.data || {};
                    this.syncForm.server_url = this.syncStatus.server_url || this.syncForm.server_url;
                }
            } catch(e) {}
        },
        async connectSync() {
            this.syncBusy = true;
            try {
                const r = await fetch('api/sync_client.php?action=connect', {
                    method:'POST',
                    headers:this.csrfHeaders(),
                    body:JSON.stringify(this.syncForm)
                });
                const d = await r.json();
                if (d.success) {
                    showToast('Senkron bağlantısı kuruldu.', 'success');
                    this.syncForm.password = '';
                    await this.loadSyncStatus();
                } else showToast(d.message || 'Bağlantı kurulamadı.', 'error');
            } catch(e) { showToast('Sunucuya bağlanılamadı.', 'error'); }
            finally { this.syncBusy = false; }
        },
        async runSync() {
            this.syncBusy = true;
            try {
                const r = await fetch('api/sync_client.php?action=run', { method:'POST', headers:this.csrfHeaders(), body:'{}' });
                const d = await r.json();
                if (d.success) {
                    showToast(`Senkron tamamlandı. Çekilen: ${d.data?.pulled ?? 0}`, 'success');
                    await Promise.all([this.loadAyarlar(), this.loadIslemler(), this.loadCihazlar(), this.loadStoklar(), this.loadSyncStatus()]);
                } else showToast(d.message || 'Senkron başarısız.', 'error');
            } catch(e) { showToast('Senkron sırasında bağlantı hatası.', 'error'); }
            finally { this.syncBusy = false; }
        },
        async disconnectSync() {
            if (!confirm('Senkron bağlantısı kesilsin mi?')) return;
            this.syncBusy = true;
            try {
                const r = await fetch('api/sync_client.php?action=disconnect', { method:'POST', headers:this.csrfHeaders(), body:'{}' });
                const d = await r.json();
                if (d.success) {
                    showToast('Senkron bağlantısı kapatıldı.', 'success');
                    await this.loadSyncStatus();
                } else showToast(d.message || 'İşlem başarısız.', 'error');
            } finally { this.syncBusy = false; }
        },

        async loadAyarlar() {
            try {
                const r=await fetch('api/ayarlar.php'); const d=await r.json();
                if(d.success && d.data) this.ayarlar={...this.ayarlar,...d.data};
            } catch(e) {}
        },
        async saveAyarlar() {
            this.saving=true;
            try {
                const r=await fetch('api/ayarlar.php',{method:'POST',headers:this.csrfHeaders(),body:JSON.stringify(this.ayarlar)});
                const d=await r.json();
                if(d.success) showToast('Ayarlar kaydedildi.','success'); else showToast(d.message??'Hata!','error');
            } catch(e){ showToast('Sunucu hatası!','error'); } finally{ this.saving=false; }
        },

        async loadStoklar() {
            try { const r=await fetch('api/stok.php'); const d=await r.json(); this.stoklar=d.data??d??[]; } catch(e) {}
        },

        async loadIslemler() {
            try { const r=await fetch('api/standart_islemler.php'); const d=await r.json(); this.islemler=d.data??d??[]; } catch(e) {}
        },
        openIslemModal() {
            this.islemEditId=null;
            this.islemForm={ islem_adi:'', varsayilan_fiyat:0, parcalar:[] };
            this.showIslemModal=true;
        },
        editIslem(ism) {
            this.islemEditId=ism.id;
            this.islemForm={
                islem_adi: ism.islem_adi,
                varsayilan_fiyat: ism.varsayilan_fiyat||0,
                parcalar: (ism.parcalar||[]).map(p=>({ parca_id: p.parca_id, miktar: p.miktar })),
            };
            this.showIslemModal=true;
        },
        addIslemParca() {
            this.islemForm.parcalar.push({ parca_id:'', miktar:1 });
        },
        async saveIslem() {
            this.saving=true;
            try {
                const url=this.islemEditId?`api/standart_islemler.php?id=${this.islemEditId}`:'api/standart_islemler.php';
                const method=this.islemEditId?'PUT':'POST';
                const payload={ ...this.islemForm, parcalar: this.islemForm.parcalar.filter(p=>p.parca_id) };
                const r=await fetch(url,{method,headers:this.csrfHeaders(),body:JSON.stringify(payload)});
                const d=await r.json();
                if(d.success){
                    showToast(this.islemEditId?'Güncellendi.':'Eklendi.','success');
                    this.showIslemModal=false;
                    await this.loadIslemler();
                } else showToast(d.message??'Hata!','error');
            } finally{ this.saving=false; }
        },
        async deleteIslem(ism) {
            if(!confirm(`"${ism.islem_adi}" silinsin mi?`)) return;
            const r=await fetch(`api/standart_islemler.php?id=${ism.id}`,{method:'DELETE',headers:this.csrfHeaders()});
            const d=await r.json();
            if(d.success){ showToast('Silindi.','success'); await this.loadIslemler(); }
            else showToast(d.message??'Hata!','error');
        },

        async loadCihazlar() {
            try { const r=await fetch('api/cihazlar.php'); const d=await r.json(); this.cihazlar=d.data??[]; } catch(e) {}
        },
        openCihazModal() { this.cihazEditId=null; this.cihazForm={cihaz_adi:'',marka:'',model:'',varsayilan_fiyat:0,aciklama:''}; this.showCihazModal=true; },
        editCihaz(c) { this.cihazEditId=c.id; this.cihazForm={cihaz_adi:c.cihaz_adi,marka:c.marka??'',model:c.model??'',varsayilan_fiyat:parseFloat(c.varsayilan_fiyat)||0,aciklama:c.aciklama??''}; this.showCihazModal=true; },
        async saveCihaz() {
            this.saving=true;
            try {
                const url=this.cihazEditId?`api/cihazlar.php?id=${this.cihazEditId}`:'api/cihazlar.php';
                const method=this.cihazEditId?'PUT':'POST';
                const r=await fetch(url,{method,headers:this.csrfHeaders(),body:JSON.stringify(this.cihazForm)});
                const d=await r.json();
                if(d.success){ showToast(this.cihazEditId?'Güncellendi.':'Eklendi.','success'); this.showCihazModal=false; await Promise.all([this.loadCihazlar(), this.loadStoklar()]); }
                else showToast(d.message??'Hata!','error');
            } finally{ this.saving=false; }
        },
        async deleteCihaz(c) {
            if(!confirm(`"${c.cihaz_adi}" silinsin mi?`)) return;
            const r=await fetch(`api/cihazlar.php?id=${c.id}`,{method:'DELETE',headers:this.csrfHeaders()});
            const d=await r.json();
            if(d.success){ showToast('Silindi.','success'); await Promise.all([this.loadCihazlar(), this.loadStoklar()]); }
            else showToast(d.message??'Hata!','error');
        },

        formatCurrency(v) { return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',minimumFractionDigits:2}).format(parseFloat(v)||0); },
    };
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
