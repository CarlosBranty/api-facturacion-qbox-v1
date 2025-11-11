<?php

namespace App\Http\Middleware;

use App\Models\CompanyApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompanyToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return response()->json([
                'message' => 'Token de autenticación no proporcionado',
                'status' => 'error'
            ], 401);
        }

        $apiToken = CompanyApiToken::findByToken($token);

        if (!$apiToken) {
            return response()->json([
                'message' => 'Token inválido',
                'status' => 'error'
            ], 401);
        }

        if (!$apiToken->isValid()) {
            return response()->json([
                'message' => 'Token inactivo o expirado',
                'status' => 'error'
            ], 401);
        }

        // Verificar IP permitida
        $clientIp = $request->ip();
        if (!$apiToken->isIpAllowed($clientIp)) {
            return response()->json([
                'message' => 'IP no autorizada',
                'status' => 'error'
            ], 403);
        }

        // Verificar límites de rate limiting
        if (!$apiToken->checkRateLimit()) {
            return response()->json([
                'message' => 'Límite de solicitudes excedido',
                'status' => 'error'
            ], 429);
        }

        // Verificar que la empresa esté activa y pueda usar el API
        $company = $apiToken->company;
        if (!$company || !$company->canUseApi()) {
            return response()->json([
                'message' => 'Empresa inactiva o sin suscripción válida',
                'status' => 'error'
            ], 403);
        }

        // Registrar uso del token
        $apiToken->recordUsage($clientIp);

        // Agregar información al request para uso en controladores
        // Usar setAttribute en lugar de merge para evitar conflictos
        $request->attributes->set('company', $company);
        $request->attributes->set('company_token', $apiToken);
        
        // También agregar como propiedades dinámicas para acceso directo
        $request->setUserResolver(function () use ($company) {
            // Crear un objeto simple para compatibilidad
            return (object) [
                'id' => $company->id,
                'company_id' => $company->id,
                'company' => $company,
            ];
        });

        return $next($request);
    }

    /**
     * Obtener token del request
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Intentar obtener del header Authorization
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Intentar obtener del header X-API-Key
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // Intentar obtener del query parameter (no recomendado para producción)
        return $request->query('api_token');
    }
}

