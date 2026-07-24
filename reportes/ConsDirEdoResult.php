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

    # --------------------------------------------------------------------------
    # Tabla resumen que se va a devolver al frontend
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE IF NOT EXISTS resumen (
      ord_present integer,
      seccion character(10),
      secc_descr character(30),
      signo_contable smallint,
      rubro character(30),
      interno numeric(14,2),
      externo numeric(14,2),
      total_fila numeric(14,2),
      porc_vta numeric(6,2)
    )";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # Obtiene Ventas y Costo de Ventas    ----------------------------
    VentasCosto($conn, $FechaDesde, $FechaHasta);

    # Merma Y Recuperación                ----------------------------
    Merma($conn, $FechaDesde, $FechaHasta);
    Recuperacion($conn, $FechaDesde, $FechaHasta);

    # Gastos de Fabricación               ----------------------------
    GastosFabricac($conn, $FechaDesde, $FechaHasta);

    # Fila para Utilidad Bruta: ventas menos costos y gastos directos 
    UtilidadBruta($conn);

    # Gastos Operativos, de "prueba" y de importacion  ---------------
    GastosOpera($conn, $FechaDesde, $FechaHasta);
    GastosPrueba($conn, $FechaDesde, $FechaHasta);
    GastosImportac($conn, $FechaDesde, $FechaHasta);

    # Fila para Utilidad Neta: Utilidad Bruta menos gastos indirectos
    UtilidadNeta($conn);

    # Resumen con datos que se van a presentar
    # -----------------------------------------
    $sqlCmd = "SELECT ord_present, trim(seccion) AS seccion, trim(secc_descr) AS secc_descr,
      signo_contable, trim(rubro) AS rubro, interno, externo, total_fila, porc_vta
      FROM resumen ORDER BY ord_present";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM resumen";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------


    # Maqueta de respuesta con datos de prueba <---------------------------------
    /*
    $arrData = [
      ['ordPresent' => 1, 'seccion' => 'Vtas', 'seccDescripc' => 'Ventas', 'signoContable' => 1, 'rubro' => 'Ventas', 'interno' => 59189770.65, 'externo' => 163510910.10, 'totalFila' => 222700680.75, 'porc_vta' => 100.00],

      ['ordPresent' => 2, 'seccion' => 'CostDirec', 'seccDescripc' => 'Costos directos', 'signoContable' => -1, 'rubro' => 'Costo de ventas', 'interno' => 41732172.43, 'externo' => 124760370.08, 'totalFila' => 166492542.51, 'porc_vta' => 74.76],
      ['ordPresent' => 2, 'seccion' => 'CostDirec', 'seccDescripc' => 'Costos directos', 'signoContable' => -1, 'rubro' => 'Merma', 'interno' => 5132620.93, 'externo' => 0.00, 'totalFila' => 5132620.93, 'porc_vta' =>  2.30],
      ['ordPresent' => 2, 'seccion' => 'CostDirec', 'seccDescripc' => 'Costos directos', 'signoContable' => -1, 'rubro' => 'Recuperación', 'interno' => -2048065.27, 'externo' => 0.00, 'totalFila' => -2048065.27, 'porc_vta' => -0.92],

      ['ordPresent' => 3, 'seccion' => 'GastDirec', 'seccDescripc' => 'Gastos directos', 'signoContable' => -1, 'rubro' => 'Gastos de fabricación', 'interno' => 0.00, 'externo' => 8540814.36, 'totalFila' => 8540814.36, 'porc_vta' => 3.84],

      ['ordPresent' => 4, 'seccion' => 'GastIndir', 'seccDescripc' => 'Gastos indirectos', 'signoContable' => -1, 'rubro' => 'Gastos operativos', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 39859428.48, 'porc_vta' => 0.00],
      ['ordPresent' => 4, 'seccion' => 'GastIndir', 'seccDescripc' => 'Gastos indirectos', 'signoContable' => -1, 'rubro' => 'Gastos prueba', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 89687.97, 'porc_vta' => 0.00],
      ['ordPresent' => 4, 'seccion' => 'GastIndir', 'seccDescripc' => 'Gastos indirectos', 'signoContable' => -1, 'rubro' => 'Gastos Importación', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 3638422.04, 'porc_vta' => 0.00],

      ['ordPresent' => 5, 'seccion' => 'Utild', 'seccDescripc' => 'Utilidad', 'signoContable' => 1, 'rubro' => 'Utilidad Neta', 'interno' => 0.00, 'externo' => 0.00, 'totalFila' => 995229.73, 'porc_vta' => 0.45]
    ];
    */

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
    $seccDescripc  = trim($data[0]["secc_descr"]);
    $signoContable = intval($data[0]["signo_contable"]);     // 1=positivo, -1=negativo
    $seccInterno   = 0.00;
    $seccExterno   = 0.00;
    $seccTotal     = 0.00;
    $seccPorcVta   = 0.00;

    $totalVentas = floatval($data[0]["total_fila"]);

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
          "SeccPorcVta"   => round($seccTotal / $totalVentas * 100, 2),
          "SeccRubros"    => $rubros
        ];
        //"SeccPorcVta"   => round($seccPorcVta, 2),
        array_push($secciones, $seccion);

        // Reinicio variables para nueva sección
        $seccion   = trim($row["seccion"]);
        $seccDescripc = trim($row["secc_descr"]);
        $signoContable = intval($row["signo_contable"]);
        $seccInterno   = 0.00;
        $seccExterno   = 0.00;
        $seccTotal     = 0.00;
        $seccPorcVta   = 0.00;
        $rubros    = array();
      }

      $seccInterno   += $row["interno"];
      $seccExterno   += $row["externo"];
      $seccTotal     += $row["total_fila"];
      $seccPorcVta   += $row["porc_vta"];

      $rubro = [
        "Rubro"        => trim($row["rubro"]),
        "RubroInterno" => round($row["interno"], 2),
        "RubroExterno" => round($row["externo"], 2),
        "RubroTotal"   => round($row["total_fila"], 2),
        "RubroPorcVta" => round($row["total_fila"] / $totalVentas * 100, 2)
      ];
      //"RubroPorcVta" => round($row["porc_vta"], 2)
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
      "SeccPorcVta"   => round($seccTotal / $totalVentas * 100, 2),
      "SeccRubros"    => $rubros
    ];
    array_push($secciones, $seccion);

    $contenido = ["EdoResultSecciones" => $secciones];
  } // if (count($data) > 0)

  return $contenido;
}


