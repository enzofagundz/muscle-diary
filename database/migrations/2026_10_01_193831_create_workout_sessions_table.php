<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('workout_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('performed_on');
            $table->string('location')->nullable();
            $table->unsignedInteger('rest_seconds')->default(90);
            $table->text('notes')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'performed_on']);
            $table->index(['user_id', 'finished_at']);
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
