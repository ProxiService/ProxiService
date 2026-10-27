<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_request_id')
                ->constrained('service_requests')
                ->restrictOnDelete();

            $table->foreignId('client_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('provider', 30)->default('geniuspay');

            $table->string('provider_reference', 100)
                ->nullable()
                ->unique();

            $table->string('provider_transaction_id', 100)->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');

            $table->decimal('fees', 12, 2)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();

            $table->string('payment_method', 40)->nullable();

            $table->string('status', 20)->default('pending');

            $table->text('checkout_url')->nullable();

            $table->json('metadata')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->string('environment', 20)->default('sandbox');

            $table->timestamps();

            $table->index([
                'service_request_id',
                'status',
            ]);

            $table->index([
                'client_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_payments');
    }
};
