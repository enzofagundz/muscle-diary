<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->ulid('based_on_id')->nullable();
            $table->string('name');
            $table->string('muscle_group');
            $table->string('unit_default')->default('kg');
            $table->decimal('kg_per_plate', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'deleted_at']);
            $table->index('muscle_group');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
