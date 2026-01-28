<?php

namespace Aytackayin\YoutubeToBlog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Exception;

class YouTubeIntegrationController extends Controller
{
    /**
     * Get the Blog model class
     */
    protected function getBlogModel(): string
    {
        return config('youtube-to-blog.blog_model', \App\Models\Blog::class);
    }

    /**
     * Get the BlogCategory model class
     */
    protected function getBlogCategoryModel(): string
    {
        return config('youtube-to-blog.blog_category_model', \App\Models\BlogCategory::class);
    }

    /**
     * Get the TouchFile model class (nullable)
     */
    protected function getTouchFileModel(): ?string
    {
        $model = config('youtube-to-blog.touch_file_model');
        return $model && class_exists($model) ? $model : null;
    }

    /**
     * Get storage disk
     */
    protected function getDisk()
    {
        return Storage::disk(config('youtube-to-blog.disk', 'attachments'));
    }

    /**
     * Create a new category from the extension.
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:blog_categories,id',
        ]);

        $categoryModel = $this->getBlogCategoryModel();

        $categoryData = [
            'user_id' => auth()->id(),
            'language_id' => config('youtube-to-blog.default_language_id', 1),
            'title' => $validated['title'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_published' => true,
        ];

        // Generate slug if model has the method
        if (method_exists($categoryModel, 'generateUniqueSlug')) {
            $categoryData['slug'] = $categoryModel::generateUniqueSlug($validated['title']);
        } else {
            $categoryData['slug'] = Str::slug($validated['title']);
        }

        $category = $categoryModel::create($categoryData);

        return response()->json([
            'message' => 'Kategori oluşturuldu.',
            'category' => $category,
        ], 201);
    }

    /**
     * List categories for the Chrome extension selection (as a tree).
     */
    public function getCategories()
    {
        $categoryModel = $this->getBlogCategoryModel();
        $categories = $categoryModel::orderBy('sort')->orderBy('title')->get();
        return response()->json($this->buildTree($categories));
    }

    private function buildTree($categories, $parentId = null)
    {
        $branch = [];
        foreach ($categories as $category) {
            if ($category->parent_id == $parentId) {
                $children = $this->buildTree($categories, $category->id);
                if ($children) {
                    $category['children'] = $children;
                }
                $branch[] = $category;
            }
        }
        return $branch;
    }

