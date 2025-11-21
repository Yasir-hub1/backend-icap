# 🔐 Configuración de Roles y Permisos

## Descripción

Este sistema implementa un control de acceso basado en roles (RBAC) con 3 roles principales:
- **ADMIN**: Acceso completo al sistema
- **DOCENTE**: Gestión de grupos, notas y estudiantes
- **ESTUDIANTE**: Portal personal con programas, pagos y documentos

## 📋 Instalación Inicial

### 1. Ejecutar Migraciones

```bash
cd backend
php artisan migrate
```

### 2. Ejecutar Seeders

```bash
php artisan db:seed
```

Esto creará automáticamente:
- ✅ 48 permisos del sistema
- ✅ 3 roles (ADMIN, DOCENTE, ESTUDIANTE)
- ✅ Asignación de permisos a cada rol
- ✅ Catálogos básicos
- ✅ Usuario administrador inicial

### 3. Verificar Instalación

```bash
# Ver roles creados
php artisan tinker
>>> App\Models\Rol::with('permisos')->get()

# Ver permisos por rol
>>> App\Models\Rol::where('nombre_rol', 'ADMIN')->first()->permisos->count()
```

## 🎯 Estructura de Permisos

### Módulos del Sistema

| Módulo | Acciones | ADMIN | DOCENTE | ESTUDIANTE |
|--------|----------|-------|---------|------------|
| **Estudiantes** | Ver, Crear, Editar, Eliminar, Activar | ✅ | Ver | - |
| **Docentes** | Ver, Crear, Editar, Eliminar | ✅ | - | - |
| **Programas** | Ver, Crear, Editar, Eliminar | ✅ | - | Ver |
| **Grupos** | Ver, Crear, Editar, Eliminar, Asignar | ✅ | Ver | - |
| **Pagos** | Ver, Verificar, Aprobar, Rechazar | ✅ | - | Ver |
| **Documentos** | Ver, Validar, Aprobar, Rechazar | ✅ | - | Ver |
| **Notas** | Ver, Crear, Editar | ✅ | ✅ | Ver |
| **Reportes** | Ver, Generar, Exportar | ✅ | Ver, Generar | - |
| **Roles** | Ver, Crear, Editar, Eliminar, Asignar | ✅ | - | - |
| **Dashboard** | Admin, Docente, Estudiante | ✅ | ✅ | ✅ |
| **Configuración** | Ver, Editar | ✅ | - | - |

## 🔄 Comandos Útiles

### Resetear Roles y Permisos

```bash
# Limpiar y recrear
php artisan migrate:fresh --seed

# Solo seeders (mantiene datos)
php artisan db:seed --class=PermisosSeeder
php artisan db:seed --class=RolesSeeder
```

### Ver Estructura

```bash
# Listar todos los permisos
php artisan tinker
>>> App\Models\Permiso::orderBy('modulo')->get(['modulo', 'accion', 'nombre_permiso'])

# Ver permisos de un rol específico
>>> App\Models\Rol::where('nombre_rol', 'DOCENTE')->first()->permisos->pluck('nombre_permiso')

# Contar usuarios por rol
>>> App\Models\Rol::withCount('usuarios')->get(['nombre_rol', 'usuarios_count'])
```

## 🛠️ Personalización

### Agregar Nuevo Permiso

```php
// database/seeders/PermisosSeeder.php
[
    'nombre_permiso' => 'modulo_accion',
    'descripcion' => 'Descripción del permiso',
    'modulo' => 'NombreModulo',
    'accion' => 'Accion',
    'activo' => true
]
```

### Crear Rol Personalizado

```bash
php artisan tinker

>>> $rol = App\Models\Rol::create([
    'nombre_rol' => 'Coordinador',
    'descripcion' => 'Coordinador académico',
    'activo' => true
]);

>>> $permisos = App\Models\Permiso::whereIn('nombre_permiso', [
    'grupos_ver',
    'estudiantes_ver',
    'reportes_ver'
])->pluck('permiso_id');

>>> $rol->permisos()->attach($permisos, ['activo' => true]);
```

## 🔒 Seguridad

### Roles Protegidos

Los siguientes roles NO pueden ser eliminados:
- ADMIN
- DOCENTE
- ESTUDIANTE

### Middleware de Roles

```php
// En las rutas (routes/api.php)
Route::middleware(['auth:api', 'role:ADMIN'])->group(function () {
    // Rutas solo para admin
});

Route::middleware(['auth:api', 'role:ADMIN,DOCENTE'])->group(function () {
    // Rutas para admin y docente
});
```

## 📝 Notas Importantes

1. **Orden de Ejecución**: Siempre ejecutar `PermisosSeeder` antes de `RolesSeeder`
2. **Primary Keys**: Los modelos usan `rol_id` y `permiso_id` como claves primarias
3. **Soft Deletes**: Los permisos se desactivan (`activo = false`) en lugar de eliminarse
4. **Pivot Table**: `rol_permiso` con campo `activo` para control granular

## 🐛 Troubleshooting

### Error: "Rol no encontrado"
```bash
# Verificar que existan roles
php artisan tinker
>>> App\Models\Rol::count()

# Si es 0, ejecutar seeders
>>> exit
php artisan db:seed --class=RolesSeeder
```

### Error: "Permiso no encontrado"
```bash
# Verificar permisos
php artisan tinker
>>> App\Models\Permiso::count()

# Si es 0, ejecutar seeders
>>> exit
php artisan db:seed --class=PermisosSeeder
```

### Error: "No autenticado" en frontend
1. Verificar que el usuario tenga un `rol_id` asignado
2. Verificar que el JWT incluya el claim `rol`
3. Revisar el middleware `RoleMiddleware`

## 📚 Referencias

- Modelos: `backend/app/Models/Rol.php` y `Permiso.php`
- Controlador: `backend/app/Http/Controllers/Admin/RolController.php`
- Rutas: `backend/routes/api.php` (líneas 193-208)
- Middleware: `backend/app/Http/Middleware/RoleMiddleware.php`
