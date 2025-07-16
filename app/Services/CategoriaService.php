<?php

namespace App\Services;

use App\Models\Categoria;
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
            ]);
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
}
