<?php
$pageTitle  = 'Servisler';
$activePage = 'servisler';
include __DIR__ . '/layout/header.php';
?>

<style>
@media (max-width: 640px) {
    .service-operation-row {
        grid-template-columns: 1fr !important;
    }
}
</style>

<div x-data="servislerApp()" x-init="init()">

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" placeholder="Müşteri ara..."
                       class="form-input pl-9 w-52"
                       x-model="search"
                       @input.debounce.300ms="loadServisler()">
            </div>
            <select class="form-select w-44" x-model="tipFiltre" @change="loadServisler()">
                <option value="">Tüm Tipler</option>
                <option value="ariza">Arıza</option>
                <option value="periyodik_bakim">Periyodik Bakım</option>
            </select>
            <select class="form-select w-40" x-model="odemeFiltre" @change="loadServisler()">
                <option value="">Tüm Ödemeler</option>
                <option value="odenmedi">Ödenmedi</option>
                <option value="kismi">Kısmi Ödendi</option>
                <option value="odendi">Ödendi</option>
            </select>
            <select class="form-select w-44" x-model="sirala" @change="loadServisler()">
                <option value="tarih_desc">Tarih: Yeni → Eski</option>
                <option value="tarih_asc">Tarih: Eski → Yeni</option>
            </select>
            <span class="text-sm text-slate-500" x-text="`${servisler.length} kayıt`"></span>
        </div>
        <button class="btn btn-primary" @click="openAddModal()">
            <i class="fas fa-plus"></i> Yeni Servis
        </button>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-center">Sıra</th>
                        <th>Müşteri</th>
                        <th>Servis Tipi</th>
                        <th>Tarih</th>
                        <th>Tutar</th>
                        <th>Ödeme</th>
                        <th>Kalan</th>
                        <th class="text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="8" class="text-center py-12 text-slate-400">
                            <div class="spinner mx-auto mb-2"></div>Yükleniyor...
                        </td></tr>
                    </template>
                    <template x-if="!loading && servisler.length === 0">
                        <tr><td colspan="8" class="text-center py-12 text-slate-400">
                            <i class="fas fa-wrench text-3xl mb-2 block text-slate-200"></i>
                            Servis kaydı bulunamadı
                        </td></tr>
                    </template>
                    <template x-for="row in groupedServisRows" :key="row.key">
                        <tr :class="row.type === 'month' ? 'bg-slate-100' : ''">
                            <td x-show="row.type === 'month'" colspan="8" class="py-3 px-4 border-y border-slate-200">
                                <div class="flex items-center gap-3">
                                    <span class="h-px bg-slate-300 flex-1"></span>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500" x-text="row.label"></span>
                                    <span class="h-px bg-slate-300 flex-1"></span>
                                </div>
                            </td>
                            <td x-show="row.type === 'service'" class="text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold"
                                      x-text="monthlySequenceNo(row.item)"></span>
                            </td>
                            <td x-show="row.type === 'service'">
                                <p class="font-medium text-slate-800" x-text="row.item?.musteri_adi"></p>
                                <p class="text-xs text-slate-400" x-text="row.item?.telefon || ''"></p>
                            </td>
                            <td x-show="row.type === 'service'">
                                <span class="badge"
                                      :class="row.item?.servis_tipi === 'ariza' ? 'badge-red' : 'badge-blue'"
                                      x-text="formatTip(row.item?.servis_tipi)">
                                </span>
                            </td>
                            <td x-show="row.type === 'service'" class="text-sm text-slate-600" x-text="formatDate(row.item?.tamamlanma_tarihi)"></td>
                            <td x-show="row.type === 'service'" class="font-semibold text-slate-700" x-text="formatCurrency(row.item?.toplam_tutar)"></td>
                            <td x-show="row.type === 'service'">
                                <span class="badge"
                                      :class="odemeBadgeClass(row.item?.odeme_durumu)"
                                      x-text="odemeBadgeText(row.item?.odeme_durumu)">
                                </span>
                            </td>
                            <td x-show="row.type === 'service'">
                                <span class="text-sm font-medium"
                                      :class="(row.item?.toplam_tutar - row.item?.odenen_tutar) > 0 ? 'text-red-600' : 'text-emerald-600'"
                                      x-text="formatCurrency(Math.max(0, (row.item?.toplam_tutar || 0) - (row.item?.odenen_tutar || 0)))">
                                </span>
                            </td>
                            <td x-show="row.type === 'service'">
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="viewServis(row.item)" title="Detay">
                                        <i class="fas fa-eye text-slate-500"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="editServis(row.item)" title="Düzenle">
                                        <i class="fas fa-pen text-blue-500"></i>
                                    </button>
                                    <button x-show="row.item?.odeme_durumu !== 'odendi'"
                                            class="btn btn-sm btn-success btn-icon" @click="openTahsilat(row.item)" title="Tahsilat Al">
                                        <i class="fas fa-money-bill-wave text-emerald-600"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-icon" @click="deleteServis(row.item)" title="Sil">
                                        <i class="fas fa-trash text-red-500"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== ADD / EDIT SERVICE MODAL ===== -->
    <div x-show="showAdd" x-cloak class="modal-backdrop" @click.self="showAdd=false">
        <div class="modal-box max-w-2xl">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="editId ? 'Servis Kaydını Düzenle' : 'Yeni Servis Kaydı'"></h3>
                <button @click="showAdd=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveServis()" class="modal-body space-y-4" novalidate>
                <!-- Müşteri seçimi -->
                <div>
                    <label class="form-label">Müşteri <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" class="form-input pl-9"
                               placeholder="Müşteri ara..."
                               x-model="musteriSearch"
                               @input.debounce.250ms="searchMusteriler()"
                               @focus="showMusteriList = musteriOneri.length > 0"
                               autocomplete="off">
                    </div>
                    <div x-show="showMusteriList && musteriOneri.length > 0"
                         class="mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-40 overflow-y-auto z-10 relative">
                        <template x-for="m in musteriOneri" :key="m.id">
                            <div class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm flex items-center justify-between"
                                 @click="selectMusteri(m)">
                                <span x-text="`${m.ad} ${m.soyad}`" class="font-medium"></span>
                                <span x-text="m.telefon || ''" class="text-slate-400 text-xs"></span>
                            </div>
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-2" x-show="selectedMusteriler.length > 0">
                        <template x-for="m in selectedMusteriler" :key="m.id">
                            <span class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 border border-blue-100 rounded-full px-3 py-1 text-sm font-medium">
                                <i class="fas fa-check-circle text-xs"></i>
                                <span x-text="`${m.ad} ${m.soyad}`"></span>
                                <button type="button" class="text-blue-400 hover:text-red-500"
                                        x-show="!editId"
                                        @click="removeMusteri(m.id)">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </span>
                        </template>
                    </div>
                    <p class="text-xs text-slate-400 mt-2" x-show="!editId && selectedMusteriler.length > 1">
                        <i class="fas fa-layer-group mr-1"></i>
                        <span x-text="`${selectedMusteriler.length} müşteri için ayrı servis kaydı oluşturulacak.`"></span>
                    </p>
                </div>

                <!-- Servis tipi & tarih -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Servis Tipi <span class="text-red-500">*</span></label>
                        <select class="form-select" x-model="form.servis_tipi">
                            <option value="">Seçiniz...</option>
                            <option value="ariza">Arıza</option>
                            <option value="periyodik_bakim">Periyodik Bakım</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Servis Tarihi</label>
                        <input type="date" class="form-input" x-model="form.servis_tarihi">
                    </div>
                </div>

                <!-- Periyot (sadece periyodik bakımda) -->
                <div x-show="form.servis_tipi === 'periyodik_bakim'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                        <label class="form-label text-blue-700">
                            <i class="fas fa-calendar-check mr-1"></i>
                            Bir Sonraki Bakım Periyodu
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="number" class="form-input w-28 text-center text-lg font-semibold"
                                   min="1" max="60" x-model="form.periyot_ay"
                                   placeholder="6">
                            <span class="text-slate-600 font-medium">ay sonra</span>
                            <div class="flex gap-1 ml-auto">
                                <button type="button" class="btn btn-sm btn-secondary" @click="form.periyot_ay = 3">3 ay</button>
                                <button type="button" class="btn btn-sm btn-secondary" @click="form.periyot_ay = 6">6 ay</button>
                                <button type="button" class="btn btn-sm btn-secondary" @click="form.periyot_ay = 12">12 ay</button>
                            </div>
                        </div>
                        <p class="text-xs text-blue-500 mt-2" x-show="form.periyot_ay > 0">
                            <i class="fas fa-info-circle mr-1"></i>
                            Sonraki bakım: <strong x-text="nextBakimTarih()"></strong>
                        </p>
                    </div>
                </div>

                <!-- İşlemler -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0">Yapılan İşlemler</label>
                        <button type="button" class="btn btn-sm btn-secondary" @click="addIslem()">
                            <i class="fas fa-plus text-xs"></i> Ekle
                        </button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(islem, i) in form.islemler" :key="i">
                            <div class="service-operation-row grid gap-2 items-start" style="grid-template-columns:minmax(220px,1fr) 9rem 2.5rem;">
                                <div class="min-w-0">
                                    <select class="form-select" x-model="islem.islem" @change="applyStandartFiyat(islem)">
                                        <option value="">İşlem seçiniz...</option>
                                        <template x-for="si in standartIslemler" :key="si.id">
                                            <option :value="si.islem_adi" x-text="si.islem_adi"></option>
                                        </template>
                                        <option value="__manual">Manuel işlem...</option>
                                    </select>
                                    <input x-show="islem.islem === '__manual'" x-transition
                                           type="text" class="form-input mt-2"
                                           placeholder="Manuel işlem adı"
                                           x-model="islem.manuel_islem">
                                </div>
                                <input type="number" class="form-input" placeholder="Tutar (₺)" step="0.01" min="0"
                                       x-model="islem.tutar" @input="calcTotal()">
                                <button type="button" class="btn btn-danger btn-icon" @click="form.islemler.splice(i,1); calcTotal()">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Parçalar -->
                <div x-show="!editId">
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0">Kullanılan Parçalar</label>
                        <button type="button" class="btn btn-sm btn-secondary" @click="addParca()">
                            <i class="fas fa-plus text-xs"></i> Manuel Ekle
                        </button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(p, i) in form.parcalar" :key="i">
                            <div class="flex gap-2 items-center rounded-xl px-2 py-1"
                                 :class="p.dahil ? 'bg-slate-50 border border-dashed border-slate-200' : ''">

                                <!-- DAHİL PARCA -->
                                <template x-if="p.dahil">
                                    <div class="flex gap-2 items-center flex-1">
                                        <div class="flex-1 flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1 text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-semibold flex-shrink-0">
                                                <i class="fas fa-link text-xs"></i> Dahil
                                            </span>
                                            <span class="text-sm text-slate-600 font-medium" x-text="p.parca_adi"></span>
                                        </div>
                                        <input type="number" class="form-input w-20 text-center text-slate-400" min="1"
                                               x-model="p.miktar" @input="calcTotal()" title="Adet">
                                    </div>
                                </template>

                                <!-- NORMAL PARCA -->
                                <template x-if="!p.dahil">
                                    <div class="flex gap-2 items-center flex-1">
                                        <select class="form-select flex-1" x-model="p.parca_id" @change="setParcaFiyat(p)">
                                            <option value="">Parça seçiniz...</option>
                                            <template x-for="pk in stoklar" :key="pk.id">
                                                <option :value="pk.id" x-text="`${pk.parca_adi} (Stok: ${pk.stok_miktari})`"></option>
                                            </template>
                                        </select>
                                        <input type="number" class="form-input w-20 text-center" placeholder="Adet" min="1"
                                               x-model="p.miktar" @input="calcTotal()">
                                        <input type="number" class="form-input w-28" placeholder="Fiyat (₺)" step="0.01"
                                               x-model="p.birim_fiyat" @input="calcTotal()">
                                    </div>
                                </template>

                                <button type="button" class="btn btn-danger btn-icon flex-shrink-0"
                                        @click="form.parcalar.splice(i,1); calcTotal()">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Toplam & Notlar -->
                <div class="grid grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="form-label">Notlar</label>
                        <textarea class="form-textarea" rows="2" x-model="form.notlar"></textarea>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                        <p class="text-xs text-blue-400 uppercase font-semibold tracking-wide mb-1">Toplam Tutar</p>
                        <p class="text-2xl font-bold text-blue-700" x-text="formatCurrency(form.toplam_tutar)"></p>
                        <button type="button" class="text-xs text-blue-400 hover:text-blue-600 mt-1" @click="calcTotal()">
                            <i class="fas fa-sync-alt mr-1"></i>Hesapla
                        </button>
                    </div>
                </div>

                <div x-show="!editId" class="border border-emerald-100 bg-emerald-50 rounded-xl p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" class="mt-1" x-model="form.tahsilat_al" @change="form.tahsilat_al && fillFullPayment()">
                        <span>
                            <span class="block font-semibold text-slate-800 text-sm">Tahsilatı şimdi al</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Servis kaydıyla birlikte ödeme de işlensin. Çoklu müşteri seçildiyse her servis için aynı tutarda tahsilat oluşturulur.
                            </span>
                        </span>
                    </label>
                    <div x-show="form.tahsilat_al" x-transition class="grid grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="form-label">Tahsilat Tutarı</label>
                            <input type="number" class="form-input" step="0.01"
                                   x-model="form.tahsilat_tutar">
                        </div>
                        <div>
                            <label class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" x-model="form.odeme_yontemi">
                                <option value="nakit">Nakit</option>
                                <option value="kart">Kredi/Banka Kartı</option>
                                <option value="havale">Havale / EFT</option>
                                <option value="cek">Çek</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="btn btn-secondary w-full" @click="fillFullPayment()">
                                <i class="fas fa-coins text-xs"></i> Tamamını Al
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showAdd=false">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <span x-text="editId ? 'Güncelle' : 'Kaydet'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== DETAIL MODAL ===== -->
    <div x-show="showDetail" x-cloak class="modal-backdrop" @click.self="showDetail=false">
        <div class="modal-box max-w-xl" x-show="detail">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Servis Detayı</h3>
                <button @click="showDetail=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400 mb-1">Müşteri</p>
                        <p class="font-medium" x-text="`${detail?.ad || ''} ${detail?.soyad || ''}`"></p>
                        <p class="text-xs text-slate-400" x-text="detail?.telefon || ''"></p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400 mb-1">Servis Tipi / Tarih</p>
                        <p class="font-medium" x-text="formatTip(detail?.servis_tipi)"></p>
                        <p class="text-xs text-slate-400" x-text="formatDate(detail?.tamamlanma_tarihi)"></p>
                    </div>
                </div>

                <!-- İşlemler -->
                <div x-show="detail?.islemler?.length">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Yapılan İşlemler</p>
                    <div class="space-y-1.5">
                        <template x-for="ism in (detail?.islemler || [])" :key="ism.id">
                            <div class="flex justify-between items-center bg-slate-50 rounded-lg px-3 py-2 text-sm">
                                <span x-text="ism.islem"></span>
                                <span class="font-semibold text-slate-700" x-text="formatCurrency(ism.tutar)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Parçalar -->
                <div x-show="detail?.parcalar?.length">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Kullanılan Parçalar / Maliyet</p>
                    <div class="space-y-1.5">
                        <template x-for="pr in (detail?.parcalar || [])" :key="pr.id">
                            <div class="flex justify-between items-center bg-slate-50 rounded-lg px-3 py-2 text-sm">
                                <span x-text="`${pr.parca_adi} × ${pr.miktar}`"></span>
                                <span class="font-semibold text-slate-700" x-text="'Maliyet: ' + formatCurrency(pr.birim_fiyat * pr.miktar)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Ödeme Özeti -->
                <div class="rounded-xl border overflow-hidden"
                     :class="detail?.odeme_durumu === 'odendi' ? 'border-emerald-200' : 'border-red-200'">
                    <div class="px-4 py-3 flex items-center justify-between"
                         :class="detail?.odeme_durumu === 'odendi' ? 'bg-emerald-50' : (detail?.odeme_durumu === 'kismi' ? 'bg-yellow-50' : 'bg-red-50')">
                        <span class="font-semibold text-slate-700">Toplam Tutar</span>
                        <span class="text-2xl font-bold text-slate-800" x-text="formatCurrency(detail?.toplam_tutar)"></span>
                    </div>
                    <div class="px-4 py-2 flex items-center justify-between bg-white text-sm">
                        <span class="text-slate-500">Ödenen</span>
                        <span class="font-semibold text-emerald-600" x-text="formatCurrency(detail?.odenen_tutar)"></span>
                    </div>
                    <div class="px-4 py-2 flex items-center justify-between bg-white text-sm border-t border-slate-100">
                        <span class="text-slate-500">Kalan</span>
                        <span class="font-bold text-red-600"
                              x-text="formatCurrency(Math.max(0,(detail?.toplam_tutar||0)-(detail?.odenen_tutar||0)))"></span>
                    </div>
                </div>

                <!-- Tahsilat Geçmişi -->
                <div x-show="detail?.tahsilatlar?.length">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Tahsilat Geçmişi</p>
                    <div class="space-y-1.5">
                        <template x-for="th in (detail?.tahsilatlar || [])" :key="th.id">
                            <div class="flex justify-between items-center bg-emerald-50 rounded-lg px-3 py-2 text-sm">
                                <div>
                                    <span class="font-medium text-emerald-700" x-text="formatCurrency(th.tutar)"></span>
                                    <span class="text-slate-400 ml-2" x-text="formatOdemeYontemi(th.odeme_yontemi)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-400 text-xs" x-text="formatDate(th.tahsilat_tarihi)"></span>
                                    <button type="button" class="btn btn-sm btn-danger py-0.5 px-2 text-xs"
                                            @click="deleteTahsilat(th.id)">Sil</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="detail?.notlar">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Notlar</p>
                    <p class="text-sm text-slate-600 bg-slate-50 rounded-lg p-3" x-text="detail?.notlar"></p>
                </div>
            </div>
            <div class="modal-footer" x-show="detail?.odeme_durumu !== 'odendi'">
                <button class="btn btn-success" @click="showDetail=false; openTahsilat(detail)">
                    <i class="fas fa-money-bill-wave"></i> Tahsilat Al
                </button>
            </div>
        </div>
    </div>

    <!-- ===== TAHSİLAT MODAL ===== -->
    <div x-show="showTahsilat" x-cloak class="modal-backdrop" @click.self="showTahsilat=false">
        <div class="modal-box max-w-md">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Tahsilat Al</h3>
                <button @click="showTahsilat=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveTahsilat()" class="modal-body space-y-4" novalidate>
                <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                    <p class="text-xs text-slate-500 mb-1">Müşteri / Servis</p>
                    <p class="font-semibold text-slate-800" x-text="tahsilatForm.musteriAdi"></p>
                    <div class="flex justify-between mt-2 text-sm">
                        <span class="text-slate-500">Kalan Borç:</span>
                        <span class="font-bold text-red-600" x-text="formatCurrency(tahsilatForm.kalan)"></span>
                    </div>
                </div>

                <div>
                    <label class="form-label">Tahsilat Tutarı <span class="text-red-500">*</span></label>
                    <input type="number" class="form-input" step="0.01"
                           x-model="tahsilatForm.tutar">
                    <div class="flex gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-secondary flex-1"
                                @click="tahsilatForm.tutar = tahsilatForm.kalan">
                            Tamamını Al
                        </button>
                    </div>
                </div>

                <div>
                    <label class="form-label">Ödeme Yöntemi</label>
                    <select class="form-select" x-model="tahsilatForm.odeme_yontemi">
                        <option value="nakit">Nakit</option>
                        <option value="kart">Kredi/Banka Kartı</option>
                        <option value="havale">Havale / EFT</option>
                        <option value="cek">Çek</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Tahsilat Tarihi</label>
                    <input type="date" class="form-input" x-model="tahsilatForm.tahsilat_tarihi">
                </div>

                <div>
                    <label class="form-label">Not</label>
                    <input type="text" class="form-input" x-model="tahsilatForm.notlar" placeholder="İsteğe bağlı">
                </div>

                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showTahsilat=false">İptal</button>
                    <button type="submit" class="btn btn-success" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <i class="fas fa-check mr-1"></i> Tahsilat Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function servislerApp() {
    return {
        servisler: [], loading: false,
        search: '', tipFiltre: '', odemeFiltre: '', sirala: 'tarih_desc',
        showAdd: false, showDetail: false, showTahsilat: false,
        saving: false, editId: null,
        detail: null,
        standartIslemler: [], stoklar: [],
        musteriSearch: '', musteriOneri: [], showMusteriList: false, selectedMusteriler: [],
        selectedMusteriName: '',
        form: {
            musteri_id: '', servis_tipi: '', servis_tarihi: new Date().toISOString().split('T')[0],
            islemler: [], parcalar: [], notlar: '', toplam_tutar: 0, periyot_ay: 6,
            tahsilat_al: false, tahsilat_tutar: 0, odeme_yontemi: 'nakit',
        },
        varsayilanPeriyot: 6,
        tahsilatForm: {
            musteri_id: '', kaynak_id: '', kaynak_tip: 'servis',
            musteriAdi: '', kalan: 0, tutar: 0,
            odeme_yontemi: 'nakit', tahsilat_tarihi: new Date().toISOString().split('T')[0], notlar: '',
        },

        get groupedServisRows() {
            const rows = [];
            let currentMonth = null;
            this.servisler.forEach(s => {
                const monthKey = this.monthKey(s.tamamlanma_tarihi || s.created_at);
                if (monthKey !== currentMonth) {
                    currentMonth = monthKey;
                    rows.push({ type: 'month', key: `month-${monthKey}`, label: this.monthLabel(s.tamamlanma_tarihi || s.created_at) });
                }
                rows.push({ type: 'service', key: `service-${s.id}`, item: s });
            });
            return rows;
        },

        monthlySequenceNo(service) {
            if (!service) return '';
            const month = this.monthKey(service.tamamlanma_tarihi || service.created_at);
            const sameMonth = this.servisler
                .filter(s => this.monthKey(s.tamamlanma_tarihi || s.created_at) === month)
                .slice()
                .sort((a, b) => {
                    const dateA = String(a.tamamlanma_tarihi || a.created_at || '');
                    const dateB = String(b.tamamlanma_tarihi || b.created_at || '');
                    if (dateA === dateB) return (Number(a.id) || 0) - (Number(b.id) || 0);
                    return dateA.localeCompare(dateB);
                });
            const index = sameMonth.findIndex(s => String(s.id) === String(service.id));
            return index >= 0 ? index + 1 : '';
        },

        async init() {
            await Promise.all([this.loadServisler(), this.loadStandartIslemler(), this.loadStoklar(), this.loadVarsayilanPeriyot()]);
        },

        async loadVarsayilanPeriyot() {
            try {
                const ayarlar = await api('api/ayarlar.php');
                // getAll() { anahtar: deger } map döndürür
                this.varsayilanPeriyot = parseInt(ayarlar['varsayilan_bakim_periyodu']) || 6;
                this.form.periyot_ay  = this.varsayilanPeriyot;
            } catch(e) {}
        },

        async loadServisler() {
            this.loading = true;
            try {
                const p = new URLSearchParams();
                if (this.search)       p.set('search', this.search);
                if (this.tipFiltre)    p.set('servis_tipi', this.tipFiltre);
                if (this.odemeFiltre)  p.set('odeme_durumu', this.odemeFiltre);
                if (this.sirala)       p.set('sirala', this.sirala);
                this.servisler = await api(`api/servisler.php?${p}`);
            } catch(e) {} finally { this.loading = false; }
        },

        async loadStandartIslemler() {
            try { this.standartIslemler = await api('api/standart_islemler.php'); } catch(e) {}
        },

        async loadStoklar() {
            try { this.stoklar = await api('api/stok.php'); } catch(e) {}
        },

        async searchMusteriler() {
            if (this.musteriSearch.length < 1) { this.musteriOneri = []; return; }
            try {
                this.musteriOneri = await api(`api/musteriler.php?search=${encodeURIComponent(this.musteriSearch)}`);
                this.showMusteriList = true;
            } catch(e) {}
        },

        selectMusteri(m) {
            if (this.editId) {
                this.selectedMusteriler = [m];
            } else if (!this.selectedMusteriler.some(x => String(x.id) === String(m.id))) {
                this.selectedMusteriler.push(m);
            }
            this.form.musteri_id     = this.selectedMusteriler[0]?.id || '';
            this.selectedMusteriName = this.selectedMusteriler.map(x => `${x.ad} ${x.soyad}`).join(', ');
            this.musteriSearch       = '';
            this.showMusteriList     = false;
            // Müşterinin mevcut periyodunu doldur, yoksa varsayılan
            this.form.periyot_ay = parseInt(m.periyot_ay) || this.varsayilanPeriyot;
        },

        removeMusteri(id) {
            this.selectedMusteriler = this.selectedMusteriler.filter(m => String(m.id) !== String(id));
            this.form.musteri_id = this.selectedMusteriler[0]?.id || '';
            this.selectedMusteriName = this.selectedMusteriler.map(x => `${x.ad} ${x.soyad}`).join(', ');
        },

        openAddModal() {
            this.editId = null;
            this.form = {
                musteri_id: '', servis_tipi: '', servis_tarihi: new Date().toISOString().split('T')[0],
                islemler: [], parcalar: [], notlar: '', toplam_tutar: 0,
                periyot_ay: this.varsayilanPeriyot,
                tahsilat_al: false, tahsilat_tutar: 0, odeme_yontemi: 'nakit',
            };
            this.musteriSearch = '';
            this.selectedMusteriName = '';
            this.selectedMusteriler = [];
            this.showAdd = true;
        },

        async editServis(s) {
            try {
                const d = await api(`api/servisler.php?id=${s.id}`);
                this.editId = s.id;
                this.form = {
                    musteri_id: d.musteri_id,
                    servis_tipi: d.servis_tipi,
                    servis_tarihi: d.tamamlanma_tarihi || new Date().toISOString().split('T')[0],
                    islemler: d.islemler || [],
                    parcalar: [],
                    notlar: d.notlar || '',
                    toplam_tutar: d.toplam_tutar || 0,
                    tahsilat_al: false, tahsilat_tutar: 0, odeme_yontemi: 'nakit',
                };
                this.musteriSearch = `${d.ad} ${d.soyad}`;
                this.selectedMusteriName = `${d.ad} ${d.soyad}`;
                this.selectedMusteriler = [{ id: d.musteri_id, ad: d.ad, soyad: d.soyad, telefon: d.telefon }];
                this.showAdd = true;
            } catch(e) {}
        },

        addIslem() { this.form.islemler.push({ islem: '', tutar: 0 }); },
        addParca() { this.form.parcalar.push({ parca_id: '', miktar: 1, birim_fiyat: 0, dahil: false }); },

        applyStandartFiyat(islem) {
            const found = this.standartIslemler.find(si => si.islem_adi === islem.islem);
            if (found && found.varsayilan_fiyat > 0) islem.tutar = found.varsayilan_fiyat;
            this._refreshDahilParcalar();
            this.calcTotal();
        },

        // Seçili tüm işlemlerin dahil parçalarını yeniden hesapla
        _refreshDahilParcalar() {
            // Önce mevcut dahil parçaları kaldır
            this.form.parcalar = this.form.parcalar.filter(p => !p.dahil);
            // Seçili her işlem için dahil parçaları ekle
            this.form.islemler.forEach(islem => {
                const si = this.standartIslemler.find(s => s.islem_adi === islem.islem);
                if (!si || !si.parcalar || si.parcalar.length === 0) return;
                si.parcalar.forEach(sp => {
                    // Aynı parça zaten dahil olarak eklenmediyse ekle
                    const zatenVar = this.form.parcalar.some(p => p.dahil && p.parca_id == sp.parca_id);
                    if (!zatenVar) {
                        this.form.parcalar.push({
                            parca_id:   sp.parca_id,
                            parca_adi:  sp.parca_adi + (sp.marka ? ' (' + sp.marka + ')' : ''),
                            miktar:     sp.miktar,
                            birim_fiyat: parseFloat(sp.birim_fiyat) || 0,
                            dahil:      true,
                        });
                    }
                });
            });
        },

        setParcaFiyat(p) {
            const found = this.stoklar.find(s => s.id == p.parca_id);
            if (found) p.birim_fiyat = found.birim_fiyat;
            this.calcTotal();
        },

        normalizeIslemler() {
            return this.form.islemler
                .map(i => ({ ...i, islem: i.islem === '__manual' ? (i.manuel_islem || '').trim() : i.islem }))
                .filter(i => i.islem);
        },

        calcTotal() {
            const islemToplam = this.form.islemler.reduce((s, i) => s + (parseFloat(i.tutar) || 0), 0);
            // Dahil parçalar ücrete eklenmez, sadece stoktan düşer
            const parcaToplam = this.form.parcalar
                .filter(p => !p.dahil)
                .reduce((s, p) => s + (parseFloat(p.birim_fiyat) || 0) * (parseInt(p.miktar) || 1), 0);
            this.form.toplam_tutar = +(islemToplam + parcaToplam).toFixed(2);
            if (this.form.tahsilat_al && (!this.form.tahsilat_tutar || parseFloat(this.form.tahsilat_tutar) > this.form.toplam_tutar)) {
                this.fillFullPayment();
            }
        },

        fillFullPayment() {
            this.form.tahsilat_tutar = +(parseFloat(this.form.toplam_tutar || 0)).toFixed(2);
        },

        async saveServis() {
            if (this.selectedMusteriler.length === 0) { showToast('Lütfen müşteri seçiniz.', 'error'); return; }
            if (!this.form.servis_tipi) { showToast('Lütfen servis tipi seçiniz.', 'error'); return; }
            this.calcTotal();
            if (!this.editId && this.form.tahsilat_al) {
                const tahsilatTutar = parseFloat(this.form.tahsilat_tutar || 0);
                if (tahsilatTutar <= 0 || tahsilatTutar > parseFloat(this.form.toplam_tutar || 0)) {
                    showToast('Tahsilat tutarı servis toplamından büyük olamaz.', 'error');
                    return;
                }
            }
            // Dahil parçalar stoktan düşer ve maliyet olarak saklanır; servis toplamına eklenmez.
            const payload = {
                ...this.form,
                musteri_id: this.selectedMusteriler[0]?.id || this.form.musteri_id,
                musteri_ids: this.editId ? [] : this.selectedMusteriler.map(m => m.id),
                islemler: this.normalizeIslemler(),
                parcalar: this.form.parcalar,
                tahsilat: (!this.editId && this.form.tahsilat_al) ? {
                    tutar: parseFloat(this.form.tahsilat_tutar || 0),
                    odeme_yontemi: this.form.odeme_yontemi || 'nakit',
                    tahsilat_tarihi: this.form.servis_tarihi,
                    notlar: 'Servis kaydı sırasında alındı',
                } : null,
            };
            this.saving = true;
            try {
                if (this.editId) {
                    await api(`api/servisler.php?id=${this.editId}`, { method: 'PUT', body: payload });
                    showToast('Servis güncellendi.', 'success');
                } else {
                    await api('api/servisler.php', { method: 'POST', body: payload });
                    showToast(this.selectedMusteriler.length > 1 ? `${this.selectedMusteriler.length} servis kaydedildi.` : 'Servis kaydedildi.', 'success');
                }
                this.showAdd = false;
                await this.loadServisler();
            } catch(e) {} finally { this.saving = false; }
        },

        async viewServis(s) {
            try {
                this.detail = await api(`api/servisler.php?id=${s.id}`);
                this.showDetail = true;
            } catch(e) {}
        },

        openTahsilat(s) {
            const kalan = Math.max(0, (s.toplam_tutar || 0) - (s.odenen_tutar || 0));
            this.tahsilatForm = {
                musteri_id: s.musteri_id || s.id,
                kaynak_id: s.id,
                kaynak_tip: 'servis',
                musteriAdi: s.musteri_adi || `${s.ad} ${s.soyad}`,
                kalan: kalan,
                tutar: kalan,
                odeme_yontemi: 'nakit',
                tahsilat_tarihi: new Date().toISOString().split('T')[0],
                notlar: '',
            };
            this.showTahsilat = true;
        },

        async saveTahsilat() {
            if (!this.tahsilatForm.tutar || this.tahsilatForm.tutar <= 0) {
                showToast('Geçerli bir tutar girin.', 'error'); return;
            }
            this.saving = true;
            try {
                await api('api/tahsilatlar.php', { method: 'POST', body: this.tahsilatForm });
                showToast('Tahsilat kaydedildi.', 'success');
                this.showTahsilat = false;
                await this.loadServisler();
            } catch(e) {} finally { this.saving = false; }
        },

        async deleteTahsilat(id) {
            if (!confirm('Bu tahsilat kaydı silinsin mi? Servis ödeme durumu yeniden hesaplanacak.')) return;
            try {
                await api(`api/tahsilatlar.php?id=${id}`, { method: 'DELETE' });
                showToast('Tahsilat geri alındı.', 'success');
                if (this.detail) {
                    this.detail = await api(`api/servisler.php?id=${this.detail.id}`);
                }
                await this.loadServisler();
            } catch(e) {}
        },

        async deleteServis(s) {
            if (!confirm('Bu servis kaydı silinsin mi?')) return;
            try {
                await api(`api/servisler.php?id=${s.id}`, { method: 'DELETE' });
                showToast('Servis silindi.', 'success');
                await this.loadServisler();
            } catch(e) {}
        },

        nextBakimTarih() {
            const ay = parseInt(this.form.periyot_ay) || 0;
            if (!ay) return '—';
            const base = this.form.servis_tarihi ? new Date(this.form.servis_tarihi) : new Date();
            base.setMonth(base.getMonth() + ay);
            return base.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' });
        },

        monthKey(dateValue) {
            if (!dateValue) return 'tarihsiz';
            const d = new Date(dateValue);
            if (Number.isNaN(d.getTime())) return 'tarihsiz';
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        },

        monthLabel(dateValue) {
            if (!dateValue) return 'Tarihsiz Kayıtlar';
            const d = new Date(dateValue);
            if (Number.isNaN(d.getTime())) return 'Tarihsiz Kayıtlar';
            return d.toLocaleDateString('tr-TR', { month: 'long', year: 'numeric' });
        },

        odemeBadgeClass(d) {
            return d === 'odendi' ? 'badge-green' : d === 'kismi' ? 'badge-yellow' : 'badge-red';
        },
        odemeBadgeText(d) {
            return d === 'odendi' ? 'Ödendi' : d === 'kismi' ? 'Kısmi' : 'Ödenmedi';
        },

        formatDate, formatCurrency, formatTip, formatOdemeYontemi,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
