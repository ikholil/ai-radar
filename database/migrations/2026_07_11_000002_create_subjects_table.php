<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('none');
            $table->string('external_url', 500)->nullable();
            $table->unsignedBigInteger('metric_value')->nullable();
            $table->json('metric_history')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamps();

            // Fetchers match incoming API results to existing subjects by URL.
            $table->index('external_url');
            $table->index('kind');
            $table->index('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
