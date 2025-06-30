<?php

namespace App\Policies;

use App\Models\Usuario;
use Illuminate\Auth\Access\HandlesAuthorization;

class UsuarioPolicy
{
    use HandlesAuthorization;

    /**
     * Determina si el usuario puede ver la lista de usuarios.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return bool
     */
    public function verTodos(Usuario $usuario)
    {
        return $usuario->rol === 'administrador';
    }

    /**
     * Determina si el usuario puede ver un usuario específico.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Usuario  $modelo
     * @return bool
     */
    public function ver(Usuario $usuario, Usuario $modelo)
    {
        return $usuario->id_usuario === $modelo->id_usuario || $usuario->rol === 'administrador';
    }

    /**
     * Determina si el usuario puede crear nuevos usuarios.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return bool
     */
    public function crear(Usuario $usuario)
    {
        return $usuario->rol === 'administrador';
    }

    /**
     * Determina si el usuario puede actualizar un usuario específico.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Usuario  $modelo
     * @return bool
     */
    public function actualizar(Usuario $usuario, Usuario $modelo)
    {
        return $usuario->rol === 'administrador' || $usuario->id_usuario === $modelo->id_usuario;
    }

    /**
     * Determina si el usuario puede eliminar un usuario específico.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Usuario  $modelo
     * @return bool
     */
    public function eliminar(Usuario $usuario, Usuario $modelo)
    {
        return $usuario->rol === 'administrador' && $usuario->id_usuario !== $modelo->id_usuario;
    }

    /**
     * Determina si el usuario puede restaurar un usuario específico.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Usuario  $modelo
     * @return bool
     */
    public function restaurar(Usuario $usuario, Usuario $modelo)
    {
        return $usuario->rol === 'administrador';
    }

    /**
     * Determina si el usuario puede ver usuarios eliminados.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return bool
     */
    public function verEliminados(Usuario $usuario)
    {
        return $usuario->rol === 'administrador';
    }

    /**
     * Determina si el usuario puede cambiar el rol de un usuario.
     *
     * @param  \App\Models\Usuario  $usuario
     * @param  \App\Models\Usuario  $modelo
     * @return bool
     */
    public function cambiarRole(Usuario $usuario, Usuario $modelo)
    {
        return $usuario->rol === 'administrador' && $usuario->id_usuario !== $modelo->id_usuario;
    }
}
