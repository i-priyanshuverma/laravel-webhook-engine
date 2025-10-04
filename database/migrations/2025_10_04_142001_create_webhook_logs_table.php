<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->nullable()->constrained('webhook_events')->nullOnDelete();
            $table->string('provider')->index();
            $table->string('event_id')->nullable()->index();
            $table->string('http_method', 10)->default('POST');
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->integer('response_code')->default(200);
            $table->float('execution_time_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
