<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Musteri.php';
require_once ROOT . '/models/Servis.php';
require_once ROOT . '/models/Parca.php';
require_once ROOT . '/models/PeriyodikBakim.php';
require_once ROOT . '/models/Ayarlar.php';
require_once ROOT . '/models/Satis.php';

$tip = $_GET['tip'] ?? '';

// ── Minimal pure-PHP XLSX üretici (ZipArchive gerektirir) ──────────────────
function xlsxResponse(array $headers, array $rows, string $filename, string $sheetTitle = 'Rapor'): void
{
    // --- Shared strings: tüm string hücreler buraya ---
    $strings = [];
    $strIdx  = [];
    $addStr  = function(string $s) use (&$strings, &$strIdx): int {
        if (!isset($strIdx[$s])) {
            $strIdx[$s] = count($strings);
            $strings[]  = $s;
        }
        return $strIdx[$s];
    };

    // Başlık satırı
    $headerRow = [];
    foreach ($headers as $h) {
        $headerRow[] = ['t' => 's', 'v' => $addStr((string)$h)];
    }

    // Veri satırları
    $dataRows = [];
    foreach ($rows as $row) {
        $cells = [];
        foreach ($row as $cell) {
            if (is_numeric($cell) && $cell !== '' && $cell !== null) {
                $cells[] = ['t' => 'n', 'v' => $cell];
            } else {
                $cells[] = ['t' => 's', 'v' => $addStr((string)($cell ?? ''))];
            }
        }
        $dataRows[] = $cells;
    }

    // ── XML üreticiler ─────────────────────────────────────────────────────
    $xe = function(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8'); };

    // Kolon harfi
    $colLetter = function(int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    };

    // worksheet/sheet1.xml
    $sheetXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>';

    $rowNum = 1;
    // Başlık satırı (s=1 = bold stil)
    $sheetXml .= "<row r=\"$rowNum\">";
    foreach ($headerRow as $ci => $cell) {
        $ref = $colLetter($ci) . $rowNum;
        $sheetXml .= "<c r=\"$ref\" t=\"s\" s=\"1\"><v>{$cell['v']}</v></c>";
    }
    $sheetXml .= '</row>';
    $rowNum++;

    foreach ($dataRows as $row) {
        $sheetXml .= "<row r=\"$rowNum\">";
        foreach ($row as $ci => $cell) {
            $ref = $colLetter($ci) . $rowNum;
            if ($cell['t'] === 'n') {
                $sheetXml .= "<c r=\"$ref\"><v>{$cell['v']}</v></c>";
            } else {
                $sheetXml .= "<c r=\"$ref\" t=\"s\"><v>{$cell['v']}</v></c>";
            }
        }
        $sheetXml .= '</row>';
        $rowNum++;
    }
    $sheetXml .= '</sheetData></worksheet>';

    // sharedStrings.xml
    $ssXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $s) {
        $ssXml .= '<si><t>' . $xe($s) . '</t></si>';
    }
    $ssXml .= '</sst>';

    // styles.xml — sadece Normal (0) + Bold (1)
    $stylesXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
        . '</cellXfs>'
        . '</styleSheet>';

    // workbook.xml
    $wbXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . $xe($sheetTitle) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    // workbook.xml.rels
    $wbRels = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    // .rels
    $dotRels = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    // [Content_Types].xml
    $ctXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    // ── ZIP oluştur ────────────────────────────────────────────────────────
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'ZipArchive açılamadı.']);
        exit;
    }
    $zip->addFromString('[Content_Types].xml',           $ctXml);
    $zip->addFromString('_rels/.rels',                   $dotRels);
    $zip->addFromString('xl/workbook.xml',               $wbXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',    $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',      $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml',          $ssXml);
    $zip->addFromString('xl/styles.xml',                 $stylesXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// ── Ortak yardımcı ────────────────────────────────────────────────────────
$ayarlar = (new Ayarlar())->getAll();
$firmaAdi = $ayarlar['firma_adi'] ?? 'Servis Takip';
$tarih = date('Ymd_His');

switch ($tip) {

    case 'aylik_trend':
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::getInstance();
        $fid = $_SESSION['firma_id'];
        $yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
        if ($yil < 2000 || $yil > 2100) {
            $yil = (int)date('Y');
        }
        $usdKur = max(0, (float)($_GET['usd_try'] ?? 0));
        $satisModel = new Satis();

        $rows = [];
        for ($ay = 1; $ay <= 12; $ay++) {
            $ayNo = str_pad((string)$ay, 2, '0', STR_PAD_LEFT);
            $baslangic = "{$yil}-{$ayNo}-01";
            $bitis = date('Y-m-t', strtotime($baslangic));

            $servis = $db->fetchOne("
                SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
                FROM servisler
                WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
            ", [$fid, $baslangic, $bitis]);

            $tahsilat = (float)$db->fetchColumn("
                SELECT COALESCE(SUM(tutar),0)
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                  AND DATE(tahsilat_tarihi) <= DATE('now')
            ", [$fid, $baslangic, $bitis]);

            $satisMaliyet = $satisModel->getMaliyetByDateRange($baslangic, $bitis, $usdKur);

            $servisMaliyet = (float)$db->fetchColumn("
                SELECT COALESCE(SUM(
                    sp.miktar
                    * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                    * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END
                ),0)
                FROM servis_parcalari sp
                JOIN servisler s ON s.id=sp.servis_id AND s.deleted_at IS NULL
                LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
                WHERE s.firma_id=? AND sp.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
            ", [$usdKur, $fid, $baslangic, $bitis]);

            $satisHacmi = $satisModel->getSatisHacmiByDateRange($baslangic, $bitis);
            $satisCiro = $satisModel->getCiroByDateRange($baslangic, $bitis);
            $servisCiro = (float)($servis['ciro'] ?? 0);
            $islemHacmi = $satisHacmi + $servisCiro;
            $tahakkukCiro = $satisCiro + $servisCiro;
            $beklenenTahsilat = $tahakkukCiro;
            $toplamMaliyet = $satisMaliyet + $servisMaliyet;

            $rows[] = [
                'ay' => $ayNo,
                'label' => ['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'][$ay - 1],
                'satis_adet' => (int)$db->fetchColumn("
                    SELECT COUNT(*)
                    FROM satislar
                    WHERE firma_id=? AND deleted_at IS NULL AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
                ", [$fid, $baslangic, $bitis]),
                'servis_adet' => (int)($servis['adet'] ?? 0),
                'satis_hacmi' => $satisHacmi,
                'satis_tahakkuk' => $satisCiro,
                'servis_ciro' => $servisCiro,
                'islem_hacmi' => $islemHacmi,
                'tahakkuk_ciro' => $tahakkukCiro,
                'toplam_ciro' => $tahakkukCiro,
                'beklenen_tahsilat' => $beklenenTahsilat,
                'tahsilat' => $tahsilat,
                'gercek_tahsilat' => $tahsilat,
                'tahakkuk_maliyet' => $toplamMaliyet,
                'toplam_maliyet' => $toplamMaliyet,
                'net_kar' => $tahakkukCiro - $toplamMaliyet,
            ];
        }

        $yilBaslangic = "{$yil}-01-01";
        $yilBitis = "{$yil}-12-31";
        $ozetSatis = $db->fetchOne("
            SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
            FROM satislar
            WHERE firma_id=? AND deleted_at IS NULL AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$fid, $yilBaslangic, $yilBitis]);
        $ozetServis = $db->fetchOne("
            SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
            FROM servisler
            WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$fid, $yilBaslangic, $yilBitis]);
        $ozetTahsilat = (float)$db->fetchColumn("
            SELECT COALESCE(SUM(tutar),0)
            FROM tahsilatlar
            WHERE firma_id=? AND deleted_at IS NULL
              AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
              AND DATE(tahsilat_tarihi) <= DATE('now')
        ", [$fid, $yilBaslangic, $yilBitis]);
        $ozetSatisMaliyet = (float)$db->fetchColumn("
            SELECT COALESCE(SUM(CASE WHEN line_cost > 0 THEN line_cost ELSE device_cost END),0)
            FROM (
                SELECT s.id,
                       COALESCE(SUM(
                           sk.miktar
                           * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE ? END
                       ),0) AS line_cost,
                       COALESCE(cp.maliyet_usd, 0) * ? AS device_cost
                FROM satislar s
                LEFT JOIN satis_kalemleri sk ON sk.satis_id=s.id AND sk.deleted_at IS NULL
                LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
                LEFT JOIN cihazlar c ON c.id=s.cihaz_id AND c.deleted_at IS NULL
                LEFT JOIN parcalar cp ON cp.id=c.parca_id AND cp.deleted_at IS NULL
                WHERE s.firma_id=? AND s.deleted_at IS NULL AND DATE(s.satis_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY s.id
            )
        ", [$usdKur, $usdKur, $fid, $yilBaslangic, $yilBitis]);
        $ozetServisMaliyet = (float)$db->fetchColumn("
            SELECT COALESCE(SUM(
                sp.miktar
                * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END
            ),0)
            FROM servis_parcalari sp
            JOIN servisler s ON s.id=sp.servis_id AND s.deleted_at IS NULL
            LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
            WHERE s.firma_id=? AND sp.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$usdKur, $fid, $yilBaslangic, $yilBitis]);
        $ozetIslemHacmi = (float)($ozetSatis['ciro'] ?? 0) + (float)($ozetServis['ciro'] ?? 0);
        $ozetTahakkukCiro = array_sum(array_map(fn($row) => (float)($row['tahakkuk_ciro'] ?? $row['toplam_ciro'] ?? 0), $rows));
        $ozetBeklenenTahsilat = array_sum(array_map(fn($row) => (float)($row['beklenen_tahsilat'] ?? 0), $rows));
        $ozetToplamMaliyet = array_sum(array_map(fn($row) => (float)($row['tahakkuk_maliyet'] ?? $row['toplam_maliyet'] ?? 0), $rows));

        echo json_encode([
            'success' => true,
            'data' => [
                'yil' => $yil,
                'usd_try' => $usdKur,
                'aylar' => $rows,
                'ozet' => [
                    'islem_hacmi' => $ozetIslemHacmi,
                    'tahakkuk_ciro' => $ozetTahakkukCiro,
                    'toplam_ciro' => $ozetTahakkukCiro,
                    'beklenen_tahsilat' => $ozetBeklenenTahsilat,
                    'tahsilat' => $ozetTahsilat,
                    'gercek_tahsilat' => $ozetTahsilat,
                    'net_kar' => $ozetTahakkukCiro - $ozetToplamMaliyet,
                    'satis_adet' => (int)($ozetSatis['adet'] ?? 0),
                    'servis_adet' => (int)($ozetServis['adet'] ?? 0),
                    'toplam_maliyet' => $ozetToplamMaliyet,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'gunluk_trend':
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::getInstance();
        $fid = $_SESSION['firma_id'];
        $ay = $_GET['ay'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $ay)) {
            $ay = date('Y-m');
        }
        $usdKur = max(0, (float)($_GET['usd_try'] ?? 0));
        $satisModel = new Satis();
        $ayBaslangic = $ay . '-01';
        $ayBitis = date('Y-m-t', strtotime($ayBaslangic));
        $gunSayisi = (int)date('t', strtotime($ayBaslangic));

        $rows = [];
        $ozet = [
            'islem_hacmi' => 0.0,
            'tahakkuk_ciro' => 0.0,
            'toplam_ciro' => 0.0,
            'beklenen_tahsilat' => 0.0,
            'tahsilat' => 0.0,
            'gercek_tahsilat' => 0.0,
            'net_kar' => 0.0,
            'satis_adet' => 0,
            'servis_adet' => 0,
            'tahakkuk_maliyet' => 0.0,
            'toplam_maliyet' => 0.0,
        ];

        for ($gun = 1; $gun <= $gunSayisi; $gun++) {
            $gunNo = str_pad((string)$gun, 2, '0', STR_PAD_LEFT);
            $tarihGun = "{$ay}-{$gunNo}";

            $servis = $db->fetchOne("
                SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
                FROM servisler
                WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi)=DATE(?)
            ", [$fid, $tarihGun]);

            $tahsilat = (float)$db->fetchColumn("
                SELECT COALESCE(SUM(tutar),0)
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL
                  AND DATE(tahsilat_tarihi)=DATE(?)
                  AND DATE(tahsilat_tarihi) <= DATE('now')
            ", [$fid, $tarihGun]);

            $satisMaliyet = $satisModel->getMaliyetByDateRange($tarihGun, $tarihGun, $usdKur);
            $servisMaliyet = (float)$db->fetchColumn("
                SELECT COALESCE(SUM(
                    sp.miktar
                    * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                    * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END
                ),0)
                FROM servis_parcalari sp
                JOIN servisler s ON s.id=sp.servis_id AND s.deleted_at IS NULL
                LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
                WHERE s.firma_id=? AND sp.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi)=DATE(?)
            ", [$usdKur, $fid, $tarihGun]);

            $satisHacmi = $satisModel->getSatisHacmiByDateRange($tarihGun, $tarihGun);
            $satisCiro = $satisModel->getCiroByDateRange($tarihGun, $tarihGun);
            $servisCiro = (float)($servis['ciro'] ?? 0);
            $islemHacmi = $satisHacmi + $servisCiro;
            $tahakkukCiro = $satisCiro + $servisCiro;
            $beklenenTahsilat = $tahakkukCiro;
            $toplamMaliyet = $satisMaliyet + $servisMaliyet;
            $satisAdet = (int)$db->fetchColumn("
                SELECT COUNT(*)
                FROM satislar
                WHERE firma_id=? AND deleted_at IS NULL AND DATE(satis_tarihi)=DATE(?)
            ", [$fid, $tarihGun]);
            $servisAdet = (int)($servis['adet'] ?? 0);
            $netKar = $tahakkukCiro - $toplamMaliyet;

            $rows[] = [
                'key' => $tarihGun,
                'gun' => $gunNo,
                'label' => $gunNo,
                'tarih' => $tarihGun,
                'satis_adet' => $satisAdet,
                'servis_adet' => $servisAdet,
                'satis_hacmi' => $satisHacmi,
                'satis_tahakkuk' => $satisCiro,
                'servis_ciro' => $servisCiro,
                'islem_hacmi' => $islemHacmi,
                'tahakkuk_ciro' => $tahakkukCiro,
                'toplam_ciro' => $tahakkukCiro,
                'beklenen_tahsilat' => $beklenenTahsilat,
                'tahsilat' => $tahsilat,
                'gercek_tahsilat' => $tahsilat,
                'tahakkuk_maliyet' => $toplamMaliyet,
                'toplam_maliyet' => $toplamMaliyet,
                'net_kar' => $netKar,
            ];

            $ozet['islem_hacmi'] = ($ozet['islem_hacmi'] ?? 0) + $islemHacmi;
            $ozet['tahakkuk_ciro'] = ($ozet['tahakkuk_ciro'] ?? 0) + $tahakkukCiro;
            $ozet['toplam_ciro'] += $tahakkukCiro;
            $ozet['beklenen_tahsilat'] = ($ozet['beklenen_tahsilat'] ?? 0) + $beklenenTahsilat;
            $ozet['tahsilat'] += $tahsilat;
            $ozet['gercek_tahsilat'] = ($ozet['gercek_tahsilat'] ?? 0) + $tahsilat;
            $ozet['net_kar'] += $netKar;
            $ozet['satis_adet'] += $satisAdet;
            $ozet['servis_adet'] += $servisAdet;
            $ozet['toplam_maliyet'] += $toplamMaliyet;
            $ozet['tahakkuk_maliyet'] = ($ozet['tahakkuk_maliyet'] ?? 0) + $toplamMaliyet;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'ay' => $ay,
                'baslangic' => $ayBaslangic,
                'bitis' => $ayBitis,
                'usd_try' => $usdKur,
                'gunler' => $rows,
                'ozet' => $ozet,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'kar_ozet':
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::getInstance();
        $fid = $_SESSION['firma_id'];
        $baslangic = $_GET['baslangic'] ?? date('Y-m-01');
        $bitis = $_GET['bitis'] ?? date('Y-m-t');
        $usdKur = max(0, (float)($_GET['usd_try'] ?? 0));
        $satisModel = new Satis();

        $servis = $db->fetchOne("
            SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
            FROM servisler
            WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$fid, $baslangic, $bitis]);

        $satisMaliyet = $satisModel->getMaliyetByDateRange($baslangic, $bitis, $usdKur);

        $servisMaliyet = (float)$db->fetchColumn("
            SELECT COALESCE(SUM(
                sp.miktar
                * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END
            ),0)
            FROM servis_parcalari sp
            JOIN servisler s ON s.id=sp.servis_id AND s.deleted_at IS NULL
            LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
            WHERE s.firma_id=? AND sp.deleted_at IS NULL AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$usdKur, $fid, $baslangic, $bitis]);

        $gercekTahsilat = (float)$db->fetchColumn("
            SELECT COALESCE(SUM(tutar),0)
            FROM tahsilatlar
            WHERE firma_id=? AND deleted_at IS NULL
              AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
              AND DATE(tahsilat_tarihi) <= DATE('now')
        ", [$fid, $baslangic, $bitis]);

        $satisHacmi = $satisModel->getSatisHacmiByDateRange($baslangic, $bitis);
        $satisCiro = $satisModel->getCiroByDateRange($baslangic, $bitis);
        $servisCiro = (float)($servis['ciro'] ?? 0);
        $islemHacmi = $satisHacmi + $servisCiro;
        $toplamCiro = $satisCiro + $servisCiro;
        $beklenenTahsilat = $toplamCiro;
        $toplamMaliyet = $satisMaliyet + $servisMaliyet;
        $netKar = $toplamCiro - $toplamMaliyet;

        echo json_encode([
            'success' => true,
            'data' => [
                'baslangic' => $baslangic,
                'bitis' => $bitis,
                'usd_try' => $usdKur,
                'satis_adet' => $satisModel->getGercekAdetByDateRange($baslangic, $bitis),
                'servis_adet' => (int)($servis['adet'] ?? 0),
                'satis_hacmi' => $satisHacmi,
                'satis_ciro' => $satisCiro,
                'satis_tahakkuk' => $satisCiro,
                'servis_ciro' => $servisCiro,
                'islem_hacmi' => $islemHacmi,
                'tahakkuk_ciro' => $toplamCiro,
                'toplam_ciro' => $toplamCiro,
                'beklenen_tahsilat' => $beklenenTahsilat,
                'gercek_tahsilat' => $gercekTahsilat,
                'tahsilat' => $gercekTahsilat,
                'satis_maliyet' => $satisMaliyet,
                'servis_maliyet' => $servisMaliyet,
                'tahakkuk_maliyet' => $toplamMaliyet,
                'toplam_maliyet' => $toplamMaliyet,
                'net_kar' => $netKar,
                'kar_orani' => $toplamCiro > 0 ? round(($netKar / $toplamCiro) * 100, 2) : 0,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'musteri':
        $m    = new Musteri();
        $rows = $m->getAll();
        $durumMap = ['gecikmis' => 'Gecikmiş', 'yakin' => 'Yaklaşıyor', 'normal' => 'İyi', 'ayarsiz' => 'Ayarsız'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['id'],
                trim($r['ad'] . ' ' . $r['soyad']),
                $r['telefon'] ?? '-',
                $r['adres'] ?? '-',
                $durumMap[$r['bakim_durumu'] ?? ''] ?? '-',
                (int)($r['toplam_servis'] ?? 0),
                $r['son_servis_tarihi'] ? date('d.m.Y', strtotime($r['son_servis_tarihi'])) : '-',
            ];
        }
        xlsxResponse(
            ['#', 'Müşteri Adı', 'Telefon', 'Adres', 'Bakım Durumu', 'Toplam Servis', 'Son Servis'],
            $data,
            "musteri_raporu_$tarih.xlsx",
            'Müşteriler'
        );

    case 'servis':
        $s = new Servis();
        $filtre = [
            'baslangic' => $_GET['baslangic'] ?? null,
            'bitis'     => $_GET['bitis'] ?? null,
            'sirala'    => 'tarih_asc',
        ];
        $rows = $s->getAll(array_filter($filtre));
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['sira_no'] ?? '',
                $r['musteri_adi'] ?? '-',
                $r['telefon'] ?? '-',
                $r['servis_tipi'] === 'ariza' ? 'Arıza' : 'Periyodik Bakım',
                $r['tamamlanma_tarihi'] ? date('d.m.Y', strtotime($r['tamamlanma_tarihi'])) : '-',
                (float)($r['toplam_tutar'] ?? 0),
                $r['notlar'] ?? '-',
            ];
        }
        xlsxResponse(
            ['Sıra', 'Müşteri', 'Telefon', 'Servis Tipi', 'Tarih', 'Tutar (₺)', 'Notlar'],
            $data,
            "servis_raporu_$tarih.xlsx",
            'Servisler'
        );

    case 'stok':
        $p    = new Parca();
        $rows = $p->getAll();
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['id'],
                $r['parca_adi'],
                $r['marka'] ?? '-',
                (int)$r['stok_miktari'],
                (int)$r['kritik_stok_seviyesi'],
                $r['stok_miktari'] <= $r['kritik_stok_seviyesi'] ? 'KRİTİK' : 'Normal',
                (float)($r['birim_fiyat'] ?? 0),
                (float)($r['maliyet_usd'] ?? 0),
                $r['tedarikci'] ?? '-',
            ];
        }
        xlsxResponse(
            ['#', 'Parça Adı', 'Marka', 'Stok', 'Kritik Seviye', 'Durum', 'Birim Fiyat (₺)', 'Maliyet ($)', 'Tedarikçi'],
            $data,
            "stok_raporu_$tarih.xlsx",
            'Stok'
        );

    case 'finans':
        $db = Database::getInstance();
        $fid = $_SESSION['firma_id'];
        $baslangic = $_GET['baslangic'] ?? date('Y-m-01');
        $bitis = $_GET['bitis'] ?? date('Y-m-t');
        $usdKur = max(0, (float)($_GET['usd_try'] ?? 0));

        $satisRows = [];
        $pesinSatislar = $db->fetchAll("
            SELECT
                DATE(s.satis_tarihi) AS tarih,
                s.id AS satis_id,
                m.ad || ' ' || m.soyad AS musteri_adi,
                m.telefon,
                COALESCE(k.kalemler, NULLIF(TRIM(COALESCE(c.marka, '') || ' ' || COALESCE(c.cihaz_adi, '')), ''), 'Satis') AS aciklama,
                s.odeme_turu,
                s.toplam_tutar AS ciro,
                CASE WHEN COALESCE(lc.line_cost, 0) > 0 THEN lc.line_cost ELSE COALESCE(dc.device_cost, 0) END AS maliyet,
                COALESCE(th.tahsilat, 0) AS tahsilat,
                s.notlar
            FROM satislar s
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON c.id=s.cihaz_id AND c.deleted_at IS NULL
            LEFT JOIN (
                SELECT satis_id, GROUP_CONCAT(urun_adi, ', ') AS kalemler
                FROM satis_kalemleri
                WHERE deleted_at IS NULL
                GROUP BY satis_id
            ) k ON k.satis_id=s.id
            LEFT JOIN (
                SELECT sk.satis_id,
                       COALESCE(SUM(
                           sk.miktar
                           * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE ? END
                       ),0) AS line_cost
                FROM satis_kalemleri sk
                LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
                WHERE sk.deleted_at IS NULL
                GROUP BY sk.satis_id
            ) lc ON lc.satis_id=s.id
            LEFT JOIN (
                SELECT c2.id AS cihaz_id, COALESCE(p2.maliyet_usd, 0) * ? AS device_cost
                FROM cihazlar c2
                LEFT JOIN parcalar p2 ON p2.id=c2.parca_id AND p2.deleted_at IS NULL
                WHERE c2.deleted_at IS NULL
            ) dc ON dc.cihaz_id=s.cihaz_id
            LEFT JOIN (
                SELECT kaynak_id, COALESCE(SUM(tutar),0) AS tahsilat
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='satis'
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY kaynak_id
            ) th ON th.kaynak_id=s.id
            WHERE s.firma_id=? AND s.deleted_at IS NULL
              AND s.odeme_turu <> 'taksitli'
              AND DATE(s.satis_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(s.satis_tarihi) ASC, s.id ASC
        ", [$usdKur, $usdKur, $fid, $baslangic, $bitis, $fid, $baslangic, $bitis]);

        foreach ($pesinSatislar as $r) {
            $satisRows[] = [
                'tarih' => $r['tarih'],
                'no' => (int)$r['satis_id'],
                'musteri_adi' => $r['musteri_adi'] ?? '-',
                'telefon' => $r['telefon'] ?? '-',
                'aciklama' => $r['aciklama'] ?? 'Satis',
                'tip' => $r['odeme_turu'] === 'pesin' ? 'Pesin satis' : 'Satis',
                'ciro' => (float)($r['ciro'] ?? 0),
                'maliyet' => (float)($r['maliyet'] ?? 0),
                'tahsilat' => (float)($r['tahsilat'] ?? 0),
                'notlar' => $r['notlar'] ?? '',
            ];
        }

        $taksitliSatislar = $db->fetchAll("
            SELECT
                DATE(t.vade_tarihi) AS tarih,
                s.id AS satis_id,
                t.taksit_no,
                m.ad || ' ' || m.soyad AS musteri_adi,
                m.telefon,
                COALESCE(k.kalemler, NULLIF(TRIM(COALESCE(c.marka, '') || ' ' || COALESCE(c.cihaz_adi, '')), ''), 'Satis') AS aciklama,
                s.toplam_tutar,
                t.tutar AS ciro,
                CASE
                    WHEN DATE(t.vade_tarihi) = (
                        SELECT MIN(DATE(t2.vade_tarihi))
                        FROM taksitler t2
                        WHERE t2.satis_id=s.id AND t2.firma_id=s.firma_id AND t2.deleted_at IS NULL
                    )
                    THEN CASE WHEN COALESCE(lc.line_cost, 0) > 0 THEN lc.line_cost ELSE COALESCE(dc.device_cost, 0) END
                    ELSE 0
                END AS maliyet,
                COALESCE(th.tahsilat, 0) AS tahsilat,
                s.notlar
            FROM taksitler t
            JOIN satislar s ON s.id=t.satis_id AND s.deleted_at IS NULL
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN cihazlar c ON c.id=s.cihaz_id AND c.deleted_at IS NULL
            LEFT JOIN (
                SELECT satis_id, GROUP_CONCAT(urun_adi, ', ') AS kalemler
                FROM satis_kalemleri
                WHERE deleted_at IS NULL
                GROUP BY satis_id
            ) k ON k.satis_id=s.id
            LEFT JOIN (
                SELECT sk.satis_id,
                       COALESCE(SUM(
                           sk.miktar
                           * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE ? END
                       ),0) AS line_cost
                FROM satis_kalemleri sk
                LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
                WHERE sk.deleted_at IS NULL
                GROUP BY sk.satis_id
            ) lc ON lc.satis_id=s.id
            LEFT JOIN (
                SELECT c2.id AS cihaz_id, COALESCE(p2.maliyet_usd, 0) * ? AS device_cost
                FROM cihazlar c2
                LEFT JOIN parcalar p2 ON p2.id=c2.parca_id AND p2.deleted_at IS NULL
                WHERE c2.deleted_at IS NULL
            ) dc ON dc.cihaz_id=s.cihaz_id
            LEFT JOIN (
                SELECT taksit_id, COALESCE(SUM(tutar),0) AS tahsilat
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='satis'
                  AND taksit_id IS NOT NULL
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY taksit_id
            ) th ON th.taksit_id=t.id
            WHERE t.firma_id=? AND t.deleted_at IS NULL
              AND s.firma_id=? AND s.odeme_turu='taksitli'
              AND DATE(t.vade_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(t.vade_tarihi) ASC, s.id ASC, t.taksit_no ASC
        ", [$usdKur, $usdKur, $fid, $baslangic, $bitis, $fid, $fid, $baslangic, $bitis]);

        foreach ($taksitliSatislar as $r) {
            $taksitNo = (int)($r['taksit_no'] ?? 0);
            $satisRows[] = [
                'tarih' => $r['tarih'],
                'no' => (int)$r['satis_id'],
                'musteri_adi' => $r['musteri_adi'] ?? '-',
                'telefon' => $r['telefon'] ?? '-',
                'aciklama' => $r['aciklama'] ?? 'Satis',
                'tip' => $taksitNo === 0 ? 'Pesinat' : $taksitNo . '. taksit',
                'ciro' => (float)($r['ciro'] ?? 0),
                'maliyet' => (float)($r['maliyet'] ?? 0),
                'tahsilat' => (float)($r['tahsilat'] ?? 0),
                'notlar' => $r['notlar'] ?? '',
            ];
        }

        usort($satisRows, function ($a, $b) {
            $cmp = strcmp((string)$a['tarih'], (string)$b['tarih']);
            return $cmp !== 0 ? $cmp : ((int)$a['no'] <=> (int)$b['no']);
        });

        $servisRows = $db->fetchAll("
            SELECT
                DATE(s.tamamlanma_tarihi) AS tarih,
                s.id AS servis_id,
                m.ad || ' ' || m.soyad AS musteri_adi,
                m.telefon,
                s.servis_tipi,
                COALESCE(i.islemler, CASE WHEN s.servis_tipi='ariza' THEN 'Ariza' ELSE 'Periyodik bakim' END) AS aciklama,
                s.toplam_tutar AS ciro,
                COALESCE(pc.maliyet, 0) AS maliyet,
                COALESCE(th.tahsilat, 0) AS tahsilat,
                s.notlar
            FROM servisler s
            JOIN musteriler m ON m.id=s.musteri_id AND m.deleted_at IS NULL
            LEFT JOIN (
                SELECT servis_id, GROUP_CONCAT(islem, ', ') AS islemler
                FROM servis_islemleri
                WHERE deleted_at IS NULL
                GROUP BY servis_id
            ) i ON i.servis_id=s.id
            LEFT JOIN (
                SELECT sp.servis_id,
                       COALESCE(SUM(
                           sp.miktar
                           * COALESCE(NULLIF(sp.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                           * CASE WHEN COALESCE(sp.usd_kur, 0) > 0 THEN sp.usd_kur ELSE ? END
                       ),0) AS maliyet
                FROM servis_parcalari sp
                LEFT JOIN parcalar p ON p.id=sp.parca_id AND p.deleted_at IS NULL
                WHERE sp.deleted_at IS NULL
                GROUP BY sp.servis_id
            ) pc ON pc.servis_id=s.id
            LEFT JOIN (
                SELECT kaynak_id, COALESCE(SUM(tutar),0) AS tahsilat
                FROM tahsilatlar
                WHERE firma_id=? AND deleted_at IS NULL AND kaynak_tip='servis'
                  AND DATE(tahsilat_tarihi) BETWEEN DATE(?) AND DATE(?)
                GROUP BY kaynak_id
            ) th ON th.kaynak_id=s.id
            WHERE s.firma_id=? AND s.deleted_at IS NULL
              AND DATE(s.tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
            ORDER BY DATE(s.tamamlanma_tarihi) ASC, s.id ASC
        ", [$usdKur, $fid, $baslangic, $bitis, $fid, $baslangic, $bitis]);

        $data = [];
        $totals = [
            'satis_ciro' => 0.0, 'satis_maliyet' => 0.0, 'satis_tahsilat' => 0.0,
            'servis_ciro' => 0.0, 'servis_maliyet' => 0.0, 'servis_tahsilat' => 0.0,
        ];

        $data[] = ['SATISLAR', '', '', '', '', '', '', '', '', '', '', ''];
        foreach ($satisRows as $r) {
            $ciro = (float)$r['ciro'];
            $maliyet = (float)$r['maliyet'];
            $tahsilat = (float)$r['tahsilat'];
            $totals['satis_ciro'] += $ciro;
            $totals['satis_maliyet'] += $maliyet;
            $totals['satis_tahsilat'] += $tahsilat;
            $data[] = [
                'Satis',
                $r['tarih'] ? date('d.m.Y', strtotime($r['tarih'])) : '-',
                $r['no'],
                $r['musteri_adi'],
                $r['telefon'],
                $r['aciklama'],
                $r['tip'],
                round($ciro, 2),
                round($maliyet, 2),
                round($ciro - $maliyet, 2),
                round($tahsilat, 2),
                $r['notlar'] ?: '-',
            ];
        }

        $data[] = ['', '', '', '', '', '', 'SATIS TOPLAMI', round($totals['satis_ciro'], 2), round($totals['satis_maliyet'], 2), round($totals['satis_ciro'] - $totals['satis_maliyet'], 2), round($totals['satis_tahsilat'], 2), ''];
        $data[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        $data[] = ['SERVISLER', '', '', '', '', '', '', '', '', '', '', ''];
        foreach ($servisRows as $r) {
            $ciro = (float)($r['ciro'] ?? 0);
            $maliyet = (float)($r['maliyet'] ?? 0);
            $tahsilat = (float)($r['tahsilat'] ?? 0);
            $totals['servis_ciro'] += $ciro;
            $totals['servis_maliyet'] += $maliyet;
            $totals['servis_tahsilat'] += $tahsilat;
            $data[] = [
                'Servis',
                $r['tarih'] ? date('d.m.Y', strtotime($r['tarih'])) : '-',
                (int)$r['servis_id'],
                $r['musteri_adi'] ?? '-',
                $r['telefon'] ?? '-',
                $r['aciklama'] ?? '-',
                $r['servis_tipi'] === 'ariza' ? 'Ariza' : 'Periyodik bakim',
                round($ciro, 2),
                round($maliyet, 2),
                round($ciro - $maliyet, 2),
                round($tahsilat, 2),
                $r['notlar'] ?: '-',
            ];
        }

        $data[] = ['', '', '', '', '', '', 'SERVIS TOPLAMI', round($totals['servis_ciro'], 2), round($totals['servis_maliyet'], 2), round($totals['servis_ciro'] - $totals['servis_maliyet'], 2), round($totals['servis_tahsilat'], 2), ''];
        $data[] = ['', '', '', '', '', '', 'GENEL TOPLAM', round($totals['satis_ciro'] + $totals['servis_ciro'], 2), round($totals['satis_maliyet'] + $totals['servis_maliyet'], 2), round(($totals['satis_ciro'] + $totals['servis_ciro']) - ($totals['satis_maliyet'] + $totals['servis_maliyet']), 2), round($totals['satis_tahsilat'] + $totals['servis_tahsilat'], 2), 'USD kuru: ' . $usdKur];

        xlsxResponse(
            ['Bolum', 'Tarih', 'Kayit No', 'Musteri', 'Telefon', 'Islem / Urun', 'Tip / Odeme', 'Ciro (TL)', 'Maliyet (TL)', 'Net Kar (TL)', 'Gercek Tahsilat (TL)', 'Notlar'],
            $data,
            "finans_raporu_$tarih.xlsx",
            'Finans'
        );

        $s = new Servis();
        $filtre = [
            'baslangic' => $_GET['baslangic'] ?? date('Y-m-01'),
            'bitis'     => $_GET['bitis'] ?? date('Y-m-t'),
            'sirala'    => 'tarih_asc',
        ];
        $rows       = $s->getAll(array_filter($filtre));
        $toplamCiro = array_sum(array_column($rows, 'toplam_tutar'));
        $data       = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['sira_no'] ?? '',
                $r['musteri_adi'] ?? '-',
                $r['servis_tipi'] === 'ariza' ? 'Arıza' : 'Periyodik Bakım',
                $r['tamamlanma_tarihi'] ? date('d.m.Y', strtotime($r['tamamlanma_tarihi'])) : '-',
                (float)($r['toplam_tutar'] ?? 0),
            ];
        }
        // Toplam satırı en sona
        $data[] = ['', '', '', 'TOPLAM CİRO', $toplamCiro];
        xlsxResponse(
            ['Sıra', 'Müşteri', 'Servis Tipi', 'Tarih', 'Tutar (₺)'],
            $data,
            "finans_raporu_$tarih.xlsx",
            'Finans'
        );

    case 'planlanan_bakim':
        $ay = $_GET['ay'] ?? date('Y-m'); // YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $ay)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Geçersiz ay formatı.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ayBaslangic = $ay . '-01';
        $ayBitis     = date('Y-m-t', strtotime($ayBaslangic)); // ayın son günü

        $db  = Database::getInstance();
        $fid = $_SESSION['firma_id'];

        // Bu ay içinde bakımı planlanan müşteriler
        // Bu ayın bakımları + önceki aylardan gecikmiş olanlar (henüz servisi yapılmamış)
        $musteriler = $db->fetchAll("
            SELECT m.id, m.ad || ' ' || m.soyad AS musteri_adi,
                   m.telefon, m.adres,
                   pb.son_bakim_tarihi, pb.sonraki_bakim_tarihi, pb.periyot_ay,
                   CASE WHEN pb.sonraki_bakim_tarihi < ? THEN 1 ELSE 0 END AS gecikti
            FROM periyodik_bakimlar pb
            JOIN musteriler m ON m.id = pb.musteri_id
            WHERE m.firma_id = ?
              AND pb.aktif = 1
              AND pb.sonraki_bakim_tarihi <= ?
              AND pb.sonraki_bakim_tarihi IS NOT NULL
            ORDER BY pb.sonraki_bakim_tarihi ASC
        ", [$ayBaslangic, $fid, $ayBitis]);

        $data = [];
        $no   = 1;
        foreach ($musteriler as $row) {
            // Son servis kaydını bul
            $sonServis = $db->fetchOne("
                SELECT id, tamamlanma_tarihi, toplam_tutar
                FROM servisler
                WHERE musteri_id = ? AND firma_id = ?
                ORDER BY tamamlanma_tarihi DESC, id DESC
                LIMIT 1
            ", [$row['id'], $fid]);

            $yapılanIslemler = '-';
            $kullanılanParcalar = '-';

            if ($sonServis) {
                // İşlemler
                $islemler = $db->fetchAll(
                    "SELECT islem FROM servis_islemleri WHERE servis_id = ?",
                    [$sonServis['id']]
                );
                if ($islemler) {
                    $yapılanIslemler = implode(', ', array_column($islemler, 'islem'));
                }

                // Parçalar
                $parcalar = $db->fetchAll("
                    SELECT p.parca_adi, sp.miktar, sp.birim_fiyat
                    FROM servis_parcalari sp
                    JOIN parcalar p ON p.id = sp.parca_id
                    WHERE sp.servis_id = ?
                ", [$sonServis['id']]);
                if ($parcalar) {
                    $parcaList = [];
                    foreach ($parcalar as $pa) {
                        $parcaList[] = $pa['parca_adi'] . ' (x' . $pa['miktar'] . ')';
                    }
                    $kullanılanParcalar = implode(', ', $parcaList);
                }
            }

            $data[] = [
                $no++,
                $row['musteri_adi'],
                $row['telefon'] ?? '-',
                $row['adres'] ?? '-',
                $row['sonraki_bakim_tarihi'] ? date('d.m.Y', strtotime($row['sonraki_bakim_tarihi'])) : '-',
                $row['son_bakim_tarihi']     ? date('d.m.Y', strtotime($row['son_bakim_tarihi']))     : 'İlk Bakım',
                (int)($row['periyot_ay'] ?? 6) . ' ay',
                $row['gecikti'] ? 'GECİKMİŞ' : 'Planlandı',
                $yapılanIslemler,
                $kullanılanParcalar,
            ];
        }

        $ayLabel = strftime('%B %Y', strtotime($ayBaslangic));
        // strftime locale sorunu olabilir, manuel yapalım
        $ayAdlari = ['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs',
                     '06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül',
                     '10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
        [$yil, $ayNo] = explode('-', $ay);
        $ayLabel = ($ayAdlari[$ayNo] ?? $ayNo) . ' ' . $yil;

        xlsxResponse(
            ['#', 'Müşteri Adı', 'Telefon', 'Adres', 'Planlanan Bakım', 'Son Bakım Tarihi', 'Periyot', 'Durum', 'Son Serviste Yapılan İşlemler', 'Son Serviste Kullanılan Parçalar'],
            $data,
            "planlanan_bakim_{$ay}.xlsx",
            $ayLabel
        );

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Geçersiz rapor tipi.'], JSON_UNESCAPED_UNICODE);
        exit;
}
