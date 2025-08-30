<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreElementoRequest;
use App\Http\Requests\UpdateElementoRequest;
use App\Http\Resources\ElementoResource;
use App\Models\Elemento;
use Illuminate\Http\Request;

class ElementoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $elementos = Elemento::withTrashed()
            ->with('espacio')
            ->orderByDesc('creado_en')
            ->paginate(request('per_page', 15));

        return ElementoResource::collection($elementos);
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
        $elemento->update($request->validated());
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
