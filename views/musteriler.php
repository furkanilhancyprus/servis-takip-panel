<?php
$pageTitle  = 'Müşteriler';
$activePage = 'musteriler';
include __DIR__ . '/layout/header.php';
?>

<!-- Leaflet.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    #formMap, #detailMap { z-index: 1; border-radius: 12px; }
    .leaflet-container { font-family: inherit; }
    .map-search-input { position: absolute; top: 10px; left: 10px; right: 10px; z-index: 999; }
</style>

<div x-data="musterilerApp()" x-init="init()">

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Müşteri</p>
            <p class="text-3xl font-bold text-slate-800 mt-1" x-text="stats.toplam || '—'"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Geciken Bakım</p>
            <p class="text-3xl font-bold text-red-600 mt-1" x-text="stats.geciken ?? '—'"></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Yaklaşan Bakım</p>
            <p class="text-3xl font-bold text-amber-500 mt-1" x-text="stats.yaklasan ?? '—'"></p>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" placeholder="İsim, telefon veya e-posta ara..."
                   class="form-input pl-9 w-72"
                   x-model="search" @input.debounce.300ms="loadMusteriler()">
        </div>
        <button class="btn btn-primary" @click="openAddModal()">
            <i class="fas fa-user-plus"></i> Yeni Müşteri
        </button>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>Telefon</th>
                        <th>E-posta</th>
                        <th>Bakım Durumu</th>
                        <th class="text-center">Servis</th>
                        <th>Son Servis</th>
                        <th class="text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400">
                            <div class="spinner mx-auto mb-2"></div>Yükleniyor...
                        </td></tr>
                    </template>
                    <template x-if="!loading && musteriler.length === 0">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400">
                            <i class="fas fa-users text-3xl mb-2 block text-slate-200"></i>
                            Müşteri bulunamadı
                        </td></tr>
                    </template>
                    <template x-for="m in musteriler" :key="m.id">
                        <tr class="cursor-pointer hover:bg-slate-50" @click="viewMusteri(m)">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm font-semibold text-blue-700"
                                              x-text="(m.ad||'')[0]+(m.soyad||'')[0]"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800" x-text="m.ad+' '+m.soyad"></p>
                                        <p class="text-xs text-slate-400 flex items-center gap-1">
                                            <template x-if="m.lat && m.lng">
                                                <i class="fas fa-location-dot text-blue-400"></i>
                                            </template>
                                            <span x-text="m.adres ? m.adres.substring(0,40)+(m.adres.length>40?'…':'') : ''"></span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-600" x-text="m.telefon || '—'"></td>
                            <td class="text-slate-500 text-sm" x-text="m.email || '—'"></td>
                            <td>
                                <span class="badge"
                                      :class="{
                                          'badge-red':   m.bakim_durumu==='gecikmis',
                                          'badge-amber': m.bakim_durumu==='yakin',
                                          'badge-green': m.bakim_durumu==='normal',
                                          'badge-blue':  m.bakim_durumu==='ayarsiz',
                                      }"
                                      x-text="{gecikmis:'Gecikmiş',yakin:'Yaklaşıyor',normal:'Normal',ayarsiz:'Ayarsız'}[m.bakim_durumu]||m.bakim_durumu">
                                </span>
                            </td>
                            <td class="text-center font-semibold text-slate-700" x-text="m.toplam_servis||0"></td>
                            <td class="text-slate-500 text-sm" x-text="m.son_servis_tarihi ? m.son_servis_tarihi.substring(0,10) : '—'"></td>
                            <td @click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-secondary btn-icon" title="Düzenle" @click="editMusteri(m)">
                                        <i class="fas fa-pen text-blue-500 text-xs"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-icon" title="Sil" @click="deleteMusteri(m)">
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
    <div x-show="showForm" x-cloak class="modal-backdrop" @click.self="closeForm()">
        <div class="modal-box max-w-2xl">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="editId ? 'Müşteri Düzenle' : 'Yeni Müşteri'"></h3>
                <button @click="closeForm()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveMusteri()" class="modal-body space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Ad <span class="text-red-500">*</span></label>
                        <input type="text" class="form-input" x-model="form.ad" required>
                    </div>
                    <div>
                        <label class="form-label">Soyad <span class="text-red-500">*</span></label>
                        <input type="text" class="form-input" x-model="form.soyad" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Telefon</label>
                        <input type="tel" class="form-input" x-model="form.telefon">
                    </div>
                    <div>
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-input" x-model="form.email">
                    </div>
                </div>

                <!-- Adres + Harita Toggle -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="form-label mb-0">Adres <span class="text-slate-400 font-normal">(opsiyonel)</span></label>
                        <button type="button"
                                @click="toggleMap()"
                                class="text-xs flex items-center gap-1.5 px-2.5 py-1 rounded-lg border transition"
                                :class="showMap ? 'border-blue-300 bg-blue-50 text-blue-600' : 'border-slate-200 text-slate-500 hover:border-blue-300 hover:text-blue-600'">
                            <i class="fas fa-map-location-dot"></i>
                            <span x-text="showMap ? 'Haritayı Gizle' : 'Haritadan Seç'"></span>
                        </button>
                    </div>
                    <input type="text" class="form-input" x-model="form.adres"
                           placeholder="Adres girebilir ya da haritadan işaretleyebilirsiniz">

                    <!-- Koordinat göstergesi -->
                    <div x-show="form.lat && form.lng" class="mt-1.5 flex items-center gap-1.5 text-xs text-emerald-600">
                        <i class="fas fa-location-dot"></i>
                        <span x-text="'Konum işaretlendi: '+Number(form.lat).toFixed(5)+', '+Number(form.lng).toFixed(5)"></span>
                        <button type="button" @click="clearLocation()" class="ml-1 text-slate-400 hover:text-red-500">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>

                <!-- Harita Paneli -->
                <div x-show="showMap" x-transition class="space-y-2">
                    <!-- Arama -->
                    <div class="flex gap-2">
                        <input type="text" class="form-input flex-1" placeholder="Adres ara (örn: Atatürk Cad. Ankara)..."
                               x-model="mapSearch"
                               @keydown.enter.prevent="searchAddress()">
                        <button type="button" class="btn btn-secondary" @click="searchAddress()" :disabled="geocoding">
                            <span x-show="geocoding" class="spinner w-4 h-4"></span>
                            <i x-show="!geocoding" class="fas fa-search text-sm"></i>
                        </button>
                        <button type="button" class="btn btn-secondary whitespace-nowrap" @click="useCurrentLocation()" :disabled="locating">
                            <span x-show="locating" class="spinner w-4 h-4"></span>
                            <i x-show="!locating" class="fas fa-location-crosshairs text-sm"></i>
                            <span class="hidden sm:inline ml-1">Konumum</span>
                        </button>
                    </div>
                    <p class="text-xs text-slate-400 flex items-center gap-1">
                        <i class="fas fa-info-circle"></i>
                        Harita Kuzey Kıbrıs'tan açılır; konum izni verirseniz bulunduğunuz yeri müşteri adresi olarak işaretleyebilirsiniz.
                    </p>
                    <!-- Map container -->
                    <div id="formMap" style="height:320px;width:100%;"></div>
                </div>

                <div>
                    <label class="form-label">Notlar</label>
                    <textarea class="form-input" rows="2" x-model="form.notlar"></textarea>
                </div>
                <div x-show="!editId" class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" class="mt-1" x-model="form.mevcut_cihaz.aktif">
                        <span>
                            <span class="block font-semibold text-slate-800 text-sm">Müşteride önceden bulunan cihazı ekle</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Bu kayıt satış oluşturmaz, ciroya girmez; sadece müşteri cihaz listesine eklenir.</span>
                        </span>
                    </label>
                    <div x-show="form.mevcut_cihaz.aktif" x-transition class="grid grid-cols-2 gap-4 mt-4">
                        <div class="col-span-2">
                            <label class="form-label">Cihaz seçimi</label>
                            <select class="form-select" x-model="form.mevcut_cihaz.cihaz_id" @change="syncExistingDeviceFields()">
                                <option value="">Katalogdan cihaz seçin...</option>
                                <template x-for="c in cihazlar" :key="c.id">
                                    <option :value="c.id" x-text="[(c.marka||''), (c.model||''), (c.cihaz_adi||'')].filter(Boolean).join(' ')"></option>
                                </template>
                            </select>
                            <p class="text-xs text-slate-400 mt-1" x-show="cihazlar.length === 0">
                                Önce Ayarlar > Ürün / Cihaz Kataloğu bölümünden cihaz tanımlayın.
                            </p>
                            <p class="text-xs text-blue-600 mt-1" x-show="selectedExistingDevice()">
                                <i class="fas fa-check-circle mr-1"></i>
                                <span x-text="selectedExistingDeviceLabel()"></span>
                            </p>
                        </div>
                        <div>
                            <label class="form-label">Seri No</label>
                            <input type="text" class="form-input" x-model="form.mevcut_cihaz.seri_no">
                        </div>
                        <div>
                            <label class="form-label">Kurulum / alış tarihi</label>
                            <input type="date" class="form-input" x-model="form.mevcut_cihaz.kurulum_tarihi">
                        </div>
                        <div class="col-span-2">
                            <label class="form-label">Cihaz notu</label>
                            <textarea class="form-input" rows="2" x-model="form.mevcut_cihaz.notlar"
                                      placeholder="Örn: Yaklaşık 5 yıl önce alındı, garanti yok, periyodik bakım takip edilecek."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="closeForm()">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <span x-text="editId ? 'Güncelle' : 'Kaydet'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== DETAIL MODAL ===== -->
    <div x-show="showDetail" x-cloak class="modal-backdrop" @click.self="closeDetail()">
        <div class="modal-box max-w-3xl" style="max-height:90vh;overflow-y:auto;">
            <div class="modal-header sticky top-0 bg-white z-10 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <span class="font-semibold text-blue-700 text-sm"
                              x-text="detail ? (detail.ad||'')[0]+(detail.soyad||'')[0] : ''"></span>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-800" x-text="detail ? detail.ad+' '+detail.soyad : ''"></h3>
                        <p class="text-xs text-slate-400" x-text="detail ? detail.telefon : ''"></p>
                    </div>
                </div>
                <button @click="closeDetail()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>

            <div class="modal-body space-y-6" x-show="detail">

                <!-- Kişisel bilgiler -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Müşteri Bilgileri</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-50 rounded-lg p-3">
                            <p class="text-xs text-slate-400">Telefon</p>
                            <p class="font-medium text-slate-700 mt-0.5" x-text="detail?.telefon || '—'"></p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3">
                            <p class="text-xs text-slate-400">E-posta</p>
                            <p class="font-medium text-slate-700 mt-0.5 text-sm" x-text="detail?.email || '—'"></p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3" :class="detail?.lat ? '' : 'col-span-2'">
                            <p class="text-xs text-slate-400">Adres</p>
                            <p class="font-medium text-slate-700 mt-0.5 text-sm" x-text="detail?.adres || '—'"></p>
                        </div>
                        <!-- Harita butonu -->
                        <template x-if="detail?.lat && detail?.lng">
                            <div class="bg-slate-50 rounded-lg p-3 flex flex-col justify-between">
                                <p class="text-xs text-slate-400 mb-2">Konum</p>
                                <button type="button"
                                        @click="toggleDetailMap()"
                                        class="btn btn-sm w-full"
                                        :class="showDetailMap ? 'btn-secondary' : 'btn-primary'">
                                    <i class="fas fa-map-location-dot mr-1"></i>
                                    <span x-text="showDetailMap ? 'Haritayı Gizle' : 'Haritada Göster'"></span>
                                </button>
                            </div>
                        </template>
                        <template x-if="detail?.notlar">
                            <div class="bg-amber-50 rounded-lg p-3 col-span-2">
                                <p class="text-xs text-amber-500 font-semibold">Not</p>
                                <p class="text-slate-700 text-sm mt-0.5" x-text="detail.notlar"></p>
                            </div>
                        </template>
                    </div>

                    <!-- Detail Map -->
                    <div x-show="showDetailMap" x-transition class="mt-3 rounded-xl overflow-hidden border border-slate-200">
                        <div id="detailMap" style="height:260px;width:100%;"></div>
                        <div class="bg-slate-50 px-3 py-2 text-xs text-slate-500 flex items-center gap-2">
                            <i class="fas fa-location-dot text-blue-500"></i>
                            <span x-text="detail ? Number(detail.lat).toFixed(5)+', '+Number(detail.lng).toFixed(5) : ''"></span>
                            <a :href="detail ? 'https://www.openstreetmap.org/?mlat='+detail.lat+'&mlon='+detail.lng+'&zoom=16' : '#'"
                               target="_blank"
                               class="ml-auto text-blue-500 hover:underline flex items-center gap-1">
                                <i class="fas fa-external-link-alt"></i> OpenStreetMap'te Aç
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Periyodik Bakım -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Periyodik Bakım</h4>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <template x-if="detail?.bakim_aktif">
                            <div class="grid grid-cols-3 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-slate-400">Periyot</p>
                                    <p class="font-medium text-slate-700" x-text="(detail.periyot_ay||6)+' Ay'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">Son Bakım</p>
                                    <p class="font-medium text-slate-700" x-text="detail.son_bakim_tarihi ? detail.son_bakim_tarihi.substring(0,10) : '—'"></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">Sonraki Bakım</p>
                                    <p class="font-medium text-slate-700" x-text="detail.sonraki_bakim_tarihi ? detail.sonraki_bakim_tarihi.substring(0,10) : '—'"></p>
                                </div>
                            </div>
                        </template>
                        <template x-if="!detail?.bakim_aktif">
                            <p class="text-sm text-slate-400">Periyodik bakım ayarlanmamış.</p>
                        </template>
                    </div>
                </div>

                <!-- Mevcut Cihazlar -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">
                        Mevcut Cihazlar
                        <span class="ml-2 bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full text-xs font-semibold"
                              x-text="(detail?.cihazlar||[]).length"></span>
                    </h4>
                    <template x-if="!detail?.cihazlar || detail.cihazlar.length === 0">
                        <div class="bg-slate-50 rounded-xl p-4 text-center text-sm text-slate-400">
                            <i class="fas fa-microchip text-2xl mb-1 block text-slate-200"></i>
                            Bu müşteriye kayıtlı cihaz yok.
                        </div>
                    </template>
                    <template x-if="detail?.cihazlar && detail.cihazlar.length > 0">
                        <div class="space-y-2">
                            <template x-for="cihaz in detail.cihazlar" :key="cihaz.id">
                                <div class="flex items-start justify-between gap-3 p-3 bg-indigo-50 rounded-xl border border-indigo-100">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-microchip text-indigo-600 text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-slate-800 text-sm"
                                               x-text="[(cihaz.marka||''), (cihaz.model||''), (cihaz.cihaz_adi||'Cihaz')].filter(Boolean).join(' ')"></p>
                                            <p class="text-xs text-slate-500 mt-0.5"
                                               x-text="(cihaz.kurulum_tarihi ? 'Kurulum: '+cihaz.kurulum_tarihi.substring(0,10) : 'Kurulum tarihi yok') + (cihaz.seri_no ? ' · Seri: '+cihaz.seri_no : '')"></p>
                                            <p class="text-xs text-slate-500 mt-0.5 truncate" x-show="cihaz.notlar" x-text="cihaz.notlar"></p>
                                        </div>
                                    </div>
                                    <span class="badge" :class="cihaz.satis_id ? 'badge-green' : 'badge-blue'"
                                          x-text="cihaz.satis_id ? 'Satıştan geldi' : 'Mevcut cihaz'"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Satışlar -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">
                        Satışlar
                        <span class="ml-2 bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full text-xs font-semibold"
                              x-text="(detail?.satislar||[]).length"></span>
                    </h4>
                    <template x-if="!detail?.satislar || detail.satislar.length === 0">
                        <div class="bg-slate-50 rounded-xl p-4 text-center text-sm text-slate-400">
                            <i class="fas fa-box-open text-2xl mb-1 block text-slate-200"></i>
                            Bu müşteriye henüz satış yapılmamış.
                        </div>
                    </template>
                    <template x-if="detail?.satislar && detail.satislar.length > 0">
                        <div class="space-y-3">
                            <template x-for="satis in detail.satislar" :key="satis.id">
                                <div class="border border-slate-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-3 bg-slate-50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                                 :class="satis.cihaz_adi ? 'bg-purple-100' : 'bg-blue-100'">
                                                <i :class="satis.cihaz_adi ? 'fas fa-microchip text-purple-600' : 'fas fa-shopping-bag text-blue-500'"
                                                   class="text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-800 text-sm"
                                                   x-text="satis.cihaz_adi ? (satis.cihaz_marka ? satis.cihaz_marka+' ' : '')+satis.cihaz_adi : 'Ürün Satışı'"></p>
                                                <p class="text-xs text-slate-500 mt-0.5 max-w-sm truncate"
                                                   x-show="satis.satis_ozeti"
                                                   x-text="satis.satis_ozeti"></p>
                                                <p class="text-xs text-slate-400"
                                                   x-text="'Satış: '+(satis.created_at||'').substring(0,10)+(satis.seri_no?' · Seri: '+satis.seri_no:'')"></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="badge"
                                                  :class="satis.odeme_turu==='pesin' ? 'badge-green' : 'badge-blue'"
                                                  x-text="satis.odeme_turu==='pesin' ? 'Peşin' : satis.taksit_sayisi+' Taksit'"></span>
                                            <span class="font-bold text-slate-800 text-sm" x-text="formatCurrency(satis.toplam_tutar)"></span>
                                            <a :href="'fiyat_teklifi.php?tip=satis&id='+satis.id" target="_blank"
                                               class="btn btn-sm btn-secondary btn-icon" title="Fatura" @click.stop>
                                                <i class="fas fa-file-invoice text-slate-500 text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <template x-if="satis.odeme_turu !== 'pesin' && satis.taksitler && satis.taksitler.length > 0">
                                        <div class="px-4 py-3 space-y-2">
                                            <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                                                <span x-text="satis.odenen_taksit+' / '+satis.toplam_taksit+' taksit ödendi'"></span>
                                                <span :class="satis.kalan_tutar > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600 font-semibold'"
                                                      x-text="satis.kalan_tutar > 0 ? 'Kalan: '+formatCurrency(satis.kalan_tutar) : 'Tamamlandı'"></span>
                                            </div>
                                            <div class="w-full bg-slate-100 rounded-full h-2">
                                                <div class="h-2 rounded-full transition-all"
                                                     :class="satis.kalan_tutar > 0 ? 'bg-blue-500' : 'bg-emerald-500'"
                                                     :style="'width:'+(satis.toplam_taksit > 0 ? Math.round(satis.odenen_taksit/satis.toplam_taksit*100) : 0)+'%'"></div>
                                            </div>
                                            <div class="mt-2 space-y-1">
                                                <template x-for="t in satis.taksitler" :key="t.taksit_no">
                                                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50 last:border-0">
                                                        <div class="flex items-center gap-2">
                                                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                                                  :class="t.odendi ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                                                <i :class="t.odendi ? 'fas fa-check' : (t.taksit_no===0 ? 'fas fa-coins' : 'far fa-clock')"></i>
                                                            </span>
                                                            <span class="text-slate-600" x-text="t.taksit_no===0 ? 'Peşinat' : t.taksit_no+'. Taksit'"></span>
                                                            <span class="text-slate-400" x-text="t.vade_tarihi ? '· '+t.vade_tarihi.substring(0,10) : ''"></span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-semibold text-slate-700" x-text="formatCurrency(t.tutar)"></span>
                                                            <span x-show="t.odendi" class="badge badge-green" style="font-size:10px;padding:2px 6px;">Ödendi</span>
                                                            <span x-show="!t.odendi" class="badge badge-red" style="font-size:10px;padding:2px 6px;">Bekliyor</span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="satis.odeme_turu === 'pesin'">
                                        <div class="px-4 py-2 text-xs text-emerald-600 font-medium">
                                            <i class="fas fa-check-circle mr-1"></i> Peşin ödeme — tamamlandı
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Servis Geçmişi -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">
                        Servis Geçmişi
                        <span class="ml-2 bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full text-xs font-semibold"
                              x-text="(detail?.servisler||[]).length"></span>
                    </h4>
                    <template x-if="!detail?.servisler || detail.servisler.length === 0">
                        <div class="bg-slate-50 rounded-xl p-4 text-center text-sm text-slate-400">
                            <i class="fas fa-wrench text-2xl mb-1 block text-slate-200"></i>
                            Servis kaydı bulunamadı.
                        </div>
                    </template>
                    <div class="space-y-2">
                        <template x-for="s in (detail?.servisler||[])" :key="s.id">
                            <div class="flex items-start justify-between gap-3 p-3 bg-slate-50 rounded-xl">
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-wrench text-blue-500 text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-slate-800 text-sm" x-text="s.servis_tipi || 'Genel Servis'"></p>
                                        <p class="text-xs text-slate-500 mt-0.5 truncate"
                                           x-show="s.servis_ozeti"
                                           x-text="s.servis_ozeti"></p>
                                        <p class="text-xs text-slate-400" x-text="(s.created_at||'').substring(0,10)"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="badge"
                                          :class="{
                                              'badge-green': s.durum==='tamamlandi',
                                              'badge-blue':  s.durum==='devam_ediyor',
                                              'badge-amber': s.durum==='beklemede',
                                              'badge-red':   s.durum==='iptal',
                                          }"
                                          x-text="{tamamlandi:'Tamamlandı',devam_ediyor:'Devam Ediyor',beklemede:'Beklemede',iptal:'İptal'}[s.durum]||s.durum">
                                    </span>
                                    <span class="font-semibold text-slate-700 text-sm" x-text="s.toplam_tutar ? formatCurrency(s.toplam_tutar) : '—'"></span>
                                    <a :href="'fiyat_teklifi.php?tip=servis&id='+s.id" target="_blank"
                                       class="btn btn-sm btn-secondary btn-icon" title="Fatura" @click.stop>
                                        <i class="fas fa-file-invoice text-slate-500 text-xs"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
