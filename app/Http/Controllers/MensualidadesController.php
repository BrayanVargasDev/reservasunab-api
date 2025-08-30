<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMensualidadRequest;
use App\Http\Requests\UpdateMensualidadRequest;
use App\Http\Resources\MensualidadResource;
use App\Models\Mensualidades;
use Illuminate\Http\Request;

class MensualidadesController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $query = Mensualidades::query()->orderByDesc('creado_en');
        return MensualidadResource::collection($query->paginate($perPage));
    }

    public function store(StoreMensualidadRequest $request)
    {
        $data = $request->validated();
        $mensualidad = Mensualidades::create($data);
        return new MensualidadResource($mensualidad);
    }

    public function show(Mensualidades $mensualidad)
    {
        return new MensualidadResource($mensualidad);
    }

    public function update(UpdateMensualidadRequest $request, Mensualidades $mensualidad)
    {
        $mensualidad->update($request->validated());
        return new MensualidadResource($mensualidad);
    }

    public function destroy(Mensualidades $mensualidad)
    {
        $mensualidad->delete();
        return response()->json(['success' => true]);
    }
}
