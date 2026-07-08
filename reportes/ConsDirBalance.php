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
$TipoCambioMN  = 1.00;     // Tipo de cambio moneda nacional
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

  # Parámetros restantes
  if (isset($TipoCambioMN)) {
    $TipoCambioMN = $_GET["TipoCambioMN"];
  }
  if (isset($TipoCambioUSD)) {
    $TipoCambioUSD = $_GET["TipoCambioUSD"];
  }
  if (isset($TipoCambioORO)) {
    $TipoCambioORO = $_GET["TipoCambioORO"];
  }
  if (isset($TipoCambioPLATA)) {
    $TipoCambioPLATA = $_GET["TipoCambioPLATA"];
  }

  //
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
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA
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

    # --------------------------------------------------------------------------
    # Tabla resumen que se va a devolver al frontend
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE IF NOT EXISTS resumen (
      seccion character(10),
      secc_descr character(30),
      rubro character(30),      
      rubro_mn numeric(14,2),
      rubro_usd numeric(14,2),
      rubro_usd_conv numeric(14,2),
      oro_gramos numeric(14,2),
      oro_importe numeric(14,2),
      plata_gramos numeric(14,2),
      plata_importe numeric(14,2),
      total_fila_mn numeric(14,2)
    )";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # Obtiene rubro "Inventario MP"
    InventarioMP($conn, $FechaCorte, $TipoCambioMN, $TipoCambioUSD, $TipoCambioORO, $TipoCambioPLATA);

    # Obtiene rubro "Inventario PT"
    InventarioPT($conn, $FechaCorte, $TipoCambioMN, $TipoCambioUSD, $TipoCambioORO, $TipoCambioPLATA);

    # Obtiene rubro "Inv en Tránsito"
    InvTransito($conn, $FechaCorte, $TipoCambioMN, $TipoCambioUSD, $TipoCambioORO, $TipoCambioPLATA);

    # Rubro Cuentas por Cobrar
    CtasCobrar($conn, $FechaCorte, $TipoCambioMN, $TipoCambioUSD, $TipoCambioORO, $TipoCambioPLATA);

    # Rubro Bancos
    Bancos($conn, $FechaCorte, $TipoCambioMN, $TipoCambioUSD, $TipoCambioORO, $TipoCambioPLATA);

    # Rubros Pasivo Circulante: Proveedor, Acreedor, Acreedor Santander, Acreedor Bancomer
    Proveedores($conn, $FechaCorte, $TipoCambioMN, $TipoCambioUSD, $TipoCambioORO, $TipoCambioPLATA);

    # Resumen con datos que se van a presentar
    $sqlCmd = "SELECT trim(seccion) AS seccion, trim(secc_descr) AS secc_descr,
      trim(rubro) AS rubro, rubro_mn, rubro_usd, rubro_usd_conv, oro_gramos, oro_importe, 
      plata_gramos, plata_importe, total_fila_mn
      FROM resumen ORDER BY seccion,secc_descr";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);


    # Maquetamos respuesta con datos de prueba <---------------------------------
    // $arrData = [
    //   ['seccion' => 'Activos', 'secc_descr' => 'ACTIVO CIRCULANTE', 'rubro' => 'Inventario M.P.',  'rubro_mn' => 13216288.18,  'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 35185.49, 'oro_importe' => 89332503.90,  'plata_gramos' => 42408.54, 'plata_importe' => 1838410.21, 'total_fila_mn' => 104387202.28],
    //   ['seccion' => 'Activos', 'secc_descr' => 'ACTIVO CIRCULANTE', 'rubro' => 'Inventario P.T.',  'rubro_mn' => 2891288.09,   'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 53708.23, 'oro_importe' => 136359922.00, 'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 139251209.91],
    //   ['seccion' => 'Activos', 'secc_descr' => 'ACTIVO CIRCULANTE', 'rubro' => 'Inv en Transito',  'rubro_mn' => 370.62,       'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 50.11,    'oro_importe' => 127224.37,    'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 127594.99],
    //   ['seccion' => 'Activos', 'secc_descr' => 'ACTIVO CIRCULANTE', 'rubro' => 'Ctas por Cobrar',  'rubro_mn' => 104421774.94, 'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 6242.95,  'oro_importe' => 15850237.00,  'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 120272011.93],
    //   ['seccion' => 'Activos', 'secc_descr' => 'ACTIVO CIRCULANTE', 'rubro' => 'CXC Cart Judic',   'rubro_mn' => 0.00,         'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 0.00,     'oro_importe' => 0.00,         'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 0.00],
    //   ['seccion' => 'Activos', 'secc_descr' => 'ACTIVO CIRCULANTE', 'rubro' => 'Bancos',           'rubro_mn' => 5620328.49,   'rubro_usd' => 3524.50,   'rubro_usd_conv' => 61048.92,   'oro_gramos' => 0.00,     'oro_importe' => 0.00,         'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 5681377.41],
    //   ['seccion' => 'Pasivos', 'secc_descr' => 'PASIVO CIRCULANTE', 'rubro' => 'Proveedores',      'rubro_mn' => -6206986.80,  'rubro_usd' => 563934.62, 'rubro_usd_conv' => 9768080.73, 'oro_gramos' => 0.00,     'oro_importe' => 0.00,         'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 3561093.93],
    //   ['seccion' => 'Pasivos', 'secc_descr' => 'PASIVO CIRCULANTE', 'rubro' => 'Acreedores',       'rubro_mn' => 20314.61,     'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 0.00,     'oro_importe' => 0.00,         'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 20314.61],
    //   ['seccion' => 'Pasivos', 'secc_descr' => 'PASIVO CIRCULANTE', 'rubro' => 'Acreed Santander', 'rubro_mn' => 142006217.21, 'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 0.00,     'oro_importe' => 0.00,         'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 142006217.21],
    //   ['seccion' => 'Pasivos', 'secc_descr' => 'PASIVO CIRCULANTE', 'rubro' => 'Acreed Bancomer',  'rubro_mn' => 20000000.00,  'rubro_usd' => 0.00,      'rubro_usd_conv' => 0.00,       'oro_gramos' => 0.00,     'oro_importe' => 0.00,         'plata_gramos' => 0.00, 'plata_importe' => 0.00, 'total_fila_mn' => 20000000.00]
    // ];

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM resumen";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------
    $msg = json_encode($arrData, JSON_PRETTY_PRINT);
    file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);


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
    $seccionDescripc  = trim($data[0]['secc_descr']);
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
        $seccionDescripc  = trim($row['secc_descr']);
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

      $seccionMN        += floatval($row['rubro_mn']);
      $seccionUSD       += floatval($row['rubro_usd']);
      $seccionUSDconv   += floatval($row['rubro_usd_conv']);
      $seccionORO       += floatval($row['oro_gramos']);
      $seccionOROconv   += floatval($row['oro_importe']);
      $seccionPLATA     += floatval($row['plata_gramos']);
      $seccionPLATAconv += floatval($row['plata_importe']);
      $seccionTotal     += floatval($row['total_fila_mn']);

      $rubro = [
        "RubroDescripc"  => trim($row['rubro']),
        "RubroMN"        => round($row['rubro_mn'], 2),
        "RubroUSD"       => round($row['rubro_usd'], 2),
        "RubroUSDconv"   => round($row['rubro_usd_conv'], 2),
        "RubroORO"       => round($row['oro_gramos'], 2),
        "RubroOROconv"   => round($row['oro_importe'], 2),
        "RubroPLATA"     => round($row['plata_gramos'], 2),
        "RubroPLATAconv" => round($row['plata_importe'], 2),
        "RubroTotal"     => round($row['total_fila_mn'], 2)
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
 * Obtiene datos para el rubro "Inventario MP"
 */
function InventarioMP(
  pdo $conn,
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA
) {
  # Obtiene articulos del catalogo de MP que se van a incluir en el reporte.
  # inv020 cmp catalogo ítems materia prima
  # a_lin='01' <- metal y piedra | a_facoro='0' <- piedra
  # --------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE IF NOT EXISTS catmp AS
    SELECT 
      -- Asigna grupo del metal: oro, plata	
      CASE
        WHEN cmp.a_facoro<>0 THEN
          CASE 
          WHEN trim(COALESCE(com.co_clave,''))='1' 
            OR trim(COALESCE(com.co_clave,''))='95'
          THEN 'PLATA'
          ELSE 'ORO'
        END
        ELSE ''
      END AS grupo_metal,
      cmp.a_lin,cmp.a_clave,cmp.a_descr,cmp.a_facoro,
      -- en caso de piedra, se va a usar el costo premium del catalogo de componentes
      CASE
        WHEN cmp.a_facoro=0 THEN COALESCE(com.co_l1p, 0)
        ELSE cmp.a_costo
      END AS a_costo,
      -- se reemplaza el factor oro en metales, excepto plata
      CASE
        WHEN cmp.a_facoro<>0 
         AND trim(COALESCE(com.co_clave,''))<>'1' 
         AND trim(COALESCE(com.co_clave,''))<>'95' 
        THEN com.co_facmaq
        ELSE cmp.a_facoro
      END AS new_facoro,
      COALESCE(exi.i5_san, 0) saldo_anterior
    FROM inv020 cmp
    LEFT JOIN compon com ON cmp.a_clave=com.co_clave
    LEFT JOIN inv060 exi ON cmp.a_clave=exi.i5_clave
    WHERE cmp.a_lin='01' -- AND cmp.a_facoro > 0
    ORDER BY cmp.a_lin,cmp.a_clave::INTEGER
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # MOVIMIENTOS DE INVENTARIO A LA FECHA DE CORTE
  # ----------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE IF NOT EXISTS movinv AS
      SELECT c.grupo_metal,c.a_clave,
        MAX(c.saldo_anterior) AS saldo_anterior,
        SUM(COALESCE(i.m_can,0)) AS cant_mov,	
        MAX(c.new_facoro) new_facoro, 
        MAX(c.a_costo) a_costo
      FROM catmp c
      LEFT JOIN inv030 i ON c.a_lin=i.m_lin AND c.a_clave=i.m_clave
      LEFT JOIN var020 v ON v.t_tica='10' AND v.t_gpo='84' AND v.t_clave=i.m_mov
      WHERE i.m_fecha <= :FechaCorte
        AND SUBSTRING(v.t_param,7,1)<>'1' 	
      GROUP BY c.grupo_metal,a_clave
      ORDER BY c.a_clave::INTEGER
      ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();

  # Obtiene existencias de metal a la fecha de corte y lo convierte a gramos
  # (saldo_anterior + cant_mov) saldo_corte,
  # ------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE IF NOT EXISTS invmp_metal AS
      SELECT grupo_metal, 
        SUM((saldo_anterior + cant_mov) * new_facoro) AS gramos
        FROM movinv 
      WHERE new_facoro > 0
      GROUP BY grupo_metal
      ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  $sqlCmd = "SELECT * FROM invmp_metal";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrMP = $oSQL->fetchAll(PDO::FETCH_ASSOC);


  # Obtiene el importe de piedra para asignarlo a la columna "importe_mn"
  # ---------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE IF NOT EXISTS invmp_piedra AS
      SELECT
      SUM((saldo_anterior + cant_mov) * a_costo) AS importe
      FROM movinv 
      WHERE trim(grupo_metal) = ''
      GROUP BY grupo_metal
      ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  $sqlCmd = "SELECT importe FROM invmp_piedra";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrPIED = $oSQL->fetchAll(PDO::FETCH_ASSOC);


  # Agrega registro a la tabla que se va a enviar al frontend
  # ---------------------------------------------------------
  $mp_mn       = 0.00;
  $mp_usd      = 0.00;
  $mp_usd_conv = 0.00;
  $mp_oro      = 0.00;
  $mp_oro_conv = 0.00;
  $mp_plata    = 0.00;
  $mp_plata_conv = 0.00;

  $numFilas = count($arrPIED);
  if ($numFilas > 0) {
    $mp_mn = $arrPIED[0]["importe"];
  }

  $numFilas = count($arrMP);
  if ($numFilas > 0) {
    foreach ($arrMP as $itm) {
      switch ($itm["grupo_metal"]) {
        case 'ORO':
          $mp_oro = $itm["gramos"];
          $mp_oro_conv = round($itm["gramos"] * $TipoCambioORO, 2);
          break;
        case 'PLATA':
          $mp_plata = $itm["gramos"];
          $mp_plata_conv = round($itm["gramos"] * $TipoCambioPLATA, 2);
          break;
      }
    }
  }
  $mp_total_fila = round($mp_mn + $mp_usd_conv + $mp_oro_conv + $mp_plata_conv, 2);

  $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Activos','ACTIVO CIRCULANTE','Inventario MP',$mp_mn,
      $mp_usd,$mp_usd_conv,$mp_oro,$mp_oro_conv,$mp_plata,$mp_plata_conv,
      $mp_total_fila)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
}

