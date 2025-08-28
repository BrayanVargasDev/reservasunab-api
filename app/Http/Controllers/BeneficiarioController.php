<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Models\Beneficiario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class BeneficiarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarioId = Auth::id();
        $search = strtolower(trim($request->query('search', '')));

        $query = Beneficiario::with('tipoDocumento')
            ->where('id_usuario', $usuarioId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(apellido) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(parentesco) LIKE ?', ["%{$search}%"])
                    ->orWhere('documento', 'LIKE', "%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('nombre')->orderBy('apellido')->get(),
        ], 200);
    }

    public function store(StoreBeneficiarioRequest $request)
    {
        try {
            $data = $request->validated();
            $data['id_usuario'] = Auth::id();
            $data['tipo_documento_id'] = $data['tipoDocumento'];

            $beneficiario = Beneficiario::create($data);

            return response()->json([
                'status' => 'success',
                'data' => $beneficiario->load('tipoDocumento'),
                'message' => 'Beneficiario creado correctamente',
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error creando beneficiario', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo crear el beneficiario',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $usuarioId = Auth::id();
            $beneficiario = Beneficiario::where('id', $id)
                ->where('id_usuario', $usuarioId)
                ->firstOrFail();

            $beneficiario->delete(); // eliminación real

            return response()->json([
                'status' => 'success',
                'message' => 'Beneficiario eliminado correctamente',
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error eliminando beneficiario', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar el beneficiario',
            ], 404);
        }
    }
}
