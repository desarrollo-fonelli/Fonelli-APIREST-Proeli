<?php
@session_start();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-type: application/json: charset=UTF-8');
date_default_timezone_set('America/Mexico_City');

/**
 * Test Documentos de Venta
 * --------------------------------------------------------------------------
 * Prueba INSERT de registros en tablas de documentos de venta
 * Creación: dRendon 15.08.2025
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "DocTest.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario    = null;     // Tipo de usuario
$Usuario        = null;     // Id del usuario (cliente, agente o gerente)
$Token          = null;     // Token obtenido por el usuario al autenticarse
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$docVenta       = null;     // Objeto JSON con datos generales y detalle del documento de venta

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "POST") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos POST";
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje]);
  exit;
}

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {
  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME de los mensajes
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (!in_array($TipoUsuario, ["C", "A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
    }
  }

  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  } else {
    $Usuario = $_GET["Usuario"];
  }

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # dRendon 04.05.2023 ********************
  # Ahora se va a verificar la identidad del usuario por medio del Token
  # recibido en el Header con Key "Auth" (PHP lo interpreta como "HTTP_AUTH")
  if (!isset($_SERVER["HTTP_AUTH"])) {
    throw new Exception("No se recibio el Token de autenticacion");
  } else {
    $Token = $_SERVER["HTTP_AUTH"];
  }

  if (!ValidaToken($conn, $TipoUsuario, $Usuario, $Token)) {
    throw new Exception("Error de autenticacion.");
  }

  // -------

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "ClienteCodigo",
  "ClienteFilial"
);

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_GET);
foreach ($arrParam as $param) {
  if (!in_array($param, $arrPermitidos)) {
    if (strlen($mensaje) > 1) {
      $mensaje .= ", ";
    }
    $mensaje .= $param;
  }
}
if (strlen($mensaje) > 0) {
  $mensaje = "Parametros no reconocidos: " . $mensaje;   // quité K_SCRIPTNAME del mensaje
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
  exit;
}

/**
 * Lee el JSON y lo decodifica para que PHP pueda usarlo
 * El JSON se debe recibir en el BODY de la petición POST
 */
$input = file_get_contents("php://input");
$docVenta = json_decode($input);

/**
 * Verifica la estructura del JSON 
 */
if (json_last_error() !== JSON_ERROR_NONE || !$docVenta) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "JSON inválido o datos vacíos"]);
  exit;
}


/**
 * Hay que pasar a variables los datos recibidos
 */


/**
 * Ejecuta el comando SQL para crear insertar los registros del documento
 */
try {

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Envuelvo las operaciones INSERT en una transacción
  $conn->beginTransaction();

  # DATOS GENERALES DEL DOCUMENTO Construyo dinámicamente el statment SQL
  # Faltan: fecha creación, ...
  # No se incluyen ord_id ni ord_folio porque se asignan en la base de datos
  $sqlCmd = "INSERT INTO testdocvta (clt_codigo, clt_filial, clt_nombre, 
  ord_fecha, ord_total) 
  VALUES (:clt_codigo, :clt_filial, :clt_nombre, :ord_fecha, :ord_total)
  RETURNING ord_id";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":clt_codigo", $docVenta->clt_codigo, PDO::PARAM_INT);
  $oSQL->bindParam(":clt_filial", $docVenta->clt_filial, PDO::PARAM_INT);
  $oSQL->bindParam(":clt_nombre", $docVenta->clt_nombre, PDO::PARAM_STR);
  $oSQL->bindParam(":ord_fecha", $docVenta->ord_fecha, PDO::PARAM_STR);
  $oSQL->bindParam(":ord_total", $docVenta->ord_total, PDO::PARAM_STR);

  $oSQL->execute();
  $orderId = $oSQL->fetchColumn();

  # Inserta filas en tabla de detalle
  $sqlCmd = "INSERT INTO testdocvta_filas (ord_id, fila_number, 
  itm_code, itm_description, piezas, gramos, precio_unit)
  VALUES (:ord_id, :fila_number, 
  :itm_code, :itm_description, :piezas, :gramos, :precio_unit)";

  $filaNum = 0;
  foreach ($docVenta->filas as $fila) {
    $filaNum = $filaNum + 1;

    $oSQL = $conn->prepare($sqlCmd);

    $oSQL->bindParam(":ord_id", $orderId);
    $oSQL->bindParam(":fila_number", $filaNum);
    $oSQL->bindParam(":itm_code", $fila->itm_code);
    $oSQL->bindParam(":itm_description", $fila->itm_description);
    $oSQL->bindParam(":piezas", $fila->piezas);
    $oSQL->bindParam(":gramos", $fila->gramos);
    $oSQL->bindParam(":precio_unit", $fila->precio_unit);

    $oSQL->execute();
  }

  # Confirmar transacción
  $conn->commit();


  # Respuesta HTTP         <------------------------------------------
  http_response_code(201);

  $codigo = K_API_OK;
  $mensaje = "success";
  $response = [
    "Codigo"  => $codigo,
    "Mensaje" => $mensaje,
    "orderId" => $orderId
  ];

  // -------

} catch (Exception $e) {

  # Revierte operaciones en caso de error
  if ($conn->inTransaction()) {
    $conn->rollBack();
  }

  http_response_code(500);

  $response = [
    "Codigo"      => K_API_ERRSQL,
    "Mensaje"     => $conn->errorInfo(),
    "Contenido"   => []
  ];
}

$response = json_encode($response);

$conn = null;   // Cierra conexión

echo $response;

return;
