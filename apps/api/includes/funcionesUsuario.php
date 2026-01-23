<?php

include 'manejoNumeros.php';

$gradosArray = array("blanco", "gris", "amarillo", "naranja", "verde", "azul", "morado", "marron", "negro");
function actualizarEstados($conn, $date1, $uuid){
    $promocionActual = "next gen";
    $fechaHoy = date_format($date1, "Y-m-d");
    if ($uuid === null){
        $sql = "SELECT * FROM satori_alumnos WHERE 
        ultima_actualizacion < '$fechaHoy' AND 
        estado != 'baja'";
        $uuidEsNulo = true;
    } else {
        $sql = "SELECT * FROM satori_alumnos WHERE nombre_tabla = '$uuid'";
        $uuidEsNulo = false;
    }
    $res = mysqli_query($conn, $sql);
    
    if ($res) {
        if (mysqli_num_rows($res) > 0 ){
            while ($row = mysqli_fetch_assoc($res)){
                actualizarEdad($conn, $row["fecha_nacimiento"], $row["nombre_tabla"], $date1);
                actualizarProgreso($conn, $row["nombre_tabla"], $row["grado"]);
                hallarSaldoClases($conn, $row["nombre_tabla"]);
                if ($uuidEsNulo === true) $uuid = $row["nombre_tabla"];
                if (($row["estado"] != "congelado") && ($row["estado"] != "staff") && ($row["estado"] != "convenio")){
                    $estadoActual = $row["estado"];
                    $estado = "";
                    $esTiquetera = FALSE;
                    $esPersonalizadas = FALSE;
                    $saldoClases = "";
                    $tipoEvento = "";
                    $comentario = "";
                    $promocion = $row["promocion"];
                    if (stripos($row["plan"],"TIQUETERA") !== FALSE){
                        $saldoClases = (int) $row["saldo_clases"];
                        $esTiquetera = TRUE;
                    } else if (stripos($row["plan"],"PERSONALIZADA") !== FALSE) {
                        $saldoClasesPersonalizadas = (int) $row["saldo_clases_personalizadas"];
                        $esPersonalizadas = TRUE;
                    }
                    $fechaVencimiento = new DateTime($row["fecha_vencimiento"]);
                    $fvVar = $fechaVencimiento;
                    $fechaHoyVar = $date1; //$date1 es la fecha de hoy
                    $restaFecha = $fechaHoyVar->diff($fvVar);
                    $restaFecha = $restaFecha->days;
                    if (($restaFecha > 2) && ($date1<$fechaVencimiento)) {
                        if ($esTiquetera){
                            if ($saldoClases > 2){
                                $estado = "activo";
                            } else if ($saldoClases > 0) {
                                $estado = "saldobajo"; 
                            } else {
                                $estado = "pendiente";
                            }
                        } else if ($esPersonalizadas) {
                            if ($saldoClasesPersonalizadas > 2) {
                                $estado = "activo";
                            } else if ($saldoClasesPersonalizadas > 0) {
                                $estado = "saldobajo"; 
                            } else {
                                $estado = "pendiente";
                            }
                        } else {
                            $estado = "activo";
                        }
                    } else if (($restaFecha >=0) && ($date1<=$fechaVencimiento)) {
                        if ($esTiquetera) {
                            if ($saldoClases > 0) {
                                $estado = "saldobajo";
                            } else {
                                $estado = "pendiente";
                            }
                        } else if ($esPersonalizadas) {
                            if ($saldoClasesPersonalizadas > 0) {
                                $estado = "saldobajo";
                            } else {
                                $estado = "pendiente";
                            }
                        } else {
                            $estado = "saldobajo";
                        }
                    } else if (($restaFecha <= 30) && ($date1 > $fechaVencimiento)) {
                        $estado = "pendiente";
                        if (($esTiquetera) && ($saldoClases > 0)) {
                            $saldoClases = floor($saldoClases/2);
                            $tipoEvento = "pendiente";
                            $comentario = "Cambio de estado a PENDIENTE el: " . $fechaHoy;
                        }
                    } else {
                        $estado = "inactivo";
                        if (($row["estado"] != "inactivo") && ($row["estado"] != "baja")) {
                            $tipoEvento = "inactivo";
                            $comentario = "Cambio de estado a INACTIVO el: " . $fechaHoy;
                        }
                        if ($esTiquetera) $saldoClases = 0;
                    }

                    if (($row["estado"] == "inactivo") || ($estado == "inactivo")){
                        $diasAtras90 = new DateTime("now");
                        date_sub($diasAtras90,date_interval_create_from_date_string("90 days"));
                        $diasAtras90 = date_format($diasAtras90, "Y-m-d");
                        $sql2 = "SELECT fecha FROM eventos WHERE uuid = '$uuid' AND evento NOT LIKE '%estado%' AND fecha > '$diasAtras90' ORDER BY fecha DESC, id DESC LIMIT 1";
                        $res2 = mysqli_query($conn, $sql2);
                        if ($res2){
                            if(mysqli_num_rows($res2) == 0){
                                $estado = 'baja';
                                if ($row["estado"] != "baja") {
                                    $tipoEvento = 'baja';
                                    $comentario = "Dado de baja el: " . $fechaHoy;
                                    $promocion = $promocionActual;
                                }
                            }
                        } else {
                            echo "PHPerror3: " . $conn->error;
                            throw new Exception("Error en actualizarEstados (ID=$uuid): " . mysqli_error($conn));
                        }
                    }

                    $sql3 = "SELECT * FROM eventos WHERE uuid = '$uuid' AND fecha = '$fechaHoy' AND item LIKE '%DROP%'";
                    $res3 = mysqli_query($conn, $sql3);
                    if (mysqli_num_rows($res3) > 0) {
                        $estado = "activo";
                        $estadoActual = "activo";
                    }

                    $sql2 = "UPDATE satori_alumnos SET estado = '$estado', ultima_actualizacion = '$fechaHoy', promocion = '$promocion' WHERE nombre_tabla = '$uuid'";
                    $res2 = mysqli_query($conn, $sql2);
                    if ($res2) {
                        // if ($tipoEvento != "") {
                        //     $sql3 = "INSERT INTO eventos (fecha, evento, saldo_clases, comentarios, uuid) VALUES ('$fechaHoy', '$tipoEvento', '$saldoClases', '$comentario', '$uuid') ";
                        //     $res3 = mysqli_query($conn, $sql3);
                        //     if (!$res3) echo $conn->error;
                        // }
                        if ($estado != $estadoActual) {
                            $comentario = 'Cambio de estado a ' . strtoupper($estado) . ' el ' . $fechaHoy;
                            $sql3 = "INSERT INTO eventos (fecha, evento, saldo_clases, comentarios, uuid) VALUES ('$fechaHoy', 'Cambio de Estado', '$saldoClases', '$comentario', '$uuid') ";
                            $res3 = mysqli_query($conn, $sql3);
                            if (!$res3) {
                                echo $conn->error;
                                throw new Exception("Error en actualizarEstados (ID=$uuid): " . mysqli_error($conn));
                            }
                            // Activar flag de cambios para la API
                            $flagPath = '../api/cambios_flag.json';
                            if (file_exists($flagPath)) {
                                $flag = ['cambios_pendientes' => true];
                                file_put_contents($flagPath, json_encode($flag));
                            }
                        }
                    } else {
                        echo $conn->error;
                        throw new Exception("Error en actualizarEstados (ID=$uuid): " . mysqli_error($conn));
                    }
                } else if (($row["estado"] == "congelado")) {
                    $sql2 = "SELECT fecha FROM eventos WHERE uuid = '$uuid' AND evento LIKE '%cambio de estado%' AND comentarios LIKE '%congelado%' ORDER BY fecha DESC LIMIT 1";
                    $res2 = mysqli_query($conn, $sql2);
                    if ($res2) {
                        if (mysqli_num_rows($res2) > 0) {
                            while ($row2 = mysqli_fetch_assoc($res2)){
                                $fechaCongelado = new DateTime($row2["fecha"]);
                            }
                            $fvVar = new DateTime($row["fecha_vencimiento"]);
                            $fechaHoyVar = $date1; //$date1 es la fecha de hoy
                            $restaFecha = $fechaHoyVar->diff($fechaCongelado);
                            $restaFecha = $restaFecha->days;
                            if ($restaFecha > 30) {
                                $comentario = 'Cambio de estado a ACTIVO el ' . $fechaHoy . ' / Descongelado automáticamente.';
                                date_add($fvVar,date_interval_create_from_date_string("30 days"));
                                $fechaVencimiento = date_format($fvVar,"Y-m-d");
                                $sql3 = "UPDATE satori_alumnos SET estado = 'activo', ultima_actualizacion = '$fechaHoy', fecha_vencimiento = '$fechaVencimiento' WHERE nombre_tabla = '$uuid'";
                                $res3 = mysqli_query($conn, $sql3);
                                if ($res3){
                                    $sql4 = "INSERT INTO eventos (fecha, evento, comentarios, uuid) VALUES ('$fechaHoy', 'Cambio de Estado', '$comentario', '$uuid')";
                                    $res4 = mysqli_query($conn, $sql4);
                                    // Activar flag de cambios para la API
                                    $flagPath = '../api/cambios_flag.json';
                                    if (file_exists($flagPath)) {
                                        $flag = ['cambios_pendientes' => true];
                                        file_put_contents($flagPath, json_encode($flag));
                                    }
                                } else {
                                    echo "PHPError3: " . mysqli_error($conn);
                                    throw new Exception("Error en actualizarEstados (ID=$uuid): " . mysqli_error($conn));
                                }
                            }
                        }
                    } else {
                        echo "PHPError2: " . mysqli_error($conn);
                        throw new Exception("Error en actualizarEstados (ID=$uuid): " . mysqli_error($conn));
                    }
                }
            }
        }
    } else {
        echo 'PHPerror1: ' . $conn->error;
        throw new Exception("Error en actualizarEstados (ID=$uuid): " . mysqli_error($conn));
    }
}

