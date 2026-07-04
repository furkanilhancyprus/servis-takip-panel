<?php
session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';
Database::getInstance();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum - Servis Takip Panel</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 grid place-items-center p-4">
    <main class="w-full max-w-md">
        <a href="landing.php" class="inline-flex items-center gap-3 mb-6">
            <span class="w-11 h-11 rounded-2xl bg-blue-600 text-white grid place-items-center shadow-lg">
                <i class="fas fa-clipboard-check"></i>
            </span>
            <span>
                <span class="block font-bold text-slate-900">Servis Takip Panel</span>
                <span class="block text-xs text-slate-500">Hesap kurtarma</span>
            </span>
        </a>

        <section class="bg-white rounded-2xl border border-slate-200 shadow-xl p-7">
            <h1 class="text-2xl font-bold text-slate-900">Şifremi unuttum</h1>
            <p class="text-sm text-slate-500 mt-1 mb-6">E-posta adresinizi yazın. Talebiniz admin paneline düşer, destek ekibi size güvenli sıfırlama linkini iletir.</p>

            <div id="msgBox" class="hidden mb-4 rounded-xl px-4 py-3 text-sm"></div>

            <form id="forgotForm" class="space-y-4">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">E-posta adresi</span>
                    <input id="email" type="email" required placeholder="ornek@firma.com"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </label>
                <button id="submitBtn" class="w-full rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 transition">
                    Talep Gönder
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <a href="giris.php" class="text-sm font-semibold text-blue-600 hover:underline">Giriş ekranına dön</a>
            </div>
        </section>
    </main>

    <script>
    const box = document.getElementById('msgBox');
    function showMsg(text, ok) {
        box.textContent = text;
        box.className = 'mb-4 rounded-xl px-4 py-3 text-sm ' + (ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
    }
    document.getElementById('forgotForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Gönderiliyor...';
        try {
            const res = await fetch('api/auth.php?action=sifre_talep', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: document.getElementById('email').value })
            });
            const data = await res.json();
            showMsg(data.message || (data.success ? 'Talep alındı.' : 'İşlem başarısız.'), !!data.success);
            if (data.success) e.target.reset();
        } catch (err) {
            showMsg('Bağlantı hatası. Lütfen tekrar deneyin.', false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Talep Gönder';
        }
    });
    </script>
</body>
</html>
