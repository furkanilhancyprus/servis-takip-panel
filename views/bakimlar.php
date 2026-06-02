<?php
$pageTitle  = 'Bakım Takibi';
$activePage = 'bakimlar';
include __DIR__ . '/layout/header.php';
?>

<div x-data="bakimlarApp()" x-init="init()">

    <!-- Başlık + Ay Seçici -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Bakım Takibi</h2>
            <p class="text-sm text-slate-400 mt-0.5">Seçilen aya göre planlı ve gecikmiş bakımlar</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="prevAy()" class="btn btn-secondary btn-icon">
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
            <input type="month" class="form-input font-semibold text-slate-700" x-model="seciliAy"
                   style="min-width:160px;" @change="loadListe()">
            <button @click="nextAy()" class="btn btn-secondary btn-icon">
                <i class="fas fa-chevron-right text-xs"></i>
            </button>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" placeholder="Müşteri ara..." class="form-input pl-9 w-48" x-model="search">
            </div>
            <button @click="showTakvim=true" class="btn btn-secondary flex items-center gap-2">
                <i class="fas fa-calendar-days text-slate-500"></i>
                <span class="text-sm">Takvim</span>
            </button>
        </div>
    </div>

    <!-- Özet Kartları -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="stat-card border-l-4 border-l-blue-400">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bu Ay Planlı</p>
            <p class="text-3xl font-bold text-blue-600 mt-1" x-text="buAyListe.length"></p>
        </div>
        <div class="stat-card border-l-4 border-l-red-400">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Önceki Ay Gecikmişler</p>
            <p class="text-3xl font-bold text-red-600 mt-1" x-text="gecikListe.length"></p>
        </div>
        <div class="stat-card border-l-4 border-l-emerald-400">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Bekleyen</p>
            <p class="text-3xl font-bold text-emerald-600 mt-1" x-text="buAyListe.length + gecikListe.length"></p>
        </div>
    </div>

    <!-- ===== BU AYA AİT BAKIMLAR ===== -->
    <div class="card overflow-hidden mb-6">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fas fa-calendar-check text-blue-500"></i>
                <span x-text="ayLabel + ' Bakımları'"></span>
            </h3>
            <span class="badge badge-blue" x-text="buAyListe.length + ' kayıt'"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>Son Bakım</th>
                        <th>Planlanan Tarih</th>
                        <th class="text-center">Periyot</th>
                        <th class="text-center">Kalan / Geçen</th>
                        <th>Durum</th>
                        <th class="text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400">
                            <div class="spinner mx-auto mb-2"></div>Yükleniyor...
                        </td></tr>
                    </template>
                    <template x-if="!loading && buAyListe.length === 0">
                        <tr><td colspan="7" class="text-center py-10 text-slate-400">
                            <i class="fas fa-calendar-check text-3xl mb-2 block text-slate-200"></i>
                            Bu ay için planlı bakım yok
                        </td></tr>
                    </template>
                    <template x-for="b in buAyListe" :key="b.musteri_id">
                        <tr :class="b.bakim_durumu === 'gecikmis' ? 'bg-red-50' : b.bakim_durumu === 'yakin' ? 'bg-amber-50' : ''">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 bg-blue-100">
                                        <span class="font-semibold text-sm text-blue-600" x-text="(b.ad||'?')[0].toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800" x-text="`${b.ad} ${b.soyad}`"></p>
                                        <p class="text-xs text-slate-400" x-text="b.telefon || ''"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm text-slate-500" x-text="formatDate(b.son_bakim_tarihi) || 'İlk bakım'"></td>
                            <td class="text-sm font-medium text-slate-700" x-text="formatDate(b.sonraki_bakim_tarihi)"></td>
                            <td class="text-center text-slate-600" x-text="`${b.periyot_ay} ay`"></td>
                            <td class="text-center">
                                <span class="font-semibold text-sm"
                                      :class="b.kalan_gun < 0 ? 'text-red-600' : b.kalan_gun <= 14 ? 'text-amber-600' : 'text-emerald-600'"
                                      x-text="b.kalan_gun !== null ? (b.kalan_gun < 0 ? Math.abs(b.kalan_gun)+' gün geçti' : b.kalan_gun+' gün kaldı') : '—'">
                                </span>
                            </td>
                            <td x-html="durumBadge(b.bakim_durumu)"></td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-success" @click="tamamla(b)">
                                        <i class="fas fa-check text-xs"></i> Tamamlandı
                                    </button>
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="ayarla(b)">
                                        <i class="fas fa-gear text-slate-500 text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== ÖNCEKİ AYLARDAN GECİKMİŞLER ===== -->
    <div class="card overflow-hidden" x-show="gecikListe.length > 0 || !loading">
        <div class="flex items-center justify-between px-5 py-4 border-b border-red-100" style="background:#fff5f5;">
            <h3 class="font-semibold text-red-700 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                Önceki Aylardan Gecikmişler
            </h3>
            <span class="badge badge-red" x-text="gecikListe.length + ' kayıt'"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>Son Bakım</th>
                        <th>Olması Gereken Tarih</th>
                        <th class="text-center">Periyot</th>
                        <th class="text-center">Kaç Gün Geçti</th>
                        <th class="text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="!loading && gecikListe.length === 0">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400">
                            <i class="fas fa-check-circle text-2xl mb-2 block text-emerald-300"></i>
                            Önceki aylardan gecikmiş bakım yok
                        </td></tr>
                    </template>
                    <template x-for="b in gecikListe" :key="b.musteri_id">
                        <tr class="bg-red-50">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                        <span class="font-semibold text-sm text-red-600" x-text="(b.ad||'?')[0].toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800" x-text="`${b.ad} ${b.soyad}`"></p>
                                        <p class="text-xs text-slate-400" x-text="b.telefon || ''"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm text-slate-500" x-text="formatDate(b.son_bakim_tarihi) || 'İlk bakım'"></td>
                            <td class="text-sm font-semibold text-red-600" x-text="formatDate(b.sonraki_bakim_tarihi)"></td>
                            <td class="text-center text-slate-600" x-text="`${b.periyot_ay} ay`"></td>
                            <td class="text-center">
                                <span class="font-semibold text-red-600 text-sm"
                                      x-text="b.kalan_gun !== null ? Math.abs(b.kalan_gun)+' gün' : '—'">
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-sm btn-success" @click="tamamla(b)">
                                        <i class="fas fa-check text-xs"></i> Tamamlandı
                                    </button>
                                    <button class="btn btn-sm btn-secondary btn-icon" @click="ayarla(b)">
                                        <i class="fas fa-gear text-slate-500 text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== AYARLAR MODAL ===== -->
    <div x-show="showAyar" x-cloak class="modal-backdrop" @click.self="showAyar=false">
        <div class="modal-box max-w-md">
            <div class="modal-header">
                <h3 class="font-semibold text-slate-800">Bakım Ayarları</h3>
                <button @click="showAyar=false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form @submit.prevent="saveAyar()" class="modal-body space-y-4">
                <div class="bg-slate-50 rounded-lg p-3 text-sm">
                    <p class="font-semibold text-slate-800" x-text="ayarForm.ad_soyad"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Periyot (Ay)</label>
                        <input type="number" class="form-input" min="1" max="60" x-model="ayarForm.periyot_ay">
                    </div>
                    <div>
                        <label class="form-label">Hatırlatma (Gün)</label>
                        <input type="number" class="form-input" min="1" max="90" x-model="ayarForm.hatirlatma_gun">
                    </div>
                </div>
                <div>
                    <label class="form-label">Son Bakım Tarihi</label>
                    <input type="date" class="form-input" x-model="ayarForm.son_bakim_tarihi">
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="bakimAktif" x-model="ayarForm.aktif" class="rounded">
                    <label for="bakimAktif" class="form-label mb-0 cursor-pointer">Periyodik bakım aktif</label>
                </div>
                <div>
                    <label class="form-label">Notlar</label>
                    <textarea class="form-textarea" rows="2" x-model="ayarForm.notlar"></textarea>
                </div>
                <div class="modal-footer px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="showAyar=false">İptal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner w-4 h-4"></span>
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- ===== TAKVİM MODAL ===== -->
    <div x-show="showTakvim" x-cloak class="modal-backdrop" @click.self="showTakvim=false" style="z-index:60;">
        <div class="modal-box" style="max-width:860px; max-height:90vh; overflow-y:auto;">
            <div class="modal-header">
                <div class="flex items-center gap-3">
                    <button @click="prevAy()" class="btn btn-secondary btn-icon btn-sm">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </button>
                    <h3 class="font-semibold text-slate-800 text-lg" x-text="ayLabel + ' — Bakım Takvimi'"></h3>
                    <button @click="nextAy()" class="btn btn-secondary btn-icon btn-sm">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
                <button @click="showTakvim=false" class="text-slate-400 hover:text-slate-600 ml-4">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <!-- Takvim legend -->
            <div class="flex items-center gap-4 px-4 pb-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Bu ay planlandı
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> Gecikmiş (önceki ay)
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> Bugün
                </span>
            </div>

            <!-- Takvim Grid -->
            <div class="px-4 pb-4">
                <!-- Gün başlıkları -->
                <div class="grid grid-cols-7 gap-1 mb-1">
                    <template x-for="gun in ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz']" :key="gun">
                        <div class="text-center text-xs font-semibold text-slate-400 py-1" x-text="gun"></div>
                    </template>
                </div>

                <!-- Takvim hücreleri -->
                <div class="grid grid-cols-7 gap-1">
                    <template x-for="(gun, idx) in takvimGunler" :key="idx">
                        <div class="min-h-20 rounded-lg border p-1.5"
                             :class="gun.bos ? 'bg-transparent border-transparent' :
                                     gun.bugun ? 'border-amber-300 bg-amber-50' :
                                     gun.bakimlar.length > 0 ? 'border-blue-200 bg-blue-50' :
                                     'border-slate-100 bg-white'">

                            <!-- Gün numarası -->
                            <div class="text-right mb-1" x-show="!gun.bos">
                                <span class="text-xs font-bold rounded-full w-5 h-5 inline-flex items-center justify-center"
                                      :class="gun.bugun ? 'bg-amber-400 text-white' : 'text-slate-500'"
                                      x-text="gun.gun"></span>
                            </div>

                            <!-- Bakım kartları -->
                            <template x-for="b in gun.bakimlar" :key="b.musteri_id">
                                <div class="mb-0.5 px-1.5 py-0.5 rounded text-xs font-medium truncate cursor-pointer"
                                     :class="b.gecikti ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-800'"
                                     :title="`${b.ad} ${b.soyad} — ${b.telefon || ''}`"
                                     @click="tamamlaConfirm(b)">
                                    <i class="fas fa-droplet text-xs mr-0.5" :class="b.gecikti ? 'text-red-400' : 'text-blue-400'"></i>
                                    <span x-text="b.ad + ' ' + (b.soyad||'')[0] + '.'"></span>
                                </div>
                            </template>

                            <!-- Gecikmişler (önceki ay - sadece ilk günde göster) -->
                            <template x-if="gun.gun === 1 && gecikListe.length > 0 && !gun.bos">
                                <div class="mt-1">
                                    <template x-for="b in gecikListe" :key="'g'+b.musteri_id">
                                        <div class="mb-0.5 px-1.5 py-0.5 rounded text-xs font-medium truncate cursor-pointer bg-red-100 text-red-700"
                                             :title="`GECİKMİŞ: ${b.ad} ${b.soyad} — ${formatDate(b.sonraki_bakim_tarihi)}`"
                                             @click="tamamlaConfirm(b)">
                                            <i class="fas fa-exclamation text-xs text-red-400 mr-0.5"></i>
                                            <span x-text="b.ad + ' ' + (b.soyad||'')[0] + '.'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Alt bilgi -->
            <div class="px-4 pb-4 flex items-center justify-between text-sm text-slate-500">
                <span>Bir müşteriye tıklayarak bakımı tamamlandı olarak işaretleyebilirsiniz.</span>
                <span>
                    <span class="font-semibold text-blue-600" x-text="buAyListe.length"></span> bu ay,
                    <span class="font-semibold text-red-600" x-text="gecikListe.length"></span> gecikmiş
                </span>
            </div>
        </div>
    </div>


