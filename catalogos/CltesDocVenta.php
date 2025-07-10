<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/*
Deben recibirse los siguientes headers:
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token');
*/

/**
 * CltesDocVenta.php
 * Devuelve los datos para Documentos de Venta, del Cliente solicitado,
 * los cuales son necesarios para el cálculo de precios y para
 * establecer las condiciones de venta.
 * --------------------------------------------------------------------------
 * dRendon 24.06.2025
 *  El parámetro "Usuario" ahora es obligatorio
 *  Ahora se recibe el "Token" con caracter obligatorio en los headers de la peticion
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "CltesDocVenta.php";

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
$AgenteCodigo   = null;     // Codigo del agente de ventas asociado al cliente
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "GET") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos GET";   // quité K_SCRIPTNAME del mensaje
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje]);
  exit;
}

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {
  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (! in_array($TipoUsuario, ["C", "A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
    }
  }

  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro 'Usuario' no fue definido.");
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
  // ValidaToken está en ./include/funciones.php
  if (!ValidaToken($conn, $TipoUsuario, $Usuario, $Token)) {
    throw new Exception("Error de autenticacion.");
  }
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["ClienteCodigo"])) {
    throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
  } else {
    $ClienteCodigo = $_GET["ClienteCodigo"];
  }

  if (!isset($_GET["ClienteFilial"])) {
    throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
  } else {
    $ClienteFilial = $_GET["ClienteFilial"];
  }

  # dRendon 04.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "C") {
    if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }
  # Fin dRendon 04.05.2023 ****************

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "ClienteCodigo",
  "ClienteFilial",
  "AgenteCodigo",
  "Pagina"
);

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_GET);
foreach ($arrParam as $param) {
  if (! in_array($param, $arrPermitidos)) {
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

if ($TipoUsuario == "A") {
  if (!isset($_GET["AgenteCodigo"]) or $AgenteCodigo != $Usuario) {
    $mensaje = "Error de autenticación";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  } else {
    $AgenteCodigo = $_GET["AgenteCodigo"];
  }
}

if ($TipoUsuario == "C") {
  if (
    !isset($ClienteCodigo) or
    (TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial) != $Usuario)
  ) {
    $mensaje = "Error de autenticación";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

/*
if (isset($_GET["Password"])) {
  if (isset($ClienteCodigo) && isset($ClienteFilial)) {
    $Password =  $_GET["Password"];
  } else {
    $mensaje = "Si incluye un 'Password', debe indicar 'ClienteCodigo' y 'ClienteFilial'.";   // quité K_SCRIPTNAME del mensaje
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}
*/

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectClientes(
    $TipoUsuario,
    $Usuario,
    $ClienteCodigo,
    $ClienteFilial,
    $AgenteCodigo
  );

  # Asigna código de respuesta HTTP 
  http_response_code(200);

  # Compone el objeto JSON que devuelve el endpoint
  $numFilas = count($data);
  $totalPaginas = ceil($numFilas / K_FILASPORPAGINA);

  if ($numFilas > 0) {
    $codigo = K_API_OK;
    $mensaje = "success";
  } else {
    $codigo = K_API_NODATA;
    $mensaje = "data not found";
  }

  $dataCompuesta = CreaDataCompuesta($data);

  $response = [
    "Codigo"      => $codigo,
    "Mensaje"     => $mensaje,
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => $dataCompuesta
  ];
} catch (Exception $e) {
  $response = [
    "Codigo"      => K_API_ERRSQL,
    "Mensaje"     => $conn->get_last_error(),
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => []
  ];
}

$response = json_encode($response);

$conn = null;   // Cierra conexión

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $AgenteCodigo
 * @return array
 */
