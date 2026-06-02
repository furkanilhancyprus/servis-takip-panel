        </main>
    </div><!-- /.flex-1 -->
</div><!-- /.flex.h-screen -->

<script>
// ===== GLOBAL HELPERS =====
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle' };
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all .3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

async function api(url, options = {}) {
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...options.headers },
            ...options,
            body: options.body ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : undefined,
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Bir hata oluştu.');
        }
        return data.data;
    } catch (e) {
        showToast(e.message, 'error');
        throw e;
    }
}

function formatDate(d) {
    if (!d) return '-';
    try { return new Date(d).toLocaleDateString('tr-TR', {day:'2-digit',month:'2-digit',year:'numeric'}); }
    catch(e) { return d; }
}

function formatCurrency(n) {
    return Number(n || 0).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺';
}

function formatTip(t) {
    return t === 'ariza' ? 'Arıza' : t === 'periyodik_bakim' ? 'Periyodik Bakım' : t || '-';
}

function formatOdemeYontemi(y) {
    const map = { nakit: 'Nakit', kart: 'Kart', havale: 'Havale/EFT', cek: 'Çek' };
    return map[y] || y || '-';
}

function durumBadge(durum) {
    const map = {
        'gecikmis':  ['badge-red',    'Gecikmiş'],
        'yakin':     ['badge-yellow', 'Yaklaşıyor'],
        'normal':    ['badge-green',  'İyi'],
        'ayarsiz':   ['badge-gray',   'Ayarsız'],
        'tamamlanan':['badge-green',  'Tamamlandı'],
    };
    const [cls, label] = map[durum] || ['badge-gray', durum || '-'];
    return `<span class="badge ${cls}">${label}</span>`;
}

<?php if (getenv('STP_DATA_DIR') && getenv('STP_LOCAL_ONLY') !== '1'): ?>
async function desktopAutoSync() {
    try {
        const statusRes = await fetch('api/sync_client.php?action=status');
        const status = await statusRes.json();
        if (!status.success || !status.data?.has_token) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        await fetch('api/sync_client.php?action=run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: '{}',
        });
    } catch (e) {
        // Offline usage is expected; auto sync stays silent.
    }
}

window.addEventListener('load', () => {
    setTimeout(desktopAutoSync, 5000);
    setInterval(desktopAutoSync, 5 * 60 * 1000);
});
<?php endif; ?>
</script>
</body>
</html>
