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
        Schema::create('service_commissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_request_id')
                ->unique()
                ->constrained('service_requests')
                ->restrictOnDelete();

            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('provider_amount', 12, 2);

            $table->string('collection_status', 30)
                ->default('pending');

            $table->timestamp('calculated_at');

            $table->timestamps();

            $table->index('collection_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_commissions');
    }
};