    /**
     * Create a blog post from YouTube data.
     */
    public function store(Request $request)
    {
        // Prevent script termination if client disconnects
        ignore_user_abort(true);
        set_time_limit(0);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'video_id' => 'required|string',
            'description' => 'nullable|string',
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:blog_categories,id',
            'note' => 'nullable|string',
            'add_to_attachments' => 'nullable|boolean',
        ]);

        $blogModel = $this->getBlogModel();
        $touchFileModel = $this->getTouchFileModel();
        $disk = $this->getDisk();

        // Check if video already exists
        $existingBlog = $blogModel::where('attachments', 'like', '%' . $validated['video_id'] . '%')->first();
        if ($existingBlog) {
            return response()->json([
                'message' => 'Bu video zaten bir blog olarak kayıtlı.',
                'blog_id' => $existingBlog->id
            ], 422);
        }

        // Generate unique slug
        if (method_exists($blogModel, 'generateUniqueSlug')) {
            $slug = $blogModel::generateUniqueSlug($validated['title']);
        } else {
            $slug = Str::slug($validated['title']);
        }

        // Default content (YouTube embed)
        $embedHtml = '<div class="video-container"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . $validated['video_id'] . '" frameborder="0" allowfullscreen></iframe></div>';
        $content = $embedHtml;

        if (!empty($validated['note'])) {
            $content .= '<br><h3>Notlarım:</h3>' . nl2br(e($validated['note'])) . '<br>';
        }

        $description = e($validated['description']);
        // Linkify
        $description = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" style="color: #6366f1; text-decoration: underline;">$1</a>',
            $description
        );
        $content .= '<br>' . nl2br($description);

        // Create Blog First
        $blog = $blogModel::create([
            'user_id' => auth()->id(),
            'language_id' => config('youtube-to-blog.default_language_id', 1),
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $content,
            'is_published' => false,
        ]);

        // Sync categories
        if (method_exists($blog, 'categories')) {
            $blog->categories()->sync($validated['category_ids']);
        }

        // Extract Tags
        if (!empty($validated['description'])) {
            preg_match_all('/#(\w+)/u', $validated['description'], $matches);
            if (!empty($matches[1])) {
                $blog->update(['tags' => array_values(array_unique($matches[1]))]);
            }
        }

        $attachments = [];

        // Get storage folder method
        $storageFolder = method_exists($blogModel, 'getStorageFolder')
            ? $blogModel::getStorageFolder()
            : 'blog';

        // 1. Handle Thumbnail / Cover Image (Synchronous)
        $imgPath = null;
        try {
            $videoId = $validated['video_id'];
            $thumbUrl = "https://i.ytimg.com/vi/{$videoId}/maxresdefault.jpg";
            $imageResponse = Http::get($thumbUrl);

            if (!$imageResponse->successful()) {
                $thumbUrl = "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
                $imageResponse = Http::get($thumbUrl);
            }

            if ($imageResponse->successful()) {
                $imgFolder = "{$storageFolder}/{$blog->id}/images";
                $imgFilename = "youtube-cover.jpg";
                $imgPath = "{$imgFolder}/{$imgFilename}";

                if (!$disk->exists($imgFolder)) {
                    $disk->makeDirectory($imgFolder);
                }
                $disk->put($imgPath, $imageResponse->body());

                // Register and generate thumbnails for the cover image
                if ($touchFileModel && method_exists($touchFileModel, 'registerFile')) {
                    $touchFileModel::registerFile($imgPath, auth()->id());
                    $touchFile = $touchFileModel::where('path', $imgPath)->first();
                    if ($touchFile && method_exists($touchFile, 'generateThumbnails')) {
                        $touchFile->generateThumbnails();
                    }
                }

                // Add to gallery
                $attachments[] = $imgPath;
            }
        } catch (Exception $e) {
            // Silent fail for thumbnail fetching
        }

        // Finalize initial attachments (Cover Image)
        if (!empty($attachments)) {
            $blog->update(['attachments' => $attachments]);
        }

        // 2. Dispatch Job for Video Download (Asynchronous)
        if ($request->boolean('add_to_attachments')) {
            \Aytackayin\YoutubeToBlog\Jobs\ProcessYouTubeVideo::dispatch($blog, $validated);

            // Trigger Queue Worker (Windows) - "Fire and Forget"
            // This starts the queue worker only for this job and stops when empty
            try {
                $artisanPath = base_path('artisan');
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    pclose(popen("start /B php {$artisanPath} queue:work --stop-when-empty", "r"));
                } else {
                    exec("php {$artisanPath} queue:work --stop-when-empty > /dev/null 2>&1 &");
                }
            } catch (Exception $e) {
                // Log error but don't fail the request
                \Illuminate\Support\Facades\Log::error("Failed to auto-start queue worker: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Blog taslağı oluşturuldu. Video indirme işlemi başlatıldı (Oto-Worker).',
            'blog_id' => $blog->id,
            'url' => url("/admin/blogs/{$blog->id}/edit"),
        ], 201);
    }



    /**
     * Check the status of blog video processing.
     */
    public function checkStatus($id)
    {
        $blogModel = $this->getBlogModel();
        $blog = $blogModel::find($id);

        if (!$blog) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // Check if content has local video player instead of iframe
        $isLocalVideo = strpos($blog->content, '<video') !== false;

        // Also check if attachments has mp4
        $hasVideoAttachment = false;
        if (!empty($blog->attachments)) {
            foreach ($blog->attachments as $attachment) {
                if (str_ends_with($attachment, '.mp4')) {
                    $hasVideoAttachment = true;
                    break;
                }
            }
        }

        if ($isLocalVideo || $hasVideoAttachment) {
            return response()->json([
                'status' => 'completed',
                'message' => 'Video işleme tamamlandı.'
            ]);
        }

        return response()->json([
            'status' => 'processing',
            'message' => 'Video indiriliyor...'
        ]);
    }

    /**
     * Get thumbnail sizes from model or config
     */
    protected function getThumbnailSizes($blog): array
    {
        if (method_exists($blog, 'getThumbnailSizes')) {
            return $blog->getThumbnailSizes();
        }

        return [150, 300, 600];
    }
}
