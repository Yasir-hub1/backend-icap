#!/bin/bash

# Script de instalación del sistema de roles y permisos
# Sistema Académico ICAP

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "   🔐 INSTALACIÓN DEL SISTEMA DE ROLES Y PERMISOS"
echo "═══════════════════════════════════════════════════════════"
echo ""

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo "❌ Error: Este script debe ejecutarse desde el directorio backend/"
    echo "   Navega a la carpeta backend y ejecuta: bash install-roles.sh"
    exit 1
fi

echo "📋 Paso 1/3: Verificando dependencias..."
echo ""

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP no está instalado"
    exit 1
fi

echo "   ✅ PHP: $(php -v | head -n 1)"

# Verificar Composer
if ! command -v composer &> /dev/null; then
    echo "❌ Error: Composer no está instalado"
    exit 1
fi

echo "   ✅ Composer instalado"

# Verificar conexión a base de datos
echo ""
echo "📋 Paso 2/3: Verificando conexión a base de datos..."
echo ""

php artisan db:show > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "❌ Error: No se puede conectar a la base de datos"
    echo "   Verifica tu archivo .env y asegúrate de que la base de datos esté corriendo"
    exit 1
fi

echo "   ✅ Conexión a base de datos OK"

# Preguntar si desea continuar
echo ""
echo "═══════════════════════════════════════════════════════════"
echo "   ⚠️  ADVERTENCIA"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "Este script va a:"
echo "  1. Crear 48 permisos en la tabla 'permisos'"
echo "  2. Crear 3 roles en la tabla 'roles' (ADMIN, DOCENTE, ESTUDIANTE)"
echo "  3. Asignar permisos a cada rol en la tabla 'rol_permiso'"
echo ""
echo "Si ya existen roles o permisos, se actualizarán (no se duplicarán)."
echo ""
read -p "¿Deseas continuar? (s/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[SsYy]$ ]]; then
    echo ""
    echo "❌ Instalación cancelada"
    exit 0
fi

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "   🚀 INICIANDO INSTALACIÓN"
echo "═══════════════════════════════════════════════════════════"
echo ""

# Ejecutar seeders
echo "📋 Paso 3/3: Ejecutando seeders..."
echo ""

echo "   → Creando permisos del sistema..."
php artisan db:seed --class=PermisosSeeder

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Error al crear permisos"
    echo "   Revisa los logs en storage/logs/laravel.log"
    exit 1
fi

echo ""
echo "   → Creando roles y asignando permisos..."
php artisan db:seed --class=RolesSeeder

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Error al crear roles"
    echo "   Revisa los logs en storage/logs/laravel.log"
    exit 1
fi

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "   ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE"
echo "═══════════════════════════════════════════════════════════"
echo ""

# Verificar instalación
echo "📊 Verificando instalación..."
echo ""

ROLES_COUNT=$(php artisan tinker --execute="echo App\\Models\\Rol::count();" 2>/dev/null)
PERMISOS_COUNT=$(php artisan tinker --execute="echo App\\Models\\Permiso::count();" 2>/dev/null)

echo "   ✅ Roles creados: $ROLES_COUNT"
echo "   ✅ Permisos creados: $PERMISOS_COUNT"

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "   📋 RESUMEN"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "Roles creados:"
echo "  • ADMIN       - Acceso completo al sistema (48 permisos)"
echo "  • DOCENTE     - Gestión de grupos y notas (8 permisos)"
echo "  • ESTUDIANTE  - Portal personal (5 permisos)"
echo ""
echo "Próximos pasos:"
echo "  1. Inicia el servidor: php artisan serve"
echo "  2. Inicia el frontend: cd ../frontend && npm run dev"
echo "  3. Accede a: http://localhost:5173/admin/roles"
echo ""
echo "📚 Documentación completa:"
echo "   • backend/SETUP_ROLES.md"
echo "   • ../CORRECCION_ROLES_COMPLETA.md"
echo ""
echo "═══════════════════════════════════════════════════════════"
echo "   🎉 ¡TODO LISTO!"
echo "═══════════════════════════════════════════════════════════"
echo ""
