<?php
// Zona horaria por defecto si no existe cookie
$defaultTimezone = "America/Bogota";

// Lee la cookie enviada por JS
if (!empty($_COOKIE['timezone'])) {
    $tz = $_COOKIE['timezone'];
    // Validar que sea un timezone válido
    if (in_array($tz, timezone_identifiers_list())) {
        date_default_timezone_set($tz);
    } else {
        date_default_timezone_set($defaultTimezone);
    }
} else {
    date_default_timezone_set($defaultTimezone);
}

$anohoyString = date("Y");
$meshoyString = date("m");
$diahoyS = date("d");
$fechaHoyString = date('Y\-m\-d');
// $fechahoy = date("Y\-m\-d" , mktime(0,0,0,$meshoy,$diahoy,$anohoy));

$fechaHoyObject = new DateTime($fechaHoyString); //$date1 es la fecha de hoy
$mesDia = DATE_FORMAT($fechaHoyObject, 'm-d');

$diasSemana = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];
