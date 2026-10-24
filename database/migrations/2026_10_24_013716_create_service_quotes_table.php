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
        Schema::create('service_quotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('provider_profile_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->string('status', 20)->default('pending');
            $table->dateTime('responded_at')->nullable();

            $table->timestamps();

            $table->index(['service_request_id', 'status']);
            $table->index(['provider_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_quotes');
    }
};
