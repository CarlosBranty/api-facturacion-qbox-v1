<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\GetsCompanyFromRequest;
use App\Models\Company;
use App\Models\CompanyApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CompanyTokenController extends Controller
{
    use GetsCompanyFromRequest;

    /**
     * Listar todos los tokens de una empresa
     */
    public function index(Request $request, $companyId)
    {
        // Verificar permisos (solo super admin o admin de la empresa)
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para ver tokens de esta empresa',
                'status' => 'error'
            ], 403);
        }

        $company = Company::findOrFail($companyId);
        $tokens = $company->apiTokens()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'tokens' => $tokens->map(function($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'is_active' => $token->is_active,
                    'expires_at' => $token->expires_at,
                    'last_used_at' => $token->last_used_at,
                    'last_used_ip' => $token->last_used_ip,
                    'abilities' => $token->abilities,
                    'created_at' => $token->created_at,
                ];
            })
        ]);
    }

    /**
     * Crear un nuevo token para una empresa
     */
    public function store(Request $request, $companyId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para crear tokens para esta empresa',
                'status' => 'error'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'max_requests_per_day' => 'nullable|integer|min:1',
            'max_requests_per_minute' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 'error'
            ], 422);
        }

        $company = Company::findOrFail($companyId);

        // Verificar que la empresa pueda usar el API
        if (!$company->canUseApi()) {
            return response()->json([
                'message' => 'La empresa no tiene una suscripción activa',
                'status' => 'error'
            ], 403);
        }

        $abilities = $request->input('abilities', ['*']);
        $expiresAt = $request->input('expires_at') 
            ? Carbon::parse($request->input('expires_at')) 
            : null;

        $token = CompanyApiToken::createForCompany(
            $companyId,
            $request->input('name'),
            $abilities,
            $expiresAt
        );

        // Configurar opciones adicionales
        if ($request->has('allowed_ips')) {
            $token->allowed_ips = $request->input('allowed_ips');
        }

        if ($request->has('max_requests_per_day')) {
            $token->max_requests_per_day = $request->input('max_requests_per_day');
        }

        if ($request->has('max_requests_per_minute')) {
            $token->max_requests_per_minute = $request->input('max_requests_per_minute');
        }

        $token->save();

        // Retornar el token completo solo en la creación
        return response()->json([
            'message' => 'Token creado exitosamente',
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'token' => $token->token, // Solo se muestra una vez
                'abilities' => $token->abilities,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ],
            'warning' => 'Guarda este token de forma segura. No se mostrará nuevamente.'
        ], 201);
    }

    /**
     * Mostrar un token específico
     */
    public function show(Request $request, $companyId, $tokenId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para ver este token',
                'status' => 'error'
            ], 403);
        }

        $token = CompanyApiToken::where('company_id', $companyId)
            ->findOrFail($tokenId);

        return response()->json([
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'is_active' => $token->is_active,
                'expires_at' => $token->expires_at,
                'last_used_at' => $token->last_used_at,
                'last_used_ip' => $token->last_used_ip,
                'abilities' => $token->abilities,
                'allowed_ips' => $token->allowed_ips,
                'max_requests_per_day' => $token->max_requests_per_day,
                'max_requests_per_minute' => $token->max_requests_per_minute,
                'request_count_today' => $token->request_count_today,
                'created_at' => $token->created_at,
            ]
        ]);
    }

    /**
     * Actualizar un token
     */
    public function update(Request $request, $companyId, $tokenId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para actualizar este token',
                'status' => 'error'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'max_requests_per_day' => 'nullable|integer|min:1',
            'max_requests_per_minute' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 'error'
            ], 422);
        }

        $token = CompanyApiToken::where('company_id', $companyId)
            ->findOrFail($tokenId);

        $token->fill($request->only([
            'name',
            'abilities',
            'allowed_ips',
            'max_requests_per_day',
            'max_requests_per_minute',
            'is_active',
        ]));

        if ($request->has('expires_at')) {
            $token->expires_at = $request->input('expires_at') 
                ? Carbon::parse($request->input('expires_at')) 
                : null;
        }

        $token->save();

        return response()->json([
            'message' => 'Token actualizado exitosamente',
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'is_active' => $token->is_active,
                'expires_at' => $token->expires_at,
                'abilities' => $token->abilities,
            ]
        ]);
    }

    /**
     * Revocar un token
     */
    public function destroy(Request $request, $companyId, $tokenId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para revocar este token',
                'status' => 'error'
            ], 403);
        }

        $token = CompanyApiToken::where('company_id', $companyId)
            ->findOrFail($tokenId);

        $token->revoke();

        return response()->json([
            'message' => 'Token revocado exitosamente'
        ]);
    }

    /**
     * Regenerar un token (crear nuevo y revocar el anterior)
     */
    public function regenerate(Request $request, $companyId, $tokenId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para regenerar este token',
                'status' => 'error'
            ], 403);
        }

        $oldToken = CompanyApiToken::where('company_id', $companyId)
            ->findOrFail($tokenId);

        // Crear nuevo token con las mismas características
        $newToken = CompanyApiToken::createForCompany(
            $companyId,
            $oldToken->name . ' (regenerado)',
            $oldToken->abilities,
            $oldToken->expires_at
        );

        $newToken->allowed_ips = $oldToken->allowed_ips;
        $newToken->max_requests_per_day = $oldToken->max_requests_per_day;
        $newToken->max_requests_per_minute = $oldToken->max_requests_per_minute;
        $newToken->save();

        // Revocar el token anterior
        $oldToken->revoke();

        return response()->json([
            'message' => 'Token regenerado exitosamente',
            'token' => [
                'id' => $newToken->id,
                'name' => $newToken->name,
                'token' => $newToken->token,
                'abilities' => $newToken->abilities,
                'expires_at' => $newToken->expires_at,
            ],
            'warning' => 'El token anterior ha sido revocado. Guarda el nuevo token de forma segura.'
        ]);
    }
}

