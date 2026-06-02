<?php if (getenv("STP_DATA_DIR")) { header("Location: index.php"); exit; } ?>
<?php
session_start();
if (isset($_SESSION['firma_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol — Servis Takip Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .bg-pattern {
            background-color: #f0f9ff;
            background-image: radial-gradient(#bae6fd 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4 py-10">

    <div class="w-full max-w-lg">

        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="landing.php" class="inline-flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-clipboard-check text-white text-xl"></i>
                </div>
                <div class="text-left">
                    <div class="text-xl font-bold text-slate-800">Servis Takip Panel</div>
                    <div class="text-xs text-slate-500">Su Arıtma Servis Yönetimi</div>
                </div>
            </a>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8">

            <h1 class="text-2xl font-bold text-slate-800 mb-1">Ücretsiz Hesap Oluşturun</h1>
            <p class="text-slate-500 text-sm mb-7">Kredi kartı gerekmez. Hemen başlayın.</p>

            <!-- Hata / Başarı mesajı -->
            <div id="alertBox" class="hidden mb-4 p-3 rounded-xl text-sm flex items-center gap-2"></div>

            <form id="kayitForm" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Firma / İşletme Adı <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-building absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                            <input type="text" name="firma_adi" id="firma_adi" required
                                placeholder="Örn: ABC Su Arıtma Servisi"
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Ad Soyad <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                            <input type="text" name="ad_soyad" id="ad_soyad" required
                                placeholder="Adınız Soyadınız"
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            E-posta Adresi <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                            <input type="email" name="email" id="email" required
                                placeholder="ornek@firma.com"
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Telefon
                        </label>
                        <div class="relative">
                            <i class="fas fa-phone absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                            <input type="tel" name="telefon" id="telefon"
                                placeholder="05XX XXX XX XX"
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <!-- boşluk -->
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Şifre <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                            <input type="password" name="sifre" id="sifre" required
                                placeholder="En az 6 karakter"
                                class="w-full pl-10 pr-11 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                            <button type="button" onclick="toggleSifre('sifre','eye1')"
                                class="absolute right-3.5 top-3 text-slate-400 hover:text-slate-600 p-0.5">
                                <i id="eye1" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Şifre Tekrar <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                            <input type="password" name="sifre2" id="sifre2" required
                                placeholder="Şifreyi tekrar girin"
                                class="w-full pl-10 pr-11 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                            <button type="button" onclick="toggleSifre('sifre2','eye2')"
                                class="absolute right-3.5 top-3 text-slate-400 hover:text-slate-600 p-0.5">
                                <i id="eye2" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Şifre güç göstergesi -->
                <div id="sifreGuc" class="hidden">
                    <div class="flex gap-1 mt-1">
                        <div id="guc1" class="h-1 flex-1 rounded-full bg-slate-200 transition"></div>
                        <div id="guc2" class="h-1 flex-1 rounded-full bg-slate-200 transition"></div>
                        <div id="guc3" class="h-1 flex-1 rounded-full bg-slate-200 transition"></div>
                        <div id="guc4" class="h-1 flex-1 rounded-full bg-slate-200 transition"></div>
                    </div>
                    <p id="gucText" class="text-xs text-slate-400 mt-1"></p>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition flex items-center justify-center gap-2 text-sm mt-2">
                    <i class="fas fa-user-plus"></i>
                    Hesap Oluştur
                </button>
            </form>

            <p class="text-xs text-slate-400 text-center mt-4">
                Kayıt olarak <a href="#" class="text-blue-600 hover:underline">Kullanım Koşulları</a>'nı kabul etmiş olursunuz.
            </p>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <span class="text-slate-500 text-sm">Zaten hesabınız var mı? </span>
                <a href="giris.php" class="text-blue-600 font-semibold text-sm hover:underline">Giriş Yapın</a>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="landing.php" class="text-slate-400 text-sm hover:text-slate-600 transition">
                <i class="fas fa-arrow-left mr-1"></i>Ana sayfaya dön
            </a>
        </div>
    </div>

    <script>
    function toggleSifre(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'fas fa-eye text-sm' : 'fas fa-eye-slash text-sm';
    }

    // Şifre güç göstergesi
    document.getElementById('sifre').addEventListener('input', function() {
        const val = this.value;
        const gucDiv = document.getElementById('sifreGuc');
        if (!val) { gucDiv.classList.add('hidden'); return; }
        gucDiv.classList.remove('hidden');

        let skor = 0;
        if (val.length >= 6)  skor++;
        if (val.length >= 10) skor++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) skor++;
        if (/[0-9]/.test(val) || /[^a-zA-Z0-9]/.test(val)) skor++;

        const renkler = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-green-500'];
        const etiketler = ['Çok zayıf','Zayıf','Orta','Güçlü'];
        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById('guc' + i);
            el.className = 'h-1 flex-1 rounded-full transition ' + (i <= skor ? renkler[skor-1] : 'bg-slate-200');
        }
        document.getElementById('gucText').textContent = etiketler[skor-1] || '';
    });

    document.getElementById('kayitForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn   = document.getElementById('submitBtn');
        const alert = document.getElementById('alertBox');

        const sifre  = document.getElementById('sifre').value;
        const sifre2 = document.getElementById('sifre2').value;
        if (sifre !== sifre2) {
            showAlert('Şifreler eşleşmiyor.', 'error');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Hesap oluşturuluyor...';
        alert.classList.add('hidden');

        try {
            const res = await fetch('api/auth.php?action=kayit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    firma_adi: document.getElementById('firma_adi').value,
                    ad_soyad:  document.getElementById('ad_soyad').value,
                    email:     document.getElementById('email').value,
                    telefon:   document.getElementById('telefon').value,
                    sifre,
                    sifre2,
                })
            });
            const data = await res.json();
            if (data.success) {
                showAlert('Hesabınız oluşturuldu! Yönlendiriliyorsunuz...', 'success');
                setTimeout(() => { window.location.href = 'index.php'; }, 1200);
            } else {
                showAlert(data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-plus"></i> Hesap Oluştur';
            }
        } catch(err) {
            showAlert('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-plus"></i> Hesap Oluştur';
        }
    });

    function showAlert(msg, type) {
        const box = document.getElementById('alertBox');
        box.classList.remove('hidden','bg-red-50','border-red-200','text-red-700','bg-green-50','border-green-200','text-green-700');
        if (type === 'error') {
            box.className = 'mb-4 p-3 rounded-xl text-sm flex items-center gap-2 bg-red-50 border border-red-200 text-red-700';
            box.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + msg + '</span>';
        } else {
            box.className = 'mb-4 p-3 rounded-xl text-sm flex items-center gap-2 bg-green-50 border border-green-200 text-green-700';
            box.innerHTML = '<i class="fas fa-check-circle"></i><span>' + msg + '</span>';
        }
    }
    </script>
</body>
</html>
