# Estados de Estudiante - Documentación Completa

## 📋 Resumen de Estados

El sistema maneja **5 estados** para los estudiantes, aunque en la base de datos solo hay **4 estados** definidos en el seeder. Hay una inconsistencia que necesita ser corregida.

---

## 🗄️ Estados en la Base de Datos (Seeder)

Según `EstadoEstudianteSeeder.php`, los estados en la base de datos son:

| ID | Nombre Estado | Descripción |
|---|---|---|
| 1 | `pre-inscrito` | Estado inicial cuando el estudiante se registra |
| 2 | `inscrito` | Estado cuando el estudiante está inscrito |
| 3 | `validado` | Estado cuando los documentos están validados |
| 4 | `Rechazado` | Estado cuando los documentos fueron rechazados |

**⚠️ PROBLEMA:** El seeder solo tiene 4 estados, pero el código espera 5 estados.

---

## 💻 Estados en el Código (Lógica de Negocio)

Según `PanelEstudianteController.php`, el código maneja estos estados:

| estado_id | Nombre en Código | Descripción | Activo | Puede Inscribirse |
|---|---|---|---|---|
| **1** | Pre-registrado | Estudiante recién registrado, sin documentos subidos | ❌ No | ❌ No |
| **2** | Documentos incompletos | Estudiante ha subido algunos documentos pero no todos | ❌ No | ❌ No |
| **3** | En revisión | Todos los documentos requeridos subidos, pendientes de validación | ❌ No | ❌ No |
| **4** | Validado - Activo | Todos los documentos aprobados, apto para inscripción | ✅ Sí | ✅ Sí |
| **5** | Rechazado | Documentos rechazados, debe volver a subirlos | ❌ No | ❌ No |

---

## 🔄 Flujo de Estados

```
1. Pre-registrado (estado_id = 1)
   ↓ [Estudiante sube documentos]
   
2. Documentos incompletos (estado_id = 2)
   ↓ [Estudiante sube los 3 documentos requeridos]
   
3. En revisión (estado_id = 3)
   ↓ [Admin aprueba todos los documentos]
   
4. Validado - Activo (estado_id = 4) ✅
   ↓ [Puede inscribirse a programas]
   
   O si hay problemas:
   
5. Rechazado (estado_id = 5)
   ↓ [Estudiante vuelve a subir documentos]
   → Vuelve a estado 3
```

---

## 📝 Detalles por Estado

### estado_id = 1: Pre-registrado

**Cuándo se asigna:**
- Al momento del registro del estudiante (`AutenticacionEstudianteController.php` línea 91)

**Características:**
- No ha subido documentos
- No puede inscribirse
- No está activo

**Mensaje en Dashboard:**
- ⚠️ URGENTE: Debe subir sus documentos
- Muestra lista de documentos faltantes
- Acción: `upload_documents`

---

### estado_id = 2: Documentos incompletos

**Cuándo se asigna:**
- Cuando el estudiante ha subido algunos documentos pero no todos los requeridos

**Características:**
- Ha subido al menos 1 documento pero menos de 3 requeridos
- No puede inscribirse
- No está activo

**Mensaje en Dashboard:**
- ⚠️ ATENCIÓN: Documentos pendientes
- Muestra progreso de documentos subidos
- Acción: `upload_documents`

---

### estado_id = 3: En revisión

**Cuándo se asigna:**
- Cuando el estudiante ha subido los 3 documentos requeridos (`DocumentoController.php` línea 160)
- Se asigna automáticamente cuando se suben los 3 documentos requeridos

**Características:**
- Todos los documentos requeridos están subidos
- Documentos pendientes de validación por admin
- No puede inscribirse aún
- No está activo

**Mensaje en Dashboard:**
- Documentos en revisión
- Será notificado cuando sean validados
- Sin acciones disponibles

**Nota:** Este es el estado que aparece en la lista de validación de documentos del admin.

---

### estado_id = 4: Validado - Activo ✅

**Cuándo se asigna:**
- Cuando el admin aprueba todos los documentos requeridos (`ValidacionDocumentoController.php` líneas 196-198, 384)
- Cuando el admin activa manualmente al estudiante (`EstudianteController.php` línea 496)

**Características:**
- ✅ Todos los documentos requeridos están aprobados
- ✅ Estudiante está activo
- ✅ Puede inscribirse a programas
- ✅ Puede ver programas disponibles

**Mensaje en Dashboard:**
- ✅ Su cuenta está activa
- Puede inscribirse a programas disponibles
- Acción: `view_programs`

**Lógica de "activo":**
```php
// En EstudianteController.php
$activo = $estadoId == 4 || ($estadoNombre === 'validado' && $documentosCompletos);
```

---

### estado_id = 5: Rechazado

**Cuándo se asigna:**
- Cuando el admin rechaza documentos (`InscripcionController.php` línea 385)

**Características:**
- Documentos fueron rechazados
- Debe volver a subir documentos
- No puede inscribirse
- No está activo

