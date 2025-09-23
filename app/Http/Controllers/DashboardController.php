<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Reservas;
use App\Models\FranjaHoraria;
use App\Services\ReservaService;
use App\Exports\ReservasExport;
use App\Exports\PagosExport;
use App\Models\EspacioConfiguracion;
use App\Models\EspacioNovedad;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

use function Psy\debug;

class DashboardController extends Controller
{
    private $reservaService;

    public function __construct(ReservaService $reservaService)
    {
        $this->reservaService = $reservaService;
    }

    public function indicadoresDashboard()
    {
        $fechaHoy = today()->toDateString();

        // Contar reservas del día
        $reservas_hoy = Reservas::whereDate('fecha', $fechaHoy)->count();

        // Contar usuarios únicos con reservas hoy (excluyendo null)
        $usuarios_con_reservas_hoy = Reservas::whereDate('fecha', $fechaHoy)
            ->whereNotNull('id_usuario')
            ->distinct('id_usuario')
            ->count('id_usuario');

        $pagos_hoy = Pago::whereDate('creado_en', $fechaHoy)->where('estado', 'OK')->sum('valor');

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
            $diaSemana = Carbon::today()->dayOfWeek + 1;

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

                    $espacio->configuracion = $primeraConfig;
                    $espacioDetalles = $this->reservaService->construirDisponibilidad($espacio, $fechaHoy);

                    if (isset($espacioDetalles) && is_array($espacioDetalles)) {
                        foreach ($espacioDetalles as $slot) {
                            try {
                                $reservasMaximas = $slot['reservas_maximas'] ?? 1;
                                $totalSlots += $reservasMaximas;
                                if (isset($slot['novedad']) && $slot['novedad']) {
                                    $slotsOcupados += $reservasMaximas;
                                } else {
                                    $slotsOcupados += $slot['reservas_actuales'] ?? 0;
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

            Log::info([
                'Ocupación Hoy',
                'Fecha' => $fechaHoy,
                'Espacios Consultados' => $espaciosConsultados,
                'Espacios con Error' => $espaciosConError,
                'Total Slots' => $totalSlots,
                'Slots con Error' => $slotsConError,
                'Slots Ocupados' => $slotsOcupados,
            ]);

            $porcentajeOcupacion = $totalSlots > 0 ? round(($slotsOcupados / $totalSlots) * 100, 2) : 0;

            return $porcentajeOcupacion;
        } catch (Exception $e) {
            Log::error('Error al calcular la ocupación de hoy: ' . $e->getMessage());
            return null;
        }
    }

    public function promedioPorHoras(Request $request)
    {
        try {
            $mes = $request->input('mes', Carbon::now()->month);
            $anio = $request->input('anio', Carbon::now()->year);

            // Validar parámetros
            if (!is_numeric($mes) || $mes < 1 || $mes > 12) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El mes debe ser un número entre 1 y 12'
                ], 400);
            }

            if (!is_numeric($anio) || $anio < 2020 || $anio > Carbon::now()->year + 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El año debe ser un número válido'
                ], 400);
            }

            // Query optimizada usando SQL raw similar a la lógica proporcionada
            $query = "
                WITH fechas AS (
                    SELECT
                        make_date(?, ?, 1) AS inicio_mes,
                        (make_date(?, ?, 1) + INTERVAL '1 month - 1 day')::date AS fin_mes
                ),
                dias AS (
                    SELECT generate_series(
                        (SELECT inicio_mes FROM fechas),
                        (SELECT fin_mes FROM fechas),
                        INTERVAL '1 day'
                    )::date AS dia
                ),
                rango_horas AS (
                    SELECT
                        MIN(EXTRACT(HOUR FROM r.hora_inicio))::int AS hmin,
                        MAX(EXTRACT(HOUR FROM r.hora_inicio))::int AS hmax
                    FROM reservas r, fechas f
                    WHERE r.estado = 'completada'
                      AND r.fecha >= f.inicio_mes
                      AND r.fecha <= f.fin_mes
                      AND r.hora_inicio IS NOT NULL
                ),
                horas AS (
                    SELECT generate_series(
                        COALESCE((SELECT hmin FROM rango_horas), 0),
                        COALESCE((SELECT hmax FROM rango_horas), 23)
                    ) AS hora
                ),
                base AS (
                    SELECT
                        d.dia,
                        h.hora,
                        COUNT(r.id) AS reservas_en_dia_hora
                    FROM dias d
                    CROSS JOIN horas h
                    LEFT JOIN reservas r
                           ON DATE(r.fecha) = d.dia
                          AND EXTRACT(HOUR FROM r.hora_inicio)::int = h.hora
                          AND r.estado = 'completada'
                          AND r.hora_inicio IS NOT NULL
                    GROUP BY d.dia, h.hora
                ),
                promedios AS (
                    SELECT
                        hora,
                        AVG(reservas_en_dia_hora)::NUMERIC(10,2) AS promedio_reservas
                    FROM base
                    GROUP BY hora
                )
                SELECT hora, promedio_reservas
                FROM promedios
                ORDER BY hora
            ";

