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
        Schema::create('oee_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->decimal('availability', 5, 2);
            $table->decimal('performance', 5, 2);
            $table->decimal('quality', 5, 2);
            $table->decimal('oee', 5, 2);
            $table->decimal('speed_ppm', 8, 2)->nullable();
            $table->unsignedInteger('total_count')->nullable();
            $table->unsignedInteger('good_count')->nullable();
            $table->unsignedInteger('reject_count')->nullable();
            $table->string('source', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oee_readings');
    }
};
