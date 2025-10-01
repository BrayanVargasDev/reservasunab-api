<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Permiso;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PermisoSeeder extends Seeder
{


    private $permisos = [
        // Pantalla reservas id = 6
        [
            'nombre' => 'administrar_reservas',
            'codigo' => 'RES000001',
            'icono' => '',
            'descripcion' => 'Administrar todas las reservas.',
            'id_pantalla' => 6,
        ],
        [
            'nombre' => 'cancelar_reservas',
            'codigo' => 'RES000002',
            'icono' => '',
            'descripcion' => 'Cancelar reservas en la zona de administración.',
            'id_pantalla' => 6,
        ],
        // Espacios id = 4
        [
            'nombre' => 'crear_espacios',
            'codigo' => 'ESP000001',
            'icono' => '',
            'descripcion' => 'Crear nuevos espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'desactivar_espacios',
            'codigo' => 'ESP000002',
            'icono' => '',
            'descripcion' => 'Desactivar espacios existentes.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'editar_espacios',
            'codigo' => 'ESP000003',
            'icono' => '',
            'descripcion' => 'Editar información general de los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'crear_franjas_horarias',
            'codigo' => 'ESP000004',
            'icono' => '',
            'descripcion' => 'Crear franjas horarias para los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'editar_franjas_horarias',
            'codigo' => 'ESP000005',
            'icono' => '',
            'descripcion' => 'Editar franjas horarias de los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'eliminar_franjas_horarias',
            'codigo' => 'ESP000006',
            'icono' => '',
            'descripcion' => 'Eliminar franjas horarias de los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'crear_configuracion_tipo_usuario',
            'codigo' => 'ESP000007',
            'icono' => '',
            'descripcion' => 'Crear configuraciones de tipo de usuario para los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'editar_configuracion_tipo_usuario',
            'codigo' => 'ESP000008',
            'icono' => '',
            'descripcion' => 'Editar configuraciones de tipo de usuario para los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'desactivar_configuracion_tipo_usuario',
            'codigo' => 'ESP000009',
            'icono' => '',
            'descripcion' => 'Desactivar configuraciones de tipo de usuario para los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'crear_novedades_espacio',
            'codigo' => 'ESP000010',
            'icono' => '',
            'descripcion' => 'Crear novedades para los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'editar_novedades_espacio',
            'codigo' => 'ESP000011',
            'icono' => '',
            'descripcion' => 'Editar novedades de los espacios.',
            'id_pantalla' => 4,
        ],
        [
            'nombre' => 'desactivar_novedades_espacio',
            'codigo' => 'ESP000012',
            'icono' => '',
            'descripcion' => 'Desactivar novedades de los espacios.',
            'id_pantalla' => 4,
        ],
        // Pagos id = 5
        [
            'nombre' => 'ver_pagos',
            'codigo' => 'PAG000001',
            'icono' => '',
            'descripcion' => 'Ver todos los pagos de la plataforma.',
            'id_pantalla' => 5,
        ],
        [
            'nombre' => 'acceso_pagos',
            'codigo' => 'PAG000002',
            'icono' => '',
            'descripcion' => 'Acceso a la pantalla de pagos.',
            'id_pantalla' => 5,
        ],
        // Usuarios id = 2
        [
            'nombre' => 'crear_usuarios',
            'codigo' => 'USR000001',
            'icono' => '',
            'descripcion' => 'Crear nuevos usuarios(graduados).',
            'id_pantalla' => 2,
        ],
        [
            'nombre' => 'editar_usuarios',
            'codigo' => 'USR000002',
            'icono' => '',
            'descripcion' => 'Editar información de usuarios.',
            'id_pantalla' => 2,
        ],
        [
            'nombre' => 'cambiar_rol_usuarios',
            'codigo' => 'USR000003',
            'icono' => '',
            'descripcion' => 'Cambiar el rol de los usuarios.',
            'id_pantalla' => 2,
        ],
        [
            'nombre' => 'desactivar_usuarios',
            'codigo' => 'USR000004',
            'icono' => '',
            'descripcion' => 'Desactivar usuarios del sistema.',
            'id_pantalla' => 2,
        ],
        // Permisos id = 3
        [
            'nombre' => 'crear_roles',
            'codigo' => 'PER000001',
            'icono' => '',
            'descripcion' => 'Crear nuevos roles.',
            'id_pantalla' => 3,
        ],
        [
            'nombre' => 'editar_roles',
            'codigo' => 'PER000002',
            'icono' => '',
            'descripcion' => 'Editar información y permisos de los roles.',
            'id_pantalla' => 3,
        ],
        // Configuracion id = 7
        [
            'nombre' => 'crear_categorias',
            'codigo' => 'CFG000001',
            'icono' => '',
            'descripcion' => 'Crear nuevas categorías de elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'editar_categorias',
            'codigo' => 'CFG000002',
            'icono' => '',
            'descripcion' => 'Editar categorías de elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'desactivar_categorias',
            'codigo' => 'CFG000003',
            'icono' => '',
            'descripcion' => 'Desactivar categorías de elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'crear_grupos',
            'codigo' => 'CFG000004',
            'icono' => '',
            'descripcion' => 'Crear nuevos grupos de elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'editar_grupos',
            'codigo' => 'CFG000005',
            'icono' => '',
            'descripcion' => 'Editar grupos de elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'desactivar_grupos',
            'codigo' => 'CFG000006',
            'icono' => '',
            'descripcion' => 'Desactivar grupos de elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'crear_elementos',
            'codigo' => 'CFG000007',
            'icono' => '',
            'descripcion' => 'Crear nuevos elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'editar_elementos',
            'codigo' => 'CFG000008',
            'icono' => '',
            'descripcion' => 'Editar elementos.',
            'id_pantalla' => 7,
        ],
        [
            'nombre' => 'desactivar_elementos',
            'codigo' => 'CFG000009',
            'icono' => '',
            'descripcion' => 'Desactivar elementos.',
            'id_pantalla' => 7,
        ],
        // Dashboard id = 1
        [
            'nombre' => 'descargar_reservas_mes',
            'codigo' => 'DSB000001',
            'icono' => '',
            'descripcion' => 'Descargar el informe de reservas por mes.',
            'id_pantalla' => 1,
        ],
        [
            'nombre' => 'descargar_recaudo_mes',
            'codigo' => 'DSB000002',
            'icono' => '',
            'descripcion' => 'Descargar el informe de recaudo mensual.',
            'id_pantalla' => 1,
        ],

    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear permisos estáticos primero
        foreach ($this->permisos as $permiso) {
            Permiso::updateOrCreate(
                ['codigo' => $permiso['codigo']],
                $permiso
            );
        }

        // Para categorías existentes que no tengan permiso, crearlos
        // (útil para migraciones o categorías creadas antes de implementar permisos automáticos)
        $categorias = Categoria::withTrashed()->get();

        foreach ($categorias as $categoria) {
            $permiso_codigo = 'ESP_CAT_' . str_pad($categoria->id, 6, '0', STR_PAD_LEFT);

            // Solo crear si no existe
            if (!Permiso::where('codigo', $permiso_codigo)->exists()) {
                Permiso::create([
                    'nombre' => 'gestionar_espacios_categoria_' . $categoria->id,
                    'codigo' => $permiso_codigo,
                    'icono' => '',
                    'descripcion' => 'Gestionar espacios de la categoría ' . $categoria->nombre,
                    'id_pantalla' => 4,
                ]);
            }
        }
    }
}
