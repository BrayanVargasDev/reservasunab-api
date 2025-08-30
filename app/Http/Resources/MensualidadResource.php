<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MensualidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_usuario' => $this->id_usuario,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'valor' => $this->valor,
            'estado' => $this->estado,
            'creado_en' => $this->creado_en,
            'actualizado_en' => $this->actualizado_en,
            'eliminado_en' => $this->eliminado_en,
        ];
    }
}
