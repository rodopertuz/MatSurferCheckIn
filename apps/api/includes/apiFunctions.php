<?php

function insertarEventoApi($conn, $estadoActual, $fecha, $horasminutos, $saldo_clases_personalizadas, $saldo_clases, $comentarios, $uuid, $plan, $edad, $promocion, $roles, $uso_compartido) {
    $horasminutos = intval($horasminutos) + 15;
    if ($estadoActual == "congelado") {
        descongelarAlumno($conn, $uuid);
    }
    
    if (stripos($comentarios, "personalizada") !== FALSE) {
        $horasminutos = intval($horasminutos) - 14;
        $saldo_clases_personalizadas--;
        $comentarios = 'Asistió: '. $uuid . ', Check In: Personalizada';
        $metadata = $comentarios . ', Coach:';
        eventoYaExiste($conn, $fecha, 'Check-In', $comentarios, $uuid, $horasminutos);
        $sql = "INSERT INTO eventos (fecha, evento, saldo_clases_personalizadas, comentarios, metadata, horasminutos, uuid) VALUES ('$fecha', 'Check-In', '$saldo_clases_personalizadas', '$comentarios', '$metadata', '$horasminutos', '$uuid')";
        $resultado = mysqli_query($conn, $sql);
        hallarSaldoClases($conn, $uuid);
        if (!$resultado || mysqli_affected_rows($conn) <= 0) {
            throw new Exception("Error en la inserción de evento de clase personalizada: " . mysqli_error($conn));
        }
    } else if (stripos($promocion, "staff") !== FALSE) {
        if ($horasminutos < 1200) {
            $comentarios = $comentarios . " AM";
        } else {
            $comentarios = $comentarios . " PM";
        }
        if (stripos($roles, "coach") !== FALSE) {
            $comentarios = 'Asistió: '. $uuid . ', Check In: ' . $comentarios;
            $metadata = $comentarios . ", Coach:";
        } else {
            $comentarios = 'Asistió: '. $uuid . ', Check In: ' . $comentarios .", Staff";
            $metadata = $comentarios;
        }
        eventoYaExiste($conn, $fecha, 'Check-In', $comentarios, $uuid, $horasminutos);
        $sql = "INSERT INTO eventos (fecha, evento, saldo_clases, comentarios, metadata, horasminutos, uuid) VALUES ('$fecha', 'Check-In', '', '$comentarios', '$metadata', '$horasminutos', '$uuid')";
        $resultado = mysqli_query($conn, $sql);
        if (!$resultado || mysqli_affected_rows($conn) <= 0) {
            throw new Exception("Error en la inserción de evento de clase regular para Staff: " . mysqli_error($conn));
        }
    } else {
        $arrayClasesDisponibles = obtenerClasesDisponiblesParaUsuario($plan, $edad, $fecha, false);
        $keyword = explode(" ", $comentarios)[0]; // primera palabra de los comentarios hace referencia a la disciplina
        $claseEnPlan = false;
        foreach ($arrayClasesDisponibles as $clases) {
            $strtest = $clases["disciplina"];
            if (stripos($strtest, $keyword) !== FALSE) {
                if ($horasminutos < 1200) {
                    $comentarios = $comentarios . " AM";
                } else {
                    $comentarios = $comentarios . " PM";
                }
                $comentarios = 'Asistió: '. $uuid . ', Check In: ' . $comentarios;
                $metadata = $comentarios;
                eventoYaExiste($conn, $fecha, 'Check-In', $comentarios, $uuid, $horasminutos);
                $sql = "INSERT INTO eventos (fecha, evento, saldo_clases, comentarios, metadata, horasminutos, uuid) VALUES ('$fecha', 'Check-In', '', '$comentarios', '$metadata', '$horasminutos', '$uuid')";
                $resultado = mysqli_query($conn, $sql);
                $claseEnPlan = true;
                if (!$resultado || mysqli_affected_rows($conn) <= 0) {
                    throw new Exception("Error en la inserción de evento de clase regular: " . mysqli_error($conn));
                }
                break;
            }
        }
        if (!$claseEnPlan) {
            $saldo_clases--;
            if ($horasminutos < 1200) {
                $comentarios = $comentarios . " AM";
            } else {
                $comentarios = $comentarios . " PM";
            }
            $comentarios = 'Asistió: '. $uuid . ', Check In: ' . $comentarios;
            $metadata = $comentarios;
            eventoYaExiste($conn, $fecha, 'Check-In', $comentarios, $uuid, $horasminutos);
            $sql = "INSERT INTO eventos (fecha, evento, saldo_clases, comentarios, metadata, horasminutos, uuid) VALUES ('$fecha', 'Check-In', '$saldo_clases', '$comentarios', '$metadata', '$horasminutos', '$uuid')";
            $resultado = mysqli_query($conn, $sql);
            hallarSaldoClases($conn, $uuid);
            if ($uso_compartido != ""){
                eventoYaExiste($conn, $fecha, 'Check-In', $comentarios, $uso_compartido, $horasminutos);
                $sql2 = "INSERT INTO eventos (fecha, evento, saldo_clases, comentarios, metadata, horasminutos, uuid) 
                VALUES ('$fecha', 'Check-In', '$saldo_clases', '$comentarios', '$metadata', '$horasminutos', '$uso_compartido')";
                $resultado2 = mysqli_query($conn, $sql2);
                hallarSaldoClases($conn, $uso_compartido);
            } else {
                $resultado2 = TRUE;
            }
            if (!$resultado || !$resultado2){
                throw new Exception("Error en la inserción de evento de clase regular: " . mysqli_error($conn));
            }
        }
    }
}

