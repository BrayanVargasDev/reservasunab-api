<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UsuariosResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id_usuario,
            'es_beneficiario' => data_get($this->resource, 'es_beneficiario', false),
            'id_beneficiario' => data_get($this->resource, 'id_beneficiario'),
            'avatar' => $this->avatar,
            'email' => $this->email,
            'tipoUsuario' => $this->tipos_usuario ?? null,
            'telefono' => $this->persona?->celular ?? '',
            'rol' => data_get($this->resource, 'rol.nombre'),
            'tipoDocumento' => $this->persona?->tipo_documento_id ?? null,
            'codigo_tipo_documento' => $this->persona?->tipoDocumento?->codigo ?? null,
            'ldap_uid' => $this->ldap_uid ?? null,
            'documento' => $this->persona?->numero_documento ?? null,
            'nombre' => trim(($this->persona?->primer_nombre ?? '') . ' ' . ($this->persona?->segundo_nombre ?? '')),
            'apellido' =>
            trim(($this->persona?->primer_apellido ?? '') . ' ' . ($this->persona?->segundo_apellido ?? '')),
            'ultimoAcceso' => data_get($this->resource, 'ultimo_acceso'),
            'estado' => $this->activo ? 'Activo' : 'Inactivo',
            'fechaCreacion' => $this->creado_en,
            'viendoDetalles' => false,
            'direccion' => $this->persona?->direccion,
            'fechaNacimiento' => $this->persona?->fecha_nacimiento,
            'regimenTributario' => $this->persona?->regimen_tributario_id ?? null,
            'ciudadExpedicion' => $this->persona?->ciudad_expedicion_id ?? null,
            'ciudadResidencia' => $this->persona?->ciudad_residencia_id ?? null,
            'tipoPersona' => $this->persona?->tipo_persona ?? null,
            'facturacion' => $this->persona?->personaFacturacion?->toArray() ?? null,
        ];
    }
}
