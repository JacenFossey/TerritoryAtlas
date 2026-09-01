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
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('area_type')->index();
            $table->string('administrative_status')->nullable();
            $table->string('source_identifier')->nullable()->index();
            $table->string('source_name')->nullable();
            $table->string('geometry_key')->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_ggh')->default(false);
            $table->boolean('is_gta')->default(false);
            $table->string('boundary_precision')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