/**
 * Obtiene datos para el rubro "Inventario MP"
 */
function InventarioPT(
  pdo $conn,
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA
) {

  # Para no trabajar con todo el catalogo de articulos, empiezo trabajando la tabla de
  # existencias inv015. 
  # A parir de aqui buscon los articulos que han tenido movimientos, los cuales se 
  # en la tabal inv040 
  # Se deben agrupar por s_lin+s_clave ya que pueden estar en diferentes oficinas y
  # diferentes áreas.
  # Para determinar el tipo de metal de cada artículo y obtener el factor oro 
  # correspondiente busco los componentes de cada articulo en la tabla compon.
  # De la tabla inv010 se obtiene el número de piedras por pieza indicado en la especificación.


  # Obtiene articulos con saldo inicial <> 0 o que han tenido movimientos
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE catpt AS
    SELECT exi.s_lin,exi.s_clave,SUM(exi.s_san) AS s_san,SUM(exi.s_sanp) AS s_sanp
      FROM inv015 exi
    GROUP BY exi.s_lin,exi.s_clave
    ORDER BY exi.s_lin,exi.s_clave ;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();


  # Obtiene movimientos a la fecha de corte
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE movinvpt AS
    SELECT exi.s_lin,exi.s_clave,
      MAX(COALESCE(exi.s_san, 0)) AS inic_gramos,
      SUM(COALESCE(mov.mo_can, 0)) mov_gramos,
      MAX(COALESCE(exi.s_san,0))+SUM(COALESCE(mov.mo_can,0)) AS saldo_gramos,
      MAX(COALESCE(exi.s_sanp,0)) AS inic_piezas,
      SUM(COALESCE(mov.mo_pzas,0)) mov_piezas,
      MAX(COALESCE(exi.s_sanp,0))+SUM(COALESCE(mov.mo_pzas,0)) AS saldo_piezas
      FROM catpt exi
      LEFT JOIN inv040 mov ON exi.s_lin=mov.mo_lin AND exi.s_clave=mov.mo_clave
      LEFT JOIN var020 doc ON doc.t_tica='10' AND doc.t_gpo='81' AND mov.mo_mov=doc.t_clave
    WHERE mov.mo_fecdo <= :FechaCorte AND SUBSTRING(doc.t_param,7,1)<>'1'
      GROUP BY exi.s_lin,exi.s_clave
      HAVING (MAX(COALESCE(exi.s_san))+SUM(COALESCE(mov.mo_can,0)))<>0 
        AND (MAX(COALESCE(exi.s_sanp,0))+SUM(COALESCE(mov.mo_pzas,0)))<> 0;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();

  # Esta técnica permite "despivotar" (convertir columnas a filas) dinámicamente para cada fila de la tabla padre,
  # lo cual es mucho más performante y escalable que hacer un UNION ALL manual si tienes muchos registros.
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE especif AS
    SELECT mov.s_lin,mov.s_clave,
      esp.c_co, esp.c_ca, esp.c_gr,
      com.co_facmaq,com.co_grupo,com.co_l1p
    FROM movinvpt mov
    INNER JOIN inv010 pt ON mov.s_lin=pt.c_lin AND mov.s_clave=pt.c_clave
    CROSS JOIN LATERAL (
      VALUES
        (COALESCE(pt.c_co1,0), COALESCE(pt.c_ca1,0), COALESCE(pt.c_gr1,0)),
        (COALESCE(pt.c_co2,0), COALESCE(pt.c_ca2,0), COALESCE(pt.c_gr2,0)),
        (COALESCE(pt.c_co3,0), COALESCE(pt.c_ca3,0), COALESCE(pt.c_gr3,0)),
        (COALESCE(pt.c_co4,0), COALESCE(pt.c_ca4,0), COALESCE(pt.c_gr4,0)),
        (COALESCE(pt.c_co5,0), COALESCE(pt.c_ca5,0),COALESCE(pt.c_gr5,0)),
        (COALESCE(pt.c_co6,0), COALESCE(pt.c_ca6,0), COALESCE(pt.c_gr6,0)),
        (COALESCE(pt.c_co7,0), COALESCE(pt.c_ca7,0), COALESCE(pt.c_gr7,0)),
        (COALESCE(pt.c_co8,0), COALESCE(pt.c_ca8,0), COALESCE(pt.c_gr8,0)),
        (COALESCE(pt.c_co9,0), COALESCE(pt.c_ca9,0), COALESCE(pt.c_gr9,0)),
        (COALESCE(pt.c_co10,0), COALESCE(pt.c_ca10,0), COALESCE(pt.c_gr10,0)),
        (COALESCE(pt.c_co11,0), COALESCE(pt.c_ca11,0), COALESCE(pt.c_gr11,0)),
        (COALESCE(pt.c_co12,0), COALESCE(pt.c_ca12,0), COALESCE(pt.c_gr12,0)),
        (COALESCE(pt.c_co13,0), COALESCE(pt.c_ca13,0), COALESCE(pt.c_gr13,0)),
        (COALESCE(pt.c_co14,0), COALESCE(pt.c_ca14,0), COALESCE(pt.c_gr14,0)),
        (COALESCE(pt.c_co15,0), COALESCE(pt.c_ca15,0), COALESCE(pt.c_gr15,0))
    ) AS esp(c_co,c_ca,c_gr)
    LEFT JOIN compon com ON esp.c_co=com.co_clave::integer
    WHERE com.co_grupo > '0'
    ORDER BY mov.s_lin,mov.s_clave,com.co_grupo;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Convierte gramos y piezas a valor mn
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE detalle AS
    SELECT M.s_lin,M.s_clave,E.c_co,
      E.co_grupo,E.co_facmaq,E.co_l1p,
      M.saldo_piezas*E.c_ca AS pzas_item,
      CASE
      WHEN E.co_grupo='2' 
        THEN M.saldo_piezas*E.c_ca*E.co_l1p
      ELSE 0
      END AS pzas_importe,
      CASE
      WHEN E.co_grupo='1' AND (E.c_co<>1 AND E.c_co<>95)
        THEN M.saldo_gramos*E.co_facmaq
      ELSE 0
      END AS gramos_oro,
      CASE
      WHEN E.co_grupo='1' AND (E.c_co=1 OR E.c_co=95)
        THEN M.saldo_gramos*E.co_facmaq
      ELSE 0
      END AS gramos_plata
    FROM movinvpt M
    LEFT JOIN especif E ON M.s_lin=E.s_lin AND M.s_clave=E. s_clave;
    ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();


  # Resumen de piedra plata y oro
  # ----------------------------------------------------------------------------
  $sqlCmd = "SELECT SUM(pzas_importe) AS pzas_importe,
    SUM(gramos_plata) AS gramos_plata,SUM(gramos_oro) AS gramos_oro
    FROM detalle    
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrPT = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  # Agrega registro a la tabla que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $pt_mn       = 0.00;
  $pt_usd      = 0.00;
  $pt_usd_conv = 0.00;
  $pt_oro      = 0.00;
  $pt_oro_conv = 0.00;
  $pt_plata    = 0.00;
  $pt_plata_conv = 0.00;

  $numFilas = count($arrPT);
  if ($numFilas > 0) {

    $pt_mn = $arrPT[0]["pzas_importe"];
    $pt_oro = $arrPT[0]["gramos_oro"];
    $pt_oro_conv = round($arrPT[0]["gramos_oro"] * $TipoCambioORO, 2);
    $pt_plata = $arrPT[0]["gramos_plata"];
    $pt_plata_conv = round($arrPT[0]["gramos_plata"] * $TipoCambioPLATA, 2);
  }
  $pt_total_fila = round($pt_mn + $pt_usd_conv + $pt_oro_conv + $pt_plata_conv, 2);

  $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Activos','ACTIVO CIRCULANTE','Inventario PT',$pt_mn,
      $pt_usd,$pt_usd_conv,$pt_oro,$pt_oro_conv,$pt_plata,$pt_plata_conv,
      $pt_total_fila)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
}

