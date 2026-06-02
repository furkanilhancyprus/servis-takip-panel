# Servis Takip Panel - Android APK

## Calisma Mantigi

- Uygulama native Java ile yazildi; lokal SQLite veritabani kullanir.
- `https://servistakippanel.com` API'leri ile senkronize olur.
- Kullanici web hesabi ile giris yapar.
- Mobil oturum token olarak saklanir.
- Baglanti yoksa kullaniciya tekrar dene ekrani gosterilir.
- Dis linkler uygulama icinde degil, telefonun tarayicisinda acilir.

## Gereksinimler
- Android 7.0+ (API 24+)
- Internet baglantisi zorunlu (sunucuya baglanir)

## Debug Derleme

```bash
gradle assembleDebug
```

Bu ortamda Gradle PATH'e ekli degilse cache'teki Gradle kullanilabilir:

```powershell
& "$env:USERPROFILE\.gradle\wrapper\dists\gradle-8.14.3-bin\cv11ve7ro1n3o1j4so8xd9n66\gradle-8.14.3\bin\gradle.bat" --offline assembleDebug
```

Debug APK:

```text
app/build/outputs/apk/debug/app-debug.apk
```

## Canli Yayin

Google Play veya site indirmesi icin release APK/AAB imzalanmalidir. Debug APK sadece test icindir.
