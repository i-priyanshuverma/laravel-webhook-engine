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
        Schema::create('dead_letter_queue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained('webhook_events')->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('event_type');
            $table->json('payload');
            $table->string('exception_class');
            $table->text('exception_message');
            $table->mediumText('stack_trace');
            $table->timestamp('failed_at');
            $table->timestamp('replayed_at')->nullable();
            $table->string('replayed_by')->nullable();
            $table->string('status')->default('unresolved')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_letter_queue_events');
    }
};
