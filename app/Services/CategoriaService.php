<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Permiso;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CategoriaService
{
    public function getAll($perPage = null, $search = '')
    {
        $query = Categoria::with(['grupo' => function ($query) use ($perPage) {
            if ($perPage) {
                $query->withTrashed();
            }
        }])
            ->when($perPage, function ($query) {
                $query->withTrashed();
            })
            ->when(!empty($search), function ($query) use ($search) {
                $query->where('nombre', 'like', '%' . $search . '%');
            })
            ->orderBy('nombre', 'asc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    public function create(array $data)
    {
        try {
            DB::beginTransaction();
            $categoria = Categoria::create([
                'nombre' => $data['nombre'],
                'id_grupo' => $data['id_grupo'],
                'creado_por' => Auth::id(),
                'reservas_estudiante' => $data['reservas_estudiante'],
                'reservas_administrativo' => $data['reservas_administrativo'],
                'reservas_egresado' => $data['reservas_egresado'],
                'reservas_externo' => $data['reservas_externo'],
            ]);

            // Crear permiso automáticamente para la categoría
            $this->crearPermisoCategoria($categoria);

            DB::commit();
            return $categoria;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Error al crear la categoría: ' . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            DB::beginTransaction();
            $categoria = Categoria::findOrFail($id);
            $categoria->update([
                'nombre' => $data['nombre'],
                'id_grupo' => $data['id_grupo'],
                'actualizado_por' => Auth::id(),
                'reservas_estudiante' => $data['reservas_estudiante'],
                'reservas_administrativo' => $data['reservas_administrativo'],
                'reservas_egresado' => $data['reservas_egresado'],
                'reservas_externo' => $data['reservas_externo'],
            ]);
            DB::commit();
            return $categoria;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Error al actualizar la categoría: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $categoria = Categoria::findOrFail($id);
            $categoria->delete();
            DB::commit();
            return $categoria;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Error al eliminar la categoría: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            DB::beginTransaction();
            $categoria = Categoria::withTrashed()->findOrFail($id);
            $categoria->restore();
            DB::commit();
            return $categoria;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Error al restaurar la categoría: ' . $e->getMessage());
        }
    }

    /**
     * Crear permiso automáticamente para una categoría
     */
    private function crearPermisoCategoria(Categoria $categoria): void
    {
        $codigo = 'ESP' . str_pad($categoria->id, 6, '0', STR_PAD_LEFT);
        $nombre = 'gestionar_espacios_categoria_' . $categoria->id;

        Permiso::updateOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre,
                'codigo' => $codigo,
                'icono' => '',
                'descripcion' => 'Gestionar espacios de la categoría ' . $categoria->nombre,
                'id_pantalla' => 4, // Pantalla de espacios
            ]
        );
    }
}
