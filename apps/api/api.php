<?php
// api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Ajusta esto según tu dominio

// Configuración segura de la base de datos
include_once  './includes/connect.php';
include_once  './includes/timeZoneDetect.php';
include_once  './includes/funcionesUsuario.php';
include_once  './includes/apiFunctions.php';

if (!file_exists('./apiErrorLog.html')) {
    // El archivo NO existe
    $errorLogContent = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head><body>\n";
    file_put_contents('./apiErrorLog.html', $errorLogContent);
}

// Token de autenticación (guárdalo seguro y cámbialo por uno propio)
define('API_TOKEN', $API_TOKEN_VALUE);

// Función para validar el token
function isAuthenticated() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = trim(str_replace('Bearer ', '', $headers['Authorization']));
        return $token === API_TOKEN;
    }
    return false;
}

// Rechazar si no está autenticado
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    $errorLogContent = "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Autenticación Fallida.</br>\n";
    file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);
    exit;
}

// Conexión segura usando MySQLi
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión']);
    $errorLogContent = "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Error de conexión: " . $conn->connect_error . "</br>\n";
    file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);
    exit;
}
$conn->set_charset('utf8mb4');

// Validar y limpiar entrada
function getParam($key) {
    return isset($_GET[$key]) ? htmlspecialchars(trim($_GET[$key])) : null;
}

// Endpoint seguro para obtener usuarios
if ($_SERVER['REQUEST_METHOD'] === 'GET'){
    if (getParam('action') === 'usuarios') {
        $result = $conn->query("SELECT a.*, ag.estado FROM alumnos a LEFT JOIN alumnos_gimnasios ag ON a.id_alumno = ag.id_alumno WHERE ag.estado != 'inactivo' OR ag.estado IS NULL ORDER BY a.nombre ASC");
        $usuarios = [];
        $clasesPorUsuario = [];
        $url_fotos = [];
        $base_url = 'https://matsurfer.co/img/users/'; // Ajusta si tu ruta es diferente
        $logo_url = 'https://matsurfer.co/img/satorilogotexto.png';

        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
            $plan = $row["plan"];
            $edad = $row["edad"];
            $arrayClasesDisponibles = obtenerClasesDisponiblesParaUsuario($plan, $edad, $fechaHoyString, true);
            $clasesPorUsuario[] = $arrayClasesDisponibles;
            if ((file_exists("../img/users/" . $row['foto'])) && (!empty($row['foto']))) {
                $url_fotos[] = 'https://matsurfer.co/img/users/' . $row['foto'];
            } else {
                $url_fotos[] = 'https://matsurfer.co/img/users/genericUser.png';
            }
        }
        $clases_ahora = obtenerClasesActualYProxima();
        // $clases_ahora['actual'] y $clases_ahora['proxima']
        
        // Resetear flag de cambios después de enviar datos
        $flagPath = './cambios_flag.json';
        if (file_exists($flagPath)) {
            $flag = ['cambios_pendientes' => false];
            file_put_contents($flagPath, json_encode($flag));
        }
        
        echo json_encode(['usuarios' => $usuarios, 'clases_usuario' => $clasesPorUsuario, 'fotos' => $url_fotos, 'logo_url' => 'https://matsurfer.co/img/satorilogotexto.png', 'clases_ahora' => $clases_ahora]);
        exit;
    } else if (getParam('action') === 'ondeck'){
        $clases_ahora = obtenerClasesActualYProxima();
        if ($clases_ahora['actual'] === 'ninguno') {
            $hora1 = $clases_ahora['proximas'][0]['start'];
            $hora2 = $clases_ahora['proximas'][0]['end'];
            $mensaje = 'clase actual ninguna';
        } else {
            $hora1 = $clases_ahora['actual']['start'];
            $hora2 = $clases_ahora['actual']['end'];
            $mensaje = 'clase actual en curso';
        }
        $hora1 = intval(str_replace(':', '', $hora1));
        $hora2 = intval(str_replace(':', '', $hora2));
        $arrayOnDeck = consultaOnDeck($conn, null, $fechaHoyString, $hora1, $hora2, true);
        $arrayOnDeckProximas = consultaOnDeck($conn, null, $fechaHoyString, $hora2, "2359", true);
        $result = $conn->query("SELECT * FROM prospectos WHERE fecha_cortesia = '$fechaHoyString' AND asistio = '1'");
        $prospectosOnDeck = [];
        $fotoProspecto = 'https://matsurfer.co/img/users/genericUser.png';
        while ($row = $result->fetch_assoc()) {
            $cortesia = $row["cortesia"];
            $horaAMPM = trim(substr($cortesia, strrpos($cortesia, ">")+2, 9));
            $dt = DateTime::createFromFormat('h:i A', $horaAMPM);
            $hora = (int)$dt->format('Hi');
            $hora = $hora+15;
            if (($hora > $hora1) && ($hora < $hora2)){
                $prospectosOnDeck[] = $row['nombre'];
            }
        }
        
        // Consultar clases personalizadas próximas
        $horaActualInt = intval(date('Hi'));
        $horaLimite = $horaActualInt - 100;
        $horaMaxima = $horaActualInt + 5;
        $sqlPersonalizadas = "SELECT uuid FROM eventos WHERE metadata LIKE '%personalizada%' AND horasminutos >= $horaLimite AND horasminutos <= $horaMaxima AND fecha = '$fechaHoyString'";
        $resultPersonalizadas = $conn->query($sqlPersonalizadas);
        $personalizadas_ondeck = [];
        if ($resultPersonalizadas && $resultPersonalizadas->num_rows > 0) {
            while ($row = $resultPersonalizadas->fetch_assoc()) {
                $personalizadas_ondeck[] = $row['uuid'];
            }
        }
        
        // Leer flag de cambios
        $cambiosPendientes = false;
        $flagPath = './cambios_flag.json';
        if (file_exists($flagPath)) {
            $flagData = json_decode(file_get_contents($flagPath), true);
            $cambiosPendientes = $flagData['cambios_pendientes'] ?? false;
        }
        
        echo json_encode(['ondeck' => $arrayOnDeck, 'prospectos_ondeck' => $prospectosOnDeck, 'foto_prospecto' => $fotoProspecto, 'cambios_pendientes' => $cambiosPendientes, 'personalizadas_ondeck' => $personalizadas_ondeck, 'ondeck_proximas' => $arrayOnDeckProximas]);
        exit;
    }
}

