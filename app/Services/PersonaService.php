<?php

namespace App\Services;

use App\Exceptions\UsuarioException;
use App\Models\Persona;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonaService
{
    // Constante para tipo de documento por defecto
    private const DEFAULT_TIPO_DOCUMENTO_ID = 1; // CC - Cédula de Ciudadanía

    /**
     * Obtener todas las personas paginadas con búsqueda
     */
    public function getAll(int $perPage = 15, string $search = ''): LengthAwarePaginator
    {
        $search = (string) $search;

        return Persona::with(['tipoDocumento', 'regimenTributario', 'ciudadExpedicion', 'ciudadResidencia', 'usuario'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(primer_nombre) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(segundo_nombre) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(primer_apellido) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(segundo_apellido) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(numero_documento) LIKE ?', ['%' . $search . '%']);
                });
            })
            ->orderBy('id_persona', 'desc')
            ->paginate($perPage);
    }

    /**
     * Obtener una persona por ID
     */
    public function getById(int $id): Persona
    {
        $persona = Persona::with(['tipoDocumento', 'regimenTributario', 'ciudadExpedicion', 'ciudadResidencia', 'usuario'])
            ->find($id);

        if (!$persona) {
            throw new Exception("Persona no encontrada con ID: {$id}", 404);
        }

        return $persona;
    }

    /**
     * Obtener una persona por número de documento
     */
    public function getByDocument(string $numeroDocumento): ?Persona
    {
        return Persona::with(['tipoDocumento', 'regimenTributario', 'ciudadExpedicion', 'ciudadResidencia', 'usuario'])
            ->where('numero_documento', $numeroDocumento)
            ->first();
    }

    /**
     * Crear una nueva persona
     */
    public function create(array $data): Persona
    {
        try {
            DB::beginTransaction();

            // Procesar datos de nombre y apellido si vienen en formato concatenado
            $this->processNombreApellido($data);

            // Procesar fecha de nacimiento
            $this->processFechaNacimiento($data);

            // Establecer valores por defecto
            if (!isset($data['tipo_documento_id'])) {
                $data['tipo_documento_id'] = self::DEFAULT_TIPO_DOCUMENTO_ID;
            }

            if (!isset($data['tipo_persona'])) {
                $data['tipo_persona'] = 'natural';
            }

            $persona = Persona::create($data);

            DB::commit();

            return $persona->load(['tipoDocumento', 'regimenTributario', 'ciudadExpedicion', 'ciudadResidencia']);
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al crear persona', $e, $data);

            throw new Exception(
                'Error al crear la persona: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Actualizar una persona existente
     */
    public function update(int $id, array $data): Persona
    {
        try {
            DB::beginTransaction();

            $persona = $this->getById($id);

            // Procesar datos de nombre y apellido si vienen en formato concatenado
            $this->processNombreApellido($data);

            // Procesar fecha de nacimiento
            $this->processFechaNacimiento($data);

            $persona->update($data);

            DB::commit();

            return $persona->load(['tipoDocumento', 'regimenTributario', 'ciudadExpedicion', 'ciudadResidencia']);
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar persona', $e, ['id' => $id, 'data' => $data]);

            throw new Exception(
                'Error al actualizar la persona: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Eliminar una persona
     */
    public function delete(int $id): bool
    {
        try {
            $persona = $this->getById($id);

            // Verificar si tiene usuario asociado
            if ($persona->usuario) {
                throw new Exception(
                    'No se puede eliminar la persona porque tiene un usuario asociado',
                    400
                );
            }

            return $persona->delete();
        } catch (Exception $e) {
            $this->logError('Error al eliminar persona', $e, ['id' => $id]);
            throw $e;
        }
    }

    /**
     * Procesar nombre y apellido cuando vienen concatenados
     */
    private function processNombreApellido(array &$data): void
    {
        if (isset($data['nombre']) && !isset($data['primer_nombre'])) {
            $nombres = explode(' ', trim($data['nombre']), 2);
            $data['primer_nombre'] = $nombres[0];
            $data['segundo_nombre'] = $nombres[1] ?? null;
            unset($data['nombre']);
        }

        if (isset($data['apellido']) && !isset($data['primer_apellido'])) {
            $apellidos = explode(' ', trim($data['apellido']), 2);
            $data['primer_apellido'] = $apellidos[0];
            $data['segundo_apellido'] = $apellidos[1] ?? null;
            unset($data['apellido']);
        }
    }

    /**
     * Procesar fecha de nacimiento
     */
    private function processFechaNacimiento(array &$data): void
    {
        if (isset($data['fechaNacimiento']) && !isset($data['fecha_nacimiento'])) {
            $data['fecha_nacimiento'] = $data['fechaNacimiento'];
            unset($data['fechaNacimiento']);
        }

        if (isset($data['fecha_nacimiento']) && is_string($data['fecha_nacimiento'])) {
            try {
                $data['fecha_nacimiento'] = Carbon::parse($data['fecha_nacimiento']);
            } catch (Exception $e) {
                Log::warning('Error al parsear fecha de nacimiento', [
                    'fecha' => $data['fecha_nacimiento'],
                    'error' => $e->getMessage()
                ]);
                unset($data['fecha_nacimiento']);
            }
        }
    }

    /**
     * Verificar si una persona puede realizar facturación
     */
    public function puedeFacturar(Persona $persona): bool
    {
        // Para facturación se requiere al menos el tipo de persona
        return !empty($persona->tipo_persona);
    }

    /**
     * Obtener datos de facturación de una persona
     */
    public function getDatosFacturacion(Persona $persona): array
    {
        // Si la persona tiene un registro de facturación asociado, usar ese; de lo contrario, usar la persona titular
        $persona->load(['regimenTributario', 'ciudadExpedicion', 'ciudadResidencia', 'personasFacturacion']);
        $fact = $persona->personasFacturacion->first() ?? $persona;

        return [
            'id_persona' => $fact->id_persona,
            'tipo_persona' => $fact->tipo_persona,
            'regimen_tributario' => $fact->regimenTributario?->nombre,
            'ciudad_expedicion' => $fact->ciudadExpedicion?->nombre,
            'ciudad_residencia' => $fact->ciudadResidencia?->nombre,
            'direccion' => $fact->direccion,
            'numero_documento' => $fact->numero_documento,
            'tipo_documento_id' => $fact->tipo_documento_id,
            'es_persona_facturacion' => (bool)($fact->es_persona_facturacion ?? false),
            'persona_facturacion_id' => $fact->persona_facturacion_id,
            'puede_facturar' => $this->puedeFacturar($fact)
        ];
    }

    public function filtrarPersonasFacturacion(
        int $perPage = 15,
        ?string $numeroDocumento = null,
        ?int $tipoDocumentoId = null
    ) {
        $numeroDocumento = $numeroDocumento !== null ? trim($numeroDocumento) : null;

        return Persona::with(['tipoDocumento', 'regimenTributario', 'ciudadExpedicion', 'ciudadResidencia', 'usuario'])
            ->when($numeroDocumento, function ($query) use ($numeroDocumento) {
                $query->whereRaw('LOWER(numero_documento) LIKE ?', ['%' . strtolower($numeroDocumento) . '%']);
            })
            ->when($tipoDocumentoId, function ($query) use ($tipoDocumentoId) {
                $query->where('tipo_documento_id', $tipoDocumentoId);
            })
            ->orderBy('id_persona', 'desc')
            ->first();
    }

    /**
     * Log de errores
     */
    private function logError(string $message, Exception $exception, array $context = []): void
    {
        Log::error($message, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
        ]);
    }
}
