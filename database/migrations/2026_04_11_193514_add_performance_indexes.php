<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });

        Schema::table('ignores', function (Blueprint $table) {
            $table->index(['ignorer_id', 'expires_at']);
            $table->index(['ignored_id', 'expires_at']);
        });

        Schema::table('trashes', function (Blueprint $table) {
            $table->index('expires_at');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('ignores', function (Blueprint $table) {
            $table->dropIndex(['ignorer_id', 'expires_at']);
            $table->dropIndex(['ignored_id', 'expires_at']);
        });

        Schema::table('trashes', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropIndex(['blocked_id']);
        });
    }
};
