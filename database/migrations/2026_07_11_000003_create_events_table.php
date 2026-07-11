<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 512);
            $table->string('url', 500);
            $table->text('summary')->nullable();
            $table->string('source', 100);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Dedupe: re-polling a source must not create duplicate events.
            $table->unique(['source', 'url']);
            $table->index('type');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
