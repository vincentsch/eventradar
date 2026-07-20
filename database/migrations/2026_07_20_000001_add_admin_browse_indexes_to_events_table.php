<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->index(['type', 'starts_at', 'id'], 'events_admin_type_index');
            $table->index(['country_code', 'starts_at', 'id'], 'events_admin_country_index');
            $table->index(['starts_on_local', 'starts_at', 'id'], 'events_admin_local_date_index');
            $table->index(['title', 'starts_at', 'id'], 'events_admin_title_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_admin_type_index');
            $table->dropIndex('events_admin_country_index');
            $table->dropIndex('events_admin_local_date_index');
            $table->dropIndex('events_admin_title_index');
        });
    }
};
