<?php

@session_start();

/**
 * Estos headers solo son efectivos si se indican en el backend,
 * no tiene caso indicarlos en los servicios de angular.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Auth');
header('Content-type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Auth');
  http_response_code(200);
  exit;
}

date_default_timezone_set('America/Mexico_City');

/**
 * Crea documentos de venta
 * --------------------------------------------------------------------------
 * INSERT de registros en tablas de documentos de venta
 * Creación: dRendon 25.08.2025
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "CotizCrear.php";

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
$AgenteCodigo   = null;     // Codigo del agente de venta
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
if (!isset($_GET["Usuario"])) {
  throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
} else {
  $Usuario = $_GET["Usuario"];
}

try {
  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (!in_array($TipoUsuario, ["C", "A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
    }

    if ($TipoUsuario == "A") {
      if (!isset($_GET["AgenteCodigo"])) {
        throw new Exception("Debe indicar un valor para 'AgenteCodigo' cuando 'TipoUsuario' es 'A'");
      }
      if (trim($_GET["AgenteCodigo"]) != $Usuario) {
        throw new Exception("Error de autenticacion Agente");
      }
    }
    if (isset($_GET["AgenteCodigo"])) {
      $AgenteCodigo = $_GET["AgenteCodigo"];
    }

    if ($TipoUsuario == 'C') {
      if (!isset($_GET["ClienteCodigo"])) {
        throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
        // 
      } else {
        $ClienteCodigo = $_GET["ClienteCodigo"];
      }

      if (!isset($_GET["ClienteFilial"])) {
        throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
      } else {
        $ClienteFilial = $_GET["ClienteFilial"];
      }
      # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
      # Verificando en este nivel ya no es necesario cambiar el código restante
      if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
        throw new Exception("Error de autenticación usuario - cliente" + $Usuario);
      }
    } else {
      if (isset($_GET["ClienteCodigo"])) {
        $ClienteCodigo = $_GET["ClienteCodigo"];
        if (isset($_GET["ClienteFilial"])) {
          $ClienteFilial = $_GET["ClienteFilial"];
        } else {
          $ClienteFilial = '0';
        }
      }
    }
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
    throw new Exception("Error de autenticacion - token");
  }

  // -------

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Codigo" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "ClienteCodigo",
  "ClienteFilial",
  "AgenteCodigo"
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
  echo json_encode(["Codigo" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
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
  echo json_encode(["Codigo" => K_API_ERRPARAM, "Mensaje" => "JSON inválido o datos vacíos"]);
  exit;
}


/**
 * Formatea variables recibidas en caso necesario.
 * Se debe verificar que el cliente indicado en el documento recibido
 * coincida con el del cliente que hace la petición
 */
//var_dump($docVenta);  // Para depurar, ver estructura del JSON recibido

