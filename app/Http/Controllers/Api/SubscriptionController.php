<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\GetsCompanyFromRequest;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    use GetsCompanyFromRequest;
    /**
     * Listar todas las suscripciones de una empresa
     */
    public function index(Request $request, $companyId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para ver suscripciones de esta empresa',
                'status' => 'error'
            ], 403);
        }

        $company = Company::findOrFail($companyId);
        $subscriptions = $company->subscriptions()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'subscriptions' => $subscriptions->map(function($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->plan_name,
                    'plan_type' => $subscription->plan_type,
                    'price' => $subscription->price,
                    'currency' => $subscription->currency,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'is_active' => $subscription->isActive(),
                    'days_remaining' => $subscription->getDaysRemaining(),
                    'max_documents_per_month' => $subscription->max_documents_per_month,
                    'max_users' => $subscription->max_users,
                    'max_branches' => $subscription->max_branches,
                    'features' => $subscription->features,
                    'created_at' => $subscription->created_at,
                ];
            })
        ]);
    }

    /**
     * Crear una nueva suscripción
     */
    public function store(Request $request, $companyId)
    {
        // Solo super admin puede crear suscripciones
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo los super administradores pueden crear suscripciones',
                'status' => 'error'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan_name' => 'required|string|max:255',
            'plan_type' => 'required|in:monthly,yearly,lifetime',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'trial_ends_at' => 'nullable|date|after:starts_at',
            'max_documents_per_month' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'metadata' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 'error'
            ], 422);
        }

        $company = Company::findOrFail($companyId);

        // Cancelar suscripciones activas anteriores si se solicita
        if ($request->input('cancel_previous', false)) {
            $company->subscriptions()
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
        }

        $subscription = Subscription::create([
            'company_id' => $companyId,
            'plan_name' => $request->input('plan_name'),
            'plan_type' => $request->input('plan_type'),
            'price' => $request->input('price'),
            'currency' => $request->input('currency', 'PEN'),
            'status' => $request->input('status', 'active'),
            'starts_at' => $request->input('starts_at') 
                ? Carbon::parse($request->input('starts_at')) 
                : now(),
            'ends_at' => $request->input('ends_at') 
                ? Carbon::parse($request->input('ends_at')) 
                : null,
            'trial_ends_at' => $request->input('trial_ends_at') 
                ? Carbon::parse($request->input('trial_ends_at')) 
                : null,
            'max_documents_per_month' => $request->input('max_documents_per_month'),
            'max_users' => $request->input('max_users', 1),
            'max_branches' => $request->input('max_branches', 1),
            'features' => $request->input('features'),
            'payment_method' => $request->input('payment_method'),
            'payment_reference' => $request->input('payment_reference'),
            'metadata' => $request->input('metadata'),
            'notes' => $request->input('notes'),
        ]);

        // Calcular fecha de próximo pago si es necesario
        if ($subscription->plan_type !== 'lifetime' && $subscription->ends_at) {
            $subscription->next_payment_at = $subscription->ends_at;
            $subscription->save();
        }

        return response()->json([
            'message' => 'Suscripción creada exitosamente',
            'subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
                'ends_at' => $subscription->ends_at,
                'days_remaining' => $subscription->getDaysRemaining(),
            ]
        ], 201);
    }

    /**
     * Mostrar una suscripción específica
     */
    public function show(Request $request, $companyId, $subscriptionId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta suscripción',
                'status' => 'error'
            ], 403);
        }

        $subscription = Subscription::where('company_id', $companyId)
            ->findOrFail($subscriptionId);

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'plan_type' => $subscription->plan_type,
                'price' => $subscription->price,
                'currency' => $subscription->currency,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'is_active' => $subscription->isActive(),
                'is_on_trial' => $subscription->isOnTrial(),
                'is_expired' => $subscription->isExpired(),
                'is_cancelled' => $subscription->isCancelled(),
                'days_remaining' => $subscription->getDaysRemaining(),
                'max_documents_per_month' => $subscription->max_documents_per_month,
                'max_users' => $subscription->max_users,
                'max_branches' => $subscription->max_branches,
                'features' => $subscription->features,
                'payment_method' => $subscription->payment_method,
                'last_payment_at' => $subscription->last_payment_at,
                'next_payment_at' => $subscription->next_payment_at,
                'created_at' => $subscription->created_at,
            ]
        ]);
    }

    /**
     * Actualizar una suscripción
     */
    public function update(Request $request, $companyId, $subscriptionId)
    {
        // Solo super admin puede actualizar suscripciones
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo los super administradores pueden actualizar suscripciones',
                'status' => 'error'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan_name' => 'sometimes|required|string|max:255',
            'plan_type' => 'sometimes|required|in:monthly,yearly,lifetime',
            'price' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:active,inactive,expired,cancelled,suspended',
            'ends_at' => 'nullable|date',
            'max_documents_per_month' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 'error'
            ], 422);
        }

        $subscription = Subscription::where('company_id', $companyId)
            ->findOrFail($subscriptionId);

        $subscription->fill($request->only([
            'plan_name',
            'plan_type',
            'price',
            'status',
            'max_documents_per_month',
            'max_total_documents',
            'max_total_sales_amount',
            'max_users',
            'max_branches',
            'features',
        ]));

        if ($request->has('ends_at')) {
            $subscription->ends_at = $request->input('ends_at') 
                ? Carbon::parse($request->input('ends_at')) 
                : null;
        }

        $subscription->save();

        return response()->json([
            'message' => 'Suscripción actualizada exitosamente',
            'subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
            ]
        ]);
    }

    /**
     * Activar una suscripción
     */
    public function activate(Request $request, $companyId, $subscriptionId)
    {
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo los super administradores pueden activar suscripciones',
                'status' => 'error'
            ], 403);
        }

        $subscription = Subscription::where('company_id', $companyId)
            ->findOrFail($subscriptionId);

        $subscription->activate();

        return response()->json([
            'message' => 'Suscripción activada exitosamente',
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
            ]
        ]);
    }

    /**
     * Cancelar una suscripción
     */
    public function cancel(Request $request, $companyId, $subscriptionId)
    {
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo los super administradores pueden cancelar suscripciones',
                'status' => 'error'
            ], 403);
        }

        $subscription = Subscription::where('company_id', $companyId)
            ->findOrFail($subscriptionId);

        $subscription->cancel();

        return response()->json([
            'message' => 'Suscripción cancelada exitosamente',
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
            ]
        ]);
    }

    /**
     * Renovar una suscripción
     */
    public function renew(Request $request, $companyId, $subscriptionId)
    {
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo los super administradores pueden renovar suscripciones',
                'status' => 'error'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'months' => 'required|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
                'status' => 'error'
            ], 422);
        }

        $subscription = Subscription::where('company_id', $companyId)
            ->findOrFail($subscriptionId);

        $subscription->renew($request->input('months'));

        return response()->json([
            'message' => 'Suscripción renovada exitosamente',
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at,
                'days_remaining' => $subscription->getDaysRemaining(),
            ]
        ]);
    }

    /**
     * Obtener la suscripción activa de una empresa
     */
    public function active(Request $request, $companyId)
    {
        // Verificar permisos
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        
        if (!$isSuperAdmin && !$this->canAccessCompany($request, $companyId)) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta información',
                'status' => 'error'
            ], 403);
        }

        $company = Company::findOrFail($companyId);
        $subscription = $company->activeSubscription();

        if (!$subscription) {
            return response()->json([
                'message' => 'No hay suscripción activa',
                'has_subscription' => false
            ], 404);
        }

        return response()->json([
            'has_subscription' => true,
            'subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'status' => $subscription->status,
                'is_active' => $subscription->isActive(),
                'ends_at' => $subscription->ends_at,
                'days_remaining' => $subscription->getDaysRemaining(),
                'max_documents_per_month' => $subscription->max_documents_per_month,
                'max_users' => $subscription->max_users,
                'max_branches' => $subscription->max_branches,
            ]
        ]);
    }
}

