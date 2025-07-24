<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'id_grupo' => $this->id_grupo,
            'creado_en' => $this->creado_en,
            'actualizado_en' => $this->actualizado_en,
            'creado_por' => $this->creado_por,
            'actualizado_por' => $this->actualizado_por,
            'eliminado_por' => $this->eliminado_por,
            'eliminado_en' => $this->eliminado_en,
            'reservas_estudiante' => $this->reservas_estudiante,
            'reservas_administrativo' => $this->reservas_administrativo,
            'reservas_egresado' => $this->reservas_egresado,
            'reservas_externo' => $this->reservas_externo,
            'grupo' => $this->grupo,
        ];
    }
}
