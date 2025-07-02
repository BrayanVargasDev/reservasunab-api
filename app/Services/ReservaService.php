<?php

namespace App\Services;

use App\Models\Espacio;
use Carbon\Carbon;

class ReservaService
{

    public function getAll(int $per_page = 10, string $search)
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
        $carbon = $fecha ? Carbon::createFromFormat('Y-m-d', $fecha)
            : now();

        $fechaConsulta = $carbon->toDateString();
        $diaSemana = $carbon->dayOfWeekIso;

        return Espacio::query()
            ->filtros($sede, $categoria, $grupo)
            ->whereHas('configuraciones', function ($q) use ($fecha, $fechaConsulta, $diaSemana) {
                $q->when(
                    $fecha,
                    fn($q)  => $q->whereDate('fecha', $fechaConsulta)
                        ->orWhere(fn($s) => $s->whereNull('fecha')
                            ->where('dia_semana', $diaSemana)),
                    fn($q)  => $q->whereNull('fecha')
                        ->where('dia_semana', $diaSemana)
                );
            })
            ->with([
                'imagen',
                'sede:id,nombre',
                'categoria:id,nombre,id_grupo',
                'categoria.grupo:id,nombre',
                'configuraciones' => fn($q) =>
                $q->select(
                    'id',
                    'id_espacio',
                    'fecha',
                    'dia_semana',
                    'minutos_uso',
                    'hora_apertura',
                    'dias_previos_apertura',
                )
                    ->where(function ($q) use ($fecha, $fechaConsulta, $diaSemana) {
                        $q->when(
                            $fecha,
                            fn($q) => $q->whereDate('fecha', $fechaConsulta)
                                ->orWhere(fn($s) => $s->whereNull('fecha')
                                    ->where('dia_semana', $diaSemana)),
                            fn($q) => $q->whereNull('fecha')
                                ->where('dia_semana', $diaSemana)
                        );
                    })
                    ->with([
                        'franjas_horarias' => fn($q) =>
                        $q->where('activa', true)->orderBy('hora_inicio')
                    ])
            ])

            ->orderBy('nombre')
            ->select(['id', 'nombre', 'id_sede', 'id_categoria'])
            ->get();
    }
}
