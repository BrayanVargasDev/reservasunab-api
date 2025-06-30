<?php

namespace App\Services;

use App\Exceptions\TipoDocumentoException;
use App\Http\Resources\TipoDocumentoResource;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TipoDocumentoService
{
    public function getAll()
    {
        return TipoDocumento::where('activo', true)->get();
    }

    public function getById(int|string $id)
    {
        if (!is_numeric($id) || intval($id) != $id) {
            throw new TipoDocumentoException(
                'El ID del tipo de documento debe ser un número entero',
                'invalid_id_format',
                400,
            );
        }

        try {
            return new TipoDocumentoResource(TipoDocumento::findOrFail($id));
        } catch (ModelNotFoundException $e) {
            throw new TipoDocumentoException(
                "Tipo de documento no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        }
    }
}
