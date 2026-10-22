<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('business_name', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('phone', 30)->nullable();

            $table->unsignedSmallInteger('years_experience')
                ->nullable();

            $table->string('city', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->text('address')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_available')->default(false);
            $table->string('status', 20)->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};
