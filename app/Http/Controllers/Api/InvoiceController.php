<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Traits\GetsCompanyFromRequest;
use App\Services\DocumentService;
use App\Services\FileService;
use App\Models\Invoice;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\IndexInvoiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    use HandlesPdfGeneration, GetsCompanyFromRequest;
    protected $documentService;
    protected $fileService;

    public function __construct(DocumentService $documentService, FileService $fileService)
    {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
    }

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        try {
            $query = Invoice::with(['company', 'branch', 'client']);

            // Si se proporciona company_id, validar acceso
            if ($request->has('company_id')) {
                if (!$this->canAccessCompany($request, $request->company_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes acceso a esta empresa'
                    ], 403);
                }
                $query->where('company_id', $request->company_id);
            } else {
                // Si no se proporciona, filtrar por empresa del request (si aplica)
                $this->filterByRequestCompany($query, $request);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('estado_sunat')) {
                $query->where('estado_sunat', $request->estado_sunat);
            }

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereBetween('fecha_emision', [
                    $request->fecha_desde,
                    $request->fecha_hasta
                ]);
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $invoices = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'message' => 'Facturas obtenidas correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las facturas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Si no se proporciona company_id, usar la empresa del request
            if (!isset($validated['company_id'])) {
                $company = $this->getCompanyFromRequest($request);
                if (!$company) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo determinar la empresa. Proporciona company_id o usa un token de empresa.'
                    ], 400);
                }
                $validated['company_id'] = $company->id;
            } else {
                // Validar acceso a la empresa especificada
                if (!$this->canAccessCompany($request, $validated['company_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes acceso a esta empresa'
                    ], 403);
                }
            }

            // Crear la factura
            $invoice = $this->documentService->createInvoice($validated);

            return response()->json([
                'success' => true,
                'data' => $invoice->load(['company', 'branch', 'client']),
                'message' => 'Factura creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la factura',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $invoice = Invoice::with(['company', 'branch', 'client'])->findOrFail($id);

            // Validar acceso a la empresa de la factura
            if (!$this->canAccessCompany($request, $invoice->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a esta factura'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $invoice,
                'message' => 'Factura obtenida correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Factura no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function sendToSunat(Request $request, $id): JsonResponse
    {
        try {
            $invoice = Invoice::with(['company', 'branch', 'client'])->findOrFail($id);

            // Validar acceso
            if (!$this->canAccessCompany($request, $invoice->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a esta factura'
                ], 403);
            }

            if ($invoice->estado_sunat === 'ACEPTADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La factura ya fue enviada y aceptada por SUNAT'
                ], 400);
            }

            $result = $this->documentService->sendToSunat($invoice, 'invoice');

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['document'],
                    'message' => 'Factura enviada correctamente a SUNAT'
                ]);
            } else {
                // Manejar diferentes tipos de error
                $errorCode = 'UNKNOWN';
                $errorMessage = 'Error desconocido';
                
                if (is_object($result['error'])) {
                    if (method_exists($result['error'], 'getCode')) {
                        $errorCode = $result['error']->getCode();
                    } elseif (property_exists($result['error'], 'code')) {
                        $errorCode = $result['error']->code;
                    }
                    
                    if (method_exists($result['error'], 'getMessage')) {
                        $errorMessage = $result['error']->getMessage();
                    } elseif (property_exists($result['error'], 'message')) {
                        $errorMessage = $result['error']->message;
                    }
                }
                
                return response()->json([
                    'success' => false,
                    'data' => $result['document'],
                    'message' => 'Error al enviar a SUNAT: ' . $errorMessage,
                    'error_code' => $errorCode
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el envío a SUNAT',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadXml(Request $request, $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            
            // Validar acceso
            if (!$this->canAccessCompany($request, $invoice->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a esta factura'
                ], 403);
            }
            
            $download = $this->fileService->downloadXml($invoice);
            
            if (!$download) {
                return response()->json([
                    'success' => false,
                    'message' => 'XML no encontrado'
                ], 404);
            }
            
            return $download;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar XML',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadCdr(Request $request, $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            
            // Validar acceso
            if (!$this->canAccessCompany($request, $invoice->company_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a esta factura'
                ], 403);
            }
            
            $download = $this->fileService->downloadCdr($invoice);
            
            if (!$download) {
                return response()->json([
                    'success' => false,
                    'message' => 'CDR no encontrado'
                ], 404);
            }
            
            return $download;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar CDR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadPdf(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        
        // Validar acceso
        if (!$this->canAccessCompany($request, $invoice->company_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta factura'
            ], 403);
        }
        
        return $this->downloadDocumentPdf($invoice, $request);
    }

    public function generatePdf(Request $request, $id)
    {
        $invoice = Invoice::with(['company', 'branch', 'client'])->findOrFail($id);
        
        // Validar acceso
        if (!$this->canAccessCompany($request, $invoice->company_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta factura'
            ], 403);
        }
        
        return $this->generateDocumentPdf($invoice, 'invoice', $request);
    }

    protected function processInvoiceDetails(array $detalles, string $tipoOperacion = '0101'): array
    {
        // Para exportaciones (0200), no se debe calcular IGV
        $isExportacion = $tipoOperacion === '0200';

        foreach ($detalles as &$detalle) {
            $cantidad = $detalle['cantidad'];
            $valorUnitario = $detalle['mto_valor_unitario'];
            $porcentajeIgv = $isExportacion ? 0 : ($detalle['porcentaje_igv'] ?? 0);
            $tipAfeIgv = $isExportacion ? '40' : ($detalle['tip_afe_igv'] ?? '10'); // 40 = Exportación

            // Actualizar tipo de afectación para exportaciones
            $detalle['tip_afe_igv'] = $tipAfeIgv;
            $detalle['porcentaje_igv'] = $porcentajeIgv;

            // Calcular valor de venta
            $valorVenta = $cantidad * $valorUnitario;
            $detalle['mto_valor_venta'] = $valorVenta;

            // Para exportaciones - según ejemplo de Greenter
            if ($isExportacion) {
                $detalle['mto_base_igv'] = $valorVenta; // Base IGV = valor venta en exportaciones
                $detalle['igv'] = 0;
                $detalle['total_impuestos'] = 0;
                $detalle['mto_precio_unitario'] = $valorUnitario;
            } else {
                // Calcular base imponible IGV
                $baseIgv = in_array($tipAfeIgv, ['10', '17']) ? $valorVenta : 0;
                $detalle['mto_base_igv'] = $baseIgv;

                // Calcular IGV
                $igv = ($baseIgv * $porcentajeIgv) / 100;
                $detalle['igv'] = $igv;

                // Calcular impuestos totales del item
                $detalle['total_impuestos'] = $igv;

                // Calcular precio unitario (incluye impuestos)
                $detalle['mto_precio_unitario'] = ($valorVenta + $igv) / $cantidad;
            }
        }

        return $detalles;
    }
}