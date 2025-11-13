<?php

namespace App\Models;

use App\Traits\HasCompanyConfigurations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CompanyApiToken;
use App\Models\Subscription;

class Company extends Model
{
    use HasFactory, HasCompanyConfigurations;

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'ubigeo',
        'distrito',
        'provincia',
        'departamento',
        'telefono',
        'email',
        'web',
        'usuario_sol',
        'clave_sol',
        'certificado_pem',
        'certificado_password',
        'gre_client_id_beta',
        'gre_client_secret_beta',
        'gre_client_id_produccion',
        'gre_client_secret_produccion',
        'gre_ruc_proveedor',
        'gre_usuario_sol',
        'gre_clave_sol',
        'endpoint_beta',
        'endpoint_produccion',
        'modo_produccion',
        'logo_path',
        'activo',
    ];

    protected $casts = [
        'modo_produccion' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $hidden = [
        'clave_sol',
        'certificado_pem',
        'certificado_password',
        'gre_client_secret_beta',
        'gre_client_secret_produccion',
        'gre_clave_sol',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(CompanyConfiguration::class);
    }

    public function activeConfigurations(): HasMany
    {
        return $this->hasMany(CompanyConfiguration::class)->where('is_active', true);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(Boleta::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function dispatchGuides(): HasMany
    {
        return $this->hasMany(DispatchGuide::class);
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }

    public function voidedDocuments(): HasMany
    {
        return $this->hasMany(VoidedDocument::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(CompanyApiToken::class);
    }

    public function activeApiTokens(): HasMany
    {
        return $this->hasMany(CompanyApiToken::class)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Verificar si la empresa tiene una suscripción activa
     */
    public function hasActiveSubscription(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription !== null && $subscription->isActive();
    }

    /**
     * Verificar si la empresa puede usar el API
     */
    public function canUseApi(): bool
    {
        if (!$this->activo) {
            return false;
        }

        // Si no hay sistema de suscripciones habilitado, permitir acceso
        // Puedes cambiar esto según tu lógica de negocio
        if (!config('app.require_subscription', false)) {
            return true;
        }

        return $this->hasActiveSubscription();
    }

    /**
     * Verificar si puede crear un documento (verifica límites de suscripción)
     */
    public function canCreateDocument(?float $amount = null): bool
    {
        if (!$this->canUseApi()) {
            return false;
        }

        $subscription = $this->activeSubscription();
        
        // Si no requiere suscripción, permitir
        if (!config('app.require_subscription', false) || !$subscription) {
            return true;
        }

        // Verificar límites de la suscripción
        if ($amount !== null) {
            return $subscription->canCreateDocumentWithAmount($amount);
        }

        return $subscription->canCreateDocument();
    }

    /**
     * Registrar creación de documento en la suscripción
     */
    public function recordDocumentCreation(float $amount = 0): void
    {
        $subscription = $this->activeSubscription();
        if ($subscription) {
            $subscription->incrementDocumentsCount();
            if ($amount > 0) {
                $subscription->incrementSalesAmount($amount);
            }
        }
    }

    /**
     * Crear token de API por defecto al crear empresa
     */
    public function createDefaultApiToken(): void
    {
        try {
            CompanyApiToken::createForCompany(
                $this->id,
                'Token Principal - ' . $this->razon_social,
                ['*'], // Todos los permisos por defecto
                null // Sin expiración por defecto
            );
        } catch (\Exception $e) {
            // Log error pero no fallar la creación de la empresa
            \Log::error('Error al crear token por defecto para empresa: ' . $e->getMessage(), [
                'company_id' => $this->id
            ]);
        }
    }

    /**
     * Crear suscripción por defecto al crear empresa
     */
    public function createDefaultSubscription(): void
    {
        try {
            // Verificar si ya existe una suscripción activa
            if ($this->activeSubscription()) {
                \Log::info('La empresa ya tiene una suscripción activa, omitiendo creación de suscripción por defecto', [
                    'company_id' => $this->id
                ]);
                return;
            }

            // Crear suscripción básica por defecto
            Subscription::create([
                'company_id' => $this->id,
                'plan_name' => 'Plan Inicial',
                'plan_type' => 'lifetime', // Sin expiración por defecto, puede cambiarse después
                'price' => 0.00,
                'currency' => 'PEN',
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null, // Sin expiración
                'trial_ends_at' => null,
                'max_documents_per_month' => null, // Ilimitado por defecto
                'max_total_documents' => null, // Ilimitado por defecto
                'total_documents_created' => 0,
                'max_total_sales_amount' => null, // Ilimitado por defecto
                'total_sales_amount' => 0.00,
                'max_users' => null, // Ilimitado por defecto
                'max_branches' => null, // Ilimitado por defecto
                'features' => [
                    'api_access' => true,
                    'pdf_generation' => true,
                    'xml_generation' => true,
                    'sunat_integration' => true,
                ],
                'payment_method' => null,
                'payment_reference' => null,
                'metadata' => [
                    'auto_created' => true,
                    'created_at' => now()->toIso8601String(),
                ],
                'notes' => 'Suscripción creada automáticamente al registrar la empresa',
            ]);
        } catch (\Exception $e) {
            // Log error pero no fallar la creación de la empresa
            \Log::error('Error al crear suscripción por defecto para empresa: ' . $e->getMessage(), [
                'company_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getEndpointAttribute(): string
    {
        return $this->modo_produccion ? $this->endpoint_produccion : $this->endpoint_beta;
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Bootstrap del modelo
     */
    protected static function boot()
    {
        parent::boot();
        
        // Al crear una nueva empresa, configurar endpoints por defecto
        static::creating(function ($company) {
            // Asignar endpoints por defecto si no están definidos
            if (empty($company->endpoint_beta)) {
                $company->endpoint_beta = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
            }
            
            if (empty($company->endpoint_produccion)) {
                $company->endpoint_produccion = 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService';
            }
            
            // Asignar valores por defecto
            $company->activo = $company->activo ?? true;
            $company->modo_produccion = $company->modo_produccion ?? false;
        });
        
        // Después de crear una empresa, inicializar configuraciones por defecto
        // TEMPORALMENTE DESHABILITADO - Solo crear empresa sin configuraciones adicionales
        // static::created(function ($company) {
        //     $company->initializeDefaultConfigurations();
        // });
        
        // Crear token de API y suscripción automáticamente al crear una empresa
        static::created(function ($company) {
            $company->createDefaultApiToken();
            $company->createDefaultSubscription();
        });
    }

    /**
     * Inicializar configuraciones por defecto para una empresa nueva
     * TEMPORALMENTE DESHABILITADO - Solo crear empresa básica
     */
    public function initializeDefaultConfigurations(): void
    {
        // DESHABILITADO TEMPORALMENTE
        return;
        
        // Configuraciones de impuestos
        $this->setConfig('tax_settings', [
            'igv_porcentaje' => 18.00,
            'isc_porcentaje' => 0.00,
            'icbper_monto' => 0.50,
            'ivap_porcentaje' => 4.00,
            'redondeo_automatico' => true,
            'decimales_precio_unitario' => 10,
            'decimales_cantidad' => 10
        ], 'general', 'general');

        // Configuraciones de documentos
        $this->setConfig('document_settings', [
            'generar_xml_automatico' => true,
            'generar_pdf_automatico' => true,
            'enviar_sunat_automatico' => false,
            'formato_pdf_default' => 'a4',
            'orientacion_pdf_default' => 'portrait',
            'incluir_qr_pdf' => true,
            'incluir_hash_pdf' => true,
            'logo_en_pdf' => true
        ], 'general', 'general');

        // Endpoints de servicios para beta
        $this->setConfig('service_endpoints', [
            'endpoint' => $this->endpoint_beta,
            'wsdl' => str_replace('billService', 'billService?wsdl', $this->endpoint_beta),
            'timeout' => 30
        ], 'beta', 'facturacion');

        // Endpoints de servicios para producción
        $this->setConfig('service_endpoints', [
            'endpoint' => $this->endpoint_produccion,
            'wsdl' => str_replace('billService', 'billService?wsdl', $this->endpoint_produccion),
            'timeout' => 30
        ], 'produccion', 'facturacion');

        // Endpoints para guías de remisión (beta)
        $this->setConfig('service_endpoints', [
            'endpoint' => 'https://gre-test.nubefact.com/v1',
            'api_endpoint' => 'https://api-cpe-beta.sunat.gob.pe/v1/',
            'wsdl' => 'https://e-beta.sunat.gob.pe/ol-ti-itcpgre-beta/billService?wsdl',
            'timeout' => 30
        ], 'beta', 'guias_remision');
    }
}