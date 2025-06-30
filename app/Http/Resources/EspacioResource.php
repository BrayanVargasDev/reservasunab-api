<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EspacioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipoEspacio' => $this->categoria->nombre ?? null,
            'estado' => !$this->eliminado_en ? 'activo' : 'inactivo',
        ];
    }
}
