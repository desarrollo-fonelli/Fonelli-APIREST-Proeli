<?php
@session_start();
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Auth');

date_default_timezone_set('America/Mexico_City');

/**
 * Consulta Directiva Comercial.
 * Creación: dRendon 21.05.2026
 * --------------------------------------------------------------------------
 * Las consultas en este script están escritas así para aproximarse al
 * método de Proeli para obtener la información y hacen los posible por
 * corregir los defectos en la integridad de datos y la pésima normalización
 * de sus tablas de datos.
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

    # 1. Crea tabla con movimientos de inventario en el periodo indicado
    # ------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE movinv AS
    SELECT mo_of, mo_tipo, mo_suc,
       mo_lin, mo_clave, mo_mov, mo_timon,
       mo_ticos,mo_costo,mo_vta1,
       mo_fecdo, 
       mo_serie, mo_doc, mo_ref,
       mo_can,mo_pzas,
       mo_cat,mo_scat,
       codmov.t_descr as mo_movdescr,
       SUBSTR(codmov.t_param,20,1) as mo_tica,
       SUBSTR(codmov.t_param,21,2) as mo_movcart,
       codcart.t_descr as mo_descrcart,
       codcart.t_param AS mo_paramcart
     FROM inv040 inv
     LEFT JOIN var020 categ ON categ.t_tica='02' AND categ.t_gpo=inv.mo_cat 
     LEFT JOIN var020 codmov ON codmov.t_tica='10' AND codmov.t_gpo='81' AND codmov.t_clave=inv.mo_mov 
     LEFT JOIN var020 codcart ON codcart.t_tica='10' AND codcart.t_gpo='80' AND codcart.t_clave=SUBSTR(codmov.t_param,21,2)
    WHERE inv.mo_fecdo >= :FechaDesde AND inv.mo_fecdo <= :FechaHasta
      AND SUBSTR(categ.t_param,1,1)='1'
      AND SUBSTR(codmov.t_param,3,1)='1'
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":FechaDesde", $FechaDesde);
    $oSQL->bindParam(":FechaHasta", $FechaHasta);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM movinv";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    # 2. Crear tabla agrupada por documento.
    #    Los códigos en BOOL_AND se refieren a N/C por Devolución
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE movdoc AS
    SELECT mo_serie,mo_doc,
      max(mo_mov) mo_mov,
      max(mo_movdescr) mo_movdescr,
      max(mo_ref) mo_ref, max(mo_fecdo) mo_fecdo,
      SUM(
        CASE  
          WHEN mo_ticos='1' THEN mo_vta1 * mo_pzas * -1
          ELSE mo_vta1 * mo_can * -1
        END
        ) AS importe,
      SUM(
        CASE
          WHEN mo_ticos='1' THEN (mo_vta1 * mo_pzas * -1) - (mo_costo * mo_pzas * -1)
          ELSE (mo_vta1 * mo_can * -1) - (mo_costo * mo_can * -1)
        END
        ) as valor_agregado,        
      MAX(mo_cat) mo_cat, MAX(mo_tica) mo_tica,
      MAX(mo_movcart) mo_movcart,
      MAX(mo_descrcart) mo_descrcart,
      MAX(mo_paramcart) mo_paramcart,
      BOOL_AND(mo_mov IN ('02','20','32','40','52','60','72','80','84','90')) as buscar_ref
    FROM movinv
    GROUP BY mo_serie,mo_doc ORDER BY mo_serie,mo_doc
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM movdoc";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    # 3. Agrega ISHIP por documento de venta o por referencia
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE moviship AS
    SELECT mo_mov,mo_movdescr,
      mo_movcart,mo_descrcart,
      mo_serie, mo_doc, mo_ref, mo_tica,mo_fecdo,
      importe,valor_agregado,
      CASE 
        WHEN SUBSTR(mo_paramcart,1,1)='1' THEN ABS(COALESCE(c.sc_iship,0.00)) * -1
        ELSE ABS(COALESCE(c.sc_iship,0.00))
      END as sc_ishipfac, 
      CASE 
        WHEN SUBSTR(mo_paramcart,1,1)='1' THEN ABS(COALESCE(r.sc_iship,0.00)) * -1
        ELSE ABS(COALESCE(r.sc_iship,0.00))
      END as sc_ishipdev
    FROM movdoc inv
    LEFT JOIN (SELECT DISTINCT ON (sc_serie,sc_apl,sc_tica,sc_mov,sc_feex) * FROM cli020) c 
      ON c.sc_serie=inv.mo_serie AND c.sc_apl=inv.mo_doc 
      AND c.sc_tica=inv.mo_tica AND c.sc_mov=inv.mo_movcart AND c.sc_feex=inv.mo_fecdo
    LEFT JOIN (SELECT DISTINCT ON (sc_serie2,sc_ref,sc_tica,sc_mov,sc_feex) * FROM cli020) r
      ON r.sc_serie2=inv.mo_serie AND r.sc_ref=inv.mo_doc 
      AND r.sc_tica=inv.mo_tica AND r.sc_mov=inv.mo_movcart AND r.sc_feex=inv.mo_fecdo
      AND inv. buscar_ref;
      ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM moviship";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    $sqlCmd = "CREATE TEMPORARY TABLE moviship_resumen AS
      SELECT mo_movcart,MAX(mo_descrcart) as mo_descrcart,
      SUM(sc_ishipfac + sc_ishipdev) as importe_iship
      FROM moviship
      GROUP BY mo_movcart
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM moviship_resumen";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    # 4. Importe de Notas de Bonificación
    #    NOTA: En proeli, el importe y el valor agregado son iguales
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE notasbonif AS
      SELECT fac.f1_mov,codcart.t_descr,
      fac.f1_serie,fac.f1_apl,fac.f1_tica,fac.f1_feex,fac.f1_ref,
      fac.f1_ticam,
      round(fac.f1_imp*fac.f1_ticam,2) AS f1_imp,
      round(fac.f1_iva*fac.f1_ticam, 2) AS f1_iva,
      round((fac.f1_imp + fac.f1_iva)*fac.f1_ticam, 2) AS bonif_importe,
      round(fac.f1_imp*fac.f1_ticam, 2) AS bonif_valoragreg
      FROM fac010 fac 
      LEFT JOIN var020 categ ON categ.t_tica='02' AND categ.t_gpo=fac.f1_cat 
      LEFT JOIN var020 codcart ON codcart.t_tica='10' AND codcart.t_gpo='80' AND codcart.t_clave=fac.f1_mov
      WHERE fac.f1_feex >= :FechaDesde AND fac.f1_feex <= :FechaHasta
        AND fac.f1_mov IN('13','22','33','42','53','73','87')
        AND SUBSTR(categ.t_param,1,1)='1'
        AND SUBSTR(codcart.t_param,3,1)='1'
      ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":FechaDesde", $FechaDesde);
    $oSQL->bindParam(":FechaHasta", $FechaHasta);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM notasbonif";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    $sqlCmd = "CREATE TEMPORARY TABLE notasbonif_resumen AS
      SELECT 'Boni' as grupocodigo, 'Bonificaciones' as grupodescripc,
      f1_mov as doccodigo,
      max(t_descr) as docdescripc,
      SUM(f1_imp) as importe,
      SUM(bonif_valoragreg) as valor_agregado,
      0.00 AS porc_fact,
      false as factbase,
      4 as ordpresent 
      FROM notasbonif 
      GROUP BY grupocodigo,grupodescripc,doccodigo,ordpresent     
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM notasbonif_resumen";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    # 5. Agrupa tabla de movimientos de inv por "concepto de cartera"
    # ------------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE movconc AS
    SELECT mo_movcart,MAX(mo_descrcart) descrcart,
    SUM(
        CASE  
          WHEN mo_ticos='1' THEN mo_vta1 * mo_pzas * -1
          ELSE mo_vta1 * mo_can * -1
        END
        ) AS importe,
    SUM(
        CASE
          WHEN mo_ticos='1' THEN (mo_vta1 * mo_pzas * -1) - (mo_costo * mo_pzas * -1)
          ELSE (mo_vta1 * mo_can * -1) - (mo_costo * mo_can * -1)
        END
        ) as valor_agregado
    FROM movinv 
    GROUP BY mo_movcart
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM movconc";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // # DEBUG END ---------------------------------

    # 6. Consolida claves de cartera y asigna secuencia de presentación
    # --------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE resumen AS
    SELECT 
     CASE 
      WHEN m.mo_movcart IN ('01','41','21') THEN 'Fact'
      WHEN m.mo_movcart IN ('14','34') THEN 'Canc'
      WHEN m.mo_movcart IN ('10','12','52') THEN 'NCre'
      WHEN m.mo_movcart IN ('13','22','53') THEN 'Boni'
      ELSE 'Otros'
     END AS grupocodigo,
     CASE 
      WHEN m.mo_movcart IN ('01','41','21') THEN 'Facturacion'
      WHEN m.mo_movcart IN ('14','34') THEN 'Cancelaciones'
      WHEN m.mo_movcart IN ('10','12','52') THEN 'Notas Credito'
      WHEN m.mo_movcart IN ('13','22','53') THEN 'Bonificaciones'
      ELSE 'Otros'
     END AS grupodescripc,
    m.mo_movcart as doccodigo,
    m.descrcart as docdescripc,
    (m.importe + COALESCE(ish.importe_iship,0)) as importe,
    m.valor_agregado,
    0.00 AS porc_fact,
    CASE
     WHEN m.mo_movcart = '01' THEN true
     ELSE false 
    END as factbase,
    CASE 
      WHEN m.mo_movcart IN ('01','41','21') THEN 1
      WHEN m.mo_movcart IN ('14','34') THEN 2
      WHEN m.mo_movcart IN ('10','12','52') THEN 3
      WHEN m.mo_movcart IN ('13','22','53') THEN 4
      ELSE 9
     END AS ordpresent
    FROM movconc m
    LEFT JOIN moviship_resumen ish ON m.mo_movcart=ish.mo_movcart
    ORDER BY ordPresent, docCodigo";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    # Agrega las bonificaciones a la tabla resumen
    # --------------------------------------------
    $sqlCmd = "INSERT INTO resumen SELECT * FROM notasbonif_resumen";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM resumen";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // exit;
    // # DEBUG END ---------------------------------

    $sqlCmd = "SELECT * FROM resumen ORDER BY ordPresent, docCodigo";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    // # Maquetamos respuesta con datos de prueba <---------------------------------
    // $arrData = [
    //   ['ordPresent' => 1, 'grupoCodigo' => 'Fact', 'grupoDescripc' => 'Facturacion', 'docCodigo' => '01', 'docDescripc' => 'FACTURA M.N.', 'importe' => 28754479.62, 'valor_agregado' => 9011312.50, 'porc_fact' => 100.00],
    //   ['ordPresent' => 1, 'grupoCodigo' => 'Fact', 'grupoDescripc' => 'Facturacion', 'docCodigo' => '41', 'docDescripc' => 'FACTURA MAQ M.N.', 'importe' => 8276259.38, 'valor_agregado' => 1256858.90, 'porc_fact' => 28.78],
    //   ['ordPresent' => 2, 'grupoCodigo' => 'Canc', 'grupoDescripc' => 'Cancelaciones', 'docCodigo' => '14', 'docDescripc' => 'CANCELAC FAC M.N.', 'importe' => -1476345.72, 'valor_agregado' => -470673.46, 'porc_fact' => -5.13],
    //   ['ordPresent' => 3, 'grupoCodigo' => 'NCre', 'grupoDescripc' => 'Notas Credito', 'docCodigo' => '10', 'docDescripc' => 'CANCELAC NOTA/CR', 'importe' => 266587.00, 'valor_agregado' => 65088.49, 'porc_fact' => 0.93],
    //   ['ordPresent' => 3, 'grupoCodigo' => 'NCre', 'grupoDescripc' => 'Notas Credito', 'docCodigo' => '12', 'docDescripc' => 'NOTA/CRED M.N.', 'importe' => -3578274.55, 'valor_agregado' => -1046964.12, 'porc_fact' => -12.44],
    //   ['ordPresent' => 3, 'grupoCodigo' => 'NCre', 'grupoDescripc' => 'Notas Credito', 'docCodigo' => '52', 'docDescripc' => 'NOTA/CRED MAQ M.N.', 'importe' => -177419.38, 'valor_agregado' => -25777.056, 'porc_fact' => -0.62],
    //   ['ordPresent' => 4, 'grupoCodigo' => 'Boni', 'grupoDescripc' => 'Bonificaciones', 'docCodigo' => '22', 'docDescripc' => 'CANC NOTA/BONI M.N.', 'importe' => 183181.42, 'valor_agregado' => 183181.42, 'porc_fact' => 0.64],
    //   ['ordPresent' => 4, 'grupoCodigo' => 'Boni', 'grupoDescripc' => 'Bonificaciones', 'docCodigo' => '13', 'docDescripc' => 'NOTA/BONI M.N.', 'importe' => -642232.25, 'valor_agregado' => -642232.25, 'porc_fact' => -2.23]
    // ];

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

    # Pedidos activos por agente
    # ------------------------------------------------
    $sqlCmd = "SELECT
      SUM(
        CASE
        WHEN p.pe_ticos=2 THEN (p.pe_grape-p.pe_grasu)*p.pe_penep
        ELSE (p.pe_canpe-p.pe_cansu)*p.pe_penep
        END ) AS importe,
      SUM(
        CASE
        WHEN p.pe_ticos=2 THEN (p.pe_grape*(p.pe_penep-p.pe_costo))-(p.pe_grasu*(p.pe_penes-p.pe_costo))
        ELSE (p.pe_canpe*(p.pe_penep-p.pe_costo))-(p.pe_cansu*(p.pe_penes-p.pe_costo))
        END ) AS valor_agregado
    FROM ped100 p 
    LEFT JOIN var020 cat ON cat.t_tica='02' AND cat.t_gpo=p.pe_cat AND cat.t_clave='  '
    WHERE pe_status='A' AND SUBSTR(cat.t_param,1,1) <> '0'
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    // SUM(         NO CHECA CON PROELI... HAY QUE REVISAR
    //   CASE
    //   WHEN p.pe_ticos=2 THEN (p.pe_grape*p.pe_penep)-(p.pe_grasu*p.pe_penes)
    //   ELSE (p.pe_canpe*p.pe_penep)-(p.pe_cansu*p.pe_penes)
    //   END ) AS importe,

    // # DEBUG -------------------------------------
    // $sqlCmd = "SELECT * FROM pedidos";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $resultDebug = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    // $msg = json_encode($resultDebug, JSON_PRETTY_PRINT);
    // file_put_contents('debug.log', "[" . date('H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
    // exit;
    // # DEBUG END ---------------------------------

    // # Maquetamos respuesta con datos de prueba <---------------------------------
    // $arrData = [
    //   ['importe' => 51243046.35, 'costo' => 44011834.99, "valor_agregado" => 11320530.53]
    // ];

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

    # Primero calculo el total que se usa como base para calcular el porcentaje 
    # por documento.
    # -------------------------------------------------------------------------
    $importeBase = 0.00;
    foreach ($data as $row) {
      if ($row['grupocodigo'] == "Fact") {
        $importeBase += floatval($row['importe']);
      }
    }

    # Ahora a recorrer el array para dar forma al JSON
    # -------------------------------------------------------------------------

    $grupoCodigo        = trim($data[0]['grupocodigo']);
    $grupoDescripc      = trim($data[0]['grupodescripc']);
    $grupoImporte       = 0.00;
    $grupoValorAgregado = 0.00;
    $grupoPorcFact      = 0.00;
    $importeFact        = 0.00;

    foreach ($data as $row) {

      //        "OrdPresent" => intval($row['ordPresent']),

      // Cambio de grupo de documentos
      if ($row['grupocodigo'] != $grupoCodigo) {
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

        $grupoCodigo        = trim($row['grupocodigo']);
        $grupoDescripc      = trim($row['grupodescripc']);
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
        "DocCodigo"   => trim($row['doccodigo']),
        "DocDescripc" => trim($row['docdescripc']),
        "DocImporte"  => round($row['importe'], 2),
        "DocValorAgregado" => round($row['valor_agregado'], 2),
        "DocPorcFact" => ($importeBase != 0) ? round(($row['importe'] / $importeBase) * 100, 2) : 0.00
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
      "Costo"         => round(floatval($pedidos[0]['importe']) - floatval($pedidos[0]['valor_agregado']), 2),
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

  $tablasTemp = [
    "movinv",
    "movdoc",
    "moviship",
    "moviship_resumen",
    "notasbonif",
    "notasbonif_resumen",
    "movconc",
    "resumen"
  ];

  foreach ($tablasTemp as $tabla) {
    $dropCmd = "DROP TABLE IF EXISTS " . $tabla;
    $drop = $conn->prepare($dropCmd);
    $drop->execute();
  }
}
