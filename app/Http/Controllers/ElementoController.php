<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreElementoRequest;
use App\Http\Requests\UpdateElementoRequest;
use App\Http\Resources\ElementoResource;
use App\Models\Elemento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ElementoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $search = strtolower(trim(request('search', '')));
        $rawIdEspacio = request('id_espacio', null);
        $from_crud = filter_var(request('from_crud', false), FILTER_VALIDATE_BOOLEAN);

        if (is_string($rawIdEspacio)) {
            $trimmed = trim($rawIdEspacio);
            $lower = strtolower($trimmed);
            if ($trimmed === '' || $lower === 'null' || $lower === 'undefined') {
                $id_espacio = null;
            } elseif (ctype_digit($trimmed)) {
                $id_espacio = (int) $trimmed;
            } else {
                $id_espacio = null;
            }
        } elseif (is_numeric($rawIdEspacio)) {
            $id_espacio = (int) $rawIdEspacio;
        } else {
            $id_espacio = null;
        }

        if ($id_espacio === 0) {
            $id_espacio = null;
        }

        $elementos = Elemento::withTrashed()
            ->orderByDesc('creado_en')
            ->when($search, function ($query, $search) {
                $query->whereRaw('LOWER(nombre) LIKE ?', ["%{$search}%"]);
            })
            ->when($id_espacio, function ($query, $id_espacio) {
                $query->whereHas('espacios', function ($q) use ($id_espacio) {
                    $q->where('espacios.id', $id_espacio);
                });
            })
            ->when(!$from_crud, function ($query) {
                $query->whereNull('eliminado_en');
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Elementos encontrados correctamente.',
            'data' => ElementoResource::collection($elementos->get()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreElementoRequest $request)
    {
        $elemento = Elemento::create($request->validated());
        return new ElementoResource($elemento);
    }

    /**
     * Display the specified resource.
     */
    public function show(Elemento $elemento)
    {
        return new ElementoResource($elemento);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Elemento $elemento)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateElementoRequest $request, Elemento $elemento)
    {
        $elemento->withTrashed()->update($request->validated());
        return new ElementoResource($elemento);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Elemento $elemento)
    {
        $elemento->delete();
        return response()->json(['success' => true]);
    }

    public function restore($id)
    {
        $elemento = Elemento::withTrashed()->findOrFail($id);
        if ($elemento->trashed()) {
            $elemento->restore();
            return response()->json(['success' => true, 'message' => 'Elemento restaurado correctamente.']);
        } else {
            return response()->json(['success' => false, 'message' => 'El elemento no está eliminado.'], 400);
        }
    }
}
