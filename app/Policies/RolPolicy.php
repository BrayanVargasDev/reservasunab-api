<?php

namespace App\Policies;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(Usuario $usuario)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Rol  $rol
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(Usuario $usuario, Rol $rol)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(Usuario $usuario)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Rol  $rol
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(Usuario $usuario, Rol $rol)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Rol  $rol
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(Usuario $usuario, Rol $rol)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Rol  $rol
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(Usuario $usuario, Rol $rol)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Rol  $rol
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(Usuario $usuario, Rol $rol)
    {
        return true;
    }
}
