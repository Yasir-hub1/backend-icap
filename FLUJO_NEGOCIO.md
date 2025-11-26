# 📚 Flujo de Negocio del Sistema de Gestión Académica

## 📋 Índice

1. [Actores del Sistema](#actores-del-sistema)
2. [Flujo Completo de Negocio](#flujo-completo-de-negocio)
3. [Módulos y Campos](#módulos-y-campos)
4. [Relaciones entre Entidades](#relaciones-entre-entidades)
5. [Estados del Estudiante](#estados-del-estudiante)
6. [Flujo de Inscripciones](#flujo-de-inscripciones)
7. [Flujo de Pagos](#flujo-de-pagos)

---

## 👥 Actores del Sistema

### 🔐 Administrador (Admin)
- **Rol:** Gestión completa del sistema
- **Responsabilidades:**
  - Crear y gestionar programas académicos
  - Crear grupos y asignar docentes
  - Validar documentos de estudiantes
  - Gestionar planes de pago
  - Verificar pagos de estudiantes
  - Activar/desactivar estudiantes

### 🎓 Estudiante
- **Rol:** Usuario final del sistema
- **Responsabilidades:**
  - Registrarse en el sistema
  - Subir documentos de identificación
  - Inscribirse a programas disponibles
  - Realizar pagos de cuotas
  - Consultar sus materias y horarios

---

## 🔄 Flujo Completo de Negocio

### Fase 1: Configuración Inicial (Admin)

```
1. ADMIN crea INSTITUCIÓN
   ↓
2. ADMIN crea PROGRAMA académico
   - Asocia programa a institución
   - Define duración, costo, módulos
   ↓
3. ADMIN asocia MÓDULOS al PROGRAMA (tabla programa_modulo)
   - Define qué módulos pertenecen al programa
   ↓
4. ADMIN crea GRUPO para el programa
   - Selecciona programa y módulo (validado que el módulo pertenece al programa)
   - Asigna docente
   - Define fechas de inicio y fin
   - Crea horarios con aulas
   ↓
5. Sistema está listo para recibir inscripciones
```

### Fase 2: Registro y Validación de Estudiante

```
1. ESTUDIANTE se registra
   - Crea registro en tabla PERSONA
   - Crea registro en tabla ESTUDIANTE (hereda de PERSONA)
   - Crea registro en tabla USUARIO (con referencia a persona_id)
   - Estado inicial: estado_id = 1 (Pre-registrado)
   ↓
2. ESTUDIANTE sube documentos
   - Sube documentos individualmente
   - Documentos requeridos:
     * Carnet de Identidad - Anverso
     * Carnet de Identidad - Reverso
     * Certificado de Nacimiento
   - Documento opcional: Título de Bachiller
   ↓
3. Sistema actualiza estado automáticamente:
   - Si sube < 3 documentos requeridos → estado_id = 2 (Documentos incompletos)
   - Si sube los 3 documentos requeridos → estado_id = 3 (En revisión)
   - Se envía notificación a ADMIN
   ↓
4. ADMIN revisa documentos
   - Aprueba o rechaza cada documento
   - Si todos aprobados → estado_id = 4 (Validado - Activo)
   - Si alguno rechazado → estado_id = 5 (Rechazado)
   - Se envía notificación a ESTUDIANTE
   ↓
5. Si estado_id = 4, ESTUDIANTE puede inscribirse
```

### Fase 3: Inscripción a Programa

```
1. ESTUDIANTE (con estado_id = 4) consulta programas disponibles
   - Sistema muestra solo programas con grupos activos
   - Muestra información: nombre, duración, costo, horarios, cupos
   ↓
2. ESTUDIANTE selecciona programa y grupo
   - Sistema valida:
     * Estudiante tiene estado_id = 4
     * Grupo tiene cupos disponibles (máximo 30)
     * No hay conflicto de horarios con otros grupos inscritos
   ↓
3. ESTUDIANTE elige número de cuotas (1-12)
   ↓
4. Sistema crea INSCRIPCIÓN
   - Registro en tabla INSCRIPCION
   - Asocia estudiante al GRUPO (tabla grupo_estudiante)
   ↓
5. Sistema crea PLAN DE PAGOS
   - Calcula monto total (costo del programa)
   - Aplica descuentos si existen
   - Divide en cuotas según selección
   ↓
6. Sistema crea CUOTAS
   - Una cuota por cada período de pago
   - Define fechas de inicio y fin para cada cuota
   ↓
7. ESTUDIANTE recibe notificación de inscripción exitosa
```

### Fase 4: Gestión de Pagos

```
1. ESTUDIANTE consulta sus cuotas pendientes
   - Ve todas las cuotas de sus planes de pago
   - Ve estado: PENDIENTE, PAGADA, VENCIDA
   ↓
2. ESTUDIANTE realiza pago
   - Opción 1: Pago con QR (genera token)
   - Opción 2: Subir comprobante (pendiente de verificación)
   ↓
3. Sistema registra PAGO
   - Crea registro en tabla PAGOS
   - Asocia pago a CUOTA
   - Estado inicial: verificado = false
   ↓
4. ADMIN verifica pago (si es comprobante)
   - Revisa comprobante subido
   - Marca como verificado = true
   - Asigna verificador (verificado_por)
   ↓
5. Sistema actualiza estado de cuota
   - Si monto_pagado >= monto → cuota está PAGADA
   - Si fecha_fin < ahora y no pagada → cuota está VENCIDA
   ↓
6. Sistema actualiza estado de plan de pago
   - Calcula monto_pagado total
   - Calcula monto_pendiente
   - Si todas las cuotas pagadas → plan COMPLETO
```

---

## 📦 Módulos y Campos

### 1. INSTITUCIÓN (`institucion`)

**Campos:**
- `id` (PK): Identificador único
- `nombre`: Nombre de la institución
- `direccion`: Dirección física
- `telefono`: Teléfono de contacto
- `email`: Correo electrónico
- `sitio_web`: URL del sitio web
- `fecha_fundacion`: Fecha de fundación
- `estado`: Estado de la institución (activo/inactivo)
- `ciudad_id` (FK): Referencia a ciudad
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `hasMany` → PROGRAMA

---

### 2. PROGRAMA (`programa`)

**Campos:**
- `id` (PK): Identificador único
- `nombre`: Nombre del programa
- `duracion_meses`: Duración en meses
- `total_modulos`: Total de módulos del programa
- `costo`: Costo total del programa (decimal)
- `version_id` (FK): Referencia a versión académica
- `rama_academica_id` (FK): Referencia a rama académica
- `tipo_programa_id` (FK): Referencia a tipo de programa
- `institucion_id` (FK): Referencia a institución
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → INSTITUCIÓN, VERSIÓN, RAMA_ACADÉMICA, TIPO_PROGRAMA
- `hasMany` → INSCRIPCIÓN, GRUPO
- `belongsToMany` → MÓDULO (tabla `programa_modulo`)

---

### 3. PROGRAMA_MODULO (`programa_modulo`) - Tabla Pivote

**Campos:**
- `programa_id` (FK, PK compuesta): Referencia a programa
- `modulo_id` (FK, PK compuesta): Referencia a módulo
- `edicion`: Edición del módulo en el programa
- `estado`: Estado del módulo en el programa
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → PROGRAMA, MÓDULO

---

### 4. GRUPO (`grupo`)

**Campos:**
- `grupo_id` (PK): Identificador único
- `fecha_ini`: Fecha de inicio del grupo
- `fecha_fin`: Fecha de fin del grupo
- `programa_id` (FK): Referencia a programa
- `modulo_id` (FK): Referencia a módulo
- `docente_id` (FK): Referencia a docente
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → PROGRAMA, MÓDULO, DOCENTE
- `belongsToMany` → ESTUDIANTE (tabla `grupo_estudiante` con campos pivot: `nota`, `estado`)
- `belongsToMany` → HORARIO (tabla `grupo_horario` con campo pivot: `aula`)

---

### 5. HORARIO (`horario`)

**Campos:**
- `horario_id` (PK): Identificador único
- `dias`: Días de la semana (ej: "LUNES,MARTES,MIÉRCOLES")
- `hora_ini`: Hora de inicio (time)
- `hora_fin`: Hora de fin (time)
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsToMany` → GRUPO (tabla `grupo_horario` con campo pivot: `aula`)

---

### 6. PERSONA (`persona`)

**Campos:**
- `id` (PK): Identificador único (usado por ESTUDIANTE y DOCENTE)
- `ci`: Carnet de identidad
- `nombre`: Nombre
- `apellido`: Apellido
- `celular`: Número de celular
- `sexo`: Sexo (M/F)
- `fecha_nacimiento`: Fecha de nacimiento
- `direccion`: Dirección
- `fotografia`: Ruta a fotografía
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `hasOne` → USUARIO (usuario tiene `persona_id`)
- Heredado por → ESTUDIANTE, DOCENTE (PostgreSQL INHERITS)

---

### 7. ESTUDIANTE (`estudiante`) - Hereda de PERSONA

**Campos (propios):**
- `id` (PK): Heredado de PERSONA
- `registro_estudiante`: Número de registro único (UNIQUE)
- `provincia`: Provincia de residencia
- `estado_id` (FK): Referencia a estado del estudiante

**Campos heredados de PERSONA:**
- `ci`, `nombre`, `apellido`, `celular`, `sexo`, `fecha_nacimiento`, `direccion`, `fotografia`

**Relaciones:**
- `belongsTo` → ESTADO_ESTUDIANTE
- `hasMany` → INSCRIPCIÓN
- `belongsToMany` → GRUPO (tabla `grupo_estudiante` con campos pivot: `nota`, `estado`)
- `hasOne` → USUARIO (a través de PERSONA)

---

### 8. ESTADO_ESTUDIANTE (`estado_estudiante`)

**Campos:**
- `id` (PK): Identificador único (1-5)
- `nombre_estado`: Nombre del estado
- `created_at`, `updated_at`: Timestamps

**Estados:**
- `1`: Pre-registrado
- `2`: Documentos incompletos
- `3`: En revisión
- `4`: Validado - Activo
- `5`: Rechazado

---

### 9. USUARIO (`usuario`)

**Campos:**
- `usuario_id` (PK): Identificador único
- `email`: Correo electrónico (usado para login)
- `password`: Contraseña (hasheada)
- `persona_id` (FK): Referencia a persona (ESTUDIANTE o DOCENTE)
- `rol_id` (FK): Referencia a rol
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → PERSONA, ROL

---

### 10. INSCRIPCIÓN (`inscripcion`)

**Campos:**
- `id` (PK): Identificador único
- `fecha`: Fecha de inscripción
- `estudiante_id` (FK): Referencia a estudiante
- `programa_id` (FK): Referencia a programa
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → ESTUDIANTE, PROGRAMA
- `hasOne` → PLAN_PAGOS, DESCUENTO

---

### 11. PLAN_PAGOS (`plan_pago`)

**Campos:**
- `id` (PK): Identificador único
- `inscripcion_id` (FK): Referencia a inscripción
- `monto_total`: Monto total del plan (decimal)
- `total_cuotas`: Número total de cuotas
- `created_at`, `updated_at`: Timestamps

**Campos calculados (accessors):**
- `monto_pagado`: Suma de pagos realizados
- `monto_pendiente`: monto_total - monto_pagado
- `esta_completo`: true si todas las cuotas están pagadas

**Relaciones:**
- `belongsTo` → INSCRIPCIÓN
- `hasMany` → CUOTA

---

### 12. CUOTA (`cuotas`)

**Campos:**
- `id` (PK): Identificador único
- `plan_pago_id` (FK): Referencia a plan de pagos
- `fecha_ini`: Fecha de inicio del período de pago
- `fecha_fin`: Fecha de vencimiento
- `monto`: Monto de la cuota (decimal)
- `created_at`, `updated_at`: Timestamps

**Campos calculados (accessors):**
- `monto_pagado`: Suma de pagos realizados para esta cuota
- `saldo_pendiente`: monto - monto_pagado
- `esta_pagada`: true si tiene pagos
- `esta_vencida`: true si fecha_fin < ahora y no está pagada

**Relaciones:**
- `belongsTo` → PLAN_PAGOS
- `hasMany` → PAGO

**Estados:**
- `PENDIENTE`: No pagada y no vencida
- `PAGADA`: Tiene pagos que cubren el monto
- `VENCIDA`: fecha_fin < ahora y no pagada

---

### 13. PAGO (`pagos`)

**Campos:**
- `id` (PK): Identificador único
- `cuota_id` (FK): Referencia a cuota
- `fecha`: Fecha del pago
- `monto`: Monto pagado (decimal)
- `token`: Token único para pagos QR
- `verificado`: Boolean - si el pago fue verificado por admin
- `fecha_verificacion`: Fecha de verificación
- `verificado_por` (FK): Usuario que verificó el pago
- `observaciones`: Observaciones del pago
- `metodo`: Método de pago (QR, transferencia, etc.)
- `comprobante`: Ruta al archivo de comprobante
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → CUOTA, USUARIO (verificador)

---

### 14. DOCUMENTO (`documento`)

**Campos:**
- `documento_id` (PK): Identificador único
- `persona_id` (FK): Referencia a persona (estudiante)
- `tipo_documento_id` (FK): Referencia a tipo de documento
- `nombre`: Nombre del documento
- `version`: Versión del documento (incrementa al re-subir)
- `path`: Ruta al archivo
- `estado`: Estado del documento ('0'=pendiente, '1'=aprobado, '2'=rechazado)
- `observaciones`: Observaciones del admin
- `fecha_subida`: Fecha de subida
- `created_at`, `updated_at`: Timestamps

**Relaciones:**
- `belongsTo` → PERSONA, TIPO_DOCUMENTO

---

## 🔗 Relaciones entre Entidades

### Diagrama de Relaciones Principales

```
INSTITUCIÓN
    ↓ (1:N)
PROGRAMA
    ↓ (1:N)                    ↓ (N:M) [programa_modulo]
INSCRIPCIÓN ←─────────────── MÓDULO
    ↓ (1:1)                   ↓ (1:N)
PLAN_PAGOS                    GRUPO
    ↓ (1:N)                       ↓ (N:M) [grupo_estudiante]
CUOTA                          ESTUDIANTE
    ↓ (1:N)                       ↓ (1:1)
PAGO                           PERSONA
                                   ↓ (1:1)
                                USUARIO
```

### Relaciones Detalladas

1. **INSTITUCIÓN → PROGRAMA** (1:N)
   - Una institución tiene muchos programas
   - `programa.institucion_id` → `institucion.id`

2. **PROGRAMA → MÓDULO** (N:M)
   - Un programa tiene muchos módulos
   - Un módulo puede estar en muchos programas
   - Tabla pivote: `programa_modulo`
   - Campos pivot: `edicion`, `estado`

3. **PROGRAMA → GRUPO** (1:N)
   - Un programa tiene muchos grupos
   - `grupo.programa_id` → `programa.id`

4. **GRUPO → MÓDULO** (N:1)
   - Un grupo pertenece a un módulo
   - `grupo.modulo_id` → `modulo.modulo_id`
   - **Validación:** El módulo debe pertenecer al programa del grupo

5. **GRUPO → ESTUDIANTE** (N:M)
   - Un grupo tiene muchos estudiantes
   - Un estudiante puede estar en muchos grupos
   - Tabla pivote: `grupo_estudiante`
   - Campos pivot: `nota`, `estado`

6. **GRUPO → HORARIO** (N:M)
   - Un grupo tiene muchos horarios
   - Un horario puede estar en muchos grupos
   - Tabla pivote: `grupo_horario`
   - Campo pivot: `aula`

7. **ESTUDIANTE → INSCRIPCIÓN** (1:N)
   - Un estudiante puede tener muchas inscripciones
   - `inscripcion.estudiante_id` → `estudiante.id`

8. **PROGRAMA → INSCRIPCIÓN** (1:N)
   - Un programa tiene muchas inscripciones
   - `inscripcion.programa_id` → `programa.id`

9. **INSCRIPCIÓN → PLAN_PAGOS** (1:1)
   - Una inscripción tiene un plan de pagos
   - `plan_pago.inscripcion_id` → `inscripcion.id`

10. **PLAN_PAGOS → CUOTA** (1:N)
    - Un plan de pagos tiene muchas cuotas
    - `cuota.plan_pago_id` → `plan_pago.id`

11. **CUOTA → PAGO** (1:N)
    - Una cuota puede tener muchos pagos (pagos parciales)
    - `pago.cuota_id` → `cuota.id`

12. **PERSONA → ESTUDIANTE** (Herencia PostgreSQL INHERITS)
    - Estudiante hereda todos los campos de Persona
    - Comparten el mismo `id`
    - `estudiante` es una tabla que hereda de `persona`

13. **PERSONA → USUARIO** (1:1)
    - Una persona tiene un usuario
    - `usuario.persona_id` → `persona.id`

14. **ESTUDIANTE → ESTADO_ESTUDIANTE** (N:1)
    - Un estudiante tiene un estado
    - `estudiante.estado_id` → `estado_estudiante.id`

15. **PERSONA → DOCUMENTO** (1:N)
    - Una persona puede tener muchos documentos
    - `documento.persona_id` → `persona.id`

---

## 📊 Estados del Estudiante

### Estado 1: Pre-registrado
- **Cuándo:** Al momento del registro
- **Características:**
  - No ha subido documentos
  - No puede inscribirse
  - No está activo

### Estado 2: Documentos incompletos
- **Cuándo:** Ha subido algunos documentos pero no todos los requeridos
- **Características:**
  - Ha subido al menos 1 documento pero menos de 3 requeridos
  - No puede inscribirse
  - No está activo

### Estado 3: En revisión
- **Cuándo:** Ha subido los 3 documentos requeridos
- **Características:**
  - Todos los documentos requeridos están subidos
  - Documentos pendientes de validación por admin
  - No puede inscribirse aún
  - No está activo
  - **Nota:** Se envía notificación a admin

### Estado 4: Validado - Activo ✅
- **Cuándo:** Admin aprueba todos los documentos requeridos
- **Características:**
  - Todos los documentos requeridos están aprobados
  - Estudiante está activo
  - Puede inscribirse a programas
  - Puede ver programas disponibles
  - **Nota:** Se envía notificación a estudiante

### Estado 5: Rechazado
- **Cuándo:** Admin rechaza documentos
- **Características:**
  - Documentos fueron rechazados
  - Debe volver a subir documentos
  - No puede inscribirse
  - No está activo
  - **Nota:** Se envía notificación a estudiante

### Transiciones de Estado

```
1 (Pre-registrado)
  ↓ [Sube < 3 documentos]
2 (Documentos incompletos)
  ↓ [Sube 3 documentos requeridos]
3 (En revisión)
  ↓ [Admin aprueba todos]     ↓ [Admin rechaza]
4 (Validado - Activo) ✅      5 (Rechazado)
                              ↓ [Re-sube documentos]
                              3 (En revisión)
```

---

## 🎯 Flujo de Inscripciones

### Pre-requisitos para Inscripción

1. **Estudiante debe tener estado_id = 4** (Validado - Activo)
2. **Programa debe estar activo** (institución con estado = activo)
3. **Grupo debe tener cupos disponibles** (máximo 30 estudiantes)
4. **No debe haber conflicto de horarios** con otros grupos inscritos

### Proceso de Inscripción

1. **Estudiante consulta programas disponibles**
   - Sistema filtra programas con grupos activos
   - Muestra información: nombre, duración, costo, horarios, cupos

2. **Estudiante selecciona programa y grupo**
   - Sistema valida:
     - Estado del estudiante
     - Cupos disponibles
     - Conflicto de horarios

3. **Estudiante elige número de cuotas** (1-12)

4. **Sistema crea inscripción**
   - Registro en `inscripcion`
   - Asocia estudiante al grupo (`grupo_estudiante`)

5. **Sistema crea plan de pagos**
   - Calcula monto total (costo del programa)
   - Aplica descuentos si existen
   - Divide en cuotas según selección

6. **Sistema crea cuotas**
   - Una cuota por cada período de pago
   - Define fechas de inicio y fin para cada cuota

7. **Notificación a estudiante**
   - Se envía notificación de inscripción exitosa

### Validación de Conflicto de Horarios

El sistema verifica que el horario del nuevo grupo no se solape con horarios de grupos ya inscritos:

```php
// Para cada grupo ya inscrito
foreach ($gruposInscritos as $grupoInscrito) {
    foreach ($grupoInscrito->horarios as $horarioInscrito) {
        foreach ($grupo->horarios as $horarioNuevo) {
            // Verificar días comunes
            $diasComunes = array_intersect($diasInscrito, $diasNuevo);
            
            if (!empty($diasComunes)) {
                // Verificar solapamiento de horas
                if (horas_se_solapan($horarioInscrito, $horarioNuevo)) {
                    // ERROR: Conflicto de horario
                }
            }
        }
    }
}
```

---

## 💰 Flujo de Pagos

### Estructura de Pagos

```
INSCRIPCIÓN
    ↓
PLAN_PAGOS (1 plan por inscripción)
    ↓
CUOTAS (N cuotas según número elegido)
    ↓
PAGOS (N pagos por cuota - permite pagos parciales)
```

### Estados de Cuota

- **PENDIENTE:** No pagada y no vencida
- **PAGADA:** Tiene pagos que cubren el monto total
- **VENCIDA:** fecha_fin < ahora y no pagada

### Proceso de Pago

1. **Estudiante consulta cuotas pendientes**
   - Ve todas las cuotas de sus planes de pago
   - Ve estado: PENDIENTE, PAGADA, VENCIDA
   - Ve monto total, pagado y pendiente

2. **Estudiante realiza pago**
   - **Opción 1:** Pago con QR
     - Sistema genera token único
     - Estudiante escanea QR y paga
     - Pago se registra automáticamente
   - **Opción 2:** Subir comprobante
     - Estudiante sube imagen del comprobante
     - Pago queda pendiente de verificación

3. **Sistema registra pago**
   - Crea registro en `pagos`
   - Asocia pago a `cuota`
   - Estado inicial: `verificado = false` (si es comprobante)

4. **Admin verifica pago** (si es comprobante)
   - Revisa comprobante subido
   - Marca como `verificado = true`
   - Asigna `verificado_por` (usuario admin)

5. **Sistema actualiza estado de cuota**
   - Calcula `monto_pagado` (suma de pagos)
   - Si `monto_pagado >= monto` → cuota está PAGADA
   - Si `fecha_fin < ahora` y no pagada → cuota está VENCIDA

6. **Sistema actualiza estado de plan de pago**
   - Calcula `monto_pagado` total (suma de todas las cuotas)
   - Calcula `monto_pendiente` (monto_total - monto_pagado)
   - Si todas las cuotas pagadas → plan COMPLETO

### Cálculo de Montos

**Cuota:**
- `monto_pagado` = SUM(pagos.monto) donde pagos.cuota_id = cuota.id
- `saldo_pendiente` = cuota.monto - monto_pagado
- `esta_pagada` = monto_pagado >= monto

**Plan de Pagos:**
- `monto_pagado` = SUM(cuota.monto_pagado) para todas las cuotas del plan
- `monto_pendiente` = monto_total - monto_pagado
- `esta_completo` = todas las cuotas están pagadas

---

## 📝 Notas Importantes

### Herencia PostgreSQL

- `ESTUDIANTE` y `DOCENTE` heredan de `PERSONA` usando PostgreSQL INHERITS
- Comparten el mismo `id` (generado desde la secuencia de `persona`)
- Los campos de `PERSONA` están disponibles directamente en `ESTUDIANTE` y `DOCENTE`

### Validación de Módulos en Grupos

- Al crear un grupo, el sistema valida que el módulo seleccionado pertenezca al programa
- Esto se hace consultando la tabla `programa_modulo`
- Evita inconsistencias en la estructura de datos

### Documentos Requeridos

- **Requeridos:** Carnet de Identidad (Anverso y Reverso), Certificado de Nacimiento
- **Opcional:** Título de Bachiller
- Solo los documentos requeridos cuentan para el cambio de estado

### Notificaciones

- El sistema envía notificaciones en puntos clave:
  - Estudiante sube 3 documentos → Notificación a admin
  - Admin aprueba/rechaza documentos → Notificación a estudiante
  - Inscripción exitosa → Notificación a estudiante

---

## 🔄 Resumen del Flujo Completo

```
ADMIN:
  1. Crea Institución
  2. Crea Programa
  3. Asocia Módulos al Programa
  4. Crea Grupo (con horarios y aulas)
  5. Valida documentos de estudiantes
  6. Verifica pagos

ESTUDIANTE:
  1. Se registra (estado_id = 1)
  2. Sube documentos (estado_id = 2 o 3)
  3. Espera validación (estado_id = 3)
  4. Si aprobado (estado_id = 4):
     - Consulta programas disponibles
     - Selecciona programa y grupo
     - Elige número de cuotas
     - Se inscribe
  5. Consulta cuotas pendientes
  6. Realiza pagos (QR o comprobante)
  7. Consulta materias y horarios
```

---

**Última actualización:** 2025-11-24
**Versión del documento:** 1.0

