<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElementoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'valor_administrativo' => $this->valor_administrativo,
            'valor_estudiante' => $this->valor_estudiante,
            'valor_externo' => $this->valor_externo,
            'valor_egresado' => $this->valor_egresado,
            'creado_en' => $this->creado_en,
            'actualizado_en' => $this->actualizado_en,
            'eliminado_en' => $this->eliminado_en,
        ];
    }
}
