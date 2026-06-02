# Servis Takip Panel - Masaustu Uygulamasi

## Calisma Mantigi

- Uygulama kendi icinde PHP sunucusu baslatir.
- Lokal SQLite veritabani kullanicinin AppData klasorunde tutulur.
- Ilk acilista firma, ad soyad, e-posta ve sifre ile yerel kullanici olusturulur.
- Sonraki acilislarda ayni e-posta/sifre ile offline giris yapilir.
- Kod girisi yoktur; web hesabi ile senkronizasyon yapilir.

## Derleme

```bash
cd desktop-app
npm install
npm run dist
```

Cikti:

```text
dist/ServisTakipPanel-1.0.0-Kurulum.exe
```

Landing sayfasindaki indirme dosyasi:

```text
../downloads/ServisTakipPanel-Kurulum.exe
```

## Senkron Notu

Veritabani `uuid`, `deleted_at`, `synced_at`, `sync_version`, `sync_queue` ve `sync_state` alanlariyla offline-first senkron icin hazirlandi. Ayarlar ekranindan web hesabi baglanabilir; baglanti varsa uygulama arka planda otomatik senkronize eder.
