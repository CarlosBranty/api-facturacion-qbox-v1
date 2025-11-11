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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            
            // Información del plan
            $table->string('plan_name'); // basic, premium, enterprise, etc.
            $table->string('plan_type')->default('monthly'); // monthly, yearly, lifetime
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('PEN');
            
            // Estado de la suscripción
            $table->enum('status', ['active', 'inactive', 'expired', 'cancelled', 'suspended'])->default('inactive');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Límites y características del plan
            $table->integer('max_documents_per_month')->nullable(); // null = ilimitado
            $table->integer('max_users')->default(1);
            $table->integer('max_branches')->default(1);
            $table->json('features')->nullable(); // Características adicionales del plan
            
            // Información de pago
            $table->string('payment_method')->nullable(); // stripe, paypal, manual, etc.
            $table->string('payment_reference')->nullable(); // ID de transacción externa
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['company_id', 'status']);
            $table->index(['status', 'ends_at']);
            $table->index('plan_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

