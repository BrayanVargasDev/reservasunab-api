<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PersonaFactory extends Factory
{
    public function definition()
    {
        return [
            'tipo_documento_id' => $this->faker->numberBetween(1, 4),
            'numero_documento' => $this->faker->unique()->numerify('##########'),
            'primer_nombre' => $this->faker->firstName(),
            'segundo_nombre' => $this->faker->optional(0.7)->firstName(),
            'primer_apellido' => $this->faker->lastName(),
            'segundo_apellido' => $this->faker->optional(0.8)->lastName(),
            'fecha_nacimiento' => $this->faker->date(),
            'direccion' => $this->faker->address(),
            'celular' => $this->faker->regexify('3[0-9]{9}'),
            'id_usuario' => UsuarioFactory::new()->create()->id_usuario,
            'creado_en' => now(),
            'actualizado_en' => null
        ];
    }
}
