-- ============================================
-- Script SQL para agregar columna Estado_id a la tabla estudiante
-- y establecer la relación foránea con estado_estudiante
-- ============================================

-- Verificar si la columna Estado_id ya existe
DO $$
BEGIN
    -- Agregar columna Estado_id si no existe
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'estudiante'
        AND column_name = 'Estado_id'
    ) THEN
        ALTER TABLE estudiante
        ADD COLUMN Estado_id BIGINT;

        RAISE NOTICE 'Columna Estado_id agregada a la tabla estudiante';
    ELSE
        RAISE NOTICE 'La columna Estado_id ya existe en la tabla estudiante';
    END IF;
END
$$;

-- Crear índice para mejorar el rendimiento
CREATE INDEX IF NOT EXISTS idx_estudiante_estado_id ON estudiante(Estado_id);

-- Agregar foreign key constraint si no existe
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_estudiante_estado_estudiante'
        AND table_name = 'estudiante'
    ) THEN
        ALTER TABLE estudiante
        ADD CONSTRAINT fk_estudiante_estado_estudiante
        FOREIGN KEY (Estado_id)
        REFERENCES estado_estudiante(estado_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

        RAISE NOTICE 'Foreign key fk_estudiante_estado_estudiante creada';
    ELSE
        RAISE NOTICE 'La foreign key fk_estudiante_estado_estudiante ya existe';
    END IF;
END
$$;

-- Establecer un valor por defecto para estudiantes existentes sin estado
-- (asumiendo que el estado_id 1 es el estado por defecto, ajustar según corresponda)
UPDATE estudiante
SET Estado_id = 1
WHERE Estado_id IS NULL;

-- Comentario de la columna
COMMENT ON COLUMN estudiante.Estado_id IS 'Referencia al estado del estudiante en la tabla estado_estudiante';

