<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\EspacioConfiguracionController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\EspacioNovedadController;
use App\Http\Controllers\EspacioTipoUsuarioConfigController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PantallaController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\RegimenTributarioController;
use App\Http\Controllers\ReservasController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\SharedController;
use App\Http\Controllers\TipoDocumentoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/fechas', [SharedController::class, 'fechas']);
Route::post('/ingresar', [AuthController::class, 'login']);
Route::post('/registrar', [AuthController::class, 'registrar']);
Route::get('/validate-email/{email}', [UsuarioController::class, 'validarEmailTomado']);
Route::get('/storage/{ruta}', [SharedController::class, 'servirArchivo'])
    ->where('ruta', '.*');

Route::group(['middleware' => ['auth:sanctum', 'verify.token.expiration']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'user']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    Route::group(['prefix' => 'usuarios'], function () {
        Route::get('/', [UsuarioController::class, 'index']);
        Route::post('/', [UsuarioController::class, 'store']);
        Route::get('/terminos-condiciones', [UsuarioController::class, 'validarTerminosCondiciones']);
        Route::get('/perfil-completo', [UsuarioController::class, 'validarPerfilCompleto']);
        Route::post('/terminos-condiciones', [UsuarioController::class, 'terminosCondiciones']);
        Route::get('/eliminados', [UsuarioController::class, 'trashed']);
        Route::get('/buscar-jugadores', [UsuarioController::class, 'jugadores']);
        Route::put('/cambiar-password', [UsuarioController::class, 'cambiarPassword']);
        Route::get('/{usuario}', [UsuarioController::class, 'show']);
        Route::patch('/{usuario}', [UsuarioController::class, 'update']);
        Route::delete('/{usuario}', [UsuarioController::class, 'destroy']);
        Route::patch('/{id}/restaurar', [UsuarioController::class, 'restore']);
        Route::patch('/{id}/validar-campos-facturacion', [UsuarioController::class, 'validarCamposFacturacion']);
        Route::post('/dsbd', [UsuarioController::class, 'storeFromDashboard']);
        Route::patch('/dsbd/{usuario}', [UsuarioController::class, 'updateFromDashboard']);

        Route::patch('/{usuario}/permisos', [UsuarioController::class, 'updatePermisos']);
    });

    Route::group(['prefix' => 'personas'], function () {
        Route::get('/', [PersonaController::class, 'index']);
        Route::post('/', [PersonaController::class, 'store']);
        Route::get('/buscar-documento', [PersonaController::class, 'buscarPorDocumento']);
        Route::get('/{id}', [PersonaController::class, 'show']);
        Route::patch('/{id}', [PersonaController::class, 'update']);
        Route::delete('/{id}', [PersonaController::class, 'destroy']);
        Route::get('/{id}/facturacion', [PersonaController::class, 'facturacion']);
        Route::get('/{id}/ubicacion', [PersonaController::class, 'ubicacion']);
    });

    Route::group(['prefix' => 'tipo-doc'], function () {
        Route::get('/', [TipoDocumentoController::class, 'index']);
        Route::get('/{tipoDocumento}', [TipoDocumentoController::class, 'show']);
    });

    Route::group(['prefix' => 'regimenes-tributarios'], function () {
        Route::get('/', [RegimenTributarioController::class, 'index']);
        Route::post('/', [RegimenTributarioController::class, 'store']);
        Route::get('/{regimenTributario}', [RegimenTributarioController::class, 'show']);
        Route::patch('/{regimenTributario}', [RegimenTributarioController::class, 'update']);
        Route::delete('/{regimenTributario}', [RegimenTributarioController::class, 'destroy']);
    });

    Route::group(['prefix' => 'ciudades'], function () {
        Route::get('/', [CiudadController::class, 'index']);
        Route::post('/', [CiudadController::class, 'store']);
        Route::get('/departamento/{departamentoId}', [CiudadController::class, 'porDepartamento']);
        Route::get('/{ciudad}', [CiudadController::class, 'show']);
        Route::patch('/{ciudad}', [CiudadController::class, 'update']);
        Route::delete('/{ciudad}', [CiudadController::class, 'destroy']);
    });

    Route::group(['prefix' => 'departamentos'], function () {
        Route::get('/', [DepartamentoController::class, 'index']);
        Route::post('/', [DepartamentoController::class, 'store']);
        Route::get('/{departamento}', [DepartamentoController::class, 'show']);
        Route::patch('/{departamento}', [DepartamentoController::class, 'update']);
        Route::delete('/{departamento}', [DepartamentoController::class, 'destroy']);
    });

    Route::group(['prefix' => 'roles'], function () {
        Route::get('/', [RolController::class, 'index']);
        Route::post('/', [RolController::class, 'store']);
        Route::get('/permisos', [RolController::class, 'indexPermisos']);
        Route::get('/{rol}', [RolController::class, 'show']);
        Route::patch('/{rol}', [RolController::class, 'update']);
        Route::delete('/{rol}', [RolController::class, 'destroy']);

        // Rutas para gestión de permisos de roles
        Route::get('/{idRol}/permisos', [RolController::class, 'getPermisosRol']);
        Route::patch('/{idRol}/permisos', [RolController::class, 'asignarPermisosRol']);
    });

    Route::group(['prefix' => 'permisos'], function () {
        Route::get('/', [PermisoController::class, 'index']);
        Route::post('/', [PermisoController::class, 'store']);
        Route::get('/{permiso}', [PermisoController::class, 'show']);
        Route::patch('/{permiso}', [PermisoController::class, 'update']);
        Route::delete('/{permiso}', [PermisoController::class, 'destroy']);

        // Rutas para gestión de permisos de usuarios
        Route::get('/usuarios/{idUsuario}', [PermisoController::class, 'getPermisosUsuario']);
        Route::patch('/usuarios/{idUsuario}', [PermisoController::class, 'asignarPermisosUsuario']);
    });

    Route::group(['prefix' => 'pantallas'], function () {
        Route::get('/', [PantallaController::class, 'index']);
        Route::post('/', [PantallaController::class, 'store']);
    });

    Route::group(['prefix' => 'sedes'], function () {
        Route::get('/', [SedeController::class, 'index']);
        Route::get('/{sede}', [SedeController::class, 'show']);
    });

    Route::group(['prefix' => 'categorias'], function () {
        Route::get('/', [CategoriaController::class, 'index']);
        Route::get('/{categoria}', [CategoriaController::class, 'show']);
        Route::post('/', [CategoriaController::class, 'store']);
        Route::patch('/{categoria}', [CategoriaController::class, 'update']);
        Route::delete('/{categoria}', [CategoriaController::class, 'destroy']);
        Route::patch('/{categoria}/restaurar', [CategoriaController::class, 'restore']);
    });

    Route::group(['prefix' => 'espacios'], function () {
        Route::get('/', [EspacioController::class, 'index']);
        Route::get('/all', [EspacioController::class, 'indexAll']);
        Route::post('/', [EspacioController::class, 'store']);

        Route::group(['prefix' => 'tipo-usuario-config'], function () {
            Route::post('/', [EspacioTipoUsuarioConfigController::class, 'store']);
            Route::get('/{tipoUsuarioConfig}', [EspacioTipoUsuarioConfigController::class, 'show']);
            Route::patch('/{tipoUsuarioConfig}', [EspacioTipoUsuarioConfigController::class, 'update']);
            Route::delete('/{tipoUsuarioConfig}', [EspacioTipoUsuarioConfigController::class, 'destroy']);
            Route::patch('/{id}/restaurar', [EspacioTipoUsuarioConfigController::class, 'restore']);
        });

        Route::group(['prefix' => 'configuracion-base'], function () {
            Route::get('/', [EspacioConfiguracionController::class, 'index']);
            Route::get('/fecha', [EspacioConfiguracionController::class, 'showPorFecha']);
            Route::get('/{configuracion}', [EspacioConfiguracionController::class, 'show']);
            Route::post('/', [EspacioConfiguracionController::class, 'store']);
            Route::patch('/', [EspacioConfiguracionController::class, 'update']);
            Route::delete('/{configuracion}', [EspacioConfiguracionController::class, 'destroy']);
        });

        Route::group(['prefix' => 'novedades'], function () {
            Route::get('/', [EspacioNovedadController::class, 'index']);
            Route::post('/', [EspacioNovedadController::class, 'store']);
            Route::get('/{novedad}', [EspacioNovedadController::class, 'show']);
            Route::patch('/{novedad}', [EspacioNovedadController::class, 'update']);
            Route::delete('/{novedad}', [EspacioNovedadController::class, 'destroy']);
            Route::patch('/{novedad}/restaurar', [EspacioNovedadController::class, 'restore']);
            Route::delete('/{novedad}/force', [EspacioNovedadController::class, 'forceDelete']);
        });

        Route::get('/{espacio}', [EspacioController::class, 'show']);
        Route::patch('/{espacio}', [EspacioController::class, 'update']);
        Route::delete('/{espacio}', [EspacioController::class, 'destroy']);
        Route::patch('/{espacio}/restaurar', [EspacioController::class, 'restore']);
    });

    Route::group(['prefix' => 'dreservas'], function () {
        Route::get('/', [ReservasController::class, 'index']);
        Route::post('/', [ReservasController::class, 'store']);
        Route::get('/espacios', [ReservasController::class, 'getEspacios']);
        Route::get('/espacios/{espacio}', [ReservasController::class, 'getEspacioDetalles']);
        Route::get('/{reserva}', [ReservasController::class, 'show']);
        Route::patch('/{reserva}', [ReservasController::class, 'update']);
        Route::delete('/{reserva}', [ReservasController::class, 'destroy']);
    });

    Route::group(['prefix' => 'reservas'], function () {
        Route::get('/me', [ReservasController::class, 'misReservas']);
        Route::post('/', [ReservasController::class, 'store']);
        Route::get('/mi-reserva/{reserva}', [ReservasController::class, 'miReserva']);
        Route::post('/{reserva}/agregar-jugadores', [ReservasController::class, 'agregarJugadores']);
    });

    Route::group(['prefix' => 'grupos'], function () {
        Route::get('/', [SharedController::class, 'grupos']);
        Route::get('/{grupo}', [SharedController::class, 'grupo']);
        Route::post('/', [SharedController::class, 'crearGrupo']);
        Route::patch('/{grupo}', [SharedController::class, 'actualizarGrupo']);
        Route::delete('/{grupo}', [SharedController::class, 'eliminarGrupo']);
        Route::patch('/{grupo}/restaurar', [SharedController::class, 'restaurarGrupo']);
    });

    Route::group(['prefix' => 'pagos'], function () {
        Route::get('/', [PagoController::class, 'index']);
        Route::post('/reservas', [PagoController::class, 'reservas']);
        Route::get('/info', [PagoController::class, 'info']);
    });
});

Route::post('/ecollect', [PagoController::class, 'ecollect']);

Route::post('/test', function () {
    return response()->json(['message' => 'Hello, World!']);
});
