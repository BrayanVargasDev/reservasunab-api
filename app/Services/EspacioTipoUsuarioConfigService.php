<?php

namespace App\Services;

use App\Exceptions\EspacioTipoUsuarioConfigException;
use App\Models\EspacioTipoUsuarioConfig;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EspacioTipoUsuarioConfigService
{

    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $dataCreate = [
                'id_espacio' => $data['id_espacio'] ?? null,
                'tipo_usuario' => $data['tipo_usuario'] ?? null,
                'porcentaje_descuento' => $data['porcentaje_descuento'] ?? 0,
                'retraso_reserva' => $data['minutos_retraso'] ?? 0,
                'creado_en' => $data['creado_en'] ?? now(),
                'creado_por' => 2, // auth()->id(),
            ];

            $tipoUsuarioConfig = EspacioTipoUsuarioConfig::create($dataCreate);
            DB::commit();
            return $tipoUsuarioConfig;
        } catch (EspacioTipoUsuarioConfigException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al crear config', $e, [
                'data' => $data,
            ]);

            throw new EspacioTipoUsuarioConfigException(
                'Error al actualizar el espacio: ' . $e->getMessage(),
                'create_tipo_usuario_config_failed',
                500,
                $e
            );
        }
    }

    public function getById(int|string $id)
    {
        if (!is_numeric($id) || intval($id) != $id) {
            throw new EspacioTipoUsuarioConfigException(
                'El ID del espacio debe ser un número entero',
                'invalid_id_format',
                400,
            );
        }

        try {
            return EspacioTipoUsuarioConfig::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new EspacioTipoUsuarioConfigException(
                "Espacio no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        }
    }

    public function update(int $id, array $data): EspacioTipoUsuarioConfig
    {
        try {
            DB::beginTransaction();

            $config = $this->getById($id);

            $updateData = [
                'retraso_reserva' => $data['minutos_retraso'] ?? $config->retraso_reserva,
                'porcentaje_descuento' => $data['porcentaje_descuento'] ?? $config->porcentaje_descuento,
                'actualizado_por' => 2, // auth()->id(),
                'actualizado_en' => now(),
            ];

            $config->update($updateData);
            DB::commit();

            return $config;
        } catch (EspacioTipoUsuarioConfigException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar config', $e, ['id' => $id]);

            throw new EspacioTipoUsuarioConfigException(
                'Error al actualizar el config: ' . $e->getMessage(),
                'update_failed',
                500,
                $e,
            );
        }
    }

    public function delete(int $id): void
    {
        try {
            DB::beginTransaction();
            $config = $this->getById($id);
            $config->delete();
            DB::commit();
        } catch (EspacioTipoUsuarioConfigException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al eliminar config', $e, ['id' => $id]);
            throw new EspacioTipoUsuarioConfigException(
                'Error al eliminar el config: ' . $e->getMessage(),
                'delete_failed',
                500,
                $e,
            );
        }
    }

    public function restore(int $id): EspacioTipoUsuarioConfig
    {
        try {
            DB::beginTransaction();
            $config = EspacioTipoUsuarioConfig::withTrashed()->findOrFail($id);
            $config->restore();
            DB::commit();
            return $config;
        } catch (ModelNotFoundException $e) {
            throw new EspacioTipoUsuarioConfigException(
                "Config no encontrado con ID: {$id}",
                'not_found',
                404,
                $e,
            );
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al restaurar config', $e, ['id' => $id]);
            throw new EspacioTipoUsuarioConfigException(
                'Error al restaurar el config: ' . $e->getMessage(),
                'restore_failed',
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
