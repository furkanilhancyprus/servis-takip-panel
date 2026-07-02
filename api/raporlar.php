<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Musteri.php';
require_once ROOT . '/models/Servis.php';
require_once ROOT . '/models/Parca.php';
require_once ROOT . '/models/PeriyodikBakim.php';
require_once ROOT . '/models/Ayarlar.php';

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

    case 'kar_ozet':
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::getInstance();
        $fid = $_SESSION['firma_id'];
        $baslangic = $_GET['baslangic'] ?? date('Y-m-01');
        $bitis = $_GET['bitis'] ?? date('Y-m-d');
        $usdKur = max(0, (float)($_GET['usd_try'] ?? 0));

        $satis = $db->fetchOne("
            SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
            FROM satislar
            WHERE firma_id=? AND deleted_at IS NULL AND DATE(satis_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$fid, $baslangic, $bitis]);

        $servis = $db->fetchOne("
            SELECT COUNT(*) AS adet, COALESCE(SUM(toplam_tutar),0) AS ciro
            FROM servisler
            WHERE firma_id=? AND deleted_at IS NULL AND DATE(tamamlanma_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$fid, $baslangic, $bitis]);

        $satisMaliyet = (float)$db->fetchColumn("
            SELECT COALESCE(SUM(
                sk.miktar
                * COALESCE(NULLIF(sk.birim_maliyet_usd, 0), p.maliyet_usd, 0)
                * CASE WHEN COALESCE(sk.usd_kur, 0) > 0 THEN sk.usd_kur ELSE ? END
            ),0)
            FROM satis_kalemleri sk
            JOIN satislar s ON s.id=sk.satis_id AND s.deleted_at IS NULL
            LEFT JOIN parcalar p ON p.id=sk.parca_id AND p.deleted_at IS NULL
            WHERE s.firma_id=? AND sk.deleted_at IS NULL AND DATE(s.satis_tarihi) BETWEEN DATE(?) AND DATE(?)
        ", [$usdKur, $fid, $baslangic, $bitis]);

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

        $satisCiro = (float)($satis['ciro'] ?? 0);
        $servisCiro = (float)($servis['ciro'] ?? 0);
        $toplamCiro = $satisCiro + $servisCiro;
        $toplamMaliyet = $satisMaliyet + $servisMaliyet;
        $netKar = $toplamCiro - $toplamMaliyet;

        echo json_encode([
            'success' => true,
            'data' => [
                'baslangic' => $baslangic,
                'bitis' => $bitis,
                'usd_try' => $usdKur,
                'satis_adet' => (int)($satis['adet'] ?? 0),
                'servis_adet' => (int)($servis['adet'] ?? 0),
                'satis_ciro' => $satisCiro,
                'servis_ciro' => $servisCiro,
                'toplam_ciro' => $toplamCiro,
                'satis_maliyet' => $satisMaliyet,
                'servis_maliyet' => $servisMaliyet,
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
        ];
        $rows = $s->getAll(array_filter($filtre));
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['id'],
                $r['musteri_adi'] ?? '-',
                $r['telefon'] ?? '-',
                $r['servis_tipi'] === 'ariza' ? 'Arıza' : 'Periyodik Bakım',
                $r['tamamlanma_tarihi'] ? date('d.m.Y', strtotime($r['tamamlanma_tarihi'])) : '-',
                (float)($r['toplam_tutar'] ?? 0),
                $r['notlar'] ?? '-',
            ];
        }
        xlsxResponse(
            ['#', 'Müşteri', 'Telefon', 'Servis Tipi', 'Tarih', 'Tutar (₺)', 'Notlar'],
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
        $s = new Servis();
        $filtre = [
            'baslangic' => $_GET['baslangic'] ?? date('Y-m-01'),
            'bitis'     => $_GET['bitis'] ?? date('Y-m-d'),
        ];
        $rows       = $s->getAll(array_filter($filtre));
        $toplamCiro = array_sum(array_column($rows, 'toplam_tutar'));
        $data       = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['id'],
                $r['musteri_adi'] ?? '-',
                $r['servis_tipi'] === 'ariza' ? 'Arıza' : 'Periyodik Bakım',
                $r['tamamlanma_tarihi'] ? date('d.m.Y', strtotime($r['tamamlanma_tarihi'])) : '-',
                (float)($r['toplam_tutar'] ?? 0),
            ];
        }
        // Toplam satırı en sona
        $data[] = ['', '', '', 'TOPLAM CİRO', $toplamCiro];
        xlsxResponse(
            ['#', 'Müşteri', 'Servis Tipi', 'Tarih', 'Tutar (₺)'],
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
