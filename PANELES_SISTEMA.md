# 📊 Documentación de Paneles del Sistema ICAP

## 📋 Índice

1. [Panel Administrativo](#panel-administrativo)
2. [Panel Estudiante](#panel-estudiante)
3. [Resumen de Funcionalidades](#resumen-de-funcionalidades)

---

## 🔐 Panel Administrativo

El panel administrativo es el centro de control del sistema, permitiendo la gestión completa de todos los aspectos académicos, financieros y administrativos.

### 🏠 Dashboard
**Ruta:** `/admin/dashboard`

**Descripción:**
- Vista general del sistema con estadísticas clave
- Métricas de estudiantes, docentes, programas activos
- Resumen de inscripciones y pagos
- Indicadores de rendimiento académico
- Gráficos y visualizaciones de datos

---

### 👥 Gestión de Usuarios

#### Usuarios
**Ruta:** `/admin/usuarios`

**Descripción:**
- CRUD completo de usuarios del sistema
- Gestión de cuentas de administradores
- Asignación de roles y permisos
- Activación/desactivación de usuarios
- Búsqueda y filtrado de usuarios

#### Sistema de Usuarios
**Ruta:** `/admin/sistema-usuarios`

**Descripción:**
- Configuración avanzada del sistema de usuarios
- Gestión de perfiles y permisos
- Configuración de políticas de seguridad
- Administración de sesiones

#### Roles
**Ruta:** `/admin/roles`

**Descripción:**
- Creación y gestión de roles del sistema
- Asignación de permisos por rol
- Configuración de permisos granulares
- Gestión de jerarquías de roles

---

### 🎓 Gestión Académica

#### Estudiantes
**Ruta:** `/admin/estudiantes`

**Descripción:**
- CRUD completo de estudiantes
- Visualización de estado de documentos
- Activación/desactivación de estudiantes
- Gestión de estados del estudiante (Pre-registrado, En revisión, Validado, etc.)
- Visualización de inscripciones por estudiante
- Exportación de datos de estudiantes
- Búsqueda y filtrado avanzado

#### Docentes
**Ruta:** `/admin/docentes`

**Descripción:**
- CRUD completo de docentes
- Asignación de docentes a grupos
- Gestión de información personal y académica
- Visualización de grupos asignados
- Historial de docentes

#### Programas
**Ruta:** `/admin/programas`

**Descripción:**
- CRUD completo de programas académicos
- Configuración de duración, costo, módulos
- Asociación de programas con instituciones
- Gestión de ramas académicas y tipos de programa
- Visualización de grupos asociados
- Estadísticas de inscripciones por programa

#### Módulos
**Ruta:** `/admin/modulos`

**Descripción:**
- CRUD completo de módulos académicos
- Asociación de módulos a programas (tabla pivote `programa_modulo`)
- Gestión de contenido y estructura de módulos
- Visualización de grupos que usan cada módulo

#### Grupos
**Ruta:** `/admin/grupos`

**Descripción:**
- CRUD completo de grupos académicos
- Asignación de programa y módulo (validación de relación)
- Asignación de docente responsable
- Configuración de fechas de inicio y fin
- Gestión de horarios y aulas
- Visualización de estudiantes inscritos
- Control de cupos (máximo 30 estudiantes)
- Filtrado de módulos por programa seleccionado

#### Horarios
**Ruta:** `/admin/horarios`

**Descripción:**
- CRUD completo de horarios
- Configuración de días de la semana
- Definición de horas de inicio y fin
- Asociación de horarios a grupos
- Gestión de aulas por horario

#### Aulas
**Ruta:** `/admin/aulas`

**Descripción:**
- CRUD completo de aulas físicas
- Gestión de capacidad y características
- Asignación de aulas a horarios de grupos
- Visualización de disponibilidad

#### Materias
**Ruta:** `/admin/materias`

**Descripción:**
- Gestión de materias del sistema
- Asociación de materias con programas
- Configuración de contenidos y créditos
- Visualización de grupos por materia

#### Asistencias
**Ruta:** `/admin/asistencias`

**Descripción:**
- Control de asistencia de estudiantes
- Registro de asistencias por grupo
- Visualización de estadísticas de asistencia
- Reportes de asistencia por estudiante/grupo
- Gestión de justificaciones

#### Gestiones Académicas
**Ruta:** `/admin/gestiones-academicas`

**Descripción:**
- Gestión de períodos académicos
- Configuración de gestiones (semestres, trimestres, etc.)
- Definición de fechas importantes
- Control de calendario académico

---

### 📝 Gestión de Inscripciones

#### Inscripciones
**Ruta:** `/admin/inscripciones`

**Descripción:**
- Visualización de todas las inscripciones
- Detalle de inscripciones por estudiante/programa
- Gestión de estados de inscripción
- Visualización de planes de pago asociados
- Estadísticas de inscripciones
- Búsqueda y filtrado avanzado

---

### 💰 Gestión Financiera

#### Pagos
**Ruta:** `/admin/pagos`

**Descripción:**
- Visualización de todos los pagos del sistema
- Historial de pagos por estudiante
- Filtrado por estado (pendiente, verificado, rechazado)
- Estadísticas de pagos
- Exportación de reportes financieros

#### Planes de Pago
**Ruta:** `/admin/planes-pago`

**Descripción:**
- Gestión de planes de pago
- Visualización de planes por inscripción
- Configuración de cuotas
- Seguimiento de estado de planes (completo, pendiente)
- Cálculo de montos pagados y pendientes

#### Descuentos
**Ruta:** `/admin/descuentos`

**Descripción:**
- CRUD completo de descuentos
- Creación de descuentos vigentes
- Descuentos por convenio
- Descuentos promocionales
- Asignación de descuentos a inscripciones
- Gestión de porcentajes y montos

#### Gestión de Pagos
**Ruta:** `/admin/gestion-pagos`

**Descripción:**
- Verificación de pagos con comprobante
- Aprobación/rechazo de pagos
- Asignación de verificador
- Gestión de observaciones
- Control de pagos pendientes de verificación

---

### 📄 Gestión de Documentos

#### Validación de Documentos
**Ruta:** `/admin/validacion-documentos` o `/admin/documentos`

**Descripción:**
- Visualización de estudiantes con documentos pendientes de revisión
- Aprobación/rechazo de documentos individuales
- Visualización previa de documentos (imágenes y PDFs)
- Asignación de observaciones al rechazar
- Cambio automático de estado del estudiante al aprobar todos los documentos
- Notificaciones a estudiantes sobre estado de documentos

#### Tipos de Documento
**Ruta:** `/admin/tipos-documento`

**Descripción:**
- CRUD completo de tipos de documentos
- Configuración de documentos requeridos
- Definición de documentos opcionales
- Gestión de categorías de documentos

---

### 🏢 Gestión Institucional

#### Instituciones
**Ruta:** `/admin/instituciones`

**Descripción:**
- CRUD completo de instituciones
- Gestión de información institucional
- Asociación de programas con instituciones
- Control de estado (activo/inactivo)
- Gestión de convenios institucionales

#### Convenios
**Ruta:** `/admin/convenios`

**Descripción:**
- CRUD completo de convenios
- Gestión de convenios entre instituciones
- Configuración de fechas y montos
- Asociación de instituciones a convenios
- Control de estado de convenios

#### Tipo de Convenios
**Ruta:** `/admin/tipo-convenios`

**Descripción:**
- CRUD completo de tipos de convenio
- Categorización de convenios
- Configuración de características por tipo

---

### 📚 Configuración Académica

#### Ramas Académicas
**Ruta:** `/admin/ramas-academicas`

**Descripción:**
- CRUD completo de ramas académicas
- Categorización de programas por rama
- Organización de la estructura académica

#### Versiones
**Ruta:** `/admin/versiones`

**Descripción:**
- CRUD completo de versiones académicas
- Gestión de versiones de programas
- Control de versionado de contenido académico

#### Tipos de Programa
**Ruta:** `/admin/tipos-programa`

**Descripción:**
- CRUD completo de tipos de programa
- Categorización de programas (diplomado, curso, especialización, etc.)
- Configuración de características por tipo

---

### 🌍 Configuración Geográfica

#### Países
**Ruta:** `/admin/paises`

**Descripción:**
- CRUD completo de países
- Gestión de datos geográficos base
- Configuración de códigos y nombres

#### Provincias
**Ruta:** `/admin/provincias`

**Descripción:**
- CRUD completo de provincias
- Asociación de provincias a países
- Gestión de datos geográficos regionales

#### Ciudades
**Ruta:** `/admin/ciudades`

**Descripción:**
- CRUD completo de ciudades
- Asociación de ciudades a provincias
- Gestión de ubicaciones de instituciones

---

### 📊 Reportes y Análisis

#### Reportes
**Ruta:** `/admin/reportes`

**Descripción:**
- Generación de reportes académicos
- Reportes financieros
- Reportes de inscripciones
- Reportes de estudiantes por estado
- Reportes de programas ofrecidos
- Reportes de convenios activos
- Reportes de movimientos financieros
- Reportes de actividad por usuario
- Reportes de actividad por institución
- Exportación de reportes en diferentes formatos

#### Auditoría
**Ruta:** `/admin/auditoria`

**Descripción:**
- Visualización de registros de auditoría
- Trazabilidad de acciones del sistema
- Historial de cambios en entidades críticas
- Logs de acceso y operaciones

---

### 📝 Bitácora
**Ruta:** `/admin/bitacora`

**Descripción:**
- Registro completo de actividades del sistema
- Historial de transacciones
- Seguimiento de cambios en datos
- Búsqueda y filtrado de registros
- Estadísticas de actividad

---

### 🔔 Notificaciones
**Ruta:** `/admin/notificaciones`

**Descripción:**
- Visualización de todas las notificaciones del sistema
- Notificaciones recibidas por el administrador
- Notificaciones sobre documentos pendientes de revisión
- Notificaciones sobre inscripciones nuevas
- Marcar notificaciones como leídas
- Filtrado por tipo y estado

---

## 🎓 Panel Estudiante

El panel estudiantil permite a los estudiantes gestionar su información académica, documentos, inscripciones y pagos.

### 🏠 Dashboard
**Ruta:** `/estudiante/dashboard`

**Descripción:**
- Vista general del estado del estudiante
- Indicador de estado de cuenta (activo/inactivo)
- Progreso de carga de documentos
- Estadísticas de documentos (requeridos, subidos, aprobados)
- Alertas y mensajes según estado del estudiante
- Accesos rápidos a acciones pendientes
- Información de inscripciones activas
- Resumen de pagos pendientes

**Estados y Mensajes:**
- **Pre-registrado (estado_id = 1):** Alerta urgente para subir documentos
- **Documentos incompletos (estado_id = 2):** Alerta de atención con progreso
- **En revisión (estado_id = 3):** Mensaje de espera de validación
- **Validado - Activo (estado_id = 4):** Banner de cuenta activa, puede inscribirse
- **Rechazado (estado_id = 5):** Alerta de documentos rechazados con motivos

---

### 📚 Inscripciones
**Ruta:** `/estudiante/inscripciones`

**Descripción:**
- Visualización de programas disponibles para inscripción
- Filtrado automático: solo programas con grupos activos y cupos disponibles
- Información detallada de cada programa:
  - Nombre, costo, duración
  - Institución, rama académica, tipo de programa
  - Grupos disponibles con horarios y aulas
  - Cupos disponibles por grupo
- Selección de programa y grupo específico
- Verificación automática de conflictos de horarios
- Selección de descuento (opcional):
  - Descuentos vigentes
  - Descuentos por convenio
  - Descuentos promocionales
- Selección de número de cuotas según reglas del programa
- Cálculo automático de costo final con descuento
- Cálculo de monto por cuota
- Confirmación de inscripción con resumen completo
- Notificación de inscripción exitosa

**Validaciones:**
- Estudiante debe tener estado_id = 4 (Validado - Activo)
- Grupo debe tener cupos disponibles (máximo 30)
- No debe haber conflictos de horarios con grupos ya inscritos
- Número de cuotas debe estar dentro de las reglas del programa

---

### 📖 Materias
**Ruta:** `/estudiante/materias`

**Descripción:**
- Visualización de todas las materias/programas inscritos
- Información detallada por materia:
  - Nombre del programa y módulo
  - Estado de la materia (Aprobada, En Curso, Pendiente)
  - Información del programa (duración, costo, institución)
  - Información del grupo (docente, fecha de inscripción, período)
  - Horarios completos con aulas
- Estadísticas generales:
  - Total de materias
  - Materias aprobadas
  - Materias en curso
- Estado de pagos por materia:
  - Monto total, pagado y pendiente
  - Progreso de pago con barra visual
  - Cuotas pagadas vs total de cuotas

---

### 📄 Documentos
**Ruta:** `/estudiante/documentos` o `/estudiante/mis-documentos`

**Descripción:**
- Visualización de todos los documentos del estudiante
- Estado de cada documento:
  - **Pendiente (estado = '0'):** Esperando revisión
  - **Aprobado (estado = '1'):** Documento validado
  - **Rechazado (estado = '2'):** Documento rechazado con observaciones
  - **No subido:** Documento faltante
- Información detallada:
  - Versión del documento
  - Fecha de subida
  - Observaciones del administrador
- Acciones disponibles:
  - **Ver documento:** Visualización en modal grande
  - **Imprimir:** Impresión directa del documento
  - **Subir/Reemplazar:** Carga de nuevos documentos
- Indicador especial para documentos opcionales (Título de Bachiller)
- Progreso visual de documentos requeridos

---

### 💳 Pagos
**Ruta:** `/estudiante/pagos` o `/estudiante/mis-pagos`

**Descripción:**
- Visualización de todos los planes de pago agrupados por inscripción
- Estadísticas generales:
  - Total a pagar
  - Total pagado
  - Total pendiente
  - Cuotas pendientes
- Resumen por plan de pago:
  - Programa asociado
  - Monto total, pagado y pendiente
  - Porcentaje de progreso
  - Barra de progreso visual
  - Estado del plan (completo/pendiente)
- Detalle de cuotas (expandible):
  - Número de cuota
  - Fechas de inicio y vencimiento
  - Monto de la cuota
  - Estado (Pendiente, Pagada, Vencida)
  - Saldo pendiente
  - Historial de pagos realizados
- Acciones de pago:
  - **Pagar con QR:** Generación de código QR para pago
  - **Subir comprobante:** Carga de comprobante para verificación manual
- Alerta de cuotas pendientes con monto total
- Botón de acción rápida para pagar primera cuota pendiente

---

### 📝 Notas
**Ruta:** `/estudiante/notas`

**Descripción:**
- Visualización de notas por grupo/materia
- Calificaciones obtenidas en cada evaluación
- Promedio por materia
- Historial académico
- Filtrado por grupo o período

---

### 🔔 Notificaciones
**Ruta:** `/estudiante/notificaciones`

**Descripción:**
- Visualización de todas las notificaciones recibidas
- Notificaciones sobre:
  - Estado de documentos (aprobados/rechazados)
  - Inscripciones exitosas
  - Planes de pago creados
  - Recordatorios de pagos
  - Actualizaciones académicas
- Fecha y hora completa de cada notificación
- Tiempo relativo ("hace X días/horas")
- Marcar notificaciones como leídas
- Filtrado por tipo y estado

---

### 👤 Perfil
**Ruta:** `/estudiante/perfil`

**Descripción:**
- Visualización de información personal
- Edición de datos de contacto
- Cambio de contraseña
- Actualización de fotografía
- Visualización de estado académico

---

## 📊 Resumen de Funcionalidades

### Panel Administrativo - Módulos por Categoría

| Categoría | Módulos | Total |
|-----------|---------|-------|
| **Dashboard** | Dashboard | 1 |
| **Usuarios** | Usuarios, Sistema de Usuarios, Roles | 3 |
| **Académico** | Estudiantes, Docentes, Programas, Módulos, Grupos, Horarios, Aulas, Materias, Asistencias, Gestiones Académicas | 10 |
| **Inscripciones** | Inscripciones | 1 |
| **Financiero** | Pagos, Planes de Pago, Descuentos, Gestión de Pagos | 4 |
| **Documentos** | Validación de Documentos, Tipos de Documento | 2 |
| **Institucional** | Instituciones, Convenios, Tipo de Convenios | 3 |
| **Configuración Académica** | Ramas Académicas, Versiones, Tipos de Programa | 3 |
| **Geográfico** | Países, Provincias, Ciudades | 3 |
| **Reportes** | Reportes, Auditoría, Bitácora | 3 |
| **Notificaciones** | Notificaciones | 1 |
| **TOTAL** | | **34 módulos** |

### Panel Estudiante - Módulos

| Módulo | Descripción Principal |
|--------|----------------------|
| **Dashboard** | Vista general y estado del estudiante |
| **Inscripciones** | Inscripción a programas disponibles |
| **Materias** | Visualización de materias/programas inscritos |
| **Documentos** | Gestión de documentos personales |
| **Pagos** | Gestión de cuotas y pagos |
| **Notas** | Visualización de calificaciones |
| **Notificaciones** | Notificaciones recibidas |
| **Perfil** | Información personal |
| **TOTAL** | **8 módulos** |

---

## 🔑 Características Clave

### Panel Administrativo

- **Control Total:** Gestión completa de todos los aspectos del sistema
- **Permisos Granulares:** Control de acceso basado en roles y permisos
- **Trazabilidad:** Bitácora y auditoría de todas las operaciones
- **Reportes Completos:** Análisis y exportación de datos
- **Validaciones:** Control de integridad de datos y relaciones
- **Notificaciones:** Sistema de alertas para acciones importantes

### Panel Estudiante

- **Autogestión:** El estudiante puede gestionar su información y procesos
- **Transparencia:** Visualización clara de estados y procesos
- **Guía Visual:** Indicadores y alertas según el estado del estudiante
- **Proceso Simplificado:** Flujo claro de inscripción con validaciones automáticas
- **Información Completa:** Acceso a toda la información académica y financiera
- **Notificaciones:** Comunicación directa sobre cambios de estado

---

## 🔄 Flujo de Interacción entre Paneles

```
ADMIN crea/configura:
  → Programas, Grupos, Horarios, Aulas
  → Descuentos y reglas de cuotas
  ↓
ESTUDIANTE se registra:
  → Sube documentos
  ↓
ADMIN valida documentos:
  → Aprueba/rechaza documentos
  → Cambia estado del estudiante
  ↓
ESTUDIANTE (si activo) se inscribe:
  → Selecciona programa y grupo
  → Aplica descuento (si aplica)
  → Elige número de cuotas
  → Confirma inscripción
  ↓
SISTEMA crea automáticamente:
  → Inscripción
  → Plan de Pagos
  → Cuotas
  → Asociación a grupo
  ↓
ESTUDIANTE realiza pagos:
  → Paga cuotas (QR o comprobante)
  ↓
ADMIN verifica pagos:
  → Aprueba/rechaza comprobantes
  → Actualiza estado de cuotas
  ↓
ESTUDIANTE consulta:
  → Materias inscritas
  → Estado de pagos
  → Notas y calificaciones
```

---

**Última actualización:** 2025-11-24
**Versión del documento:** 1.0

