<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista de CFDIs de clientes
 * --------------------------------------------------------------------------
 * dRendon 16.04.2025 
 *  El parámetro "Usuario" ahora es obligatorio
 *  Ahora se recibe el "Token" con caracter obligatorio en los headers de la peticion
 * --------------------------------------------------------------------------
 * En esta versión:
 * El nombre del archivo que se va a descargar se compone del RFC de Fonelli, 
 * el RFC del cliente, la serie y folio del documento
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Configuraciones a nivel aplicación
$arrAppConfig = require_once "../include/appconfig.php";

# Constantes locales
const K_SCRIPTNAME  = "descargarcfdi.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario    = null;     // Tipo de usuario
$Usuario        = null;     // Id del usuario (cliente, agente o gerente)
$Token          = null;     // Token obtenido por el usuario al autenticarse
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$ClienteRfc     = null;     // RFC del cliente
// OjO: los datos del cliente los voy a usar 
// para evitar que el usuario pueda obtener un CFDI que
// no le corresponde
$year            = null;     // Ej: "2025" - carpeta padre se alojan los comprobantes
$mes            = null;     // Ej: "04" - carpeta hija donde se alojan los comprobantes
$fileName       = null;     // Nombre del archivo: [rfc del cliente] + serieDoc + folioDoc
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

# Variables usadas en la rutina
$rfcFonelli     = $arrAppConfig["rfcFonelli"];
$rutaBase       = $arrAppConfig["rutaBaseCfdi"];


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
    if (! in_array($TipoUsuario, ["C", "A", "G"])) {
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

  if (!isset($_GET["ClienteCodigo"])) {
    throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
  } else {
    $ClienteCodigo = $_GET["ClienteCodigo"];
  }

  if (!isset($_GET["ClienteFilial"])) {
    throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
  } else {
    $ClienteFilial = $_GET["ClienteFilial"];
  }

  # dRendon 04.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "C") {
    if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["ClienteRfc"])) {
    throw new Exception("El parametro obligatorio 'ClienteRfc' no fue definido.");
  } else {
    $ClienteRfc = $_GET["ClienteRfc"];
  }

  if (!isset($_GET["year"])) {
    throw new Exception("El parametro obligatorio 'year' no fue definido.");
  } else {
    $year = $_GET["year"];
  }

  if (!isset($_GET["mes"])) {
    throw new Exception("El parametro obligatorio 'mes' no fue definido.");
  } else {
    $mes = $_GET["mes"];
  }

  if (!isset($_GET["fileName"])) {
    throw new Exception("El parámetro obligatorio 'fileName' no fue definido");
  } else {
    $fileName = $_GET["fileName"];

    // Validar que el nombre del archivo sea seguro (evitar inyección de rutas)
    if (!$fileName || preg_match('/[^a-zA-Z0-9._-]/', $fileName)) {
      throw new Exception('Nombre de archivo inválido.');
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
  "ClienteCodigo",
  "ClienteFilial",
  "ClienteRfc",
  "year",
  "mes",
  "fileName",
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

/*
if (isset($_GET["Pedido"])) {
  if ($_GET["Pedido"] > 0) {
    $Pedido = $_GET["Pedido"];
  }
}
  */

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {

  $data = SelectCFDIS(
    $TipoUsuario,
    $Usuario,
    $ClienteCodigo,
    $ClienteFilial,
    $ClienteRfc,
    $rutaBase,
    $rfcFonelli,
    $year,
    $mes,
    $fileName
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
 * @param int $Usuario
 * @param string $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $ClienteRfc
 * @param string $rutaBase
 * @param string $rfcFonelli
 * @param int $year
 * @param string $mes
 * @param string $fileName
 * @return array
 */
function SelectCFDIS(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $ClienteRfc,
  $rutaBase,
  $rfcFonelli,
  $year,
  $mes,
  $fileName
) {
  $where = "";    // Variable para almacenar dinamicamente la clausula WHERE del SELECT

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

  $strClienteCodigo = str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT);
  $strClienteFilial = str_pad($ClienteFilial, 3, " ", STR_PAD_LEFT);

  // se asume que el cliente envia el year y mes en el formato correcto, por ejemplo:
  // "2025", "04"
  $cfdiName = $rutaBase . $year . "/" . $mes . "/" . $rfcFonelli . "_" . $fileName . ".PDF";


  /**
   * 
   * En esta sección se debe verificar que el RFC del cliente sea el que viene en el "fileName"
   * Es necesario hacer una consulta SQL para obtener el RFC guardado en la base de datos.
   * 
   */


  // Doy un plazo de hasta Cinco minutos para completar cada consulta...
  set_time_limit(300);

  $arrData = array();

  // Verificar que el archivo exista
  clearstatcache();

  //  die($cfdiName . " - Inicia file_exist");
  if (!file_exists($cfdiName)) {
    //http_response_code(404);      <-- provoca error fatal, NO generes una excepcion
    $response = [
      "Codigo" => K_API_NODATA,
      "Mensaje" => 'Archivo no encontrado: ' . $cfdiName,
      "Contenido" => []
    ];
    echo json_encode($response);
    exit;
  }


  try {

    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($cfdiName) . '"');
    //header('Content-Disposition: inline; filename="' . filename($cfdiName) . '"');
    header('Content-Length: ' . filesize($cfdiName));
    header('Cache-Control: must-revalidate');
    header('Content-Transfer-Encoding: binary');

    ob_clean();   // limpia buffer de salida
    flush();
    readfile($cfdiName);    // Lee el archivo

    array_push(
      $arrData,
      [
        "ClienteCodigo" => $strClienteCodigo,
        "ClienteFilial" => $strClienteFilial,
        "ClienteRfc"    => $ClienteRfc,
        "CfdiName" => $cfdiName
      ]
    );
  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

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

  $cfdisarray = array();
  $cfdisrow   = array();

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea un array con los nodos requeridos
      $cfdisrow = [
        "CfdiName" => $row["CfdiName"]
      ];

      // Se agrega el array a la seccion "contenido"
      array_push($cfdisarray, $cfdisrow);
    }   // foreach($data as $row)

    $contenido = [
      "ClienteCodigo" => $data[0]["ClienteCodigo"],
      "ClienteFilial" => $data[0]["ClienteFilial"],
      "ClienteRfc"    => $data[0]["ClienteRfc"],
      "Cfdis" => $cfdisarray
    ];
  } // count($data)>0

  return $contenido;
}
