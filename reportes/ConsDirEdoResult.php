<?php
@session_start();
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Auth');

date_default_timezone_set('America/Mexico_City');

/**
 * Consulta Directiva Estado de Resultados.
 * --------------------------------------------------------------------------
 * Creación: dRendon 27.05.2026
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ConsDirEdoResult.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con datos que se devuelven de las consultas
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario   = null;     // Tipo de usuario
$Usuario       = null;     // Id del usuario (cliente, agente o gerente)
$Token         = null;     // Token obtenido por el usuario al autenticarse
$FechaDesde    = null;     // Fecha inicial
$FechaHasta    = null;     // Fecha final
$Pagina       = 1;         // Pagina devuelta del conjunto de datos obtenido

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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME del mensaje
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (! in_array($TipoUsuario, ["A", "G"])) {
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
  // ValidaToken está en ./include/funciones.php
  if (!ValidaToken($conn, $TipoUsuario, $Usuario, $Token)) {
    throw new Exception("Error de autenticacion.");
  }
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["FechaDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaDesde' no fue definido.");
  } else {
    $FechaDesde = $_GET["FechaDesde"];
    if (!ValidaFormatoFecha($FechaDesde)) {
      throw new Exception("El parametro 'FechaDesde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }
  if (!isset($_GET["FechaHasta"])) {
    throw new Exception("El parametro obligatorio 'FechaHasta' no fue definido.");
  } else {
    $FechaHasta = $_GET["FechaHasta"];
    if (!ValidaFormatoFecha($FechaHasta)) {
      throw new Exception("El parametro 'FechaHasta ' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "FechaDesde", "FechaHasta", "Pagina");

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

# Hay que inicializarverificar parametros opcionales y en caso 
# que estos no se indiquen, asignar valores por omisión.
# (dichos valores se definieron al inicio del script, al declarar las variables)

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
# --------------------------------------------------------------
$numFilas = 0;
$totalPaginas = 0;

try {
  $data = SelectData($FechaDesde, $FechaHasta);

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
    "Mensaje"     => $e->getMessage(),
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => []
  ];
}

$response = json_encode($response);

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param string $FechaDesde
 * @param string $FechaHasta
 * @return array
 */