function SelectClientes(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $AgenteCodigo
) {
  $where = "";

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  $strUsuario = str_pad($Usuario, 2, " ", STR_PAD_LEFT);
  $strClienteCodigo = str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT);
  $strClienteFilial = str_pad($ClienteFilial, 3, " ", STR_PAD_LEFT);
  if (isset($AgenteCodigo)) {
    $strAgenteCodigo = str_pad($AgenteCodigo, 2, " ", STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  // require_once "../db/conexion.php";   <-- el script se leyó previamente
  $conn = DB::getConn();

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.cc_num = :strClienteCodigo AND a.cc_fil = :strClienteFilial ";

  if (isset($AgenteCodigo)) {
    if ($where == "") {
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= "a.cc_age = :strAgenteCodigo ";
  }

  $sqlCmd = "SELECT trim(a.cc_num) cc_num,trim(a.cc_fil) cc_fil,trim(a.cc_raso) cc_raso,
    trim(a.cc_suc) cc_suc,trim(a.cc_status)cc_status,trim(a.cc_age) cc_age,
    trim(a.cc_rfc) cc_rfc,trim(a.cc_tipoli) cc_tipoli,trim(a.cc_tipoli2) cc_tipoli2,
    a.cc_tparid,
    CASE 
    WHEN a.cc_tparid = 'N' THEN 'NORMAL' 
    WHEN a.cc_tparid = 'E' THEN 'ESPECIAL' 
    WHEN a.cc_tparid = 'P' THEN 'PREMIUM'
    ELSE ''
    END desc_paridad,
    a.cc_limcre,cc_plazo,trim(cc_email1) cc_email1,trim(cc_email2) cc_email2,
    trim(d.gc_nom) gc_nom,trim(g.t_descr) desc_tipoli,trim(h.t_descr) desc_tipoli2
    FROM cli010 a 
    LEFT JOIN var030 d ON d.GC_LLAVE = a.CC_AGE
    LEFT JOIN var020 g ON (g.T_TICA = '10' AND g.T_GPO = '93' AND g.T_CLAVE = a.CC_TIPOLI) 
    LEFT JOIN var020 h ON (h.T_TICA = '10' AND h.T_GPO = '93' AND h.T_CLAVE = a.CC_TIPOLI2) 
    $where ";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn->prepare($sqlCmd);

    $oSQL->bindParam(":strClienteCodigo", $strClienteCodigo, PDO::PARAM_STR);
    $oSQL->bindParam(":strClienteFilial", $strClienteFilial, PDO::PARAM_STR);
    if (isset($AgenteCodigo)) {
      $oSQL->bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }
    //$oSQL-> bindParam(":provocaerror", "",PDO::PARAM_STR);  usado para pruebas de control de errores

    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    //
  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  # Falta tener en cuenta la paginacion

  return $arrData;
}

/**
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
function CreaDataCompuesta($data)
{

  $contenido = array();
  $oFila = array();

  foreach ($data as $row) {

    // Se crea un array con los nodos requeridos
    $oFila = [
      "ClienteCodigo" => intval($row["cc_num"]),
      "ClienteFilial" => intval($row["cc_fil"]),
      "ClteRazonSocial" => $row["cc_raso"],
      "ClteSucursal" => $row["cc_suc"],
      "ClteStatus" => $row["cc_status"],
      "ClteRfc" => $row["cc_rfc"],
      "AgenteCodigo" => $row["cc_age"],
      "AgenteNombre" => $row["gc_nom"],
      "ListaPreciosCodigo" => $row["cc_tipoli"],
      "ListaPreciosDescripc" => $row["desc_tipoli"],
      "ListaPrecios2Codigo" => $row["cc_tipoli2"],
      "ListaPrecios2Descripc" => $row["desc_tipoli2"],
      "ParidadCodigo" => $row["cc_tparid"],
      "ParidadDescripc" => $row["desc_paridad"],
      "ClteLimiteCredito" => floatval($row["cc_limcre"]),
      "CltePlazo" => intval($row["cc_plazo"])
    ];

    // // Se agrega el array a la seccion "contenido"
    // array_push($contenido, $oFila);

  }   // foreach($data as $row)

  $contenido = $oFila;    // no es necesario un array, retorna un solo registro

  return $contenido;
}
