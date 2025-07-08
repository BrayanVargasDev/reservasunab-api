<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SamlAuthService
{
    /**
     * Procesar autenticación SAML2
     */
    public function handleSamlSignIn($event)
    {
        try {
            $messageId = $event->auth->getLastMessageId();

            // Prevenir ataques de replay verificando si el messageId ya fue usado
            if ($this->isMessageIdUsed($messageId)) {
                Log::warning('Intento de reutilización de messageId detectado', ['messageId' => $messageId]);
                return false;
            }

            // Marcar messageId como usado
            $this->markMessageIdAsUsed($messageId);

            $samlUser = $event->auth->getSaml2User();
            $userData = [
                'id' => $samlUser->getUserId(),
                'attributes' => $samlUser->getAttributes(),
                'assertion' => $samlUser->getRawSamlAssertion()
            ];

            Log::info('SAML2 SignedIn event triggered', ['userData' => $userData]);

            // Buscar o crear usuario basado en los datos SAML
            $user = $this->findOrCreateUser($userData);

            if ($user) {
                // Autenticar al usuario
                Auth::login($user);
                Log::info('Usuario autenticado exitosamente via SAML2', ['user_id' => $user->id_usuario]);

                // Disparar evento personalizado
                event(new \App\Events\SamlAuth($user, $userData, 'login'));

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error en autenticación SAML2: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verificar si un messageId ya fue usado
     */
    private function isMessageIdUsed($messageId)
    {
        $cacheKey = "saml_message_id_{$messageId}";
        return Cache::has($cacheKey);
    }

    /**
     * Marcar messageId como usado (cache por 24 horas)
     */
    private function markMessageIdAsUsed($messageId)
    {
        $cacheKey = "saml_message_id_{$messageId}";
        Cache::put($cacheKey, true, now()->addHours(24));
    }

    /**
     * Buscar o crear usuario basado en datos SAML
     */
    private function findOrCreateUser($userData)
    {
        $attributes = $userData['attributes'];

        // Extraer datos del usuario SAML (ajusta según tu configuración SAML)
        $email = $this->extractEmail($attributes, $userData['id']);
        $name = $this->extractName($attributes);

        if (!$email) {
            Log::error('No se pudo obtener email del usuario SAML', ['userData' => $userData]);
            return null;
        }

        // Buscar usuario existente por email o ldap_uid
        $user = Usuario::where('email', $email)
                      ->orWhere('ldap_uid', $userData['id'])
                      ->first();

        if (!$user) {
            // Crear nuevo usuario si no existe
            $user = $this->createSamlUser($email, $userData['id'], $name, $attributes);
            Log::info('Nuevo usuario creado via SAML2', ['user_id' => $user->id_usuario, 'email' => $email]);
        } else {
            // Actualizar datos del usuario existente
            $this->updateSamlUser($user, $email, $userData['id'], $name, $attributes);
            Log::info('Usuario existente actualizado para SAML2', ['user_id' => $user->id_usuario, 'email' => $email]);
        }

        return $user;
    }

    /**
     * Extraer email de los atributos SAML
     */
    private function extractEmail($attributes, $userId)
    {
        // Posibles atributos donde puede estar el email
        $emailFields = ['email', 'emailAddress', 'mail', 'Email', 'EmailAddress'];

        foreach ($emailFields as $field) {
            if (isset($attributes[$field]) && !empty($attributes[$field])) {
                return is_array($attributes[$field]) ? $attributes[$field][0] : $attributes[$field];
            }
        }

        // Si no se encuentra email en atributos, usar userId si parece un email
        if (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
            return $userId;
        }

        return null;
    }

    /**
     * Extraer nombre de los atributos SAML
     */
    private function extractName($attributes)
    {
        // Posibles atributos donde puede estar el nombre
        $nameFields = ['name', 'displayName', 'cn', 'fullName', 'Name', 'DisplayName'];

        foreach ($nameFields as $field) {
            if (isset($attributes[$field]) && !empty($attributes[$field])) {
                return is_array($attributes[$field]) ? $attributes[$field][0] : $attributes[$field];
            }
        }

        // Intentar combinar firstName y lastName
        $firstName = $attributes['firstName'][0] ?? $attributes['givenName'][0] ?? '';
        $lastName = $attributes['lastName'][0] ?? $attributes['surname'][0] ?? '';

        if ($firstName || $lastName) {
            return trim($firstName . ' ' . $lastName);
        }

        return null;
    }

    /**
     * Crear nuevo usuario SAML
     */
    private function createSamlUser($email, $ldapUid, $name, $attributes)
    {
        return Usuario::create([
            'email' => $email,
            'ldap_uid' => $ldapUid,
            'tipo_usuario' => 'saml',
            'activo' => true,
            // Agrega otros campos según tus necesidades y la estructura de tu tabla
            // 'nombre' => $name,
            // 'id_rol' => $this->determineUserRole($attributes),
        ]);
    }

    /**
     * Actualizar usuario SAML existente
     */
    private function updateSamlUser($user, $email, $ldapUid, $name, $attributes)
    {
        $updateData = [];

        // Actualizar email si cambió
        if ($user->email !== $email) {
            $updateData['email'] = $email;
        }

        // Actualizar ldap_uid si no existe
        if (!$user->ldap_uid) {
            $updateData['ldap_uid'] = $ldapUid;
        }

        // Asegurar que el usuario esté activo
        if (!$user->activo) {
            $updateData['activo'] = true;
        }

        // Actualizar nombre si cambió (si tienes este campo)
        // if ($name && isset($user->nombre) && $user->nombre !== $name) {
        //     $updateData['nombre'] = $name;
        // }

        if (!empty($updateData)) {
            $user->update($updateData);
        }
    }

    /**
     * Determinar rol del usuario basado en atributos SAML
     */
    private function determineUserRole($attributes)
    {
        // Implementa lógica para determinar el rol basado en atributos SAML
        // Por ejemplo, basado en grupos, departamento, etc.

        $groups = $attributes['groups'] ?? $attributes['memberOf'] ?? [];

        // Ejemplo de lógica de roles
        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (strpos(strtolower($group), 'admin') !== false) {
                    return 1; // ID del rol admin
                }
                if (strpos(strtolower($group), 'teacher') !== false || strpos(strtolower($group), 'profesor') !== false) {
                    return 2; // ID del rol profesor
                }
            }
        }

        return 3; // ID del rol por defecto (estudiante)
    }
}
