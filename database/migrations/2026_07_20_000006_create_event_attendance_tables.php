<?php

use App\Domain\Attendance\AttendanceIntent;
use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('intent', AttendanceIntent::values());
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'cancelled_at', 'updated_at'], 'attendance_user_active_index');
            $table->index(['event_id', 'cancelled_at', 'intent'], 'attendance_event_summary_index');
        });

        Schema::create('attendance_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('attendance_id');
            $table->unsignedInteger('attendance_revision');
            $table->enum('kind', DeliveryKind::values());
            $table->enum('status', DeliveryStatus::values())->default(DeliveryStatus::Pending->value);
            $table->timestamp('due_at');
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->foreign('attendance_id')
                ->references('id')
                ->on('event_attendances')
                ->cascadeOnDelete();
            $table->unique(
                ['attendance_id', 'attendance_revision', 'kind'],
                'attendance_delivery_unique',
            );
            $table->index(['status', 'due_at', 'id'], 'attendance_delivery_due_index');
            $table->index(['attendance_id', 'status'], 'attendance_delivery_state_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_deliveries');
        Schema::dropIfExists('event_attendances');
    }
};
