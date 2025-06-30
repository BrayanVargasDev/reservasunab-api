<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RolResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id_rol,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
            'creadoEn' => $this->creado_en,
            'actualizadoEn' => $this->actualizado_en,
        ];
    }
}