/**
 * Obtiene datos para el rubro "Inv en Transito"
 */
function InvTransito(
  pdo $conn,
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA
) {

  # Es necesario consolidar los códigos de artículo en cada documento de entrada y salida,
  # aprovechamos para obtener todas las entradas y salidas 
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE tr_salidas AS
    SELECT mo_serie,mo_doc,mo_mov,mo_lin,mo_clave,
      SUM(mo_can) AS mo_can,
      SUM(mo_pzas) AS mo_pzas
    FROM inv070
    WHERE mo_mov='13' AND mo_fecdo<=:FechaCorte
    GROUP BY mo_serie,mo_doc,mo_mov,mo_lin,mo_clave;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();

  $sqlCmd = "CREATE TEMPORARY TABLE tr_entradas AS
    SELECT mo_serie,mo_doc,mo_mov,mo_lin,mo_clave,
      SUM(mo_can) AS mo_can,
      SUM(mo_pzas) AS mo_pzas
    FROM inv070
    WHERE mo_mov='03' AND mo_fecdo<=:FechaCorte
    GROUP BY mo_serie,mo_doc,mo_mov,mo_lin,mo_clave;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();

  # Obtiene salidas en tránsito (piezas que no han sido recibidas)
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE transito AS
    SELECT s.mo_serie,s.mo_doc,s.mo_lin,s.mo_clave,
        s.mo_pzas AS pzas_sal,s.mo_can AS can_sal,
      e.mo_pzas AS pzas_ent,e.mo_can AS can_ent
    FROM tr_salidas s
    LEFT JOIN tr_entradas e 
      ON s.mo_serie=e.mo_serie AND s.mo_doc=e.mo_doc AND s.mo_lin=e.mo_lin AND s.mo_clave=e.mo_clave
    WHERE s.mo_mov = '13'         -- Filtra solo por movimientos de salida por traspaso
      AND (e.mo_clave IS NULL);   -- Filtra lo que NO tiene correspondencia en destino
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Lista de artículos con diferencias
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE tr_diff AS
    SELECT ts.mo_serie,ts.mo_doc,ts.mo_lin,ts.mo_clave,
      ts.mo_can AS can_sal,ts.mo_pzas AS pzas_sal,
      COALESCE(te.mo_can,0) AS can_ent, COALESCE(te.mo_pzas,0) AS pzas_ent,
      (ts.mo_can + COALESCE(te.mo_can,0)) AS diff_can,
      (ts.mo_pzas + COALESCE(te.mo_pzas,0)) AS diff_pzas
    FROM tr_salidas ts
    INNER JOIN tr_entradas te ON ts.mo_serie=te.mo_serie AND ts.mo_doc=te.mo_doc AND ts.mo_lin=te.mo_lin AND ts.mo_clave=te.mo_clave
    WHERE ts.mo_mov='13'
      AND (ts.mo_pzas + COALESCE(te.mo_pzas,0)) <> 0 OR (ts.mo_can + COALESCE(te.mo_can)) <> 0;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Une articulos en trasito con diferencias
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE trans_union AS
    SELECT mo_lin,mo_clave,ABS(pzas_sal) AS saldo_piezas,ABS(can_sal) AS saldo_gramos FROM transito
    UNION ALL
    SELECT mo_lin,mo_clave,(diff_pzas) diff_pzas,(diff_can) diff_can FROM tr_diff;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Esta técnica permite "despivotar" (convertir columnas a filas) dinámicamente para cada fila de la tabla padre,
  # lo cual es mucho más performante y escalable que hacer un UNION ALL manual si tienes muchos registros.
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE comp_especif AS
    SELECT tr.mo_lin,tr.mo_clave,
      esp.c_co, esp.c_ca, esp.c_gr,
      com.co_facmaq,com.co_grupo,com.co_l1p
    FROM trans_union tr
    INNER JOIN inv010 pt ON tr.mo_lin=pt.c_lin AND tr.mo_clave=pt.c_clave
    CROSS JOIN LATERAL (
      VALUES
        (COALESCE(pt.c_co1,0), COALESCE(pt.c_ca1,0), COALESCE(pt.c_gr1,0)),
        (COALESCE(pt.c_co2,0), COALESCE(pt.c_ca2,0), COALESCE(pt.c_gr2,0)),
        (COALESCE(pt.c_co3,0), COALESCE(pt.c_ca3,0), COALESCE(pt.c_gr3,0)),
        (COALESCE(pt.c_co4,0), COALESCE(pt.c_ca4,0), COALESCE(pt.c_gr4,0)),
        (COALESCE(pt.c_co5,0), COALESCE(pt.c_ca5,0),COALESCE(pt.c_gr5,0)),
        (COALESCE(pt.c_co6,0), COALESCE(pt.c_ca6,0), COALESCE(pt.c_gr6,0)),
        (COALESCE(pt.c_co7,0), COALESCE(pt.c_ca7,0), COALESCE(pt.c_gr7,0)),
        (COALESCE(pt.c_co8,0), COALESCE(pt.c_ca8,0), COALESCE(pt.c_gr8,0)),
        (COALESCE(pt.c_co9,0), COALESCE(pt.c_ca9,0), COALESCE(pt.c_gr9,0)),
        (COALESCE(pt.c_co10,0), COALESCE(pt.c_ca10,0), COALESCE(pt.c_gr10,0)),
        (COALESCE(pt.c_co11,0), COALESCE(pt.c_ca11,0), COALESCE(pt.c_gr11,0)),
        (COALESCE(pt.c_co12,0), COALESCE(pt.c_ca12,0), COALESCE(pt.c_gr12,0)),
        (COALESCE(pt.c_co13,0), COALESCE(pt.c_ca13,0), COALESCE(pt.c_gr13,0)),
        (COALESCE(pt.c_co14,0), COALESCE(pt.c_ca14,0), COALESCE(pt.c_gr14,0)),
        (COALESCE(pt.c_co15,0), COALESCE(pt.c_ca15,0), COALESCE(pt.c_gr15,0))
    ) AS esp(c_co,c_ca,c_gr)
    LEFT JOIN compon com ON esp.c_co=com.co_clave::integer
    WHERE com.co_grupo <> '0'
    ORDER BY tr.mo_lin,tr.mo_clave,com.co_grupo;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Convierte gramos y piezas a valor mn
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE det_tran AS
    SELECT M.mo_lin,M.mo_clave,E.c_co,
      E.co_grupo,E.co_facmaq,E.co_l1p,
      M.saldo_piezas*E.c_ca AS pzas_item,
      CASE
      WHEN E.co_grupo='2' 
        THEN M.saldo_piezas*E.c_ca*E.co_l1p
      ELSE 0
      END AS pzas_importe,
      CASE
      WHEN E.co_grupo='1' AND (E.c_co<>1 AND E.c_co<>95)
        THEN M.saldo_gramos*E.co_facmaq
      ELSE 0
      END AS gramos_oro,
      CASE
      WHEN E.co_grupo='1' AND (E.c_co=1 OR E.c_co=95)
        THEN M.saldo_gramos*E.co_facmaq
      ELSE 0
      END AS gramos_plata
    FROM trans_union M
    LEFT JOIN comp_especif E ON M.mo_lin=E.mo_lin AND M.mo_clave=E. mo_clave;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Resumen de piedra, plata y oro
  # ----------------------------------------------------------------------------
  $sqlCmd = "SELECT 
    SUM(pzas_importe) AS pzas_importe,
    SUM(gramos_plata) AS gramos_plata,
    SUM(gramos_oro) AS gramos_oro
    FROM det_tran
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrTRANS = $oSQL->fetchAll(PDO::FETCH_ASSOC);


  # Agrega registro a la tabla que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $tr_mn       = 0.00;
  $tr_usd      = 0.00;
  $tr_usd_conv = 0.00;
  $tr_oro      = 0.00;
  $tr_oro_conv = 0.00;
  $tr_plata    = 0.00;
  $tr_plata_conv = 0.00;

  $numFilas = count($arrTRANS);
  if ($numFilas > 0) {

    $tr_mn       = $arrTRANS[0]["pzas_importe"];
    $tr_oro      = $arrTRANS[0]["gramos_oro"];
    $tr_oro_conv = round($arrTRANS[0]["gramos_oro"] * $TipoCambioORO, 2);
    $tr_plata    = $arrTRANS[0]["gramos_plata"];
    $tr_plata_conv = round($arrTRANS[0]["gramos_plata"] * $TipoCambioPLATA, 2);
  }
  $tr_total_fila = round($tr_mn + $tr_usd_conv + $tr_oro_conv + $tr_plata_conv, 2);

  $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Activos','ACTIVO CIRCULANTE','Inv en Transito',$tr_mn,
      $tr_usd,$tr_usd_conv,$tr_oro,$tr_oro_conv,$tr_plata,$tr_plata_conv,
      $tr_total_fila)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene datos para el rubro "Ctas por Cobrar"
 */
