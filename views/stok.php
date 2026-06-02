<?php
$pageTitle  = 'Stok Yönetimi';
$activePage = 'stok';
include __DIR__ . '/layout/header.php';
?>

<div x-data="stokApp()" x-init="init()">

    <!-- Stats Row -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Parça</p>
                    <p class="text-3xl font-bold text-slate-800 mt-1" x-text="parcalar.length"></p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-boxes-stacked text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Kritik Stok</p>
                    <p class="text-3xl font-bold text-red-600 mt-1" x-text="kritikCount"></p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-triangle-exclamation text-red-500"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Stok Değeri</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="formatCurrency(stokDegeri)"></p>
                </div>
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-turkish-lira-sign text-emerald-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" placeholder="Parça ara..."
                       class="form-input pl-9 w-56"
                       x-model="search">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                <input type="checkbox" class="rounded" x-model="sadecekritik">
                <span>Yalnız Kritik</span>
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                <input type="checkbox" class="rounded" x-model="sadececihaz">
                <span>Yalnız Cihazlar</span>
            </label>
        </div>
        <button class="btn btn-primary" @click="openAddModal()">
            <i class="fas fa-plus"></i> Yeni Parça / Cihaz
        </button>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Parça / Cihaz Adı</th>
                        <th>Marka</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Kritik Seviye</th>
                        <th>Birim Fiyat</th>
                        <th>Tedarikçi</th>
                        <th>Tür</th>
                        <th>Durum</th>
                        <th class="text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="9" class="text-center py-10 text-slate-400">
                            <div class="spinner mx-auto mb-2"></div>Yükleniyor...
                        </td></tr>
                    </template>
                    <template x-if="!loading && filtered.length === 0">
                        <tr><td colspan="9" class="text-center py-10 text-slate-400">
                            <i class="fas fa-boxes-stacked text-3xl mb-2 block text-slate-200"></i>
                            Parça bulunamadı
                        </td></tr>
                    </template>
                    <template x-for="p in filtered" :key="p.id">
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-800" x-text="p.parca_adi"></span>
                                </div>
                            </td>
                            <td class="text-slate-500" x-text="p.marka || '—'"></td>
                            <td class="text-center">
                                <span class="text-lg font-bold"
                                      :class="p.stok_miktari <= p.kritik_stok_seviyesi ? 'text-red-600' : 'text-slate-800'"
                                      x-text="p.stok_miktari"></span>
                            </td>
                            <td class="text-center text-slate-400" x-text="p.kritik_stok_seviyesi"></td>
                            <td class="font-medium text-slate-700" x-text="formatCurrency(p.birim_fiyat)"></td>
                            <td class="text-slate-500 text-sm" x-text="p.tedarikci || '—'"></td>
                            <td>
                                <span x-show="p.is_cihaz == 1"
                                      class="badge"
                                      style="background:#ede9fe;color:#7c3aed;">
                                    <i class="fas fa-microchip text-xs mr-1"></i>Cihaz
                                </span>
                                <span x-show="p.is_cihaz != 1"
                                      class="badge badge-blue">Parça</span>
                            </td>
                            <td>
                                <span class="badge"
                                      :class="p.stok_miktari <= p.kritik_stok_seviyesi ? 'badge-red' : 'badge-green'"
                                      x-text="p.stok_miktari <= p.kritik_stok_seviyesi ? 'Kritik' : 'Normal'"></span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-success btn-icon" title="Stok Girişi"
                                            @click="stokGirisi(p)">
                                        <i class="fas fa-plus text-emerald-600 text-xs"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary btn-icon" title="Düzenle"
                                            @click="editParca(p)">
                                        <i class="fas fa-pen text-blue-500 text-xs"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-icon" title="Sil"
                                            @click="deleteParca(p)">
                                        <i class="fas fa-trash text-red-500 text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== ADD/EDIT MODAL ===== -->
    <div x-show="showForm" x-cloak class="modal-backdrop" @click.self="showForm=false">
        <div class="modal-box max-w-lg">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="editId ? 'Parça / Cihaz Düzenle' : 'Yeni Parça / Cihaz Ekle'"></h3>
                <button @click="showForm=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveParca()" class="modal-body space-y-4">

                <!-- Cihaz toggle -->
                <div class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer"
                     :class="form.is_cihaz ? 'border-purple-300 bg-purple-50' : 'border-slate-200 bg-slate-50'"
                     @click="form.is_cihaz = !form.is_cihaz">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                         :class="form.is_cihaz ? 'bg-purple-100' : 'bg-slate-200'">
                        <i class="fas fa-microchip text-sm"
                           :class="form.is_cihaz ? 'text-purple-600' : 'text-slate-400'"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium"
                           :class="form.is_cihaz ? 'text-purple-800' : 'text-slate-700'">
                            Bu ürün bir cihazdır (satılabilir ürün)
                        </p>
                        <p class="text-xs mt-0.5"
                           :class="form.is_cihaz ? 'text-purple-500' : 'text-slate-400'">
                            Cihazlar müşterilere satılabilir ve taksit planı oluşturulabilir
                        </p>
                    </div>
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                         :class="form.is_cihaz ? 'border-purple-500 bg-purple-500' : 'border-slate-300'">
                        <i x-show="form.is_cihaz" class="fas fa-check text-white text-xs"></i>
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        <span x-text="form.is_cihaz ? 'Cihaz Adı' : 'Parça Adı'"></span>
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" class="form-input" x-model="form.parca_adi" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Marka</label>
                        <input type="text" class="form-input" x-model="form.marka">
                    </div>
                    <div>
                        <label class="form-label">Tedarikçi</label>
                        <input type="text" class="form-input" x-model="form.tedarikci">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="form-label" x-text="form.is_cihaz ? 'Satış Fiyatı (₺)' : 'Birim Fiyat (₺)'"></label>
                        <input type="number" class="form-input" step="0.01" min="0" x-model="form.birim_fiyat">
                    </div>
                    <div>
                        <label class="form-label">Stok Miktarı</label>
                        <input type="number" class="form-input" min="0" x-model="form.stok_miktari">
                    </div>
                    <div>
                        <label class="form-label">Kritik Seviye</label>
                        <input type="number" class="form-input" min="0" x-model="form.kritik_stok_seviyesi">
                    </div>
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showForm=false">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <span x-text="editId ? 'Güncelle' : 'Kaydet'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== STOK GİRİŞİ MODAL ===== -->
    <div x-show="showStok" x-cloak class="modal-backdrop" @click.self="showStok=false">
        <div class="modal-box max-w-sm">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Stok Girişi</h3>
                <button @click="showStok=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body space-y-4">
                <div class="bg-slate-50 rounded-lg p-3 text-sm">
                    <p class="font-medium text-slate-800" x-text="stokForm.parca_adi"></p>
                    <p class="text-slate-400 mt-0.5">Mevcut Stok: <span class="font-semibold text-slate-700" x-text="stokForm.mevcutStok"></span></p>
                </div>
                <div>
                    <label class="form-label">Eklenecek Miktar</label>
                    <input type="number" class="form-input" min="1" x-model="stokForm.miktar">
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showStok=false">İptal</button>
                    <button class="btn btn-success" @click="saveStokGirisi()" :disabled="saving">
                        <i class="fas fa-plus mr-1"></i> Stok Ekle
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function stokApp() {
    return {
        parcalar: [], loading: false, search: '', sadecekritik: false, sadececihaz: false,
        showForm: false, showStok: false, editId: null, saving: false,
        form: { parca_adi: '', marka: '', birim_fiyat: 0, stok_miktari: 0, kritik_stok_seviyesi: 5, tedarikci: '', is_cihaz: false },
        stokForm: { id: null, parca_adi: '', mevcutStok: 0, miktar: 1 },

        get filtered() {
            return this.parcalar.filter(p => {
                const q = this.search.toLowerCase();
                const match = !q || p.parca_adi.toLowerCase().includes(q) || (p.marka || '').toLowerCase().includes(q);
                const kritik = !this.sadecekritik || p.stok_miktari <= p.kritik_stok_seviyesi;
                const cihaz = !this.sadececihaz || p.is_cihaz == 1;
                return match && kritik && cihaz;
            });
        },

        get kritikCount() { return this.parcalar.filter(p => p.stok_miktari <= p.kritik_stok_seviyesi).length; },
        get stokDegeri() { return this.parcalar.reduce((s, p) => s + (p.stok_miktari * (p.birim_fiyat || 0)), 0); },

        async init() { await this.loadParcalar(); },

        async loadParcalar() {
            this.loading = true;
            try { this.parcalar = await api('api/stok.php'); } catch(e) {} finally { this.loading = false; }
        },

        openAddModal() {
            this.editId = null;
            this.form = { parca_adi: '', marka: '', birim_fiyat: 0, stok_miktari: 0, kritik_stok_seviyesi: 5, tedarikci: '', is_cihaz: false };
            this.showForm = true;
        },

        editParca(p) {
            this.editId = p.id;
            this.form = {
                parca_adi: p.parca_adi, marka: p.marka || '',
                birim_fiyat: p.birim_fiyat || 0, stok_miktari: p.stok_miktari || 0,
                kritik_stok_seviyesi: p.kritik_stok_seviyesi || 5,
                tedarikci: p.tedarikci || '',
                is_cihaz: p.is_cihaz == 1,
            };
            this.showForm = true;
        },

        stokGirisi(p) {
            this.stokForm = { id: p.id, parca_adi: p.parca_adi, mevcutStok: p.stok_miktari, miktar: 1 };
            this.showStok = true;
        },

        async saveParca() {
            this.saving = true;
            try {
                if (this.editId) {
                    await api(`api/stok.php?id=${this.editId}`, { method: 'PUT', body: this.form });
                    showToast('Güncellendi.', 'success');
                } else {
                    await api('api/stok.php', { method: 'POST', body: this.form });
                    showToast('Eklendi.', 'success');
                }
                this.showForm = false;
                await this.loadParcalar();
            } catch(e) {} finally { this.saving = false; }
        },

        async saveStokGirisi() {
            this.saving = true;
            try {
                await api(`api/stok.php?id=${this.stokForm.id}`, { method: 'PUT', body: { stok_artis: this.stokForm.miktar } });
                showToast(`${this.stokForm.miktar} adet stok eklendi.`, 'success');
                this.showStok = false;
                await this.loadParcalar();
            } catch(e) {} finally { this.saving = false; }
        },

        async deleteParca(p) {
            if (!confirm(`"${p.parca_adi}" silinsin mi?`)) return;
            try {
                await api(`api/stok.php?id=${p.id}`, { method: 'DELETE' });
                showToast('Silindi.', 'success');
                await this.loadParcalar();
            } catch(e) {}
        },

        formatCurrency,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
