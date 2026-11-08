<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Numérotation des documents : une ligne par type, verrouillée lors de l'attribution.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->unique();
            $table->string('label', 80);
            $table->string('prefix', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->boolean('yearly_reset')->default(true);
            $table->unsignedSmallInteger('current_year')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            // cash = espèces (impacte le tiroir-caisse) ; electronic = Mobile Money, virement, carte...
            $table->enum('kind', ['cash', 'electronic']);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->decimal('rate', 7, 4); // en %, ex. 16.0000
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('settings');
    }
};