function CtasCobrar(
  pdo $conn,
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA

) {

  # Totaliza por codigo de cartera 1=mn | 2=oro | 3= usd
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE cart_cxc AS
    SELECT 
      SUM(
      CASE
        WHEN SUBSTR(tc.t_param,1,1)='1' THEN sc.sc_imp + sc.sc_iva
      ELSE 0
      END 
      ) AS total_mn,
      SUM(
      CASE
        WHEN SUBSTR(tc.t_param,1,1)='2' THEN sc.sc_imp + sc.sc_iva
        ELSE 0
      END
      ) AS total_oro,
      SUM(
      CASE
        WHEN SUBSTR(tc.t_param,1,1)='3' THEN sc.sc_imp + sc.sc_iva
      ELSE 0
      END
      ) AS total_usd
    FROM cli020 sc 
    LEFT JOIN var020 tc ON tc.t_tica='10' AND tc.t_gpo='88' AND tc.t_clave=sc.sc_tica
    WHERE sc_feex <= :FechaCorte        -- Fecha de corte
      AND SUBSTRING(tc.t_param,3,1)<>'1'; -- 1=cobranza judicial
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();


  # Resumen mn, oro, usd
  # ----------------------------------------------------------------------------
  $sqlCmd = "SELECT 
    SUM(total_mn) AS total_mn,
    SUM(total_oro) AS total_oro,
    SUM(total_usd) AS total_usd
    FROM cart_cxc
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  $arrCXC = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  # Agrega registro a la tabla que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $cxc_mn       = 0.00;
  $cxc_usd      = 0.00;
  $cxc_usd_conv = 0.00;
  $cxc_oro      = 0.00;
  $cxc_oro_conv = 0.00;
  $cxc_plata    = 0.00;
  $cxc_plata_conv = 0.00;

  $numFilas = count($arrCXC);
  if ($numFilas > 0) {

    $cxc_mn       = $arrCXC[0]["total_mn"];
    $cxc_oro      = $arrCXC[0]["total_oro"];
    $cxc_oro_conv = round($arrCXC[0]["total_oro"] * $TipoCambioORO, 2);
    $cxc_usd      = $arrCXC[0]["total_usd"];
    $cxc_usd_conv = round($arrCXC[0]["total_usd"] * $TipoCambioUSD, 2);
  }
  $cxc_total_fila = round($cxc_mn + $cxc_usd_conv + $cxc_oro_conv + $cxc_plata_conv, 2);

  $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Activos','ACTIVO CIRCULANTE','Ctas por Cobrar',$cxc_mn,
      $cxc_usd,$cxc_usd_conv,$cxc_oro,$cxc_oro_conv,$cxc_plata,$cxc_plata_conv,
      $cxc_total_fila)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene datos para el rubro "Bancos"
 */