function consultaOnDeck($conn, $date1, $fecha, $hora1, $hora2, $expectReturn){

    if (($fecha == "") || ($fecha == null)){
        $fecha = $date1->format('Y-m-d');
        $hms = date("Hi");
        $rangos = rangoHorasClases($fecha, $hms);
        $hora1 = $rangos[0];
        $hora2 = $rangos[1];
    }

    if ($hora2 == 1000) $hora2--;

    if ($hora1 < 1000) {
        $hora1a = "0" . strval($hora1);
    } else {
        $hora1a = $hora1;
    }

    if ($hora2 < 1000) {
        $hora2a = "0" . strval($hora2);
    } else {
        $hora2a = $hora2;
    }

    if ($hora1 == "100") {
        $sql = "SELECT uuid, comentarios, horasminutos FROM eventos 
        WHERE evento LIKE '%check%' 
        AND fecha = '$fecha'
        AND comentarios LIKE '%personalizada%'
        ";
    } else {
        $sql = "SELECT uuid, comentarios FROM eventos 
        WHERE evento LIKE '%check%' 
        AND fecha = '$fecha' 
        AND ((horasminutos >= '$hora1' AND horasminutos <= '$hora2') 
        OR (horasminutos >= '$hora1a' AND horasminutos <= '$hora2a'))
        AND comentarios NOT LIKE '%personalizada%'
        ";
    }
    
    $res = mysqli_query($conn, $sql);
    
    if ($expectReturn){
        if ($res){
            $onDeckArray = array();
            if (mysqli_num_rows($res) > 0) {
                while($row = mysqli_fetch_assoc($res)){
                    if (stripos($row["comentarios"], $row["uuid"]) !== FALSE) array_push($onDeckArray, $row["uuid"]);
                }
            }
            return $onDeckArray;
        } else {
            echo mysqli_error($conn);
            throw new Exception ("sin resultados: " . mysqli_num_rows($res));
        }
    } else {

        echo '<div class="encabezadoContenido" id="encabezadoContenidoAsistencias">
            <div class="encabezadoContenido-btn" onclick="cambiarPestanaContenido(1)">
                <span>
                    <i class="fa-solid fa-rotate-right"></i>
                </span>
            </div>
            <div class="encabezadoContenido-item">
                <select name="filtrar" id="filtrarUsuarios" onchange="mostrarInputBusqueda(this)">
                    <option value="" selected disabled>Filtrar por... </option>
                    <option value="nombre">Nombre</option>
                    <option value="estado">Estado actual de afiliación</option>
                    <option value="promocion">Plan</option>
                </select>
            </div>
            <div class="encabezadoContenido-item">
                <input type="text" id="inputBusqueda" onkeyup="busquedaFiltro(this)" placeholder="buscar..." autocomplete="off" style="display: none;">
                <i class="fa-solid fa-eraser manito" style="padding-left: 10px; display: none;" onclick="borrarInputUniversal(this)"></i>
                <select name="selectEstado" id="selectEstado" style="display: none;" onchange="busquedaFiltro(this)">
                </select>
            </div>
            <div class="encabezadoContenido-item">
                <input type="date" name="fechaPrevCheckin" id="fechaPrevCheckin" value="' . $fecha . '" onChange="cargarHorasDeClase(false, null, gymScheduleData)">
            </div>
            <div class="encabezadoContenido-item">
                <select name="horaPrevCheckin" id="horaPrevCheckin">
                </select>
            </div>
            <div class="encabezadoContenido-item">
                <button type="button" onclick="consultarPrevCheckin()">
                    IR
                </button>
            </div>
        </div>';
    
        echo '<div class="pestanasContenido">
            <div class="pestanaContenido" onclick="cambiarPestanaContenido(0)">
                ASISTENCIAS
            </div>
            <div class="pestanaContenido pestanaContenido-activo" onclick="cambiarPestanaContenido(1)">
                ON-DECK
            </div>
            <a href="../admin/asistenciasQr.php?evento=Check-In" target="_blank">
                <div class="pestanaContenido">
                    SELF CHECK-IN
                </div>
            </a>
        </div>';
    
        echo '<div class="listadoContenidoOnDeck" id="listadoContenidoAsistencias">';
        $sql2 = "SELECT * FROM prospectos WHERE fecha_cortesia = '$fecha' AND asistio = '1'";
        $res2 = mysqli_query($conn, $sql2);
        if ($res2){
            if (($res2->num_rows > 0) && ($hora1 != "100")){
                while ($row2 = $res2->fetch_assoc()){
                    $cortesia = $row2["cortesia"];
                    $horaAMPM = trim(substr($cortesia, strrpos($cortesia, ">")+2, 9));
                    $dt = DateTime::createFromFormat('h:i A', $horaAMPM);
                    $hora = (int)$dt->format('Hi');
                    $hora = $hora+15;
                    if (($hora > $hora1) && ($hora < $hora2)){
                        echo '<div class="IDCard" style="background-image: url(\'../img/idcortesia.png\'); border: 5px solid black;" title="-1">';
                        echo '<i class="fa-solid fa-user-plus fotoUsuarioOnDeck" style="opacity: 0.75;"></i>';
                        echo '<div class="infoUsuarioOnDeck manito"> <b>Nombre: </b>' . $row2["nombre"] . '</div>';
                        echo '<div class="infoUsuarioOnDeck">';
                            echo '<i class="fa-regular fa-eye"></i>';
                        echo '</div>';
                        echo '<div class="infoUsuarioOnDeck">';
                            $imageUrl = "../img/grados/blanco.png";
                            if (file_exists($imageUrl)) {
                                echo '<img src="'. $imageUrl . '" alt="img-cinta" class="cinta-usuario" style="margin-top: 5px;">';
                            } else {
                                echo '<img src="../img/missingGrado.png" alt="img-cinta" class="cinta-usuario">';
                            }
                        echo '</div>';
                        if (stripos($row2["comentarios"], "DROP") !== FALSE){
                            echo '<div class="infoUsuarioOnDeck" style="font-size: 2em; font-weight: bold;">DROP IN</div>';
                        } else {
                            echo '<div class="infoUsuarioOnDeck" style="font-size: 2em; font-weight: bold;">CORTESÍA</div>';
                        }
                        echo '</div>';
                    }
                }
            }
        } else {
            echo "PHPerror3: " . $conn->error;
        }
    
        if ($res){
            if ($res->num_rows>0){
                while ($row = $res->fetch_assoc()){
                    $sql2 = "SELECT * FROM satori_alumnos WHERE nombre_tabla = '$row[uuid]'";
                    $res2 = mysqli_query($conn, $sql2);
                    if($res2){
                        if ($res2->num_rows>0){
                            $printOnDeck = false;
                            while ($row2 = $res2->fetch_assoc()){
                                if ((stripos($row["comentarios"], $row2["nombre_tabla"])) && ($row2 ["uso_compartido"] != "")){
                                    $printOnDeck = true;
                                } else if ($row2 ["uso_compartido"] == ""){
                                    $printOnDeck = true;
                                }
                                if ($printOnDeck){
                                    $strtest = $row2["grado"];
                
                                    if (strpos($strtest, "zul") == '1'){
                                        $cinta = 'blue';
                                        $idcinta = 'idazul.png';
                                    } elseif (strpos($strtest, "ora") == '1'){
                                        $cinta = 'purple';
                                        $idcinta = 'idmorado.png';
                                    } elseif (strpos($strtest, "arr") == '1'){
                                        $cinta = 'brown';
                                        $idcinta = 'idmarron.png';
                                    } elseif (strpos($strtest, "egr") == '1'){
                                        $cinta = 'black';
                                        $idcinta = 'idnegro.png';
                                    } else {
                                        $cinta = 'white';
                                        $idcinta = 'idblanco.png';
                                    }
                
                                    $colorEstado = 'black';
                
                                    if (($row2["estado"] == 'saldobajo') || ($row2["estado"] == 'pendiente') || ($row2["estado"] == 'inactivo')){
                                        $colorEstado = 'red';
                                    }
                
                                    $testFoto = "../img/users/" . $row2["foto"];
                                    if ((file_exists($testFoto)) && ($row2["foto"] != "")){
                                        $foto = '<img src="' . $testFoto . '" alt="?" class="fotoUsuarioOnDeck">';
                                    } else {
                                        $foto = '<i class="fa-solid fa-user-tie fotoUsuarioOnDeck"  style="opacity: 0.75;"></i>';
                                    }
                                    global $gradosArray;
                                    $indexGrados = count($gradosArray)-1;
                                    if (stripos($row2["grado"], "blanco") === 0){
                                        $indexGrados = 0;
                                    } else {
                                        for ($i=1; $i<count($gradosArray); $i++) {
                                            if(stripos($row2["grado"], $gradosArray[$i]) !== FALSE) $indexGrados = $i;
                                        }
                                    }
                                    echo '<div class="IDCard" style="background-image: url(\'../img/'.$idcinta.'\'); border: 5px solid ' . $colorEstado . ';" title="' . $indexGrados . '">';
                                    echo $foto;
                                    echo '<div class="infoUsuarioOnDeck manito" onclick="consultarUsuarioPorNombre(`' . $row2["nombre_tabla"] . '`)">
                                        <b>Nombre: </b>' . $row2["nombre"];

                                    if (($row2["saldo_clases"] != "") && (stripos($row2["plan"], "Tiquetera") !== FALSE)){
                                        echo ' / T <span style="font-weight: bold;">[ <span>' . $row2["saldo_clases"] . '</span> ]</span>';
                                    } else if (($row2["saldo_clases"] != "") && ($row2["saldo_clases"] != "0")){
                                        echo ' / T <span style="font-weight: bold;">[ <span>' . $row2["saldo_clases"] . '</span> ]</span>';
                                    } else {
                                        echo '<span style="font-weight: bold; display: none;"> / T [ <span>' . $row2["saldo_clases"] . '</span> ]</span>';
                                    }
                                    if (($row2["saldo_clases_personalizadas"] != "") && ($row2["saldo_clases_personalizadas"] != "0")) {
                                        echo ' + P <span style="font-weight: bold;">[ <span>' . $row2["saldo_clases_personalizadas"] . '</span> ]</span>';
                                    } else {
                                        echo '<span style="font-weight: bold; display: none;"> + P [ <span>' . $row2["saldo_clases_personalizadas"] . '</span> ]</span>';
                                    }

                                    echo '</div>';
                                    echo '<div class="infoUsuarioOnDeck">
                                        <b>Estado: </b>' . $row2["estado"];
                                        if ($colorEstado == 'red'){
                                            echo '<table id="tablaUsuarios" title="tablaOculta">
                                                <tr>
                                                    <td style="display: none;">ID</td>
                                                    <td style="display: none;" title="columnaNombre">' . $row2["nombre"] . '</td>
                                                    <td style="display: none;" title="columnaCelular">' . $row2["celular"] . '</td>
                                                    <td style="display: none;" title="columnaEmail">' . $row2["email"] . '</td>
                                                    <td style="display: none;">Estado Icono</td>
                                                    <td style="display: none;" title="columnaEstadoTexto">' . $row2["estado"] . '</td>
                                                    <th style="display: none" title="columnaEstadoNumero">Estado Numero</th>
                                                    <td style="display: none;" title="columnaFechaVencimiento">' . $row2["fecha_vencimiento"]. '</td>
                                                    <td style="display: none;" title="columnaPromocion">' . $row2["promocion"] . '</td>
                                                    <td style="display: none;" title="columnaPlan">' . $row2["plan"] . '</td>
                                                    <td style="border: none;"><i class="fa-brands fa-whatsapp" style="float: right;" onclick="mostrarMensajesWapp(this)"></i></td>
                                                    <td style="display: none" title="columnaApodo">' . $row2["apodo"] . '</td>
                                                    <td style="display: none" id="columna_11" title="columnaFechaInicio">' . $row2["fecha_inicio"] . '</td>
                                                    <td style="display: none" id="columna_12" title="columnaFechaMensajeBienvenida">' . $row2["wapp_bienvenida"] . '</td>
                                                    <td style="display: none" id="columna_13" title="columnaFechaMensajeSaldoBajo">' . $row2["wapp_saldobajo"] . '</td>
                                                    <td style="display: none" id="columna_14" title="columnaFechaMensajePendiente">' . $row2["wapp_pendiente"] . '</td>
                                                    <td style="display: none" id="columna_15" title="columnaAcudiente">' . $row2["nombre_acudiente1"] . '</td>
                                                </tr>
                                            </table>';
                                        }
                                    echo '</div>';
                                    echo '<div class="infoUsuarioOnDeck">';
                                    if (strpos(strtoupper($row2["plan"]), "BOXEO") !== FALSE){
                                        echo '<img src="../img/icons/boxeo-ico.png" width="20px;">';
                                    }
                                    if (strpos(strtoupper($row2["plan"]), "MMA") !== FALSE) {
                                        echo '<img src="../img/icons/mma-ico.png" width="20px;">';
                                    }
                                    if (strpos(strtoupper($row2["plan"]), "BJJ") !== FALSE) {
                                        echo '<img src="../img/icons/bjj-ico.png" width="20px;">';
                                    }
                                    if (strpos(strtoupper($row2["plan"]), "TIQUETERA") !== FALSE){
                                        echo '<i class="fa-solid fa-ticket"></i>';
                                    }
                                    if (($row2["saldo_clases_personalizadas"] != "") && ($row2["saldo_clases_personalizadas"] != "0")) {
                                        echo '<i class="fa-solid fa-fingerprint"></i>';
                                    }
                                    echo '</div>';

                                    echo '<div class="infoUsuarioOnDeck">';
                                        $imageUrl = "../img/grados/" . $row2["grado"] . ".png";
                                        if ((file_exists($imageUrl)) && ($row2["grado"] != "")){
                                            echo '<img src="'. $imageUrl . '" alt="img-cinta" class="cinta-usuario" style="margin-top: 5px;">';
                                        } else {
                                            echo '<img src="../img/missingGrado.png" alt="img-cinta" class="cinta-usuario">';
                                        }
                                    echo '</div>';

                                    $grado = $row2["grado"];
                                    $imageUrl = "../img/" . preg_replace('/[0-9]+/', '', $grado) . ".png";
                                    if ((file_exists($imageUrl)) && ($grado != "")){
                                        $imageUrl = "'../img/" . preg_replace('/[0-9]+/', '', $grado) . ".png'";
                                    } else {
                                        $imageUrl = "'../img/missingGrado.png'";
                                    }
                                    $progreso = $row2["progreso"];
                                    echo '<div class="infoUsuarioOnDeck">
                                        <div class="progress-bar-container" style="width: 100%;">
                                            <div style="height:18px; width:' . $progreso . '%; 
                                                        background-image: url(' . $imageUrl . ');
                                                        background-position: left;
                                                        background-size: 100% 18px;
                                                        border:1px solid black; font-weight: bolder; text-align: center;">
                                            ' . $progreso . '%
                                            </div>
                                        </div>
                                    </div>';

                                    if ($hora1 == "100") {
                                        $timeStr = str_pad($row["horasminutos"], 4, '0', STR_PAD_LEFT);
                                        $hours = substr($timeStr, 0, 2);
                                        $minutes = substr($timeStr, 2, 2);
                                        $formattedTime = $hours . ':' . $minutes;
                                        echo '<div class="infoUsuarioOnDeck">
                                            <b>Personalizada: </b>' . $formattedTime . ' / <b>Coach: </b>';
                                            $coach="";
                                            $pos = stripos($row["comentarios"], "personalizada");
                                            if ($pos !== false) {
                                                // $coach = substr($row["comentarios"], $pos + strlen("personalizada") + strlen(", Coach:"));
                                                $coach = substr($row["comentarios"], $pos);
                                                $coach = substr($coach, strrpos($coach, ":")+1);
                                                $coach = trim($coach);
                                                $sql3 = "SELECT nombre FROM satori_alumnos WHERE nombre_tabla = '$coach'";
                                                $res3 = mysqli_query($conn, $sql3);
                                                if ($res3){
                                                    if (mysqli_num_rows($res3) > 0){
                                                        while ($row3 = $res3->fetch_assoc()){
                                                            $coach = $row3["nombre"];
                                                        }
                                                    } else {
                                                        $coach = "sin asignar";
                                                    }
                                                } else {
                                                    $coach = "sin asignar";
                                                }
                                            } else {
                                                $coach = "No es personalizada";
                                            }
                                            echo $coach;
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                        }
                    } else {
                        echo 'PHPerror1: ' . $conn->error;
                    }
                }
            }
        } else {
            echo "PHPerror2: " . $conn->error;
        }
        echo '</div>';
    }
}

function actualizarEdad($conn, $fechaNacimiento, $uuid, $date1) {
    $date2 = new DateTime($fechaNacimiento);
    $restaFecha = $date1->diff($date2); //$date1 es la fecha de hoy
    $edadActual = $restaFecha->y;
    $sql2 = "UPDATE satori_alumnos SET edad = '$edadActual' WHERE nombre_tabla = '$uuid'";
    mysqli_query($conn, $sql2);
}

function actualizarProgreso($conn, $uuid, $grado) {
    
    $progreso = 1;
    $asistenciaObjetivo = 1;
    $progresoActual = 0;

    if (stripos($grado, "azul") !== false){
        $asistenciaObjetivo = 576;
    } elseif (stripos($grado, "morado") !== false){
        $asistenciaObjetivo = 432;
    } elseif (stripos($grado, "marron") !== false){
        $asistenciaObjetivo = 288;
    } elseif (stripos($grado, "negro") !== false){
        $asistenciaObjetivo = 2880;
    } else {
        $asistenciaObjetivo = 288;
    }

    if (strpos($grado, "1") !== false){
        $progresoActual = 20;
    } else if (strpos($grado, "2") != false) {
        $progresoActual = 40;
    } else if (strpos($grado, "3") != false) {
        $progresoActual = 60;
    } else if (strpos($grado, "4") != false) {
        $progresoActual = 80;
    } else {
        $progresoActual = 0;
    }
    
    $sql2 = "SELECT fecha FROM eventos WHERE uuid = '$uuid' AND evento LIKE '%graduacion%' ORDER BY fecha DESC LIMIT 1";
    $resultado2 = mysqli_query($conn, $sql2);
    if ($resultado2){
        if (mysqli_num_rows($resultado2) > 0){
            while ($row2 = mysqli_fetch_assoc($resultado2)){
                $fechaUltimaGraduacion = $row2["fecha"];
            }
        } else {
            $fechaUltimaGraduacion = 0;
        }
    } else {
        echo mysqli_error($conn);
    }
    
    $sql2 = "SELECT id FROM eventos WHERE uuid = '$uuid' 
        AND evento LIKE '%check%'
        AND fecha >= '$fechaUltimaGraduacion'
        AND (comentarios = ''
        OR comentarios LIKE '%bjj%')
    ";
    $resultado2 = mysqli_query($conn, $sql2);
    if ($resultado2){
        $asistencias = mysqli_num_rows($resultado2);
        $progreso = $progreso + round(($asistencias/$asistenciaObjetivo)*100);
    } else {
        echo mysqli_error($conn);
    }
    
    $progreso = $progresoActual+$progreso;
    if ($progreso == 0) $progreso = 1; 
    if ($progreso > 100) $progreso = 100; 

    $sql2 = "UPDATE satori_alumnos SET progreso = '$progreso' WHERE nombre_tabla = '$uuid'";
    $resultado2 = mysqli_query($conn, $sql2);
    if (!$resultado2){
        echo mysqli_error($conn);
    }

}

function rangoHorasClases($fecha, $hms){
    $scheduleFile = '../json/gym_schedule.json';
    if (!file_exists($scheduleFile)) {
        return ["a", "b"]; // Return empty array if file doesn't exist
        throw new Exception("El archivo de horario no fue encontrado.");
    }
    $jsonData = file_get_contents($scheduleFile);
    $schedule = json_decode($jsonData, true);

    $timeStr = str_pad($hms, 4, '0', STR_PAD_LEFT);
    $searchTime = substr($timeStr, 0, 2) . ':' . substr($timeStr, 2, 2);
    
    $rangos = ["a", "b"];
    
    // Primero verificar si la fecha está en holidays
    if (isset($schedule['holidays'][$fecha])) {
        foreach ($schedule['holidays'][$fecha] as $ageGroup => $activities) {
            foreach ($activities as $activity => $classes) {
                foreach ($classes as $class) {
                    // Check if the search time falls within class time range
                    if ($searchTime >= $class['start_time'] && $searchTime <= $class['end_time']) {
                        $startTimeInt = (int)str_replace(':', '', $class['start_time']);
                        $endTimeInt = (int)str_replace(':', '', $class['end_time']);
                        $rangos = [$startTimeInt, $endTimeInt];
                        return $rangos;
                    }
                }
            }
        }
    }
    
    // Si no es holiday, usar la lógica normal de días de la semana
    $dayOfWeek = date('l', strtotime($fecha));
    
    foreach ($schedule['age_groups'] as $ageGroup => $activities) {
        foreach ($activities as $activity => $classes) {
            // Handle different data structures (array vs object)
            if (is_array($classes) && isset($classes[0])) {
                // Classes is an array
                foreach ($classes as $class) {
                    if (in_array($dayOfWeek, $class['days_of_week'])) {
                        // Check if the search time falls within class time range
                        if ($searchTime >= $class['start_time'] && $searchTime <= $class['end_time']) {
                            $startTimeInt = (int)str_replace(':', '', $class['start_time']);
                            $endTimeInt = (int)str_replace(':', '', $class['end_time']);
                            $rangos = [$startTimeInt, $endTimeInt];
                        }
                    }
                }
            } else {
                // Classes is an object with numbered keys
                foreach ($classes as $classKey => $class) {
                    if (in_array($dayOfWeek, $class['days_of_week'])) {
                        // Check if the search time falls within class time range
                        if ($searchTime >= $class['start_time'] && $searchTime <= $class['end_time']) {
                            $startTimeInt = (int)str_replace(':', '', $class['start_time']);
                            $endTimeInt = (int)str_replace(':', '', $class['end_time']);
                            
                            $rangos = [$startTimeInt, $endTimeInt];
                        }
                    }
                }
            }
        }
    }
    return $rangos;
}

function hallarSaldoClases($conn, $uuid){
    $saldo_clases = [];
    $sql1 = "SELECT saldo_clases_personalizadas, id FROM eventos WHERE 
            uuid = '$uuid' AND 
            (comentarios LIKE '%Personalizada%' OR
            item LIKE '%Personalizada%') AND 
            (evento LIKE '%check%' OR 
            evento LIKE '%pago%' OR
            evento LIKE '%Cambio de Estado%') 
            ORDER BY fecha DESC, id DESC LIMIT 1";
    $sql2 = "SELECT saldo_clases FROM eventos WHERE 
            uuid = '$uuid' AND 
            comentarios NOT LIKE '%personalizada%' AND
            item NOT LIKE '%personalizada%' AND
            saldo_clases != '' AND 
            (evento LIKE '%check%' OR 
            evento LIKE '%pago%' OR
            evento LIKE '%Cambio de Estado%')
            ORDER BY fecha DESC, id DESC LIMIT 1";
    $res1 = mysqli_query($conn, $sql1);
    $res2 = mysqli_query($conn, $sql2);
    if($res1 && $res2){
        if (mysqli_num_rows($res1) > 0){
            // echo "filas afctadas: " . mysqli_num_rows($res1) . "<br>";
            while ($row = mysqli_fetch_assoc($res1)){
                $saldo_clases[] = $row["saldo_clases_personalizadas"];
                // echo "ID evento: " . $row["id"] . "<br>";
            }
        } else {
            $saldo_clases[] = "";
        }
        if (mysqli_num_rows($res2) > 0){
            while ($row = mysqli_fetch_assoc($res2)){
                $saldo_clases[] = $row["saldo_clases"];
            }
        } else {
            $saldo_clases[] = "";
        }
    } else {
        echo mysqli_error($conn);
    }
    $sql = "UPDATE satori_alumnos SET saldo_clases = '$saldo_clases[1]', saldo_clases_personalizadas = '$saldo_clases[0]' WHERE nombre_tabla = '$uuid'";
    $res= mysqli_query($conn, $sql);
    if (!$res) {
        echo mysqli_error($conn);
    }
    return $saldo_clases;
}

function generarUuid($conn) {
    $existe = true;
    while ($existe){
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $newUuid = random_bytes(8);
        assert(strlen($newUuid) == 8);
    
        // Set version to 0100
        $newUuid[6] = chr(ord($newUuid[6]) & 0x0f | 0x40);
        
        // Output the 12 character UUID.
        $newUuid = vsprintf('%s%s%s%s', str_split(bin2hex($newUuid), 3));

        // Verificar que dicho UUID no existe en la tabla de alumnos
        $sqlVerificarUuid = "SELECT * FROM satori_alumnos WHERE nombre_tabla = '$newUuid' OR temp_pw = '$newUuid' OR qr_activo = '$newUuid'";
        $resVerificarUuid = mysqli_query($conn, $sqlVerificarUuid);
        if ($resVerificarUuid->num_rows === 0){
            $existe = false;
        }
    }
 
    // Return the 12 character UUID.
    return $newUuid;
}

function prepImprimirFilaAsistencias($conn, $uuid, $onDeckArray) {
    $sql = "SELECT * FROM satori_alumnos WHERE nombre_tabla = '$uuid'";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        if (($res->num_rows > 0)) {
            while ($row = mysqli_fetch_assoc($res)){
                $filaCompleta = imprimirFilaAsistencias($row, $onDeckArray);
                return $filaCompleta;
            }
        } else {
            throw new Exception ("sin resultados: " . mysqli_num_rows($res));
        }
    } else {
        throw new Exception("Error en consulta prepImprimirFilaAsistencias: " . mysqli_error($conn));
    }
}

function imprimirFilaAsistencias($row, $onDeckArray) {
    $respuestaHtml = '<tr onmouseenter="filaActual(this)" onmouseleave="filaAnterior(this)">';
    $testFoto = "../img/users/" . $row["foto"];
    if ((file_exists($testFoto)) && ($row["foto"] != "")){
        $foto = '<img src="' . $testFoto . '" alt="?" class="fotoUsuario">';
    } else {
        $foto = '<i class="fa-solid fa-user-tie fotoUsuario"></i>';
    }
    $respuestaHtml .= '<td id="columna_0">' . $foto . '</td>';

    $respuestaHtml .= '<td id="columnaNombre" class="columnaNombre" onclick="consultarUsuarioPorNombre(event)">' . $row["nombre"];
    if (($row["saldo_clases"] != "") && (stripos($row["plan"], "Tiquetera") !== FALSE)){
        $respuestaHtml .= ' / T <span style="font-weight: bold;">[ <span>' . $row["saldo_clases"] . '</span> ]</span>';
    } else if (($row["saldo_clases"] != "") && ($row["saldo_clases"] != "0")){
        $respuestaHtml .= ' / T <span style="font-weight: bold;">[ <span>' . $row["saldo_clases"] . '</span> ]</span>';
    } else {
        $respuestaHtml .= '<span style="font-weight: bold; display: none;"> / T [ <span>' . $row["saldo_clases"] . '</span> ]</span>';
    }
    if (($row["saldo_clases_personalizadas"] != "") && ($row["saldo_clases_personalizadas"] != "0")) {
        $respuestaHtml .= ' + P <span style="font-weight: bold;">[ <span>' . $row["saldo_clases_personalizadas"] . '</span> ]</span>';
    } else {
        $respuestaHtml .= '<span style="font-weight: bold; display: none;"> + P [ <span>' . $row["saldo_clases_personalizadas"] . '</span> ]</span>';
    }
    $respuestaHtml .= '</td>';
    
    $respuestaHtml .= '<td id="columna_2" style="display: none;">' . $row["celular"] . '</td>';
    
    $respuestaHtml .= '<td id="columna_3" style="display: none;">' . $row["email"] . '</td>';
    
    $colorEstado = '';
    if ($row["estado"] == "activo") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-toggle-on" style="margin-left: 10px; color: #00e616;"></i></td>';
    } else if ($row["estado"] == "saldobajo") {
        $colorEstado = '#f8ff42';
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-toggle-on" style="margin-left: 10px; color: ' . $colorEstado . ';"></i></td>';
    } else if ($row["estado"] == "pendiente") {
        $colorEstado = '#ff8a00';
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-circle-pause" style="margin-left: 10px; color: ' . $colorEstado . ';"></i></td>';
    } else if ($row["estado"] == "congelado") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-regular fa-snowflake" style="margin-left: 10px; color: #00d2ff;"></i></td>';
    } else if ($row["estado"] == "inactivo") {
        $colorEstado = '#ff0000';
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-toggle-off" style="margin-left: 10px; color: ' . $colorEstado . ';"></i></td>';
    } else if ($row["estado"] == "convenio") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-handshake" style="margin-left: 10px; color: black;"></i></td>';
    } else if ($row["estado"] == "staff") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-id-card-clip" style="margin-left: 10px; color: black;"></i></td>';
    } else {
        $respuestaHtml .= '<td id="columna_4">?</td>';
    }

    $respuestaHtml .= '<td id="columna_5" style="display: none;">' . $row["estado"] . '</td>';
    
    $respuestaHtml .= '<td id="columna_6" style="display: none;">';
    if ($row["estado"] == 'activo') {
        $respuestaHtml .= '1';
    } else if ($row["estado"] == "saldobajo") {
        $respuestaHtml .= '2';
    } else if ($row["estado"] == "pendiente") {
        $respuestaHtml .= '3';
    } else if ($row["estado"] == "congelado") {
        $respuestaHtml .= '4';
    } else if ($row["estado"] == "convenio") {
        $respuestaHtml .= '5';
    } else if ($row["estado"] == "staff") {
        $respuestaHtml .= '6';
    } else if ($row["estado"] == "inactivo") {
        $respuestaHtml .= '7';
    }
    $respuestaHtml .= '</td>';
    
    $promoPlan = "";
    if (strpos(strtolower($row["plan"]), 'bjj') !== false) {
        $promoPlan = "BJJ";
    } else if (strpos(strtolower($row["plan"]), 'boxeo') !== false){
        $promoPlan = "Boxeo";
    } else if (strpos(strtolower($row["plan"]), 'mma') !== false) {
        $promoPlan = "MMA";
    } else if (stripos($row["plan"], 'tiquetera') !== false) {
        $promoPlan = "Tiquetera";
    } else if (stripos($row["plan"], 'personalizada') !== false) {
        $promoPlan = "Personalizadas";
    } else {
        $promoPlan = "Otros";
    }

    $respuestaHtml .= '<td id="columna_7" style="text-align: center" class="noMobile">';
    if ($promoPlan == "Kids") {
        $respuestaHtml .= '<i class="fa-solid fa-child" style="color: purple;"></i></td>';
    } else if($promoPlan == "Tiquetera10C") {
        $respuestaHtml .= '<i class="fa-solid fa-ticket"></i></td>';
    } else if ($promoPlan == "Kids2"){
        $respuestaHtml .= '<i class="fa-solid fa-child" style="color: purple;"></i></td>';
        $promoPlan = "Kids";
    }else {
        $respuestaHtml .= '<i class="fa-solid fa-user-tie"></i></td>';
    }
    $respuestaHtml .= '</td>';

    $respuestaHtml .= '<td id="columna_8" style="display: none;">' . $promoPlan . '</td>';
    if (stripos($promoPlan, "Otros") !== false) {
        $respuestaHtml .= '<td id="columna_9" style="display: none;">Otros</td>';
    } else {
        $respuestaHtml .= '<td id="columna_9" style="display: none;">' . $row["plan"] . '</td>';
    }

    $cinta = 'white';
    $grado = $row["grado"];
    if (stripos($grado, "azul") !== false){
        $cinta = 'blue';
    } elseif (stripos($grado, "morado") !== false){
        $cinta = 'purple';
    } elseif (stripos($grado, "marron") !== false){
        $cinta = 'brown';
    } elseif (stripos($grado, "negro") !== false){
        $cinta = 'black';
    } else {
        $cinta = 'white';
    }
    $imageUrl = "../img/" . preg_replace('/[0-9]+/', '', $grado) . ".png";
    if ((file_exists($imageUrl)) && ($grado != "")){
        $imageUrl = "'../img/" . preg_replace('/[0-9]+/', '', $grado) . ".png'";
    } else {
        $imageUrl = "'../img/missingGrado.png'";
    }

    $progreso = $row["progreso"];
    $respuestaHtml .= '<td id="columna_10">
        <div class="progress-bar-container">
            <div style="height:18px; width:' . $progreso . '%; 
                        background-image: url(' . $imageUrl . ');
                        background-position: left;
                        background-size: 100% 18px;
                        border:1px solid black; font-weight: bolder; text-align: center;">
            ' . $progreso . '%
            </div>
        </div>
    </td>';

    $uuid = $row["nombre_tabla"];
    $onDeck = false;
    if ($onDeckArray === null) {
        $onDeck = true;
    } else if (in_array($uuid, $onDeckArray)) {
        $onDeck = true;
    }

    $respuestaHtml .= '<td id="columna_11">
        <button onclick="enviarFormularioAgregarEvento(this)"
        style="min-width: 75px; background-color: ' . $colorEstado . ';"';
        if($onDeck){
            $respuestaHtml .= 'disabled';
        }
    $respuestaHtml .= '>
        Check-In
        </button>
        </td>'; 
        
    $respuestaHtml .= '<td style="display: none" id="columna_12">' . $row["nombre_tabla"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_13">' . $row["saldo_clases"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_14"></td>';
    $respuestaHtml .= '<td style="display: none" id="columna_15"  title="columnaAsistencias">';
    $respuestaHtml .= '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_16">' . $progreso . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_17"></td>';
    $respuestaHtml .= '<td style="display: none" id="columna_18">' . $onDeck . '</td>';

    if ($row["edad"] == 0){
        $categoriaEdad = "Indeterminado";
    } else if ($row["edad"] > 17) {
        $categoriaEdad = "Adultos";
    } else if ($row["edad"] > 12){
        $categoriaEdad = "Teens (13 - 18 años)";
    } else {
        $categoriaEdad = "Kids (4 - 12 años)";
    }
    $respuestaHtml .= '<td style="display: none" id="columna_19">' . $categoriaEdad . '</td>';
    
    $respuestaHtml .= '<td style="display: none" id="columna_20">' . $row["uso_compartido"] . '</td>';

    global $gradosArray;
    $indexGrados = count($gradosArray)-1;
    if (stripos($row["grado"], "blanco") === 0){
        $indexGrados = 0;
    } else {
        for ($i=1; $i<count($gradosArray); $i++) {
            if(stripos($row["grado"], $gradosArray[$i]) !== FALSE) $indexGrados = $i;
        }
    }
    $respuestaHtml .= '<td style="display: none" id="columna_20">' . $gradosArray[$indexGrados] . '</td>';
    $respuestaHtml .= '</tr>';

    return $respuestaHtml;
}

