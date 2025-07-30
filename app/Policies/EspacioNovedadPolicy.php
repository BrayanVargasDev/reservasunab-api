<?php

namespace App\Policies;

use App\Models\EspacioNovedad;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

class EspacioNovedadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Usuario $usuario): bool
    {
        // Permitir ver novedades a usuarios autenticados
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Usuario $usuario, EspacioNovedad $espacioNovedad): bool
    {
        // Permitir ver una novedad específica a usuarios autenticados
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Usuario $usuario): bool
    {
        // Solo administradores y staff pueden crear novedades
        return in_array($usuario->tipo_usuario, ['administrador', 'staff']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Usuario $usuario, EspacioNovedad $espacioNovedad): bool
    {
        // Solo administradores, staff o quien creó la novedad pueden actualizarla
        return in_array($usuario->tipo_usuario, ['administrador', 'staff']) || 
               $espacioNovedad->creado_por === $usuario->id_usuario;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Usuario $usuario, EspacioNovedad $espacioNovedad): bool
    {
        // Solo administradores, staff o quien creó la novedad pueden eliminarla
        return in_array($usuario->tipo_usuario, ['administrador', 'staff']) || 
               $espacioNovedad->creado_por === $usuario->id_usuario;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Usuario $usuario, EspacioNovedad $espacioNovedad): bool
    {
        // Solo administradores y staff pueden restaurar novedades eliminadas
        return in_array($usuario->tipo_usuario, ['administrador', 'staff']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Usuario $usuario, EspacioNovedad $espacioNovedad): bool
    {
        // Solo administradores pueden eliminar permanentemente
        return $usuario->tipo_usuario === 'administrador';
    }
}
