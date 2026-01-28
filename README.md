# YouTube to Blog Filament Package

A comprehensive Laravel/Filament package that converts YouTube videos into blog posts via a Chrome extension, automatically downloading the video to the gallery.

> **v1.0.0 Features:**
> - Background video downloading (Queue worker)
> - Automatically running worker (Special trigger for Windows)
> - Instant status tracking with Chrome Extension (Polling)
> - "Human-like" downloading capabilities (YouTube 403 bypass)

## Requirements

- **Laravel 11+** or **Laravel 12**
- **Filament 4.x**
- **yt-dlp:** Must be installed on the server.
- **PHP Queue Worker:** Required for background processes (The package automatically triggers locally).

## Installation

### 1. Install the Package

(After publishing on Packagist):

```bash
composer require aytackayin/youtube-to-blog
```

During development with `composer.json` repositories setting:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/aytackayin/youtube-to-blog"
    }
]
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Publish Config File

```bash
php artisan vendor:publish --tag=youtube-to-blog-config
```

### 4. yt-dlp Installation (Important!)

`yt-dlp` must be installed on the server for the video download feature to work.

**Windows:**
1. Download `yt-dlp.exe`.
2. Place it in a folder in your project (e.g., `extensions/youtube-to-blog/`).
3. Add the path to your `.env` file:

```env
YT_DLP_PATH="C:/laragon/www/project/extensions/youtube-to-blog/yt-dlp.exe"
```

**Linux:**
1. Install via terminal:
   ```bash
   sudo curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
   sudo chmod a+rx /usr/local/bin/yt-dlp
   ```
2. Configure `.env`:
   ```env
   YT_DLP_PATH="/usr/local/bin/yt-dlp"
   ```

### 5. Chrome Extension Installation

This package comes with a Chrome Extension.

1. **Download the Package:** Download the `youtube-to-blog-extension.zip` file from this repository.
2. **Unzip:** Extract the files to a folder.
3. **Install on Chrome:**
   - Go to `chrome://extensions` in Chrome.
   - Enable **Developer mode** in the top right.
   - Click **Load unpacked** and select the folder you extracted.
4. **Configure:**
   - Click the extension icon.
   - **Site URL:** `https://your-site.com` (or `http://localhost/project`)
   - **API Key:** Enter the Chrome Token generated from your profile page.

## Usage

1. **Go to YouTube:** Open any video.
2. **"Save as Blog" Button:** Click the button added below the video or the extension icon.
3. **Select Category:** Select a category from the popup and click "Save".
4. **Process Tracking:**
   - The extension will first say "Saved".
   - The video starts downloading in the background (Depends on server load).
   - When the video is downloaded, the Extension gives a "Completed" notification.
   - A system notification also appears in the Panel.

## Model Settings

Add the trait to `App\Models\User` model:

```php
use Aytackayin\YoutubeToBlog\Traits\HasYouTubeApiToken;

class User extends Authenticatable
{
    use HasYouTubeApiToken;
}
```

Add the API key management component to your Filament profile page:

```php
use Aytackayin\YoutubeToBlog\Filament\Components\YouTubeApiKeyComponent;

YouTubeApiKeyComponent::getTab(),
```

## Configuration

You can change the Model classes and file path settings via `config/youtube-to-blog.php`.

```php
return [
    'disk' => 'attachments', // Disk where files will be uploaded
    'video_download_enabled' => true,
    'yt_dlp_path' => env('YT_DLP_PATH'),
    // ...
];
```

## License

MIT License
