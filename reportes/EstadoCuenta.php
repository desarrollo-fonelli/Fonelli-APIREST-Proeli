<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Estado de Cuenta de Clientes
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Constantes locales
const K_SCRIPTNAME  = "estadocuenta.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con registros del estado de cuenta
$ResumenCartera = [];   // arreglo asociativo con resumen por cartera
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario  = null;     // Tipo de usuario
$Usuario      = null;     // Id del usuario (cliente, agente o gerente)
$ClienteDesde = null;     // Id del cliente inicial
$FilialDesde  = null;     // Filial del cliente inicial
$ClienteHasta = null;     // Id del cliente final
$FilialHasta  = null;     // Filial del cliente final
$CarteraDesde = null;     // Id cartera inicial
$CarteraHasta = null;     // Id cartera final
$Pagina       = 1;        // Pagina devuelta del conjunto de datos obtenido

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

  if (!isset($_GET["ClienteDesde"])) {
    throw new Exception("El parametro obligatorio 'ClienteDesde' no fue definido.");
  } else {
    $ClienteDesde = $_GET["ClienteDesde"];
  }

  if (!isset($_GET["FilialDesde"])) {
    throw new Exception("El parametro obligatorio 'FilialDesde' no fue definido.");
  } else {
    $FilialDesde = $_GET["FilialDesde"] ;
  }

  if (!isset($_GET["ClienteHasta"])) {
    throw new Exception("El parametro obligatorio 'ClienteHasta' no fue definido.");
  } else {
    $ClienteHasta = $_GET["ClienteHasta"];
  }

  if (!isset($_GET["FilialHasta"])) {
    throw new Exception("El parametro obligatorio 'FilialHasta' no fue definido.");
  } else {
    $FilialHasta = $_GET["FilialHasta"] ;
  }

  if (!isset($_GET["CarteraDesde"])) {
    throw new Exception("El parametro obligatorio 'CarteraDesde' no fue definido.");
  } else {
    $CarteraDesde = $_GET["CarteraDesde"];
  }
  
  if (!isset($_GET["CarteraHasta"])) {
    throw new Exception("El parametro obligatorio 'CarteraHasta' no fue definido.");
  } else {
    $CarteraHasta = $_GET["CarteraHasta"] ;
  }
  
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}


# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "ClienteDesde", "FilialDesde",
"ClienteHasta", "FilialHasta", "CarteraDesde", "CarteraHasta", "Pagina");

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

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectEdoCta($TipoUsuario, $Usuario, $ClienteDesde, $FilialDesde, 
  $ClienteHasta, $FilialHasta, $CarteraDesde, $CarteraHasta);

  $ResumenCartera = SelectResumenCartera($TipoUsuario, $Usuario, $ClienteDesde, $FilialDesde, 
  $ClienteHasta, $FilialHasta, $CarteraDesde, $CarteraHasta);

  $ResumenStatusClte = SelectResumenStatusClte($TipoUsuario, $Usuario);

  $ResumenTipoClte = SelectResumenTipoClte($TipoUsuario, $Usuario);

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

  $dataCompuesta = CreaDataCompuesta( $data, $ResumenCartera, $ResumenStatusClte, $ResumenTipoClte );

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
 * @param int $ClienteDesde
 * @param int $FilialDesde
 * @param int $ClienteHasta
 * @param int $FilialHasta
 * @param int $CarteraDesde
 * @param int $CarteraHasta
 * @return array
 */
FUNCTION SelectEdoCta($TipoUsuario, $Usuario, $ClienteDesde, $FilialDesde, $ClienteHasta, $FilialHasta, $CarteraDesde, $CarteraHasta)
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
  
  $strClteInic      = str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT);
  $strClteFinal     = str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT);
  $strCarteraDesde  = str_pad($CarteraDesde, 1, " ", STR_PAD_RIGHT);
  $strCarteraHasta  = str_pad($CarteraHasta, 1, " ", STR_PAD_RIGHT);

  //var_dump($strClienteDesde,$strFilialDesde,$strClienteHasta,$strFilialHasta,$strCarteraDesde,$strCarteraHasta);
  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE CONCAT(a.sc_num,a.sc_fil) >= :strClteInic AND CONCAT(a.sc_num,a.sc_fil) <= :strClteFinal 
  AND a.sc_tica >= :strCarteraDesde AND a.sc_tica <= :strCarteraHasta 
  AND SUBSTRING(b.t_param,2,1) = '1' ";
  
  if(in_array($TipoUsuario, ["A"])){
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND a.sc_age = :strUsuario ";
  }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();
    
  # Instrucción SELECT para documentos en estado de cuenta
  $sqlCmd = "SELECT trim(a.sc_num) sc_num,trim(a.sc_fil) sc_fil,
    a.sc_of, a.sc_tica, trim(a.sc_serie) sc_serie, trim(a.sc_apl) sc_apl, 
    a.sc_feex, a.sc_feve, 
    a.sc_cargos, a.sc_abonos, a.sc_saldo, a.sc_dias, a.sc_saldove,
    trim(a.sc_serie2) sc_serie2, trim(a.sc_ref) sc_ref, a.sc_age, 
    trim(c.cc_raso) cc_raso,trim(c.cc_suc) cc_suc, c.cc_status,
    TRIM(b.t_descr) t_descr 
    FROM edocta a
    LEFT JOIN var020 b ON t_tica='10' AND t_gpo='88' AND t_clave=sc_tica
    LEFT JOIN cli010 c ON c.cc_num = a.sc_num AND c.cc_fil = a.sc_fil 
    $where ORDER BY a.sc_num,a.sc_fil,a.sc_tica,a.sc_of,a.sc_serie,a.sc_apl ";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCarteraDesde", $strCarteraDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCarteraHasta", $strCarteraHasta, PDO::PARAM_STR);

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
    exit;
  }

  $conn = null;   // Cierra la conexión 

  # Falta tener en cuenta la paginacion
  return $arrData;

}

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $ClienteDesde
 * @param int $FilialDesde
 * @param int $ClienteHasta
 * @param int $FilialHasta
 * @param int $CarteraDesde
 * @param int $CarteraHasta
 * @return array
 */
