<?php
$pageTitle = 'Detaylı Finans Raporu';
$activePage = 'raporlar';
require_once ROOT . '/models/FinansRapor.php';

$baslangic = $_GET['baslangic'] ?? date('Y-m-01');
$bitis = $_GET['bitis'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $baslangic)) $baslangic = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis)) $bitis = date('Y-m-d');
$usdKur = max(0, (float)($_GET['usd_try'] ?? 0));

$rapor = (new FinansRapor())->getDetay($baslangic, $bitis, $usdKur);
$toplam = $rapor['toplamlar'];

$fmt = fn($v) => number_format((float)$v, 2, ',', '.') . ' ₺';
$dateFmt = fn($d) => $d ? date('d.m.Y', strtotime((string)$d)) : '-';
$excelUrl = 'api/raporlar.php?tip=finans&baslangic=' . urlencode($baslangic)
    . '&bitis=' . urlencode($bitis)
    . '&usd_try=' . urlencode((string)$usdKur);

include __DIR__ . '/layout/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Detaylı Finans Raporu</h2>
            <p class="text-sm text-slate-500 mt-1">
                <?= htmlspecialchars($dateFmt($baslangic)) ?> - <?= htmlspecialchars($dateFmt($bitis)) ?>
                <?php if ($usdKur > 0): ?>
                    · USD kuru: <?= htmlspecialchars(number_format($usdKur, 4, ',', '.')) ?> ₺
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="?page=raporlar" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Raporlara Dön
            </a>
            <a href="<?= htmlspecialchars($excelUrl) ?>" target="_blank" class="btn btn-primary">
                <i class="fas fa-file-excel"></i> Excel İndir
            </a>
        </div>
    </div>

    <form method="get" class="card p-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="finans_raporu">
        <div>
            <label class="form-label">Başlangıç</label>
            <input type="date" class="form-input" name="baslangic" value="<?= htmlspecialchars($baslangic) ?>">
        </div>
        <div>
            <label class="form-label">Bitiş</label>
            <input type="date" class="form-input" name="bitis" value="<?= htmlspecialchars($bitis) ?>">
        </div>
        <div>
            <label class="form-label">USD Kuru</label>
            <input type="number" class="form-input" name="usd_try" step="0.0001" min="0" value="<?= htmlspecialchars((string)$usdKur) ?>">
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-calculator"></i> Hesapla
        </button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Ciro</p>
            <p class="text-2xl font-bold text-slate-800 mt-1"><?= $fmt($toplam['toplam_ciro']) ?></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Toplam Maliyet</p>
            <p class="text-2xl font-bold text-orange-600 mt-1"><?= $fmt($toplam['toplam_maliyet']) ?></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Net Kar</p>
            <p class="text-2xl font-bold <?= $toplam['toplam_kar'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?> mt-1"><?= $fmt($toplam['toplam_kar']) ?></p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Gerçek Tahsilat</p>
            <p class="text-2xl font-bold text-blue-600 mt-1"><?= $fmt($toplam['toplam_tahsilat']) ?></p>
        </div>
    </div>

    <?php
    $renderTotals = function (string $title, array $values, string $accent) use ($fmt): void {
        ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <p class="text-xs font-semibold text-slate-500 uppercase"><?= htmlspecialchars($title) ?> Ciro</p>
                <p class="text-lg font-bold text-slate-800 mt-1"><?= $fmt($values['ciro']) ?></p>
            </div>
            <div class="rounded-lg border border-orange-100 bg-orange-50 p-3">
                <p class="text-xs font-semibold text-orange-600 uppercase">Maliyet</p>
                <p class="text-lg font-bold text-orange-700 mt-1"><?= $fmt($values['maliyet']) ?></p>
            </div>
            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                <p class="text-xs font-semibold text-emerald-600 uppercase">Net Kar</p>
                <p class="text-lg font-bold text-emerald-700 mt-1"><?= $fmt($values['kar']) ?></p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                <p class="text-xs font-semibold text-blue-600 uppercase">Tahsilat</p>
                <p class="text-lg font-bold text-blue-700 mt-1"><?= $fmt($values['tahsilat']) ?></p>
            </div>
        </div>
        <?php
    };

    $renderTable = function (array $rows) use ($fmt, $dateFmt): void {
        ?>
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Kayıt</th>
                        <th>Müşteri</th>
                        <th>İşlem / Ürün</th>
                        <th>Tip</th>
                        <th class="text-right">Ciro</th>
                        <th class="text-right">Maliyet</th>
                        <th class="text-right">Net Kar</th>
                        <th class="text-right">Tahsilat</th>
                        <th>Not</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" class="text-center py-8 text-slate-400">Bu aralıkta kayıt yok.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($dateFmt($row['tarih'])) ?></td>
                        <td>#<?= (int)$row['no'] ?></td>
                        <td>
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($row['musteri_adi']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($row['telefon']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($row['aciklama']) ?></td>
                        <td><?= htmlspecialchars($row['tip']) ?></td>
                        <td class="text-right font-semibold"><?= $fmt($row['ciro']) ?></td>
                        <td class="text-right text-orange-700"><?= $fmt($row['maliyet']) ?></td>
                        <td class="text-right font-semibold <?= $row['net_kar'] >= 0 ? 'text-emerald-700' : 'text-red-600' ?>"><?= $fmt($row['net_kar']) ?></td>
                        <td class="text-right text-blue-700"><?= $fmt($row['tahsilat']) ?></td>
                        <td><?= htmlspecialchars($row['notlar'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    };
    ?>

    <section class="card p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="font-semibold text-slate-800">Satışlar</h3>
            <span class="badge badge-blue"><?= count($rapor['satislar']) ?> kayıt</span>
        </div>
        <?php $renderTotals('Satış', [
            'ciro' => $toplam['satis_ciro'],
            'maliyet' => $toplam['satis_maliyet'],
            'kar' => $toplam['satis_kar'],
            'tahsilat' => $toplam['satis_tahsilat'],
        ], 'blue'); ?>
        <?php $renderTable($rapor['satislar']); ?>
    </section>

    <section class="card p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="font-semibold text-slate-800">Servisler</h3>
            <span class="badge badge-green"><?= count($rapor['servisler']) ?> kayıt</span>
        </div>
        <?php $renderTotals('Servis', [
            'ciro' => $toplam['servis_ciro'],
            'maliyet' => $toplam['servis_maliyet'],
            'kar' => $toplam['servis_kar'],
            'tahsilat' => $toplam['servis_tahsilat'],
        ], 'green'); ?>
        <?php $renderTable($rapor['servisler']); ?>
    </section>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
