<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservasRequest;
use App\Http\Requests\UpdateReservasRequest;
use App\Http\Resources\EspacioReservaResource;
use App\Models\Reservas;
use App\Services\ReservaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReservasController extends Controller
{

    private $reserva_service;

    public function __construct(ReservaService $reserva_service)
    {
        $this->reserva_service = $reserva_service;
    }

    public function index()
    {
        //
    }

    public function getEspacios(Request $request)
    {
        try {
            // $this->authorize('verTodos', Usuario::class);
            $per_page = $request->input('per_page', 10);
            $fecha = $request->input('fecha', '');
            $grupo = $request->input('id_grupo', '');
            $sede = $request->input('id_sede', '');
            $categoria = $request->input('id_categoria', '');

            $espacios = $this->reserva_service->getAllEspacios(
                $fecha,
                $grupo,
                $sede,
                $categoria
            );
            return response()->json(
                [
                    'status' => 'success',
                    'data' => EspacioReservaResource::collection($espacios),
                    'message' => 'Espacios obtenidos correctamente.',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar espacios', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los espacios',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function getEspacioDetalles($espacioId)
    {
        try {

            $fecha = request()->input('fecha', '');

            $espacio = $this->reserva_service->getEspacioDetalles((int) $espacioId, $fecha);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacio,
                    'message' => 'Detalles del espacio obtenidos correctamente.',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error('Error al obtener detalles del espacio', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los detalles del espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function create()
    {
        //
    }

    public function store(StoreReservasRequest $request)
    {
        try {
            $data = $request->validated();

            $reserva = $this->reserva_service->iniciarReserva($data);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $reserva,
                    'message' => 'Reserva creada correctamente.',
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al crear la reserva', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al crear la reserva',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Reservas  $reservas
     * @return \Illuminate\Http\Response
     */
    public function show(Reservas $reservas)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Reservas  $reservas
     * @return \Illuminate\Http\Response
     */
    public function edit(Reservas $reservas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateReservasRequest  $request
     * @param  \App\Models\Reservas  $reservas
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateReservasRequest $request, Reservas $reservas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Reservas  $reservas
     * @return \Illuminate\Http\Response
     */
    public function destroy(Reservas $reservas)
    {
        //
    }
}
