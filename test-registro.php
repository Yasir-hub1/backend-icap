<?php

// Script para probar el endpoint de registro de estudiantes
$url = 'http://localhost:8000/api/auth/estudiante/registrar';

$data = [
    'ci' => '12345678',
    'nombre' => 'Juan',
    'apellido' => 'Pérez',
    'celular' => '0987654321',
    'fecha_nacimiento' => '1995-01-01',
    'direccion' => 'Calle Principal 123',
    'provincia' => 'Guayas',
    'password' => 'Test123456',
    'password_confirmation' => 'Test123456'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

$decoded = json_decode($response, true);
if ($decoded) {
    echo "\nDecoded response:\n";
    print_r($decoded);

    if (isset($decoded['token'])) {
        echo "\n✅ Token recibido: " . substr($decoded['token'], 0, 20) . "...\n";
    }

    if (isset($decoded['user'])) {
        echo "\n✅ Usuario recibido:\n";
        print_r($decoded['user']);
    }
}
