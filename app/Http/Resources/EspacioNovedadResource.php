<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspacioNovedadResource extends JsonResource
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
            'id_espacio' => $this->espacio->id ?? null,
            'fecha_inicio' => $this->fecha?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'hora_inicio' => $this->hora_inicio ? Carbon::parse($this->hora_inicio)->format('H:i') : null,
            'hora_fin' => $this->hora_fin ? Carbon::parse($this->hora_fin)->format('H:i') : null,
            'descripcion' => $this->descripcion,
            'creado_en' => $this->creado_en?->format('Y-m-d H:i:s'),
            'actualizado_en' => $this->actualizado_en?->format('Y-m-d H:i:s'),
            'eliminado_en' => $this->eliminado_en?->format('Y-m-d H:i:s'),
            'creado_por' => $this->creado_por,
            'eliminado_por' => $this->eliminado_por,
        ];
    }
}