function imprimirFilaUsuarios($row) {
    if ($row["edad"] == 0){
        $categoriaEdad = "Indeterminado";
    } else if ($row["edad"] > 17) {
        $categoriaEdad = "Adultos";
    } else if ($row["edad"] > 12){
        $categoriaEdad = "Teens (13 - 18 años)";
    } else {
        $categoriaEdad = "Kids (4 - 12 años)";
    }
    $respuestaHtml = '<tr onmouseenter="filaActual(this)" onmouseleave="filaAnterior(this)">';
    $respuestaHtml .= '<td id="columna_0">' . $row["id"] . '</td>';
    $respuestaHtml .= '<td id="columnaNombre" class="columnaNombre" title="columnaNombre" onclick="consultarUsuarioPorNombre(event)">' . $row["nombre"]. '</td>';
    $respuestaHtml .= '<td id="columna_2" title="columnaCelular">' . $row["celular"] . '</td>';
    $respuestaHtml .= '<td id="columna_3" title="columnaEmail" class="noMobile">' . $row["email"] . '</td>';
    if ($row["estado"] == "activo") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-toggle-on" style="margin-left: 10px; color: #00e616;"></i></td>';
    } else if ($row["estado"] == "saldobajo") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-toggle-on" style="margin-left: 10px; color: #f8ff42;"></i></td>';
    } else if ($row["estado"] == "pendiente") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-circle-pause" style="margin-left: 10px; color: #ff8a00;"></i></td>';
    } else if ($row["estado"] == "congelado") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-regular fa-snowflake" style="margin-left: 10px; color: #00d2ff;"></i></td>';
    } else if ($row["estado"] == "inactivo") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-toggle-off" style="margin-left: 10px; color: #ff0000;"></i></td>';
    } else if ($row["estado"] == "convenio") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-handshake" style="margin-left: 10px; color: black;"></i></td>';
    } else if ($row["estado"] == "staff") {
        $respuestaHtml .= '<td id="columna_4"><i class="fa-solid fa-id-card-clip" style="margin-left: 10px; color: black;"></i></td>';
    } else {
        $respuestaHtml .= '<td id="columna_4">i</td>';
    }
    $respuestaHtml .= '<td id="columna_5" title="columnaEstadoTexto" class="noMobile">' . $row["estado"] . '</td>';
    $respuestaHtml .= '<td id="columna_6"  style="display: none">';
        if ($row["estado"] == 'activo') {
            $respuestaHtml .= 1;
        } else if ($row["estado"] == "saldobajo") {
            $respuestaHtml .= 2;
        } else if ($row["estado"] == "pendiente") {
            $respuestaHtml .= 3;
        } else if ($row["estado"] == "congelado") {
            $respuestaHtml .= 4;
        } else if ($row["estado"] == "convenio") {
            $respuestaHtml .= 5;
        } else if ($row["estado"] == "staff") {
            $respuestaHtml .= 6;
        } else if ($row["estado"] == "inactivo") {
            $respuestaHtml .= 7;
        }
        $respuestaHtml .= '</td>';
    $respuestaHtml .= '<td id="columna_7" title="columnaFechaVencimiento">' . $row["fecha_vencimiento"]. '</td>';
    $respuestaHtml .= '<td id="columna_8" title="columnaPromocion">' . $row["promocion"] . '</td>';
    $respuestaHtml .= '<td id="columna_9" title="columnaPlan">' . $row["plan"] . '</td>';
    $respuestaHtml .= '<td style="font-size: large" id="columna_10">';
    if (!existeArchivoConString(quitarSeparadorMiles($row["cedula"]))) {
        $respuestaHtml .= '<i class="fa-regular fa-file-lines" style="padding-left: 5px;" onclick="firmarWaiver(this)"></i>';
    }
    if (!informacionCompleta($row)) {
        $respuestaHtml .= '<i class="fa-solid fa-triangle-exclamation" style="padding-left: 5px; color: red;" title="Falta información importante." onclick="mostrarInformacionIncompleta(this)"></i>';
    }
    // $respuestaHtml .= '
    //     <i class="fa-regular fa-envelope" style="padding-left: 5px;"></i>';
    $respuestaHtml .=
            '<i class="fa-brands fa-whatsapp" style="padding-left: 5px;" onclick="mostrarMensajesWapp(this)"></i>
        </td>';
    $respuestaHtml .= '<td style="display: none" id="columna_11" title="columnaApodo">' . $row["apodo"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_12" title="columnaFechaInicio">' . $row["fecha_inicio"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_13" title="columnaFechaMensajeBienvenida">' . $row["wapp_bienvenida"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_14" title="columnaFechaMensajeSaldoBajo">' . $row["wapp_saldobajo"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_15" title="columnaFechaMensajePendiente">' . $row["wapp_pendiente"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_16" title="columnaAcudiente">' . $row["nombre_acudiente1"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_17">' . $categoriaEdad . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_18">' . $row["nombre_tabla"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_19">' . $row["cedula"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_20">' . $row["fecha_nacimiento"] . '</td>';
    $respuestaHtml .= '<td style="display: none" id="columna_21">' . $row["eps"] . '</td>';
    $respuestaHtml .= '</tr>';

    return $respuestaHtml;
}

function obtenerClasesDisponiblesParaUsuario($plan, $edad, $fecha, $simple) {
    
    if ($edad > 17) $edad = "Adultos";
    else if ($edad > 11) $edad = "Teens";
    else $edad = "Kids";

    $scheduleFile = '../json/gym_schedule.json';
    if (!file_exists($scheduleFile)) {
        return ["error" => "El archivo de horario no fue encontrado."];
    }
    
    $contenidoJson = file_get_contents($scheduleFile);
    if ($contenidoJson === false) {
        return ["error" => "No se pudo leer el archivo de horario."];
    }
    
    $horarioJson = json_decode(file_get_contents($scheduleFile), true);
    if ($horarioJson === null || !isset($horarioJson['age_groups'][$edad])) {
        return ["error" => "El archivo de horario no contiene el grupo de edad especificado."];
    }
    
    // Obtener el día actual en inglés
    include 'includes/timeZoneDetect.php';
    // date_default_timezone_set('America/Bogota');
    if ($fecha !== null) {
        $diaConsulta = date('l', strtotime($fecha)); // Monday, Tuesday, etc.
        $horaConsulta = '01:00';
    } else {
        $diaConsulta = date('l'); // Monday, Tuesday, etc.
        $horaConsulta = date('H:i');
    }
    
    // Obtener disciplinas del grupo de edad
    $disciplinas = array_keys($horarioJson["age_groups"][$edad]);

    // Buscar TODAS las disciplinas válidas según el plan
    $disciplinasValidas = [];
    
    // Primero buscar en el grupo de edad principal
    foreach ($disciplinas as $disciplina) {
        $keyword = explode(" ", $disciplina)[0]; // primera palabra
        if (stripos($plan, $keyword) !== false) {
            $disciplinasValidas[] = ['disciplina' => $disciplina, 'grupoEdad' => $edad];
        }
    }
    
    // Ahora buscar en TODOS los grupos de edad si allowed_age_groups incluye la edad del usuario
    foreach ($horarioJson['age_groups'] as $grupoEdadActual => $disciplinasGrupo) {
        if ($grupoEdadActual === $edad) continue; // Ya revisamos el grupo principal
        
        foreach ($disciplinasGrupo as $disciplina => $horariosDisciplina) {
            $keyword = explode(" ", $disciplina)[0];
            if (stripos($plan, $keyword) === false) continue;
            
            // Verificar si algún horario tiene allowed_age_groups que incluya la edad del usuario
            foreach ($horariosDisciplina as $horario) {
                if (isset($horario['allowed_age_groups']) && in_array($edad, $horario['allowed_age_groups'])) {
                    // Evitar duplicados
                    $yaExiste = false;
                    foreach ($disciplinasValidas as $dv) {
                        if ($dv['disciplina'] === $disciplina && $dv['grupoEdad'] === $grupoEdadActual) {
                            $yaExiste = true;
                            break;
                        }
                    }
                    if (!$yaExiste) {
                        $disciplinasValidas[] = ['disciplina' => $disciplina, 'grupoEdad' => $grupoEdadActual];
                    }
                    break; // Ya encontramos uno, no necesitamos revisar más horarios de esta disciplina
                }
            }
        }
    }

    if ($simple){
        // Extraer solo los nombres de las disciplinas para el modo simple
        $resultado = [];
        foreach ($disciplinasValidas as $dv) {
            $resultado[] = $dv['disciplina'];
        }
        return array_unique($resultado);
    }

    $clasesDisponibles = [];
    
    // Recorrer cada disciplina válida
    foreach ($disciplinasValidas as $disciplinaInfo) {
        $disciplina = $disciplinaInfo['disciplina'];
        $grupoEdadDisciplina = $disciplinaInfo['grupoEdad'];
        $horariosDisciplina = $horarioJson['age_groups'][$grupoEdadDisciplina][$disciplina];
        
        // Recorrer cada horario de la disciplina
        foreach ($horariosDisciplina as $horario) {
            // Si no es del grupo principal, verificar allowed_age_groups
            if ($grupoEdadDisciplina !== $edad) {
                if (!isset($horario['allowed_age_groups']) || !in_array($edad, $horario['allowed_age_groups'])) {
                    continue;
                }
            }
            
            // Verificar si la clase es hoy
            if (!in_array($diaConsulta, $horario['days_of_week'])) {
                continue;
            }
            
            // Verificar si la clase es en la hora actual o más tarde
            if ($horario['start_time'] >= $horaConsulta) {
                $clasesDisponibles[] = [
                    'disciplina' => $disciplina,
                    'hora_inicio' => $horario['start_time'],
                    'hora_fin' => $horario['end_time'],
                    'edad' => $grupoEdadDisciplina
                ];
            }
        }
    }
    
    // Ordenar las clases por hora de inicio
    usort($clasesDisponibles, function($a, $b) {
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    });
    
    return $clasesDisponibles;
}

function logout() {
    session_start();
    // remove all session variables
    session_unset();

    // destroy the session
    session_destroy();
}

function informacionCompleta($row) {
    if ($row["nombre"] == "" || $row["email"] == "" || $row["fecha_nacimiento"] == "" || $row["cedula"] == "") {
        return false;
    }
    return true;
}

define("BASE_PATH", dirname(dirname(__DIR__)));
function existeArchivoConString($stringBuscado) {
    $carpeta = str_replace('\\', '/', BASE_PATH . '/docs/signed');
    
    // Verificar si la carpeta existe
    if (!is_dir($carpeta)) {
        error_log("La carpeta no existe: " . $carpeta);
        return true;
    }

    // Verificar que el string no esté vacío
    if (empty($stringBuscado)) {
        // error_log("String de búsqueda vacío");
        return false;
    }

    // Obtener lista de archivos
    $archivos = glob($carpeta . '/*');
    if ($archivos === false) {
        error_log("Error al leer archivos de: " . $carpeta);
        return true;
    }

    // Buscar coincidencia
    foreach ($archivos as $archivo) {
        $nombreArchivo = basename($archivo);
        if (stripos($nombreArchivo, $stringBuscado) !== false) {
            error_log("Archivo encontrado: " . $nombreArchivo);
            return true;
        }
    }

    error_log("No se encontró archivo con: " . $stringBuscado);
    return false;
}