</div>

<script>
function bakimlarApp() {
    return {
        liste: [],
        loading: false,
        search: '',
        seciliAy: '<?= date('Y-m') ?>',
        showTakvim: false,
        showAyar: false,
        saving: false,
        ayarForm: { musteri_id: null, ad_soyad: '', periyot_ay: 6, hatirlatma_gun: 7, son_bakim_tarihi: '', aktif: true, notlar: '' },

        get ayLabel() {
            const ayAdlari = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
            const [yil, ay] = this.seciliAy.split('-');
            return ayAdlari[parseInt(ay) - 1] + ' ' + yil;
        },

        // Seçili ayın ilk ve son günü
        get ayBaslangic() { return this.seciliAy + '-01'; },
        get ayBitis() {
            const [y, m] = this.seciliAy.split('-').map(Number);
            return new Date(y, m, 0).toISOString().slice(0, 10); // ayın son günü
        },

        // Bu aya ait bakımlar (sonraki_bakim_tarihi bu ay içinde)
        get buAyListe() {
            const q = this.search.toLowerCase();
            return this.liste.filter(b => {
                if (!b.sonraki_bakim_tarihi) return false;
                const t = b.sonraki_bakim_tarihi;
                const match = t >= this.ayBaslangic && t <= this.ayBitis;
                const searchOk = !q || `${b.ad} ${b.soyad}`.toLowerCase().includes(q) || (b.telefon||'').includes(q);
                return match && searchOk;
            }).sort((a,b) => a.sonraki_bakim_tarihi.localeCompare(b.sonraki_bakim_tarihi));
        },

        // Önceki aylardan gecikmiş (sonraki_bakim_tarihi < bu ayın başı)
        get gecikListe() {
            const q = this.search.toLowerCase();
            return this.liste.filter(b => {
                if (!b.sonraki_bakim_tarihi) return false;
                const match = b.sonraki_bakim_tarihi < this.ayBaslangic;
                const searchOk = !q || `${b.ad} ${b.soyad}`.toLowerCase().includes(q) || (b.telefon||'').includes(q);
                return match && searchOk;
            }).sort((a,b) => a.sonraki_bakim_tarihi.localeCompare(b.sonraki_bakim_tarihi));
        },

        // Takvim grid verisi: 7 sütunlu, ayın günleri + boş hücreler
        get takvimGunler() {
            const [y, m] = this.seciliAy.split('-').map(Number);
            const ilkGun = new Date(y, m - 1, 1);
            const sonGun = new Date(y, m, 0);
            const ayGunSayisi = sonGun.getDate();

            // Pazartesi=0 ... Pazar=6 (JS: 0=Pazar, 1=Pzt)
            let baslangicOffset = (ilkGun.getDay() + 6) % 7; // Pzt bazlı

            const gunler = [];
            // Boş hücreler (önceki ay)
            for (let i = 0; i < baslangicOffset; i++) {
                gunler.push({ bos: true, gun: null, bakimlar: [], bugun: false });
            }

            const bugunStr = new Date().toISOString().slice(0, 10);

            for (let g = 1; g <= ayGunSayisi; g++) {
                const tarihStr = y + '-' + String(m).padStart(2,'0') + '-' + String(g).padStart(2,'0');
                // Bu güne ait bakımlar
                const bakimlar = this.liste.filter(b => b.sonraki_bakim_tarihi === tarihStr);
                gunler.push({
                    bos: false,
                    gun: g,
                    tarih: tarihStr,
                    bugun: tarihStr === bugunStr,
                    bakimlar,
                });
            }

            // Sona boş hücreler (7'nin katına tamamla)
            while (gunler.length % 7 !== 0) {
                gunler.push({ bos: true, gun: null, bakimlar: [], bugun: false });
            }

            return gunler;
        },

        async tamamlaConfirm(b) {
            if (!confirm(`"${b.ad} ${b.soyad}" için bakım tamamlandı olarak işaretlensin mi?\nSonraki bakım tarihi otomatik güncellenecek.`)) return;
            try {
                await api(`api/bakimlar.php?musteri_id=${b.musteri_id}`, { method: 'POST', body: { tamamlandi: true } });
                showToast('Bakım tamamlandı! Sonraki bakım tarihi güncellendi.', 'success');
                await this.loadListe();
            } catch(e) {}
        },

        async init() { await this.loadListe(); },

        async loadListe() {
            this.loading = true;
            try { this.liste = await api('api/bakimlar.php?liste=1'); }
            catch(e) {}
            finally { this.loading = false; }
        },

        prevAy() {
            const [y, m] = this.seciliAy.split('-').map(Number);
            const d = new Date(y, m - 2, 1);
            this.seciliAy = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        },

        nextAy() {
            const [y, m] = this.seciliAy.split('-').map(Number);
            const d = new Date(y, m, 1);
            this.seciliAy = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        },

        ayarla(b) {
            this.ayarForm = {
                musteri_id: b.musteri_id,
                ad_soyad: `${b.ad} ${b.soyad}`,
                periyot_ay: b.periyot_ay || 6,
                hatirlatma_gun: b.hatirlatma_gun || 7,
                son_bakim_tarihi: b.son_bakim_tarihi || '',
                aktif: !!b.aktif,
                notlar: b.notlar || '',
            };
            this.showAyar = true;
        },

        async saveAyar() {
            this.saving = true;
            try {
                await api(`api/bakimlar.php?musteri_id=${this.ayarForm.musteri_id}`, { method: 'PUT', body: this.ayarForm });
                showToast('Bakım ayarları kaydedildi.', 'success');
                this.showAyar = false;
                await this.loadListe();
            } catch(e) {} finally { this.saving = false; }
        },

        async tamamla(b) {
            if (!confirm(`"${b.ad} ${b.soyad}" için bakım tamamlandı olarak işaretlensin mi?`)) return;
            try {
                await api(`api/bakimlar.php?musteri_id=${b.musteri_id}`, { method: 'POST', body: { tamamlandi: true } });
                showToast('Bakım tamamlandı! Sonraki bakım tarihi güncellendi.', 'success');
                await this.loadListe();
            } catch(e) {}
        },

        formatDate, durumBadge,
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
