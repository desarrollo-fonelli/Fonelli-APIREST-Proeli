<?php
@session_start();

/**
 * Estos headers solo son efectivos si se indican en el backend,
 * no tiene caso indicarlos en los servicios de angular.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Auth');
header('Content-type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Auth');
  http_response_code(200);
  exit;
}

date_default_timezone_set('America/Mexico_City');

/**
 * Lista de artículos como resultado de una búsqueda de códigos de producto 
 * similares o con errores tipográficos
 * Creación:  17.07.2025 | dRendon
 * --------------------------------------------------------------------------
 * 10.09.2025 | dRendon
 * Se agrega un "campo virtual" para devolver la ruta completa de la imagen
 * del producto, si es que existe.
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Función para cálculo de Precios
require_once "../include/CalcPrec2025.php";

# Rutas de acceso a recursos
$arrAppConfig = require_once "../include/appconfig.php";

# Constantes locales
const K_SCRIPTNAME  = "ArticulosLista.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con registros del estado de cuenta
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario    = null;     // Tipo de usuario
$Usuario        = null;     // Id del usuario (cliente, agente o gerente)
$Token          = null;     // Token obtenido por el usuario al autenticarse
$ItemCode       = null;     // Codigo de modelo, puede ser un código parcial
$MetodoBusqueda = null;     // Algoritmo que se va a usar en la búsqueda
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

$rutaBaseImgPT  = $arrAppConfig["rutaBaseImgPT"];

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
  }

  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  } else {
    $Usuario = $_GET["Usuario"];
  }

  if (!isset($_GET["ItemCode"])) {
    throw new Exception("El parametro obligatorio 'ItemCode' no fue definido.");
  } else {
    $ItemCode = $_GET["ItemCode"];
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


  // Fin comprobación parámetros obligatorios

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "ItemCode",
  "MetodoBusqueda",
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

if (isset($_GET["MetodoBusqueda"])) {
  $MetodoBusqueda = $_GET["MetodoBusqueda"];
} else {
  $MetodoBusqueda = "ILIKE";   // Por omisión, se usa el operador LIKE
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Llama la función que Ejecuta la consulta 
try {

  $data = SelectData(
    $TipoUsuario,
    $Usuario,
    $ItemCode,
    $MetodoBusqueda,
    $Pagina,
    $rutaBaseImgPT
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
    "Mensaje"     => $e->getMessage(),
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => []
  ];
}

$response = json_encode($response);

$conn = null;   // Cierra conexión

echo $response;

return;


/***********************************************************
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 */
function SelectData(
  $TipoUsuario,
  $Usuario,
  $ItemCode,
  $MetodoBusqueda,
  $Pagina,
  $rutaBaseImgPT
) {

  $arrData = array();   // Arreglo para almacenar los datos obtenidos
  $where = "";  // Variable para almacenar dinamicamente la clausula WHERE del SELECT

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  switch ($TipoUsuario) {
    // Cliente 
    /*
    case "C":     <-- cuando el tipo es "Cliente", no se requiere "Usuario"
      $strUsuario = str_pad($Usuario, 6," ",STR_PAD_LEFT);
      break;
      */

    // Agente
    case "A":
      $strUsuario = str_pad($Usuario, 2, " ", STR_PAD_LEFT);
      break;
    // Gerente
    case "G":
      $strUsuario = str_pad($Usuario, 2, " ", STR_PAD_LEFT);
      break;
  }

  $ItemCode = "%" . trim($ItemCode) . "%";

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE itm.c_clave ILIKE :ItemCode ";


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
    $sqlCmd = "SELECT itm.c_lin,itm.c_clave,itm.c_descr,
    CONCAT('{$rutaBaseImgPT}', trim(img.imagen), '.png') AS img_path
    FROM inv010 itm
    LEFT JOIN item_img img ON CONCAT(img.linpt_id,img.item_code) = CONCAT(itm.c_lin,itm.c_clave)
    $where 
    ORDER BY itm.c_lin,itm.c_clave";
    //exit($sqlCmd);

    // Prepara la consulta SQL
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":ItemCode", $ItemCode, PDO::PARAM_STR);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    /* 
      // Preproceso los datos obtenidos para ajustar la extensión de la imagen.
      // Se prefieren imagenes PNG
      if ($numRows > 0) {
        foreach ($arrData as $key => $row) {
          // Verifica si el archivo de imagen existe
          if (file_exists($row["img_path"] . ".png")) {
            // La imagen existe con extension .png
            $arrData[$key]["img_path"] = $row["img_path"] . '.png';
          } elseif (file_exists($row["img_path"] . '.jpg')) {
            // La imagen existe con extension .jpg
            $arrData[$key]["img_path"] = $row["img_path"] . '.jpg';
          } else {
            // La imagen no existe, se asigna valor nulo
            $arrData[$key]["img_path"] = "assets/img/diamante-azul.png";
          }
        }
      }
        OJO: El problema con esta solución es que las imagenes se guardan en una carpeta
       administrada por el sitio web, no están en una carpeta "independiente", es por
       eso que el código para reconocer la externsión se va a manejar en el frontend
    */

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
        "LineaPT"     => $row["c_lin"],
        "ItemCode"    => trim($row["c_clave"]),
        "Descripc"    => trim($row["c_descr"]),
        "ImgPath"     => $row["img_path"]
      );
    }
  }

  return $contenido;
}
