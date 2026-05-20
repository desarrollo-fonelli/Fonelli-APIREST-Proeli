<?php

/**
 * RecalcPrecPed-trigger.php
 * -----------------------------------------------------------------------------
 * Script CLI para disparar el recálculo de precios en pedidos de cliente
 * Puede ejecutarse desde la línea de comando o desde un archivo batch, y puede
 * programarse en windows con el programador de tareas o en Linux con cron.
 * Uso: php RecalcPrecPed-trigger.php
 * -----------------------------------------------------------------------------
 * dRendon | 05.03.2026 | Creación del script
 */

declare(strict_types=1);

set_time_limit(60 * 5);

date_default_timezone_set('America/Mexico_City');

$apiUrl = "http://localhost/med_fonelli_apiportal/procesos/RecalcPrecPed-controlador.php";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
//  ------   Quita el comentario a las lineas siguientes para ver la respuesta 
//           completa del servidor incluyendo errores.
// print_r(json_decode($response, true));
// var_dump($response);
// exit;
//  ------
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
} else {
    echo "Respuesta del Servidor ($httpCode):\n";
    print_r(json_decode($response, true));
}

// curl_close($ch);   no es necesario en PHP 8.0+ ya que se cierra automáticamente al finalizar el script
