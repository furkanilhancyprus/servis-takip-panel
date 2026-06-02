# Deploy Notlari

## Domain

Ana domain: `servistakippanel.com`

Turkiye odakli kullanim icin `servistakippanel.com.tr` de alinip ana domaine yonlendirilebilir. Web, masaustu senkronizasyonu ve mobil uygulama ayni web hesabini kullanacak.

## Admin panel

Canliya aldiktan sonra ilk is olarak `/admin.php` adresini acin ve ilk admin hesabini olusturun. Bu panelden firmalar, paketler, aktif/pasif durumlari, musteri detaylari, tahsilatlar, cihaz/senkron bilgileri ve destek notlari yonetilir.

## Ortam degiskenleri

Normal web kurulumunda zorunlu ortam degiskeni yoktur. Hata ayiklama icin gecici olarak acilabilir:

```env
STP_DEBUG=1
```

Canlida hata ayiklama kapali olmalidir.

## Dosya izinleri

Web surumunde SQLite dosyasi `database/` altinda tutulur. Bu klasor PHP tarafindan yazilabilir olmali, fakat disaridan indirilebilir olmamali. Apache icin `.htaccess` dosyasi `.db`, `.sqlite`, `.env`, `.json`, `.lock` gibi hassas dosyalari engeller.

## Deploy disinda tutulacaklar

Kaynak deploy ederken su klasorleri/artefaktlari canliya koymayin:

- `desktop-app/node_modules/`
- `desktop-app/dist/`
- `desktop-app/resources/php/`
- `desktop-app/resources/www/`
- `downloads/*.exe`

## Yayina verilecek indirilebilir dosyalar

Landing sayfasindaki indirme linkleri su dosyalari bekler:

- `downloads/ServisTakipPanel-Kurulum.exe`: SaaS masaustu uygulamasi. Offline calisir, web hesabi ile senkronize olur.
- `downloads/ServisTakipPanel-Lokal-Lifetime-Kurulum.exe`: Lokal lifetime masaustu uygulamasi. Web, mobil ve bulut senkronizasyon kapali olur.
- `downloads/ServisTakipPanel.apk`: Native Android uygulamasi. Offline kayit tutar, internet gelince web hesabi ile senkronize olur.

## Kontrol

Deploy sonrasi hizli kontrol:

```bash
php -l index.php
php -l api/_base.php
php -l models/Servis.php
php -l models/Satis.php
php -l models/Tahsilat.php
```

Ardindan tarayicida landing, kayit/giris, admin panel, musteri ekleme, servis kaydi, satis kaydi, tahsilat, masaustu senkronizasyon, lokal lifetime masaustu ve Android senkronizasyon akisini deneyin.
