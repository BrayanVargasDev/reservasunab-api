<?php

namespace App\Policies;

use App\Models\Espacio;
use App\Models\Usuario;
use Illuminate\Auth\Access\HandlesAuthorization;

class EspacioPolicy
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
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Espacio  $espacio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(Usuario $usuario, Espacio $espacio)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(Usuario $usuario)
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Espacio  $espacio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(Usuario $usuario, Espacio $espacio)
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Espacio  $espacio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(Usuario $usuario, Espacio $espacio)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Espacio  $espacio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(Usuario $usuario, Espacio $espacio)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Espacio  $espacio
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(Usuario $usuario, Espacio $espacio)
    {
        //
    }
}
