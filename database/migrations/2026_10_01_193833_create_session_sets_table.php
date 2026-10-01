<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('session_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->unsignedInteger('part')->default(0);
            $table->decimal('load', 7, 2)->nullable();
            $table->string('unit')->default('kg');
            $table->unsignedInteger('reps')->nullable();
            $table->boolean('is_warmup')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['session_item_id', 'set_number', 'part']);
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_sets');
    }
};
