<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();

            $table->string('pricing_type', 20)->default('quote');
            $table->decimal('base_price', 12, 2)->nullable();

            $table->unsignedInteger('estimated_duration_minutes')
                ->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