function SelectData($FechaDesde, $FechaHasta)
{

  $arrData  = array();  // Array que se va a devolver
  $where    = "";       // Variable para almacenar dinamicamente la clausula WHERE del SELECT

  // Doy un plazo de hasta Cinco minutos para completar cada consulta...
  set_time_limit(300);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";
  $conn = DB::getConn();

  try {

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # Borra tablas temporales
    BorraTemporales($conn);


    # Maquetamos respuesta con datos de prueba <---------------------------------

    $arrData = [
      ['ordPresent' => 1, 'seccion' => 'Vtas', 'seccDescripc' => 'Ventas', 'signoContable' => 1, 'rubro' => 'Ventas', 'interno' => 59189770.65, 'externo' => 163510910.10, 'totalFila' => 222700680.75, 'porc_vta' => 100.00],

      ['ordPresent' => 2, 'seccion' => 'CostDirec', 'seccDescripc' => 'Costos directos', 'signoContable' => -1, 'rubro' => 'Costo de ventas', 'interno' => 41732172.43, 'externo' => 124760370.08, 'totalFila' => 166492542.51, 'porc_vta' => 74.76],
      ['ordPresent' => 2, 'seccion' => 'CostDirec', 'seccDescripc' => 'Costos directos', 'signoContable' => -1, 'rubro' => 'Merma', 'interno' => 5132620.93, 'externo' => 0.00, 'totalFila' => 5132620.93, 'porc_vta' =>  2.30],
      ['ordPresent' => 2, 'seccion' => 'CostDirec', 'seccDescripc' => 'Costos directos', 'signoContable' => -1, 'rubro' => 'Recuperación', 'interno' => -2048065.27, 'externo' => 0.00, 'totalFila' => -2048065.27, 'porc_vta' => -0.92],

      ['ordPresent' => 3, 'seccion' => 'GastDirec', 'seccDescripc' => 'Gastos directos', 'signoContable' => -1, 'rubro' => 'Gastos de fabricación', 'interno' => 0.00, 'externo' => 8540814.36, 'totalFila' => 8540814.36, 'porc_vta' => 3.84],

      ['ordPresent' => 4, 'seccion' => 'GastIndir', 'seccDescripc' => 'Gastos indirectos', 'signoContable' => -1, 'rubro' => 'Gastos operativos', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 39859428.48, 'porc_vta' => 0.00],
      ['ordPresent' => 4, 'seccion' => 'GastIndir', 'seccDescripc' => 'Gastos indirectos', 'signoContable' => -1, 'rubro' => 'Gastos prueba', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 89687.97, 'porc_vta' => 0.00],
      ['ordPresent' => 4, 'seccion' => 'GastIndir', 'seccDescripc' => 'Gastos indirectos', 'signoContable' => -1, 'rubro' => 'Gastos operativos', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 3638422.04, 'porc_vta' => 0.00],

      ['ordPresent' => 5, 'seccion' => 'Utild', 'seccDescripc' => 'Utilidad', 'signoContable' => 1, 'rubro' => 'Utilidad Neta', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 995229.73, 'porc_vta' => 0.45]
    ];



    # ----
  } catch (Exception $e) {

    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  BorraTemporales($conn);
  $conn = null;   // Cierra la conexión 

  # Falta tener en cuenta la paginacion
  return $arrData;
}


/** ----------------------------------------------------------------------------
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
function CreaDataCompuesta($data)
{
  $contenido = array();
  $secciones = array();
  $seccion   = array();
  $rubros    = array();
  $rubro     = array();

  if (count($data) > 0) {

    $seccion       = trim($data[0]["seccion"]);
    $seccDescripc  = trim($data[0]["seccDescripc"]);
    $signoContable = intval($data[0]["signoContable"]);     // 1=positivo, -1=negativo
    $seccInterno   = 0.00;
    $seccExterno   = 0.00;
    $seccTotal     = 0.00;
    $seccPorcVta   = 0.00;

    foreach ($data as $row) {

      // Cambio de Sección
      if ($row['seccion'] != $seccion) {

        $seccion = [
          "Seccion"       => trim($seccion),
          "SeccDescripc"  => trim($seccDescripc),
          "SignoContable" => intval($signoContable),
          "SeccInterno"   => round($seccInterno, 2),
          "SeccExterno"   => round($seccExterno, 2),
          "SeccTotal"     => round($seccTotal, 2),
          "SeccPorcVta"   => round($seccPorcVta, 2),
          "SeccRubros"    => $rubros
        ];
        array_push($secciones, $seccion);

        // Reinicio variables para nueva sección
        $seccion   = trim($row["seccion"]);
        $seccDescripc = trim($row["seccDescripc"]);
        $signoContable = intval($row["signoContable"]);
        $seccInterno   = 0.00;
        $seccExterno   = 0.00;
        $seccTotal     = 0.00;
        $seccPorcVta   = 0.00;
        $rubros    = array();
      }

      $seccInterno   += $row["interno"];
      $seccExterno   += $row["externo"];
      $seccTotal     += $row["totalFila"];
      $seccPorcVta   += $row["porc_vta"];

      $rubro = [
        "Rubro"        => trim($row["rubro"]),
        "RubroInterno" => round($row["interno"], 2),
        "RubroExterno" => round($row["externo"], 2),
        "RubroTotal"   => round($row["totalFila"], 2),
        "RubroPorcVta" => round($row["porc_vta"], 2)
      ];
      array_push($rubros, $rubro);
    }

    // Ultimo registro
    $seccion = [
      "Seccion"       => trim($seccion),
      "SeccDescripc"  => trim($seccDescripc),
      "SignoContable" => intval($signoContable),
      "SeccInterno"   => round($seccInterno, 2),
      "SeccExterno"   => round($seccExterno, 2),
      "SeccTotal"     => round($seccTotal, 2),
      "SeccPorcVta"   => round($seccPorcVta, 2),
      "SeccRubros"    => $rubros
    ];
    array_push($secciones, $seccion);

    $contenido = ["EdoResultSecciones" => $secciones];
  } // if (count($data) > 0)

  return $contenido;
}



/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn)
{

  // $sqlcmd = "DROP TABLE IF EXISTS indic_ventas";
  // $drop = $conn->prepare($sqlcmd);
  // $drop->execute();

  // $sqlcmd = "DROP TABLE IF EXISTS indic_pedidos";
  // $drop = $conn->prepare($sqlcmd);
  // $drop->execute();

}
