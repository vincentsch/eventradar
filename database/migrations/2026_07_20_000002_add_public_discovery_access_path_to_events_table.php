<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE events
                ADD COLUMN is_public TINYINT(1)
                    GENERATED ALWAYS AS (status IN ('published', 'sold_out')) STORED,
                ADD CONSTRAINT events_duration_limit_check
                    CHECK (ends_at <= DATE_ADD(starts_at, INTERVAL 72 HOUR)),
                ADD INDEX events_public_cursor_index (is_public, starts_at, id, ends_at),
                ADD INDEX events_public_location_options_index (
                    is_public, ends_at, location_key, locality, region, country
                )
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE events
                DROP INDEX events_public_location_options_index,
                DROP INDEX events_public_cursor_index,
                DROP CONSTRAINT events_duration_limit_check,
                DROP COLUMN is_public
        SQL);
    }
};
