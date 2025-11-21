# Arquitectura de Autenticación y Autorización

## 📋 Índice
1. [Visión General](#visión-general)
2. [Flujo de Autenticación](#flujo-de-autenticación)
3. [Sistema de Roles y Permisos](#sistema-de-roles-y-permisos)
4. [Backend (Laravel)](#backend-laravel)
5. [Frontend (React)](#frontend-react)
6. [Mejores Prácticas](#mejores-prácticas)
7. [Optimizaciones](#optimizaciones)

---

## 🎯 Visión General

El sistema utiliza **JWT (JSON Web Tokens)** para autenticación stateless, con un sistema granular de roles y permisos basado en:

- **Roles**: ADMIN, DOCENTE, ESTUDIANTE
- **Permisos**: Control granular por módulo y acción (ver, crear, editar, eliminar)

### Stack Tecnológico
- **Backend**: Laravel 11 + JWT Auth (tymon/jwt-auth)
- **Frontend**: React + Vite + Axios + Context API
- **Base de Datos**: MySQL/PostgreSQL

---

## 🔐 Flujo de Autenticación

### 1. Login (Backend)

#### Para Administradores/Docentes
```
POST /api/auth/admin/login
Body: { email: string, password: string } o { ci: string, password: string }
```

**Proceso:**
1. Validar credenciales (email/CI + password)
2. Buscar usuario en tabla `usuario`
3. Verificar que tenga rol ADMIN o DOCENTE
4. Cargar relaciones: `rol.permisos`, `persona`
5. Generar JWT con custom claims:
   ```php
   [
     'rol' => 'ADMIN' | 'DOCENTE',
     'rol_id' => int,
     'usuario_id' => int,
     'persona_id' => int,
     'email' => string
   ]
   ```
6. Retornar token + datos del usuario + permisos

#### Para Estudiantes
```
POST /api/auth/estudiante/login
Body: { ci: string, password: string }
```

**Proceso:**
1. Validar CI + password
2. Buscar estudiante por CI
3. Obtener usuario asociado a la persona del estudiante
4. Verificar password
5. Generar JWT (el modelo Estudiante también implementa JWTSubject)
6. Retornar token + datos del estudiante

### 2. Protección de Rutas (Backend)

#### Middleware de Roles
```php
Route::middleware(['auth:api', 'role:ADMIN,DOCENTE'])->group(function () {
    // Rutas compartidas
});
```

**RoleMiddleware** verifica:
1. Usuario autenticado (JWT válido)
2. Rol del usuario está en los roles permitidos
3. Extrae rol del JWT payload (más rápido) o del modelo

#### Middleware de Permisos
```php
Route::middleware(['auth:api', 'permission:estudiantes_ver,estudiantes_editar'])->group(function () {
    // Rutas con permisos específicos
});
```

**PermissionMiddleware** verifica:
1. Usuario autenticado
2. Si es ADMIN → acceso completo (bypass)
3. Si no es ADMIN → verificar que tenga al menos uno de los permisos solicitados
4. Carga permisos del rol solo si es necesario

### 3. Autenticación en Frontend

#### Flujo Completo
```
1. Usuario ingresa credenciales → Login Component
2. authService.login() → POST /api/auth/admin/login o /api/auth/estudiante/login
3. Backend retorna { token, user, permisos }
4. Guardar token en localStorage
5. AuthContext actualiza estado global
6. Interceptor de Axios agrega token automáticamente a todas las peticiones
7. ProtectedRoute verifica autenticación antes de renderizar
8. RoleBasedRoute verifica rol antes de permitir acceso
```

#### Manejo de Token
- **Almacenamiento**: `localStorage.getItem('token')`
- **Inyección automática**: Interceptor de Axios agrega `Authorization: Bearer {token}`
- **Refresh**: Endpoint `/api/auth/refresh` (si se implementa)
- **Expiración**: Manejo automático de 401 → logout + redirect a login

---

## 👥 Sistema de Roles y Permisos

### Roles del Sistema

| Rol | Descripción | Acceso |
|-----|-------------|--------|
| **ADMIN** | Administrador del sistema | Acceso completo + bypass de permisos |
| **DOCENTE** | Docente/Profesor | Sus grupos, estudiantes, calificaciones |
| **ESTUDIANTE** | Estudiante | Sus materias, calificaciones, documentos, pagos |

### Estructura de Permisos

Los permisos siguen el formato: `{modulo}_{accion}`

**Ejemplos:**
- `estudiantes_ver` - Ver lista de estudiantes
- `estudiantes_crear` - Crear nuevo estudiante
- `estudiantes_editar` - Editar estudiante existente
- `estudiantes_eliminar` - Eliminar estudiante
- `roles_ver` - Ver roles
- `roles_asignar_permisos` - Asignar permisos a roles

### Tablas de Base de Datos

```sql
-- Tabla de roles
roles (
  rol_id (PK),
  nombre_rol (UNIQUE), -- 'ADMIN', 'DOCENTE', 'ESTUDIANTE'
  descripcion,
  activo (boolean)
)

-- Tabla de permisos
permisos (
  permiso_id (PK),
  nombre_permiso (UNIQUE), -- 'estudiantes_ver', 'roles_crear', etc.
  modulo, -- 'estudiantes', 'roles', 'pagos', etc.
  accion, -- 'ver', 'crear', 'editar', 'eliminar'
  descripcion,
  activo (boolean)
)

-- Tabla pivot rol_permiso
rol_permiso (
  rol_id (FK),
  permiso_id (FK),
  activo (boolean)
)

-- Tabla usuario (ya existente)
usuario (
  usuario_id (PK),
  email,
  password,
  persona_id (FK),
  rol_id (FK) -- Relación con roles
)
```

---

## 🔧 Backend (Laravel)

### Modelos

#### Usuario.php
```php
class Usuario extends Authenticatable implements JWTSubject
{
    // Relación con rol
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'rol_id');
    }
    
    // Métodos JWT
    public function getJWTIdentifier() { return $this->getKey(); }
    
    public function getJWTCustomClaims() {
        return [
            'rol' => $this->rol ? $this->rol->nombre_rol : 'ADMIN',
            'email' => $this->email,
            'persona_id' => $this->persona_id,
            'rol_id' => $this->rol_id
        ];
    }
    
    // Verificar permisos
    public function tienePermiso(string $permiso): bool
    {
        if (!$this->rol) return false;
        return $this->rol->tienePermiso($permiso);
    }
}
```

#### Rol.php
```php
class Rol extends Model
{
    // Relación many-to-many con permisos
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', ...)
                    ->wherePivot('activo', true);
    }
    
    // Verificar permiso
    public function tienePermiso(string $permiso): bool
    {
        return $this->permisos()
            ->where('nombre_permiso', $permiso)
            ->exists();
    }
}
```

### Middlewares

#### RoleMiddleware
```php
public function handle(Request $request, Closure $next, ...$roles): Response
{
    $user = auth('api')->user();
    
    if (!$user) {
        return response()->json(['message' => 'No autenticado'], 401);
    }
    
    // Obtener rol del JWT (más rápido)
    $payload = JWTAuth::parseToken()->getPayload();
    $userRole = $payload->get('rol');
    
    // Si no está en JWT, cargar del modelo
    if (!$userRole) {
        $user->load('rol');
        $userRole = $user->rol->nombre_rol ?? null;
    }
    
    // Verificar rol
    if (!in_array($userRole, $roles)) {
        return response()->json(['message' => 'Acceso denegado'], 403);
    }
    
    return $next($request);
}
```

#### PermissionMiddleware
```php
public function handle(Request $request, Closure $next, ...$permissions): Response
{
    $user = auth('api')->user();
    
    // ADMIN tiene acceso completo
    $payload = JWTAuth::parseToken()->getPayload();
    $userRole = $payload->get('rol');
    
    if ($userRole === 'ADMIN') {
        return $next($request);
    }
    
    // Verificar permisos
    $user->load('rol.permisos');
    $tienePermiso = false;
    
    foreach ($permissions as $permiso) {
        if ($user->tienePermiso($permiso)) {
            $tienePermiso = true;
            break;
        }
    }
    
    if (!$tienePermiso) {
        return response()->json(['message' => 'Sin permisos'], 403);
    }
    
    return $next($request);
}
```

### Rutas API

```php
// Públicas
Route::prefix('auth')->group(function () {
    Route::post('/admin/login', [AutenticacionAdminController::class, 'iniciarSesion']);
    Route::post('/estudiante/login', [AutenticacionEstudianteController::class, 'iniciarSesion']);
    Route::post('/logout', [AutenticacionEstudianteController::class, 'cerrarSesion']);
    
    Route::middleware('auth:api')->group(function () {
        Route::post('/refresh', [AutenticacionEstudianteController::class, 'refrescarToken']);
        Route::get('/perfil', [AutenticacionEstudianteController::class, 'obtenerPerfil']);
    });
});

// Protegidas por rol
Route::middleware(['auth:api', 'role:ADMIN'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [PanelAdminController::class, 'obtenerDashboard']);
    // ...
});

Route::middleware(['auth:api', 'role:ESTUDIANTE'])->prefix('estudiante')->group(function () {
    Route::get('/dashboard', [PanelEstudianteController::class, 'obtenerDashboard']);
    // ...
});

// Protegidas por permisos
Route::middleware(['auth:api', 'permission:estudiantes_ver'])->get('/admin/estudiantes', ...);
```

---

## ⚛️ Frontend (React)

### Estructura de Carpetas
```
src/
├── contexts/
│   └── AuthContext.jsx          # Contexto global de autenticación
├── services/
│   ├── api.js                    # Configuración de Axios
│   └── authService.js            # Servicio de autenticación
├── components/
│   └── auth/
│       ├── ProtectedRoute.jsx    # Ruta protegida (requiere auth)
│       └── RoleBasedRoute.jsx    # Ruta basada en roles
├── hooks/
│   └── usePermissions.js         # Hook para verificar permisos
├── utils/
│   ├── constants.js              # Constantes (roles, permisos)
│   └── roleUtils.js              # Utilidades para normalizar roles
└── routes/
    └── index.jsx                 # Configuración de rutas
```

### AuthContext

```jsx
// Estado global de autenticación
const [state, dispatch] = useReducer(authReducer, {
  user: null,
  token: null,
  isAuthenticated: false,
  loading: true
});

// Funciones principales
const login = async (credentials) => {
  const response = await authService.login(credentials);
  if (response.success) {
    localStorage.setItem('token', response.data.token);
    dispatch({ type: 'AUTH_SUCCESS', payload: response.data });
  }
};

const logout = async () => {
  await authService.logout();
  localStorage.removeItem('token');
  dispatch({ type: 'AUTH_LOGOUT' });
};

// Helpers de roles
const hasRole = (role) => {
  return normalizeRole(state.user?.rol) === normalizeRole(role);
};

const hasAnyRole = (roles) => {
  return roles.some(r => hasRole(r));
};
```

### Interceptor de Axios

```javascript
// Agregar token automáticamente
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Manejar errores 401
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
```

### ProtectedRoute

```jsx
function ProtectedRoute({ children, requiredRoles = [] }) {
  const { isAuthenticated, loading, user } = useAuth();
  
  if (loading) return <LoadingSpinner />;
  if (!isAuthenticated) return <Navigate to="/login" />;
  
  if (requiredRoles.length > 0) {
    const userRole = normalizeRole(user?.rol);
    if (!requiredRoles.some(r => normalizeRole(r) === userRole)) {
      return <AccessDenied />;
    }
  }
  
  return children;
}
```

---

## ✅ Mejores Prácticas

### Backend

1. **Siempre cargar relaciones antes de generar JWT**
   ```php
   $usuario->load('rol.permisos', 'persona');
   $token = JWTAuth::fromUser($usuario);
   ```

2. **Usar FormRequest para validación**
   ```php
   class LoginRequest extends FormRequest {
       public function rules() {
           return [
               'email' => 'required|email',
               'password' => 'required|min:6'
           ];
       }
   }
   ```

3. **Cachear permisos del rol** (opcional, para alta performance)
   ```php
   Cache::remember("rol_{$rolId}_permisos", 3600, function() use ($rol) {
       return $rol->permisos->pluck('nombre_permiso')->toArray();
   });
   ```

4. **Logging de accesos denegados**
   ```php
   Log::warning('Access denied', [
       'user_id' => $user->id,
       'required_role' => $roles,
       'user_role' => $userRole
   ]);
   ```

### Frontend

1. **Normalizar roles** (backend usa mayúsculas, frontend puede usar minúsculas)
   ```javascript
   export const normalizeRole = (role) => {
     if (!role) return null;
     return role.toUpperCase();
   };
   ```

2. **Verificar permisos en componentes**
   ```jsx
   const { can } = usePermissions();
   
   {can('estudiantes_crear') && (
     <Button onClick={handleCreate}>Crear Estudiante</Button>
   )}
   ```

3. **Manejo centralizado de errores**
   ```javascript
   // En api.js interceptor
   const errorMessage = error.response?.data?.message || 'Error desconocido';
   toast.error(errorMessage);
   ```

4. **Lazy loading de rutas protegidas**
   ```jsx
   const Dashboard = lazy(() => import('../pages/Dashboard'));
   
   <Suspense fallback={<LoadingSpinner />}>
     <ProtectedRoute>
       <Dashboard />
     </ProtectedRoute>
   </Suspense>
   ```

---

## 🚀 Optimizaciones

### 1. Refresh Token Automático
```javascript
// Interceptor para refrescar token antes de expirar
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && !error.config._retry) {
      error.config._retry = true;
      const newToken = await authService.refreshToken();
      if (newToken) {
        error.config.headers.Authorization = `Bearer ${newToken}`;
        return api(error.config);
      }
    }
    return Promise.reject(error);
  }
);
```

### 2. Cache de Permisos en Frontend
```javascript
// Guardar permisos en localStorage después del login
localStorage.setItem('user_permissions', JSON.stringify(user.permisos));

// Leer desde cache para verificaciones rápidas
const cachedPermissions = JSON.parse(localStorage.getItem('user_permissions') || '[]');
```

### 3. Versionado de API
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // Rutas actuales
});

Route::prefix('v2')->group(function () {
    // Futuras rutas
});
```

### 4. Rate Limiting
```php
// En routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/auth/login', ...);
});
```

---

## 📝 Notas Importantes

1. **Roles en Backend**: Siempre usar mayúsculas ('ADMIN', 'DOCENTE', 'ESTUDIANTE')
2. **Roles en Frontend**: Normalizar a mayúsculas para comparaciones
3. **JWT Claims**: Incluir rol en custom claims para evitar queries adicionales
4. **Permisos**: ADMIN tiene bypass automático en PermissionMiddleware
5. **Seguridad**: Nunca exponer tokens en logs o respuestas de error
6. **CORS**: Configurar correctamente en `config/cors.php`

---

## 🔍 Debugging

### Verificar Token JWT
```javascript
// En consola del navegador
const token = localStorage.getItem('token');
const payload = JSON.parse(atob(token.split('.')[1]));
console.log('Rol en token:', payload.rol);
console.log('Permisos:', payload);
```

### Logs en Backend
```php
Log::info('Auth Debug', [
    'user_id' => $user->id,
    'rol' => $user->rol->nombre_rol,
    'permisos' => $user->rol->permisos->pluck('nombre_permiso')
]);
```

---

**Última actualización**: Enero 2025
**Versión**: 1.0.0

