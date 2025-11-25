-- ============================================
-- Script SQL para actualizar estados de estudiante
-- Asegura que existan los 5 estados correctos con IDs específicos
-- ============================================

-- Paso 1: Crear los 5 estados si no existen
-- Usar INSERT ... ON CONFLICT para PostgreSQL
INSERT INTO estado_estudiante (id, nombre_estado, created_at, updated_at)
VALUES 
    (1, 'Pre-registrado', NOW(), NOW()),
    (2, 'Documentos incompletos', NOW(), NOW()),
    (3, 'En revisión', NOW(), NOW()),
    (4, 'Validado - Activo', NOW(), NOW()),
    (5, 'Rechazado', NOW(), NOW())
ON CONFLICT (id) DO UPDATE
SET nombre_estado = EXCLUDED.nombre_estado,
    updated_at = NOW();

-- Actualizar la secuencia de PostgreSQL
SELECT setval('estado_estudiante_id_seq', GREATEST((SELECT MAX(id) FROM estado_estudiante), 1), true);

-- Paso 2: Actualizar estados existentes con nombres antiguos
-- Mapeo: pre-inscrito -> Pre-registrado (ID 1)
UPDATE estado_estudiante
SET nombre_estado = 'Pre-registrado', updated_at = NOW()
WHERE nombre_estado = 'pre-inscrito' AND id != 1;

-- Mapeo: inscrito -> Documentos incompletos (ID 2) si aplica
-- Nota: Este mapeo puede no ser exacto, ajustar según necesidad
UPDATE estado_estudiante
SET nombre_estado = 'Documentos incompletos', updated_at = NOW()
WHERE nombre_estado = 'inscrito' AND id != 2;

-- Mapeo: validado -> Validado - Activo (ID 4)
UPDATE estado_estudiante
SET nombre_estado = 'Validado - Activo', updated_at = NOW()
WHERE nombre_estado = 'validado' AND id != 4;

-- Mapeo: Rechazado -> Rechazado (ID 5)
UPDATE estado_estudiante
SET nombre_estado = 'Rechazado', updated_at = NOW()
WHERE nombre_estado = 'Rechazado' AND id != 5;

-- Paso 3: Si hay estados con IDs incorrectos pero nombres correctos, actualizar referencias de estudiantes
-- Esto es para casos donde el estado tiene el nombre correcto pero el ID no coincide

-- Actualizar estudiantes que tienen estado_id apuntando a estados con nombres antiguos
-- pero que deberían apuntar a los nuevos IDs

-- Pre-registrado (nombre antiguo: pre-inscrito)
UPDATE estudiante e
SET Estado_id = 1
FROM estado_estudiante es
WHERE e.Estado_id = es.id 
  AND es.nombre_estado = 'pre-inscrito'
  AND es.id != 1;

-- Validado - Activo (nombre antiguo: validado)
UPDATE estudiante e
SET Estado_id = 4
FROM estado_estudiante es
WHERE e.Estado_id = es.id 
  AND es.nombre_estado = 'validado'
  AND es.id != 4;

-- Rechazado
UPDATE estudiante e
SET Estado_id = 5
FROM estado_estudiante es
WHERE e.Estado_id = es.id 
  AND es.nombre_estado = 'Rechazado'
  AND es.id != 5;

-- Paso 4: Eliminar estados duplicados o incorrectos (solo si no tienen estudiantes asociados)
DELETE FROM estado_estudiante
WHERE id NOT IN (1, 2, 3, 4, 5)
  AND id NOT IN (
    SELECT DISTINCT Estado_id 
    FROM estudiante 
    WHERE Estado_id IS NOT NULL
  );

-- Paso 5: Verificar que todos los estudiantes tengan un estado válido
-- Si un estudiante tiene un estado_id que no existe, asignarle el estado por defecto (1)
UPDATE estudiante
SET Estado_id = 1
WHERE Estado_id IS NOT NULL
  AND Estado_id NOT IN (SELECT id FROM estado_estudiante);

-- Verificar resultados
SELECT 
    id,
    nombre_estado,
    (SELECT COUNT(*) FROM estudiante WHERE Estado_id = estado_estudiante.id) as estudiantes_asociados
FROM estado_estudiante
ORDER BY id;

