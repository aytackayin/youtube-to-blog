<?php

namespace Aytackayin\YoutubeToBlog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class YouTubeExtensionAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-KEY');

        if (!$token) {
            return response()->json(['message' => 'API anahtarı bulunamadı.'], 401);
        }

        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');
        $permissionName = config('youtube-to-blog.permission_name', 'AccessYouTubeExtension');

        // Get User model from Laravel's auth config
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        $user = $userModel::where($tokenColumn, $token)->first();

        if (!$user) {
            return response()->json(['message' => 'Geçersiz API anahtarı.'], 401);
        }

        // Security: Check if user has permission to use the extension
        // Check if the user model has the can() method (Spatie Permission)
        if (method_exists($user, 'can') && !$user->can($permissionName)) {
            return response()->json(['message' => 'Bu eklentiyi kullanma yetkiniz bulunmamaktadır.'], 403);
        }

        auth()->login($user);

        return $next($request);
    }
}
