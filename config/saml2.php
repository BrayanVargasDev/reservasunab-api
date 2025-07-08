<?php

return [
    'tenantModel' => \Slides\Saml2\Models\Tenant::class,
    'useRoutes' => true,
    'routesPrefix' => '/saml',
    'routesMiddleware' => ['web'],
    'retrieveParametersFromServer' => false,
    'loginRoute' => null,
    'logoutRoute' => null,
    'errorRoute' => null,
    'baseUrl' => env('APP_URL') . '',
    'strict' => true,
    'debug' => env('SAML2_DEBUG', env('APP_DEBUG', false)),
    'proxyVars' => true,
    'security' => [
        'nameIdEncrypted' => false,
        'authnRequestsSigned' => false,
        'logoutRequestSigned' => false,
        'logoutResponseSigned' => false,
        'signMetadata' => false,
        'wantMessagesSigned' => false,
        'wantAssertionsSigned' => false,
        'wantNameIdEncrypted' => false,
        'requestedAuthnContext' => true,
    ],
    'contactPerson' => [
        'technical' => [
            'givenName' => 'WG Soluciones',
            'emailAddress' => 'soporte@wgsoluciones.com'
        ],
        'support' => [
            'givenName' => 'WG Soporte',
            'emailAddress' => 'soporte@wgsoluciones.com'
        ],
    ],
    'organization' => [
        'en-US' => [
            'name' => 'WG Soluciones',
            'displayname' => 'WG Soluciones',
            'url' => 'https://wgsoluciones.com',
        ],
    ],
    'load_migrations' => true,
];
