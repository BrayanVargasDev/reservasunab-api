<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Models\Persona;
use App\Services\PersonaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonaController extends Controller
{
    protected PersonaService $personaService;

    public function __construct(PersonaService $personaService)
    {
        $this->personaService = $personaService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $personas = $this->personaService->getAll($perPage, $search);

            return response()->json([
                'success' => true,
                'data' => $personas,
                'message' => 'Personas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las personas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StorePersonaRequest $request
     * @return JsonResponse
     */
    public function store(StorePersonaRequest $request): JsonResponse
    {
        try {
            $persona = $this->personaService->create($request->validated());

            return response()->json([
                'success' => true,
                'data' => $persona,
                'message' => 'Persona creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $persona = $this->personaService->getById($id);

            return response()->json([
                'success' => true,
                'data' => $persona,
                'message' => 'Persona obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePersonaRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdatePersonaRequest $request, int $id): JsonResponse
    {
        try {
            $persona = $this->personaService->update($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $persona,
                'message' => 'Persona actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->personaService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Persona eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener datos de facturación de una persona
     *
     * @param int $id
     * @return JsonResponse
     */
    public function facturacion(int $id): JsonResponse
    {
        try {
            $persona = $this->personaService->getById($id);
            $datosFacturacion = $this->personaService->getDatosFacturacion($persona);

            return response()->json([
                'success' => true,
                'data' => $datosFacturacion,
                'message' => 'Datos de facturación obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Buscar persona por número de documento
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function buscarPorDocumento(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'numero_documento' => 'required|string'
            ]);

            $persona = $this->personaService->getByDocument($request->numero_documento);

            if (!$persona) {
                return response()->json([
                    'success' => false,
                    'message' => 'Persona no encontrada con el documento proporcionado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $persona,
                'message' => 'Persona encontrada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener información completa de ubicación de una persona
     *
     * @param int $id
     * @return JsonResponse
     */
    public function ubicacion(int $id): JsonResponse
    {
        try {
            $persona = $this->personaService->getById($id);

            $ubicacion = [
                'ciudad_expedicion' => [
                    'id' => $persona->ciudadExpedicion?->id,
                    'nombre' => $persona->ciudadExpedicion?->nombre,
                    'codigo' => $persona->ciudadExpedicion?->codigo,
                    'departamento' => $persona->ciudadExpedicion?->departamento?->nombre
                ],
                'ciudad_residencia' => [
                    'id' => $persona->ciudadResidencia?->id,
                    'nombre' => $persona->ciudadResidencia?->nombre,
                    'codigo' => $persona->ciudadResidencia?->codigo,
                    'departamento' => $persona->ciudadResidencia?->departamento?->nombre
                ],
                'direccion' => $persona->direccion
            ];

            return response()->json([
                'success' => true,
                'data' => $ubicacion,
                'message' => 'Información de ubicación obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Listar personas de facturación con filtros opcionales por número de documento y tipo de documento
     */
    public function facturacionIndex(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $numeroDocumento = $request->get('numero_documento');
            $tipoDocumentoId = $request->get('tipo_documento_id');

            $resultado = $this->personaService->filtrarPersonasFacturacion(
                $perPage,
                $numeroDocumento,
                $tipoDocumentoId ? (int) $tipoDocumentoId : null
            );

            return response()->json([
                'success' => true,
                'data' => $resultado,
                'message' => 'Personas de facturación obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
}
