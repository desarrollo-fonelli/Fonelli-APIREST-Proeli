<?php
@session_start();
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Auth');

date_default_timezone_set('America/Mexico_City');

/**
 * Consulta Directiva Comercial.
 * --------------------------------------------------------------------------
 * Creación: dRendon 21.05.2026
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ConsDirBalance.php";

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
$FechaCorte    = null;     // Fecha final
$TipoCambioMN  = 0.00;     // Tipo de cambio moneda nacional
$TipoCambioUSD = 0.00;     // Tipo de cambio dolares
$TipoCambioORO = 0.00;     // Tipo de cambio oro
$TipoCambioPLATA = 0.00;   // Tipo de cambio plata
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

  if (!isset($_GET["FechaCorte"])) {
    throw new Exception("El parametro obligatorio 'FechaCorte' no fue definido.");
  } else {
    $FechaCorte = $_GET["FechaCorte"];
    if (!ValidaFormatoFecha($FechaCorte)) {
      throw new Exception("El parametro 'FechaCorte' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "FechaCorte",
  "TipoCambioMN",
  "TipoCambioUSD",
  "TipoCambioORO",
  "TipoCambioPLATA",
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

# Hay que inicializarverificar parametros opcionales y en caso 
# que estos no se indiquen, asignar valores por omisión.
# (dichos valores se definieron al inicio del script, al declarar las variables)

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# --------------------------------------------------------------
# Ejecuta la consulta 
# --------------------------------------------------------------
$numFilas = 0;
$totalPaginas = 0;

try {
  $data = SelectData(
    $FechaCorte,
    $TipoCambioMN,
    $TipoCambioUSD,
    $TipoCambioORO,
    $TipoCambioPLATA
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
 * @param string $FechaCorte
 * @return array
 */
