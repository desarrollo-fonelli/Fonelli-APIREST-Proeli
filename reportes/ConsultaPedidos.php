<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista de Pedidos asociados a un cliente
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Constantes locales
const K_SCRIPTNAME  = "consultapedidos.php";

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
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$Status         = null;     // Status del cliente
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME del mensaje
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if(! in_array($TipoUsuario, ["C","A","G"])){
      throw new Exception("Valor '". $TipoUsuario ."' NO permitido para 'TipoUsuario'");
    }
  }

  if (!isset($_GET["ClienteCodigo"])) {
    throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
  } else {
    $ClienteCodigo = $_GET["ClienteCodigo"];
  }

  if (!isset($_GET["ClienteFilial"])) {
    throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
  } else {
    $ClienteFilial = $_GET["ClienteFilial"] ;
  }

} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "ClienteCodigo", "ClienteFilial", 
"Status", "Pagina");

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
if (isset($_GET["Usuario"])) {
  $Usuario = $_GET["Usuario"];
} else {
  if(in_array($TipoUsuario, ["A", "G"])){
    $mensaje = "Debe indicar 'Usuario' cuando 'TipoUsuario' es 'A' o 'G'";    // quité K_SCRIPTNAME del mensaje
    http_response_code(400);  
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;  
  }
}

if (isset($_GET["Status"])) {
  $Status = $_GET["Status"];
  if(! in_array($Status, ["A", "I"]) ){
    $mensaje = "Valor '". $Status. "' NO permitido para 'Status'";
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
  $data = SelectPedidos($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $Status);

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
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $Status
 * @return array
 */
FUNCTION SelectPedidos($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $Status)
{
  $where = "";    // Variable para almacenar dinamicamente la clausula WHERE del SELECT

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  switch($TipoUsuario){
    // Cliente 
    /*
    case "C":     <-- cuando el tipo es "Cliente", no se requiere "Usuario"
      $strUsuario = str_pad($Usuario, 6," ",STR_PAD_LEFT);
      break;
      */

    // Agente
    case "A":
      $strUsuario = str_pad($Usuario, 2," ",STR_PAD_LEFT);
      break;
    // Gerente
    case "G":
      $strUsuario = str_pad($Usuario, 2," ",STR_PAD_LEFT);
      break;      
  }
  
  $strClienteCodigo = str_pad($ClienteCodigo, 6," ",STR_PAD_LEFT);
  $strClienteFilial = str_pad($ClienteFilial, 3," ",STR_PAD_LEFT);

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.pe_num = :strClienteCodigo AND a.pe_fil = :strClienteFilial ";
  
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND a.pe_age = :strUsuario ";
    }

  if(isset($Status)){
    $where .= "AND a.pe_status = :Status ";
  }


  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();
  
  # Instrucción SELECT
  $sqlCmd = "SELECT trim(a.pe_num) pe_num,trim(a.pe_fil) pe_fil,
    trim(c.cc_raso) cc_raso,trim(c.cc_suc) cc_suc,
    a.pe_letra,trim(a.pe_ped) pe_ped,a.pe_of pe_of,a.pe_status,
    a.pe_fepe,a.pe_fecao,a.pe_fecs,a.pe_canpe,a.pe_cansu,a.pe_difcia 
    FROM peda a 
    JOIN cli010 c ON c.cc_num = a.pe_num AND c.cc_fil = a.pe_fil 
    $where 
    ORDER BY a.pe_letra,a.pe_ped";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn-> prepare($sqlCmd);

    $oSQL-> bindParam(":strClienteCodigo", $strClienteCodigo, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClienteFilial", $strClienteFilial, PDO::PARAM_STR);

    if(isset($Status)){
      $oSQL-> bindParam(":Status" , $Status, PDO::PARAM_STR);
    }

    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
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

  $contenido  = array();
  $pedidos    = array();

  if(count($data)>0){

    foreach($data as $row){

      // Se crea un array con los nodos requeridos
      $pedidos = [
          "PedidoLetra" => $row["pe_letra"],
          "PedidoFolio" => $row["pe_ped"],
          "OficinaFonelliCodigo" => $row["pe_of"],
          "Status" => $row["pe_status"],
          "FechaPedido" => $row["pe_fepe"],
          "FechaCancelacion"  => $row["pe_fecao"],
          "FechaSurtido" => $row["pe_fecs"],
          "CantidadPedida"  => $row["pe_canpe"],
          "CantidadSurtida" => $row["pe_cansu"],
          "DiferenciaPedidosSurtido" => $row["pe_difcia"]
        ]
      ;

      // Se agrega el array a la seccion "contenido"
      array_push($contenido, $pedidos);

    }   // foreach($data as $row)

    $contenido = [
      "ClienteCodigo" => $data[0]["pe_num"],
      "ClienteFilial" => $data[0]["pe_fil"],
      "ClienteNombre" => $data[0]["cc_raso"],
      "Sucursal"      => $data[0]["cc_suc"],
      "Pedidos"       => $contenido
    ];
  
  } // count($data)>0
  
  return $contenido; 

}