// Endpoint seguro para agregar usuario (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (getParam('action') === 'agregar_usuario') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['nombre'], $data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            $errorLogContent = "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Datos incompletos al agregar usuario.</br>\n";
            file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);
            exit;
        }
        $nombre = htmlspecialchars(trim($data['nombre']));
        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Email inválido']);
            exit;
        }
        $stmt = $conn->prepare('INSERT INTO usuarios (nombre, email) VALUES (?, ?)');
        $stmt->bind_param('ss', $nombre, $email);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al insertar']);
        }
        $stmt->close();
        exit;
    } else if (getParam('action') === 'check-in'){
        // Leer el body JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Validar datos recibidos
        $uuid = isset($data['nombre_tabla']) ? $data['nombre_tabla'] : null;
        $clases = isset($data['clases']) ? $data['clases'] : [];

        if ($uuid && is_array($clases) && count($clases) > 0) {

            $htmlContent = "<!DOCTYPE html>\n<html>\n<head>\n<title>Resultado Check-in</title>\n</head>\n<body>\n";
            $htmlContent .= "<h1>Check-in realizado</h1>\n";
            $htmlContent .= "<p><strong>UUID:</strong> " . htmlspecialchars($uuid) . "</p>\n";
            $htmlContent .= "<h2>Clases:</h2>\n<ul>\n";
            foreach ($clases as $clase) {
                $htmlContent .= "<li>" . htmlspecialchars(json_encode($clase)) . "</li>\n";
            }
            $htmlContent .= "</ul>\n";
            $htmlContent .= "</body>\n</html>";

            file_put_contents('./apiPostResult.html', $htmlContent);

            //Insertar check_ins en la base de datos
            try {
                $sql = "SELECT * FROM satori_alumnos WHERE nombre_tabla = '$uuid'";
                $resultado = mysqli_query($conn, $sql);
                if ($resultado) {
                    if (($resultado->num_rows > 0)) {
                        while ($row = mysqli_fetch_assoc($resultado)){
                            $estadoActual = $row["estado"];
                            $fechaVencimientoActual = $row["fecha_vencimiento"];
                            $saldo_clases = $row["saldo_clases"];
                            $saldo_clases_personalizadas = $row["saldo_clases_personalizadas"];
                            $plan = $row["plan"];
                            $promocion = $row["promocion"];
                            $edad = $row["edad"];
                            $roles = $row["roles"];
                            $uso_compartido = $row["uso_compartido"];
                        }
                    } else {
                        throw new Exception("No se encontraron registros para ". $uuid);
                    }
                } else {
                    throw new Exception("Error en la consulta SQL: " . $conn->error);
                }
            } catch (Exception $e) {
                $errorLogContent .= "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Error en seleccionar los datos del alumno de la bdd.</br>\n" . $e->getMessage();
                file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);
                echo json_encode(['success' => false, 'error' => 'Error en la consulta' . $e->getMessage()]);
                exit;
            }

            try {
                foreach ($clases as $clase) {
                    $fecha = $fechaHoyString;
                    $comentarios = isset($clase['disciplina']) ? $clase['disciplina'] : '';
                    $comentarios .= isset($clase['grupo']) ? ' ' . $clase['grupo'] : '';
                    $horasminutos = isset($clase['start']) ? str_replace(":", "", $clase['start']) : intval(date('Hi'));
                    $order_id = '';
                    insertarEventoApi($conn, $estadoActual, $fecha, $horasminutos, $saldo_clases_personalizadas, $saldo_clases, $comentarios, $uuid, $plan, $edad, $promocion, $roles, $uso_compartido);
                }
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'duplicado') === false) {
                    $errorLogContent .= "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Error en insertarEventoApi de apiFunctions.php.</br>\n" . $e->getMessage();
                    file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);
                    echo json_encode(['success' => false, 'error' => 'Error en insertarEventoApi' . $e->getMessage()]);
                    exit;
                } else {
                    echo json_encode(['success' => true, 'message' => 'check_ins duplicado']);
                    exit;
                }
            }
            echo json_encode(['success' => true]);
            exit;
        } else {
            // Respuesta de error
            $errorLogContent = "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Datos inválidos en check-in. uuid && is_array(clases) && count(clases) > 0</br>\n";
            file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        }
    }
}

