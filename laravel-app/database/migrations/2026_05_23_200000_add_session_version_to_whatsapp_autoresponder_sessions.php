<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_autoresponder_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('session_version')->default(1)->after('last_interaction_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_autoresponder_sessions', function (Blueprint $table): void {
            $table->dropColumn('session_version');
        });
    }
};
