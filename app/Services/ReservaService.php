<?php

namespace App\Services;

use App\Models\Espacio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReservaService
{
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
            ->whereHas('configuraciones', $filtroConfiguraciones)
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

        Log::debug([
            'id' => $id,
            'fechaConsulta' => $fechaConsulta,
            'diaSemana' => $diaSemana
        ]);

        $espacio = Espacio::query()
            ->with([
                'imagen',
                'sede:id,nombre',
                'categoria:id,nombre,id_grupo',
                'categoria.grupo:id,nombre',
                'configuraciones' => function ($q) use ($fechaConsulta, $diaSemana) {
                    $q->where(function ($query) use ($fechaConsulta, $diaSemana) {
                        $query->whereDate('fecha', $fechaConsulta)
                            ->orWhere(function ($subQuery) use ($diaSemana) {
                                $subQuery->whereNull('fecha')
                                    ->where('dia_semana', $diaSemana);
                            });
                    })
                        ->select(
                            'id',
                            'id_espacio',
                            'fecha',
                            'dia_semana',
                            'minutos_uso',
                            'hora_apertura',
                            'dias_previos_apertura'
                        )
                        ->with([
                            'franjas_horarias' => fn($q) =>
                            $q->where('activa', true)->orderBy('hora_inicio')
                        ]);
                },
                'novedades' => function ($q) use ($fechaConsulta) {
                    $q->whereDate('fecha', $fechaConsulta);
                }
            ])
            ->find($id);

        if (!$espacio) {
            return $espacio;
        }

        $espacio->disponibilidad = $this->construirDisponibilidad($espacio, $fechaConsulta);
        return $espacio;
    }

    private function construirDisponibilidad($espacio, string $fechaConsulta): array
    {
        $disponibilidad = [];

        // Obtener la configuración aplicable para la fecha
        $configuracion = $espacio->configuraciones->first();

        if (!$configuracion) {
            return $disponibilidad;
        }

        // Obtener las franjas horarias de la configuración
        $franjasHorarias = $configuracion->franjas_horarias;

        // Obtener las novedades del día
        $novedades = $espacio->novedades;

        // Validar y formatear la hora de apertura
        if (empty($configuracion->hora_apertura)) {
            Log::warning('Hora de apertura no configurada para el espacio', [
                'espacio_id' => $espacio->id,
                'configuracion_id' => $configuracion->id ?? null
            ]);
            return $disponibilidad;
        }

        try {
            // Intentar crear Carbon desde diferentes formatos posibles
            $horaApertura = null;

            // Formato H:i:s (ej: 08:00:00)
            if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $configuracion->hora_apertura)) {
                $horaApertura = Carbon::createFromFormat('H:i:s', $configuracion->hora_apertura);
            }
            // Formato H:i (ej: 08:00)
            elseif (preg_match('/^\d{1,2}:\d{2}$/', $configuracion->hora_apertura)) {
                $horaApertura = Carbon::createFromFormat('H:i', $configuracion->hora_apertura);
            }
            // Si no coincide con ningún formato, usar valor por defecto
            else {
                Log::warning('Formato de hora de apertura no válido', [
                    'hora_apertura' => $configuracion->hora_apertura,
                    'espacio_id' => $espacio->id
                ]);
                $horaApertura = Carbon::createFromFormat('H:i:s', '08:00:00'); // Valor por defecto
            }

            $horaFin = Carbon::createFromFormat('H:i:s', '23:59:59');
            $minutosUso = $configuracion->minutos_uso ?? 60; // Valor por defecto
        } catch (\Exception $e) {
            Log::error('Error al procesar hora de apertura', [
                'error' => $e->getMessage(),
                'hora_apertura' => $configuracion->hora_apertura,
                'espacio_id' => $espacio->id
            ]);
            return $disponibilidad;
        }

        $horaActual = $horaApertura->copy();

        while ($horaActual->lessThan($horaFin)) {
            $horaInicioSlot = $horaActual->format('h:i A');
            $horaFinSlot = $horaActual->copy()->addMinutes($minutosUso)->format('h:i A');

            // Buscar si hay una franja horaria que coincida con este horario
            $franjaCoincidente = $franjasHorarias->first(function ($franja) use ($horaActual) {
                try {
                    if (!$franja->hora_inicio || !$franja->hora_fin) {
                        return false;
                    }

                    $franjaInicio = $this->createCarbonSafely($franja->hora_inicio->format('H:i'), 'H:i');
                    $franjaFin = $this->createCarbonSafely($franja->hora_fin->format('H:i'), 'H:i');

                    if (!$franjaInicio || !$franjaFin) {
                        return false;
                    }

                    return $franjaInicio->equalTo($horaActual) ||
                        ($franjaInicio->lessThanOrEqualTo($horaActual) && $horaActual->lessThan($franjaFin));
                } catch (\Exception $e) {
                    Log::warning('Error al procesar franja horaria', ['error' => $e->getMessage()]);
                    return false;
                }
            });

            // Buscar novedades que afecten este horario
            $novedadCoincidente = $novedades->first(function ($novedad) use ($horaActual, $minutosUso) {
                try {
                    if (!$novedad->hora_inicio || !$novedad->hora_fin) {
                        return false;
                    }

                    $novedadInicio = $this->createCarbonSafely($novedad->hora_inicio, 'H:i:s');
                    $novedadFin = $this->createCarbonSafely($novedad->hora_fin, 'H:i:s');
                    $slotFin = $horaActual->copy()->addMinutes($minutosUso);

                    if (!$novedadInicio || !$novedadFin) {
                        return false;
                    }

                    return ($horaActual->greaterThanOrEqualTo($novedadInicio) && $horaActual->lessThan($novedadFin)) ||
                        ($slotFin->greaterThan($novedadInicio) && $slotFin->lessThanOrEqualTo($novedadFin)) ||
                        ($horaActual->lessThanOrEqualTo($novedadInicio) && $slotFin->greaterThanOrEqualTo($novedadFin));
                } catch (\Exception $e) {
                    Log::warning('Error al procesar novedad', ['error' => $e->getMessage()]);
                    return false;
                }
            });

            // Construir el objeto de disponibilidad
            $slot = [
                'hora_inicio' => $horaInicioSlot,
                'hora_fin' => $horaFinSlot,
                'disponible' => true,
                'valor' => null,
                'estilos' => [
                    'background_color' => 'accent',
                    'text_color' => 'accent',
                    'border_color' => 'accent'
                ],
                'novedad' => null
            ];

            // Si hay franja horaria que coincide, agregar valor y estilos
            if ($franjaCoincidente) {
                $slot['valor'] = $franjaCoincidente->valor;
                $slot['estilos'] = [
                    'background_color' => $franjaCoincidente->valor > 0 ? 'success' : 'ghost',
                    'text_color' => $franjaCoincidente->valor > 0 ? 'success' : 'ghost',
                    'border_color' => $franjaCoincidente->valor > 0 ? 'success' : 'ghost',
                ];
            }

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

        return $disponibilidad;
    }
}