**Mensaje en Dashboard:**
- ❌ Documentos rechazados
- Muestra documentos rechazados con motivos
- Acción: `re_upload_documents`

---

## 🔧 Lógica de "Activo" en el Backend

El backend calcula si un estudiante está activo de esta manera:

```php
// EstudianteController.php (línea 71)
$estadoId = $estudiante->Estado_id ?? 1;
$estadoNombre = strtolower($estudiante->estadoEstudiante->nombre_estado ?? '');
$documentosCompletos = $this->verificarDocumentosCompletos($estudiante->registro_estudiante);
$activo = $estadoId == 4 || ($estadoNombre === 'validado' && $documentosCompletos);
```

**Un estudiante está activo si:**
1. `estado_id === 4` (Validado - Activo)
2. **O** el nombre del estado es "validado" **Y** tiene documentos completos

Esto permite compatibilidad con datos existentes donde el estado puede tener nombre "validado" pero `estado_id` diferente.

---

## 🎯 Lógica de "Activo" en el Frontend

```javascript
// Estudiantes.jsx (línea 486)
const estadoNombre = (row.estado || '').toLowerCase()
const isActive = row.activo === true || 
                row.estado_id === 4 || 
                estadoNombre === 'validado' ||
                estadoNombre === 'activo'
```

**Un estudiante se muestra como activo si:**
1. `activo === true` (del backend)
2. **O** `estado_id === 4`
3. **O** el nombre del estado es "validado" o "activo"

---

## ⚠️ Inconsistencias Detectadas

### Problema 1: Seeder vs Código
- **Seeder:** 4 estados (pre-inscrito, inscrito, validado, Rechazado)
- **Código:** 5 estados (1, 2, 3, 4, 5)

**Solución recomendada:** Actualizar el seeder para incluir los 5 estados correctos.

### Problema 2: Mapeo de IDs
- El seeder no especifica IDs, Laravel los asigna automáticamente (1, 2, 3, 4)
- El código espera estados específicos en IDs específicos (1, 2, 3, 4, 5)

**Solución recomendada:** Actualizar el seeder para crear los estados con los IDs correctos.

---

## 📊 Documentos Requeridos

Los documentos requeridos para cambiar de estado son:
1. **Carnet de Identidad - Anverso**
2. **Carnet de Identidad - Reverso**
3. **Certificado de Nacimiento**

**Nota:** "Título de Bachiller" es **opcional** y no cuenta para el cambio de estado.

---

## 🔄 Transiciones de Estado Automáticas

### De estado 1 → 2
- **Cuándo:** Estudiante sube al menos 1 documento pero no todos
- **Código:** No se hace automáticamente actualmente

### De estado 1/2 → 3
- **Cuándo:** Estudiante sube los 3 documentos requeridos
- **Código:** `DocumentoController.php` línea 159-160

### De estado 3 → 4
- **Cuándo:** Admin aprueba todos los documentos requeridos
- **Código:** `ValidacionDocumentoController.php` líneas 194-206

### De estado 3 → 5
- **Cuándo:** Admin rechaza documentos
- **Código:** `InscripcionController.php` línea 385

---

## 📍 Archivos Relacionados

- **Seeder:** `backend/database/seeders/EstadoEstudianteSeeder.php`
- **Modelo:** `backend/app/Models/EstadoEstudiante.php`
- **Lógica Dashboard:** `backend/app/Http/Controllers/Student/PanelEstudianteController.php`
- **Lógica Admin:** `backend/app/Http/Controllers/Admin/EstudianteController.php`
- **Validación Docs:** `backend/app/Http/Controllers/Admin/ValidacionDocumentoController.php`
- **Subida Docs:** `backend/app/Http/Controllers/Student/DocumentoController.php`

---

## ✅ Recomendaciones

1. ✅ **Actualizar el seeder** para incluir los 5 estados correctos con IDs específicos - **COMPLETADO**
2. ✅ **Verificar la base de datos** para asegurar que los estados existan con los IDs correctos - **Script SQL creado**
3. ✅ **Documentar claramente** el mapeo entre nombres de estado y IDs - **COMPLETADO**
4. ✅ **Considerar migración** para actualizar estados existentes si es necesario - **Script SQL creado**

---

## 🚀 Cómo Aplicar los Cambios

### Opción 1: Ejecutar el Seeder (Recomendado)
```bash
php artisan db:seed --class=EstadoEstudianteSeeder
```

### Opción 2: Ejecutar el Script SQL
```bash
psql -U tu_usuario -d tu_base_de_datos -f database/scripts/actualizar_estados_estudiante.sql
```

### Opción 3: Ejecutar desde Laravel Tinker
```php
php artisan tinker
>>> DB::unprepared(file_get_contents('database/scripts/actualizar_estados_estudiante.sql'));
```

**Nota:** El seeder actualizado maneja automáticamente:
- Crear estados con IDs específicos (1, 2, 3, 4, 5)
- Actualizar estados existentes con nombres antiguos
- Migrar estudiantes a los nuevos IDs de estado
- Eliminar estados duplicados

