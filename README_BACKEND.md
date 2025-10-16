# Backend - Instituto de Capacitación

## Descripción
Sistema de gestión académica desarrollado con Laravel 11 y PostgreSQL, implementando Clean Architecture y las mejores prácticas de desarrollo.

## Características Principales

### 🏗️ Arquitectura
- **Clean Architecture** con separación clara de responsabilidades
- **Modelos Eloquent** optimizados con relaciones eficientes
- **Servicios** para lógica de negocio compleja
- **Form Requests** para validaciones robustas
- **Caché** implementado para mejorar performance

### 📊 Base de Datos
- **PostgreSQL** como motor principal
- **Herencia nativa** para Usuario -> Estudiante/Docente
- **Relaciones optimizadas** con índices estratégicos
- **Integridad referencial** completa

### 🚀 API RESTful
- **Endpoints organizados** por módulos con prefijos
- **Paginación** en todas las consultas
- **Filtros avanzados** para búsquedas eficientes
- **Validaciones** en español con mensajes personalizados

## Estructura del Proyecto

```
app/
├── Http/
│   ├── Controllers/Api/          # Controladores API
│   └── Requests/                 # Validaciones
├── Models/                       # Modelos Eloquent
├── Services/                     # Lógica de negocio
└── ...
```

## Módulos Implementados

### 👨‍🎓 Estudiantes
- CRUD completo con validaciones
- Búsqueda avanzada y autocompletado
- Historial académico completo
- Estadísticas y reportes

### 📚 Programas
- Gestión de programas y cursos
- Asociación con módulos
- Control de versiones
- Análisis de costos y duración

### 📝 Inscripciones
- Proceso de inscripción completo
- Planes de pago flexibles
- Control de descuentos
- Validaciones de negocio

### 💰 Pagos
- Registro de pagos por cuotas
- Control de vencimientos
- Reportes financieros
- Alertas automáticas

### 🏢 Instituciones
- Gestión de instituciones
- Convenios y acuerdos
- Control de estados
- Ubicación geográfica

## Configuración

### Requisitos
- PHP 8.2+
- PostgreSQL 12+
- Composer
- Node.js (para assets)

### Instalación

1. **Clonar repositorio**
```bash
git clone <repository-url>
cd marcela/backend
```

2. **Instalar dependencias**
```bash
composer install
```

3. **Configurar entorno**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurar base de datos**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=instituto_capacitacion
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

5. **Ejecutar migraciones** (si las hay)
```bash
php artisan migrate
```

6. **Iniciar servidor**
```bash
php artisan serve
```

## Endpoints Principales

### Estudiantes
```
GET    /api/estudiantes              # Listar estudiantes
GET    /api/estudiantes/{id}         # Obtener estudiante
POST   /api/estudiantes              # Crear estudiante
PUT    /api/estudiantes/{id}         # Actualizar estudiante
DELETE /api/estudiantes/{id}         # Eliminar estudiante
GET    /api/estudiantes/buscar       # Buscar estudiantes
GET    /api/estudiantes/estadisticas # Estadísticas
```

### Programas
```
GET    /api/programas                # Listar programas
GET    /api/programas/{id}           # Obtener programa
POST   /api/programas                # Crear programa
PUT    /api/programas/{id}           # Actualizar programa
DELETE /api/programas/{id}           # Eliminar programa
GET    /api/programas/datos-formulario # Datos para formularios
GET    /api/programas/estadisticas   # Estadísticas
```

### Inscripciones
```
GET    /api/inscripciones            # Listar inscripciones
GET    /api/inscripciones/{id}       # Obtener inscripción
POST   /api/inscripciones            # Crear inscripción
PUT    /api/inscripciones/{id}       # Actualizar inscripción
DELETE /api/inscripciones/{id}       # Eliminar inscripción
GET    /api/inscripciones/estadisticas # Estadísticas
```

### Pagos
```
GET    /api/pagos                    # Listar pagos
GET    /api/pagos/{id}               # Obtener pago
POST   /api/pagos                    # Registrar pago
PUT    /api/pagos/{id}               # Actualizar pago
DELETE /api/pagos/{id}               # Eliminar pago
GET    /api/pagos/cuotas-pendientes  # Cuotas pendientes
GET    /api/pagos/estadisticas       # Estadísticas
```

### Catálogos
```
GET    /api/catalogos/paises         # Países
GET    /api/catalogos/provincias/{id} # Provincias
GET    /api/catalogos/ciudades/{id}  # Ciudades
GET    /api/catalogos/tipos-programa # Tipos de programa
GET    /api/catalogos/ramas-academicas # Ramas académicas
GET    /api/catalogos/modulos        # Módulos
GET    /api/catalogos/estados-estudiante # Estados
GET    /api/catalogos/descuentos     # Descuentos
```

## Características Técnicas

### Performance
- **Eager Loading** para evitar N+1 queries
- **Caché Redis** para consultas frecuentes
- **Índices** en campos de búsqueda
- **Paginación** en todas las listas

### Seguridad
- **Validaciones** robustas en Form Requests
- **Sanitización** de datos de entrada
- **Transacciones** para operaciones críticas
- **Soft deletes** lógicos

### Mantenibilidad
- **Código limpio** siguiendo SOLID
- **Servicios** para lógica compleja
- **Mensajes** en español
- **Documentación** completa

## Desarrollo

### Comandos Útiles
```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar tests
php artisan test

# Análisis de código
./vendor/bin/pint
```

### Estructura de Respuestas API
```json
{
  "success": true,
  "data": {...},
  "message": "Operación exitosa"
}
```

### Manejo de Errores
```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": {
    "campo": ["Error específico"]
  }
}
```

## Contribución

1. Seguir las convenciones de código de Laravel
2. Escribir tests para nuevas funcionalidades
3. Documentar cambios importantes
4. Usar mensajes en español
5. Mantener la arquitectura limpia

## Licencia
Este proyecto es privado y confidencial.