function descongelarAlumno($conn, $uuid) {
    $sql3 = "SELECT fecha FROM eventos WHERE uuid = '$uuid' AND evento LIKE '%cambio de estado%' AND comentarios LIKE '%congelado%' ORDER BY fecha DESC LIMIT 1";
    $res3 = mysqli_query($conn, $sql3);
    if ($res3) {
        if (mysqli_num_rows($res3) > 0) {
            while ($row3 = mysqli_fetch_assoc($res3)){
                $fechaCongelado = new DateTime($row3["fecha"]);
            }
            $fvVar = new DateTime($fechaVencimientoActual);
            $fechaHoy = new DateTime("now");
            $restaFecha = $fechaHoy->diff($fechaCongelado);
            $restaFecha = $restaFecha->days;
            date_add($fvVar,date_interval_create_from_date_string($restaFecha . " days"));
            $fecha_vencimiento = date_format($fvVar,"Y-m-d");
            $fechaHoy = new DateTime("now");
            $fechaHoy = date_format($fechaHoy, "Y-m-d");
            $comentario = 'Cambio de estado a ACTIVO el '. $fechaHoy . ' / Descongelado por Check-In.';
            $sql4 = "UPDATE satori_alumnos SET estado = 'activo', fecha_vencimiento = '$fecha_vencimiento' WHERE nombre_tabla = '$uuid'";
            $res4 = mysqli_query($conn, $sql4);
            if ($res4){
                eventoYaExiste($conn, $fechaHoy, 'Check-In', 'Cambio de Estado', $comentario, '');
                $sql5 = "INSERT INTO eventos (fecha, evento, comentarios, uuid) VALUES ('$fechaHoy', 'Cambio de Estado', '$comentario', '$uuid')";
                $res5 = mysqli_query($conn, $sql5);
                if (!$res5) {
                    throw new Exception("Error al insertar evento descongelar usuario: " . mysqli_error($conn));
                }
            } else {
                throw new Exception("Error al asignar nueva fecha de vencimiento después de descongelar usuario: " . mysqli_error($conn));
            }
        }
    } else {
        throw new Exception("Error al consultar eventos del usuario a descongelar: " . mysqli_error($conn));
    }
}

function eventoYaExiste($conn, $fecha, $evento, $comentarios, $uuid, $horasminutos) {
    $sql = "SELECT id FROM eventos 
            WHERE fecha = ? AND evento = ? AND comentarios = ? AND uuid = ? AND horasminutos = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssi', $fecha, $evento, $comentarios, $uuid, $horasminutos);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();
    if ($existe) {
        throw new Exception("Evento duplicado.");
    }
}