<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Artículos incluidos en un documento de venta (facturas, remisiones)
 * --------------------------------------------------------------------------
 * dRendon 20.05.2025
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
const K_SCRIPTNAME  = "DocVentaDetalle.php";

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
$DocSerie       = null;     // Serie del documento de venta
$DocFolio       = null;     // Folio del documento de venta
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME de los mensajes
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (!in_array($TipoUsuario, ["C", "A", "G"])) {
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

  if (!isset($_GET["DocSerie"])) {
    throw new Exception("El parametro obligatorio 'DocSerie' no fue definido.");
  } else {
    $DocSerie = $_GET["DocSerie"];
  }

  if (!isset($_GET["DocFolio"])) {
    throw new Exception("El parametro obligatorio 'DocFolio' no fue definido.");
  } else {
    $DocFolio = $_GET["DocFolio"];
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
  "DocSerie",
  "DocFolio"
);

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_GET);
foreach ($arrParam as $param) {
  if (!in_array($param, $arrPermitidos)) {
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
if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}
*/

# Ejecuta la consulta 
try {
  $data = SelectArticulos($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $DocSerie, $DocFolio);

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
    "Contenido"   => $dataCompuesta
    //"Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina]
  ];
} catch (Exception $e) {
  $response = [
    "Codigo"      => K_API_ERRSQL,
    "Mensaje"     => $conn->get_last_error(),
    "Contenido"   => []
    //"Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina]
  ];
}

$response = json_encode($response);

$conn = null;   // Cierra conexión

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * @param string $TipoUsuario
 * @param string $Usuario
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $DocSerie
 * @param string $DocFolio
 * @return array
 */
function SelectArticulos($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $DocSerie, $DocFolio)
{
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
  $strDocFolio   = str_pad($DocFolio, 6, " ", STR_PAD_LEFT);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.mo_serie = :DocSerie AND a.mo_doc = :strDocFolio ";

  // if (in_array($TipoUsuario, ["A"])) {
  //   // Solo aplica filtro cuando el usuario es un agente
  //   $where .= "AND a.pe_age = :strUsuario ";
  // }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Instrucción SELECT
  # mo_letra + mo_ref = pedido de cliente
  $sqlCmd = "SELECT 
    a.mo_serie,a.mo_doc,a.mo_lin,a.mo_clave,c.c_descr,a.mo_fecdo,
    (a.mo_pzas*-1) mo_pzas, (a.mo_can*-1) mo_can,
    a.mo_letra,a.mo_ref
    FROM inv040 a 
    LEFT JOIN inv010 c ON c.c_lin=a.mo_lin AND c.c_clave=a.mo_clave
    $where 
    ORDER BY a.mo_fecdo";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":DocSerie", $DocSerie, PDO::PARAM_STR);
    $oSQL->bindParam(":strDocFolio", $strDocFolio, PDO::PARAM_STR);

    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);
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

  $contenido = array();
  $articulos = array();
  $articuloRow = array();

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea un array con los nodos requeridos
      $articuloRow = [
        "LinPT"       => $row["mo_lin"],
        "ItemCode"    => trim($row["mo_clave"]),
        "Descripc"    => trim($row["c_descr"]),
        "FechaMovInv" => $row["mo_fecdo"],
        "Piezas"      => intval($row["mo_pzas"]),
        "Gramos"      => floatval($row["mo_can"]),
        "Letra"       => $row["mo_letra"],
        "Pedido"      => $row["mo_ref"]
      ];

      // Se agrega el array a la seccion "contenido"
      array_push($articulos, $articuloRow);
    }   // foreach($data as $row)

    $contenido = [
      "DocSerie" => $data[0]["mo_serie"],
      "DocFolio" => $data[0]["mo_doc"],
      "DocvtaArticulos" => $articulos
    ];
  } // count($data)>0

  return $contenido;
}
