<?php
@session_start();
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Auth');

date_default_timezone_set('America/Mexico_City');

/**
 * Consulta Directiva Flujo de Efectivo.
 * --------------------------------------------------------------------------
 * Creación: dRendon 28.05.2026
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ConsDirFlujo.php";

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
      ['ordPresent' => 1, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Ingresos', 'signoContable' => 1, 'rubro' => '11', 'rubroDescripc' => 'Pagos MN', 'oro' => 0.00, 'importeMN' => 232512.45],
      ['ordPresent' => 1, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Ingresos', 'signoContable' => 1, 'rubro' => '15', 'rubroDescripc' => 'Pagos Interbancarios MN', 'oro' => 0.00, 'importeMN' => 45504001.11],
      ['ordPresent' => 1, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Ingresos', 'signoContable' => 1, 'rubro' => '68', 'rubroDescripc' => 'Pagos Factura Reposición', 'oro' => 0.00, 'importeMN' => 658165.93],
      ['ordPresent' => 1, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Ingresos', 'signoContable' => 1, 'rubro' => '98', 'rubroDescripc' => 'Pagos Cheque', 'oro' => 0.00, 'importeMN' => 408053.76],
      ['ordPresent' => 1, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Ingresos', 'signoContable' => 1, 'rubro' => '08', 'rubroDescripc' => 'Entradas por Traspaso', 'oro' => 0.00, 'importeMN' => 2536461.45],
      ['ordPresent' => 2, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Egresos', 'signoContable' => -1, 'rubro' => '11', 'rubroDescripc' => 'Cheques', 'oro' => 0.00, 'importeMN' => 276016.55],
      ['ordPresent' => 2, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Egresos', 'signoContable' => -1, 'rubro' => '15', 'rubroDescripc' => 'Transferencias', 'oro' => 0.00, 'importeMN' => 12064551.02],
      ['ordPresent' => 2, 'tipoFlujo' => 'Flujo Operativo', 'seccion' => 'Egresos', 'signoContable' => -1, 'rubro' => '14', 'rubroDescripc' => 'Pagos DLLS', 'oro' => 0.00, 'importeMN' => 36750977.28],
      ['ordPresent' => 3, 'tipoFlujo' => 'Flujo Financiero', 'seccion' => 'Financiero', 'signoContable' => 1, 'rubro' => '25', 'rubroDescripc' => 'Disposición de crédito', 'oro' => 0.00, 'importeMN' => 26450000.00],
      ['ordPresent' => 3, 'tipoFlujo' => 'Flujo Financiero', 'seccion' => 'Financiero', 'signoContable' => -1, 'rubro' => '26', 'rubroDescripc' => 'Pagos al crédito', 'oro' => 0.00, 'importeMN' => 25600000.00],
      ['ordPresent' => 4, 'tipoFlujo' => 'Cobranza Oro', 'seccion' => 'Cobranza Oro', 'signoContable' => 1, 'rubro' => '51', 'rubroDescripc' => 'Pagos Oro', 'oro' => 3152.25, 'importeMN' => 8031932.01]
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
  $contenido     = array();
  $arrTiposFlujo = array();
  $arrTipoFlujo  = array();
  $arrSecciones  = array();
  $arrSeccion    = array();
  $arrRubros     = array();
  $arrRubro      = array();

  if (count($data) > 0) {

    $tipoFlujo          = trim($data[0]["tipoFlujo"]);
    $tipoFlujoSignoContable = intval($data[0]["signoContable"]);
    $tipoFlujoOro       = 0.00;
    $tipoFlujoImporteMN = 0.00;

    $seccion       = trim($data[0]["seccion"]);
    $seccSignoContable = intval($data[0]["signoContable"]);     // 1=positivo, -1=negativo
    $seccOro       = 0.00;
    $seccImporteMN = 0.00;

    foreach ($data as $row) {

      // Cambio de Sección
      if ($row['seccion'] != $seccion) {

        $arrSeccion = [
          "Seccion"       => trim($seccion),
          "SignoContable" => intval($seccSignoContable),
          "SeccOro"       => round($seccOro, 2),
          "SeccImporteMN" => round($seccImporteMN, 2),
          "SeccRubros"    => $arrRubros
        ];
        array_push($arrSecciones, $arrSeccion);

        // Reinicio variables para nueva sección
        $seccion       = trim($row["seccion"]);
        $seccSignoContable = intval($row["signoContable"]);
        $seccOro       = 0.00;
        $seccImporteMN = 0.00;
        $arrRubros     = array();
      }

      // Cambio de Tipo de Flujo
      if ($row['tipoFlujo'] != $tipoFlujo) {

        $arrTipoFlujo = [
          "TipoFlujo"    => trim($tipoFlujo),
          "SignoContable"      => intval($tipoFlujoSignoContable),
          "TipoFlujoOro"       => round($tipoFlujoOro, 2),
          "TipoFlujoImporteMN" => round($tipoFlujoImporteMN, 2),
          "FlujoSecciones"     => $arrSecciones
        ];
        array_push($arrTiposFlujo, $arrTipoFlujo);

        // Reinicio variables para nuevo tipo de flujo
        $tipoFlujo    = trim($row["tipoFlujo"]);
        $tipoFlujoSignoContable = intval($row["signoContable"]);
        $tipoFlujoOro       = 0.00;
        $tipoFlujoImporteMN = 0.00;
        $arrSecciones = array();
      }


      $seccOro       += ($row["signoContable"] * $row["oro"]);
      $seccImporteMN += ($row["signoContable"] * $row["importeMN"]);
      $tipoFlujoOro       += ($row["signoContable"] * $row["oro"]);
      $tipoFlujoImporteMN += ($row["signoContable"] * $row["importeMN"]);

      $arrRubro = [
        "Rubro"          => trim($row["rubro"]),
        "RubroDescripc"  => trim($row["rubroDescripc"]),
        "SignoContable"  => intval($row["signoContable"]),
        "RubroOro"       => round($row["signoContable"] * $row["oro"], 2),
        "RubroImporteMN" => round($row["signoContable"] * $row["importeMN"], 2)
      ];
      array_push($arrRubros, $arrRubro);
    }

    // Agrega la última sección
    $arrSeccion = [
      "Seccion"       => trim($seccion),
      "SignoContable" => intval($seccSignoContable),
      "SeccOro"       => round($seccOro, 2),
      "SeccImporteMN" => round($seccImporteMN, 2),
      "SeccRubros"    => $arrRubros
    ];
    array_push($arrSecciones, $arrSeccion);

    $arrTipoFlujo = [
      "TipoFlujo"          => trim($tipoFlujo),
      "SignoContable"      => intval($tipoFlujoSignoContable),
      "TipoFlujoOro"       => round($tipoFlujoOro, 2),
      "TipoFlujoImporteMN" => round($tipoFlujoImporteMN, 2),
      "FlujoSecciones"     => $arrSecciones
    ];
    array_push($arrTiposFlujo, $arrTipoFlujo);

    //$contenido = ["FlujoSecciones" => $arrSecciones];
    $contenido = ["FlujosContenido" => $arrTiposFlujo];
  }  // if (count($data) > 0)

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
