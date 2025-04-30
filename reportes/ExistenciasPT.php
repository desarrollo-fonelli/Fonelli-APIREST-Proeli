<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Existencias de PT desglosado por almacén
 * --------------------------------------------------------------------------
 * Corresponde a la "Consulta de Existencias" de Proeli: PT > Consultas > Existencias
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
const K_SCRIPTNAME  = "ExistenciasPT.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con registros del estado de cuenta
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario  = null;     // Tipo de usuario
$Usuario      = null;     // Id del usuario (cliente, agente o gerente)
$Token        = null;     // Token obtenido por el usuario al autenticarse
$OficinaDesde = null;     // Código Oficina en que se registra el pedido 
$OficinaHasta = null;     // Código Oficina en que se registra el pedido
$LineaPTDesde = null;     // Linea de PT 
$LineaPTHasta = null;     // Linea de PT
$AlmacDesde   = null;     // Almacén de PT
$AlmacHasta   = null;     // Almacén de PT
$SoloExist    = null;     // Solo existencias mayores a cero ('S' | 'N')
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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (! in_array($TipoUsuario, ["C", "A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
    }
    if ($TipoUsuario == "C") {
      throw new Exception("Servicio no disponible cuando 'TipoUsuario' es  '" . $TipoUsuario . "'");
    }
  }

  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  } else {
    $Usuario = $_GET["Usuario"];
  }

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # dRendon 05.05.2023 ********************
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
  # Fin dRendon 05.05.2023 ****************

  if (!isset($_GET["OficinaDesde"])) {
    throw new Exception("El parametro obligatorio 'OficinaDesde' no fue definido.");
  } else {
    $OficinaDesde = $_GET["OficinaDesde"];
  }

  if (!isset($_GET["OficinaHasta"])) {
    throw new Exception("El parametro obligatorio 'OficinaHasta' no fue definido.");
  } else {
    $OficinaHasta = $_GET["OficinaHasta"];
  }

  if (!isset($_GET["LineaPTDesde"])) {
    throw new Exception("El parametro obligatorio 'LineaPTDesde' no fue definido.");
  } else {
    $LineaPTDesde = $_GET["LineaPTDesde"];
  }

  if (!isset($_GET["LineaPTHasta"])) {
    throw new Exception("El parametro obligatorio 'LineaPTHasta' no fue definido.");
  } else {
    $LineaPTHasta = $_GET["LineaPTHasta"];
  }

  if (!isset($_GET["AlmacDesde"])) {
    throw new Exception("El parametro obligatorio 'AlmacDesde' no fue definido.");
  } else {
    $AlmacDesde = $_GET["AlmacDesde"];
  }
  if (!isset($_GET["AlmacHasta"])) {
    throw new Exception("El parametro obligatorio 'AlmacHasta' no fue definido.");
  } else {
    $AlmacHasta = $_GET["AlmacHasta"];
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
  "OficinaDesde",
  "OficinaHasta",
  "LineaPTDesde",
  "LineaPTHasta",
  "AlmacDesde",
  "AlmacHasta",
  "SoloExist",
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
if (isset($_GET["SoloExist"])) {
  $SoloExist = $_GET["SoloExist"];
  if (! in_array($SoloExist, ["S", "N"])) {
    $mensaje = "Valor '" . $SoloExist . "NO permitido para 'SoloExist'";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {

  $data = SelectData(
    $TipoUsuario,
    $OficinaDesde,
    $OficinaHasta,
    $LineaPTDesde,
    $LineaPTHasta,
    $AlmacDesde,
    $AlmacHasta,
    $SoloExist,
    $Pagina
  );

  # Asigna código de respuesta HTTP por default
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
 * @param int $OficinaDesde
 * @param int $OficinaHasta
 * @param string $LineaPTDesde
 * @param string $LineaPTHasta
 * @param string $AlmacDesde
 * @param string $AlmacHasta
 * @param string $Pagina
 * @return array
 */
function SelectData(
  $TipoUsuario,
  $OficinaDesde,
  $OficinaHasta,
  $LineaPTDesde,
  $LineaPTHasta,
  $AlmacDesde,
  $AlmacHasta,
  $SoloExist,
  $Pagina
) {

  # Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $where = "";

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  $OficinaDesde  = str_pad($OficinaDesde, 2, "0", STR_PAD_LEFT);
  $OficinaHasta  = str_pad($OficinaHasta, 2, "0", STR_PAD_LEFT);

  //$strAlmacDesde = $AlmacDesde;
  //$strAlmacHasta = $AlmacHasta;
  $strAlmacDesde = str_replace(' ', '0', $AlmacDesde);
  $strAlmacHasta = str_replace(' ', '0', $AlmacHasta);


  // Construyo dinamicamente la condicion WHERE
  $where = "WHERE 
    ex.s_of >= :OficinaDesde AND ex.s_of <= :OficinaHasta 
    AND ex.s_lin >= :LineaPTDesde
    AND ex.s_lin <= :LineaPTHasta 
    AND concat(ex.s_tipo,REPLACE(ex.s_suc,' ','0')) >= :strAlmacDesde 
    AND concat(ex.s_tipo,REPLACE(ex.s_suc,' ','0')) <= :strAlmacHasta ";

  //  AND concat(ex.s_tipo,ex.s_suc) >= :strAlmacDesde
  //  AND concat(ex.s_tipo,ex.s_suc) <= :strAlmacHasta "

  if (isset($SoloExist)) {
    if ($SoloExist == 'S') {
      $where .= "AND (ex.s_sacp <> 0 OR ex.s_sac <> 0) ";
    }
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
    $sqlCmd = "SELECT ex.s_lin,ex.s_clave,art.c_descr,
    substring(lin.t_param FROM 20 FOR 1) intext,
    ex.s_of,ex.s_tipo,ex.s_suc,alm.t_nom,ex.s_sacp,ex.s_sac
    FROM inv015 ex
    LEFT JOIN inv018 alm ON alm.t_tipo = ex.s_tipo AND alm.t_num = ex.s_suc
    LEFT JOIN inv010 art ON art.c_lin = ex.s_lin AND art.c_clave = ex.s_clave
    LEFT JOIN var020 lin ON lin.t_tica='05' AND lin.t_gpo = ex.s_lin
    $where 
    ORDER BY ex.s_lin,ex.s_clave,ex.s_of,ex.s_tipo,ex.s_suc ";

    //var_dump($sqlCmd);
    //var_dump($strAlmacDesde, $strAlmacHasta);

    // Prepara la consulta SQL
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":LineaPTDesde", $LineaPTDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":LineaPTHasta", $LineaPTHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":strAlmacDesde", $strAlmacDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAlmacHasta", $strAlmacHasta, PDO::PARAM_STR);

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

  // Cierra la conexión 
  $conn = null;

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
        "Linea"       => $row["s_lin"],
        "Clave"       => $row["s_clave"],
        "Descripcion" => $row["c_descr"],
        "Oficina"     => $row["s_of"],
        "Tipo"        => $row["s_tipo"],
        "Almacen"     => $row["s_suc"],
        "Nombre"      => $row["t_nom"],
        "IE"          => $row["intext"],
        "ExPiezas"    => intval($row["s_sacp"]),
        "ExGramos"    => floatval($row["s_sac"])
      );
    }
  }

  return $contenido;
}
