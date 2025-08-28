<?php

namespace App\Services;

use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Movimientos;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharedService
{

    private $fechas_url = 'https://date.nager.at/api/v3';
    private $codigo_pais = 'CO';


    public function seed_fechas(int | string $anio)
    {
        try {
            $url = $this->fechas_url . '/PublicHolidays' . '/' . $anio . '/' . $this->codigo_pais;
            $response = Http::get($url);

            if (!$response->successful()) {
                throw new Exception('Error al obtener los datos de la API: ' . $response->status());
            }

            $data = $response->json();

            if (empty($data)) {
                throw new Exception('No se encontraron datos para el año ' . $anio);
            }

            $fechas = [];

            foreach ($data as $fecha) {
                $fechas[] = [
                    'fecha' => $fecha['date'],
                    'descripcion' => $fecha['localName'],
                ];
            }

            Fecha::insert($fechas);
        } catch (Exception $e) {
            throw new Exception('Error al procesar las fechas: ' . $e->getMessage());
        }
    }

    public function get_grupos($perPage = null, $search = '')
    {
        try {
            $query = Grupo::when($perPage, function ($query) {
                $query->withTrashed();
            })
                ->when(!empty($search), function ($query) use ($search) {
                    $query->where('nombre', 'like', '%' . $search . '%');
                })
                ->orderBy('nombre', 'asc');

            return $perPage ? $query->paginate($perPage) : $query->get();
        } catch (Exception $e) {
            Log::error('Error al obtener grupos', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function create_grupo(array $data)
    {
        try {
            DB::beginTransaction();
            $grupo = Grupo::create([
                'nombre' => $data['nombre'],
                'creado_por' => Auth::id(),
            ]);
            DB::commit();
            return $grupo;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear grupo', ['error' => $e->getMessage()]);
            throw new Exception('Error al crear el grupo: ' . $e->getMessage());
        }
    }

    public function obtener_grupo($id)
    {
        try {
            $grupo = Grupo::with(['categorias'])->findOrFail($id);
            return $grupo;
        } catch (Exception $e) {
            Log::error('Error al obtener grupo', ['id' => $id, 'error' => $e->getMessage()]);
            throw new Exception('Error al obtener el grupo: ' . $e->getMessage());
        }
    }

    public function actualizar_grupo($id, array $data)
    {
        try {
            DB::beginTransaction();
            $grupo = Grupo::findOrFail($id);
            $grupo->update([
                'nombre' => $data['nombre'],
                'actualizado_por' => Auth::id(),
            ]);
            DB::commit();
            return $grupo;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar grupo', ['id' => $id, 'error' => $e->getMessage()]);
            throw new Exception('Error al actualizar el grupo: ' . $e->getMessage());
        }
    }

    public function eliminar_grupo($id)
    {
        try {
            DB::beginTransaction();
            $grupo = Grupo::findOrFail($id);
            $grupo->update(['eliminado_por' => Auth::id()]);
            $grupo->delete();
            DB::commit();
            return $grupo;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar grupo', ['id' => $id, 'error' => $e->getMessage()]);
            throw new Exception('Error al eliminar el grupo: ' . $e->getMessage());
        }
    }

    public function restore_grupo($id)
    {
        try {
            DB::beginTransaction();
            $grupo = Grupo::withTrashed()->findOrFail($id);
            $grupo->restore();
            DB::commit();
            return $grupo;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al restaurar grupo', ['id' => $id, 'error' => $e->getMessage()]);
            throw new Exception('Error al restaurar el grupo: ' . $e->getMessage());
        }
    }

    public function obtener_creditos()
    {
        // Consultar movimientos restar los ingresos y los egresos y hacer la resta
        $ingresos = Movimientos::where('tipo', 'ingreso')->where('id_usuario', Auth::id())->sum('valor');
        $egresos = Movimientos::where('tipo', 'egreso')->where('id_usuario', Auth::id())->sum('valor');
        return $ingresos - $egresos;
    }
}
