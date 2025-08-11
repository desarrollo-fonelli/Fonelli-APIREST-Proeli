<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Indicadores de Venta Acumulada
 * --------------------------------------------------------------------------
 * dRendon 06.08.2025
 * Obtiene las ventas acumuladas en importe bruto y en valor agregado,
 * agrupando por Agente de Ventas.
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "IndicadVentaAcum.php";

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
$AgenteDesde  = null;     // Id del agente inicial
$AgenteHasta  = null;     // Id del agente final
$FechaCorte   = null;     // Fecha de corte para considerar datos de venta
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

  if (!isset($_GET["AgenteDesde"])) {
    throw new Exception("El parametro obligatorio 'AgenteDesde' no fue definido.");
  } else {
    $AgenteDesde = $_GET["AgenteDesde"];
  }

  if (!isset($_GET["AgenteHasta"])) {
    throw new Exception("El parametro obligatorio 'AgenteHasta' no fue definido.");
  } else {
    $AgenteHasta = $_GET["AgenteHasta"];
  }

  # dRendon 04.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "A") {
    if (
      TRIM($AgenteDesde) != $Usuario or
      TRIM($AgenteHasta) != $Usuario
    ) {
      throw new Exception("Error de autenticación");
    }
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
$arrPermitidos = array("TipoUsuario", "Usuario", "AgenteDesde", "AgenteHasta", "FechaCorte", "Pagina");

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
try {
  $data = SelectIndicadores($AgenteDesde, $AgenteHasta, $FechaCorte);

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

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param int $AgenteDesde
 * @param int $AgenteHasta
 * @param string $FechaCorte
 * @return array
 */
function SelectIndicadores($AgenteDesde, $AgenteHasta, $FechaCorte)
{

  $arrData  = array();  // Array que se va a devolver
  $where    = "";       // Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $year     = "";
  $month    = "";

  $strAgenteDesde = str_pad($AgenteDesde, 2, " ", STR_PAD_LEFT);
  $strAgenteHasta = str_pad($AgenteHasta, 2, " ", STR_PAD_LEFT);

  $fecha = date("Y/n/d", strtotime($FechaCorte));
  $fecha_det = explode("/", $fecha);
  $month = $fecha_det[1];
  $year  = $fecha_det[0];
  $fechaInic = date("Y-m-d", strtotime($year . "-" . $month . "-" . "01"));
  $fechaFinal = $FechaCorte;

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

    # Ventas a la fecha 
    # -----------------------------------------------------------------
    #    dRendon 15/07/2022 Por alguna razon no se está aceptando el
    #    paso de parametros, por lo tanto uso las variables 'tal cual'
    $sqlCmd = "CREATE TEMPORARY TABLE indic_ventas AS  
    SELECT e1_age,
    SUM(
    CASE
    WHEN e1_fecha = '" . $fechaFinal . "' THEN e1_va
    ELSE 0
    END ) diaria,

    SUM(e1_va) AS acumulada, 
    
    SUM(
    CASE
    WHEN e1_fecha = '" . $fechaFinal . "' THEN e1_imp
    ELSE 0
    END ) diaria_bruto,

    SUM(e1_imp) AS acumulada_bruto,
    
    max(b.tco_inferi) tco_inferi, max(b.tco_minimo) tco_minimo, 
    max(b.tco_meta) tco_meta, max(b.tco_infers) tco_infers,max(b.tco_minims) tco_minims,
    max(b.tco_metas) tco_metas,max(c.gc_nom) gc_nom
    FROM cli040 a
    LEFT JOIN var035 b ON CONCAT('" . (string)$year . "',LPAD(trim('" . (string)$month . "'),2,' '),e1_age)=CONCAT(b.tco_amo,b.tco_mes,b.tco_age)
    LEFT JOIN var030 c ON e1_age = gc_llave
    INNER JOIN var020 d ON concat('02',e1_cat,'1') = concat(d.t_tica,TRIM(d.t_gpo),SUBSTR(d.t_param,1,1))
    WHERE e1_fecha >= '" . $fechaInic . "' AND e1_fecha <= '" . $fechaFinal . "' 
      AND e1_cat <> 'Z' AND e1_cat <> 'Y'
      AND trim(e1_age) >= trim(:strAgenteDesde) AND trim(e1_age) <= trim(:strAgenteHasta)
    GROUP BY e1_age";

    $oSQL = $conn->prepare($sqlCmd);
    //$oSQL->bindParam(':fechaInic' , $fechaInic, PDO::PARAM_STR);
    //$oSQL->bindParam(':fechaFinal', $fechaFinal, PDO::PARAM_STR);
    //$oSQL->bindParam(":month"     , $month, PDO::PARAM_STR);
    //$oSQL->bindParam(":year"      , $year, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);

    $oSQL->execute();
    $numrows = $oSQL->rowCount();


    # Pedidos activos por agente
    # ------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE indic_pedidos AS
    SELECT pe_age,
    SUM(
      CASE
      WHEN PE_TICOS=2 THEN (PE_GRAPE*(PE_PENEP-PE_COSTO))-(PE_GRASU*(PE_PENES-PE_COSTO))
      ELSE (PE_CANPE*(PE_PENEP-PE_COSTO))-(PE_CANSU*(PE_PENES-PE_COSTO))
      END ) AS pedidos,

    SUM(
      CASE
      WHEN PE_TICOS=2 THEN (PE_GRAPE*(PE_PENEP))-(PE_GRASU*(PE_PENES))
      ELSE (PE_CANPE*(PE_PENEP))-(PE_CANSU*(PE_PENES))
      END ) AS pedidos_bruto

    FROM ped100 WHERE pe_status='A'
    GROUP BY pe_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows = $oSQL->rowCount();


    # Une tablas temporales para crear la tabla final
    # ---------------------------------------------------------------------
    $sqlCmd = "SELECT a.*,b.pedidos,b.pedidos_bruto
    FROM indic_ventas a
    LEFT JOIN indic_pedidos b ON e1_age=pe_age
    ORDER BY a.e1_age";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);
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
function CreaDataCompuesta($data)
{
  $contenido  = array();

  if (count($data) > 0) {

    foreach ($data as $row) {
      array_push(
        $contenido,
        [
          "AgenteCodigo"  => trim($row["e1_age"]),
          "AgenteNombre"  => trim($row["gc_nom"]),
          "ImporteVentas" => [
            "VentaDiaria"       => floatval($row["diaria"]),
            "VentasAcumuladas"  => floatval($row["acumulada"]),

            "DiariaBruto"  => floatval($row["diaria_bruto"]),
            "AcumuladaBruto" => floatval($row["acumulada_bruto"]),

            "LimiteInferior"    => floatval($row["tco_inferi"]),
            "DiferenciaLimiteInferior" => $row["tco_inferi"] - $row["acumulada"],
            "Minimo" => floatval($row["tco_minimo"]),
            "DiferenciaMinimo"  => $row["tco_minimo"] - $row["acumulada"],
            "Meta"   => floatval($row["tco_meta"]),
            "DiferenciaMeta"    => $row["tco_meta"] - $row["acumulada"],
            "ImportePedidos"    => floatval($row["pedidos"]),
            "PedidosBruto"      => floatval($row["pedidos_bruto"])
          ]
        ]
      );
    }
  }

  return $contenido;
}

/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn)
{

  $sqlcmd = "DROP TABLE IF EXISTS indic_ventas";
  $drop = $conn->prepare($sqlcmd);
  $drop->execute();

  $sqlcmd = "DROP TABLE IF EXISTS indic_pedidos";
  $drop = $conn->prepare($sqlcmd);
  $drop->execute();
}

/**
 * Obtiene total de clientes por agente
 */
function cltesAgente($agente, $arraycltesporagente)
{
  $numcltes = 0;
  foreach ($arraycltesporagente as $fila) {
    if (trim($fila["cc_age"]) == trim($agente)) {
      $numcltes = $fila["numcltes"];
      break;
    }
  }
  return $numcltes;
}
