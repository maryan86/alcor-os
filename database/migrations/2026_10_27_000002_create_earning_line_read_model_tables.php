<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Read side (CQRS): disposable projections, rebuildable from the event store. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('earning_lines', static function (Blueprint $table): void {
            $table->uuid('line_id')->primary();
            $table->char('currency', 3);
            $table->bigInteger('system_value_minor');
            $table->bigInteger('current_value_minor');
            $table->boolean('is_manually_adjusted');
            $table->dateTime('updated_at', precision: 6);
        });

        Schema::create('earning_line_audit_entries', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('line_id');
            $table->unsignedInteger('sequence');
            $table->string('type', 50);
            $table->bigInteger('amount_minor');
            $table->bigInteger('value_after_minor');
            $table->unsignedInteger('adjustment_number')->nullable();
            $table->unsignedInteger('corrects_adjustment_number')->nullable();
            $table->text('comment')->nullable();
            $table->string('adjusted_by')->nullable();
            $table->dateTime('occurred_at', precision: 6);

            $table->unique(['line_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_line_audit_entries');
        Schema::dropIfExists('earning_lines');
    }
};
