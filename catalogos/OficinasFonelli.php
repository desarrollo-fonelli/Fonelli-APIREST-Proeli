<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista del Catalogo de Oficinas Fonelli
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Constantes locales
const K_SCRIPTNAME  = "oficinasfonelli.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$Usuario       = null;    // Id del usuario (cliente, agente, gerente)
$OficinaCodigo = null;    // Id de la Oficina
$Pagina        =  1;      // Pagina devuelta del conjunto de datos obtenido

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "GET") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos GET";   // quité K_SCRIPTNAME del mensaje
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje ]);
  exit;
}

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas

/*
    19.may.2022 dRendon: Por ahora el Usuario va a ser un parámetro OPCIONAL

try {
  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  }
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}
*/

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("Usuario", "OficinaCodigo", "Pagina");

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_GET);
foreach($arrParam as $param){
  if(! in_array($param, $arrPermitidos)){
    if(strlen($mensaje) > 1){
      $mensaje .= ", ";
    }
    $mensaje .= $param;
  }  
}
if(strlen($mensaje) > 0){
  $mensaje = "Parametros no reconocidos: ". $mensaje;   // quité K_SCRIPTNAME del mensaje
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
  exit;
}

# Hay que inicializarverificar parametros opcionales y en caso 
# que estos no se indiquen, asignar valores por omisión.
# (dichos valores se definieron al inicio del script, al declarar las variables)
if (isset($_GET["OficinaCodigo"])) {
  $OficinaCodigo = $_GET["OficinaCodigo"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectOficinas($OficinaCodigo);

  # Asigna código de respuesta HTTP 
  http_response_code(200);

  # Compone el objeto JSON que devuelve el endpoint
  $numFilas = count($data);
  $totalPaginas = ceil($numFilas/K_FILASPORPAGINA);

  if($numFilas > 0){
    $codigo = K_API_OK;
    $mensaje = "success";
  } else {
    $codigo = K_API_NODATA;
    $mensaje = "data not found";
  }

  $dataCompuesta = CreaDataCompuesta( $data );

  $response = [
    "Codigo"      => $codigo,
    "Mensaje"     => $mensaje,
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => $dataCompuesta
  ];    

} catch (Exception $e) {
  /*
  $result_array = [
      "result" => $e,
      "error" => $conn->get_last_error(),
      "Code" => -1
  ];
*/
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
 * @param string $OficinaCodigo
 * @return array
 */
FUNCTION SelectOficinas($OficinaCodigo)
{
  $where = "";

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  if(isset($OficinaCodigo)){
    $strOficinaCodigo = str_pad($OficinaCodigo, 2,"0",STR_PAD_LEFT);
  }

  //var_dump($strLineaCodigo);

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.s_dopv = '1' ";
  if(isset($OficinaCodigo)){
    $where .= "AND a.s_llave = :strOficinaCodigo ";
  }

  $sqlCmd = "SELECT trim(a.s_llave) s_llave,trim(a.s_nomsuc) s_nomsuc
   FROM dirsdo a 
   $where 
   ORDER BY s_llave";

  //var_dump($sqlCmd);
  try {
    $oSQL = $conn-> prepare($sqlCmd);

    if(isset($OficinaCodigo)){
      $oSQL-> bindParam(":strOficinaCodigo", $strOficinaCodigo, PDO::PARAM_STR);
    }
    //$oSQL-> bindParam(":provocaerror", "",PDO::PARAM_STR);  usado para pruebas de control de errores

    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;

  }

  $conn = null;   // Cierra conexión

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
FUNCTION CreaDataCompuesta( $data )
{

  $contenido = array();
  $oFila = array();

  foreach($data as $row){

    // Se crea un array con los nodos requeridos
    $oFila = [
      "OficinaCodigo" => $row["s_llave"],
      "OficinaNombre" => $row["s_nomsuc"]
    ];  

    // Se agrega el array a la seccion "contenido"
    array_push($contenido, $oFila);

  } // foreach($data as $row)

  return $contenido; 

}
