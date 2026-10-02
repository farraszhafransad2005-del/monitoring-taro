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
        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('production_date');
            $table->unsignedTinyInteger('shift');
            $table->string('shift_type', 20);
            $table->string('break_type', 20)->nullable();
            $table->string('crew_group', 20)->nullable();
            $table->string('line', 20)->nullable();
            $table->string('part_no', 20);
            $table->string('item');

            $table->unsignedSmallInteger('speed_ppm')->nullable();
            $table->unsignedSmallInteger('pcs_per_carton')->nullable();
            $table->decimal('kg_per_carton', 8, 4)->nullable();

            $table->decimal('planned_minutes', 8, 2);
            $table->decimal('unavailable_minutes', 8, 2)->default(0);
            $table->decimal('downtime_minutes', 8, 2)->default(0);

            $table->decimal('target_cartons', 10, 2)->nullable();
            $table->unsignedInteger('actual_cartons')->default(0);
            $table->decimal('actual_kg', 12, 3)->default(0);
            $table->decimal('cake_usage_kg', 12, 3)->nullable();
            $table->unsignedInteger('carton_reject_total')->default(0);

            $table->unsignedInteger('reject_setup')->default(0);
            $table->unsignedInteger('reject_roll_change')->default(0);
            $table->unsignedInteger('reject_post_repair_check')->default(0);
            $table->unsignedInteger('reject_empty_pack')->default(0);
            $table->unsignedInteger('reject_bad_coding')->default(0);
            $table->unsignedInteger('reject_leaking_pack')->default(0);
            $table->unsignedInteger('reject_trapped_product')->default(0);
            $table->unsignedInteger('reject_overlap')->default(0);
            $table->unsignedInteger('reject_emark')->default(0);
            $table->unsignedInteger('reject_total_pcs')->default(0);

            $table->timestamps();

            $table->unique(['production_date', 'shift', 'machine_id', 'part_no', 'shift_type'], 'production_records_natural_key');
            $table->index(['production_date', 'shift']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};
