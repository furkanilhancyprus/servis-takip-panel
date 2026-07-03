<?php
$pageTitle  = 'Satışlar';
$activePage = 'satislar';
include __DIR__ . '/layout/header.php';
?>

<div x-data="satislarApp()" x-init="init()">

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" placeholder="Müşteri ara..."
                       class="form-input pl-9 w-52"
                       x-model="search" @input.debounce.300ms="loadSatislar()">
            </div>
            <select class="form-select w-40" x-model="odemeFiltre" @change="loadSatislar()">
                <option value="">Tüm Ödemeler</option>
                <option value="odenmedi">Ödenmedi</option>
                <option value="kismi">Kısmi</option>
                <option value="odendi">Ödendi</option>
            </select>
            <span class="text-sm text-slate-500" x-text="`${satislar.length} kayıt`"></span>
        </div>
        <button class="btn btn-primary" @click="openAddModal()">
            <i class="fas fa-plus"></i> Yeni Satış
        </button>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-3 gap-4 mb-5">
        <div class="card p-4">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Toplam Satış</p>
            <p class="text-2xl font-bold text-slate-800 mt-1" x-text="formatCurrency(satislar.reduce((s,r)=>s+(+r.toplam_tutar||0),0))"></p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Tahsil Edilen</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="formatCurrency(satislar.reduce((s,r)=>s+(+r.odenen_tutar||0),0))"></p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Bekleyen</p>
            <p class="text-2xl font-bold text-red-600 mt-1"
               x-text="formatCurrency(satislar.reduce((s,r)=>s+Math.max(0,(+r.toplam_tutar||0)-(+r.odenen_tutar||0)),0))"></p>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Müşteri</th>
                        <th>Cihaz</th>
                        <th>Tarih</th>
                        <th>Ödeme</th>
                        <th>Toplam</th>
                        <th>Durum</th>
                        <th>Kalan</th>
                        <th class="text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="9" class="text-center py-12 text-slate-400">
                            <div class="spinner mx-auto mb-2"></div>Yükleniyor...
                        </td></tr>
                    </template>
                    <template x-if="!loading && satislar.length === 0">
                        <tr><td colspan="9" class="text-center py-12 text-slate-400">
                            <i class="fas fa-cart-shopping text-3xl mb-2 block text-slate-200"></i>Satış yok
                        </td></tr>
                    </template>
                    <template x-for="s in satislar" :key="s.id">
                        <tr>
                            <td class="text-slate-400 text-xs" x-text="`#${s.id}`"></td>
                            <td>
                                <p class="font-medium text-slate-800" x-text="s.musteri_adi"></p>
                                <p class="text-xs text-slate-400" x-text="s.telefon || ''"></p>
                            </td>
                            <td>
                                <template x-if="s.cihaz_adi">
                                    <div>
                                        <p class="text-sm font-medium text-slate-700" x-text="s.cihaz_adi"></p>
                                        <p class="text-xs text-slate-400" x-text="s.cihaz_marka || ''"></p>
                                    </div>
                                </template>
                                <template x-if="!s.cihaz_adi">
                                    <span class="text-slate-300 text-xs">—</span>
                                </template>
                            </td>
                            <td class="text-sm text-slate-600" x-text="formatDate(s.satis_tarihi)"></td>
                            <td>
                                <span class="badge"
                                      :class="s.odeme_turu === 'taksitli' ? 'badge-purple' : 'badge-blue'"
                                      x-text="s.odeme_turu === 'taksitli' ? `${s.taksit_sayisi} Taksit` : 'Peşin'">
                                </span>
                            </td>
                            <td class="font-semibold text-slate-700" x-text="formatCurrency(s.toplam_tutar)"></td>
                            <td>
                                <span class="badge" :class="odemeBadgeClass(s.odeme_durumu)"
                                      x-text="odemeBadgeText(s.odeme_durumu)"></span>
                                <p x-show="s.bekleyen_taksit > 0"
                                   class="text-xs text-orange-500 mt-0.5" x-text="`${s.bekleyen_taksit} taksit bekliyor`"></p>
                            </td>
                            <td>
                                <span class="text-sm font-medium"
                                      :class="(s.toplam_tutar - s.odenen_tutar) > 0 ? 'text-red-600' : 'text-emerald-600'"
                                      x-text="formatCurrency(Math.max(0,(+s.toplam_tutar||0)-(+s.odenen_tutar||0)))">
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="viewSatis(s)" title="Detay">
                                        <i class="fas fa-eye text-slate-500"></i>
                                    </button>
                                    <a :href="`fiyat_teklifi.php?tip=satis&id=${s.id}`" target="_blank"
                                       class="btn btn-sm btn-secondary btn-icon" title="Fatura / Teklif">
                                        <i class="fas fa-file-invoice text-indigo-500"></i>
                                    </a>
                                    <button x-show="s.odeme_durumu !== 'odendi'"
                                            class="btn btn-sm btn-success btn-icon" @click="openTahsilat(s)" title="Tahsilat Al">
                                        <i class="fas fa-money-bill-wave text-emerald-600"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-icon" @click="deleteSatis(s)" title="Sil">
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

    <!-- ===== ADD MODAL ===== -->
    <div x-show="showAdd" x-cloak class="modal-backdrop" @click.self="showAdd=false">
        <div class="modal-box max-w-2xl">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Yeni Satış</h3>
                <button @click="showAdd=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveSatis()" class="modal-body space-y-4">

                <!-- Müşteri -->
                <div>
                    <label class="form-label">Müşteri <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" class="form-input pl-9" placeholder="Müşteri ara..."
                               x-model="musteriSearch" @input.debounce.250ms="searchMusteriler()"
                               @focus="showMusteriList = musteriOneri.length > 0" autocomplete="off">
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
                    <p class="text-sm text-blue-600 mt-1" x-show="form.musteri_id">
                        <i class="fas fa-check-circle mr-1"></i><span x-text="selectedMusteriName"></span>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Tarih -->
                    <div>
                        <label class="form-label">Satış Tarihi</label>
                        <input type="date" class="form-input" x-model="form.satis_tarihi">
                        <p class="text-xs text-slate-400 mt-1">Bu tarihe göre müşteri otomatik bakım takibine alınır.</p>
                    </div>
                    <!-- Cihaz -->
                    <div>
                        <label class="form-label">Cihaz (Opsiyonel)</label>
                        <select class="form-select" x-model="form.cihaz_id" @change="setCihazFiyat()">
                            <option value="">— Cihaz seçin —</option>
                            <template x-for="c in cihazlar" :key="c.id">
                                <option :value="c.id"
                                        x-text="`${c.cihaz_adi}${c.marka ? ' - '+c.marka : ''}${c.model ? ' '+c.model : ''}`">
                                </option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Seri No -->
                <div x-show="form.cihaz_id">
                    <label class="form-label">Seri No / Barkod (Opsiyonel)</label>
                    <input type="text" class="form-input" x-model="form.seri_no" placeholder="Cihaz seri numarası">
                </div>

                <!-- Kalemler -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0">Ürünler / Kalemler</label>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-sm btn-secondary" @click="addStokKalem()">
                                <i class="fas fa-boxes-stacked text-xs"></i> Stoktan
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" @click="addManuelKalem()">
                                <i class="fas fa-plus text-xs"></i> Manuel
                            </button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <template x-if="form.kalemler.length === 0">
                            <div class="text-center py-4 text-slate-400 text-sm border-2 border-dashed border-slate-200 rounded-lg">
                                Kalem eklenmedi — opsiyonel
                            </div>
                        </template>
                        <template x-for="(k, i) in form.kalemler" :key="i">
                            <div class="flex flex-wrap gap-2 items-start p-3 bg-slate-50 rounded-lg">
                                <div style="flex:1 1 18rem;min-width:15rem;">
                                    <template x-if="k.stok_mod">
                                        <select class="form-select" x-model="k.parca_id" @change="setStokFiyat(k)" :title="k.urun_adi || 'Stoktan ürün seçin'">
                                            <option value="">Stoktan seçin...</option>
                                            <template x-for="pk in stoklar" :key="pk.id">
                                                <option :value="pk.id"
                                                        x-text="`${pk.parca_adi}${pk.marka ? ' ('+pk.marka+')' : ''} — Stok: ${pk.stok_miktari}`">
                                                </option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="!k.stok_mod">
                                        <input type="text" class="form-input" placeholder="Ürün adı" x-model="k.urun_adi">
                                    </template>
                                </div>
                                <input type="number" class="form-input w-20" placeholder="Adet" min="1"
                                       x-model="k.miktar" @input="calcTotal()">
                                <input type="number" class="form-input w-28" placeholder="Fiyat ₺" step="0.01"
                                       x-model="k.birim_fiyat" @input="calcTotal()">
                                <div class="text-right min-w-20 pt-2">
                                    <span class="text-sm font-semibold text-slate-700"
                                          x-text="formatCurrency((+k.miktar||1)*(+k.birim_fiyat||0))"></span>
                                </div>
                                <button type="button" class="btn btn-danger btn-icon btn-sm"
                                        @click="form.kalemler.splice(i,1); calcTotal()">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Toplam & Ödeme Türü -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Toplam Tutar (₺) <span class="text-red-500">*</span></label>
                        <input type="number" class="form-input text-lg font-bold" step="0.01" min="0"
                               x-model="form.toplam_tutar" @input="calcTaksit()">
                        <p class="text-xs text-slate-400 mt-1">Kalem girilmezse buraya yazın</p>
                    </div>
                    <div>
                        <label class="form-label">Ödeme Türü <span class="text-red-500">*</span></label>
                        <div class="flex gap-2 mt-1">
                            <button type="button"
                                    class="flex-1 py-2.5 rounded-xl border-2 text-sm font-semibold transition"
                                    :class="form.odeme_turu==='pesin' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-500'"
                                    @click="form.odeme_turu='pesin'">
                                <i class="fas fa-money-bill-wave mr-1"></i>Peşin
                            </button>
                            <button type="button"
                                    class="flex-1 py-2.5 rounded-xl border-2 text-sm font-semibold transition"
                                    :class="form.odeme_turu==='taksitli' ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-slate-200 text-slate-500'"
                                    @click="form.odeme_turu='taksitli'">
                                <i class="fas fa-calendar-days mr-1"></i>Taksitli
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Taksit Ayarları -->
                <div x-show="form.odeme_turu==='taksitli'"
                     class="bg-purple-50 border border-purple-100 rounded-xl p-4 space-y-3">
                    <h4 class="text-sm font-semibold text-purple-800 flex items-center gap-2">
                        <i class="fas fa-calendar-days"></i> Taksit Planı
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Peşinat (₺)</label>
                            <input type="number" class="form-input" step="0.01" min="0"
                                   x-model="form.pesinat" @input="calcTaksit()">
                        </div>
                        <div>
                            <label class="form-label">Taksit Sayısı</label>
                            <select class="form-select" x-model="form.taksit_sayisi" @change="calcTaksit()">
                                <template x-for="n in [2,3,4,5,6,9,10,12,18,24,36]" :key="n">
                                    <option :value="n" x-text="`${n} taksit`"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">İlk Taksit Tarihi</label>
                        <input type="date" class="form-input" x-model="form.ilk_taksit_tarihi">
                        <div class="flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-secondary"
                                    @click="form.ilk_taksit_tarihi = form.satis_tarihi || todayDate()">
                                Satış günü
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary"
                                    @click="setIlkTaksitSonrakiAy()">
                                1 ay sonra
                            </button>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            Boş bırakılırsa ilk taksit satış tarihinde başlar.
                        </p>
                    </div>
                    <!-- Taksit Özeti -->
                    <div class="bg-white rounded-lg p-3 text-sm space-y-1.5 border border-purple-100">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Toplam Tutar:</span>
                            <span class="font-semibold" x-text="formatCurrency(+form.toplam_tutar||0)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Peşinat:</span>
                            <span class="font-semibold text-emerald-600" x-text="formatCurrency(+form.pesinat||0)"></span>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-1.5">
                            <span class="text-slate-500">Kalan (taksit):</span>
                            <span class="font-semibold text-purple-700" x-text="formatCurrency(Math.max(0,(+form.toplam_tutar||0)-(+form.pesinat||0)))"></span>
                        </div>
                        <div class="flex justify-between font-bold border-t border-slate-100 pt-1.5">
                            <span>Aylık Taksit:</span>
                            <span class="text-purple-700" x-text="formatCurrency(taksitTutari)"></span>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-1.5">
                            <span class="text-slate-500">İlk Taksit:</span>
                            <span class="font-semibold text-slate-700" x-text="formatDate(form.ilk_taksit_tarihi || form.satis_tarihi)"></span>
                        </div>
                    </div>
                </div>

                <!-- Notlar -->
                <div>
                    <label class="form-label">Notlar</label>
                    <textarea class="form-textarea" rows="2" x-model="form.notlar"></textarea>
                </div>

                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showAdd=false">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        <i x-show="!saving" class="fas fa-save"></i>
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== DETAIL MODAL ===== -->
    <div x-show="showDetail" x-cloak class="modal-backdrop" @click.self="showDetail=false">
        <div class="modal-box max-w-xl" x-show="detail">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800" x-text="`Satış #${detail?.id}`"></h3>
                <div class="flex gap-2">
                    <a :href="`fiyat_teklifi.php?tip=satis&id=${detail?.id}`" target="_blank"
                       class="btn btn-sm btn-secondary">
                        <i class="fas fa-file-invoice text-indigo-500"></i> Fatura
                    </a>
                    <button @click="showDetail=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="modal-body space-y-4">
                <!-- Bilgiler -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400 mb-1">Müşteri</p>
                        <p class="font-medium" x-text="`${detail?.ad || ''} ${detail?.soyad || ''}`"></p>
                        <p class="text-xs text-slate-400" x-text="detail?.telefon || ''"></p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400 mb-1">Satış Tarihi</p>
                        <p class="font-medium" x-text="formatDate(detail?.satis_tarihi)"></p>
                        <span class="badge mt-1"
                              :class="detail?.odeme_turu === 'taksitli' ? 'badge-purple' : 'badge-blue'"
                              x-text="detail?.odeme_turu === 'taksitli' ? `${detail?.taksit_sayisi} Taksit` : 'Peşin'">
                        </span>
                    </div>
                </div>

                <!-- Cihaz -->
                <div x-show="detail?.cihaz_adi" class="bg-indigo-50 rounded-lg p-3 flex items-center gap-3">
                    <i class="fas fa-microchip text-indigo-400"></i>
                    <div>
                        <p class="text-xs text-slate-400">Satılan Cihaz</p>
                        <p class="font-semibold text-indigo-700" x-text="`${detail?.cihaz_adi || ''} ${detail?.cihaz_marka || ''} ${detail?.cihaz_model || ''}`"></p>
                        <p class="text-xs text-slate-400" x-show="detail?.seri_no" x-text="`Seri: ${detail?.seri_no}`"></p>
                    </div>
                </div>

                <!-- Kalemler -->
                <div x-show="detail?.kalemler?.length > 0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Satılan Ürünler</p>
                    <div class="space-y-1.5">
                        <template x-for="k in (detail?.kalemler || [])" :key="k.id">
                            <div class="flex justify-between items-center bg-slate-50 rounded-lg px-3 py-2 text-sm">
                                <span class="font-medium" x-text="`${k.urun_adi} × ${k.miktar}`"></span>
                                <span class="font-semibold" x-text="formatCurrency(k.birim_fiyat * k.miktar)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Taksit Planı -->
                <div x-show="detail?.taksitler?.length > 0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Taksit Planı</p>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto">
                        <template x-for="tk in (detail?.taksitler || [])" :key="tk.id">
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg text-sm"
                                 :class="Number(tk.odendi) === 1 ? 'bg-emerald-50' : (new Date(tk.vade_tarihi) < new Date() ? 'bg-red-50' : 'bg-slate-50')">
                                <div class="flex items-center gap-2">
                                    <i class="fas text-xs"
                                       :class="Number(tk.odendi) === 1 ? 'fa-check-circle text-emerald-500' : (tk.taksit_no === 0 ? 'fa-hand-holding-dollar text-blue-400' : 'fa-clock text-slate-400')"></i>
                                    <span x-text="tk.taksit_no === 0 ? 'Peşinat' : `${tk.taksit_no}. Taksit`"></span>
                                    <span class="text-xs text-slate-400" x-text="formatDate(tk.vade_tarihi)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold" x-text="formatCurrency(tk.tutar)"></span>
                                    <button x-show="Number(tk.odendi) !== 1 && tk.taksit_no > 0"
                                            class="btn btn-sm btn-success py-0.5 px-2 text-xs"
                                            @click="odeTaksit(tk)">Öde</button>
                                    <button x-show="Number(tk.odendi) === 1 && tk.taksit_no > 0"
                                            class="btn btn-sm btn-warning py-0.5 px-2 text-xs"
                                            @click="geriAlTaksit(tk)">Geri Al</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Tahsilat Geçmişi -->
                <div x-show="detail?.tahsilatlar?.length > 0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Tahsilat Geçmişi</p>
                    <div class="space-y-1.5 max-h-40 overflow-y-auto">
                        <template x-for="th in (detail?.tahsilatlar || [])" :key="th.id">
                            <div class="flex items-center justify-between bg-emerald-50 rounded-lg px-3 py-2 text-sm">
                                <div>
                                    <span class="font-semibold text-emerald-700" x-text="formatCurrency(th.tutar)"></span>
                                    <span class="text-slate-400 ml-2" x-text="formatOdemeYontemi(th.odeme_yontemi)"></span>
                                    <span class="text-slate-400 ml-2" x-text="formatDate(th.tahsilat_tarihi)"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger py-0.5 px-2 text-xs"
                                        @click="deleteTahsilat(th.id, 'satis')">Sil</button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Ödeme Özeti -->
                <div class="rounded-xl border overflow-hidden"
                     :class="detail?.odeme_durumu === 'odendi' ? 'border-emerald-200' : 'border-red-200'">
                    <div class="px-4 py-3 flex items-center justify-between"
                         :class="detail?.odeme_durumu === 'odendi' ? 'bg-emerald-50' : 'bg-red-50'">
                        <span class="font-semibold text-slate-700">Toplam</span>
                        <span class="text-2xl font-bold text-slate-800" x-text="formatCurrency(detail?.toplam_tutar)"></span>
                    </div>
                    <div class="px-4 py-2 flex justify-between bg-white text-sm">
                        <span class="text-slate-500">Ödenen</span>
                        <span class="font-semibold text-emerald-600" x-text="formatCurrency(detail?.odenen_tutar)"></span>
                    </div>
                    <div class="px-4 py-2 flex justify-between bg-white text-sm border-t border-slate-100">
                        <span class="text-slate-500">Kalan</span>
                        <span class="font-bold text-red-600"
                              x-text="formatCurrency(Math.max(0,(detail?.toplam_tutar||0)-(detail?.odenen_tutar||0)))"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" x-show="detail?.odeme_durumu !== 'odendi' && detail?.odeme_turu !== 'taksitli'">
                <button class="btn btn-success" @click="showDetail=false; openTahsilat(detail)">
                    <i class="fas fa-money-bill-wave"></i> Tahsilat Al
                </button>
            </div>
        </div>
    </div>

    <!-- ===== TAHSİLAT MODAL (peşin) ===== -->
    <div x-show="showTahsilat" x-cloak class="modal-backdrop" @click.self="showTahsilat=false">
        <div class="modal-box max-w-md">
            <div class="modal-header">
                <h3 class="font-semibold">Tahsilat Al</h3>
                <button @click="showTahsilat=false"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveTahsilat()" class="modal-body space-y-4">
                <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                    <p class="font-semibold" x-text="tahsilatForm.musteriAdi"></p>
                    <div class="flex justify-between mt-2 text-sm">
                        <span class="text-slate-500">Kalan Borç:</span>
                        <span class="font-bold text-red-600" x-text="formatCurrency(tahsilatForm.kalan)"></span>
                    </div>
                </div>
                <div>
                    <label class="form-label">Tutar <span class="text-red-500">*</span></label>
                    <input type="number" class="form-input" step="0.01" min="0.01"
                           :max="tahsilatForm.kalan" x-model="tahsilatForm.tutar">
                    <button type="button" class="btn btn-sm btn-secondary mt-2 w-full"
                            @click="tahsilatForm.tutar = tahsilatForm.kalan">Tamamını Al</button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Yöntem</label>
                        <select class="form-select" x-model="tahsilatForm.odeme_yontemi">
                            <option value="nakit">Nakit</option>
                            <option value="kart">Kart</option>
                            <option value="havale">Havale / EFT</option>
                            <option value="cek">Çek</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tarih</label>
                        <input type="date" class="form-input" x-model="tahsilatForm.tahsilat_tarihi">
                    </div>
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showTahsilat=false">İptal</button>
                    <button type="submit" class="btn btn-success" :disabled="saving">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== TAKSİT ÖDE MODAL ===== -->
    <div x-show="showTaksitOde" x-cloak class="modal-backdrop" @click.self="showTaksitOde=false">
        <div class="modal-box max-w-sm">
            <div class="modal-header">
                <h3 class="font-semibold">Taksit Öde</h3>
                <button @click="showTaksitOde=false"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveTaksitOde()" class="modal-body space-y-4">
                <div class="bg-purple-50 rounded-xl p-4 border border-purple-100 text-sm">
                    <div class="flex justify-between">
                        <span x-text="taksitForm.taksit_no === 0 ? 'Peşinat' : `${taksitForm.taksit_no}. Taksit`" class="font-semibold"></span>
                        <span class="font-bold text-purple-700" x-text="formatCurrency(taksitForm.tutar)"></span>
                    </div>
                    <div class="text-slate-400 mt-1" x-text="`Vade: ${formatDate(taksitForm.vade_tarihi)}`"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Ödeme Yöntemi</label>
                        <select class="form-select" x-model="taksitForm.odeme_yontemi">
                            <option value="nakit">Nakit</option>
                            <option value="kart">Kart</option>
                            <option value="havale">Havale / EFT</option>
                            <option value="cek">Çek</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Ödeme Tarihi</label>
                        <input type="date" class="form-input" x-model="taksitForm.odeme_tarihi">
                    </div>
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showTaksitOde=false">İptal</button>
                    <button type="submit" class="btn btn-success" :disabled="saving">
                        <i class="fas fa-check"></i> Ödendi Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function satislarApp() {
    return {
        satislar: [], loading: false,
        search: '', odemeFiltre: '',
        showAdd: false, showDetail: false, showTahsilat: false,
        showTaksitOde: false,
        saving: false, detail: null,
        stoklar: [], cihazlar: [],
        musteriSearch: '', musteriOneri: [], showMusteriList: false,
        selectedMusteriName: '',
        taksitTutari: 0,
        form: {
            musteri_id: '', satis_tarihi: new Date().toISOString().split('T')[0],
            cihaz_id: '', seri_no: '',
            kalemler: [], notlar: '', toplam_tutar: 0,
            odeme_turu: 'pesin', taksit_sayisi: 3, pesinat: 0, ilk_taksit_tarihi: '',
        },
        tahsilatForm: {
            musteri_id: '', kaynak_id: '', kaynak_tip: 'satis',
            musteriAdi: '', kalan: 0, tutar: 0,
            odeme_yontemi: 'nakit', tahsilat_tarihi: new Date().toISOString().split('T')[0],
        },
        taksitForm: {
            id: null, taksit_no: 0, tutar: 0, vade_tarihi: '',
            odeme_yontemi: 'nakit', odeme_tarihi: new Date().toISOString().split('T')[0],
        },

        async init() {
            await Promise.all([this.loadSatislar(), this.loadStoklar(), this.loadCihazlar()]);
        },

        async loadSatislar() {
            this.loading = true;
            try {
                const p = new URLSearchParams();
                if (this.search)      p.set('search', this.search);
                if (this.odemeFiltre) p.set('odeme_durumu', this.odemeFiltre);
                this.satislar = await api(`api/satislar.php?${p}`);
            } catch(e) {} finally { this.loading = false; }
        },

        async loadStoklar()  { try { this.stoklar  = await api('api/stok.php');    } catch(e) {} },
        async loadCihazlar() { try { this.cihazlar = await api('api/cihazlar.php'); } catch(e) {} },

        async searchMusteriler() {
            if (!this.musteriSearch) { this.musteriOneri = []; return; }
            try {
                this.musteriOneri = await api(`api/musteriler.php?search=${encodeURIComponent(this.musteriSearch)}`);
                this.showMusteriList = true;
            } catch(e) {}
        },

        selectMusteri(m) {
            this.form.musteri_id     = m.id;
            this.selectedMusteriName = `${m.ad} ${m.soyad}`;
            this.musteriSearch       = `${m.ad} ${m.soyad}`;
            this.showMusteriList     = false;
        },

        openAddModal() {
            this.form = {
                musteri_id: '', satis_tarihi: new Date().toISOString().split('T')[0],
                cihaz_id: '', seri_no: '',
                kalemler: [], notlar: '', toplam_tutar: 0,
                odeme_turu: 'pesin', taksit_sayisi: 3, pesinat: 0, ilk_taksit_tarihi: '',
            };
            this.musteriSearch = ''; this.selectedMusteriName = '';
            this.taksitTutari  = 0;
            this.showAdd = true;
        },

        setCihazFiyat() {
            const c = this.cihazlar.find(x => x.id == this.form.cihaz_id);
            if (c && c.varsayilan_fiyat > 0 && !this.form.toplam_tutar) {
                this.form.toplam_tutar = c.varsayilan_fiyat;
                this.calcTaksit();
            }
        },

        addStokKalem()  { this.form.kalemler.push({ parca_id: '', urun_adi: '', miktar: 1, birim_fiyat: 0, stok_mod: true  }); },
        addManuelKalem(){ this.form.kalemler.push({ parca_id: null, urun_adi: '', miktar: 1, birim_fiyat: 0, stok_mod: false }); },

        setStokFiyat(k) {
            const found = this.stoklar.find(s => s.id == k.parca_id);
            if (found) { k.birim_fiyat = found.birim_fiyat; k.urun_adi = found.parca_adi; }
            this.calcTotal();
        },

        calcTotal() {
            if (this.form.kalemler.length > 0) {
                this.form.toplam_tutar = +this.form.kalemler
                    .reduce((s, k) => s + (parseFloat(k.birim_fiyat) || 0) * (parseInt(k.miktar) || 1), 0).toFixed(2);
            }
            this.calcTaksit();
        },

        calcTaksit() {
            const kalan = Math.max(0, (parseFloat(this.form.toplam_tutar) || 0) - (parseFloat(this.form.pesinat) || 0));
            const n     = parseInt(this.form.taksit_sayisi) || 1;
            this.taksitTutari = n > 0 ? +(kalan / n).toFixed(2) : 0;
        },

        todayDate() {
            const now = new Date();
            return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        },

        setIlkTaksitSonrakiAy() {
            const base = this.form.satis_tarihi || this.todayDate();
            const [year, month, day] = base.split('-').map(Number);
            const lastDay = new Date(year, month + 1, 0).getDate();
            const next = new Date(year, month, Math.min(day, lastDay));
            this.form.ilk_taksit_tarihi = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
        },

        async saveSatis() {
            if (!this.form.musteri_id) { showToast('Lütfen müşteri seçin.', 'error'); return; }
            if (!this.form.toplam_tutar && !this.form.kalemler.length) {
                showToast('Tutar veya kalem girin.', 'error'); return;
            }
            for (const k of this.form.kalemler) {
                if (k.stok_mod && k.parca_id) {
                    const found = this.stoklar.find(s => s.id == k.parca_id);
                    if (found) k.urun_adi = found.parca_adi;
                }
                if (!k.urun_adi) { showToast('Tüm kalemlere ürün adı girin.', 'error'); return; }
            }
            this.calcTotal();
            this.saving = true;
            try {
                await api('api/satislar.php', { method: 'POST', body: this.form });
                showToast('Satış kaydedildi.', 'success');
                this.showAdd = false;
                await this.loadSatislar();
            } catch(e) {} finally { this.saving = false; }
        },

        async viewSatis(s) {
            try {
                this.detail = await api(`api/satislar.php?id=${s.id}`);
                this.showDetail = true;
            } catch(e) {}
        },

        openTahsilat(s) {
            const kalan = Math.max(0, (s.toplam_tutar||0) - (s.odenen_tutar||0));
            this.tahsilatForm = {
                musteri_id: s.musteri_id, kaynak_id: s.id, kaynak_tip: 'satis',
                musteriAdi: s.musteri_adi || `${s.ad} ${s.soyad}`,
                kalan, tutar: kalan,
                odeme_yontemi: 'nakit',
                tahsilat_tarihi: new Date().toISOString().split('T')[0],
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
                await this.loadSatislar();
            } catch(e) {} finally { this.saving = false; }
        },

        odeTaksit(tk) {
            this.taksitForm = {
                id: tk.id, taksit_no: tk.taksit_no,
                tutar: tk.tutar, vade_tarihi: tk.vade_tarihi,
                odeme_yontemi: 'nakit',
                odeme_tarihi: new Date().toISOString().split('T')[0],
            };
            this.showTaksitOde = true;
        },

        async saveTaksitOde() {
            this.saving = true;
            try {
                await api('api/tahsilatlar.php', {
                    method: 'POST',
                    body: {
                        taksit_id:     this.taksitForm.id,
                        odeme_yontemi: this.taksitForm.odeme_yontemi,
                        odeme_tarihi:  this.taksitForm.odeme_tarihi,
                    }
                });
                showToast('Taksit ödendi.', 'success');
                this.showTaksitOde = false;
                // Detayı yenile
                if (this.detail) {
                    this.detail = await api(`api/satislar.php?id=${this.detail.id}`);
                }
                await this.loadSatislar();
            } catch(e) {} finally { this.saving = false; }
        },

        async geriAlTaksit(tk) {
            if (!confirm(`${tk.taksit_no}. taksit ödemesi geri alınsın mı?`)) return;
            try {
                await api(`api/tahsilatlar.php?id=${tk.id}&taksit=1`, { method: 'DELETE' });
                showToast('Taksit ödemesi geri alındı.', 'success');
                if (this.detail) {
                    this.detail = await api(`api/satislar.php?id=${this.detail.id}`);
                }
                await this.loadSatislar();
            } catch(e) {}
        },

        async deleteTahsilat(id, tip = 'satis') {
            if (!confirm('Bu tahsilat kaydı silinsin mi? Ödeme durumu yeniden hesaplanacak.')) return;
            try {
                await api(`api/tahsilatlar.php?id=${id}`, { method: 'DELETE' });
                showToast('Tahsilat geri alındı.', 'success');
                if (this.detail) {
                    this.detail = await api(`api/satislar.php?id=${this.detail.id}`);
                }
                await this.loadSatislar();
            } catch(e) {}
        },

        async deleteSatis(s) {
            if (!confirm(`Satış #${s.id} silinsin mi?`)) return;
            try {
                await api(`api/satislar.php?id=${s.id}`, { method: 'DELETE' });
                showToast('Silindi.', 'success');
                await this.loadSatislar();
            } catch(e) {}
        },

        odemeBadgeClass(d) {
            return d === 'odendi' ? 'badge-green' : d === 'kismi' ? 'badge-yellow' : 'badge-red';
        },
        odemeBadgeText(d) {
            return d === 'odendi' ? 'Ödendi' : d === 'kismi' ? 'Kısmi' : 'Ödenmedi';
        },

        formatDate, formatCurrency, formatOdemeYontemi,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
