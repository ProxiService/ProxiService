<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_review_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_review_id')
                ->constrained('service_reviews')
                ->cascadeOnDelete();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('reason', 50);
            $table->text('details')->nullable();

            $table->string('status', 20)->default('pending');

            $table->timestamps();

            $table->unique([
                'service_review_id',
                'reporter_id',
            ]);

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_review_reports');
    }
};
