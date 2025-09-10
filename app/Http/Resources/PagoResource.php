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
            'ticket_id' => $this->ticket_id,
            'url_ecollect' => $this->url_ecollect,
            'valor' => $this->valor,
            'reserva' => $this->reserva,
            'detalles' => $this->whenLoaded('detalles', function () {
                return $this->detalles->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'tipo_concepto' => $d->tipo_concepto,
                        'cantidad' => $d->cantidad,
                        'id_concepto' => $d->id_concepto,
                        'total' => $d->total,
                        'creado_en' => $d->creado_en,
                        'actualizado_en' => $d->actualizado_en,
                        'eliminado_en' => $d->eliminado_en,
                    ];
                });
            }),
        ];
    }
}
