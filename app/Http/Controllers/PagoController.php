<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoRequest;
use App\Http\Requests\UpdatePagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Services\PagoService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PagoController extends Controller
{

    private $pago_service;

    public function __construct(PagoService $pago_service)
    {
        $this->pago_service = $pago_service;
    }

    public function index(Request $request)
    {
        try {
            $per_page = $request->get('per_page', 10);
            $search = $request->get('search', '');

            $pagos = $this->pago_service->obtenerPagos($per_page, $search);
            return PagoResource::collection($pagos);
        } catch (Exception $e) {
            Log::error('Error al obtener los pagos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los pagos.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function reservas(Request $request)
    {
        try {
            // $this->authorize('crearDesdeDashboard', Espacio::class);
            $data = $request->all();
            $origen = $data['origen'] ?? 'web';
            $so = $data['so'] ?? 'other';

            $pago = $this->pago_service->iniciarTransaccionDePago(
                id_reserva: $data['id_reserva'],
                origen: $origen,
                so: $so
            );

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $pago,
                    'message' => 'Pago creado correctamente.',
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al crear el pago.', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' =>
                    'Ocurrió un error al crear el el pago',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function pagarConSaldo(Request $request)
    {
        try {
            $idReserva = (int)($request->input('id_reserva'));
            $resultado = $this->pago_service->pagarConSaldo($idReserva);

            return response()->json([
                'status' => 'success',
                'data' => $resultado,
                'message' => 'Pago con saldo realizado correctamente.',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al pagar con saldo', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al pagar con saldo.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePagoRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Pago $pago)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pago $pago)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePagoRequest $request, Pago $pago)
    {
        //
    }

    public function destroy(Pago $pago)
    {
        //
    }

    public function ecollect(Request $request) {}

    public function info(Request $request)
    {
        try {
            $data = $request->all();

            $pago = $this->pago_service->get_info_pago(
                codigo: $data['codigo'],
            );

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $pago,
                    'message' => 'Información del pago obtenida correctamente.',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al obtener la información del pago.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener la información del pago.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function mensualidad(Request $request)
    {
        try {
            $data = $request->all();

            if (!isset($data['id_espacio'])) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'El ID del espacio es requerido.',
                    ],
                    400
                );
            }

            $mensualidad = $this->pago_service->crearMensualidad((int) $data['id_espacio']);
            $urlPago = $this->pago_service->iniciarTransaccionDeMensualidad($mensualidad->id);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $urlPago,
                    'message' => 'Pago de mensualidad creado correctamente.',
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al obtener la información de la mensualidad.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener la información de la mensualidad.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
