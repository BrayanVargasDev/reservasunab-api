<?php

namespace App\Services;

use App\Exceptions\EspacioException;
use App\Models\Espacio;
use App\Models\EspacioImagen;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EspacioService
{

    public function getAll(int $perPage = 10, string $search = '')
    {
        $query = Espacio::withTrashed()
            ->with(['sede', 'categoria', 'creadoPor', 'categoria.grupo']);

        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(espacios.nombre::text) LIKE ?', [$searchTerm])
                    ->orWhereExists(function ($subQuery) use ($searchTerm) {
                        $subQuery->select(DB::raw(1))
                            ->from('sedes')
                            ->whereRaw('espacios.id_sede::text = sedes.id::text')
                            ->whereRaw('LOWER(sedes.nombre::text) LIKE ?', [$searchTerm]);
                    })
                    ->orWhereExists(function ($subQuery) use ($searchTerm) {
                        $subQuery->select(DB::raw(1))
                            ->from('categorias')
                            ->whereRaw('espacios.id_categoria::text = categorias.id::text')
                            ->whereRaw('LOWER(categorias.nombre::text) LIKE ?', [$searchTerm])
                            ->whereNull('categorias.eliminado_en');
                    })
                    ->orWhereExists(function ($subQuery) use ($searchTerm) {
                        $subQuery->select(DB::raw(1))
                            ->from('categorias')
                            ->join('grupos', function ($join) {
                                $join->whereRaw('categorias.id_grupo::text = grupos.id::text')
                                    ->whereNull('grupos.eliminado_en');
                            })
                            ->whereRaw('espacios.id_categoria::text = categorias.id::text')
                            ->whereRaw('LOWER(grupos.nombre::text) LIKE ?', [$searchTerm])
                            ->whereNull('categorias.eliminado_en');
                    });
            });
        }
        $query->orderBy('espacios.id', 'asc');
        return $query->paginate($perPage);
    }

    public function getWithoutFilters()
    {
        return Espacio::with(['sede', 'categoria', 'creadoPor'])
            ->orderBy('nombre', 'asc')
            ->get();
    }

    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $espacio = [
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'agregar_jugadores' => $data['permitirJugadores'] ?? false,
                'minimo_jugadores' => $data['minimoJugadores'] ?? 0,
                'maximo_jugadores' => $data['maximoJugadores'] ?? 0,
                'permite_externos' => $data['permitirExternos'] ?? false,
                'id_sede' => $data['sede'],
                'id_categoria' => $data['categoria'],
                'creado_por' => Auth::id(),
            ];

            $espacio = Espacio::create($espacio);
            DB::commit();
            return $espacio->load('sede', 'categoria');
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al crear espacio', $e);

            throw $e;
        }
    }

    public function getById(int|string $id)
    {
        if (!is_numeric($id) || intval($id) != $id) {
            throw new EspacioException(
                'El ID del espacio debe ser un número entero',
                'invalid_id_format',
                400,
            );
        }

        try {
            return Espacio::with('sede', 'categoria')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new EspacioException(
                "Espacio no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        }
    }

    public function getByIdFull(int | string $id)
    {
        if (!is_numeric($id) || intval($id) != $id) {
            throw new EspacioException(
                'El ID del espacio debe ser un número entero',
                'invalid_id_format',
                400,
            );
        }

        try {
            return Espacio::with([
                'sede',
                'categoria',
                'elementos',
                'configuraciones' => function ($query) {
                    $query->with(['franjas_horarias']);
                },
                'novedades' => function ($query) {
                    $query->orderBy('fecha', 'desc');
                },
                'imagen',
                'configuraciones',
                'configuraciones.franjas_horarias',
                'tipo_usuario_config' => function ($query) {
                    $query->withTrashed();
                },
                'creadoPor:id_usuario',
            ])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new EspacioException(
                "Espacio no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        } catch (\Exception $e) {
            $this->logError('Error al consultar espacio completo', $e, ['id' => $id]);

            throw new EspacioException(
                'Error al consultar el espacio: ' . $e->getMessage(),
                'query_failed',
                500,
                $e,
            );
        }
    }

    public function delete(int $id): ?bool
    {
        try {
            $espacio = $this->getById($id);
            return $espacio->delete();
        } catch (EspacioException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error al eliminar espacio', $e, ['id' => $id]);

            throw new EspacioException(
                'Error al eliminar el espacio: ' . $e->getMessage(),
                'deletion_failed',
                500,
                $e,
            );
        }
    }

    public function restore(int $id): Espacio
    {
        try {
            $espacio = Espacio::withTrashed()->find($id);

            if (!$espacio) {
                throw new ModelNotFoundException(
                    "Espacio no encontrado con ID: {$id}",
                );
            }

            if (!$espacio->trashed()) {
                throw new EspacioException(
                    "El espacio con ID: {$id} no está eliminado",
                    'not_deleted',
                    400,
                );
            }

            $espacio->restore();
            $espacio->load('sede', 'categoria');
            return $espacio;
        } catch (ModelNotFoundException $e) {
            throw new EspacioException(
                "Espacio no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        } catch (EspacioException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error al restaurar espacio', $e, ['id' => $id]);

            throw new EspacioException(
                'Error al restaurar el espacio: ' . $e->getMessage(),
                'restore_failed',
                500,
                $e,
            );
        }
    }

    public function update(int $id, array $data): Espacio
    {
        try {
            DB::beginTransaction();
            $espacio = $this->getById($id);

            $updateData = [
                'nombre' => $data['nombre'] ?? $espacio->nombre,
                'descripcion' => $data['descripcion'] ?? $espacio->descripcion,
                'agregar_jugadores' => array_key_exists('permitirJugadores', $data) ? (bool)$data['permitirJugadores'] : $espacio->agregar_jugadores,
                'minimo_jugadores' => $data['minimoJugadores'] ?? $espacio->minimo_jugadores,
                'maximo_jugadores' => $data['maximoJugadores'] ?? $espacio->maximo_jugadores,
                'permite_externos' => array_key_exists('permitirExternos', $data) ? (bool)$data['permitirExternos'] : $espacio->permite_externos,
                'id_sede' => $data['sede'] ?? $espacio->id_sede,
                'id_categoria' => $data['categoria'] ?? $espacio->id_categoria,
                'actualizado_por' => Auth::id(),
                'reservas_simultaneas' => $data['reservasSimultaneas'] ?? $espacio->reservas_simultaneas,
                'aprobar_reserva' => array_key_exists('aprobarReservas', $data) ? (bool)$data['aprobarReservas'] : $espacio->aprobar_reserva,
                'tiempo_limite_reserva' => $data['limiteTiempoReserva'] ?? $espacio->tiempo_limite_reserva,
                'despues_hora' => array_key_exists('despuesHora', $data) ? $data['despuesHora'] : $espacio->despues_hora,
                'id_edificio' => $data['codigoEdificio'] ?? $espacio->id_edificio,
                'codigo' => $data['codigoEspacio'] ?? $espacio->codigo,
                'pago_mensual' => array_key_exists('pagoMensualidad', $data) ? (bool)$data['pagoMensualidad'] : $espacio->pago_mensual,
                'valor_mensualidad' => $data['valorMensualidad'] ?? $espacio->valor_mensual,
            ];

            $espacio->update($updateData);

            if (isset($data['elementosEnlazados'])) {
                $espacio->elementos()->sync($data['elementosEnlazados']);
            }

            if ($espacio->getOriginal('aprobar_reserva')) {
                try {
                    $today = now()->startOfDay();

                    $configIds = DB::table('espacios_configuracion')
                        ->where('id_espacio', $espacio->id)
                        ->whereNull('eliminado_en')
                        ->where(function ($q) use ($today) {
                            $q->whereNull('fecha')
                                ->orWhere('fecha', '>=', $today->toDateString());
                        })
                        ->pluck('id');

                    if ($configIds->count()) {
                        $franjas = DB::table('franjas_horarias')
                            ->whereIn('id_config', $configIds)
                            ->whereNull('eliminado_en');
                        $franjas->update(['valor' => 0, 'actualizado_en' => now()]);
                    }
                } catch (\Exception $inner) {
                    $this->logError('Error al actualizar franjas horarias tras aprobar reserva', $inner, ['id_espacio' => $espacio->id]);
                }
            }
            DB::commit();

            return $espacio->load('sede', 'categoria');
        } catch (EspacioException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar espacio', $e, ['id' => $id]);

            throw new EspacioException(
                'Error al actualizar el espacio: ' . $e->getMessage(),
                'update_failed',
                500,
                $e,
            );
        }
    }

    public function forceDelete(int $id): bool
    {
        try {
            $espacio = Espacio::withTrashed()->find($id);

            if (!$espacio) {
                throw new ModelNotFoundException(
                    "Espacio no encontrado con ID: {$id}",
                );
            }

            return $espacio->forceDelete();
        } catch (ModelNotFoundException $e) {
            throw new EspacioException(
                "Espacio no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        } catch (\Exception $e) {
            $this->logError('Error al eliminar permanentemente el espacio', $e, ['id' => $id]);

            throw new EspacioException(
                'Error al eliminar permanentemente el espacio: ' . $e->getMessage(),
                'force_delete_failed',
                500,
                $e,
            );
        }
    }

    public function getAllWithTrashed(int $perPage = 10, string $search = '')
    {
        $query = Espacio::withTrashed()
            ->with(['sede', 'categoria', 'creadoPor']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('creado_en', 'desc')->paginate($perPage);
    }

    public function getOnlyTrashed(int $perPage = 10, string $search = '')
    {
        $query = Espacio::onlyTrashed()
            ->with(['sede', 'categoria', 'creadoPor']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('eliminado_en', 'desc')->paginate($perPage);
    }

    public function existeImagen(int $id_espacio, string $hash): bool
    {
        return EspacioImagen::where('id_espacio', $id_espacio)
            ->where('codigo', $hash)
            ->exists();
    }

    public function guardarImagen(Espacio $espacio, $data)
    {
        try {
            DB::beginTransaction();

            if ($espacio->imagen) {
                $espacio->imagen->delete();
                Storage::disk('public')->delete($espacio->imagen->url);
            }

            $espacio->imagen()->create([
                'url' => $data['url'],
                'titulo' => $data['titulo'] ?? null,
                'codigo' => $data['codigo'],
                'ubicacion' => $data['ubicacion'] ?? null,
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al guardar imagen del espacio', $e, ['id_espacio' => $espacio->id]);
            return false;
        }
    }

    private function logError(string $message, Exception $exception, array $context = []): void
    {
        Log::error($message, array_merge($context, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]));
    }
}
