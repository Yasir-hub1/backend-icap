# 🔄 Guía para Actualizar Estados de Estudiante

## 📋 Resumen

Se ha actualizado el sistema para manejar **5 estados** de estudiante con IDs específicos, eliminando inconsistencias entre el seeder y el código.

## 🎯 Estados Definidos

| ID | Nombre | Descripción |
|---|---|---|
| 1 | Pre-registrado | Estudiante recién registrado |
| 2 | Documentos incompletos | Faltan algunos documentos |
| 3 | En revisión | Documentos pendientes de validación |
| 4 | Validado - Activo | Documentos aprobados, puede inscribirse |
| 5 | Rechazado | Documentos rechazados |

## 🚀 Cómo Aplicar los Cambios

### Opción 1: Ejecutar el Seeder (Recomendado)

El seeder actualizado maneja automáticamente:
- ✅ Crear estados con IDs específicos
- ✅ Actualizar estados existentes
- ✅ Migrar estudiantes a nuevos IDs
- ✅ Eliminar estados duplicados

```bash
cd backend
php artisan db:seed --class=EstadoEstudianteSeeder
```

### Opción 2: Ejecutar el Script SQL

Si prefieres ejecutar directamente en la base de datos:

```bash
# PostgreSQL
psql -U tu_usuario -d tu_base_de_datos -f database/scripts/actualizar_estados_estudiante.sql
```

O desde Laravel Tinker:
```php
php artisan tinker
>>> DB::unprepared(file_get_contents('database/scripts/actualizar_estados_estudiante.sql'));
```

## ✅ Verificación

Después de ejecutar el seeder o script, verifica que los estados estén correctos:

```sql
SELECT id, nombre_estado, 
       (SELECT COUNT(*) FROM estudiante WHERE Estado_id = estado_estudiante.id) as estudiantes
FROM estado_estudiante
ORDER BY id;
```

Deberías ver exactamente 5 estados con los IDs 1, 2, 3, 4, 5.

## 📝 Cambios Realizados

1. ✅ **Seeder actualizado** (`EstadoEstudianteSeeder.php`)
   - Crea 5 estados con IDs específicos
   - Actualiza estados existentes con nombres antiguos
   - Migra estudiantes automáticamente

2. ✅ **Script SQL creado** (`actualizar_estados_estudiante.sql`)
   - Alternativa para actualización directa en BD
   - Maneja migración de datos existentes

3. ✅ **Documentación actualizada** (`ESTADOS_ESTUDIANTE.md`)
   - Documentación completa de todos los estados
   - Flujos y transiciones
   - Lógica de "activo"

## ⚠️ Notas Importantes

- El seeder es **idempotente**: puedes ejecutarlo múltiples veces sin problemas
- Los estudiantes existentes se migran automáticamente a los nuevos IDs
- Los estados duplicados se eliminan solo si no tienen estudiantes asociados
- La secuencia de PostgreSQL se actualiza automáticamente

## 🔍 Troubleshooting

Si encuentras problemas:

1. **Verificar que la tabla existe:**
   ```sql
   SELECT * FROM estado_estudiante;
   ```

2. **Verificar estudiantes sin estado:**
   ```sql
   SELECT * FROM estudiante WHERE Estado_id IS NULL;
   ```

3. **Verificar referencias rotas:**
   ```sql
   SELECT e.* FROM estudiante e
   LEFT JOIN estado_estudiante es ON e.Estado_id = es.id
   WHERE e.Estado_id IS NOT NULL AND es.id IS NULL;
   ```

