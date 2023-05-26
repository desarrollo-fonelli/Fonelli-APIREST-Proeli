<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista del Catalogo de Agentes de Venta
 * --------------------------------------------------------------------------
 * dRendon 05.05.2023 
 *  El parámetro "Usuario" ahora es obligatorio
 *  Ahora se recibe el "Token" con caracter obligatorio en los headers de la peticion
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "listaagentes.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario    = null;   // Tipo de usuario
$Usuario        = null;   // Id del usuario (cliente, agente, gerente)
$Token          = null;   // Token obtenido por el usuario al autenticarse
$AgenteCodigo   = null;   // Id del agente
$Password       = null;   // Contraseña asignada al agente
$Status         = null;   // Status del agente
$Pagina         = 1;      // Pagina devuelta del conjunto de datos obtenido

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
try {
  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if(! in_array($TipoUsuario, ["C","A","G"])){
      throw new Exception("Valor '". $TipoUsuario ."' NO permitido para 'TipoUsuario'");
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
  
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "AgenteCodigo", "Password", "Status", "Pagina");

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

if (isset($_GET["AgenteCodigo"])) {
  $AgenteCodigo =  $_GET["AgenteCodigo"];
}

if (isset($_GET["Password"])) {
  if(isset($AgenteCodigo)){
    $Password =  $_GET["Password"];
  } else {
    $mensaje = "Si incluye un 'Password', debe indicar el 'AgenteCodigo'.";   // quité K_SCRIPTNAME del mensaje
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }  
}

if($TipoUsuario == "A"){
  if(!isset($AgenteCodigo) OR 
     TRIM($AgenteCodigo) != $Usuario){
      $mensaje = "Error de autenticación";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;  
  }
}

if (isset($_GET["Status"])) {
  $Status = $_GET["Status"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectAgentes($AgenteCodigo, $Password, $Status);

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

$conn = null;   // Cierra conexión

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param int $AgenteCodigo
 * @param string $Password
 * @param string $Status
 * @return array
 */
FUNCTION SelectAgentes($AgenteCodigo, $Password, $Status)
{
  $where = "";

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  if(isset($AgenteCodigo)){
    $strAgenteCodigo = str_pad($AgenteCodigo, 2, " ",STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  // require_once "../db/conexion.php";   <-- el script se leyó previamente
  $conn = DB::getConn();

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();

  # Construyo dinamicamente la ondicion WHERE
  if(isset($AgenteCodigo)){
    $where = "WHERE a.gc_llave = :strAgenteCodigo ";
  }
  if(isset($Password)){
    if($where == ""){
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= " TRIM(a.gc_passw) = :Password ";
  }
  if(isset($Status)){
    if($where == ""){
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= " TRIM(a.gc_status) = :Status ";
  }
  
  $sqlCmd = "SELECT trim(a.gc_llave) gc_llave,trim(a.gc_nom) gc_nom,
    a.gc_status,trim(a.gc_passw) gc_passw,trim(a.gc_ofi) gc_ofi
   FROM var030 a 
   $where
   ORDER BY replace(a.gc_llave,' ','0')";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn-> prepare($sqlCmd);
    if(isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo" , $strAgenteCodigo, PDO::PARAM_STR);
    }
    if(isset($Password)){
      $oSQL-> bindParam(":Password" , $Password, PDO::PARAM_STR);
    }
    if(isset($Status)){
      $oSQL-> bindParam(":Status" , $Status, PDO::PARAM_STR);
    }
    //$oSQL-> bindParam(":provocaerror", "",PDO::PARAM_STR);  usado para pruebas de control de errores

    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);

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
      "AgenteCodigo"  => $row["gc_llave"],
      "AgenteNombre"  => $row["gc_nom"],
      //"Password"      => $row["gc_passw"],
      "Status"        => $row["gc_status"],
      "Oficina"       => $row["gc_ofi"]

    ];  

    // Se agrega el array a la seccion "contenido"
    array_push($contenido, $oFila);

  } // foreach($data as $row)

  return $contenido; 

}
