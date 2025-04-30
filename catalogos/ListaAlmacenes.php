<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista Almacenes de PT 
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ListaAlmacenes.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$Usuario        = null;   // Id del usuario (cliente, agente, gerente)
$AlmacenTipo    = null;   // Tipo de almacén
$AlmacenNum     = null;   // Número de almacén
$AlmacenStatus  = null;   // Status del almacén
$Pagina         =  1;     // Pagina devuelta del conjunto de datos obtenido

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

// --> No hay parámetros obligatorios en este servicio

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "Usuario",
  "AlmacenTipo",
  "AlmacenNum",
  "AlmacenStatus",
  "Pagina"
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

# Hay que inicializarverificar parametros opcionales y en caso 
# que estos no se indiquen, asignar valores por omisión.
# (dichos valores se definieron al inicio del script, al declarar las variables)
if (isset($_GET["AlmacenTipo"])) {
  $AlmacenTipo = $_GET["AlmacenTipo"];
}
if (isset($_GET["AlmacenNum"])) {
  $AlmacenNum = $_GET["AlmacenNum"];
}
if (isset($_GET["AlmacenStatus"])) {
  $AlmacenStatus = $_GET["AlmacenStatus"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectData($AlmacenTipo, $AlmacenNum, $AlmacenStatus);

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

$conn = null;   // Cierra conexión

$response = json_encode($response);

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param string $AlmacenTipo
 * @param string $AlmacenNum
 * @param string $AlmacenStatus
 * @return array
 */
function SelectData($AlmacenTipo, $AlmacenNum, $AlmacenStatus)
{
  # Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $where = "";

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  if (isset($AlmacenNum)) {
    $strAlmacenNum = str_pad($AlmacenNum, 6, " ", STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Construyo dinamicamente la condicion WHERE
  if (isset($AlmacenTipo)) {
    $where = "WHERE alm.t_tipo = :AlmacenTipo ";

    if (isset($AlmacenNum)) {
      $where .= "AND alm.t_num = :strAlmacenNum ";
    }
  }
  if (isset($AlmacenStatus)) {
    if ($where == "") {
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= "alm.t_status = :AlmacenStatus ";
  }

  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

  try {

    # Se conecta a la base de datos
    // require_once "../db/conexion.php";  <-- el script se leyó previamente
    $conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // Instrucción SELECT - SQL
    $sqlCmd = "SELECT alm.t_tipo, alm.t_num, alm.t_nom, alm.t_status, alm.t_of 
      FROM inv018 alm
      $where 
      ORDER BY t_tipo, cast(t_num as int)";
    //var_dump($sqlCmd);

    // Prepara la consulta SQL
    $oSQL = $conn->prepare($sqlCmd);
    if (isset($AlmacenTipo)) {
      $oSQL->bindParam(":AlmacenTipo", $AlmacenTipo, PDO::PARAM_STR);
    }
    if (isset($AlmacenNum)) {
      $oSQL->bindParam(":strAlmacenNum", $strAlmacenNum, PDO::PARAM_STR);
    }
    if (isset($AlmacenStatus)) {
      $oSQL->bindParam(":AlmacenStatus", $AlmacenStatus, PDO::PARAM_STR);
    }
    //$oSQL-> bindParam(":provocaerror", "",PDO::PARAM_STR);  usado para pruebas de control de errores

    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    // fin del proceso normal

  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  $conn = null;   // Cierra conexión

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

  if (count($data) > 0) {
    foreach ($data as $row) {
      // Se crea un array con los nodos requeridos
      // Bueno, en este caso, no hay nodos, solo se van agregando filas
      $contenido[] = array(
        "AlmTipo"   => $row["t_tipo"],
        "AlmNum"    => $row["t_num"],
        "AlmNom"    => $row["t_nom"],
        "AlmStatus" => $row["t_status"],
        "AlmOfic"   => $row["t_of"]
      );
    } // foreach($data as $row)
  }

  return $contenido;
}
