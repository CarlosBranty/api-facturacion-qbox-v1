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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Límite total de documentos/ventas (null = ilimitado)
            $table->integer('max_total_documents')->nullable()->after('max_documents_per_month');
            
            // Contador de documentos creados desde el inicio de la suscripción
            $table->integer('total_documents_created')->default(0)->after('max_total_documents');
            
            // Límite total de ventas en monto (null = ilimitado)
            $table->decimal('max_total_sales_amount', 15, 2)->nullable()->after('total_documents_created');
            
            // Contador de monto total de ventas
            $table->decimal('total_sales_amount', 15, 2)->default(0)->after('max_total_sales_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'max_total_documents',
                'total_documents_created',
                'max_total_sales_amount',
                'total_sales_amount',
            ]);
        });
    }
};

