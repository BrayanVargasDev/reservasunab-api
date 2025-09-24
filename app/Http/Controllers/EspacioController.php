<?php

namespace App\Http\Controllers;

use App\Exceptions\EspacioException;
use App\Http\Requests\StoreEspacioRequest;
use App\Http\Requests\UpdateEspacioRequest;
use App\Http\Resources\EspacioResource;
use App\Models\Espacio;
use App\Services\EspacioService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class EspacioController extends Controller
{
    private $espacios_service;

    public function __construct(EspacioService $espacios_service)
    {
        $this->espacios_service = $espacios_service;
    }

    public function index(Request $request)
    {
        try {
            // $this->authorize('verTodos', Espacio::class);

            $perPage = $request->query('per_page', 10);
            $search = $request->query('search', '');

            $espacios = $this->espacios_service->getAll($perPage, $search);
            return EspacioResource::collection(
                $espacios,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar espacios', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
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

    public function indexAll(Request $request)
    {
        try {
            $espacios = $this->espacios_service->getWithoutFilters();
            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacios,
                    'message' => 'Espacios obtenidos correctamente.',
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al consultar todos los espacios', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
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

    public function store(StoreEspacioRequest $request)
    {
        try {
            // $this->authorize('crearDesdeDashboard', Espacio::class);
            $data = $request->validated();
            $espacio = $this->espacios_service->create($data, true);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacio,
                    'message' => 'Espacio creado correctamente.',
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error('Error al crear espacio.', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' =>
                    'Ocurrió un error al crear el espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function show($id)
    {
        try {
            $espacio = $this->espacios_service->getByIdFull($id);

            // $this->authorize('ver', $espacio);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacio,
                    'message' => 'espacio obtenido correctamente',
                ],
                200,
            );
        } catch (EspacioException $e) {
            Log::warning('Problema al obtener espacio', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al consultar espacio', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener el espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function edit(Espacio $espacio)
    {
        //
    }

    public function update(UpdateEspacioRequest $request, Espacio $espacio)
    {
        try {
            // $this->authorize('editar', $espacio);
            $data = $request->validated();
            $espacio = $this->espacios_service->update($espacio->id, $data);

            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');

                $hash = hash_file('sha256', $imagen->getRealPath());

                if (!$this->espacios_service->existeImagen($espacio->id, $hash)) {
                    $path = $imagen->store(
                        "espacios/{$espacio->id}",
                        'public',
                    );

                    $this->espacios_service->guardarImagen($espacio, [
                        'url' => $path,
                        'titulo' => $imagen->getClientOriginalName(),
                        'codigo' => $hash,
                        'ubicacion' => Storage::url($path),
                    ]);
                }
            }

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacio->load('imagen:id,url'),
                    'message' => 'Espacio actualizado correctamente',
                ],
                200,
            );
        } catch (EspacioException $e) {
            Log::warning('Problema al actualizar espacio', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $espacio->id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al actualizar espacio', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $espacio->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al actualizar el espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function destroy(Espacio $espacio)
    {
        try {
            // $this->authorize('eliminar', $espacio);

            $espacio_id = $espacio->id;
            if (
                !is_numeric($espacio_id) ||
                intval($espacio_id) != $espacio_id
            ) {
                throw new EspacioException(
                    'El ID del espacio debe ser un número entero',
                    'invalid_id_format',
                    400,
                );
            }

            $this->espacios_service->delete($espacio_id);

            return response()->json(
                [
                    'status' => 'success',
                    'message' => 'Espacio eliminado correctamente',
                ],
                200,
            );
        } catch (EspacioException $e) {
            Log::warning('Problema al eliminar espacio', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'espacio_id' => $espacio->id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al eliminar espacio', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'espacio_id' => $espacio->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al eliminar el espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function restore($id)
    {
        try {
            if (!is_numeric($id) || intval($id) != $id) {
                throw new EspacioException(
                    'El ID del espacio debe ser un número entero',
                    'invalid_id_format',
                    400,
                );
            }

            $espacio = Espacio::withTrashed()->findOrFail($id);

            // $this->authorize('restaurar', $espacio);

            $espacio = $this->espacios_service->restore($id);

            return response()->json(
                [
                    'status' => 'success',
                    'data' => $espacio,
                    'message' => 'Espacio restaurado correctamente',
                ],
                200,
            );
        } catch (EspacioException $e) {
            Log::warning('Problema al restaurar espacio', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'espacio_buscado_id' => $id,
                'error_type' => $e->getErrorType(),
                'error' => $e->getMessage(),
            ]);

            return $e->render();
        } catch (Exception $e) {
            Log::error('Error al restaurar espacio', [
                'espacio_id' => Auth::id() ?? 'no autenticado',
                'espacio_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al restaurar el espacio',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
