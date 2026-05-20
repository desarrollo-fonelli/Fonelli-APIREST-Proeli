<?php

/**
 * RecalcPrecPed-controlador.php
 * -----------------------------------------------------------------------------
 * Controla el proceso de recálculo de precios en pedidos de cliente
 * -----------------------------------------------------------------------------
 * dRendon | 05.03.2026 | Creación del script
 */

declare(strict_types=1);
set_time_limit(60 * 5);

date_default_timezone_set('America/Mexico_City');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Auth');
header('Content-type: application/json; charset=UTF-8');

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";
require_once "../db/conexion.php";

# Clase para realizar el recálculo de precios en pedidos de cliente
require_once './RecalcPrecPedServicio.php';

# Clase para escribir logs de errores, información, etc.
require_once "../include/LoggerService.php";

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "POST") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos POST";
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje]);
  exit;
}

$logger = new LoggerService();
$logger->logInfo("Iniciando proceso de recálculo de precios en pedidos de cliente");

try {

  // $db = (new Database())->getConnection();
  # Se conecta a la base de datos
  $logger->logInfo("Se conecta a la base de datos");
  $oDB = DB::getConn();

  $service = new RecalcPrecPedServicio($oDB);
  $result = $service->updatePrecioPedidos();

  echo json_encode($result);
} catch (Exception $e) {
  //$logger->logError("Error en el proceso: " . $e->getMessage(), ["exception" => $e]);

  http_response_code(500);
  echo json_encode(["error" => $e->getMessage()]);
}
