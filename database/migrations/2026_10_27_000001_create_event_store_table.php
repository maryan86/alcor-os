<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_store', static function (Blueprint $table): void {
            $table->id()->comment('Global position, defines the order for projection replays');
            $table->string('stream_id', 100);
            $table->unsignedInteger('version')->comment('Position of the event inside its stream, starting at 1');
            $table->string('event_type', 150);
            $table->jsonb('payload');
            $table->dateTime('occurred_at', precision: 6);
            $table->dateTime('recorded_at', precision: 6);

            // Optimistic concurrency guard: two writers can never append the same version of a stream.
            $table->unique(['stream_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_store');
    }
};
