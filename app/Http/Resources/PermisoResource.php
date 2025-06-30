<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PermisoResource extends JsonResource
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
            'id_usuario' => $this->id_usuario,
            'nombre' => $this->persona?->primer_nombre . ' ' . $this->persona?->primer_apellido
                . ($this->persona?->segundo_nombre ? ' ' . $this->persona?->segundo_nombre : '')
                . ($this->persona?->segundo_apellido ? ' ' . $this->persona?->segundo_apellido : ''),
            'rol' => $this->rol?->nombre ?? null,
            'documento' => $this->persona?->numero_documento ?? null,
            'esAdmin' => $this->rol?->nombre === 'Administrador',
            'permisos' => $this->permisos_completos,
        ];
    }
}
