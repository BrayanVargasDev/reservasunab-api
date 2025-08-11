<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'codigo' => $this->codigo,
            'actualizado_en' => $this->actualizado_en,
            'creado_en' => $this->creado_en,
            'eliminado_en' => $this->eliminado_en,
            'estado' => $this->estado,
            'id_reserva' => $this->id_reserva,
            'reserva' => $this->reserva,
            'ticket_id' => $this->ticket_id,
            'url_ecollect' => $this->url_ecollect,
            'valor' => $this->valor,
        ];
    }
}
