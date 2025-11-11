<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_name',
        'plan_type',
        'price',
        'currency',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'max_documents_per_month',
        'max_total_documents',
        'total_documents_created',
        'max_total_sales_amount',
        'total_sales_amount',
        'max_users',
        'max_branches',
        'features',
        'payment_method',
        'payment_reference',
        'last_payment_at',
        'next_payment_at',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'max_total_sales_amount' => 'decimal:2',
        'total_sales_amount' => 'decimal:2',
        'features' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Verificar si la suscripción está activa
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Verificar si no ha expirado
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Verificar si está en período de prueba
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Verificar si está expirada
     */
    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Verificar si está cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelled_at !== null;
    }

    /**
     * Activar suscripción
     */
    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'starts_at' => $this->starts_at ?? now(),
        ]);
    }

    /**
     * Cancelar suscripción
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Suspender suscripción
     */
    public function suspend(): void
    {
        $this->update([
            'status' => 'suspended',
        ]);
    }

    /**
     * Renovar suscripción
     */
    public function renew(int $months = 1): void
    {
        $newEndDate = $this->ends_at 
            ? $this->ends_at->copy()->addMonths($months)
            : now()->addMonths($months);

        $this->update([
            'status' => 'active',
            'ends_at' => $newEndDate,
            'next_payment_at' => $newEndDate,
            'last_payment_at' => now(),
        ]);
    }

    /**
     * Verificar si puede crear más documentos este mes
     */
    public function canCreateDocument(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // Verificar límite mensual
        if ($this->max_documents_per_month !== null) {
            // Contar documentos del mes actual
            $currentMonthDocuments = $this->company->invoices()
                ->whereYear('fecha_emision', now()->year)
                ->whereMonth('fecha_emision', now()->month)
                ->count() + 
                $this->company->boletas()
                ->whereYear('fecha_emision', now()->year)
                ->whereMonth('fecha_emision', now()->month)
                ->count();

            if ($currentMonthDocuments >= $this->max_documents_per_month) {
                return false;
            }
        }

        // Verificar límite total de documentos
        if ($this->max_total_documents !== null) {
            if ($this->total_documents_created >= $this->max_total_documents) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verificar si puede crear un documento con un monto específico
     */
    public function canCreateDocumentWithAmount(float $amount): bool
    {
        if (!$this->canCreateDocument()) {
            return false;
        }

        // Verificar límite total de ventas
        if ($this->max_total_sales_amount !== null) {
            $newTotal = $this->total_sales_amount + $amount;
            if ($newTotal > $this->max_total_sales_amount) {
                return false;
            }
        }

        return true;
    }

    /**
     * Incrementar contador de documentos creados
     */
    public function incrementDocumentsCount(int $count = 1): void
    {
        $this->increment('total_documents_created', $count);
    }

    /**
     * Incrementar monto total de ventas
     */
    public function incrementSalesAmount(float $amount): void
    {
        $this->increment('total_sales_amount', $amount);
    }

    /**
     * Obtener documentos restantes (límite total)
     */
    public function getRemainingDocuments(): ?int
    {
        if ($this->max_total_documents === null) {
            return null; // Ilimitado
        }

        return max(0, $this->max_total_documents - $this->total_documents_created);
    }

    /**
     * Obtener monto restante de ventas
     */
    public function getRemainingSalesAmount(): ?float
    {
        if ($this->max_total_sales_amount === null) {
            return null; // Ilimitado
        }

        return max(0, $this->max_total_sales_amount - $this->total_sales_amount);
    }

    /**
     * Resetear contadores (útil al renovar suscripción)
     */
    public function resetCounters(): void
    {
        $this->update([
            'total_documents_created' => 0,
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * Obtener días restantes de suscripción
     */
    public function getDaysRemaining(): ?int
    {
        if (!$this->ends_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->ends_at, false));
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<=', now())
            ->where('status', '!=', 'cancelled');
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByPlan($query, string $planName)
    {
        return $query->where('plan_name', $planName);
    }
}

