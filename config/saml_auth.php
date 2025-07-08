<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración SAML personalizada
    |--------------------------------------------------------------------------
    |
    | Configuraciones adicionales para el manejo de autenticación SAML
    |
    */

    // Prevención de ataques de replay
    'prevent_replay_attacks' => env('SAML_PREVENT_REPLAY', true),
    'replay_cache_ttl' => env('SAML_REPLAY_CACHE_TTL', 24 * 60), // 24 horas en minutos

    // Mapeo de atributos SAML a campos de usuario
    'attribute_mapping' => [
        'email' => ['email', 'emailAddress', 'mail', 'Email'],
        'name' => ['name', 'displayName', 'cn', 'fullName'],
        'first_name' => ['firstName', 'givenName', 'FirstName'],
        'last_name' => ['lastName', 'surname', 'LastName'],
        'groups' => ['groups', 'memberOf', 'Groups'],
        'department' => ['department', 'Department', 'ou'],
        'employee_id' => ['employeeId', 'employeeNumber', 'EmployeeID'],
    ],

    // Configuración de roles
    'role_mapping' => [
        'admin_groups' => ['admin', 'administrators', 'Admin'],
        'teacher_groups' => ['teacher', 'teachers', 'profesor', 'profesores', 'faculty'],
        'student_groups' => ['student', 'students', 'estudiante', 'estudiantes'],
    ],

    // Configuración de usuario por defecto
    'default_user_config' => [
        'tipo_usuario' => 'saml',
        'activo' => true,
        'id_rol' => 3, // Rol por defecto (ajustar según tu tabla de roles)
    ],

    // Redirecciones
    'redirect_after_login' => env('SAML_REDIRECT_AFTER_LOGIN', '/dashboard'),
    'redirect_after_logout' => env('SAML_REDIRECT_AFTER_LOGOUT', '/'),

    // Logging
    'log_saml_events' => env('SAML_LOG_EVENTS', true),
    'log_user_data' => env('SAML_LOG_USER_DATA', false), // Cuidado con datos sensibles

    // Validaciones adicionales
    'required_attributes' => ['email'], // Atributos obligatorios
    'validate_email_domain' => env('SAML_VALIDATE_EMAIL_DOMAIN', false),
    'allowed_email_domains' => explode(',', env('SAML_ALLOWED_DOMAINS', '')),

    // Configuración de sesión
    'session_lifetime' => env('SAML_SESSION_LIFETIME', 120), // minutos
    'remember_me' => env('SAML_REMEMBER_ME', false),
];
