<?php

/**
 * Script de pruebas para verificar endpoints del backend Laravel
 * Ejecutar con: php test-endpoints.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Configuración
$baseUrl = 'http://localhost:8000';
$apiUrl = $baseUrl . '/api';

// Colores para consola
class Colors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const BOLD = "\033[1m";
    const RESET = "\033[0m";
}

// Función para imprimir resultados
function printResult($test, $status, $message = '') {
    $statusColor = $status === 'PASS' ? Colors::GREEN : Colors::RED;
    $statusText = $status === 'PASS' ? '✓' : '✗';

    echo "{$statusColor}{$statusText}{Colors::RESET} {$test}\n";
    if ($message) {
        echo "   " . Colors::YELLOW . $message . Colors::RESET . "\n";
    }
}

// Función para imprimir sección
function printSection($title) {
    echo "\n" . Colors::BOLD . Colors::BLUE . "=== {$title} ===" . Colors::RESET . "\n";
}

// Función para hacer petición HTTP
function makeRequest($method, $endpoint, $data = null) {
    $url = $GLOBALS['apiUrl'] . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error, 'status' => 0];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data, 'status' => $httpCode];
    } else {
        return ['success' => false, 'error' => $data, 'status' => $httpCode];
    }
}

// Pruebas de conectividad
function testConnectivity() {
    printSection('PRUEBAS DE CONECTIVIDAD');

    // 1. Verificar que el servidor esté ejecutándose
    $healthCheck = makeRequest('GET', '/health');
    if ($healthCheck['success']) {
        printResult('Servidor Laravel', 'PASS', 'Servidor respondiendo correctamente');
    } else {
        printResult('Servidor Laravel', 'FAIL', 'Servidor no disponible - Verificar que esté ejecutándose');
        return false;
    }

    // 2. Verificar conexión a base de datos
    try {
        $pdo = new PDO(
            'pgsql:host=localhost;dbname=instituto_capacitacion',
            'postgres',
            'password'
        );
        printResult('Base de Datos PostgreSQL', 'PASS', 'Conexión exitosa');
    } catch (PDOException $e) {
        printResult('Base de Datos PostgreSQL', 'FAIL', 'Error de conexión: ' . $e->getMessage());
        return false;
    }

    return true;
}

// Pruebas de rutas de catálogos
function testCatalogRoutes() {
    printSection('PRUEBAS DE RUTAS DE CATÁLOGOS');

    $catalogos = [
        'Países' => '/paises',
        'Provincias' => '/provincias',
        'Ciudades' => '/ciudades',
        'Ramas Académicas' => '/ramas-academicas',
        'Tipos de Programa' => '/tipos-programa',
        'Módulos' => '/modulos',
        'Versiones' => '/versiones',
        'Estados Estudiante' => '/estados-estudiante',
        'Tipos Convenio' => '/tipos-convenio',
        'Tipos Documento' => '/tipos-documento',
        'Descuentos' => '/descuentos',
        'Horarios' => '/horarios',
        'Planes de Pago' => '/planes-pago',
        'Cuotas' => '/cuotas'
    ];

    foreach ($catalogos as $name => $endpoint) {
        $result = makeRequest('GET', $endpoint);
        if ($result['success']) {
            $count = is_array($result['data']) ? count($result['data']) : 0;
            printResult($name, 'PASS', "{$count} registros encontrados");
        } else {
            printResult($name, 'FAIL', "Error {$result['status']}: " . ($result['error']['message'] ?? 'Sin respuesta'));
        }
    }
}

// Pruebas de rutas principales
function testMainRoutes() {
    printSection('PRUEBAS DE RUTAS PRINCIPALES');

    $rutas = [
        'Estudiantes' => '/estudiantes',
        'Docentes' => '/docentes',
        'Programas' => '/programas',
        'Instituciones' => '/instituciones',
        'Convenios' => '/convenios',
        'Grupos' => '/grupos',
        'Inscripciones' => '/inscripciones',
        'Pagos' => '/pagos',
        'Documentos' => '/documentos',
        'Bitácora' => '/bitacora'
    ];

    foreach ($rutas as $name => $endpoint) {
        $result = makeRequest('GET', $endpoint);
        if ($result['success']) {
            $count = is_array($result['data']) ? count($result['data']) : 0;
            printResult($name, 'PASS', "{$count} registros encontrados");
        } else {
            printResult($name, 'FAIL', "Error {$result['status']}: " . ($result['error']['message'] ?? 'Sin respuesta'));
        }
    }
}

// Pruebas de dashboards
function testDashboardRoutes() {
    printSection('PRUEBAS DE DASHBOARDS');

    $dashboards = [
        'Dashboard Admin' => '/dashboard/admin',
        'Dashboard Docente' => '/dashboard/docente',
        'Dashboard Estudiante' => '/dashboard/estudiante'
    ];

    foreach ($dashboards as $name => $endpoint) {
        $result = makeRequest('GET', $endpoint);
        if ($result['success']) {
            printResult($name, 'PASS', 'Dashboard funcionando');
        } else {
            printResult($name, 'FAIL', "Error {$result['status']}: " . ($result['error']['message'] ?? 'Sin respuesta'));
        }
    }
}

// Pruebas de CRUD
function testCRUDOperations() {
    printSection('PRUEBAS DE OPERACIONES CRUD');

    // 1. Crear un país de prueba
    echo "\n1. Crear País de Prueba:\n";
    $createData = [
        'nombre_pais' => 'País de Prueba',
        'codigo_iso' => 'PT',
        'codigo_telefono' => '+999'
    ];

    $createResult = makeRequest('POST', '/paises', $createData);
    if ($createResult['success']) {
        printResult('Crear País', 'PASS', "ID: {$createResult['data']['id']}");
        $paisId = $createResult['data']['id'];

        // 2. Leer el país creado
        echo "\n2. Leer País Creado:\n";
        $readResult = makeRequest('GET', "/paises/{$paisId}");
        if ($readResult['success']) {
            printResult('Leer País', 'PASS', "Nombre: {$readResult['data']['nombre_pais']}");
        } else {
            printResult('Leer País', 'FAIL', "Error: " . ($readResult['error']['message'] ?? 'Sin respuesta'));
        }

        // 3. Actualizar el país
        echo "\n3. Actualizar País:\n";
        $updateData = [
            'nombre_pais' => 'País de Prueba Actualizado',
            'codigo_iso' => 'PTA',
            'codigo_telefono' => '+9999'
        ];

        $updateResult = makeRequest('PUT', "/paises/{$paisId}", $updateData);
        if ($updateResult['success']) {
            printResult('Actualizar País', 'PASS', 'Actualización exitosa');
        } else {
            printResult('Actualizar País', 'FAIL', "Error: " . ($updateResult['error']['message'] ?? 'Sin respuesta'));
        }

        // 4. Eliminar el país
        echo "\n4. Eliminar País:\n";
        $deleteResult = makeRequest('DELETE', "/paises/{$paisId}");
        if ($deleteResult['success']) {
            printResult('Eliminar País', 'PASS', 'Eliminación exitosa');
        } else {
            printResult('Eliminar País', 'FAIL', "Error: " . ($deleteResult['error']['message'] ?? 'Sin respuesta'));
        }

    } else {
        printResult('Crear País', 'FAIL', "Error: " . ($createResult['error']['message'] ?? 'Sin respuesta'));
    }
}

// Pruebas de autenticación
function testAuthentication() {
    printSection('PRUEBAS DE AUTENTICACIÓN');

    // 1. Login de estudiante
    echo "\n1. Login de Estudiante:\n";
    $studentLogin = makeRequest('POST', '/auth/student/login', [
        'ci' => '12345678',
        'password' => 'password123'
    ]);

    if ($studentLogin['success']) {
        printResult('Login Estudiante', 'PASS', 'Autenticación exitosa');
    } else {
        printResult('Login Estudiante', 'FAIL', "Error: " . ($studentLogin['error']['message'] ?? 'Sin respuesta'));
    }

    // 2. Login de admin/docente
    echo "\n2. Login de Admin/Docente:\n";
    $adminLogin = makeRequest('POST', '/auth/admin/login', [
        'ci' => '87654321',
        'password' => 'admin123'
    ]);

    if ($adminLogin['success']) {
        printResult('Login Admin', 'PASS', 'Autenticación exitosa');
    } else {
        printResult('Login Admin', 'FAIL', "Error: " . ($adminLogin['error']['message'] ?? 'Sin respuesta'));
    }

    // 3. Registro de estudiante
    echo "\n3. Registro de Estudiante:\n";
    $studentRegister = makeRequest('POST', '/auth/student/register', [
        'ci' => '99999999',
        'nombre' => 'Test',
        'apellido' => 'Usuario',
        'celular' => '77777777',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ]);

    if ($studentRegister['success']) {
        printResult('Registro Estudiante', 'PASS', 'Registro exitoso');
    } else {
        printResult('Registro Estudiante', 'FAIL', "Error: " . ($studentRegister['error']['message'] ?? 'Sin respuesta'));
    }
}

// Pruebas de relaciones
function testRelationships() {
    printSection('PRUEBAS DE RELACIONES');

    // 1. Verificar relaciones de programas
    echo "\n1. Relaciones de Programas:\n";
    $programas = makeRequest('GET', '/programas');
    if ($programas['success'] && !empty($programas['data'])) {
        $programa = $programas['data'][0];
        $hasRelations = isset($programa['tipo_programa']) || isset($programa['rama_academica']) || isset($programa['institucion']);
        printResult('Relaciones Programa', $hasRelations ? 'PASS' : 'FAIL', $hasRelations ? 'Relaciones cargadas' : 'Relaciones faltantes');
    } else {
        printResult('Relaciones Programa', 'FAIL', 'No hay programas para probar');
    }

    // 2. Verificar relaciones de grupos
    echo "\n2. Relaciones de Grupos:\n";
    $grupos = makeRequest('GET', '/grupos');
    if ($grupos['success'] && !empty($grupos['data'])) {
        $grupo = $grupos['data'][0];
        $hasRelations = isset($grupo['programa']) || isset($grupo['docente']) || isset($grupo['horario']);
        printResult('Relaciones Grupo', $hasRelations ? 'PASS' : 'FAIL', $hasRelations ? 'Relaciones cargadas' : 'Relaciones faltantes');
    } else {
        printResult('Relaciones Grupo', 'FAIL', 'No hay grupos para probar');
    }
}

// Función principal
function runTests() {
    echo Colors::BOLD . Colors::BLUE . "🧪 INICIANDO PRUEBAS DEL BACKEND LARAVEL 🧪" . Colors::RESET . "\n";
    echo "Backend URL: {$GLOBALS['apiUrl']}\n";

    try {
        // Verificar conectividad primero
        if (!testConnectivity()) {
            echo Colors::RED . "❌ No se puede continuar sin conectividad básica" . Colors::RESET . "\n";
            return;
        }

        // Ejecutar todas las pruebas
        testCatalogRoutes();
        testMainRoutes();
        testDashboardRoutes();
        testCRUDOperations();
        testAuthentication();
        testRelationships();

        echo "\n" . Colors::BOLD . Colors::GREEN . "✅ PRUEBAS COMPLETADAS ✅" . Colors::RESET . "\n";
        echo Colors::YELLOW . "Nota: Algunas pruebas pueden fallar si no hay datos de prueba en la base de datos." . Colors::RESET . "\n";

    } catch (Exception $e) {
        echo Colors::RED . "❌ Error ejecutando pruebas: " . $e->getMessage() . Colors::RESET . "\n";
    }
}

// Ejecutar pruebas
runTests();
