<?php

namespace Aytackayin\YoutubeToBlog\Traits;

/**
 * Trait HasYouTubeApiToken
 * 
 * Bu trait'i User modeline ekleyerek YouTube API token desteği ekleyebilirsiniz.
 * 
 * Örnek kullanım:
 * ```php
 * use Aytackayin\YoutubeToBlog\Traits\HasYouTubeApiToken;
 * 
 * class User extends Authenticatable
 * {
 *     use HasYouTubeApiToken;
 *     // ...
 * }
 * ```
 */
trait HasYouTubeApiToken
{
    /**
     * Initialize the trait.
     */
    public function initializeHasYouTubeApiToken(): void
    {
        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');

        // Add to fillable if not already there
        if (!in_array($tokenColumn, $this->fillable)) {
            $this->fillable[] = $tokenColumn;
        }

        // Add to hidden for security
        if (!in_array($tokenColumn, $this->hidden)) {
            $this->hidden[] = $tokenColumn;
        }
    }

    /**
     * Get the YouTube API token.
     */
    public function getYouTubeApiToken(): ?string
    {
        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');
        return $this->{$tokenColumn};
    }

    /**
     * Set the YouTube API token.
     */
    public function setYouTubeApiToken(string $token): void
    {
        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');
        $this->{$tokenColumn} = $token;
        $this->save();
    }

    /**
     * Generate a new YouTube API token.
     */
    public function generateYouTubeApiToken(): string
    {
        $token = \Illuminate\Support\Str::random(40);
        $this->setYouTubeApiToken($token);
        return $token;
    }

    /**
     * Check if user has valid YouTube API token.
     */
    public function hasYouTubeApiToken(): bool
    {
        return !empty($this->getYouTubeApiToken());
    }

    /**
     * Check if user can access YouTube extension.
     */
    public function canAccessYouTubeExtension(): bool
    {
        $permissionName = config('youtube-to-blog.permission_name', 'AccessYouTubeExtension');

        if (!method_exists($this, 'can')) {
            return true; // If no permission system, allow by default
        }

        return $this->can($permissionName);
    }
}
