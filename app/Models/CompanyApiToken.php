<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CompanyApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'token',
        'token_hash',
        'abilities',
        'allowed_ips',
        'restrictions',
        'is_active',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'max_requests_per_minute',
        'max_requests_per_day',
        'request_count_today',
        'request_count_date',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'abilities' => 'array',
        'allowed_ips' => 'array',
        'restrictions' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'metadata' => 'array',
        'request_count_date' => 'date',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Generar un nuevo token
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Crear un nuevo token para una empresa
     */
    public static function createForCompany(
        int $companyId,
        string $name,
        ?array $abilities = null,
        ?\DateTime $expiresAt = null
    ): self {
        $token = self::generateToken();
        $tokenHash = hash('sha256', $token);

        return self::create([
            'company_id' => $companyId,
            'name' => $name,
            'token' => $token,
            'token_hash' => $tokenHash,
            'abilities' => $abilities ?? ['*'],
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    /**
     * Buscar token por hash
     */
    public static function findByToken(string $token): ?self
    {
        $tokenHash = hash('sha256', $token);
        return self::where('token_hash', $tokenHash)->first();
    }

    /**
     * Verificar si el token es válido
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Verificar si el token tiene un permiso específico
     */
    public function hasAbility(string $ability): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $abilities = $this->abilities ?? [];

        // Si tiene permiso de todo
        if (in_array('*', $abilities)) {
            return true;
        }

        return in_array($ability, $abilities);
    }

    /**
     * Verificar si la IP está permitida
     */
    public function isIpAllowed(string $ip): bool
    {
        $allowedIps = $this->allowed_ips ?? [];

        if (empty($allowedIps)) {
            return true; // Sin restricciones de IP
        }

        // Verificar IP exacta
        if (in_array($ip, $allowedIps)) {
            return true;
        }

        // Verificar rangos CIDR
        foreach ($allowedIps as $allowedIp) {
            if (str_contains($allowedIp, '/')) {
                if ($this->ipInRange($ip, $allowedIp)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verificar si una IP está en un rango CIDR
     */
    private function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }

    /**
     * Registrar uso del token
     */
    public function recordUsage(string $ip): void
    {
        $today = now()->toDateString();

        // Resetear contador si es un nuevo día
        if ($this->request_count_date !== $today) {
            $this->request_count_today = 0;
            $this->request_count_date = $today;
        }

        $this->increment('request_count_today');
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    /**
     * Verificar límites de rate limiting
     */
    public function checkRateLimit(): bool
    {
        // Verificar límite por minuto (requeriría implementación más compleja con cache)
        // Por ahora solo verificamos límite diario

        if ($this->max_requests_per_day === null) {
            return true; // Sin límite
        }

        $today = now()->toDateString();

        // Resetear contador si es un nuevo día
        if ($this->request_count_date !== $today) {
            $this->request_count_today = 0;
            $this->request_count_date = $today;
            $this->save();
            return true;
        }

        return $this->request_count_today < $this->max_requests_per_day;
    }

    /**
     * Revocar token
     */
    public function revoke(): void
    {
        $this->update([
            'is_active' => false,
        ]);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}