FUNCTION SelectResumenCartera($TipoUsuario, $Usuario, $ClienteDesde, $FilialDesde, 
  $ClienteHasta, $FilialHasta, $CarteraDesde, $CarteraHasta)

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
    
    $strClteInic      = str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT);
    $strClteFinal     = str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT);
    $strCarteraDesde  = str_pad($CarteraDesde, 1, " ", STR_PAD_RIGHT);
    $strCarteraHasta  = str_pad($CarteraHasta, 1, " ", STR_PAD_RIGHT);
  
    //var_dump($strClienteDesde,$strFilialDesde,$strClienteHasta,$strFilialHasta,$strCarteraDesde,$strCarteraHasta);
    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  <-- el script se leyó previamente
    $conn = DB::getConn();
  
    # Construyo dinamicamente la condicion WHERE
    $where = "WHERE CONCAT(a.sc_num,a.sc_fil) >= :strClteInic AND CONCAT(a.sc_num,a.sc_fil) <= :strClteFinal 
    AND a.sc_tica >= :strCarteraDesde AND a.sc_tica <= :strCarteraHasta 
    AND a.sc_saldo<>0 AND SUBSTRING(b.t_param,2,1)='1' ";
  
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND a.sc_age = :strUsuario ";
    }
  
    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();
      
    # Instrucción SELECT para documentos en estado de cuenta
    $sqlCmd = "SELECT a.sc_tica,TRIM(b.t_descr) t_descr,sum(a.sc_cargos) sum_cargos, 
      sum(a.sc_abonos) sum_abonos,sum(a.sc_saldo) sum_saldo,min(a.sc_dias) min_dias,
      sum(a.sc_saldove) sum_saldove 
      FROM edocta a
      LEFT JOIN var020 b ON t_tica='10' AND t_gpo='88' AND t_clave=sc_tica
      LEFT JOIN cli010 c ON c.cc_num = a.sc_num AND c.cc_fil = a.sc_fil 
      $where GROUP BY a.sc_tica,t_descr ";
  
    //var_dump($sqlCmd);
  
    try {
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
      $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
      $oSQL-> bindParam(":strCarteraDesde", $strCarteraDesde, PDO::PARAM_STR);
      $oSQL-> bindParam(":strCarteraHasta", $strCarteraHasta, PDO::PARAM_STR);
  
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
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @return array
 */
FUNCTION SelectResumenStatusClte($TipoUsuario, $Usuario)

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
 
    //var_dump($strClienteDesde,$strFilialDesde,$strClienteHasta,$strFilialHasta,$strCarteraDesde,$strCarteraHasta);
    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  <-- el script se leyó previamente
    $conn = DB::getConn();
  
    # Construyo dinamicamente la condicion WHERE    
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "WHERE cc_age = :strUsuario ";
    }
   
    try {
      # Hay que definir dinamicamente el schema <---------------------------------
      $sqlCmd = "SET SEARCH_PATH TO dateli;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();

      # Borra tabla temporal en caso de que exista
      $sqlCmd = "DROP TABLE IF EXISTS tmpCli010;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();

      # Crea tabla temporal con clientes "efectivos", descartando filiales
      $sqlCmd = "CREATE TEMPORARY TABLE tmpCli010 AS
      SELECT cc_num,cc_status FROM cli010 $where GROUP BY cc_num,cc_status";

      $oSQL2 = $conn-> prepare($sqlCmd);
      //var_dump($strUsuario);
      if($TipoUsuario == "A"){
        $oSQL2-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
      }
      $oSQL2-> execute();

      # Instrucción SELECT para obtener resumen por status del cliente
      $sqlCmd = "SELECT cc_status,count(*) numcltes FROM tmpCli010 GROUP BY cc_status ORDER BY cc_status;";    
 
      $oSQL3 = $conn-> prepare($sqlCmd);
      $oSQL3-> execute();
      $numRows = $oSQL3->rowCount();      
      $arrData = $oSQL3->fetchAll(PDO::FETCH_ASSOC);

      # Borra tabla temporal en caso de que se haya creado
      $sqlCmd = "DROP TABLE IF EXISTS tmpCli010;";
      $oSQL4 = $conn-> prepare($sqlCmd);
      $oSQL4-> execute();
  
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
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @return array
 */
FUNCTION SelectResumenTipoClte($TipoUsuario, $Usuario)

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
 
    //var_dump($strClienteDesde,$strFilialDesde,$strClienteHasta,$strFilialHasta,$strCarteraDesde,$strCarteraHasta);
    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  <-- el script se leyó previamente
    $conn = DB::getConn();
  
    # Construyo dinamicamente la condicion WHERE    
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "WHERE cc_age = :strUsuario ";
    }
   
    try {
      # Hay que definir dinamicamente el schema <---------------------------------
      $sqlCmd = "SET SEARCH_PATH TO dateli;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();

      # Borra tabla temporal en caso de que exista
      $sqlCmd = "DROP TABLE IF EXISTS tmpCli010;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();

      # Crea tabla temporal con clientes "efectivos", descartando filiales
      $sqlCmd = "CREATE TEMPORARY TABLE tmpCli010 AS
      SELECT cc_num,cc_ticte FROM cli010 $where GROUP BY cc_num,cc_ticte";

      $oSQL2 = $conn-> prepare($sqlCmd);
      if($TipoUsuario == "A"){
        $oSQL2-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
      }
      $oSQL2-> execute();

      # Instrucción SELECT para obtener resumen por status del cliente
      $sqlCmd = "SELECT cc_ticte,count(cc_ticte) numcltes FROM tmpCli010 GROUP BY cc_ticte ORDER BY cc_ticte;";  
 
      $oSQL3 = $conn-> prepare($sqlCmd);
      $oSQL3-> execute();
      $numRows = $oSQL3->rowCount();      
      $arrData = $oSQL3->fetchAll(PDO::FETCH_ASSOC);

      # Borra tabla temporal en caso de que se haya creado
      $sqlCmd = "DROP TABLE IF EXISTS tmpCli010;";
      $oSQL4 = $conn-> prepare($sqlCmd);
      $oSQL4-> execute();
  
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
 FUNCTION CreaDataCompuesta( $data, $ResumenCartera, $ResumenStatusClte, $ResumenTipoClte)
{

  $contenido        = array();
  $arrClientes      = array();
  $arrTiposCartera  = array();
  $arrMovimientos   = array();

  // Detalle de documentos con saldo
  if(count($data)>0){

    $ClteFil       = $data[0]["sc_num"].$data[0]["sc_fil"];
    $ClteFilCart   = $data[0]["sc_num"].$data[0]["sc_fil"].$data[0]["sc_tica"];
    $ClienteCodigo = $data[0]["sc_num"];
    $ClienteFilial = $data[0]["sc_fil"];
    $ClienteNombre = $data[0]["cc_raso"];
    $ClienteSucursal     = $data[0]["cc_suc"];
    $TipoCarteraCodigo   = $data[0]["sc_tica"];
    $TipoCarteraDescripc = $data[0]["t_descr"];
  
    foreach($data as $row){

      // Cambio de cliente filial
      if ($row["sc_num"].$row["sc_fil"] != $ClteFil){

        array_push($arrTiposCartera, [
          "TipoCarteraCodigo"   => $TipoCarteraCodigo,
          "TipoCarteraDescripc" => $TipoCarteraDescripc,
          "Movimientos"         => $arrMovimientos
        ]);
     
        $arrClientes = [
          "ClienteCodigo" => $ClienteCodigo,
          "ClienteFilial" => $ClienteFilial,
          "ClienteNombre" => $ClienteNombre,
          "Sucursal"      => $ClienteSucursal,
          "TipoCartera"   => $arrTiposCartera
        ];

        // Se agrega el array del nuevo cliente a la seccion "contenido"
        array_push($contenido, $arrClientes);

        $ClienteCodigo = $row["sc_num"];
        $ClienteFilial = $row["sc_fil"];
        $ClienteNombre = $row["cc_raso"];
        $ClienteSucursal = $row["cc_suc"];

        $TipoCarteraCodigo   = $row["sc_tica"];
        $TipoCarteraDescripc = $row["t_descr"];

        $ClteFil       = $row["sc_num"].$row["sc_fil"];
        $ClteFilCart   = $row["sc_num"].$row["sc_fil"].$row["sc_tica"];

        $arrTiposCartera  = array();
        $arrMovimientos = array();

      }

      // Cambio en tipo de cartera
      if ($ClteFilCart != $row["sc_num"].$row["sc_fil"].$row["sc_tica"]){
        array_push($arrTiposCartera, [
          "TipoCarteraCodigo"   => $TipoCarteraCodigo,
          "TipoCarteraDescripc" => $TipoCarteraDescripc,
          "Movimientos"         => $arrMovimientos
        ]);

        $TipoCarteraCodigo   = $row["sc_tica"];
        $TipoCarteraDescripc = $row["t_descr"];

        $ClteFilCart = $row["sc_num"].$row["sc_fil"].$row["sc_tica"];

        $arrMovimientos = array();

      }

      array_push($arrMovimientos, [
        "OficinaFonelliCodigo"  => $row["sc_of"],
        "DocumentoSerie"        => $row["sc_serie"],
        "DocumentoFolio"        => $row["sc_apl"],
        "FechaExpedicion"       => $row["sc_feex"],
        "FechaVencimiento"      => $row["sc_feve"],
        "Cargos"                => floatval($row["sc_cargos"]),
        "Abonos"                => floatval($row["sc_abonos"]),
        "Saldo"                 => floatval($row["sc_saldo"]),
        "DiasVencimiento"       => $row["sc_dias"],
        "SaldoVencido"          => floatval($row["sc_saldove"]),
        "Documento2Serie"       => $row["sc_serie2"],
        "Referencia"            => $row["sc_ref"]
      ]);

    }   // foreach($data as $row)

    // Ultimo registro
    array_push($arrTiposCartera, [
      "TipoCarteraCodigo"   => $TipoCarteraCodigo,
      "TipoCarteraDescripc" => $TipoCarteraDescripc,
      "Movimientos"         => $arrMovimientos
    ]);
 
    $arrClientes = [
      "ClienteCodigo" => $ClienteCodigo,
      "ClienteFilial" => $ClienteFilial,
      "ClienteNombre" => $ClienteNombre,
      "Sucursal"      => $ClienteSucursal,
      "TipoCartera"   => $arrTiposCartera
    ];

    // Se agrega el array del nuevo cliente a la seccion "contenido"
    array_push($contenido, $arrClientes);

  } // count($data)>0

  // Resumen por Tipo de Cartera
  $oFila = array();
  $arrResumenCartera = array();

  foreach($ResumenCartera as $row){

    // Se crea un array con los nodos requeridos
    $oFila = [
      "TipoCarteraCodigo"   => $row["sc_tica"],
      "TipoCarteraDescripc" => $row["t_descr"] ,
      "TipoCarteraCargos"   => floatval($row["sum_cargos"]),
      "TipoCarteraAbonos"   => floatval($row["sum_abonos"]),
      "TipoCarteraSaldo"    => floatval($row["sum_saldo"]),
      "TipoCarteraSaldoVencido" => floatval($row["sum_saldove"])
    ];

    // Se agrega el array a la seccion "contenido"
    array_push($arrResumenCartera, $oFila);

  }   // foreach($ResumenCartera as $row)


  // Resumen por status del cliente
  $oFila = array();
  $arrResumenStatusClte = array();

  foreach($ResumenStatusClte as $row){

    // Se crea un array con los nodos requeridos
    $status_descripc = "Otro";
    switch($row["cc_status"]){
      case "A":
        $status_descripc = "Activos";
        break;
      case "I":
        $status_descripc = "Inactivos";
        break;
      case "R":
        $status_descripc ="Atrasados";        
    }
    
    $oFila = [
      "StatusClienteDescripc" => $status_descripc,
      "StatusClienteNumero"   => floatval($row["numcltes"])
    ];

    // Se agrega el array a la seccion "contenido"
    array_push($arrResumenStatusClte, $oFila);

  }   // foreach($ResumenStatusClte as $row)

  // Resumen por tipo de cliente
  $oFila = array();
  $arrResumenTipoClte = array();

  foreach($ResumenTipoClte as $row){

    $oFila = [
      "TipoClienteCodigo" => $row["cc_ticte"],
      "TipoClienteTotal"  => floatval($row["numcltes"])
    ];

    // Se agrega el array a la seccion "contenido"
    array_push($arrResumenTipoClte, $oFila);

  }   // foreach($ResumenTipoClte as $row)

  $contenido = [
    "Clientes" => $contenido,
    "ResumenTipoCartera" => $arrResumenCartera,
    "ResumenStatusCliente" => $arrResumenStatusClte,
    "ResumenTipoCliente" => $arrResumenTipoClte
  ];

  return $contenido; 

}
