<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('planned_sets')->default(3);
            $table->unsignedInteger('rep_min')->nullable();
            $table->unsignedInteger('rep_max')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workout_session_id', 'position']);
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_items');
    }
};
