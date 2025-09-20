<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Reservas;
use App\Models\FranjaHoraria;
use App\Services\ReservaService;
use App\Exports\ReservasExport;
use App\Exports\PagosExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    private $reservaService;

    public function __construct(ReservaService $reservaService)
    {
        $this->reservaService = $reservaService;
    }

    public function indicadoresDashboard()
    {
        $reservas_query = Reservas::with('usuarioReserva')->whereDate('fecha', today())->get();
        $reservas_hoy = $reservas_query->count();
        $usuarios_con_reservas_hoy = $reservas_query->pluck('id_usuario')->unique()->count();
        $pagos_hoy = Pago::whereDate('creado_en', today())->where('estado', 'OK')->sum('valor');

        return response()->json([
            'status' => 'success',
            'message' => 'Indicadores del día obtenidos correctamente',
            'data' => [
                'reservas_hoy' => $reservas_hoy ?? 0,
                'usuarios_hoy' => $usuarios_con_reservas_hoy ?? 0,
                'recaudado_hoy' => $pagos_hoy ?? 0,
                'ocupacion_hoy' => $this->getOcupacionHoy() ?? 0,
            ]
        ]);
    }

    private function getOcupacionHoy()
    {
        try {
            $fechaHoy = Carbon::today()->toDateString();

            $espacios = $this->reservaService->getAllEspacios($fechaHoy);
            $totalSlots = 0;
            $slotsOcupados = 0;
            $espaciosConsultados = 0;
            $espaciosConError = 0;
            $slotsConError = 0;
            foreach ($espacios as $espacio) {
                try {
                    if (!$espacio->configuraciones) {
                        continue;
                    }
                    $primeraConfig = $espacio->configuraciones->first();
                    if (!$primeraConfig || empty($primeraConfig->franjas_horarias)) {
                        continue;
                    }

                    $espacioDetalles = $this->reservaService->getEspacioDetalles($espacio->id, $fechaHoy);

                    if (isset($espacioDetalles->disponibilidad) && is_array($espacioDetalles->disponibilidad)) {
                        foreach ($espacioDetalles->disponibilidad as $slot) {
                            try {
                                $totalSlots++;
                                if ((isset($slot['reservada']) && $slot['reservada']) || (isset($slot['novedad']) && $slot['novedad'])) {
                                    $slotsOcupados++;
                                }
                            } catch (Exception $eSlot) {
                                $slotsConError++;
                                continue;
                            }
                        }
                    }
                    $espaciosConsultados++;
                } catch (Exception $eEspacio) {
                    $espaciosConError++;
                    Log::warning('Error procesando espacio ' . ($espacio->id ?? 'desconocido') . ': ' . $eEspacio->getMessage());
                    continue;
                }
            }

            $porcentajeOcupacion = $totalSlots > 0 ? round(($slotsOcupados / $totalSlots) * 100, 2) : 0;

            return $porcentajeOcupacion;
        } catch (Exception $e) {
            Log::error('Error al calcular la ocupación de hoy: ' . $e->getMessage());
            return null;
        }
    }

    public function promedioPorHoras()
    {
        try {
            // Obtener todas las horas disponibles de las franjas horarias activas
            $horasDisponibles = FranjaHoraria::where('activa', true)
                ->selectRaw('DISTINCT EXTRACT(HOUR FROM hora_inicio) as hora')
                ->orderBy('hora')
                ->pluck('hora')
                ->toArray();

            if (empty($horasDisponibles)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No hay franjas horarias configuradas',
                    'data' => []
                ]);
            }

            $horaMinima = min($horasDisponibles);
            $horaMaxima = max($horasDisponibles);

            // Obtener todas las reservas con sus horas de inicio
            $reservasPorHora = Reservas::withTrashed()
                ->selectRaw('EXTRACT(HOUR FROM hora_inicio) as hora, COUNT(*) as cantidad')
                ->whereNotNull('hora_inicio')
                ->groupByRaw('EXTRACT(HOUR FROM hora_inicio)')
                ->pluck('cantidad', 'hora')
                ->toArray();

            // Formatear respuesta con todas las horas desde mínima hasta máxima
            $resultado = [];
            $totalReservas = 0;

            for ($h = $horaMinima; $h <= $horaMaxima; $h++) {
                $cantidad = isset($reservasPorHora[$h]) ? (int) $reservasPorHora[$h] : 0;
                $hora24 = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                $hora12 = Carbon::createFromTime($h)->format('h:00 A');
                $resultado[] = [
                    'hora_24h' => $hora24,
                    'hora' => $hora12,
                    'promedio' => $cantidad
                ];
                $totalReservas += $cantidad;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reservas por hora obtenidas correctamente',
                'data' => $resultado,
                'total_reservas' => $totalReservas
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener reservas por horas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al obtener las reservas por horas'
            ], 500);
        }
    }

    public function recaudoMensual(Request $request)
    {
        try {
            // Obtener el año del request, o usar el año actual si no se proporciona
            $anio = $request->input('anio', Carbon::now()->year);

            $aniosValidos = Pago::selectRaw('DISTINCT EXTRACT(YEAR FROM creado_en) as anio')
                ->whereNotNull('creado_en')
                ->where('estado', 'OK')
                ->orderBy('anio', 'desc')
                ->pluck('anio')
                ->toArray();

            if (!is_numeric($anio) || !in_array($anio, $aniosValidos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El año proporcionado no es válido'
                ], 400);
            }

            // Consultar recaudo mensual solo para pagos con estado 'OK'
            $recaudoMensual = Pago::selectRaw('EXTRACT(MONTH FROM creado_en) as mes, SUM(valor) as recaudo')
                ->whereYear('creado_en', $anio)
                ->where('estado', 'OK')
                ->groupByRaw('EXTRACT(MONTH FROM creado_en)')
                ->orderByRaw('EXTRACT(MONTH FROM creado_en)')
                ->pluck('recaudo', 'mes')
                ->toArray();

            $mesesAbreviados = [
                1 => 'Ene',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Abr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Ago',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dic'
            ];

            $resultado = [];
            for ($mes = 1; $mes <= 12; $mes++) {
                $resultado[] = [
                    'mes' => $mesesAbreviados[$mes],
                    'recaudo' => $recaudoMensual[$mes] ?? 0
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Recaudo mensual obtenido correctamente',
                'data' => $resultado,
                'anio' => $anio
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener recaudo mensual: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al obtener el recaudo mensual'
            ], 500);
        }
    }

    public function reservasPorCategoria()
    {
        try {
            $totalReservas = Reservas::withTrashed()->count();

            if ($totalReservas == 0) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No hay reservas registradas',
                    'data' => []
                ]);
            }

            $reservasPorCategoria = Reservas::withTrashed()
                ->join('espacios', 'reservas.id_espacio', '=', 'espacios.id')
                ->join('categorias', 'espacios.id_categoria', '=', 'categorias.id')
                ->selectRaw('categorias.nombre as categoria, COUNT(*) as cantidad')
                ->groupBy('categorias.id', 'categorias.nombre')
                ->orderBy('cantidad', 'desc')
                ->get();

            $resultado = [];
            foreach ($reservasPorCategoria as $item) {
                $porcentaje = round(($item->cantidad / $totalReservas) * 100);
                $resultado[] = [
                    'categoria' => $item->categoria,
                    'cantidad' => $porcentaje
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reservas por categoría obtenidas correctamente',
                'data' => $resultado,
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener reservas por categoría: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al obtener las reservas por categoría'
            ], 500);
        }
    }

    public function reservasPorMes(Request $request)
    {
        try {
            // Obtener el año del request, o usar el año actual si no se proporciona
            $anio = $request->input('anio', Carbon::now()->year);

            $aniosValidos = Reservas::selectRaw('DISTINCT EXTRACT(YEAR FROM fecha) as anio')
                ->whereNotNull('fecha')
                ->orderBy('anio', 'desc')
                ->pluck('anio')
                ->toArray();

            if (!is_numeric($anio) || !in_array($anio, $aniosValidos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El año proporcionado no es válido'
                ], 400);
            }

            // Consultar reservas totales (incluyendo eliminadas)
            $reservasTotales = Reservas::selectRaw('EXTRACT(MONTH FROM fecha) as mes, COUNT(*) as cantidad')
                ->whereYear('fecha', $anio)
                ->withTrashed()
                ->whereNotIn('estado', ['inicial'])
                ->groupByRaw('EXTRACT(MONTH FROM fecha)')
                ->orderByRaw('EXTRACT(MONTH FROM fecha)')
                ->pluck('cantidad', 'mes')
                ->toArray();

            // Consultar reservas completadas (estado 'completada' o 'pagada')
            $reservasCompletadas = Reservas::selectRaw('EXTRACT(MONTH FROM fecha) as mes, COUNT(*) as cantidad')
                ->whereYear('fecha', $anio)
                ->withTrashed()
                ->whereIn('estado', ['completada', 'pagada'])
                ->groupByRaw('EXTRACT(MONTH FROM fecha)')
                ->orderByRaw('EXTRACT(MONTH FROM fecha)')
                ->pluck('cantidad', 'mes')
                ->toArray();

            // Consultar reservas canceladas
            $reservasCanceladas = Reservas::selectRaw('EXTRACT(MONTH FROM fecha) as mes, COUNT(*) as cantidad')
                ->whereYear('fecha', $anio)
                ->withTrashed()
                ->where('estado', 'cancelada')
                ->groupByRaw('EXTRACT(MONTH FROM fecha)')
                ->orderByRaw('EXTRACT(MONTH FROM fecha)')
                ->pluck('cantidad', 'mes')
                ->toArray();

            $mesesAbreviados = [
                1 => 'Ene',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Abr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Ago',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dic'
            ];

            $resultado = [];
            for ($mes = 1; $mes <= 12; $mes++) {
                $resultado[] = [
                    'mes' => $mesesAbreviados[$mes],
                    'total' => $reservasTotales[$mes] ?? 0,
                    'completadas' => $reservasCompletadas[$mes] ?? 0,
                    'canceladas' => $reservasCanceladas[$mes] ?? 0
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reservas por mes obtenidas correctamente',
                'data' => $resultado,
                'anio' => $anio
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener reservas por mes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al obtener las reservas por mes'
            ], 500);
        }
    }

    public function aniosConReservas()
    {
        try {
            $anios = Reservas::selectRaw('DISTINCT EXTRACT(YEAR FROM fecha) as anio')
                ->whereNotNull('fecha')
                ->orderBy('anio', 'desc')
                ->pluck('anio')
                ->toArray();

            return response()->json([
                'status' => 'success',
                'message' => 'Años con reservas obtenidos correctamente',
                'data' => $anios,
                'total_anios' => count($anios)
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener años con reservas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al obtener los años con reservas'
            ], 500);
        }
    }

    public function descargarReservasExcel(Request $request)
    {
        try {
            $mes = $request->input('mes');
            $anio = $request->input('anio', Carbon::now()->year);

            // Validar parámetros
            if (!$mes || !is_numeric($mes) || $mes < 1 || $mes > 12) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El mes es requerido y debe ser un número entre 1 y 12'
                ], 400);
            }

            if (!is_numeric($anio) || $anio < 2020 || $anio > Carbon::now()->year + 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El año debe ser un número válido'
                ], 400);
            }

            $nombreArchivo = "reservas_{$mes}_{$anio}.xlsx";

            return Excel::download(new ReservasExport($mes, $anio), $nombreArchivo);
        } catch (Exception $e) {
            Log::error('Error al descargar reservas Excel: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al descargar el archivo Excel de reservas'
            ], 500);
        }
    }

    public function descargarPagosExcel(Request $request)
    {
        try {
            $mes = $request->input('mes');
            $anio = $request->input('anio', Carbon::now()->year);

            // Validar parámetros
            if (!$mes || !is_numeric($mes) || $mes < 1 || $mes > 12) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El mes es requerido y debe ser un número entre 1 y 12'
                ], 400);
            }

            if (!is_numeric($anio) || $anio < 2020 || $anio > Carbon::now()->year + 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El año debe ser un número válido'
                ], 400);
            }

            $nombreArchivo = "pagos_{$mes}_{$anio}.xlsx";

            return Excel::download(new PagosExport($mes, $anio), $nombreArchivo);
        } catch (Exception $e) {
            Log::error('Error al descargar pagos Excel: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al descargar el archivo Excel de pagos'
            ], 500);
        }
    }
}