function Bancos(
  pdo $conn,
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA
) {

  # Por el diseño de datos de proeli, se tienen por separado los movimientos de cuentas
  # fiscales y "de prueba" (remisiones), por lo que deben  unirse para poder incluir todos 
  # los movimientos en el reporte
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE bancos_deta AS
    SELECT * FROM ach020
    UNION ALL
    SELECT * FROM ach020p;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  $sqlCmd = "CREATE INDEX idx_bancosdeta_cta ON bancos_deta (sa_cta)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Obtiene total de cargos y abonos a la fecha de corte para agregarlos al saldo inicial
  # y obtener asi el saldo a la fecha indicada.
  # Desglos por cuenta bancaria para permitir una revisión en caso necesario.
  # ----------------------------------------------------------------------------
  $sql = "CREATE TEMPORARY TABLE bancos AS
    SELECT a.cu_cta, MAX(a.cu_tica) tipo_cartera, MAX(a.cu_nom) nom, MAX(a.cu_ant) saldo_inic,
      SUM(b.sa_impor) AS cargos_abonos,
      SUM(
      CASE WHEN a.cu_tica='1' THEN b.sa_impor ELSE 0 END ) AS movim_mn,
      SUM(
      CASE WHEN a.cu_tica='2' THEN b.sa_impor ELSE 0 END ) AS movim_oro,
      SUM(
      CASE WHEN a.cu_tica='3' THEN b.sa_impor ELSE 0 END ) AS movim_usd, 
      (MAX(a.cu_ant) + SUM(b.sa_impor)) AS saldo_corte
    FROM ach010 a
    LEFT JOIN bancos_deta b ON a.cu_cta=b.sa_cta
    WHERE b.sa_fecco <= :FechaCorte   -- fecha de corte
    GROUP BY a.cu_cta HAVING (MAX(a.cu_ant) + SUM(b.sa_impor))<>0
    ORDER BY a.cu_cta::INTEGER ;
  ";
  $oSQL = $conn->prepare($sql);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();

  # Totales por tipo de cartera
  # ----------------------------------------------------------------------------
  $sqlCmd = "SELECT 
    SUM(CASE WHEN tipo_cartera='1' THEN saldo_corte ELSE 0 END) AS total_mn,
    SUM(CASE WHEN tipo_cartera='2' THEN saldo_corte ELSE 0 END) AS total_oro,
    SUM(CASE WHEN tipo_cartera='3' THEN saldo_corte ELSE 0 END) AS total_usd 
    FROM bancos;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  $arrBANCOS = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  # Agrega registro a la tabla que se va a enviar al frontend
  # ----------------------------------------------------------------------------
  $bancos_mn       = 0.00;
  $bancos_usd      = 0.00;
  $bancos_usd_conv = 0.00;
  $bancos_oro      = 0.00;
  $bancos_oro_conv = 0.00;
  $bancos_plata    = 0.00;
  $bancos_plata_conv = 0.00;

  $numFilas = count($arrBANCOS);
  if ($numFilas > 0) {
    $bancos_mn       = $arrBANCOS[0]["total_mn"];
    $bancos_oro      = $arrBANCOS[0]["total_oro"];
    $bancos_oro_conv = round($arrBANCOS[0]["total_oro"] * $TipoCambioORO, 2);
    $bancos_usd      = $arrBANCOS[0]["total_usd"];
    $bancos_usd_conv = round($arrBANCOS[0]["total_usd"] * $TipoCambioUSD, 2);
  }
  $bancos_total_fila = round($bancos_mn + $bancos_usd_conv + $bancos_oro_conv + $bancos_plata_conv, 2);

  $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Activos','ACTIVO CIRCULANTE','Bancos',$bancos_mn,
      $bancos_usd,$bancos_usd_conv,$bancos_oro,$bancos_oro_conv,$bancos_plata,$bancos_plata_conv,
      $bancos_total_fila)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
}

