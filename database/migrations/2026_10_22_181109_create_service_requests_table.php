<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('provider_profile_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('service_id')
                ->constrained()
                ->restrictOnDelete();

            $table->text('description');

            $table->string('city', 100);
            $table->string('neighborhood', 100);
            $table->string('address', 255)->nullable();

            $table->dateTime('scheduled_at')->nullable();
            $table->boolean('is_urgent')->default(false);

            $table->string('status', 30)->default('pending');

            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['provider_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
