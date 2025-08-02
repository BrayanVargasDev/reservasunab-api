<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UsuariosResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id_usuario,
            'avatar' => $this->avatar,
            'email' => $this->email,
            'tipoUsuario' => $this->tipos_usuario ?? null,
            'telefono' => $this->persona?->celular ?? '',
            'rol' => $this->rol->nombre ?? null,
            'tipoDocumento' => $this->persona?->tipo_documento_id ?? null,
            'documento' => $this->persona?->numero_documento ?? null,
            'nombre' => trim(($this->persona?->primer_nombre ?? '') . ' ' . ($this->persona?->segundo_nombre ?? '')),
            'apellido' =>
            trim(($this->persona?->primer_apellido ?? '') . ' ' . ($this->persona?->segundo_apellido ?? '')),
            'ultimoAcceso' => $this->ultimo_acceso,
            'estado' => $this->activo ? 'Activo' : 'Inactivo',
            'fechaCreacion' => $this->creado_en,
            'viendoDetalles' => false,
            'direccion' => $this->persona?->direccion,
            'fechaNacimiento' => $this->persona?->fecha_nacimiento,
            'regimenTributario' => $this->persona?->regimen_tributario_id ?? null,
            'ciudadExpedicion' => $this->persona?->ciudad_expedicion_id ?? null,
            'ciudadResidencia' => $this->persona?->ciudad_residencia_id ?? null,
            'tipoPersona' => $this->persona?->tipo_persona ?? null,
        ];
    }
}