/** ****************************************************************************
 * Obtiene Ventas y Costo de Ventas, y los inserta en la tabla temporal 'resumen'
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function VentasCosto(PDO $conn, string $FechaDesde, string $FechaHasta)
{

  # Ventas en el periodo  (sin agrupar por e1_tipoie, da una sola fila)
  # Obtiene suma de importe SIN IVA y valor agregado
  # ----------------------------------------------------------------------------
  // CREATE TEMPORARY TABLE IF NOT EXISTS ventas_costo AS
  $sqlCmd = "SELECT 
    SUM( CASE
     WHEN est.e1_tipoie='I' THEN est.e1_imp
     ELSE 0 END) AS importe_int,
    SUM( CASE
     WHEN est.e1_tipoie='E' THEN est.e1_imp
     ELSE 0 END) AS importe_ext,
    SUM( CASE
     WHEN est.e1_tipoie='I' THEN est.e1_va
     ELSE 0 END) AS val_agreg_int,
    SUM( CASE
     WHEN est.e1_tipoie='E' THEN est.e1_va
     ELSE 0 END) AS val_agreg_ext
    FROM cli040 est
    JOIN var020 cat ON cat.t_tica='02' AND est.e1_cat=cat.t_gpo AND t_clave=''
    WHERE est.e1_fecha >= :FechaDesde AND est.e1_fecha <= :FechaHasta
      AND SUBSTRING(cat.t_param,1,1) = '1'
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();
  $arrVtasCosto = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  # Agrega registros de ventas y costo a la tabla que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totVtasFila = 0.00;
  $porcVta     = 0.00;
  $numFilas = count($arrVtasCosto);
  if ($numFilas > 0) {
    $importeInt  = $arrVtasCosto[0]["importe_int"];
    $importeExt  = $arrVtasCosto[0]["importe_ext"];
    $totVtasFila  = $importeInt + $importeExt;
    $porcVta = 100.00;
  }

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, signo_contable,
      rubro, interno, externo, total_fila, porc_vta) 
    VALUES (1, 'Vtas', 'Ventas', 1, 'Ventas', :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totVtasFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();

  # Costo de ventas
  $totCostoFila = $arrVtasCosto[0]["val_agreg_int"] + $arrVtasCosto[0]["val_agreg_ext"];

  $importeInt   = 0.00;
  $importeExt   = 0.00;
  $totCostoFila = 0.00;
  $porcVta      = 0.00;
  $numFilas = count($arrVtasCosto);
  if ($numFilas > 0) {
    $importeInt  = $arrVtasCosto[0]["importe_int"] - $arrVtasCosto[0]["val_agreg_int"];
    $importeExt  = $arrVtasCosto[0]["importe_ext"] - $arrVtasCosto[0]["val_agreg_ext"];
    $totCostoFila  = $importeInt + $importeExt;
    if ($totVtasFila != 0) {
      $porcVta = ($totCostoFila / $totVtasFila) * 100;
    }
  }

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, signo_contable,
      rubro, interno, externo, total_fila, porc_vta) 
    VALUES (2, 'CostDirec', 'Costos Directos', -1, 'Costo de Ventas', :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totCostoFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene importe de la Merma y lo inserta en la tabla temporal 'resumen'
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function Merma(PDO $conn, string $FechaDesde, string $FechaHasta)
{
  # Obtiene Merma por clave de artículo
  # 18 Salidas por Merma MP | 48 Salidas por Merma PT | 10 Entradas por Recuperacion
  $sqlCmd = "CREATE TEMPORARY TABLE merma AS
    SELECT mo.m_clave,SUM(mo.m_can) AS m_can, MAX(co.co_l1c) AS co_l1c
    FROM inv030 mo
    JOIN compon co ON mo.m_lin=co.co_lin AND mo.m_clave=co.co_clave
    WHERE m_fecac >= :FechaDesde AND m_fecac <= :FechaHasta
      AND (m_mov='18' OR m_mov='48')
    GROUP BY m_clave
    ORDER BY m_clave::integer;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();

  # Suma Costo de elementos de Merma
  $sqlCmd = "SELECT SUM(m_can * co_l1c) AS merma_importe FROM merma";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrMerma = $oSQL->fetch(PDO::FETCH_ASSOC);

  // # DEBUG -------------------------------------
  // $msg = json_encode($arrMerma, JSON_PRETTY_PRINT);
  // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
  // # DEBUG END ---------------------------------

  # Agrega registro de merma que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;
  $numFilas = count($arrMerma);
  if ($numFilas > 0) {
    $importeInt  = $arrMerma["merma_importe"] * -1;
    $totalFila   = $importeInt + $importeExt;
  }

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, signo_contable,
      rubro, interno, externo, total_fila, porc_vta) 
    VALUES (2, 'CostDirec', 'Costos Directos', -1, 'Merma', :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene importe por recuperación y lo inserta en la tabla 'resumen'
 * Hay que obtener por separado el importe por tipo de metal y sumarlo al final
 * 
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function Recuperacion(PDO $conn, string $FechaDesde, string $FechaHasta)
{
  # Obtiene paridades del Oro y la Plata porque algunos movimientos de recuperacion 
  # se registran en dias donde no se capturaron paridades 
  $paridadOro = 0.00;
  $paridadPlata = 0.00;

  $sqlCmd = "SELECT ti_parx AS par_oro FROM inv100 WHERE ti_llave='2'";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  if ($arrParid = $oSQL->fetch(PDO::FETCH_ASSOC)) {
    $paridadOro = $arrParid["par_oro"];
  }

  $sqlCmd = "SELECT ti_parx AS par_plata FROM inv100 WHERE ti_llave='7'";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  if ($arrParid = $oSQL->fetch(PDO::FETCH_ASSOC)) {
    $paridadPlata = $arrParid["par_plata"];
  }

  # Obtiene importe de recuperacion por clave de articulo 
  # Clave de metal: 95=Plata | 99=Oro
  # Clave de movimiento 10=Entradas por Recuperacion
  # Clave en Paridades: 2=Oro | 7=Plata
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE recup_plata AS
    SELECT mo.m_clave,mo.m_can,mo.m_fecha,
      COALESCE(h.hi_parx, :ParidadPlata) AS hi_parx  -- Considera caso en que no se registro paridad del dia
    FROM inv030 mo
    LEFT JOIN inv110 h ON h.hi_llave='7' AND mo.m_fecha=h.hi_fecha  -- 7=Plata
    WHERE mo.m_fecac >= :FechaDesde AND mo.m_fecac <= :FechaHasta
      AND mo.m_mov='10'
      AND trim(mo.m_clave)='95' 
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":ParidadPlata", $paridadPlata);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();

  $sqlCmd = "CREATE TEMPORARY TABLE recup_oro AS
    SELECT mo.m_clave,mo.m_can,mo.m_fecha,
      COALESCE(h.hi_parx, :ParidadOro) AS hi_parx -- Considera caso en que no se registro paridad del dia
    FROM inv030 mo
    LEFT JOIN inv110 h ON h.hi_llave='2' AND mo.m_fecha=h.hi_fecha	-- 2=Oro
    WHERE mo.m_fecac >= :FechaDesde AND mo.m_fecac <= :FechaHasta
      AND mo.m_mov='10'
      AND trim(mo.m_clave)='99'
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":ParidadOro", $paridadOro);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();

  # Suma resultados para obtener el Costo de la recuperación por tipo de metal
  # --------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE recup_suma AS
    SELECT 'Oro' AS tipo_metal,SUM(m_can * hi_parx) AS importe FROM recup_oro
    UNION ALL
    SELECT 'Plata' AS tipo_metal,SUM(m_can * hi_parx) AS importe FROM recup_plata
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  $sqlCmd = "SELECT tipo_metal,importe FROM recup_suma";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrRecup = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  $sumaRecuperac = 0.00;
  if (!empty($arrRecup)) {
    foreach ($arrRecup as $recup) {
      $sumaRecuperac += floatval($recup["importe"]);
    }
  }

  # Agrega registros de recuperacion que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;
  if (!empty($arrRecup)) {
    $importeInt  = $sumaRecuperac * -1;
    $totalFila   = $importeInt + $importeExt;
  }

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, signo_contable,
      rubro, interno, externo, total_fila, porc_vta) 
    VALUES (2, 'CostDirec', 'Costos Directos', -1, 'Recuperacion', :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene gastos de fabricación para artículos de MP y PT, los suma y
 * los inserta en la tabla temporal 'resumen'
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function GastosFabricac(PDO $conn, string $FechaDesde, string $FechaHasta)
{

  # Obtiene Gastos de Fabricación MP y PT en tablas separadas
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE gasfab_mp AS
    SELECT SUM(COALESCE(m_mo, 0)) AS mano_obra, SUM(COALESCE(m_ishipc, 0)) AS iship,
    SUM(COALESCE(m_gasfab, 0)) AS gasfab, SUM(COALESCE(m_otr, 0)) AS otros
    FROM inv030 
    WHERE m_fecha >= :FechaDesde AND m_fecha <= :FechaHasta
    AND m_lin='01'
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();

  $sqlCmd = "CREATE TEMPORARY TABLE gasfab_pt AS
    SELECT SUM(COALESCE(mo_mo, 0)) mano_obra, SUM(COALESCE(mo_ishipc, 0)) iship,
    SUM(COALESCE(mo_gasfab, 0)) gasfab, SUM(COALESCE(mo_otr, 0)) otros
    FROM inv040
    WHERE mo_fecac >= :FechaDesde AND mo_fecac <= :FechaHasta
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();

  # Une MP y PT para sumar gastos (el resultado debe ser una sola fila)
  # ----------------------------------------------------------------------------
  $sqlCmd = "SELECT
      SUM(mano_obra) AS mano_obra,
      SUM(iship) AS iship,
      SUM(gasfab) AS gasfab,
      SUM(otros) AS otros
    FROM (
      SELECT mano_obra,iship,gasfab,otros FROM gasfab_mp
      UNION ALL
      SELECT mano_obra,iship,gasfab,otros FROM gasfab_pt
    ) suma_tablas
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrGasFab = $oSQL->fetch(PDO::FETCH_ASSOC);    // Debe ser una sola fila

  # Agrega registro con gastos de fabricacion a tabla que se envia al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;
  $numFilas = count($arrGasFab);
  if ($numFilas > 0) {
    $importeExt  = $arrGasFab["mano_obra"] + $arrGasFab["iship"] +
      $arrGasFab["gasfab"] + $arrGasFab["otros"];
    $totalFila   = $importeInt + $importeExt;
  }

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, 
    signo_contable, rubro, interno, externo, total_fila, porc_vta) 
    VALUES (3, 'GastDirec', 'Gastos Directos', -1, 'Gastos de fabricacion', 
    :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene importe de Gastos Operativos y lo inserta en la tabla 'resumen'
 * Además de los movimientos en CxP incluye las comisiones a los agentes de venta
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function GastosOpera(PDO $conn, string $FechaDesde, string $FechaHasta)
{

  $GastosCxP   = 0.00;
  $GastosComis = 0.00;

  # Obtiene Gastos operativos de la tabla de CxP
  # --------------------------------------------
  $sqlCmd = "SELECT SUM(cxp.vs_imp * cxp.vs_par) AS importe_gastos
    FROM prov20 cxp
    JOIN var020 gast ON gast.t_tica='10' AND gast.t_gpo='83' AND cxp.vs_cve = gast.t_clave
    WHERE cxp.vs_feex >= :FechaDesde AND cxp.vs_feex <= :FechaHasta
    AND cxp.vs_mov='02'
    AND SUBSTRING(gast.t_param,1,1)='1'
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();
  $arrGasCxP = $oSQL->fetch(PDO::FETCH_ASSOC);

  if ($arrGasCxP) {
    $GastosCxP = $arrGasCxP["importe_gastos"];
  }

  # Obtiene Comisiones sobe venta para agregarlos a los Gastos de CxP
  # ----------------------------------------------------------------------------
  $sqlCmd = "SELECT SUM(cxp.sc_imp * cxp.sc_ticam) AS importe_comis
    FROM cli020 cxp
    JOIN var020 com ON com.t_tica='10' AND com.t_gpo='80' AND cxp.sc_mov=com.t_clave
    WHERE cxp.sc_feex >= :FechaDesde AND cxp.sc_feex <= :FechaHasta
    AND SUBSTRING(com.t_param,27,1)='1'
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();
  $arrGasComis = $oSQL->fetch(PDO::FETCH_ASSOC);

  if ($arrGasComis) {
    $GastosComis = $arrGasComis["importe_comis"] * -1;
  }

  # Agrega registro con gastos de operacion a tabla que se envia al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;

  $totalFila   = $GastosCxP + $GastosComis;

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, 
    signo_contable, rubro, interno, externo, total_fila, porc_vta) 
    VALUES (5, 'GastIndir', 'Gastos indirectos', -1, 'Gastos Operativos', 
    :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene importe de Gastos "de Prueba" y lo inserta en la tabla 'resumen'
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function GastosPrueba(PDO $conn, string $FechaDesde, string $FechaHasta)
{

  $GastosPrueba = 0.00;

  # Obtiene gastos de la compañía "de prueba"
  # -----------------------------------------
  $sqlCmd = "SELECT SUM((cxp.vs_imp + cxp.vs_iva)*vs_par) AS importe_gastos
    FROM prov20p cxp
    JOIN var020 prm ON prm.t_tica='10' AND prm.t_gpo='82' AND prm.t_clave=cxp.vs_mov
    WHERE cxp.vs_feex >= :FechaDesde AND cxp.vs_feex <= :FechaHasta
    AND vs_num::integer >= 9900
    AND SUBSTRING(prm.t_param,6,1) <> '0'
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();
  $arrGasPrba = $oSQL->fetch(PDO::FETCH_ASSOC);

  if ($arrGasPrba) {
    $GastosPrueba = $arrGasPrba["importe_gastos"];
  }

  # Agrega registro con gastos de operacion a tabla que se envia al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;

  $totalFila   = $GastosPrueba;

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, 
    signo_contable, rubro, interno, externo, total_fila, porc_vta) 
    VALUES (5, 'GastIndir', 'Gastos indirectos', -1, 'Gastos Prueba', 
    :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene importe de Gastos de Importación MP y lo inserta en la tabla 'resumen'
 * @param PDO $conn
 * @param string $FechaDesde
 * @param string $FechaHasta
 */
