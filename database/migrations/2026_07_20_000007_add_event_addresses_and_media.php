<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('formatted_address', 320)->nullable()->after('venue_name');
            $table->string('address_line_1', 180)->nullable()->after('formatted_address');
            $table->string('postal_code', 24)->nullable()->after('region');
        });

        Schema::create('event_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id');
            $table->string('disk', 32)->default('public');
            $table->string('path')->unique();
            $table->string('card_path')->unique();
            $table->unsignedTinyInteger('position');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('card_width');
            $table->unsignedInteger('card_height');
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('alt', 180);
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['formatted_address', 'address_line_1', 'postal_code']);
        });
    }
};