function emptyDeviceForm() {
    return { aktif:false, cihaz_id:null, cihaz_adi:'', marka:'', model:'', seri_no:'', kurulum_tarihi:'', notlar:'' };
}

function emptyCustomerForm() {
    return {
        ad:'', soyad:'', telefon:'', email:'', adres:'', notlar:'', lat:null, lng:null,
        mevcut_cihaz: emptyDeviceForm(),
    };
}

function musterilerApp() {
    return {
        musteriler: [], cihazlar: [], stats: {}, loading: false, search: '',
        showForm: false, showDetail: false, editId: null, saving: false,
        detail: null, showDetailMap: false,
        // Map state
        showMap: false, mapSearch: '', geocoding: false, locating: false,
        _formMap: null, _formMarker: null,
        _detailMap: null,
        defaultMapCenter: [35.1856, 33.3823],
        form: emptyCustomerForm(),

        async init() {
            await Promise.all([this.loadMusteriler(), this.loadStats(), this.loadCihazlar()]);
            this.$watch('showForm', val => { if (!val) this._destroyFormMap(); });
            this.$watch('showDetail', val => { if (!val) { this._destroyDetailMap(); this.showDetailMap = false; } });
        },

        async loadMusteriler() {
            this.loading = true;
            try {
                const q = this.search ? `?search=${encodeURIComponent(this.search)}` : '';
                this.musteriler = await api('api/musteriler.php' + q);
            } catch(e) {} finally { this.loading = false; }
        },

        async loadStats() {
            try { this.stats = await api('api/musteriler.php?stats=1'); } catch(e) {}
        },

        async loadCihazlar() {
            try { this.cihazlar = await api('api/cihazlar.php'); } catch(e) { this.cihazlar = []; }
        },

        selectedExistingDevice() {
            return this.cihazlar.find(c => String(c.id) === String(this.form.mevcut_cihaz.cihaz_id || '')) || null;
        },

        selectedExistingDeviceLabel() {
            const c = this.selectedExistingDevice();
            return c ? [(c.marka||''), (c.model||''), (c.cihaz_adi||'')].filter(Boolean).join(' ') : '';
        },

        syncExistingDeviceFields() {
            const c = this.selectedExistingDevice();
            this.form.mevcut_cihaz.cihaz_adi = c ? (c.cihaz_adi || '') : '';
            this.form.mevcut_cihaz.marka = c ? (c.marka || '') : '';
            this.form.mevcut_cihaz.model = c ? (c.model || '') : '';
        },

        openAddModal() {
            this.editId = null;
            this.showMap = false;
            this.mapSearch = '';
            this.form = emptyCustomerForm();
            this.showForm = true;
        },

        editMusteri(m) {
            this.editId = m.id;
            this.showMap = false;
            this.mapSearch = '';
            this.form = {
                ad: m.ad, soyad: m.soyad, telefon: m.telefon||'',
                email: m.email||'', adres: m.adres||'', notlar: m.notlar||'',
                lat: m.lat || null, lng: m.lng || null,
                mevcut_cihaz: emptyDeviceForm(),
            };
            this.showForm = true;
        },

        closeForm() {
            this.showForm = false;
        },

        async viewMusteri(m) {
            this.detail = null;
            this.showDetailMap = false;
            this.showDetail = true;
            try {
                this.detail = await api(`api/musteriler.php?id=${m.id}`);
            } catch(e) { this.showDetail = false; }
        },

        closeDetail() {
            this.showDetail = false;
        },

        // ── Harita (Form) ──────────────────────────────────────────
        toggleMap() {
            this.showMap = !this.showMap;
            if (this.showMap) {
                this.$nextTick(() => this._initFormMap());
            } else {
                this._destroyFormMap();
            }
        },

        _initFormMap() {
            if (this._formMap) { this._formMap.remove(); this._formMap = null; this._formMarker = null; }
            const lat = this.form.lat || this.defaultMapCenter[0];
            const lng = this.form.lng || this.defaultMapCenter[1];
            const zoom = this.form.lat ? 15 : 10;

            this._formMap = L.map('formMap').setView([lat, lng], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this._formMap);

            if (this.form.lat && this.form.lng) {
                this._setFormLocation(this.form.lat, this.form.lng, { reverse: false, zoom: false });
            }

            this._formMap.on('click', e => {
                this._setFormLocation(e.latlng.lat, e.latlng.lng, { zoom: false });
            });
        },

        _setFormLocation(lat, lng, options = {}) {
            const point = [Number(lat), Number(lng)];
            this.form.lat = point[0];
            this.form.lng = point[1];

            if (options.address) this.form.adres = options.address;

            if (this._formMap && options.zoom !== false) {
                this._formMap.setView(point, options.zoom || 16);
            }

            if (this._formMarker) {
                this._formMarker.setLatLng(point);
            } else if (this._formMap) {
                this._formMarker = L.marker(point, { draggable: true }).addTo(this._formMap);
                this._formMarker.on('dragend', e => {
                    const p = e.target.getLatLng();
                    this._setFormLocation(p.lat, p.lng, { zoom: false });
                });
            }

            if (options.reverse !== false) this._reverseGeocode(point[0], point[1]);
        },

        _destroyFormMap() {
            if (this._formMap) { this._formMap.remove(); this._formMap = null; this._formMarker = null; }
        },

        async searchAddress() {
            if (!this.mapSearch.trim()) return;
            this.geocoding = true;
            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.mapSearch)}&limit=1&accept-language=tr`;
                const res = await fetch(url, { headers: { 'User-Agent': 'MusteriTakipSistemi/1.0' } });
                const results = await res.json();
                if (results.length > 0) {
                    const r = results[0];
                    const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
                    this._setFormLocation(lat, lng, { address: r.display_name, reverse: false });
                } else {
                    showToast('Adres bulunamadı. Daha ayrıntılı bir adres deneyin.', 'error');
                }
            } catch(e) {
                showToast('Arama başarısız. İnternet bağlantınızı kontrol edin.', 'error');
            } finally { this.geocoding = false; }
        },

        async useCurrentLocation() {
            if (!navigator.geolocation) {
                showToast('Bu cihaz konum paylaşımını desteklemiyor.', 'error');
                return;
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                pos => {
                    this.locating = false;
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    if (this.showMap && !this._formMap) this._initFormMap();
                    this._setFormLocation(lat, lng, { zoom: 17 });
                    showToast('Mevcut konum işaretlendi.', 'success');
                },
                err => {
                    this.locating = false;
                    const denied = err && err.code === 1;
                    showToast(denied ? 'Konum izni verilmedi.' : 'Konum alınamadı. GPS ve bağlantıyı kontrol edin.', 'error');
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
            );
        },

        async _reverseGeocode(lat, lng) {
            try {
                const res = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=tr`,
                    { headers: { 'User-Agent': 'MusteriTakipSistemi/1.0' } }
                );
                const r = await res.json();
                if (r.display_name) this.form.adres = r.display_name;
            } catch(e) {}
        },

        clearLocation() {
            this.form.lat = null; this.form.lng = null;
            if (this._formMarker) { this._formMarker.remove(); this._formMarker = null; }
        },

        // ── Harita (Detail) ────────────────────────────────────────
        toggleDetailMap() {
            this.showDetailMap = !this.showDetailMap;
            if (this.showDetailMap) {
                this.$nextTick(() => this._initDetailMap());
            } else {
                this._destroyDetailMap();
            }
        },

        _initDetailMap() {
            if (this._detailMap) { this._detailMap.remove(); this._detailMap = null; }
            if (!this.detail?.lat) return;
            this._detailMap = L.map('detailMap').setView([this.detail.lat, this.detail.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this._detailMap);
            L.marker([this.detail.lat, this.detail.lng])
                .addTo(this._detailMap)
                .bindPopup(`<strong>${this.detail.ad} ${this.detail.soyad}</strong><br>${this.detail.adres||''}`)
                .openPopup();
        },

        _destroyDetailMap() {
            if (this._detailMap) { this._detailMap.remove(); this._detailMap = null; }
        },

        // ── CRUD ───────────────────────────────────────────────────
        async saveMusteri() {
            if (!this.editId && this.form.mevcut_cihaz.aktif && !this.form.mevcut_cihaz.cihaz_id) {
                showToast('Mevcut cihaz eklemek için katalogdan cihaz seçin.', 'error');
                return;
            }
            this.saving = true;
            try {
                if (this.editId) {
                    await api(`api/musteriler.php?id=${this.editId}`, { method: 'PUT', body: this.form });
                    showToast('Müşteri güncellendi.', 'success');
                } else {
                    await api('api/musteriler.php', { method: 'POST', body: this.form });
                    showToast('Müşteri eklendi.', 'success');
                }
                this.showForm = false;
                await this.loadMusteriler();
            } catch(e) {} finally { this.saving = false; }
        },

        async deleteMusteri(m) {
            if (!confirm(`"${m.ad} ${m.soyad}" silinsin mi?`)) return;
            try {
                const r = await api(`api/musteriler.php?id=${m.id}`, { method: 'DELETE' });
                if (r.success === false) { alert(r.message); return; }
                showToast('Müşteri silindi.', 'success');
                await this.loadMusteriler();
            } catch(e) {}
        },

        formatCurrency,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
