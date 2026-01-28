# YouTube to Blog Filament Package

YouTube videolarını Chrome extension aracılığıyla blog yazısına dönüştüren, videoyu otomatik indirip galeriye ekleyen kapsamlı Laravel/Filament paketi.

> **v1.0.0 Özellikleri:**
> - Arka plan video indirme (Queue worker)
> - Otomatik çalışan worker (Windows için özel tetikleyici)
> - Chrome Eklentisi ile anlık durum takibi (Polling)
> - "İnsansı" indirme yetenekleri (YouTube 403 bypass)

## Gereksinimler

- **Laravel 11+** veya **Laravel 12**
- **Filament 4.x**
- **yt-dlp:** Sunucuda kurulu olmalıdır.
- **PHP Queue Worker:** Arka plan işlemleri için gereklidir (Paket localde otomatik tetikler).

## Kurulum

### 1. Paketi Yükleyin

(Packagist'te yayınlandıktan sonra):

```bash
composer require aytackayin/youtube-to-blog
```

Geliştirme aşamasında `composer.json` repositories ayarı ile:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/aytackayin/youtube-to-blog"
    }
]
```

### 2. Migration'ları Çalıştırın

```bash
php artisan migrate
```

### 3. Config Dosyasını Publish Edin

```bash
php artisan vendor:publish --tag=youtube-to-blog-config
```

### 4. yt-dlp Kurulumu (Önemli!)

Video indirme özelliğinin çalışması için sunucuda `yt-dlp` yüklü olmalıdır.

**Windows:**
1. `yt-dlp.exe` dosyasını indirin.
2. Projenizde bir klasöre (örn: `extensions/youtube-to-blog/`) koyun.
3. `.env` dosyasına yolunu ekleyin:

```env
YT_DLP_PATH="C:/laragon/www/proje/extensions/youtube-to-blog/yt-dlp.exe"
```

**Linux:**
1. Terminalden yükleyin:
   ```bash
   sudo curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
   sudo chmod a+rx /usr/local/bin/yt-dlp
   ```
2. `.env` ayarını yapın:
   ```env
   YT_DLP_PATH="/usr/local/bin/yt-dlp"
   ```

### 5. Chrome Extension Kurulumu

Bu paket beraberinde bir Chrome Eklentisi ile gelir.

1. **Paketi İndirin:** Bu repository'deki `youtube-to-blog-extension.zip` dosyasını indirin.
2. **Zip'i Açın:** Dosyaları bir klasöre çıkarın.
3. **Chrome'a Yükleyin:**
   - Chrome'da `chrome://extensions` adresine gidin.
   - Sağ üstten **Developer mode**'u açın.
   - **Load unpacked** butonuna basın ve çıkardığınız klasörü seçin.
4. **Ayarları Yapın:**
   - Eklenti ikonuna tıklayın.
   - **Site URL:** `https://siteniz.com` (veya `http://localhost/proje`)
   - **API Key:** Profil sayfanızdan oluşturduğunuz Chrome Token'ı girin.

## Kullanım

1. **YouTube'a Gidin:** Herhangi bir videoyu açın.
2. **"Blog Olarak Kaydet" Butonu:** Videonun altına eklenen butona veya eklenti ikonuna tıklayın.
3. **Kategori Seçin:** Açılan popup'tan kategori seçin ve "Kaydet" deyin.
4. **İşlem Takibi:**
   - Eklenti önce "Kaydedildi" diyecektir.
   - Video arka planda inmeye başlar (Sunucu yoğunluğuna göre değişir).
   - Video inince Eklenti "Tamamlandı" bildirimi verir.
   - Panelde de sistem bildirimi görünür.

## Model Ayarları

`App\Models\User` modeline trait'i ekleyin:

```php
use Aytackayin\YoutubeToBlog\Traits\HasYouTubeApiToken;

class User extends Authenticatable
{
    use HasYouTubeApiToken;
}
```

Filament profil sayfanıza API key yönetim bileşenini ekleyin:

```php
use Aytackayin\YoutubeToBlog\Filament\Components\YouTubeApiKeyComponent;

YouTubeApiKeyComponent::getTab(),
```

## Yapılandırma

`config/youtube-to-blog.php` üzerinden kullanılan Model sınıflarını ve dosya yolu ayarlarını değiştirebilirsiniz.

```php
return [
    'disk' => 'attachments', // Dosyaların yükleneceği disk
    'video_download_enabled' => true,
    'yt_dlp_path' => env('YT_DLP_PATH'),
    // ...
];
```

## Lisans

MIT License
