<?php

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Events\ImageRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_image_sets', function (Blueprint $table): void {
            $table->string('key', 96)->primary();
            $table->enum('category', EventType::values())->index();
        });

        Schema::create('event_images', function (Blueprint $table): void {
            $table->id();
            $table->string('image_set_key', 96);
            $table->enum('role', ImageRole::values());
            $table->string('path')->unique();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->string('alt');

            $table->foreign('image_set_key')
                ->references('key')
                ->on('event_image_sets')
                ->cascadeOnDelete();
            $table->unique(['image_set_key', 'role']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('organizer_name', 120);
            $table->string('venue_name', 120);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64);
            $table->date('starts_on_local');
            $table->string('location_key', 64);
            $table->string('locality', 80);
            $table->string('region', 80)->nullable();
            $table->string('country', 80);
            $table->char('country_code', 2);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('image_set_key', 96);
            $table->enum('status', EventStatus::values());
            $table->enum('type', EventType::values());
            $table->json('tags');
            $table->decimal('minimum_price', 12, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->mediumText('payload');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('image_set_key')
                ->references('key')
                ->on('event_image_sets')
                ->restrictOnDelete();

            $table->index(['status', 'starts_at', 'id'], 'events_public_feed_index');
            $table->index(['status', 'starts_on_local', 'id'], 'events_public_local_date_index');
            $table->index(['status', 'type', 'starts_at', 'id'], 'events_public_type_index');
            $table->index(['country_code', 'locality', 'starts_at', 'id'], 'events_location_index');
            $table->index(['starts_at', 'id'], 'events_starts_at_index');
            $table->index(['latitude', 'longitude'], 'events_coordinates_index');
        });

        $this->addMySqlChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_images');
        Schema::dropIfExists('event_image_sets');
    }

    private function addMySqlChecks(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE events ADD CONSTRAINT events_time_order_check CHECK (ends_at > starts_at)');
        DB::statement(<<<'SQL'
            ALTER TABLE events ADD CONSTRAINT events_coordinates_check CHECK (
                (latitude IS NULL AND longitude IS NULL)
                OR (latitude IS NOT NULL AND longitude IS NOT NULL
                    AND latitude BETWEEN -90 AND 90
                    AND longitude BETWEEN -180 AND 180)
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE events ADD CONSTRAINT events_price_check CHECK (
                (minimum_price IS NULL AND currency_code IS NULL)
                OR (minimum_price IS NOT NULL AND currency_code IS NOT NULL
                    AND minimum_price >= 0
                    AND REGEXP_LIKE(currency_code, '^[A-Z]{3}$', 'c'))
            )
        SQL);
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_country_code_check CHECK (REGEXP_LIKE(country_code, '^[A-Z]{2}$', 'c'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_check CHECK (capacity IS NULL OR capacity > 0)');
        DB::statement('ALTER TABLE event_images ADD CONSTRAINT event_images_dimensions_check CHECK (width > 0 AND height > 0)');
    }
};