/** ****************************************************************************
 * Obtiene datos para los rubros del Pasivo Circulante: Proveedores, Acreedores
 */

function Proveedores(
  pdo $conn,
  string $FechaCorte,
  float $TipoCambioMN,
  float $TipoCambioUSD,
  float $TipoCambioORO,
  float $TipoCambioPLATA
) {
  # Por el diseño de datos de proeli, se tienen por separado los movimientos de cuentas
  # fiscales y "de prueba" (remisiones), por lo que deben unirse para poder incluir todos 
  # los movimientos en el reporte
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE cxp_union AS
    SELECT a.* FROM prov20 a WHERE a.vs_feex <= :FechaCorte
    UNION ALL
    SELECT b.* FROM prov20p b WHERE b.vs_feex <= :FechaCorte;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":FechaCorte", $FechaCorte);
  $oSQL->execute();

  # Tomando como guia el catalogo de proceedores, suma los movimientos de cargo y 
  # abono por tipo de cartera y codigo de cartera
  # Recuerda que anteriormente se aplico el filtro por fecha de corte
  # ----------------------------------------------------------------------------
  $sqlCmd = "CREATE TEMPORARY TABLE prov_cxp AS
    SELECT cxp.vs_tica, MAX(SUBSTR(car.t_param,1,1)) AS codcart, p.pr_tipo,
    MAX(tpr.t_descr) AS descr,
    SUM(cxp.vs_imp) AS importe, SUM(cxp.vs_iva) AS iva,
    SUM(cxp.vs_imp+cxp.vs_iva) AS total
    FROM prov10 p
    INNER JOIN cxp_union cxp ON p.pr_num=cxp.vs_num
    INNER JOIN var020 tpr ON tpr.t_tica='34' AND tpr.t_gpo=p.pr_tipo
    INNER JOIN var020 car ON car.t_tica='10' AND car.t_gpo='89' AND car.t_clave=cxp.vs_tica
    WHERE SUBSTR(tpr.t_param,1,1) <> '0'
    GROUP BY cxp.vs_tica,p.pr_tipo
    ORDER BY cxp.vs_tica,p.pr_tipo;
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Hace un resumen por tipo y codigo de cartera
  $sqlCmd = "CREATE TEMPORARY TABLE prov_carteras AS
    SELECT pr_tipo, MAX(descr) AS descr, codcart, SUM(total) AS total
    FROM prov_cxp 
    WHERE total <> 0
    GROUP BY pr_tipo,codcart
    ORDER BY pr_tipo,codcart
  ";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Acomoda los resultados en la tabla resumen para enviarlos al frontend
  $sqlCmd = "SELECT * FROM prov_carteras";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
  $arrProvCarteras = $oSQL->fetchAll(PDO::FETCH_ASSOC);


  # Acumula totales por grupo de proveedor y codigo de cartera 
  # Las monedas diferentes a mn,oro y usd se convierten a mn
  $prov_mn       = 0.00;
  $prov_usd      = 0.00;
  $prov_usd_conv = 0.00;
  $prov_oro      = 0.00;
  $prov_oro_conv = 0.00;
  $prov_plata    = 0.00;
  $prov_plata_conv = 0.00;

  $pr_tipo = $arrProvCarteras[0]["pr_tipo"];
  $descr   = $arrProvCarteras[0]["descr"];

  foreach ($arrProvCarteras as $row) {
    $codcart = $row["codcart"];
    $total   = $row["total"];

    if ($row["pr_tipo"] != $pr_tipo) {
      $prov_oro_conv = round($prov_oro * $TipoCambioORO, 2);
      $prov_usd_conv = round($prov_usd * $TipoCambioUSD, 2);
      $prov_total_fila = round($prov_mn + $prov_usd_conv + $prov_oro_conv + $prov_plata_conv, 2);

      $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Pasivos','PASIVO CIRCULANTE','{$descr}', $prov_mn,
      $prov_usd,$prov_usd_conv,$prov_oro,$prov_oro_conv,$prov_plata,$prov_plata_conv,
      $prov_total_fila)";
      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->execute();

      $pr_tipo = $row["pr_tipo"];
      $descr   = $row["descr"];
      //$codcart = $row["codcart"];
      //$total   = $row["total"];

      $prov_mn       = 0.00;
      $prov_usd      = 0.00;
      $prov_usd_conv = 0.00;
      $prov_oro      = 0.00;
      $prov_oro_conv = 0.00;
    }
    if ($codcart != '1' && $codcart != '2' && $codcart != '3') {
      $sqlCmd = "SELECT hi_llave, hi_parx FROM inv110 
          WHERE hi_fecha=:FechaCorte AND hi_llave=:codCart";
      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->bindParam(":FechaCorte", $FechaCorte);
      $oSQL->bindParam(":codCart", $codcart);
      $oSQL->execute();
      $arrTipoCambio = $oSQL->fetchAll(PDO::FETCH_ASSOC);
      if (count($arrTipoCambio) > 0) {
        $tipoCambio = $arrTipoCambio[0]["hi_parx"];
        $prov_mn += round($total * $tipoCambio, 2);
      }
    }

    switch ($codcart) {
      case '1':
        $prov_mn += $total;
        break;
      case '2':
        $prov_oro += $total;
        break;
      case '3':
        $prov_usd += $total;
        break;
      default:
        break;
    }
  }   // foreach ($arrProvCarteras as $row)

  // Ultimo registro
  $prov_oro_conv = round($prov_oro * $TipoCambioORO, 2);
  $prov_usd_conv = round($prov_usd * $TipoCambioUSD, 2);
  $prov_total_fila = round($prov_mn + $prov_usd_conv + $prov_oro_conv + $prov_plata_conv, 2);

  $sqlCmd = "INSERT INTO resumen (seccion,secc_descr,rubro,rubro_mn,
      rubro_usd,rubro_usd_conv,oro_gramos,oro_importe,plata_gramos,plata_importe,
      total_fila_mn)
      VALUES ('Pasivos','PASIVO CIRCULANTE','{$descr}', $prov_mn,
      $prov_usd,$prov_usd_conv,$prov_oro,$prov_oro_conv,$prov_plata,$prov_plata_conv,
      $prov_total_fila)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();
}

/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales(PDO $conn)
{

  $tablasTemp = [
    "resumen",
    "catmp",
    "movinv",
    "invmp_metal",
    "invmp_piedra",
    "catpt",
    "movinvpt",
    "especif",
    "detalle",
    "tr_salidas",
    "tr_entradas",
    "tr_diff",
    "trans_union",
    "comp_especif",
    "det_tran",
    "cart_cxc",
    "bancos_deta",
    "bancos",
    "cxp_union",
    "prov_cxp",
    "prov_carteras"
  ];

  foreach ($tablasTemp as $tabla) {
    $dropCmd = "DROP TABLE IF EXISTS " . $tabla;
    $drop = $conn->prepare($dropCmd);
    $drop->execute();
  }
}