// Si no coincide ningún endpoint
http_response_code(404);
echo json_encode(['error' => 'Endpoint no encontrado']);
$conn->close();
$errorLogContent = "<b>[" . date("Y/m/d - H:i:s") . "]</b> - Endpoint no encontrado.</br>\n";
file_put_contents('./apiErrorLog.html', $errorLogContent, FILE_APPEND);

function obtenerClasesActualYProxima() {
    // Leer y decodificar el JSON
    $jsonPath = '../json/gym_schedule.json';
    $json = file_get_contents($jsonPath);
    $data = json_decode($json, true);

    // Obtener día y hora actual
    $diasSemana = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
    ];
    $diaActual = $diasSemana[date('w')];
    $horaActual = date('H:i');
    $fechaActual = date('Y-m-d');

    $claseActual = [
        'disciplina' => 'ninguno',
        'grupo' => 'ninguno',
        'start' => 'ninguno',
        'end' => 'ninguno',
        'grupos_permitidos' => []
    ];
    $proximasClases = [];
    $clasesHoy = [];

    // Verificar si hoy es un día festivo
    $esHoliday = isset($data['holidays'][$fechaActual]);
    $scheduleData = $esHoliday ? $data['holidays'][$fechaActual] : $data['age_groups'];

    // Recorrer todas las clases
    foreach ($scheduleData as $grupo => $disciplinas) {
        foreach ($disciplinas as $disciplina => $horarios) {
            foreach ($horarios as $horario) {
                // En holidays no hay "days_of_week", solo horarios directos
                if ($esHoliday || in_array($diaActual, $horario['days_of_week'])) {
                    $start = $horario['start_time'];
                    $end = $horario['end_time'];
                    $gruposPermitidos = isset($horario['allowed_age_groups']) ? $horario['allowed_age_groups'] : [];
                    
                    // Si la clase está ocurriendo ahora
                    if ($horaActual >= $start && $horaActual < $end) {
                         $claseActual = [
                            'disciplina' => $disciplina,
                            'grupo' => $grupo,
                            'start' => $start,
                            'end' => $end,
                            'grupos_permitidos' => $gruposPermitidos
                        ];
                    }
                    // Guardar clases de hoy para buscar la próxima
                    $clasesHoy[] = [
                        'disciplina' => $disciplina,
                        'grupo' => $grupo,
                        'start' => $start,
                        'end' => $end,
                        'grupos_permitidos' => $gruposPermitidos
                    ];
                }
            }
        }
    }

    // Ordenar clases de hoy por hora de inicio
    usort($clasesHoy, function($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    // Buscar todas las próximas clases de hoy en orden
    foreach ($clasesHoy as $clase) {
        if ($clase['start'] > $horaActual) {
            $proximasClases[] = [
                'disciplina' => $clase['disciplina'],
                'grupo' => $clase['grupo'],
                'start' => $clase['start'],
                'end' => $clase['end'],
                'grupos_permitidos' => $clase['grupos_permitidos']
            ];
        }
    }

    // Si no hay próximas clases, poner un array con campos "ninguno"
    if (empty($proximasClases)) {
        $proximasClases[] = [
            'disciplina' => 'ninguno',
            'grupo' => 'ninguno',
            'start' => 'ninguno',
            'end' => 'ninguno',
            'grupos_permitidos' => []
        ];
    }

    return [
        'actual' => $claseActual,
        'proximas' => $proximasClases
    ];
}

?>
