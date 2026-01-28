<?php

return [
    /*
    |--------------------------------------------------------------------------
    | YouTube Video Download (yt-dlp)
    |--------------------------------------------------------------------------
    |
    | YouTube videolarını sunucuya indirmek için yt-dlp yürütülebilir dosyasının yolu.
    | Windows için .exe uzantılı dosya kullanılmalıdır.
    |
    */
    'yt_dlp_path' => env('YT_DLP_PATH', base_path('extensions/youtube-to-blog/yt-dlp.exe')),

    /*
    |--------------------------------------------------------------------------
    | Video Download Enabled
    |--------------------------------------------------------------------------
    |
    | Video indirme özelliğinin aktif olup olmadığını belirler.
    | Kapatıldığında sadece YouTube embed linki kullanılır.
    |
    */
    'video_download_enabled' => env('YT_VIDEO_DOWNLOAD', true),

    /*
    |--------------------------------------------------------------------------
    | Default Language ID
    |--------------------------------------------------------------------------
    |
    | Oluşturulan blog yazıları için varsayılan dil ID'si.
    |
    */
    'default_language_id' => env('YT_DEFAULT_LANGUAGE_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | API route'ları için prefix.
    |
    */
    'route_prefix' => 'api/youtube',

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Dosyaların kaydedileceği disk.
    |
    */
    'disk' => 'attachments',

    /*
    |--------------------------------------------------------------------------
    | Blog Model
    |--------------------------------------------------------------------------
    |
    | Kullanılacak Blog model sınıfı.
    |
    */
    'blog_model' => \App\Models\Blog::class,

    /*
    |--------------------------------------------------------------------------
    | Blog Category Model
    |--------------------------------------------------------------------------
    |
    | Kullanılacak BlogCategory model sınıfı.
    |
    */
    'blog_category_model' => \App\Models\BlogCategory::class,

    /*
    |--------------------------------------------------------------------------
    | TouchFile Model
    |--------------------------------------------------------------------------
    |
    | Dosya yönetimi için kullanılacak model.
    | null bırakılırsa TouchFile kullanılmaz.
    |
    */
    'touch_file_model' => \App\Models\TouchFile::class,

    /*
    |--------------------------------------------------------------------------
    | Permission Name
    |--------------------------------------------------------------------------
    |
    | Chrome extension erişimi için gerekli permission adı.
    | Filament Shield ile uyumlu olmalıdır.
    |
    */
    'permission_name' => 'AccessChromeExtension',

    /*
    |--------------------------------------------------------------------------
    | API Token Column
    |--------------------------------------------------------------------------
    |
    | Users tablosunda API token için kullanılacak kolon adı.
    |
    */
    'api_token_column' => 'chrome_token',
];
