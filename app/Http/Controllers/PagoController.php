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
            $pago = $this->pago_service->iniciarTransaccionDePago($data['id_reserva']);

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
            $pago = $this->pago_service->get_info_pago($data['codigo']);

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
}
