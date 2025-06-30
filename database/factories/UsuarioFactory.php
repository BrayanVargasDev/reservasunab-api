<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UsuarioFactory extends Factory
{
    public function definition()
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'password_hash' => bcrypt('password'),
            'tipo_usuario' => 'administrativo',
            'id_rol' => 1,
            'ldap_uid' => $this->faker->optional(0.5)->uuid(),
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => null
        ];
    }
}
