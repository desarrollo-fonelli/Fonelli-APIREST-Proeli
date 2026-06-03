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
const K_SCRIPTNAME  = "ConsDirComercial.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con datos que se devuelven de las consultas
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario  = null;     // Tipo de usuario
$Usuario      = null;     // Id del usuario (cliente, agente o gerente)
$Token        = null;     // Token obtenido por el usuario al autenticarse
$FechaDesde   = null;     // Fecha inicial
$FechaHasta   = null;     // Fecha final
$Pagina       = 1;        // Pagina devuelta del conjunto de datos obtenido

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
      throw new Exception("El parametro 'FechaHasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
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
$pedidos = [];  // Importe y valor agregado de pedidos de venta

$numFilas = 0;
$totalPaginas = 0;

try {
  # Obtiene documentos de verta
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

  # Obtiene importe y valor agregado de pedidos de venta activos
  $pedidos = SelectPedidos($FechaDesde, $FechaHasta);

  # Función que estructura el JSON de respuesta con los nodos requeridos, 
  # a partir de los datos obtenidos en las consultas
  $dataCompuesta = CreaDataCompuesta($data, $pedidos);

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
 * los resultados obtenidos para los DOCUMENTOS DE VENTA.
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
    # Faltan 53 NOTA/BONI MAQ M.N.

    $arrData = [
      ['ordPresent' => 1, 'grupoCodigo' => 'Fact', 'grupoDescripc' => 'Facturacion', 'docCodigo' => '01', 'docDescripc' => 'FACTURA M.N.', 'importe' => 28754479.62, 'valor_agregado' => 9011312.50, 'porc_fact' => 100.00],
      ['ordPresent' => 1, 'grupoCodigo' => 'Fact', 'grupoDescripc' => 'Facturacion', 'docCodigo' => '41', 'docDescripc' => 'FACTURA MAQ M.N.', 'importe' => 8276259.38, 'valor_agregado' => 1256858.90, 'porc_fact' => 28.78],
      ['ordPresent' => 2, 'grupoCodigo' => 'Canc', 'grupoDescripc' => 'Cancelaciones', 'docCodigo' => '14', 'docDescripc' => 'CANCELAC FAC M.N.', 'importe' => -1476345.72, 'valor_agregado' => -470673.46, 'porc_fact' => -5.13],
      ['ordPresent' => 3, 'grupoCodigo' => 'NCre', 'grupoDescripc' => 'Notas Credito', 'docCodigo' => '10', 'docDescripc' => 'CANCELAC NOTA/CR', 'importe' => 266587.00, 'valor_agregado' => 65088.49, 'porc_fact' => 0.93],
      ['ordPresent' => 3, 'grupoCodigo' => 'NCre', 'grupoDescripc' => 'Notas Credito', 'docCodigo' => '12', 'docDescripc' => 'NOTA/CRED M.N.', 'importe' => -3578274.55, 'valor_agregado' => -1046964.12, 'porc_fact' => -12.44],
      ['ordPresent' => 3, 'grupoCodigo' => 'NCre', 'grupoDescripc' => 'Notas Credito', 'docCodigo' => '52', 'docDescripc' => 'NOTA/CRED MAQ M.N.', 'importe' => -177419.38, 'valor_agregado' => -25777.056, 'porc_fact' => -0.62],
      ['ordPresent' => 4, 'grupoCodigo' => 'Boni', 'grupoDescripc' => 'Bonificaciones', 'docCodigo' => '22', 'docDescripc' => 'CANC NOTA/BONI M.N.', 'importe' => 183181.42, 'valor_agregado' => 183181.42, 'porc_fact' => 0.64],
      ['ordPresent' => 4, 'grupoCodigo' => 'Boni', 'grupoDescripc' => 'Bonificaciones', 'docCodigo' => '13', 'docDescripc' => 'NOTA/BONI M.N.', 'importe' => -642232.25, 'valor_agregado' => -642232.25, 'porc_fact' => -2.23]
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


/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos para Pedidos de Venta.
 * 
 * @param string $FechaDesde
 * @param string $FechaHasta
 * @return array
 */
function SelectPedidos($FechaDesde, $FechaHasta)
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
      ['importe' => 51243046.35, 'costo' => 44011834.99, "valor_agregado" => 11320530.53]
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


/**
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
function CreaDataCompuesta($data, $pedidos)
{
  $contenido  = array();
  $grupos     = array();
  $grupo      = array();
  $docums     = array();
  $docum      = array();
  $arrPedidos = array();

  # Estructura para Documentos de venta
  if (count($data) > 0) {

    $grupoCodigo        = trim($data[0]['grupoCodigo']);
    $grupoDescripc      = trim($data[0]['grupoDescripc']);
    // $grupoImporte       = floatval($data[0]['importe']);
    // $grupoValorAgregado = floatval($data[0]['valor_agregado']);
    // $grupoPorcFact      = floatval($data[0]['porc_fact']);
    $grupoImporte       = 0.00;
    $grupoValorAgregado = 0.00;
    $grupoPorcFact      = 0.00;
    $importeFact        = 0.00;

    foreach ($data as $row) {

      //        "OrdPresent" => intval($row['ordPresent']),

      // Cambio de grupo de documentos
      if ($row['grupoCodigo'] != $grupoCodigo) {
        $grupoPorcFact = ($importeFact != 0) ? round(($grupoImporte / $importeFact) * 100, 2) : 0.00;

        $grupo = [
          "GrupoCodigo"   => trim($grupoCodigo),
          "GrupoDescripc" => trim($grupoDescripc),
          "GrupoImporte"  => round($grupoImporte, 2),
          "GrupoValorAgregado" => round($grupoValorAgregado, 2),
          "GrupoPorcFact" => round($grupoPorcFact, 2),
          "Documentos"    => $docums
        ];
        array_push($grupos, $grupo);

        $grupoCodigo        = trim($row['grupoCodigo']);
        $grupoDescripc      = trim($row['grupoDescripc']);
        $grupoImporte       = 0.00;
        $grupoValorAgregado = 0.00;
        $grupoPorcFact      = 0.00;
        $docums = array();
      }

      $grupoImporte       += floatval($row['importe']);
      $grupoValorAgregado += floatval($row['valor_agregado']);
      $grupoPorcFact      += floatval($row['porc_fact']);

      if (trim($grupoCodigo) == "Fact") {
        $importeFact += floatval($row['importe']);
      }

      $docum = [
        "DocCodigo"   => trim($row['docCodigo']),
        "DocDescripc" => trim($row['docDescripc']),
        "DocImporte"  => round($row['importe'], 2),
        "DocValorAgregado" => round($row['valor_agregado'], 2),
        "DocPorcFact" => round($row['porc_fact'], 2)
      ];
      array_push($docums, $docum);
    }

    // Ultimo registro
    $grupoPorcFact = ($importeFact != 0) ? ($grupoImporte / $importeFact) * 100 : 0.00;

    $grupo = [
      "GrupoCodigo"   => trim($grupoCodigo),
      "GrupoDescripc" => trim($grupoDescripc),
      "GrupoImporte"  => round($grupoImporte, 2),
      "GrupoValorAgregado" => round($grupoValorAgregado, 2),
      "GrupoPorcFact" => round($grupoPorcFact, 2),
      "Documentos"    => $docums
    ];
    array_push($grupos, $grupo);
  } // if (count($data) > 0)

  # Estructura para Pedidos de venta
  if (count($pedidos) > 0) {

    $arrPedidos = [
      "Importe"       => round(floatval($pedidos[0]['importe']), 2),
      "Costo"         => round(floatval($pedidos[0]['costo']), 2),
      "ValorAgregado" => round(floatval($pedidos[0]['valor_agregado']), 2)
    ];
  }

  $contenido = [
    "GruposFilas" => $grupos,
    "Pedidos" => $arrPedidos
  ];

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
