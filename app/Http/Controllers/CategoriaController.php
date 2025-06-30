<?php

namespace App\Http\Controllers;

use App\Services\CategoriaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CategoriaController extends Controller
{
    private $categoria_service;

    public function __construct(CategoriaService $categoria_service)
    {
        $this->categoria_service = $categoria_service;
    }

    public function index()
    {
        try {
            $categorias = $this->categoria_service->getAll();
            return response()->json([
                'success' => true,
                'data' => $categorias,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al consultar categorías', [
                'usuario_id' => Auth::id() ?? 'no autenticado',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los categorías',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
