<?php
/**
 * Fiyat Teklifi / Fatura Sayfası
 * Kullanım: fiyat_teklifi.php?tip=satis&id=X  veya  ?tip=servis&id=X
 */
session_start();
define('ROOT', __DIR__);

// Oturum kontrolü
if (!isset($_SESSION['firma_id'])) {
    header('Location: giris.php');
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/models/Ayarlar.php';

$tip = in_array($_GET['tip'] ?? '', ['satis', 'servis']) ? $_GET['tip'] : 'satis';
$id  = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Geçersiz ID.'); }

// Ayarları yükle
$ayarlarModel = new Ayarlar();
$ayarlar      = $ayarlarModel->getAll();

$firmaAdi     = $ayarlar['firma_adi']      ?? 'Firma';
$firmaTel     = $ayarlar['firma_telefon']  ?? '';
$firmaAdres   = $ayarlar['firma_adres']    ?? '';
$firmaEmail   = $ayarlar['firma_email']    ?? '';
$firmaVergiNo = $ayarlar['firma_vergi_no'] ?? '';
$firmaIban    = $ayarlar['firma_iban']     ?? '';
$faturaLogo   = $ayarlar['fatura_logo']    ?? '';
$faturaNotu   = $ayarlar['fatura_notu']    ?? 'Ödeme için teşekkür ederiz.';
$paraBirimi   = $ayarlar['para_birimi']    ?? '₺';

// Veri yükle
$kayit = null;
if ($tip === 'satis') {
    require_once ROOT . '/models/Satis.php';
    $model = new Satis();
    $kayit = $model->getById($id);
} else {
    require_once ROOT . '/models/Servis.php';
    $model = new Servis();
    $kayit = $model->getById($id);
}
if (!$kayit) { http_response_code(404); exit('Kayıt bulunamadı.'); }

// Ortak alanlar
$musteriAdi  = ($kayit['ad'] ?? '') . ' ' . ($kayit['soyad'] ?? '');
$musteriTel  = $kayit['telefon'] ?? '';
$musteriAdres= $kayit['adres'] ?? '';

$tarih       = $tip === 'satis' ? ($kayit['satis_tarihi'] ?? '') : ($kayit['tamamlanma_tarihi'] ?? '');
$toplamTutar = (float)($kayit['toplam_tutar'] ?? 0);
$odenenTutar = (float)($kayit['odenen_tutar'] ?? 0);
$kalanTutar  = $toplamTutar - $odenenTutar;

// Belge no
$belgeNo = ($tip === 'satis' ? 'SAT-' : 'SRV-') . str_pad($id, 5, '0', STR_PAD_LEFT);

// Para biçimi
function paraBicimi(float $tutar, string $pb = '₺'): string {
    return number_format($tutar, 2, ',', '.') . ' ' . $pb;
}

function formatTarih(string $d): string {
    if (!$d) return '—';
    [$y,$m,$g] = explode('-', $d);
    return "$g.$m.$y";
}

// Kalemler (her iki tip için normalize)
$kalemler = [];
if ($tip === 'satis') {
    foreach (($kayit['kalemler'] ?? []) as $k) {
        $kalemler[] = [
            'aciklama' => $k['urun_adi'],
            'miktar'   => $k['miktar'],
            'birim'    => 'Adet',
            'fiyat'    => (float)$k['birim_fiyat'],
            'toplam'   => $k['miktar'] * (float)$k['birim_fiyat'],
        ];
    }
    if (empty($kalemler)) {
        $cihazAdi = '';
        if (!empty($kayit['cihaz_adi'])) {
            $cihazAdi = ($kayit['cihaz_marka'] ? $kayit['cihaz_marka'].' ' : '') . $kayit['cihaz_adi'];
            if (!empty($kayit['seri_no'])) $cihazAdi .= ' (S/N: '.$kayit['seri_no'].')';
        }
        $kalemler[] = [
            'aciklama' => $cihazAdi ?: 'Ürün/Hizmet',
            'miktar'   => 1,
            'birim'    => 'Adet',
            'fiyat'    => $toplamTutar,
            'toplam'   => $toplamTutar,
        ];
    }
} else {
    foreach (($kayit['islemler'] ?? []) as $i) {
        $kalemler[] = [
            'aciklama' => $i['islem'],
            'miktar'   => 1,
            'birim'    => 'İşlem',
            'fiyat'    => (float)$i['tutar'],
            'toplam'   => (float)$i['tutar'],
        ];
    }
    foreach (($kayit['parcalar'] ?? []) as $p) {
        $aciklama = ($p['marka'] ? $p['marka'].' ' : '') . $p['parca_adi'];
        $kalemler[] = [
            'aciklama' => $aciklama,
            'miktar'   => $p['miktar'],
            'birim'    => 'Adet',
            'fiyat'    => (float)$p['birim_fiyat'],
            'toplam'   => $p['miktar'] * (float)$p['birim_fiyat'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($belgeNo) ?> — <?= htmlspecialchars($firmaAdi) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
        }
        .page-wrapper {
            max-width: 820px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,.10);
            overflow: hidden;
        }

        /* ── Header ── */
        .doc-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%);
            color: #fff;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2rem;
        }
        .doc-header .logo { height: 48px; object-fit: contain; filter: brightness(0) invert(1); }
        .firm-name { font-size: 1.25rem; font-weight: 700; }
        .firm-details { font-size: .8rem; opacity: .75; margin-top: .25rem; line-height: 1.7; }
        .doc-meta { text-align: right; }
        .doc-title { font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; opacity: .65; }
        .doc-no { font-size: 2rem; font-weight: 800; letter-spacing: -.02em; margin: .1rem 0; }
        .doc-date { font-size: .85rem; opacity: .7; }

        /* ── Body ── */
        .doc-body { padding: 2rem 2.5rem; }

        /* Müşteri & Belge Bilgileri */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; }
        .info-box h4 { font-size: .7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .5rem; }
        .info-box p { font-size: .875rem; color: #334155; }
        .info-box .name { font-weight: 600; font-size: 1rem; color: #0f172a; margin-bottom: .15rem; }

        /* Kalemler tablosu */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        .items-table thead th {
            background: #0f172a; color: #fff;
            padding: .625rem 1rem; text-align: left;
            font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;
        }
        .items-table thead th:last-child { text-align: right; }
        .items-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .items-table tbody td { padding: .75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
        .items-table tbody td:last-child { text-align: right; font-weight: 600; }
        .items-table tfoot td { padding: .5rem 1rem; font-size: .875rem; }

        /* Toplamlar */
        .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 1.5rem; }
        .totals-box { width: 260px; }
        .totals-row { display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
        .totals-row:last-child { border-bottom: none; }
        .totals-row.grand { font-size: 1rem; font-weight: 700; color: #0f172a; padding: .6rem 0 0; }
        .totals-row.paid { color: #15803d; }
        .totals-row.due { color: #dc2626; font-weight: 700; font-size: 1rem; }

        /* Taksit planı */
        .taksit-section { margin-bottom: 1.5rem; }
        .taksit-section h4 { font-size: .8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .75rem; }
        .taksit-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        .taksit-table th { background: #f1f5f9; padding: .5rem .75rem; text-align: left; font-weight: 600; color: #475569; }
        .taksit-table td { padding: .45rem .75rem; border-bottom: 1px solid #f8fafc; }
        .taksit-table .odendi { color: #15803d; font-weight: 600; }
        .taksit-table .bekleyen { color: #dc2626; }

        /* Footer notu */
        .doc-note { background: #f8fafc; border-left: 4px solid #2563eb; border-radius: 0 8px 8px 0; padding: .875rem 1.25rem; margin-bottom: 1.5rem; font-size: .8rem; color: #475569; }

        /* IBAN */
        .iban-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: .75rem 1.25rem; margin-bottom: 1.5rem; font-size: .8rem; color: #1e40af; }
        .iban-box strong { font-weight: 700; }

        /* Print bar */
        .print-bar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: .75rem 2.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .print-bar a { color: #475569; text-decoration: none; font-size: .85rem; display: flex; align-items: center; gap: .4rem; }
        .print-bar a:hover { color: #1d4ed8; }
        .btn-print {
            background: #2563eb; color: #fff;
            border: none; cursor: pointer;
            padding: .5rem 1.25rem; border-radius: 8px;
            font-size: .875rem; font-weight: 600;
            display: flex; align-items: center; gap: .5rem;
            margin-left: auto;
        }
        .btn-print:hover { background: #1d4ed8; }

        .badge-odendi { display:inline-block; background:#dcfce7; color:#15803d; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:600; }
        .badge-bekleyen { display:inline-block; background:#fee2e2; color:#dc2626; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:600; }
        .badge-pesinat { display:inline-block; background:#dbeafe; color:#1d4ed8; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:600; }

        .doc-footer { padding: 1.5rem 2.5rem; border-top: 1px solid #f1f5f9; text-align: center; font-size: .75rem; color: #94a3b8; }

        @media print {
            body { background: #fff; }
            .print-bar { display: none !important; }
            .page-wrapper { margin: 0; border-radius: 0; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>

<!-- ── Yazdır / Geri çubuğu (ekranda görünür, baskıda gizlenir) ── -->
<div class="print-bar">
    <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Geri</a>
    <a href="?page=<?= $tip === 'satis' ? 'satislar' : 'servisler' ?>"><i class="fas fa-list"></i> Listeye Dön</a>
    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Yazdır / PDF</button>
</div>

<!-- ── Belge ── -->
<div class="page-wrapper">

    <!-- Başlık -->
    <div class="doc-header">
        <div>
            <?php if ($faturaLogo): ?>
                <img src="<?= htmlspecialchars($faturaLogo) ?>" class="logo" alt="Logo">
            <?php endif; ?>
            <div class="firm-name"><?= htmlspecialchars($firmaAdi) ?></div>
            <div class="firm-details">
                <?php if ($firmaTel): ?><i class="fas fa-phone" style="width:12px"></i> <?= htmlspecialchars($firmaTel) ?><br><?php endif; ?>
                <?php if ($firmaEmail): ?><i class="fas fa-envelope" style="width:12px"></i> <?= htmlspecialchars($firmaEmail) ?><br><?php endif; ?>
                <?php if ($firmaVergiNo): ?><i class="fas fa-hashtag" style="width:12px"></i> VKN: <?= htmlspecialchars($firmaVergiNo) ?><br><?php endif; ?>
                <?php if ($firmaAdres): ?>
                    <i class="fas fa-map-marker-alt" style="width:12px"></i> <?= nl2br(htmlspecialchars($firmaAdres)) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="doc-meta">
            <div class="doc-title"><?= $tip === 'satis' ? 'Satış Faturası' : 'Servis Faturası' ?></div>
            <div class="doc-no"><?= htmlspecialchars($belgeNo) ?></div>
            <?php if ($tarih): ?>
                <div class="doc-date"><i class="fas fa-calendar-alt"></i> <?= formatTarih($tarih) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="doc-body">

        <!-- Müşteri & Belge bilgileri -->
        <div class="info-grid">
            <div class="info-box">
                <h4><i class="fas fa-user"></i> Müşteri Bilgileri</h4>
                <p class="name"><?= htmlspecialchars($musteriAdi) ?></p>
                <?php if ($musteriTel): ?>
                    <p><i class="fas fa-phone fa-xs" style="color:#94a3b8"></i> <?= htmlspecialchars($musteriTel) ?></p>
                <?php endif; ?>
                <?php if (!empty($kayit['email'])): ?>
                    <p><i class="fas fa-envelope fa-xs" style="color:#94a3b8"></i> <?= htmlspecialchars($kayit['email']) ?></p>
                <?php endif; ?>
                <?php if ($musteriAdres): ?>
                    <p style="margin-top:.25rem; font-size:.8rem; color:#64748b"><?= nl2br(htmlspecialchars($musteriAdres)) ?></p>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <h4><i class="fas fa-file-alt"></i> Belge Bilgileri</h4>
                <p><span style="color:#64748b">Belge No:</span> <strong><?= htmlspecialchars($belgeNo) ?></strong></p>
                <p><span style="color:#64748b">Tarih:</span> <?= formatTarih($tarih) ?></p>
                <?php if ($tip === 'satis' && !empty($kayit['cihaz_adi'])): ?>
                    <p><span style="color:#64748b">Cihaz:</span>
                        <?= htmlspecialchars(($kayit['cihaz_marka'] ? $kayit['cihaz_marka'].' ' : '') . $kayit['cihaz_adi']) ?></p>
                    <?php if (!empty($kayit['cihaz_model'])): ?>
                        <p><span style="color:#64748b">Model:</span> <?= htmlspecialchars($kayit['cihaz_model']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($kayit['seri_no'])): ?>
                        <p><span style="color:#64748b">Seri No:</span> <?= htmlspecialchars($kayit['seri_no']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($tip === 'servis' && !empty($kayit['servis_tipi'])): ?>
                    <p><span style="color:#64748b">Servis Tipi:</span>
                        <?= $kayit['servis_tipi'] === 'ariza' ? 'Arıza Servis' : 'Periyodik Bakım' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kalemler -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Açıklama</th>
                    <th>Miktar</th>
                    <th>Birim Fiyat</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kalemler as $i => $k): ?>
                    <tr>
                        <td style="color:#94a3b8; font-size:.8rem"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($k['aciklama']) ?></td>
                        <td><?= htmlspecialchars($k['miktar']) ?> <?= htmlspecialchars($k['birim']) ?></td>
                        <td><?= paraBicimi($k['fiyat'], $paraBirimi) ?></td>
                        <td><?= paraBicimi($k['toplam'], $paraBirimi) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Toplamlar -->
        <div class="totals-wrap">
            <div class="totals-box">
                <div class="totals-row">
                    <span style="color:#64748b">Ara Toplam</span>
                    <span><?= paraBicimi($toplamTutar, $paraBirimi) ?></span>
                </div>
                <div class="totals-row grand">
                    <span>Genel Toplam</span>
                    <span><?= paraBicimi($toplamTutar, $paraBirimi) ?></span>
                </div>
                <?php if ($odenenTutar > 0): ?>
                    <div class="totals-row paid">
                        <span>Ödenen</span>
                        <span><?= paraBicimi($odenenTutar, $paraBirimi) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($kalanTutar > 0): ?>
                    <div class="totals-row due">
                        <span>Kalan Borç</span>
                        <span><?= paraBicimi($kalanTutar, $paraBirimi) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Taksit Planı (sadece taksitli satışlarda) -->
        <?php if ($tip === 'satis' && !empty($kayit['taksitler']) && ($kayit['odeme_turu'] ?? '') === 'taksitli'): ?>
            <div class="taksit-section">
                <h4><i class="fas fa-calendar-alt"></i> Taksit Planı</h4>
                <table class="taksit-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tür</th>
                            <th>Vade</th>
                            <th>Tutar</th>
                            <th>Ödeme Tarihi</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kayit['taksitler'] as $t): ?>
                            <tr>
                                <td><?= $t['taksit_no'] == 0 ? '—' : $t['taksit_no'] ?></td>
                                <td><?= $t['taksit_no'] == 0 ? '<span class="badge-pesinat">Peşinat</span>' : $t['taksit_no'].'. Taksit' ?></td>
                                <td><?= formatTarih($t['vade_tarihi'] ?? '') ?></td>
                                <td><?= paraBicimi((float)$t['tutar'], $paraBirimi) ?></td>
                                <td><?= $t['odeme_tarihi'] ? formatTarih($t['odeme_tarihi']) : '—' ?></td>
                                <td>
                                    <?php if ($t['odendi']): ?>
                                        <span class="badge-odendi">✓ Ödendi</span>
                                    <?php else: ?>
                                        <span class="badge-bekleyen">Bekliyor</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- IBAN -->
        <?php if ($firmaIban): ?>
            <div class="iban-box">
                <i class="fas fa-university"></i>
                <strong>Banka Bilgileri — IBAN:</strong> <?= htmlspecialchars($firmaIban) ?>
                &nbsp;&nbsp;|&nbsp;&nbsp; Hesap Sahibi: <?= htmlspecialchars($firmaAdi) ?>
            </div>
        <?php endif; ?>

        <!-- Fatura Notu -->
        <?php if ($faturaNotu): ?>
            <div class="doc-note">
                <i class="fas fa-info-circle" style="color:#2563eb; margin-right:.4rem"></i>
                <?= nl2br(htmlspecialchars($faturaNotu)) ?>
            </div>
        <?php endif; ?>

        <!-- Notlar -->
        <?php if (!empty($kayit['notlar'])): ?>
            <div class="doc-note" style="border-left-color:#94a3b8">
                <strong>Notlar:</strong> <?= nl2br(htmlspecialchars($kayit['notlar'])) ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="doc-footer">
        Bu belge <?= htmlspecialchars($firmaAdi) ?> tarafından <?= date('d.m.Y H:i') ?> tarihinde düzenlenmiştir.
    </div>
</div>

</body>
</html>
