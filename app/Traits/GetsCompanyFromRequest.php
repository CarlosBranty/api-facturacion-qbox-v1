<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Http\Request;

trait GetsCompanyFromRequest
{
    /**
     * Obtener la empresa del request
     * Funciona tanto con token de usuario como con token de empresa
     */
    protected function getCompanyFromRequest(Request $request): ?Company
    {
        // Si hay una empresa en los atributos (token de empresa)
        $company = $request->attributes->get('company');
        if ($company instanceof Company) {
            return $company;
        }

        // Si hay un usuario autenticado con empresa
        $user = $request->user();
        if ($user && isset($user->company_id)) {
            // Si es un objeto con company_id (token de empresa)
            if (isset($user->company_id) && is_numeric($user->company_id)) {
                return Company::find($user->company_id);
            }
            
            // Si es un modelo User con relación company
            if (method_exists($user, 'company')) {
                return $user->company;
            }
        }

        return null;
    }

    /**
     * Obtener el ID de la empresa del request
     */
    protected function getCompanyIdFromRequest(Request $request): ?int
    {
        $company = $this->getCompanyFromRequest($request);
        return $company ? $company->id : null;
    }

    /**
     * Verificar si el usuario/token tiene acceso a una empresa específica
     */
    protected function canAccessCompany(Request $request, int $companyId): bool
    {
        $user = $request->user();
        
        // Si es super admin (solo usuarios reales pueden ser super admin), puede acceder a todas las empresas
        if ($user && method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole('super_admin')) {
                    return true;
                }
            } catch (\Exception $e) {
                // Si hasRole falla, continuar con otras verificaciones
            }
        }

        // Verificar si la empresa del request coincide
        $requestCompanyId = $this->getCompanyIdFromRequest($request);
        return $requestCompanyId === $companyId;
    }

    /**
     * Obtener la empresa o lanzar excepción si no se puede determinar
     */
    protected function getCompanyOrFail(Request $request, ?int $companyId = null): Company
    {
        // Si se proporciona un company_id específico, validar acceso
        if ($companyId) {
            if (!$this->canAccessCompany($request, $companyId)) {
                abort(403, 'No tienes acceso a esta empresa');
            }
            return Company::findOrFail($companyId);
        }

        // Intentar obtener del request
        $company = $this->getCompanyFromRequest($request);
        if (!$company) {
            abort(403, 'No se pudo determinar la empresa. Proporciona company_id o usa un token de empresa.');
        }

        return $company;
    }

    /**
     * Filtrar query por empresa del request (si aplica)
     */
    protected function filterByRequestCompany($query, Request $request, string $column = 'company_id')
    {
        $companyId = $this->getCompanyIdFromRequest($request);
        
        // Si hay una empresa en el request y el usuario no es super admin, filtrar
        if ($companyId) {
            $user = $request->user();
            $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
            
            if (!$isSuperAdmin) {
                $query->where($column, $companyId);
            }
        }

        return $query;
    }
}

