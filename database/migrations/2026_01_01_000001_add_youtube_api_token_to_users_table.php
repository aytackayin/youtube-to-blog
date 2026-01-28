<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');

        // Check if column already exists
        if (!Schema::hasColumn('users', $tokenColumn)) {
            Schema::table('users', function (Blueprint $table) use ($tokenColumn) {
                $table->string($tokenColumn)->nullable()->unique()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tokenColumn = config('youtube-to-blog.api_token_column', 'youtube_api_token');

        if (Schema::hasColumn('users', $tokenColumn)) {
            Schema::table('users', function (Blueprint $table) use ($tokenColumn) {
                $table->dropColumn($tokenColumn);
            });
        }
    }
};
