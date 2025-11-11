<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Http\JsonResponse;

trait ChecksSubscriptionLimits
{
    /**
     * Verificar límites de suscripción antes de crear un documento
     */
    protected function checkSubscriptionLimits(Company $company, ?float $amount = null): ?JsonResponse
    {
        // Si no requiere suscripción, permitir
        if (!config('app.require_subscription', false)) {
            return null;
        }

        // Verificar si la empresa puede crear documentos
        if (!$company->canCreateDocument($amount)) {
            $subscription = $company->activeSubscription();
            
            $message = 'Límite de suscripción alcanzado. ';
            
            if ($subscription) {
                $remainingDocs = $subscription->getRemainingDocuments();
                $remainingAmount = $subscription->getRemainingSalesAmount();
                
                if ($remainingDocs !== null) {
                    $message .= "Documentos restantes: {$remainingDocs}. ";
                }
                
                if ($remainingAmount !== null && $amount !== null) {
                    $message .= "Monto restante: " . number_format($remainingAmount, 2) . " {$subscription->currency}. ";
                }
                
                $message .= "Por favor, actualiza tu plan o contacta al administrador.";
            } else {
                $message .= "No tienes una suscripción activa.";
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'status' => 'error',
                'subscription_limits' => $subscription ? [
                    'max_total_documents' => $subscription->max_total_documents,
                    'total_documents_created' => $subscription->total_documents_created,
                    'remaining_documents' => $subscription->getRemainingDocuments(),
                    'max_total_sales_amount' => $subscription->max_total_sales_amount,
                    'total_sales_amount' => $subscription->total_sales_amount,
                    'remaining_sales_amount' => $subscription->getRemainingSalesAmount(),
                ] : null
            ], 403);
        }

        return null;
    }
}