            $resultados = DB::select($query, [$anio, $mes, $anio, $mes]);

            // Formatear respuesta
            $data = [];
            $totalPromedio = 0;

            foreach ($resultados as $row) {
                $hora = (int) $row->hora;
                $promedio = (float) $row->promedio_reservas;
                $hora24 = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
                $hora12 = Carbon::createFromTime($hora)->format('h:00 A');
                $data[] = [
                    'hora_24h' => $hora24,
                    'hora' => $hora12,
                    'promedio' => $promedio
                ];
                $totalPromedio += $promedio;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Promedio de reservas por hora obtenido correctamente',
                'data' => $data,
                'mes' => $mes,
                'anio' => $anio,
                'total_promedio' => round($totalPromedio, 2)
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener promedio de reservas por horas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al obtener el promedio de reservas por horas'
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

    public function reservasPorCategoria(Request $request)
    {
        try {
            $mes = $request->input('mes', Carbon::now()->month);
            $anio = $request->input('anio', Carbon::now()->year);

            // Validar parámetros
            if (!is_numeric($mes) || $mes < 1 || $mes > 12) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El mes debe ser un número entre 1 y 12'
                ], 400);
            }

            if (!is_numeric($anio) || $anio < 2020 || $anio > Carbon::now()->year + 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El año debe ser un número válido'
                ], 400);
            }

            // Query optimizada usando SQL raw
            $query = "
                WITH fechas AS (
                    SELECT
                        make_date(?, ?, 1) AS inicio_mes,
                        (make_date(?, ?, 1) + INTERVAL '1 month - 1 day')::date AS fin_mes
                ),
                reservas_filtradas AS (
                    SELECT r.id, e.id_categoria
                    FROM reservas r
                    JOIN espacios e ON r.id_espacio = e.id
                    JOIN fechas f ON r.fecha >= f.inicio_mes AND r.fecha <= f.fin_mes
                    WHERE r.estado = 'completada'
                ),
                totales AS (
                    SELECT COUNT(*)::numeric AS total_reservas
                    FROM reservas_filtradas
                )
                SELECT
                    c.nombre AS categoria,
                    COUNT(rf.id) AS cantidad_reservas,
                    ROUND(100 * COUNT(rf.id) / t.total_reservas, 2) AS porcentaje
                FROM reservas_filtradas rf
                JOIN categorias c ON rf.id_categoria = c.id
                CROSS JOIN totales t
                GROUP BY c.nombre, t.total_reservas
                ORDER BY cantidad_reservas DESC
            ";

            $resultados = DB::select($query, [$anio, $mes, $anio, $mes]);

            if (empty($resultados)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No hay reservas completadas en el período seleccionado',
                    'data' => [],
                    'mes' => $mes,
                    'anio' => $anio
                ]);
            }

            $data = [];
            foreach ($resultados as $row) {
                $data[] = [
                    'categoria' => $row->categoria,
                    'cantidad' => (float) $row->porcentaje
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reservas por categoría obtenidas correctamente',
                'data' => $data,
                'mes' => $mes,
                'anio' => $anio
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
