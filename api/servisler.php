<?php
require_once __DIR__ . '/_base.php';
require_once ROOT . '/models/Servis.php';
require_once ROOT . '/models/Tahsilat.php';

$s  = new Servis();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch (method()) {
    case 'GET':
        if ($id > 0) {
            $servis = $s->getById($id);
            if (!$servis) json_err('Servis bulunamadı', 404);
            json_ok($servis);
        }
        $filtre = [
            'musteri_id'    => $_GET['musteri_id']    ?? null,
            'servis_tipi'   => $_GET['servis_tipi']   ?? null,
            'odeme_durumu'  => $_GET['odeme_durumu']  ?? null,
            'baslangic'     => $_GET['baslangic']     ?? null,
            'bitis'         => $_GET['bitis']          ?? null,
            'search'        => $_GET['search']         ?? null,
            'sirala'        => $_GET['sirala']         ?? null,
            'limit'         => $_GET['limit']          ?? null,
        ];
        json_ok($s->getAll(array_filter($filtre)));

    case 'POST':
        $data = get_input();
        $musteriIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['musteri_ids'] ?? [])))));
        if (!empty($musteriIds)) {
            $data['musteri_ids'] = $musteriIds;
        }

        if ((empty($data['musteri_id']) && empty($data['musteri_ids'])) || empty($data['servis_tipi'])) {
            json_err('Müşteri ve servis tipi zorunludur.');
        }
        if (!empty($data['tahsilat']['tutar'])) {
            $tahsilatTutar = (float)$data['tahsilat']['tutar'];
            $servisToplam = (float)($data['toplam_tutar'] ?? 0);
            if ($tahsilatTutar <= 0 || $tahsilatTutar > $servisToplam) {
                json_err('Tahsilat tutarı servis toplamından büyük olamaz.');
            }
        }
        if (!empty($data['musteri_ids'])) {
            $newIds = $s->createMany($data['musteri_ids'], $data);
            if (!empty($data['tahsilat']['tutar'])) {
                $tahsilat = new Tahsilat();
                foreach ($newIds as $i => $servisId) {
                    $tahsilat->create([
                        'musteri_id' => $data['musteri_ids'][$i] ?? null,
                        'kaynak_tip' => 'servis',
                        'kaynak_id' => $servisId,
                        'tutar' => $data['tahsilat']['tutar'],
                        'odeme_yontemi' => $data['tahsilat']['odeme_yontemi'] ?? 'nakit',
                        'tahsilat_tarihi' => $data['tahsilat']['tahsilat_tarihi'] ?? date('Y-m-d'),
                        'notlar' => $data['tahsilat']['notlar'] ?? null,
                    ]);
                }
            }
            json_ok(['ids' => $newIds, 'count' => count($newIds)], count($newIds) . ' servis kaydedildi.');
        }

        $newId = $s->create($data);
        if (!empty($data['tahsilat']['tutar'])) {
            (new Tahsilat())->create([
                'musteri_id' => $data['musteri_id'],
                'kaynak_tip' => 'servis',
                'kaynak_id' => $newId,
                'tutar' => $data['tahsilat']['tutar'],
                'odeme_yontemi' => $data['tahsilat']['odeme_yontemi'] ?? 'nakit',
                'tahsilat_tarihi' => $data['tahsilat']['tahsilat_tarihi'] ?? date('Y-m-d'),
                'notlar' => $data['tahsilat']['notlar'] ?? null,
            ]);
        }
        json_ok(['id' => $newId], 'Servis kaydedildi.');

    case 'PUT':
        if (!$id) json_err('ID gerekli.');
        $data = get_input();
        $s->update($id, $data);
        json_ok(['id' => $id], 'Servis güncellendi.');

    case 'DELETE':
        if (!$id) json_err('ID gerekli.');
        $s->delete($id);
        json_ok(null, 'Servis silindi.');

    default:
        json_err('Desteklenmeyen metod.', 405);
}