if ($TipoUsuario == 'C') {
  if (
    trim($docVenta->ClienteCodigo) != trim($ClienteCodigo) or
    trim($docVenta->ClienteFilial) != trim($ClienteFilial)
  ) {

    $mensaje = "Cliente conectado es distinto al del documento.";
    http_response_code(400);
    echo json_encode(["Codigo" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

$strClienteCodigo = str_pad($docVenta->ClienteCodigo, 6, " ", STR_PAD_LEFT);
$strClienteFilial = str_pad($docVenta->ClienteFilial, 3, " ", STR_PAD_LEFT);
$strAgenteCodigo  = str_pad($AgenteCodigo, 2, " ", STR_PAD_LEFT);

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
  # No se incluyen ord_id ni ord_folio porque se asignan en la base de datos
  $sqlCmd = "INSERT INTO cotiz_doc (fecha_doc, status_doc, 
  cliente_codigo, cliente_filial, cliente_nombre, cliente_sucursal,
  lprec_codigo, parid_tipo, comentarios)
  VALUES (:fecha_doc, :status_doc, :cliente_codigo, :cliente_filial, 
  :cliente_nombre, :cliente_sucursal, :lprec_codigo, :parid_tipo, :comentarios)
  RETURNING id";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":fecha_doc", $docVenta->FechaDoc);
  $oSQL->bindParam(":status_doc", $docVenta->StatusDoc);
  $oSQL->bindParam(":cliente_codigo", $strClienteCodigo);
  $oSQL->bindParam(":cliente_filial", $strClienteFilial);
  $oSQL->bindParam(":cliente_nombre", $docVenta->ClienteNombre);
  $oSQL->bindParam(":cliente_sucursal", $docVenta->ClienteSucursal);
  $oSQL->bindParam(":lprec_codigo", $docVenta->ListaPreciosCodigo);
  $oSQL->bindParam(":parid_tipo", $docVenta->ParidadTipo);
  $oSQL->bindParam(":comentarios", $docVenta->Comentarios);

  $oSQL->execute();

  $doc_id = $oSQL->fetchColumn();

  # Inserta filas en tabla de detalle
  $sqlCmd = "INSERT INTO cotiz_fila (doc_id, fila, lineapt, itemcode, 
  descripc, precio, costo, piezas, gramos,
  tipo_costeo, importe, kilataje, int_ext, lprec_dircomp)
  VALUES (:doc_id, :fila, 
  :lineapt, :itemcode, :descripc, :precio, :costo, :piezas, :gramos, 
  :tipo_costeo, :importe, :kilataje, :int_ext, :lprec_dircomp)";

  $filaNum = 0;
  foreach ($docVenta->CotizacFilas as $fila) {
    $filaNum = $filaNum + 1;

    $oSQL = $conn->prepare($sqlCmd);

    // OJO: Si no se hace este casteo se producen errores en postgresql
    $_precio = floatval($fila->Precio);
    $_costo = floatval($fila->Costo);
    $_piezas = intval($fila->Piezas);
    $_gramos = floatval($fila->Gramos);
    $_importe = floatval($fila->Importe);

    $oSQL->bindParam(":doc_id", $doc_id);
    $oSQL->bindParam(":fila", $filaNum);
    $oSQL->bindParam(":lineapt", $fila->LineaPT);
    $oSQL->bindParam(":itemcode", $fila->ItemCode);
    $oSQL->bindParam(":descripc", $fila->Descripc);
    $oSQL->bindParam(":precio", $_precio);
    $oSQL->bindParam(":costo", $_costo);
    $oSQL->bindParam(":piezas", $_piezas);
    $oSQL->bindParam(":gramos", $_gramos);
    $oSQL->bindParam(":tipo_costeo", $fila->TipoCosteo);
    $oSQL->bindParam(":importe", $_importe);
    $oSQL->bindParam(":kilataje", $fila->Kilataje);
    $oSQL->bindParam(":int_ext", $fila->IntExt);
    $oSQL->bindParam(":lprec_dircomp", $fila->LPrecDirComp);

    $oSQL->execute();
  }

  # Confirmar transacción
  $conn->commit();

  # Se hace una consulta SQL adicional para devolver también el "folio" 
  # asignado a la Cotización, pues es más significativo para el usuario
  $sqlCmd = "SELECT folio FROM cotiz_doc WHERE id = :doc_id
  LIMIT 1";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":doc_id", $doc_id, PDO::PARAM_INT);
  $oSQL->execute();
  $folio = $oSQL->fetchColumn();
  if ($folio === false) {
    throw new Exception("No se pudo recuperar el folio de la cotización.");
  }

  # Respuesta HTTP ------
  http_response_code(201);

  $codigo = K_API_OK;
  $mensaje = "success";
  $response = [
    "Codigo"  => $codigo,
    "Mensaje" => $mensaje,
    "Contenido" => ["folio" => $folio, "doc_id" => $doc_id]
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
    "Mensaje"     => $e->getMessage(),
    "Contenido"   => []
  ];
}

$response = json_encode($response);

$conn = null;   // Cierra conexión

echo $response;

return;
