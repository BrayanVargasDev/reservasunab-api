<?php

namespace App\Services;

use App\Exceptions\EspacioConfiguracionException;
use App\Models\EspacioConfiguracion;
use App\Models\Fecha;
use App\Models\FranjaHoraria;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EspacioConfiguracionService
{
    public function getAll(int | string $id_espacio)
    {
        return EspacioConfiguracion::with('franjas_horarias')
            ->where('id_espacio', $id_espacio)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getPorFecha(int|string $id_espacio, string $fecha)
    {
        $configPorFecha = EspacioConfiguracion::with('franjas_horarias')
            ->where('id_espacio', $id_espacio)
            ->where('fecha', $fecha)
            ->orderBy('id', 'desc')
            ->first();

        if ($configPorFecha) {
            return $configPorFecha->load('franjas_horarias');
        }

        $esFestivo = Fecha::where('fecha', $fecha)->exists();

        $carbon = Carbon::parse($fecha);
        $diaSemana = $esFestivo ? 8 : $carbon->dayOfWeekIso;

        $configPorDia = EspacioConfiguracion::with('franjas_horarias')
            ->where('id_espacio', $id_espacio)
            ->where('dia_semana', $diaSemana)
            ->orderBy('id', 'desc')
            ->first();

        if ($configPorDia) {
            try {
                DB::beginTransaction();

                $nuevaConfig = [
                    'id_espacio' => $configPorDia->id_espacio,
                    'minutos_uso' => $configPorDia->minutos_uso,
                    'hora_apertura' => $configPorDia->hora_apertura,
                    'dias_previos_apertura' => $configPorDia->dias_previos_apertura,
                    'tiempo_cancelacion' => $configPorDia->tiempo_cancelacion,
                    'fecha' => $fecha,
                    'dia_semana' => null,
                    'creado_por' => Auth::id()
                ];

                $nuevaConfiguracion = EspacioConfiguracion::create($nuevaConfig);

                $franjasOriginales = $configPorDia->franjas_horarias;
                $nuevasFranjas = [];

                foreach ($franjasOriginales as $franja) {
                    $nuevasFranjas[] = [
                        'id_config' => $nuevaConfiguracion->id,
                        'hora_inicio' => $franja->hora_inicio,
                        'hora_fin' => $franja->hora_fin,
                        'valor' => $franja->valor,
                        'activa' => true,
                    ];
                }

                if (!empty($nuevasFranjas)) {
                    $nuevaConfiguracion->franjas_horarias()->createMany($nuevasFranjas);
                }

                DB::commit();
                return $nuevaConfiguracion->load('franjas_horarias');
            } catch (Exception $e) {
                DB::rollBack();
                $this->logError('Error al crear configuración basada en día de semana', $e);

                return $configPorDia->load('franjas_horarias');
            }
        }

        return null;
    }

    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $config = [
                'id_espacio' => $data['id_espacio'],
                'minutos_uso' => $data['minutos_uso'],
                'hora_apertura' => $data['hora_apertura'],
                'dias_previos_apertura' => $data['dias_previos_apertura'] ?? 1,
                'tiempo_cancelacion' => $data['tiempo_cancelacion'],
                'fecha' => $data['fecha'] ?? null,
                'dia_semana' => $data['dia_semana'] ?? null,
                'creado_por' => 2,
                // 'creado_por' => auth()->id(),
            ];

            $db_config = EspacioConfiguracion::create($config);

            $franjasHorarias = [];

            if (isset($data['franjas_horarias']) && is_array($data['franjas_horarias'])) {
                foreach ($data['franjas_horarias'] as $franja) {
                    $franjasHorarias[] = [
                        'id_config' => $db_config->id,
                        'hora_inicio' => $franja['hora_inicio'],
                        'hora_fin' => $franja['hora_fin'],
                        'valor' => $franja['valor'] ?? 0,
                        'activa' => $franja['activa'] ?? true,
                    ];
                }

                if (!empty($franjasHorarias)) {
                    $db_config->franjas_horarias()->createMany($franjasHorarias);
                }
            }

            DB::commit();
            return $db_config->load('franjas_horarias');
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al crear la configuracion del espacio', $e);

            throw $e;
        }
    }

    public function getById(int|string $id)
    {
        if (!is_numeric($id) || intval($id) != $id) {
            throw new EspacioConfiguracionException(
                'El ID del espacio debe ser un número entero',
                'invalid_id_format',
                400,
            );
        }

        try {
            return EspacioConfiguracion::with('franjas_horarias')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new EspacioConfiguracionException(
                "Espacio no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        }
    }

    public function update(int $id, array $data): EspacioConfiguracion
    {
        try {
            DB::beginTransaction();

            $db_config = $this->getById($id);

            $updateData = [
                'id_espacio' => $data['id_espacio'] ?? $db_config->id_espacio,
                'minutos_uso' => $data['minutos_uso'] ?? $db_config->minutos_uso,
                'hora_apertura' => $data['hora_apertura'] ?? $db_config->hora_apertura,
                'dias_previos_apertura' => $data['dias_previos_apertura'] ?? $db_config->dias_previos_apertura,
                'tiempo_cancelacion' => $data['tiempo_cancelacion'] ?? $db_config->tiempo_cancelacion,
                'fecha' => $data['fecha'] ?? $db_config->fecha,
                'dia_semana' => $data['dia_semana'] ?? $db_config->dia_semana,
                'actualizado_por' => 2, // auth()->id(),
            ];

            $db_config->update($updateData);

            if (!isset($data['franjas_horarias']) && !is_array($data['franjas_horarias'])) {
                DB::commit();
                return $db_config->load('franjas_horarias');
            }

            $db_config->franjas_horarias()->delete();

            $franjasHorarias = [];
            foreach ($data['franjas_horarias'] as $franja) {
                $franjasHorarias[] = [
                    'id_config' => $db_config->id,
                    'hora_inicio' => $franja['hora_inicio'],
                    'hora_fin' => $franja['hora_fin'],
                    'valor' => $franja['valor'] ?? 0,
                    'activa' => $franja['activa'] ?? true,
                ];
            }

            if (!empty($franjasHorarias)) {
                $db_config->franjas_horarias()->createMany($franjasHorarias);
            }

            DB::commit();
            return $db_config->load('franjas_horarias');
        } catch (EspacioConfiguracionException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar configuracion del espacio', $e, ['id' => $id]);

            throw new EspacioConfiguracionException(
                'Error al actualizar el la configuracion del espacio: ' . $e->getMessage(),
                'update_failed',
                500,
                $e,
            );
        }
    }

    private function logError(string $message, Exception $exception, array $context = []): void
    {
        Log::error($message, array_merge($context, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]));
    }
}