function GastosImportac(PDO $conn, string $FechaDesde, string $FechaHasta)
{

  $gastosImportac = 0.00;

  # Obtiene Gastos de Importación MP F/C de la tabla de movimientos de compras
  # Clave 02=Gastos | El parámetro del concepto de gasto indica si es por importación
  # ------------------------------------------------------------------------------
  $sqlCmd = "SELECT SUM(vs_imp * vs_par) AS importe_gastos
  FROM prov20 cxp
  JOIN var020 prm ON prm.t_tica='10' AND prm.t_gpo='83' AND prm.t_clave=cxp.vs_cve -- Conc Gastos
  WHERE cxp.vs_feex >= :FechaDesde AND cxp.vs_feex <= :FechaHasta
  AND vs_mov='02'
  AND SUBSTRING(prm.t_param,1,1) = '2'
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaDesde", $FechaDesde);
  $oSQL->bindParam(":FechaHasta", $FechaHasta);
  $oSQL->execute();
  $arrGasImpo = $oSQL->fetch(PDO::FETCH_ASSOC);

  if ($arrGasImpo) {
    $gastosImportac = $arrGasImpo["importe_gastos"];
  }

  # Agrega registro con gastos de operacion a tabla que se envia al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;

  $totalFila   = $gastosImportac;

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, 
    signo_contable, rubro, interno, externo, total_fila, porc_vta) 
    VALUES (5, 'GastIndir', 'Gastos indirectos', -1, 'Gastos Importacion', 
    :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Calcula Utilidad Bruta en base a filas agregadas anteriormente
 * @param PDO $conn
 */
function UtilidadBruta(PDO $conn)
{

  # El signo contable se usa para indicar si el importe se suma o resta 
  # al total que se está calculando
  $sqlCmd = "SELECT 
      SUM(interno * signo_contable) AS interno,
      SUM(externo * signo_contable) AS externo,
      SUM(total_fila * signo_contable ) AS total_fila
    FROM resumen
    WHERE TRIM(seccion) IN('Vtas', 'CostDirec', 'GastDirec')
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrUtilBruta = $oSQL->fetchall(PDO::FETCH_ASSOC);

  # Agrega registro a la tabla que se envia al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;

  if ($arrUtilBruta) {
    $importeInt = $arrUtilBruta[0]["interno"];
    $importeExt = $arrUtilBruta[0]["externo"];
    $totalFila = $arrUtilBruta[0]["total_fila"];
  }

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, 
    signo_contable, rubro, interno, externo, total_fila, porc_vta) 
    VALUES (4, 'UtilBr', 'Utilidad Bruta', 1, 'Utilidad Bruta', 
    :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();


  // # DEBUG -------------------------------------
  // $msg = json_encode($arrUtilBruta, JSON_PRETTY_PRINT);
  // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
  // # DEBUG END ---------------------------------
  // exit;

  // # DEBUG -------------------------------------
  // $sqlCmd = "SELECT * FROM gasfab_pt";
  // $oSQL = $conn->prepare($sqlCmd);
  // $oSQL->execute();
  // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
  // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
  // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
  // # DEBUG END ---------------------------------
  // exit;

}

/** ****************************************************************************
 * Calcula Utilidad Neta en base a filas agregadas anteriormente
 * @param PDO $conn
 */
function UtilidadNeta(PDO $conn)
{

  $utilidadNeta = 0.00;

  # El signo contable se usa para indicar si el importe se suma o resta 
  # al total que se está calculando
  $sqlCmd = "SELECT SUM( total_fila * signo_contable ) AS utilidad_neta
    FROM resumen
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrUtilidad = $oSQL->fetch(PDO::FETCH_ASSOC);

  if ($arrUtilidad) {
    $utilidadNeta = $arrUtilidad["utilidad_neta"];
  }

  # Agrega registro a la tabla que se envia al frontend
  # ----------------------------------------------------------------------------
  $importeInt  = 0.00;
  $importeExt  = 0.00;
  $totalFila   = 0.00;
  $porcVta     = 0.00;

  $totalFila   = $utilidadNeta;

  $sqlCmd = "INSERT INTO resumen (ord_present, seccion, secc_descr, 
    signo_contable, rubro, interno, externo, total_fila, porc_vta) 
    VALUES (7, 'Utild', 'Utilidad Neta', 1, 'Utilidad Neta', 
    :importe_int, :importe_ext, :totalFila, :porcVta)
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":importe_int", $importeInt);
  $oSQL->bindParam(":importe_ext", $importeExt);
  $oSQL->bindParam(":totalFila", $totalFila);
  $oSQL->bindParam(":porcVta", $porcVta);
  $oSQL->execute();
}

/** ****************************************************************************
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales(PDO $conn)
{

  $tablasTemp = [
    "resumen",
    "ventas_costo",
    "merma",
    "recup_plata",
    "recup_oro",
    "recup_suma",
    "gasfab_mp",
    "gasfab_pt",
  ];

  foreach ($tablasTemp as $tabla) {
    $dropCmd = "DROP TABLE IF EXISTS " . $tabla;
    $drop = $conn->prepare($dropCmd);
    $drop->execute();
  }
}
