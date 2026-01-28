<?php

namespace Aytackayin\YoutubeToBlog\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Added Logging
use App\Models\Blog;
use Exception;
use Illuminate\Support\Str;

class ProcessYouTubeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // Increased to 2 hours

    protected $blog;
    protected $data;
    protected $blogModelClass;
    protected $touchFileModelClass;
    protected $diskName;
    protected $ytDlpPath;

    /**
     * Create a new job instance.
     */
    public function __construct($blog, array $data)
    {
        $this->blog = $blog;
        $this->data = $data;
        $this->blogModelClass = config('youtube-to-blog.blog_model', \App\Models\Blog::class);
        $this->touchFileModelClass = config('youtube-to-blog.touch_file_model');
        $this->diskName = config('youtube-to-blog.disk', 'attachments');
        $this->ytDlpPath = config('youtube-to-blog.yt_dlp_path');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessYouTubeVideo Started for Blog ID: " . $this->blog->id);

        // Notify User: Started
        if (class_exists(\Filament\Notifications\Notification::class)) {
            \Filament\Notifications\Notification::make()
                ->title('Video İşleme Başladı')
                ->body("{$this->blog->title} için video indirme işlemi arka planda başlatıldı.")
                ->info()
                ->sendToDatabase(\App\Models\User::find($this->blog->user_id));
        }

        $disk = Storage::disk($this->diskName);
        $blogModel = $this->blogModelClass;
        $touchFileModel = $this->touchFileModelClass;

        $attachments = $this->blog->attachments ?? [];
        $videoDownloaded = false;
        $localVideoPath = null;
        $slug = $this->blog->slug;

        // Get storage folder method
        $storageFolder = method_exists($blogModel, 'getStorageFolder')
            ? $blogModel::getStorageFolder()
            : 'blog';

        // 1. Handle Video Download
        if (($this->data['add_to_attachments'] ?? false) && config('youtube-to-blog.video_download_enabled', true)) {
            try {
                if (file_exists($this->ytDlpPath)) {
                    $videoUrl = "https://www.youtube.com/watch?v=" . $this->data['video_id'];

                    $videoFolder = "{$storageFolder}/{$this->blog->id}/videos";
                    $videoFilename = "{$slug}.mp4";
                    $videoPath = "{$videoFolder}/{$videoFilename}";
                    $absSavePath = $disk->path($videoPath);

                    if (!$disk->exists($videoFolder)) {
                        $disk->makeDirectory($videoFolder);
                    }

                    Log::info("Starting yt-dlp download for video: {$videoUrl} to {$absSavePath}");

                    // Run yt-dlp with timeout
                    $result = Process::timeout(7100)->run([
                        $this->ytDlpPath,
                        $videoUrl,
                        '--user-agent',
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        '--referer',
                        'https://www.youtube.com/',
                        '--no-cache-dir',
                        '--ignore-errors',
                        '--no-check-certificates',
                        '--extractor-args',
                        'youtube:player_client=android',
                        '-f',
                        'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
                        '-o',
                        $absSavePath,
                        '--no-playlist',
                        '--no-mtime'
                    ]);

                    if ($result->successful() && $disk->exists($videoPath)) {
                        Log::info("yt-dlp download successful.");
                        $attachments[] = $videoPath;
                        $localVideoPath = $videoPath;
                        $videoDownloaded = true;

                        // Register video in TouchFile
                        if ($touchFileModel && class_exists($touchFileModel) && method_exists($touchFileModel, 'registerFile')) {
                            $touchFileModel::registerFile($videoPath, $this->blog->user_id);
                        }
                    } else {
                        Log::error("yt-dlp download failed: " . $result->errorOutput());
                        $attachments[] = "https://www.youtube.com/watch?v=" . $this->data['video_id'];

                        // Notify User: Failed
                        if (class_exists(\Filament\Notifications\Notification::class)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Video İndirme Başarısız')
                                ->body("{$this->blog->title} videosu indirilemedi. YouTube bağlantısı kullanıldı.")
                                ->danger()
                                ->sendToDatabase(\App\Models\User::find($this->blog->user_id));
                        }
                    }
                } else {
                    Log::warning("yt-dlp executable not found at: " . $this->ytDlpPath);
                    $attachments[] = "https://www.youtube.com/watch?v=" . $this->data['video_id'];
                }
            } catch (Exception $e) {
                Log::error("Exception handles video download: " . $e->getMessage());
                $attachments[] = "https://www.youtube.com/watch?v=" . $this->data['video_id'];
            }
        }

        // 2. Handle Thumbnail / Cover Image (Already handled in controller usually, but if job handles it too)
        // Note: Controller acts mostly synchronous for cover now, but job has duplicate logic.
        // We will keep job logic as fallback or for higher res processing if needed.
        $imgPath = null;
        try {
            $videoId = $this->data['video_id'];
            $thumbUrl = "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg";
            $imageResponse = Http::get($thumbUrl);

            if (!$imageResponse->successful()) {
                $thumbUrl = "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
                $imageResponse = Http::get($thumbUrl);
            }

            if ($imageResponse->successful()) {
                $imgFolder = "{$storageFolder}/{$this->blog->id}/images";
                $thumbsFolder = "{$storageFolder}/{$this->blog->id}/videos/thumbs";
                $imgFilename = "youtube-cover.jpg";
                $imgPath = "{$imgFolder}/{$imgFilename}";

                if (!$disk->exists($imgFolder)) {
                    $disk->makeDirectory($imgFolder);
                }
                $disk->put($imgPath, $imageResponse->body());

                // Register cover image
                if ($touchFileModel && class_exists($touchFileModel) && method_exists($touchFileModel, 'registerFile')) {
                    $touchFileModel::registerFile($imgPath, $this->blog->user_id);
                    $touchFile = $touchFileModel::where('path', $imgPath)->first();
                    if ($touchFile && method_exists($touchFile, 'generateThumbnails')) {
                        $touchFile->generateThumbnails();
                    }
                }

                // Add to gallery if not present
                if (!in_array($imgPath, $attachments)) {
                    $attachments[] = $imgPath;
                }

                if ($videoDownloaded) {
                    // Video thumbnails
                    if (!$disk->exists($thumbsFolder)) {
                        $disk->makeDirectory($thumbsFolder);
                    }

                    if (class_exists(\Intervention\Image\ImageManager::class) && class_exists(\Intervention\Image\Drivers\Gd\Driver::class)) {
                        $sizes = $this->getThumbnailSizes($this->blog);
                        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                        foreach ($sizes as $size) {
                            try {
                                $img = $manager->read($imageResponse->body());
                                $img->scale(width: (int) $size);
                                $disk->put("{$thumbsFolder}/{$slug}_{$size}.jpg", $img->toJpeg()->toString());
                            } catch (Exception $e) {
                                // Silent fail
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail
        }

        // Finalize attachments
        if (!empty($attachments)) {
            $currentAttachments = $this->blog->attachments ?? [];

            // Merge new attachments
            foreach ($attachments as $attachment) {
                if (!in_array($attachment, $currentAttachments)) {
                    // If video, prepend to start
                    if (str_ends_with($attachment, '.mp4')) {
                        array_unshift($currentAttachments, $attachment);
                    } else {
                        // Else append to end
                        $currentAttachments[] = $attachment;
                    }
                }
            }

            $this->blog->update(['attachments' => array_values($currentAttachments)]);
            Log::info("Updated attachments for Blog ID: " . $this->blog->id);
        }

        // 3. Update Content
        if ($videoDownloaded && $localVideoPath) {
            $embedHtml = '<div class="video-container"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . $this->data['video_id'] . '" frameborder="0" allowfullscreen></iframe></div>';

            $localUrl = Storage::disk($this->diskName)->url($localVideoPath);
            $posterUrl = $imgPath ? Storage::disk($this->diskName)->url($imgPath) : null;

            $localVideoHtml = '<div class="video-container"><video controls width="100%"' . ($posterUrl ? ' poster="' . $posterUrl . '"' : '') . '><source src="' . $localUrl . '" type="video/mp4"></video></div>';

            // İçerikteki iframe'i yerel video ile değiştir
            // We need to refresh the blog model to ensure we have the latest content in case of race conditions
            $this->blog->refresh();
            $newContent = str_replace($embedHtml, $localVideoHtml, $this->blog->content);
            $this->blog->update(['content' => $newContent]);
            Log::info("Updated blog content with local video for Blog ID: " . $this->blog->id);

            // Notify User: Success
            if (class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title('Video İşleme Tamamlandı')
                    ->body("{$this->blog->title} blog yazısı için video başarıyla indirildi ve eklendi.")
                    ->success()
                    ->sendToDatabase(\App\Models\User::find($this->blog->user_id));
            }
        }

        Log::info("ProcessYouTubeVideo Finished for Blog ID: " . $this->blog->id);
    }

    protected function getThumbnailSizes($blog): array
    {
        if (method_exists($blog, 'getThumbnailSizes')) {
            return $blog->getThumbnailSizes();
        }
        return [150, 300, 600];
    }
}