function SelectData(
  $FechaCorte,
  $TipoCambioMN,
  $TipoCambioUSD,
  $TipoCambioORO,
  $TipoCambioPLATA
) {

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
      ['seccion' => 'Activos', 'seccionDescripc' => 'ACTIVO CIRCULANTE', 'rubroDescripc' => 'Inventario M.P.',  'rubroMN' => 13216288.18, 'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 35185.49, 'rubroOROconv' => 89332503.90, 'rubroPLATA' => 42408.54, 'rubroPLATAconv' => 1838410.21, 'rubroTotal' => 104387202.28],
      ['seccion' => 'Activos', 'seccionDescripc' => 'ACTIVO CIRCULANTE', 'rubroDescripc' => 'Inventario P.T.',  'rubroMN' => 2891288.09,  'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 53708.23, 'rubroOROconv' => 136359922.00, 'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 139251209.91],
      ['seccion' => 'Activos', 'seccionDescripc' => 'ACTIVO CIRCULANTE', 'rubroDescripc' => 'Inv en Transito',  'rubroMN' => 370.62,      'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 50.11,    'rubroOROconv' => 127224.37,   'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 127594.99],
      ['seccion' => 'Activos', 'seccionDescripc' => 'ACTIVO CIRCULANTE', 'rubroDescripc' => 'Ctas por Cobrar',  'rubroMN' => 104421774.94, 'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 6242.95,  'rubroOROconv' => 15850237.00, 'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 120272011.93],
      ['seccion' => 'Activos', 'seccionDescripc' => 'ACTIVO CIRCULANTE', 'rubroDescripc' => 'CXC Cart Judic',   'rubroMN' => 0.00,        'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 0.00,     'rubroOROconv' => 0.00,        'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 0.00],
      ['seccion' => 'Activos', 'seccionDescripc' => 'ACTIVO CIRCULANTE', 'rubroDescripc' => 'Bancos',           'rubroMN' => 5620328.49,  'rubroUSD' => 3524.50,   'rubroUSDconv' => 61048.92,   'rubroORO' => 0.00,     'rubroOROconv' => 0.00,        'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 5681377.41],
      ['seccion' => 'Pasivos', 'seccionDescripc' => 'PASIVO CIRCULANTE', 'rubroDescripc' => 'Proveedores',      'rubroMN' => -6206986.80, 'rubroUSD' => 563934.62, 'rubroUSDconv' => 9768080.73, 'rubroORO' => 0.00,     'rubroOROconv' => 0.00,        'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 3561093.93],
      ['seccion' => 'Pasivos', 'seccionDescripc' => 'PASIVO CIRCULANTE', 'rubroDescripc' => 'Acreedores',       'rubroMN' => 20314.61,    'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 0.00,     'rubroOROconv' => 0.00,        'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 20314.61],
      ['seccion' => 'Pasivos', 'seccionDescripc' => 'PASIVO CIRCULANTE', 'rubroDescripc' => 'Acreed Santander', 'rubroMN' => 142006217.21, 'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 0.00,     'rubroOROconv' => 0.00,        'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 142006217.21],
      ['seccion' => 'Pasivos', 'seccionDescripc' => 'PASIVO CIRCULANTE', 'rubroDescripc' => 'Acreed Bancomer',  'rubroMN' => 20000000.00, 'rubroUSD' => 0.00,      'rubroUSDconv' => 0.00,       'rubroORO' => 0.00,     'rubroOROconv' => 0.00,        'rubroPLATA' => 0.00, 'rubroPLATAconv' => 0.00, 'rubroTotal' => 20000000.00]
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

    $seccionTipo      = trim($data[0]['seccion']);
    $seccionDescripc  = trim($data[0]['seccionDescripc']);
    $seccionMN        = 0.00;
    $seccionUSD       = 0.00;
    $seccionUSDconv   = 0.00;
    $seccionORO       = 0.00;
    $seccionOROconv   = 0.00;
    $seccionPLATA     = 0.00;
    $seccionPLATAconv = 0.00;
    $seccionTotal     = 0.00;

    foreach ($data as $row) {

      //        "OrdPresent" => intval($row['ordPresent']),

      // Cambio de grupo de documentos
      if ($row['seccion'] != $seccionTipo) {

        $seccion = [
          "SeccionTipo"      => trim($seccionTipo),
          "SeccionDescripc"  => trim($seccionDescripc),
          "SeccionMN"        => round($seccionMN, 2),
          "SeccionUSD"       => round($seccionUSD, 2),
          "SeccionUSDconv"   => round($seccionUSDconv, 2),
          "SeccionORO"       => round($seccionORO, 2),
          "SeccionOROconv"   => round($seccionOROconv, 2),
          "SeccionPLATA"     => round($seccionPLATA, 2),
          "SeccionPLATAconv" => round($seccionPLATAconv, 2),
          "SeccionTotal"     => round($seccionTotal, 2),
          "BalanceRubros"    => $rubros
        ];
        array_push($secciones, $seccion);

        $seccionTipo      = trim($row['seccion']);
        $seccionDescripc  = trim($row['seccionDescripc']);
        $seccionMN        = 0.00;
        $seccionUSD       = 0.00;
        $seccionUSDconv   = 0.00;
        $seccionORO       = 0.00;
        $seccionOROconv   = 0.00;
        $seccionPLATA     = 0.00;
        $seccionPLATAconv = 0.00;
        $seccionTotal     = 0.00;
        $rubros           = array();
      }

      $seccionMN        += floatval($row['rubroMN']);
      $seccionUSD       += floatval($row['rubroUSD']);
      $seccionUSDconv   += floatval($row['rubroUSDconv']);
      $seccionORO       += floatval($row['rubroORO']);
      $seccionOROconv   += floatval($row['rubroOROconv']);
      $seccionPLATA     += floatval($row['rubroPLATA']);
      $seccionPLATAconv += floatval($row['rubroPLATAconv']);
      $seccionTotal     += floatval($row['rubroTotal']);

      $rubro = [
        "RubroDescripc"  => trim($row['rubroDescripc']),
        "RubroMN"        => round($row['rubroMN'], 2),
        "RubroUSD"       => round($row['rubroUSD'], 2),
        "RubroUSDconv"   => round($row['rubroUSDconv'], 2),
        "RubroORO"       => round($row['rubroORO'], 2),
        "RubroOROconv"   => round($row['rubroOROconv'], 2),
        "RubroPLATA"     => round($row['rubroPLATA'], 2),
        "RubroPLATAconv" => round($row['rubroPLATAconv'], 2),
        "RubroTotal"     => round($row['rubroTotal'], 2)
      ];
      array_push($rubros, $rubro);
    }

    // Ultimo registro
    $seccion = [
      "SeccionTipo"      => trim($seccionTipo),
      "SeccionDescripc"  => trim($seccionDescripc),
      "SeccionMN"        => round($seccionMN, 2),
      "SeccionUSD"       => round($seccionUSD, 2),
      "SeccionUSDconv"   => round($seccionUSDconv, 2),
      "SeccionORO"       => round($seccionORO, 2),
      "SeccionOROconv"   => round($seccionOROconv, 2),
      "SeccionPLATA"     => round($seccionPLATA, 2),
      "SeccionPLATAconv" => round($seccionPLATAconv, 2),
      "SeccionTotal"     => round($seccionTotal, 2),
      "BalanceRubros"    => $rubros
    ];
    array_push($secciones, $seccion);

    $contenido = ["BalanceSecciones" => $secciones];
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
