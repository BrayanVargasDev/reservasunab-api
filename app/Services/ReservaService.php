<?php

namespace App\Services;

use App\Models\Espacio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReservaService
{

    const MINUTOS_USO_DEFAULT = 60;
    const DIAS_PREVIOS_APERTURA_DEFAULT = 1;
    const TIEMPO_CANCELACION_DEFAULT = 30;
    const HORA_APERTURA_DEFAULT = '08:00';

    /**
     * Crear un objeto Carbon de manera segura desde un formato específico
     */
    private function createCarbonSafely($value, $format, $default = null)
    {
        try {
            if (empty($value)) {
                return $default;
            }
            return Carbon::createFromFormat($format, $value);
        } catch (\Exception $e) {
            Log::warning('Error al crear Carbon desde formato', [
                'value' => $value,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    public function getAll(string $search = '', int $per_page = 10)
    {
        return [
            'data' => [],
            'per_page' => $per_page,
            'current_page' => 1,
            'total' => 0,
        ];
    }

    public function getAllEspacios(
        string $fecha = '',
        string $grupo = '',
        string $sede  = '',
        string $categoria = ''
    ) {
        $carbon = $fecha ? Carbon::createFromFormat('Y-m-d', $fecha) : now();

        $fechaConsulta = $carbon->toDateString();
        $diaSemana = $carbon->dayOfWeekIso;

        $filtroConfiguraciones = function ($q) use ($fecha, $fechaConsulta, $diaSemana) {
            if (!$fecha) {
                return $q->whereNull('fecha')->where('dia_semana', $diaSemana);
            }

            $q->where(function ($query) use ($fechaConsulta, $diaSemana) {
                $query->whereDate('fecha', $fechaConsulta)
                    ->orWhere(function ($subQuery) use ($fechaConsulta, $diaSemana) {
                        $subQuery->whereNull('fecha')
                            ->where('dia_semana', $diaSemana)
                            ->whereHas('espacio', function ($espacioQuery) use ($fechaConsulta) {
                                $espacioQuery->whereDoesntHave('configuraciones', function ($c) use ($fechaConsulta) {
                                    $c->whereDate('fecha', $fechaConsulta);
                                });
                            });
                    });
            });
        };

        return Espacio::query()
            ->filtros($sede, $categoria, $grupo)
            ->whereHas('configuraciones', function ($q) use ($filtroConfiguraciones) {
                $filtroConfiguraciones($q);
                $q->whereHas('franjas_horarias', function ($franjaQuery) {
                    $franjaQuery->where('activa', true);
                });
            })
            ->with([
                'imagen',
                'sede:id,nombre',
                'categoria:id,nombre,id_grupo',
                'categoria.grupo:id,nombre',
                'configuraciones' => function ($q) use ($filtroConfiguraciones) {
                    $filtroConfiguraciones($q);
                    $q->select(
                        'id',
                        'id_espacio',
                        'fecha',
                        'dia_semana',
                        'minutos_uso',
                        'hora_apertura',
                        'dias_previos_apertura',
                    )
                        ->with([
                            'franjas_horarias' => fn($q) =>
                            $q->where('activa', true)->orderBy('hora_inicio')
                        ]);
                }
            ])
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'id_sede', 'id_categoria'])
            ->get();
    }


    public function getEspacioDetalles(int $id, string $fecha = '')
    {
        $carbon = $fecha ? Carbon::createFromFormat('Y-m-d', $fecha) : now();
        $fechaConsulta = $carbon->toDateString();
        $diaSemana = $carbon->dayOfWeekIso;

        $espacio = Espacio::query()
            ->with([
                'imagen',
                'sede:id,nombre',
                'categoria:id,nombre,id_grupo',
                'categoria.grupo:id,nombre',
                'novedades' => function ($q) use ($fechaConsulta) {
                    $q->whereDate('fecha', $fechaConsulta);
                }
            ])
            ->find($id);

        if (!$espacio) {
            return $espacio;
        }

        $configuracion = $espacio->configuraciones()
            ->whereDate('fecha', $fechaConsulta)
            ->with([
                'franjas_horarias' => fn($q) =>
                $q->where('eliminado_en', null)->orderBy('hora_inicio')
            ])
            ->first();

        if (!$configuracion) {
            $configuracion = $espacio->configuraciones()
                ->whereNull('fecha')
                ->where('dia_semana', $diaSemana)
                ->with([
                    'franjas_horarias' => fn($q) =>
                    $q->where('eliminado_en', null)->orderBy('hora_inicio')
                ])
                ->first();
        }

        $espacio->configuracion = $configuracion;

        $espacio->disponibilidad = $this->construirDisponibilidad($espacio, $fechaConsulta);
        return $espacio;
    }

    private function construirDisponibilidad($espacio, string $fechaConsulta): array
    {
        $disponibilidad = [];

        // Obtener la configuración aplicable para la fecha
        $configuracion = $espacio->configuracion;

        if (!$configuracion) {
            return $disponibilidad;
        }

        // Obtener las franjas horarias de la configuración
        $franjasHorarias = $configuracion->franjas_horarias;

        // Obtener las novedades del día
        $novedades = $espacio->novedades;

        // Verificar que existan franjas horarias
        if ($franjasHorarias->isEmpty()) {
            Log::warning('No hay franjas horarias configuradas para el espacio', [
                'espacio_id' => $espacio->id,
                'configuracion_id' => $configuracion->id ?? null
            ]);
            return $disponibilidad;
        }

        $minutosUso = $configuracion->minutos_uso ?? 60; // Valor por defecto

        // Procesar cada franja horaria
        foreach ($franjasHorarias as $franja) {
            try {
                if (!$franja->hora_inicio || !$franja->hora_fin) {
                    Log::warning('Franja horaria con horas no válidas', [
                        'franja_id' => $franja->id,
                        'hora_inicio' => $franja->hora_inicio,
                        'hora_fin' => $franja->hora_fin
                    ]);
                    continue;
                }

                $franjaInicio = $this->createCarbonSafely($franja->hora_inicio->format('H:i'), 'H:i');
                $franjaFin = $this->createCarbonSafely($franja->hora_fin->format('H:i'), 'H:i');

                if (!$franjaInicio || !$franjaFin) {
                    Log::warning('Error al procesar horarios de franja', [
                        'franja_id' => $franja->id
                    ]);
                    continue;
                }

                $horaActual = $franjaInicio->copy();

                // Generar slots dentro de la franja horaria
                while ($horaActual->lessThan($franjaFin)) {
                    $horaInicioSlot = $horaActual->format('h:i A');
                    $horaFinSlot = $horaActual->copy()->addMinutes($minutosUso);

                    // Verificar que el slot no se salga de la franja
                    if ($horaFinSlot->greaterThan($franjaFin)) {
                        break;
                    }

                    $horaFinSlotFormatted = $horaFinSlot->format('h:i A');

                    // Buscar novedades que afecten este horario
                    $novedadCoincidente = $novedades->first(function ($novedad) use ($horaActual, $horaFinSlot) {
                        try {
                            if (!$novedad->hora_inicio || !$novedad->hora_fin) {
                                return false;
                            }

                            $novedadInicio = $this->createCarbonSafely($novedad->hora_inicio, 'H:i:s');
                            $novedadFin = $this->createCarbonSafely($novedad->hora_fin, 'H:i:s');

                            if (!$novedadInicio || !$novedadFin) {
                                return false;
                            }

                            return ($horaActual->greaterThanOrEqualTo($novedadInicio) && $horaActual->lessThan($novedadFin)) ||
                                ($horaFinSlot->greaterThan($novedadInicio) && $horaFinSlot->lessThanOrEqualTo($novedadFin)) ||
                                ($horaActual->lessThanOrEqualTo($novedadInicio) && $horaFinSlot->greaterThanOrEqualTo($novedadFin));
                        } catch (\Exception $e) {
                            Log::warning('Error al procesar novedad', ['error' => $e->getMessage()]);
                            return false;
                        }
                    });

                    // Construir el objeto de disponibilidad
                    $slot = [
                        'hora_inicio' => $horaInicioSlot,
                        'hora_fin' => $horaFinSlotFormatted,
                        'disponible' => true,
                        'valor' => $franja->valor,
                        'estilos' => [
                            'background_color' => $franja->valor > 0 ? 'accent' : 'ghost',
                            'text_color' => $franja->valor > 0 ? 'accent' : 'ghost',
                            'border_color' => $franja->valor > 0 ? 'accent' : 'ghost',
                        ],
                        'novedad' => null
                    ];

                    // Si hay novedad, agregarla y modificar disponibilidad
                    if ($novedadCoincidente) {
                        $slot['novedad'] = [
                            'id' => $novedadCoincidente->id,
                            'descripcion' => $novedadCoincidente->descripcion,
                            'tipo' => $novedadCoincidente->tipo,
                            'hora_inicio' => Carbon::createFromFormat('H:i:s', $novedadCoincidente->hora_inicio)->format('h:i A'),
                            'hora_fin' => Carbon::createFromFormat('H:i:s', $novedadCoincidente->hora_fin)->format('h:i A')
                        ];

                        // Modificar estilos según el tipo de novedad
                        if ($novedadCoincidente->tipo === 'mantenimiento' || $novedadCoincidente->tipo === 'cerrado') {
                            $slot['disponible'] = false;
                            $slot['estilos'] = [
                                'background_color' => 'error',
                                'text_color' => 'error',
                                'border_color' => 'error'
                            ];
                        } else {
                            $slot['estilos'] = [
                                'background_color' => 'warning',
                                'text_color' => 'warning',
                                'border_color' => 'warning'
                            ];
                        }
                    }

                    $disponibilidad[] = $slot;
                    $horaActual->addMinutes($minutosUso);
                }
            } catch (\Exception $e) {
                Log::error('Error al procesar franja horaria', [
                    'error' => $e->getMessage(),
                    'franja_id' => $franja->id ?? null,
                    'espacio_id' => $espacio->id
                ]);
                continue;
            }
        }

        // Ordenar la disponibilidad por hora de inicio
        usort($disponibilidad, function ($a, $b) {
            return strtotime($a['hora_inicio']) <=> strtotime($b['hora_inicio']);
        });

        return $disponibilidad;
    }
}
