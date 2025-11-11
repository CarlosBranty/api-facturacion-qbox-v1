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
        Schema::create('company_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            
            // Información del token
            $table->string('name'); // Nombre descriptivo del token
            $table->string('token', 64)->unique(); // Token único
            $table->string('token_hash', 64)->unique(); // Hash del token para búsqueda
            
            // Permisos y restricciones
            $table->json('abilities')->nullable(); // Permisos específicos ['invoices.create', 'boletas.view', etc.]
            $table->json('allowed_ips')->nullable(); // IPs permitidas para este token
            $table->json('restrictions')->nullable(); // Restricciones adicionales
            
            // Estado y expiración
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip')->nullable();
            
            // Límites de uso
            $table->integer('max_requests_per_minute')->nullable(); // Rate limiting
            $table->integer('max_requests_per_day')->nullable();
            $table->integer('request_count_today')->default(0);
            $table->date('request_count_date')->nullable(); // Fecha del último conteo
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['company_id', 'is_active']);
            $table->index(['token_hash']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_api_tokens');
    }
};

