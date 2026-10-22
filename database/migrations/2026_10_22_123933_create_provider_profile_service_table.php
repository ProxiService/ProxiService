<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profile_service', function (Blueprint $table) {
            $table->foreignId('provider_profile_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained()
                ->cascadeOnDelete();

            // Prix spécifique proposé par le prestataire.
            // Null signifie qu'aucun prix personnalisé n'est renseigné.
            $table->decimal('price', 12, 2)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'provider_profile_id',
                'service_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profile_service');
    }
};
