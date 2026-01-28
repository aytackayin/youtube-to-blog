<?php

namespace Aytackayin\YoutubeToBlog\Filament\Components;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class YouTubeApiKeyComponent
{
    /**
     * Get the schema for the YouTube API key management section.
     * 
     * @param string|null $permissionName Permission name to check (null to skip check)
     * @return array
     */
    public static function getSchema(?string $permissionName = null): array
    {
        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');
        $permissionName = $permissionName ?? config('youtube-to-blog.permission_name', 'AccessYouTubeExtension');

        return [
            Grid::make(1)->schema([
                Section::make()
                    ->description('YouTube videolarını tek tıkla blog makalesine dönüştürmek için bu bölümdeki API anahtarını kullanın.')
                    ->visible(function () use ($permissionName) {
                        if (!$permissionName) {
                            return true;
                        }
                        $user = auth()->user();
                        return $user && method_exists($user, 'can') && $user->can($permissionName);
                    })
                    ->schema([
                        TextInput::make($tokenColumn)
                            ->label('API Anahtarınız')
                            ->password()
                            ->revealable()
                            ->readOnly()
                            ->dehydrated()
                            ->helperText('Bu anahtarı Chrome eklentisi ayarlarında "API Key" alanına yapıştırın.'),

                        Actions::make([
                            Action::make('generateYouTubeToken')
                                ->label('Yeni Anahtar Oluştur')
                                ->icon('heroicon-o-arrow-path')
                                ->color('warning')
                                ->action(function (callable $set) use ($tokenColumn) {
                                    $token = Str::random(40);
                                    $set($tokenColumn, $token);

                                    Notification::make()
                                        ->title('Yeni API anahtarı hazırlandı. Kaydet butonu ile kalıcı hale getirebilirsiniz.')
                                        ->warning()
                                        ->send();
                                })
                                ->requiresConfirmation()
                                ->modalHeading('Emin misiniz?')
                                ->modalDescription('Yeni bir anahtar oluşturduğunuzda, eklenti içindeki eski anahtarınız geçersiz kalacaktır.'),
                        ]),
                    ]),
            ]),
        ];
    }

    /**
     * Get a Tab component for Filament profile pages.
     * 
     * @param string|null $permissionName Permission name to check (null to skip check)
     * @return \Filament\Schemas\Components\Tabs\Tab
     */
    public static function getTab(?string $permissionName = null)
    {
        $permissionName = $permissionName ?? config('youtube-to-blog.permission_name', 'AccessYouTubeExtension');

        return \Filament\Schemas\Components\Tabs\Tab::make('YouTubeExtension')
            ->label('YouTube Eklentisi')
            ->icon('heroicon-o-puzzle-piece')
            ->visible(function () use ($permissionName) {
                if (!$permissionName) {
                    return true;
                }
                $user = auth()->user();
                return $user && method_exists($user, 'can') && $user->can($permissionName);
            })
            ->schema(static::getSchema($permissionName));
    }
}
