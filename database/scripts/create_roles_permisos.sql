-- ============================================
-- Script SQL para crear tablas de Roles y Permisos
-- Sistema de Control de Acceso Basado en Roles (RBAC)
-- ============================================

-- 1. Tabla de Roles
CREATE TABLE IF NOT EXISTS roles (
    rol_id SERIAL PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para roles
CREATE INDEX IF NOT EXISTS idx_roles_nombre ON roles(nombre_rol);
CREATE INDEX IF NOT EXISTS idx_roles_activo ON roles(activo);

-- 2. Tabla de Permisos
CREATE TABLE IF NOT EXISTS permisos (
    permiso_id SERIAL PRIMARY KEY,
    nombre_permiso VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para permisos
CREATE INDEX IF NOT EXISTS idx_permisos_nombre ON permisos(nombre_permiso);
CREATE INDEX IF NOT EXISTS idx_permisos_modulo ON permisos(modulo);
CREATE INDEX IF NOT EXISTS idx_permisos_accion ON permisos(accion);
CREATE INDEX IF NOT EXISTS idx_permisos_activo ON permisos(activo);

-- 3. Tabla pivot: Rol-Permiso (Many-to-Many)
CREATE TABLE IF NOT EXISTS rol_permiso (
    rol_permiso_id SERIAL PRIMARY KEY,
    rol_id INTEGER NOT NULL,
    permiso_id INTEGER NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Claves foráneas
    CONSTRAINT fk_rol_permiso_rol FOREIGN KEY (rol_id)
        REFERENCES roles(rol_id) ON DELETE CASCADE,
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (permiso_id)
        REFERENCES permisos(permiso_id) ON DELETE CASCADE,

    -- Evitar duplicados
    CONSTRAINT uk_rol_permiso UNIQUE (rol_id, permiso_id)
);

-- Índices para rol_permiso
CREATE INDEX IF NOT EXISTS idx_rol_permiso_rol ON rol_permiso(rol_id);
CREATE INDEX IF NOT EXISTS idx_rol_permiso_permiso ON rol_permiso(permiso_id);
CREATE INDEX IF NOT EXISTS idx_rol_permiso_activo ON rol_permiso(activo);

-- 4. Verificar que la tabla usuario tenga columna rol_id
-- Si no existe, agregarla
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'usuario' AND column_name = 'rol_id'
    ) THEN
        ALTER TABLE usuario ADD COLUMN rol_id INTEGER NULL;
        CREATE INDEX IF NOT EXISTS idx_usuario_rol ON usuario(rol_id);

        -- Agregar clave foránea si es posible
        ALTER TABLE usuario
            ADD CONSTRAINT fk_usuario_rol
            FOREIGN KEY (rol_id) REFERENCES roles(rol_id) ON DELETE SET NULL;
    END IF;
END $$;

-- 5. Insertar roles del sistema
INSERT INTO roles (nombre_rol, descripcion, activo) VALUES
    ('ADMIN', 'Administrador del sistema con acceso completo', true),
    ('DOCENTE', 'Docente con acceso a grupos y notas', true),
    ('ESTUDIANTE', 'Estudiante con acceso a su portal personal', true)
ON CONFLICT (nombre_rol) DO NOTHING;

-- 6. Insertar permisos básicos del sistema
-- Módulo: Estudiantes
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('estudiantes_ver', 'Ver lista de estudiantes', 'estudiantes', 'ver', true),
    ('estudiantes_crear', 'Crear nuevo estudiante', 'estudiantes', 'crear', true),
    ('estudiantes_editar', 'Editar información de estudiante', 'estudiantes', 'editar', true),
    ('estudiantes_eliminar', 'Eliminar estudiante', 'estudiantes', 'eliminar', true),
    ('estudiantes_activar', 'Activar/desactivar estudiante', 'estudiantes', 'activar', true),
    ('estudiantes_ver_detalle', 'Ver detalle completo de estudiante', 'estudiantes', 'ver_detalle', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Docentes
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('docentes_ver', 'Ver lista de docentes', 'docentes', 'ver', true),
    ('docentes_crear', 'Crear nuevo docente', 'docentes', 'crear', true),
    ('docentes_editar', 'Editar información de docente', 'docentes', 'editar', true),
    ('docentes_eliminar', 'Eliminar docente', 'docentes', 'eliminar', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Programas
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('programas_ver', 'Ver lista de programas', 'programas', 'ver', true),
    ('programas_crear', 'Crear nuevo programa', 'programas', 'crear', true),
    ('programas_editar', 'Editar programa', 'programas', 'editar', true),
    ('programas_eliminar', 'Eliminar programa', 'programas', 'eliminar', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Grupos
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('grupos_ver', 'Ver lista de grupos', 'grupos', 'ver', true),
    ('grupos_crear', 'Crear nuevo grupo', 'grupos', 'crear', true),
    ('grupos_editar', 'Editar grupo', 'grupos', 'editar', true),
    ('grupos_eliminar', 'Eliminar grupo', 'grupos', 'eliminar', true),
    ('grupos_asignar_estudiantes', 'Asignar estudiantes a grupo', 'grupos', 'asignar_estudiantes', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Pagos
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('pagos_ver', 'Ver lista de pagos', 'pagos', 'ver', true),
    ('pagos_verificar', 'Verificar pago pendiente', 'pagos', 'verificar', true),
    ('pagos_aprobar', 'Aprobar pago', 'pagos', 'aprobar', true),
    ('pagos_rechazar', 'Rechazar pago', 'pagos', 'rechazar', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Documentos
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('documentos_ver', 'Ver lista de documentos', 'documentos', 'ver', true),
    ('documentos_validar', 'Validar documento pendiente', 'documentos', 'validar', true),
    ('documentos_aprobar', 'Aprobar documento', 'documentos', 'aprobar', true),
    ('documentos_rechazar', 'Rechazar documento', 'documentos', 'rechazar', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Notas
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('notas_ver', 'Ver notas', 'notas', 'ver', true),
    ('notas_crear', 'Crear nueva nota', 'notas', 'crear', true),
    ('notas_editar', 'Editar nota', 'notas', 'editar', true),
    ('notas_crear_masivo', 'Crear notas masivamente', 'notas', 'crear_masivo', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Reportes
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('reportes_ver', 'Ver reportes', 'reportes', 'ver', true),
    ('reportes_generar', 'Generar reportes', 'reportes', 'generar', true),
    ('reportes_exportar', 'Exportar reportes', 'reportes', 'exportar', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Roles
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('roles_ver', 'Ver lista de roles', 'roles', 'ver', true),
    ('roles_crear', 'Crear nuevo rol', 'roles', 'crear', true),
    ('roles_editar', 'Editar rol', 'roles', 'editar', true),
    ('roles_eliminar', 'Eliminar rol', 'roles', 'eliminar', true),
    ('roles_asignar_permisos', 'Asignar permisos a rol', 'roles', 'asignar_permisos', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- Módulo: Dashboard
INSERT INTO permisos (nombre_permiso, descripcion, modulo, accion, activo) VALUES
    ('dashboard_admin', 'Acceder al dashboard de administrador', 'dashboard', 'admin', true),
    ('dashboard_docente', 'Acceder al dashboard de docente', 'dashboard', 'docente', true),
    ('dashboard_estudiante', 'Acceder al dashboard de estudiante', 'dashboard', 'estudiante', true)
ON CONFLICT (nombre_permiso) DO NOTHING;

-- 7. Asignar todos los permisos al rol ADMIN
INSERT INTO rol_permiso (rol_id, permiso_id, activo)
SELECT
    r.rol_id,
    p.permiso_id,
    true
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ADMIN'
ON CONFLICT (rol_id, permiso_id) DO UPDATE SET activo = true;

-- 8. Asignar permisos específicos al rol DOCENTE
INSERT INTO rol_permiso (rol_id, permiso_id, activo)
SELECT
    r.rol_id,
    p.permiso_id,
    true
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'DOCENTE'
    AND p.modulo IN ('grupos', 'notas', 'dashboard')
    AND p.accion IN ('ver', 'crear', 'editar', 'crear_masivo', 'docente')
ON CONFLICT (rol_id, permiso_id) DO UPDATE SET activo = true;

-- 9. Asignar permisos específicos al rol ESTUDIANTE
INSERT INTO rol_permiso (rol_id, permiso_id, activo)
SELECT
    r.rol_id,
    p.permiso_id,
    true
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre_rol = 'ESTUDIANTE'
    AND p.modulo IN ('programas', 'pagos', 'documentos', 'notas', 'dashboard')
    AND p.accion IN ('ver', 'estudiante')
ON CONFLICT (rol_id, permiso_id) DO UPDATE SET activo = true;

-- Verificación final
SELECT 'Tablas de roles y permisos creadas exitosamente' AS mensaje;
SELECT COUNT(*) AS total_roles FROM roles;
SELECT COUNT(*) AS total_permisos FROM permisos;
SELECT COUNT(*) AS total_asignaciones FROM rol_permiso;

