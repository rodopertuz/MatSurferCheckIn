<?php
// apps/api/google_login.php
// Endpoint para login/registro con Google en la web

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['credential'])) {
    echo json_encode(['success' => false, 'message' => 'Token no recibido']);
    exit;
}

$google_token = $input['credential'];

// Cargar datos de cliente Google
$secrets_path = __DIR__ . '/../../local_secrets/google_oauth.json';
$secrets = json_decode(file_get_contents($secrets_path), true);
$client_id = $secrets['web']['client_id'];

// Verificar token con Google
$verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($google_token);
$verify = json_decode(file_get_contents($verify_url), true);

if (!isset($verify['aud']) || $verify['aud'] !== $client_id) {
    echo json_encode(['success' => false, 'message' => 'Token de Google inválido']);
    exit;
}

// Extraer datos del usuario
$email = $verify['email'] ?? null;
$name = $verify['name'] ?? '';
$picture = $verify['picture'] ?? '';

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'No se pudo obtener el email de Google']);
    exit;
}


require_once __DIR__ . '/includes/connect.php';

// 1. Verificar si ya existe un gimnasio con este email
$stmt = $pdo->prepare('SELECT id_gimnasio FROM gimnasios WHERE email = ?');
$stmt->execute([$email]);
$gimnasio = $stmt->fetch();

if (!$gimnasio) {
    // Crear nuevo gimnasio
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $stmt = $pdo->prepare('INSERT INTO gimnasios (nombre, slug, email, logo_url, id_plan, estado) VALUES (?, ?, ?, ?, 1, "prueba")');
    $stmt->execute([$name, $slug, $email, $picture]);
    $id_gimnasio = $pdo->lastInsertId();
} else {
    $id_gimnasio = $gimnasio['id_gimnasio'];
}

// 2. Verificar si ya existe usuario_admin con este email
$stmt = $pdo->prepare('SELECT id_admin FROM usuarios_admin WHERE email = ?');
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin) {
    // Crear usuario_admin vinculado al gimnasio
    $stmt = $pdo->prepare('INSERT INTO usuarios_admin (id_gimnasio, nombre, email, password_hash, foto_url, activo) VALUES (?, ?, ?, ?, ?, 1)');
    $fake_password = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT); // No se usa, pero requerido
    $stmt->execute([$id_gimnasio, $name, $email, $fake_password, $picture]);
    $id_admin = $pdo->lastInsertId();
} else {
    $id_admin = $admin['id_admin'];
}

// 3. Asignar rol superadmin si no lo tiene
$stmt = $pdo->prepare('SELECT id FROM usuarios_roles WHERE id_admin = ? AND id_rol = (SELECT id_rol FROM roles WHERE nombre = "superadmin")');
$stmt->execute([$id_admin]);
$rol = $stmt->fetch();
if (!$rol) {
    $stmt = $pdo->prepare('INSERT INTO usuarios_roles (id_admin, id_rol) VALUES (?, (SELECT id_rol FROM roles WHERE nombre = "superadmin"))');
    $stmt->execute([$id_admin]);
}

// Iniciar sesión PHP
session_start();
$_SESSION['id_admin'] = $id_admin;
$_SESSION['email'] = $email;
$_SESSION['nombre'] = $name;
$_SESSION['id_gimnasio'] = $id_gimnasio;

echo json_encode(['success' => true]);
