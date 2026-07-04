<?php
session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';
Database::getInstance();
$token = trim($_GET['token'] ?? '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırla - Servis Takip Panel</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 grid place-items-center p-4">
    <main class="w-full max-w-md">
        <section class="bg-white rounded-2xl border border-slate-200 shadow-xl p-7">
            <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white grid place-items-center mb-5">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Yeni şifre belirle</h1>
            <p id="accountInfo" class="text-sm text-slate-500 mt-1 mb-6">Bağlantı kontrol ediliyor...</p>

            <div id="msgBox" class="hidden mb-4 rounded-xl px-4 py-3 text-sm"></div>

            <form id="resetForm" class="space-y-4 hidden">
                <input type="hidden" id="token" value="<?= htmlspecialchars($token) ?>">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Yeni şifre</span>
                    <input id="sifre" type="password" required minlength="6"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Yeni şifre tekrar</span>
                    <input id="sifre2" type="password" required minlength="6"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </label>
                <button id="submitBtn" class="w-full rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 transition">
                    Şifreyi Güncelle
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <a href="giris.php" class="text-sm font-semibold text-blue-600 hover:underline">Giriş ekranına dön</a>
            </div>
        </section>
    </main>

    <script>
    const token = document.getElementById('token')?.value || '';
    const box = document.getElementById('msgBox');
    const form = document.getElementById('resetForm');
    const accountInfo = document.getElementById('accountInfo');
    function showMsg(text, ok) {
        box.textContent = text;
        box.className = 'mb-4 rounded-xl px-4 py-3 text-sm ' + (ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
    }
    async function checkToken() {
        if (!token) {
            accountInfo.textContent = 'Sıfırlama bağlantısı eksik.';
            showMsg('Lütfen size gönderilen bağlantıyı tekrar açın.', false);
            return;
        }
        try {
            const res = await fetch('api/auth.php?action=reset_kontrol&token=' + encodeURIComponent(token));
            const data = await res.json();
            if (!data.success) {
                accountInfo.textContent = 'Bağlantı geçersiz.';
                showMsg(data.message || 'Sıfırlama bağlantısı kullanılamıyor.', false);
                return;
            }
            accountInfo.textContent = `${data.data.firma_adi} hesabı için yeni şifre oluşturun.`;
            form.classList.remove('hidden');
        } catch (err) {
            accountInfo.textContent = 'Bağlantı kontrol edilemedi.';
            showMsg('Bağlantı hatası. Lütfen tekrar deneyin.', false);
        }
    }
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const sifre = document.getElementById('sifre').value;
        const sifre2 = document.getElementById('sifre2').value;
        if (sifre !== sifre2) {
            showMsg('Şifreler eşleşmiyor.', false);
            return;
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Güncelleniyor...';
        try {
            const res = await fetch('api/auth.php?action=sifre_sifirla', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token, sifre, sifre2 })
            });
            const data = await res.json();
            showMsg(data.message || (data.success ? 'Şifre güncellendi.' : 'İşlem başarısız.'), !!data.success);
            if (data.success) {
                form.classList.add('hidden');
                setTimeout(() => { window.location.href = 'giris.php'; }, 1800);
            }
        } catch (err) {
            showMsg('Bağlantı hatası. Lütfen tekrar deneyin.', false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Şifreyi Güncelle';
        }
    });
    checkToken();
    </script>
</body>
</html>
