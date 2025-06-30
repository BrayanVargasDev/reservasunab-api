<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TipoDocumentoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id_tipo,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'activo' => $this->activo
        ];
    }
}
