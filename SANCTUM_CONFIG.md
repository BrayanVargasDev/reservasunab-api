# Configuración de Laravel Sanctum - API de Autenticación

## Descripción General

Laravel Sanctum está configurado para proporcionar autenticación de API mediante tokens personales de acceso. Esta configuración incluye:

- Autenticación basada en tokens con expiración
- Middleware de verificación de tokens
- Limpieza automática de tokens expirados
- Manejo seguro de CORS

## Endpoints de Autenticación

### 1. Registro de Usuario
```http
POST /api/registrar
Content-Type: application/json

{
    "nombre": "Juan",
    "apellido": "Pérez", 
    "celular": "3001234567",
    "email": "juan@ejemplo.com",
    "password": "contraseña123"
}
```

**Respuesta exitosa:**
```json
{
    "status": "success",
    "message": "Usuario registrado correctamente.",
    "data": {
        "id": 1,
        "email": "juan@ejemplo.com",
        "nombre": "Juan",
        "apellido": "Pérez",
        "token": "1|abc123def456..."
    }
}
```

### 2. Inicio de Sesión
```http
POST /api/ingresar
Content-Type: application/json

{
    "email": "juan@ejemplo.com",
    "password": "contraseña123"
}
```

**Respuesta exitosa:**
```json
{
    "status": "success",
    "message": "Login exitoso.",
    "data": {
        "id": 1,
        "email": "juan@ejemplo.com",
        "nombre": "Juan",
        "apellido": "Pérez",
        "tipo_usuario": "externo",
        "activo": true,
        "token": "1|abc123def456...",
        "token_expires_at": "2025-07-02T10:30:00.000000Z",
        "permisos": ["read_espacios", "create_reservas"]
    }
}
```

### 3. Obtener Usuario Autenticado
```http
GET /api/me
Authorization: Bearer 1|abc123def456...
```

**Respuesta exitosa:**
```json
{
    "status": "success",
    "message": "Usuario autenticado correctamente.",
    "data": {
        "id": 1,
        "email": "juan@ejemplo.com",
        "nombre": "Juan",
        "apellido": "Pérez",
        "tipo_usuario": "externo",
        "activo": true,
        "token_expires_at": "2025-07-02T10:30:00.000000Z",
        "permisos": ["read_espacios", "create_reservas"]
    }
}
```

### 4. Refrescar Token
```http
POST /api/refresh-token
Authorization: Bearer 1|abc123def456...
```

**Respuesta exitosa:**
```json
{
    "status": "success",
    "message": "Token refrescado correctamente.",
    "data": {
        "id": 1,
        "email": "juan@ejemplo.com",
        "nombre": "Juan",
        "apellido": "Pérez",
        "tipo_usuario": "externo",
        "activo": true,
        "token": "1|new789token123...",
        "token_expires_at": "2025-07-02T10:30:00.000000Z",
        "permisos": ["read_espacios", "create_reservas"]
    }
}
```

### 5. Cerrar Sesión
```http
POST /api/logout
Authorization: Bearer 1|abc123def456...
```

**Respuesta exitosa:**
```json
{
    "status": "success",
    "message": "Sesión cerrada correctamente."
}
```

## Configuración del Cliente (Frontend)

### JavaScript/Vue.js/React
```javascript
// Configurar axios para incluir el token en todas las requests
axios.defaults.baseURL = 'http://localhost:8000/api';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

// Interceptor para incluir token automáticamente
axios.interceptors.request.use(config => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Interceptor para manejar tokens expirados
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401 && 
            error.response?.data?.error_code === 'TOKEN_EXPIRED') {
            // Token expirado, redirigir a login
            localStorage.removeItem('auth_token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);
```

### Ejemplo de uso completo
```javascript
// Login
async function login(email, password) {
    try {
        const response = await axios.post('/ingresar', {
            email,
            password
        });
        
        if (response.data.status === 'success') {
            const token = response.data.data.token;
            localStorage.setItem('auth_token', token);
            localStorage.setItem('user_data', JSON.stringify(response.data.data));
            return response.data.data;
        }
    } catch (error) {
        console.error('Error en login:', error.response?.data);
        throw error;
    }
}

// Hacer una request autenticada
async function getMyProfile() {
    try {
        const response = await axios.get('/me');
        return response.data.data;
    } catch (error) {
        console.error('Error obteniendo perfil:', error.response?.data);
        throw error;
    }
}

// Logout
async function logout() {
    try {
        await axios.post('/logout');
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
    } catch (error) {
        console.error('Error en logout:', error.response?.data);
        // Limpiar datos locales aunque falle la request
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
    }
}
```

## Variables de Entorno Importantes

```env
# Configuración de Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,localhost:8100,127.0.0.1,127.0.0.1:3000,127.0.0.1:8100
SANCTUM_TOKEN_EXPIRATION=1440
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
FRONTEND_URL=http://localhost:8100
```

## Comandos Artisan Útiles

```bash
# Limpiar tokens expirados manualmente
php artisan sanctum:clean-expired-tokens

# Limpiar tokens más antiguos de X días
php artisan sanctum:clean-expired-tokens --days=7

# Ver estado de migraciones de Sanctum
php artisan migrate:status | grep personal_access_tokens
```

## Middleware Personalizado

### CustomSanctumAuth
Maneja la autenticación tanto para requests stateful (frontend con cookies) como para API tokens.

### VerifyTokenExpiration
Verifica automáticamente si los tokens han expirado y los elimina si es necesario.

## Seguridad

1. **Expiración de Tokens**: Los tokens expiran automáticamente después de 24 horas (configurable).
2. **Limpieza Automática**: Los tokens expirados se limpian diariamente a las 2:00 AM.
3. **CORS Configurado**: Solo permite requests desde dominios específicos en producción.
4. **Verificación Activa**: Middleware verifica la expiración en cada request.

## Manejo de Errores

### Token Expirado
```json
{
    "status": "error",
    "message": "Token expirado. Por favor, inicie sesión nuevamente.",
    "error_code": "TOKEN_EXPIRED"
}
```

### Usuario No Autenticado
```json
{
    "status": "error",
    "message": "Usuario no autenticado."
}
```

### Credenciales Incorrectas
```json
{
    "status": "error",
    "message": "El usuario o la contraseña son incorrectos."
}
```

## Consideraciones de Producción

1. **HTTPS**: Usar siempre HTTPS en producción.
2. **Dominios Stateful**: Configurar solo los dominios necesarios.
3. **Expiración**: Ajustar el tiempo de expiración según las necesidades.
4. **Logs**: Monitorear los logs para detectar intentos de acceso maliciosos.
5. **Rate Limiting**: Implementar rate limiting en endpoints de autenticación.
