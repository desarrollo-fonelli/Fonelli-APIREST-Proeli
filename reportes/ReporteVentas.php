<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Reporte de Ventas
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ReporteVentas.php";

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
$AgenteCodigo = null;     // Codigo del agente de ventas (para filtrar clientes)
$ClienteDesde = null;     // Código del cliente inicial
$FilialDesde  = null;     // Filial del cliente inicial
$ClienteHasta = null;     // Código del cliente final
$FilialHasta  = null;     // Filial del cliente final
$CategoriaDesde = null;   // Código de categoria inicial
$SubcategoDesde = null;   // Código de Subcategoria inicial
$CategoriaHasta = null;   // Código de categoria final
$SubcategoHasta = null;   // Código de Subcategoria final
$Fecha1Desde  = null;     // Fecha inicial primer periodo
$Fecha1Hasta  = null;     // Fecha final primer periodo
$Fecha2Desde  = null;     // Fecha inicial segundo periodo
$Fecha2Hasta  = null;     // Fecha final segundo periodo
$TipoClienteDesde = null;    // Tipo de cliente inicial
$TipoClienteHasta = null;    // Tipo de cliente final
$OrdenReporte = null;     // Orden del reporte: C=Código cliente | I=Importe | V=Valor agregado
$DesglosaClte = null;     // Desglosa clientes S | N
$DesglosaCatego = null;   // Desglossa categorias S | N
$TipoOrigen   = null;     // Origen de producción del artículo: I=inteno | E=externo
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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if(! in_array($TipoUsuario, ["C","A","G"])){
      throw new Exception("Valor '". $TipoUsuario ."' NO permitido para 'TipoUsuario'");
    }
    if($TipoUsuario == "A" && !isset($_GET["AgenteCodigo"])){
      throw new Exception("Debe indicar un valor para 'AgenteCodigo' cuando 'TipoUsuario' es 'A'");
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

  if (!isset($_GET["CategoriaDesde"])) {
    throw new Exception("El parametro obligatorio 'CategoriaDesde' no fue definido.");
  } else {
    $CategoriaDesde = $_GET["CategoriaDesde"] ;
  }

  if (!isset($_GET["SubcategoDesde"])) {
    throw new Exception("El parametro obligatorio 'SubcategoDesde' no fue definido.");
  } else {
    $SubcategoDesde = $_GET["SubcategoDesde"] ;
  }

  if (!isset($_GET["CategoriaHasta"])) {
    throw new Exception("El parametro obligatorio 'CategoriaHasta' no fue definido.");
  } else {
    $CategoriaHasta = $_GET["CategoriaHasta"] ;
  }

  if (!isset($_GET["SubcategoHasta"])) {
    throw new Exception("El parametro obligatorio 'SubcategoHasta' no fue definido.");
  } else {
    $SubcategoHasta = $_GET["SubcategoHasta"] ;
  }

  if (!isset($_GET["Fecha1Desde"])) {
    throw new Exception("El parametro obligatorio 'Fecha1Desde' no fue definido.");
  } else {
    $Fecha1Desde = $_GET["Fecha1Desde"];
    if(!ValidaFormatoFecha($Fecha1Desde)){
      throw new Exception("El parametro 'Fecha1Desde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["Fecha1Hasta"])) {
    throw new Exception("El parametro obligatorio 'Fecha1Hasta' no fue definido.");
  } else {
    $Fecha1Hasta = $_GET["Fecha1Hasta"];
    if(!ValidaFormatoFecha($Fecha1Hasta)){
      throw new Exception("El parametro 'Fecha1Hasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["Fecha2Desde"])) {
    throw new Exception("El parametro obligatorio 'Fecha2Desde' no fue definido.");
  } else {
    $Fecha2Desde = $_GET["Fecha2Desde"];
    if(!ValidaFormatoFecha($Fecha2Desde)){
      throw new Exception("El parametro 'Fecha2Desde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["Fecha2Hasta"])) {
    throw new Exception("El parametro obligatorio 'Fecha2Hasta' no fue definido.");
  } else {
    $Fecha2Hasta = $_GET["Fecha2Hasta"];
    if(!ValidaFormatoFecha($Fecha2Hasta)){
      throw new Exception("El parametro 'Fecha2Hasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["TipoClienteDesde"])) {
    throw new Exception("El parametro obligatorio 'TipoClienteDesde' no fue definido.");
  } else {
    $TipoClienteDesde = $_GET["TipoClienteDesde"] ;
  }

  if (!isset($_GET["TipoClienteHasta"])) {
    throw new Exception("El parametro obligatorio 'TipoClienteHasta' no fue definido.");
  } else {
    $TipoClienteHasta = $_GET["TipoClienteHasta"] ;
  }

  if (!isset($_GET["OrdenReporte"])) {
    throw new Exception("El parametro obligatorio 'OrdenReporte' no fue definido.");
  } else {
    $OrdenReporte = $_GET["OrdenReporte"] ;
    if(! in_array($OrdenReporte, ["C","I","V"]) ){
      throw new Exception("Valor '". $OrdenReporte. "' NO permitido para 'OrdenReporte'");
    }  
  }

  if (!isset($_GET["DesglosaCliente"])) {
    throw new Exception("El parametro obligatorio 'DesglosaCliente' no fue definido.");
  } else {
    $DesglosaCliente = $_GET["DesglosaCliente"] ;
    if(! in_array($DesglosaCliente, ["S","N"]) ){
      throw new Exception("Valor '". $DesglosaCliente. "' NO permitido para 'DesglosaCliente'");
    }  
  }

  if (!isset($_GET["DesglosaCategoria"])) {
    throw new Exception("El parametro obligatorio 'DesglosaCategoria' no fue definido.");
  } else {
    $DesglosaCategoria = $_GET["DesglosaCategoria"] ;
    if(! in_array($DesglosaCategoria, ["S","N"]) ){
      throw new Exception("Valor '". $DesglosaCategoria. "' NO permitido para 'DesglosaCategoria'");
    }  
  }

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario","Usuario","AgenteCodigo","ClienteDesde","FilialDesde",
"ClienteHasta","FilialHasta","CategoriaDesde","SubcategoDesde","CategoriaHasta","SubcategoHasta", 
"Fecha1Desde","Fecha1Hasta","Fecha2Desde","Fecha2Hasta","TipoClienteDesde","TipoClienteHasta",
"OrdenReporte","DesglosaCliente","DesglosaCategoria","TipoOrigen","Pagina");

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
  $mensaje = "Parametros no reconocidos: ". $mensaje;   
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
  exit;
}

# Hay que inicializarverificar parametros opcionales y en caso 
# que estos no se indiquen, asignar valores por omisión.
# (dichos valores se definieron al inicio del script, al declarar las variables)

// --- Primero este parámetro porque puede cambiar después
if (isset($_GET["AgenteCodigo"])) {
  $AgenteCodigo = $_GET["AgenteCodigo"];
  if(isset($TipoUsuario) && $TipoUsuario == "A"){
    if(isset($_GET["Usuario"]) && $AgenteCodigo != $_GET["Usuario"]){
      $mensaje = "'Usuario' y 'AgenteCodigo' deben ser iguales para 'TipoUsuario' = 'A'";
      http_response_code(400);  
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit; 
    }
  }
} 

if (isset($_GET["Usuario"])) {
  $Usuario = $_GET["Usuario"];  
  if(!isset($_GET["TipoUsuario"])){
    $mensaje = "Debe indicar 'TipoUsuario' cuando indica valor para 'Usuario'";
    http_response_code(400);  
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit; 
  }

} else {
  if($TipoUsuario == "A"){
    $mensaje = "Debe indicar 'Usuario' cuando 'TipoUsuario' es 'A'";
    http_response_code(400);  
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;  
  }
  if(in_array($TipoUsuario, ["A", "G"])){
    $mensaje = "Debe indicar 'Usuario' cuando 'TipoUsuario' es 'A' o 'G'"; 
    http_response_code(400);  
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;  
  }
}

if (isset($_GET["TipoOrigen"])) {
  $TipoOrigen = $_GET["TipoOrigen"];
  if(! in_array($TipoOrigen, ["I", "E"]) ){
    $mensaje = "Valor '". $TipoOrigen. "' NO permitido para 'TipoOrigen'";
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

  $data = SelectData($TipoUsuario,$Usuario,$AgenteCodigo,$ClienteDesde,$FilialDesde, 
  $ClienteHasta,$FilialHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
  $Fecha1Desde,$Fecha1Hasta,$Fecha2Desde,$Fecha2Hasta,$TipoClienteDesde,$TipoClienteHasta,
  $OrdenReporte,$DesglosaCliente,$DesglosaCategoria,$TipoOrigen,$Pagina);

  $dataCltesconVenta = SelectCltesconVenta($TipoUsuario,$Usuario,$AgenteCodigo,$ClienteDesde,
  $FilialDesde,$ClienteHasta,$FilialHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
  $Fecha1Desde,$Fecha1Hasta,$Fecha2Desde,$Fecha2Hasta,$TipoClienteDesde,$TipoClienteHasta,$TipoOrigen);

  $dataTotalCatego = SelectTotalCatego($TipoUsuario,$Usuario,$AgenteCodigo,$ClienteDesde,$FilialDesde, 
  $ClienteHasta,$FilialHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
  $Fecha1Desde,$Fecha1Hasta,$Fecha2Desde,$Fecha2Hasta,$TipoClienteDesde,$TipoClienteHasta,
  $TipoOrigen);

  # Asigna código de respuesta HTTP por default
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

  $dataCompuesta = CreaDataCompuesta( $data, $dataCltesconVenta, $dataTotalCatego );

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
 * @param int $AgenteCodigo
 * @param int $ClienteDesde
 * @param int $FilialDesde
 * @param int $ClienteHasta
 * @param int $FilialHasta
 * @param string $CategoriaDesde
 * @param string $SubcategoDesde
 * @param string $CategoriaHasta
 * @param string $SubcategoHasta
 * @param string $Fecha1Desde
 * @param string $Fecha1Hasta
 * @param string $Fecha2Desde
 * @param string $Fecha2Hasta
 * @param string $TipoClienteDesde
 * @param string $TipoClienteHasta
 * @param string $OrdenReporte
 * @param string $DesglosaCliente
 * @param string $DesglosaCategoria
 * @param string $TipoOrigen
 * @param string $Pagina
 * @return array
 */
FUNCTION SelectData($TipoUsuario,$Usuario,$AgenteCodigo,$ClienteDesde,$FilialDesde, 
$ClienteHasta,$FilialHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
$Fecha1Desde,$Fecha1Hasta,$Fecha2Desde,$Fecha2Hasta,$TipoClienteDesde,$TipoClienteHasta,
$OrdenReporte,$DesglosaCliente,$DesglosaCategoria,$TipoOrigen,$Pagina)
{
  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

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
  
  $strClteInic   = str_replace(' ','0',str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  $strCategoDesde   = $CategoriaDesde. $SubcategoDesde;
  $strCategoHasta   = $CategoriaHasta. $SubcategoHasta; 

  if(isset($AgenteCodigo)){
    $strAgenteCodigo = str_pad($AgenteCodigo, 2," ",STR_PAD_LEFT);
  }

  try {

    # Se conecta a la base de datos
    require_once "../db/conexion.php";  

    # Handler para la conexión a la base de datos
    //$conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # Crea tablas temporales normalizadas para categorias y subcategorias
    $sqlCmd = "CREATE TEMPORARY TABLE catego AS 
    SELECT trim(t_gpo) as idcatego, t_descr AS descripc 
      FROM var020 WHERE t_tica = '02' AND SUBSTR(t_param,1,1) = '1' 
      ORDER BY T_GPO";    
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "CREATE TEMPORARY TABLE subcatego AS
    SELECT a.idcatego, a.descripc AS namecatego,
            trim(b.t_clave) AS idsubcatego, b.t_descr AS namesubcatego 
      FROM catego a LEFT JOIN var020 b 
        ON b.t_tica = '02' AND a.idcatego = b.t_gpo
      WHERE b.t_clave <> '  ' AND SUBSTR(b.T_PARAM,1,1) <> '1'
      ORDER BY a.idcatego,b.t_clave";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   
    
    # Crea tabla temporal resumida para primer periodo --------------------------------

    # Construyo dinamicamente la condicion WHERE
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta
    AND a.e1_fecha >= :fechaDesde AND a.e1_fecha <= :fechaHasta
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal 
    AND concat(a.e1_cat,a.e1_scat) >= :strCategoDesde
    AND concat(a.e1_cat,a.e1_scat) <= :strCategoHasta
    AND concat(a.e1_cat,a.e1_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el tipo de usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    $sqlCmd = "CREATE TEMPORARY TABLE tmp_tot1 AS 
      SELECT SUM(a.e1_imp) AS e1_imp, SUM(a.e1_va) AS e1_va
      FROM cli040 a 
      LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
      $where ";

    //var_dump($sqlCmd); exit;

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" AND isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 

    // Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      # Borra tablas temporales
      BorraTemporales($conn);

      $conn = null;   // Cierra la conexión 
      return [];
    }


    # Crea tabla con detalle del reporte agrupado por cliente, categoria y linea de producto
    # Utilizo la función agregada MAX para no incluir en la clausula GROUP BY todas las columnas
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_tot2 AS 
      SELECT SUM(a.e1_imp) AS e1_imp2, SUM(a.e1_va) AS e1_va2
      FROM cli040 a 
      LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
      $where ";
      
    /* ------ OJO --- OJO --- OJO --- OJO ---
      Hay que ver si se debe retirar esta linea de la clausula WHERE    
      AND concat(a.va_cat,a.va_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego)
      -> segun yo, con que se aplique en la primera consulta es suficiente
    */

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      # Borra tablas temporales
      BorraTemporales($conn);

      $conn = null;   // Cierra la conexión 
      return [];
    }

    # Detalle de piezas y gramos 1er Periodo
    # Resumo inv050 y cli040 incluyendo columnas para ambas tablas y al final las uno

    # Construyo dinamicamente la condicion WHERE
    $where = "WHERE c.cc_ticte >= :tipoClienteDesde AND c.cc_ticte <= :tipoClienteHasta
    AND a.va_fecha >= :fechaDesde AND a.va_fecha <= :fechaHasta
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) <= :strClteFinal 
    AND concat(a.va_cat,a.va_scat) >= :strCategoDesde
    AND concat(a.va_cat,a.va_scat) <= :strCategoHasta
    AND concat(a.va_cat,a.va_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.va_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    // dRendon: Debido a restricciones de PostGreSQL, utilizo la funcion de agregacion max() 
    // para no incluir todos los campos en la clausula GROUP BY

    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzas1 AS
      SELECT va_num,va_fil,MAX(va_age) va_age,va_cat,va_scat,
      SUM(COALESCE(va_pza,0)+COALESCE(va_pzae,0)) AS va_pza, 
      SUM(COALESCE(va_can,0)+COALESCE(va_cane,0)) as va_can, 
      0 AS e1_imp, 0 AS e1_va,
      0 AS va_pza2, 0 AS va_can2,0 AS e1_imp2,0 AS e1_va2
      FROM inv050 a
      LEFT JOIN var020 b ON CONCAT('05',va_lin)=CONCAT(b.T_TICA,b.T_GPO)
      LEFT JOIN cli010 c ON va_num=c.cc_num AND va_fil=c.cc_fil
      $where GROUP BY va_num,va_fil,va_cat,va_scat ";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Detalle de Importe y Valor Agregado 1er Periodo

    # Construyo dinamicamente la condicion WHERE
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta
    AND a.e1_fecha >= :fechaDesde AND a.e1_fecha <= :fechaHasta
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal 
    AND concat(a.e1_cat,a.e1_scat) >= :strCategoDesde
    AND concat(a.e1_cat,a.e1_scat) <= :strCategoHasta
    AND concat(a.e1_cat,a.e1_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    $sqlCmd = "CREATE TEMPORARY TABLE tmp_imp1 AS
      SELECT a.e1_num,a.e1_fil,MAX(a.e1_age) e1_age,e1_cat,e1_scat,
      0 AS va_pza,0 AS va_can,
      SUM(COALESCE(a.e1_imp,0)) AS e1_imp, SUM(COALESCE(a.e1_va,0)) AS e1_va,
      0 AS va_pza2,0 AS va_can2,0 AS e1_imp2,0 AS e1_va2
      FROM cli040 a 
      LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
      $where GROUP BY a.e1_num,a.e1_fil,e1_cat,e1_scat ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Une piezas con importes 1er Periodo
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimp1 AS
    SELECT * FROM tmp_pzas1
    UNION
    SELECT * FROM tmp_imp1";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();

    # Agrupa por cliente filial y categoria/subcategoria
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimpnum1 AS
    SELECT va_num,va_fil,max(va_age) va_age,va_cat,va_scat,
    SUM(va_pza) AS va_pza,sum(va_can) AS va_can,
    SUM(e1_imp) AS e1_imp, sum(e1_va) AS e1_va,
    0 AS va_pza2,0 AS va_can2,0 AS e1_imp2,0 AS e1_va2
    FROM tmp_pzasimp1 GROUP BY va_num,va_fil,va_cat,va_scat";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();

    # Relaciona tabla detallada con la de totales para calcular porcentajes 1er Periodo
    //Utilizo ON 1=1 para que PostgreSQL una la tabla a que tiene muchos registros con la
    //tabla d que tiene un solo registro pero no tiene campos que pueda usar como llave
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_1per AS
    SELECT a.va_num,a.va_fil,a.va_age,va_cat,va_scat, 
    a.va_pza,a.va_can,
    a.e1_imp,round(a.e1_imp/d.e1_imp*100,2) AS porc_imp,
    a.e1_va,round(a.e1_va/d.e1_va*100,2) AS porc_va,
    0 AS va_pza2,0 AS va_can2,0 AS e1_imp2,0 AS porc_imp2,
    0 AS e1_va2,0 AS porc_va2
    FROM tmp_pzasimpnum1 a
    JOIN tmp_tot1 d ON 1=1 ";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL ->execute();
    $numRows = $oSQL->rowCount(); 

    # Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      # Borra tablas temporales
      BorraTemporales($conn);

      $conn = null;   // Cierra la conexión 
      return [];
    }

    # Detalle de piezas y gramos 2do Periodo ----------------------------------------
    # Resumo inv050 y cli040 incluyendo columnas para ambas tablas y al final las uno
 
    # Construyo dinamicamente la condicion WHERE
    $where = "WHERE c.cc_ticte >= :tipoClienteDesde AND c.cc_ticte <= :tipoClienteHasta
    AND va_fecha >= :fechaDesde AND va_fecha <= :fechaHasta
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) <= :strClteFinal 
    AND concat(va_cat,va_scat) >= :strCategoDesde 
    AND concat(va_cat,va_scat) <= :strCategoHasta 
    AND concat(va_cat,va_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.va_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzas2 AS
      SELECT va_num, va_fil, MAX(va_age) va_age, va_cat,va_scat,
      0 AS va_pza, 0 AS va_can, 0 AS e1_imp, 0 AS e1_va,
      SUM(COALESCE(va_pza,0)+COALESCE(va_pzae,0)) AS va_pza2, 
      SUM(COALESCE(va_can,0)+COALESCE(va_cane,0)) as va_can2,
      0 AS e1_imp2, 0 AS e1_va2 
      FROM inv050 a
      LEFT JOIN var020 b ON CONCAT('05',va_lin)=CONCAT(b.T_TICA,b.T_GPO)
      LEFT JOIN cli010 c ON va_num=c.cc_num AND va_fil=c.cc_fil
      $where GROUP BY va_num,va_fil,va_cat,va_scat ";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);
  
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }
  
    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    

    # Detalle de Importe y Valor Agregado 2do Periodo ----------------------------------
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta
    AND a.e1_fecha >= :fechaDesde AND a.e1_fecha <= :fechaHasta
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal 
    AND concat(a.e1_cat,a.e1_scat) >= :strCategoDesde 
    AND concat(a.e1_cat,a.e1_scat) <= :strCategoHasta 
    AND concat(a.e1_cat,a.e1_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }
    
    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_imp2 AS
      SELECT a.e1_num,a.e1_fil,MAX(a.e1_age) e1_age,a.e1_cat,a.e1_scat,
      0 AS va_pza,0 AS va_can,0 AS e1_imp,0 AS e1_va,
      0 AS va_pza2,0 AS va_can2,
      SUM(COALESCE(a.e1_imp,0)) AS e1_imp2, 
      SUM(COALESCE(a.e1_va,0)) AS e1_va2    
      FROM cli040 a 
      LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
      $where GROUP BY a.e1_num,a.e1_fil,a.e1_cat,a.e1_scat ";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);
  
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }
  
    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    


    # Une piezas con importes 2do Periodo   -------------------------------
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimp2 AS
    SELECT * FROM tmp_pzas2
    UNION
    SELECT * FROM tmp_imp2";
    
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();


    # Agrupar por cliente,filial y familia
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimpnum2 AS
    SELECT va_num,va_fil,MAX(va_age) va_age,va_cat,va_scat,
    0 AS va_pza,0 AS va_can,0 AS e1_imp,0 AS e1_va,
    SUM(va_pza2) AS va_pza2,sum(va_can2) AS va_can2,
    SUM(e1_imp2) AS e1_imp2, sum(e1_va2) AS e1_va2
    FROM tmp_pzasimp2 GROUP BY va_num,va_fil,va_cat,va_scat";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();


    # Relaciona tabla detallada con la de totales para calcular porcentajes 2do Periodo
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_2per AS
    SELECT a.va_num,a.va_fil,a.va_age,va_cat,va_scat, 
    0 AS va_pza,0 AS va_can,0 AS e1_imp,0 AS porc_imp,
    0 AS e1_va,0 AS porc_va,
    a.va_pza2,a.va_can2,
    a.e1_imp2,round(a.e1_imp2/d.e1_imp2*100,2) AS porc_imp2,
    a.e1_va2,round(a.e1_va2/d.e1_va2*100,2) AS porc_va2
    FROM tmp_pzasimpnum2 a
    JOIN tmp_tot2 d ON 1=1";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();

    # Une las tablas de los dos periodos en una sola
    # Es importante utilizar el metodo de UNION porque se deben incluir
    # en la tabla final los clientes encontrados en 2018 y los encontrados en 2019
    # ya que un periodo puede incluir clientes que no se incluyan en el otro.

    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_union AS
    SELECT * FROM tmp_1per
    UNION
    SELECT * FROM tmp_2per
    ORDER BY va_num,va_fil";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();

    # Agrupacion final para no duplicar renglones

            // dRendon 19/feb/2020
            // Voy a agregar el filtro por tipo de cliente solicitado por rLopez.
            // La razon para ponerlo hasta este punto es que URGE el cambio, por
            // lo que no voy a dedicar tiempo para modificar los procesos anteriores.

    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_repo AS
    SELECT va_num,va_fil,MAX(va_age) va_age,va_cat,va_scat,
    MAX(trim(T_DESCR)) AS categonombre,MAX(trim(d.nameSubcatego)) AS subcategonombre,
    MAX(concat(trim(T_DESCR),' - ', trim(d.nameSubcatego))) AS catdescripc,
    MAX(trim(cc_raso)) cc_raso,MAX(cc_suc) cc_suc,MAX(cc_status) cc_status,
    MAX(cc_ticte) cc_ticte,MAX(cc_tipoli) cc_tipoli,MAX(cc_tipoli2) cc_tipoli2,MAX(cc_tparid) cc_tparid,
    SUM(va_pza) AS va_pza, SUM(va_can) AS va_can,
    SUM(e1_imp) AS e1_imp, SUM(porc_imp) as porc_imp,
    SUM(e1_va) AS e1_va, SUM(porc_va) AS porc_va,
    SUM(va_pza2) AS va_pza2, SUM(va_can2) AS va_can2,
    SUM(e1_imp2) AS e1_imp2, SUM(porc_imp2) as porc_imp2,
    SUM(e1_va2) AS e1_va2, SUM(porc_va2) as porc_va2
    FROM tmp_union 
    LEFT JOIN cli010 ON va_num=cc_num AND va_fil=cc_fil 
    LEFT JOIN var020 ON CONCAT('02',va_cat,'1')=CONCAT(T_TICA,TRIM(T_GPO),TRIM(T_PARAM))
    LEFT JOIN subcatego d ON tmp_union.va_cat = d.idCatego AND tmp_union.va_scat = d.idSubcatego
    GROUP BY va_num,va_fil,va_cat,va_scat 
    ORDER BY replace(va_num,' ','0'),replace(va_fil,' ','0'),va_cat,va_scat" ;
    $oSQL = $conn->prepare($sqlCmd);
    //$oSQL->bindParam(":ticteInic",$TipoClienteDesde);
    //$oSQL->bindParam(":ticteFinal",$TipoClienteHasta);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();


    # En caso que se pida Ordenar el reporte por importe o por valor agregado
    # es necesario obtener primero en una tabla el total por cliente y despues 
    # relacionarla con la tabla obtenida anteriormente para listarla en el
    # orden solicitado. 
    unset($oSQL);
    switch($OrdenReporte) {
      // Ordenado por Cliente
      case 'C':
        $sqlCmd = "SELECT * FROM tmp_repo";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->execute();
        $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);
        break;
      }



    //var_dump($numRows); return [];

  } catch (Exception $e) {
    BorraTemporales($conn);
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }
 
  # Borra tablas temporales
  BorraTemporales($conn);

  # Cierra la conexión 
  $conn = null;   

  # Falta tener en cuenta la paginacion
  return $arrData;

}

FUNCTION SelectCltesconVenta($TipoUsuario,$Usuario,$AgenteCodigo,$ClienteDesde,
$FilialDesde,$ClienteHasta,$FilialHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
$Fecha1Desde,$Fecha1Hasta,$Fecha2Desde,$Fecha2Hasta,$TipoClienteDesde,$TipoClienteHasta,$TipoOrigen)
{
  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

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
  
  $strClteInic   = str_replace(' ','0',str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  $strCategoDesde   = $CategoriaDesde. $SubcategoDesde;
  $strCategoHasta   = $CategoriaHasta. $SubcategoHasta; 

  if($TipoUsuario == "A" && isset($AgenteCodigo)){
    $strAgenteCodigo = str_pad($AgenteCodigo, 2," ",STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  // require_once "../db/conexion.php";

  try {

    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  

    # Handler para la conexión a la base de datos
    $conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Busca clientes con venta en cada periodo
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta
      AND e1_fecha >= :fechaDesde AND e1_fecha <= :fechaHasta
      AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
      AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal
      AND concat(e1_cat,e1_scat) >= :strCategoDesde 
      AND concat(e1_cat,e1_scat) <= :strCategoHasta ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    $sqlCmd = "DROP TABLE IF EXISTS temp_tot1;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute(); 
    $sqlCmd = "DROP TABLE IF EXISTS temp_tot2;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute(); 

    $sqlCmd = "CREATE TEMPORARY TABLE temp_tot1 AS
    SELECT e1_num,e1_fil, SUM(e1_imp) AS e1_imp, SUM(e1_va) AS e1_va 
    FROM cli040 a
    LEFT JOIN cli010 b ON e1_num = cc_num AND e1_fil = cc_fil
    $where 
    GROUP BY e1_num,e1_fil";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 

    if($numRows < 1){
      $cltesConVenta1 = 0; 
    } else {

      $sqlCmd = "SELECT COUNT(*) numcltes FROM temp_tot1 WHERE e1_imp <> 0 OR e1_va <> 0;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute(); 
      $row = $oSQL->fetch(PDO::FETCH_ASSOC);
      $cltesConVenta1 = $row["numcltes"];
    }
        
    $sqlCmd = "CREATE TEMPORARY TABLE temp_tot2 AS
    SELECT e1_num,e1_fil,SUM(e1_imp) AS e1_imp, SUM(e1_va) AS e1_va 
    FROM cli040 a
    LEFT JOIN cli010 b ON e1_num = cc_num AND e1_fil = cc_fil
    $where 
    GROUP BY e1_num,e1_fil";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 

    if($numRows < 1){
      $cltesConVenta2 = 0; 
    } else {

      $sqlCmd = "SELECT COUNT(*) numcltes FROM temp_tot2 WHERE e1_imp <> 0 OR e1_va <> 0;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute(); 
      $row = $oSQL->fetch(PDO::FETCH_ASSOC);
      $cltesConVenta2 = $row["numcltes"];
    }

    $sqlCmd = "DROP TABLE IF EXISTS temp_tot1;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute(); 
    $sqlCmd = "DROP TABLE IF EXISTS temp_tot2;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute(); 
  

    # Total de Clientes 1er y 2do Periodo
    # NOTA: en Proeli se cuentan cc_num y cc_fil

    $where = "WHERE cc_alta <= :fechaHasta ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "cc_age = :strAgenteCodigo ";
    }

    unset($oSQL);
    $sqlCmd = "SELECT cc_num FROM cli010 $where ";    
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 
    if($numRows < 1){
      $cltesTotales1 = 0; 
    } else {
      $cltesTotales1 = $numRows;
    }

    $where = "WHERE cc_alta <= :fechaHasta ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "cc_age = :strAgenteCodigo ";
    }

    unset($oSQL);
    $sqlCmd = "SELECT cc_num FROM cli010 $where ";
    
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 
    if($numRows < 1){
      $cltesTotales2 = 0; 
    } else {
      $cltesTotales2 = $numRows;
    }

    # Por diferencia obtengo los clientes sin venta
    $cltesSinVenta1 = $cltesTotales1 - $cltesConVenta1;
    $cltesSinVenta2 = $cltesTotales2 - $cltesConVenta2;

    $arrData = [
      "ClientesConVenta1" => $cltesConVenta1,
      "ClientesSinVenta1" => $cltesSinVenta1,
      "ClientesTotales1"  => $cltesTotales1,
      "ClientesConVenta2" => $cltesConVenta2,
      "ClientesSinVenta2" => $cltesSinVenta2,
      "ClientesTotales2"  => $cltesTotales2
    ];
 
  } catch (Exception $e) {
    BorraTemporales($conn);
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }
 
  # Cierra la conexión 
  $conn = null;   

  # Falta tener en cuenta la paginacion
  return $arrData;

}

FUNCTION SelectTotalCatego($TipoUsuario,$Usuario,$AgenteCodigo,$ClienteDesde,$FilialDesde, 
  $ClienteHasta,$FilialHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
  $Fecha1Desde,$Fecha1Hasta,$Fecha2Desde,$Fecha2Hasta,$TipoClienteDesde,$TipoClienteHasta,
  $TipoOrigen)
{

  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

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
  
  $strClteInic   = str_replace(' ','0',str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  $strCategoDesde   = $CategoriaDesde. $SubcategoDesde;
  $strCategoHasta   = $CategoriaHasta. $SubcategoHasta; 

  if(isset($AgenteCodigo)){
    $strAgenteCodigo = str_pad($AgenteCodigo, 2," ",STR_PAD_LEFT);
  }

  try {
    # Handler para la conexión a la base de datos
    $conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # Crea tablas temporales normalizadas para categorias y subcategorias
    $sqlCmd = "CREATE TEMPORARY TABLE catego AS 
    SELECT trim(t_gpo) as idcatego, t_descr AS descripc 
      FROM var020 WHERE t_tica = '02' AND SUBSTR(t_param,1,1) = '1' 
      ORDER BY T_GPO";    
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "CREATE TEMPORARY TABLE subcatego AS
    SELECT a.idcatego, a.descripc AS namecatego,
            trim(b.t_clave) AS idsubcatego, b.t_descr AS namesubcatego 
      FROM catego a LEFT JOIN var020 b 
        ON b.t_tica = '02' AND a.idcatego = b.t_gpo
      WHERE b.t_clave <> '  ' AND SUBSTR(b.T_PARAM,1,1) <> '1'
      ORDER BY a.idcatego,b.t_clave";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    # Totales del reporte, 1er Periodo y 2do Periodo
    # importe y valor agregado para obtener porcentajes mas adelante
    # ---------------------------------------------------------------------------------
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta 
    AND a.e1_fecha >= :fechaDesde AND a.e1_fecha <= :fechaHasta
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal 
    AND concat(a.e1_cat,a.e1_scat) >= :strCategoDesde
    AND concat(a.e1_cat,a.e1_scat) <= :strCategoHasta
    AND concat(a.e1_cat,a.e1_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego) ";
    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    $sqlCmd = "CREATE TEMPORARY TABLE tmp_tot1 AS
      SELECT SUM(a.e1_imp) AS e1_imp, SUM(a.e1_va) AS e1_va
      FROM cli040 a 
      LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
      $where ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 

    // Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      # Borra tablas temporales
      BorraTemporales($conn);

      $conn = null;   // Cierra la conexión 
      return [];
    }

    $sqlCmd = "CREATE TEMPORARY TABLE tmp_tot2 AS
    SELECT SUM(a.e1_imp) AS e1_imp2, SUM(a.e1_va) AS e1_va2
    FROM cli040 a 
    LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
    $where ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount(); 

    // Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      # Borra tablas temporales
      BorraTemporales($conn);

      $conn = null;   // Cierra la conexión 
      return [];
    }

    # Detalle de piezas y gramos 1er Periodo
    # ---------------------------------------
    $where = "WHERE c.cc_ticte >= :tipoClienteDesde AND c.cc_ticte <= :tipoClienteHasta 
    AND va_fecha >= :fechaDesde AND va_fecha <= :fechaHasta
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) <= :strClteFinal 
    AND concat(a.va_cat,a.va_scat) >= :strCategoDesde
    AND concat(a.va_cat,a.va_scat) <= :strCategoHasta
    AND concat(a.va_cat,a.va_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.va_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzas1 AS
      SELECT MAX(va_num) va_num,MAX(va_fil) va_fil,MAX(va_age) va_age,va_cat,va_scat,
      SUM(COALESCE(va_pza,0)+COALESCE(va_pzae,0)) AS va_pza, 
      SUM(COALESCE(va_can,0)+COALESCE(va_cane,0)) as va_can, 
      0 AS e1_imp, 0 AS e1_va,
      0 AS va_pza2, 0 AS va_can2,0 AS e1_imp2, 0 AS e1_va2
      FROM inv050 a
      LEFT JOIN var020 b ON CONCAT('05',va_lin)=CONCAT(b.T_TICA,b.T_GPO)      
      LEFT JOIN cli010 c ON va_num=c.cc_num AND va_fil=c.cc_fil
      $where GROUP BY va_cat,va_scat ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Detalle de Importe y Valor Agregado 1er Periodo
    # -----------------------------------------------
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta
    AND a.e1_fecha >= :fechaDesde AND a.e1_fecha <= :fechaHasta
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal 
    AND concat(a.e1_cat,a.e1_scat) >= :strCategoDesde
    AND concat(a.e1_cat,a.e1_scat) <= :strCategoHasta
    AND concat(a.e1_cat,a.e1_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }
    
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_imp1 AS
    SELECT max(a.e1_num) e1_num, max(a.e1_fil) e1_fil,max(a.e1_age) e1_age,a.e1_cat,a.e1_scat,
    0 AS va_pza,0 AS va_can,
    SUM(COALESCE(a.e1_imp,0)) AS e1_imp, SUM(COALESCE(a.e1_va,0)) AS e1_va,
    0 AS va_pza2,0 AS va_can2,0 AS e1_imp2,0 AS e1_va2
    FROM cli040 a 
    LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
    $where
    GROUP BY a.e1_cat,a.e1_scat ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha1Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha1Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Une piezas con importes 1er Periodo
    # ----------------------------------- 
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimp1 AS
    SELECT * FROM tmp_pzas1
    UNION
    SELECT * FROM tmp_imp1";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
 
    # Agrupa por categoria, subcategoria
    # ----------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimpnum1 AS
    SELECT MAX(va_age) va_age,va_cat,va_scat,
    SUM(va_pza) AS va_pza,sum(va_can) AS va_can,
    SUM(e1_imp) AS e1_imp, sum(e1_va) AS e1_va,
    0 AS va_pza2,0 AS va_can2,0 AS e1_imp2,0 AS e1_va2
    FROM tmp_pzasimp1 GROUP BY va_cat,va_scat";
    unset($oSQL);
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();

    # Relaciona tabla detallada con la de totales para calcular porcentajes
    # 1er Periodo
    # ---------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_1per AS
    SELECT a.va_age,a.va_cat,a.va_scat,
    a.va_pza,a.va_can,
    a.e1_imp,round(a.e1_imp/d.e1_imp*100,2) AS porc_imp,
    a.e1_va,round(a.e1_va/d.e1_va*100,2) AS porc_va,
    0 AS va_pza2,0 AS va_can2,0 AS e1_imp2,0 AS porc_imp2,
    0 AS e1_va2,0 AS porc_va2
    FROM tmp_pzasimpnum1 a
    JOIN tmp_tot1 d ON 1=1";
 
    unset($oSQL);
    $oSQL = $conn->prepare($sqlCmd);
    if($oSQL->execute()==false){
      $oSQL=null;
      $conn=null;
      return [];
    }
 
    # Detalle de piezas y gramos 1er Periodo
    # ---------------------------------------
    $where = "WHERE c.cc_ticte >= :tipoClienteDesde AND c.cc_ticte <= :tipoClienteHasta 
    AND va_fecha >= :fechaDesde AND va_fecha <= :fechaHasta
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) <= :strClteFinal 
    AND concat(a.va_cat,a.va_scat) >= :strCategoDesde
    AND concat(a.va_cat,a.va_scat) <= :strCategoHasta
    AND concat(a.va_cat,a.va_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.va_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND SUBSTR(b.t_param,20,1) = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }

    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzas2 AS
      SELECT MAX(va_num) va_num,MAX(va_fil) va_fil,MAX(va_age) va_age,va_cat,va_scat,
      0 AS va_pza, 0 AS va_can, 0 AS e1_imp, 0 AS e1_va,
      SUM(COALESCE(va_pza,0)+COALESCE(va_pzae,0)) AS va_pza2, 
      SUM(COALESCE(va_can,0)+COALESCE(va_cane,0)) AS va_can2,
      0 AS e1_imp2, 0 AS e1_va2
      FROM inv050 a
      LEFT JOIN var020 b ON CONCAT('05',va_lin)=CONCAT(b.T_TICA,b.T_GPO)      
      LEFT JOIN cli010 c ON va_num=c.cc_num AND va_fil=c.cc_fil
      $where GROUP BY va_cat,va_scat ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Detalle de Importe y Valor Agregado 1er Periodo
    # -----------------------------------------------
    $where = "WHERE b.cc_ticte >= :tipoClienteDesde AND b.cc_ticte <= :tipoClienteHasta
    AND a.e1_fecha >= :fechaDesde AND a.e1_fecha <= :fechaHasta
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.e1_num,' ','0'),replace(a.e1_fil,' ','0')) <= :strClteFinal 
    AND concat(a.e1_cat,a.e1_scat) >= :strCategoDesde
    AND concat(a.e1_cat,a.e1_scat) <= :strCategoHasta
    AND concat(a.e1_cat,a.e1_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";

    // Solo aplica filtro cuando el usuario es un agente
    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      if($where == ""){
        $where = "WHERE ";
      } else {
        $where .= "AND ";
      }
      $where .= "a.e1_age = :strAgenteCodigo ";
    }

    $filtroTipoie = "";
    if(isset($TipoOrigen)){
      switch ($TipoOrigen){
        case "I":
          $filtroTipoie = "AND a.e1_tipoie = 'I' ";
          break;
        case "E":
          $filtroTipoie = "AND a.e1_tipoie = 'E' ";
          break;
      }    
      $where .= $filtroTipoie;
    }
    
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_imp2 AS
    SELECT max(a.e1_num) e1_num, max(a.e1_fil) e1_fil,max(a.e1_age) e1_age,a.e1_cat,a.e1_scat,
    0 AS va_pza,0 AS va_can,0 AS e1_imp, 0 AS e1_va,
    0 AS va_pza2,0 AS va_can2,
    SUM(COALESCE(a.e1_imp,0)) AS e1_imp2,
    SUM(COALESCE(a.e1_va,0)) AS e1_va2
    FROM cli040 a 
    LEFT JOIN cli010 b ON a.e1_num = b.cc_num AND a.e1_fil = b.cc_fil
    $where
    GROUP BY a.e1_cat,a.e1_scat ";

    unset($oSQL);
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":tipoClienteDesde", $TipoClienteDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":tipoClienteHasta", $TipoClienteHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaDesde", $Fecha2Desde, PDO::PARAM_STR);
    $oSQL-> bindParam(":fechaHasta", $Fecha2Hasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A" && isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    # Une piezas con importes 2o Periodo
    # ----------------------------------- 
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimp2 AS
    SELECT * FROM tmp_pzas2
    UNION
    SELECT * FROM tmp_imp2";
    unset($oSQL);
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
 
    # Agrupa por categoria, subcategoria
    # ----------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_pzasimpnum2 AS
    SELECT MAX(va_age) va_age,va_cat,va_scat,
    0 AS va_pza,0 AS va_can,0 AS e1_imp,0 AS e1_va,
    SUM(va_pza2) AS va_pza2,sum(va_can2) AS va_can2,
    SUM(e1_imp2) AS e1_imp2,sum(e1_va2) AS e1_va2
    FROM tmp_pzasimp2 GROUP BY va_cat,va_scat";
    unset($oSQL);
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();

    # Relaciona tabla detallada con la de totales para calcular porcentajes
    # 2o Periodo
    # ---------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_2per AS
    SELECT a.va_age,a.va_cat,va_scat,
    0 AS va_pza,0 AS va_can,0 AS e1_imp,0 AS porc_imp,
    0 AS e1_va,0 AS porc_va,
    a.va_pza2,a.va_can2,
    a.e1_imp2,round(a.e1_imp2/d.e1_imp2*100,2) AS porc_imp2,
    a.e1_va2,round(a.e1_va2/d.e1_va2*100,2) AS porc_va2
    FROM tmp_pzasimpnum2 a
    JOIN tmp_tot2 d ON 1=1";
 
    unset($oSQL);
    $oSQL = $conn->prepare($sqlCmd);
    if($oSQL->execute()==false){
      $oSQL=null;
      $conn=null;
      return [];
    }
 
    
    # Une las tablas de los dos periodos en una sola
    # Es importante el metodo de UNION porque se deben incluir
    # en la tabla final los clientes encontrados en 2018 y los
    # encontrados en 2019, y un periodo puede incluir clientes
    # que no incluya el otro.

    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp_union AS
    SELECT * FROM tmp_1per
    UNION
    SELECT * FROM tmp_2per
    ORDER BY va_cat,va_scat";

    $oSQL = $conn->prepare($sqlCmd);
    if($oSQL->execute() == false){
      $oSQL=null;
      $conn=null;
      return [];
    }

    unset($oSQL);
    $sqlCmd = "SELECT max(va_age) va_age,va_cat,va_scat,
    max(trim(t_descr)) as catnombre, max(trim(d.nameSubcatego)) as subcatnombre,
    SUM(va_pza) AS va_pza, SUM(va_can) AS va_can,
    SUM(e1_imp) AS e1_imp, SUM(porc_imp) as porc_imp,
    SUM(e1_va) AS e1_va, SUM(porc_va) AS porc_va,
    SUM(va_pza2) AS va_pza2, SUM(va_can2) AS va_can2,
    SUM(e1_imp2) AS e1_imp2, SUM(porc_imp2) as porc_imp2,
    SUM(e1_va2) AS e1_va2, SUM(porc_va2) as porc_va2
    FROM tmp_union 
    LEFT JOIN var020 ON CONCAT('02',va_cat,'1') = CONCAT(T_TICA,trim(T_GPO),trim(T_PARAM))
    LEFT JOIN subcatego d ON tmp_union.va_cat = d.idCatego AND tmp_union.va_scat = d.idSubcatego
    GROUP BY va_cat,va_scat ORDER BY va_cat,va_scat" ;

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $data = $oSQL->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($data)>0){
      $CategoriaCodigo = $data[0]["va_cat"];
      $CategoriaNombre = $data[0]["catnombre"];
      $SubcategoriaCodigo = $data[0]["va_scat"];
      $SubcategoriaNombre = $data[0]["subcatnombre"];
      $CategoriaSubcategoria = $data[0]["va_cat"]. $data[0]["va_scat"];

      $arrCat = array();
      $arrSubCat = array();

      foreach($data as $row) {
        if($CategoriaCodigo != $row["va_cat"]){
          array_push($arrCat, [
            "CategoriaCodigo" => $CategoriaCodigo,
            "CategoriaNombre" => $CategoriaNombre,
            "TotalGeneralSubcatego" => $arrSubCat
          ]);

          $CategoriaCodigo = $row["va_cat"];
          $CategoriaNombre = $row["catnombre"];
          $SubcategoriaCodigo = $row["va_scat"];
          $SubcategoriaNombre = $row["subcatnombre"];
          $CategoriaSubcategoria = $row["va_cat"]. $row["va_scat"];
          $arrSubCat = array();
        }

        if($CategoriaSubcategoria != $row["va_cat"]. $row["va_scat"]){
          $SubcategoriaCodigo = $row["va_scat"];
          $SubcategoriaNombre = $row["subcatnombre"];
        }

        array_push($arrSubCat,[
          "SubcategoriaCodigo"  => $SubcategoriaCodigo,
          "SubcategoriaNombre"  => $SubcategoriaNombre,
          "TotalPiezas1"        => intval($row["va_pza"]),
          "TotalGramos1"        => floatval($row["va_can"]),
          "TotalImporte1"       => floatval($row["e1_imp"]),
          "TotalPorcentajeImporte1" => floatval($row["porc_imp"]),
          "TotalValorAgregado1" => floatval($row["e1_va"]),
          "TotalPorcentajeValorAgregado1" => floatval($row["porc_va"]),
          "TotalPiezas2"        => intval($row["va_pza2"]),
          "TotalGramos2"        => floatval($row["va_can2"]),
          "TotalImporte2"       => floatval($row["e1_imp2"]),
          "TotalPorcentajeImporte2" => floatval($row["porc_imp2"]),
          "TotalValorAgregado2" => floatval($row["e1_va2"]),
          "TotalPorcentajeValorAgregado2" => floatval($row["porc_va2"])
        ]);

      } // foreach($data as $row)

      // Ultimo registro
      array_push($arrCat, [
        "CategoriaCodigo" => $CategoriaCodigo,
        "CategoriaNombre" => $CategoriaNombre,
        "TotalGeneralSubcatego" => $arrSubCat
      ]);

      $arrData = $arrCat;

    } else {
      $arrData = [];
    }  

  } catch (Exception $e) {
    BorraTemporales($conn);
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  # Borra tablas temporales
  BorraTemporales($conn);

  # Cierra la conexión 
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
FUNCTION CreaDataCompuesta( $data, $dataCltesconVenta, $dataTotalCatego )
{
 
  //return ["End Point en fase de pruebas..."];

  $contenido     = array();
  $arrClientes   = array();
  $arrCategorias = array();
  $arrSubcatego  = array();  
  
  // Organiza Reporte por Cliente
  if(count($data)>0){

    $ClteFil       = $data[0]["va_num"].$data[0]["va_fil"];
    $ClteFilCatego = $data[0]["va_num"].$data[0]["va_fil"].$data[0]["va_cat"];
    $ClteFilCategoSubcatego = $data[0]["va_num"].$data[0]["va_fil"].$data[0]["va_cat"].$data[0]["va_scat"];
    $ClienteCodigo = $data[0]["va_num"];
    $ClienteFilial = $data[0]["va_fil"];
    $ClienteNombre = $data[0]["cc_raso"];
    $CategoCodigo  = $data[0]["va_cat"];
    $CategoNombre  = $data[0]["categonombre"];
    $SubcategoCodigo  = $data[0]["va_scat"];
    $SubcategoNombre  = $data[0]["subcategonombre"];    

    $ClienteStatus  = $data[0]["cc_status"];
    $Lista1         = $data[0]["cc_tipoli"];
    $Lista2         = $data[0]["cc_tipoli2"];
    $TipoParidad    = $data[0]["cc_tparid"];
    $TipoCliente    = $data[0]["cc_ticte"];
    $AgenteCodigo   = $data[0]["va_age"];

    $CategoSubcatego  = $CategoCodigo. $SubcategoCodigo;
    $CatScatDescripc  = $data[0]["catdescripc"];
  
    foreach($data as $row) {
  
      // Cambio de cliente y filial
      if ($ClteFil != $row["va_num"].$row["va_fil"]){
             
        array_push($arrCategorias, [
          "CategoriaCodigo" => $CategoCodigo,
          "CategoriaNombre" => $CategoNombre,
          "Subcategorias"   => $arrSubcatego
        ]);

        $arrClientes = [
          "ClienteCodigo" => trim($ClienteCodigo),
          "ClienteFilial" => trim($ClienteFilial),
          "ClienteNombre" => trim($ClienteNombre),
          "ClienteStatus" => $ClienteStatus,
          "Lista1"        => $Lista1,
          "Lista2"        => $Lista2,
          "TipoParidad"   => $TipoParidad,
          "TipoCliente"   => $TipoCliente,
          "AgentCodigo"   => $AgenteCodigo,
          "Categorias"    => $arrCategorias
        ];

        // Se agrega el array del nuevo cliente a la seccion "contenido"
        array_push($contenido, $arrClientes);

        $ClienteCodigo = $row["va_num"];
        $ClienteFilial = $row["va_fil"];
        $ClienteNombre = $row["cc_raso"];
        $ClienteStatus  = $row["cc_status"];
        $Lista1         = $row["cc_tipoli"];
        $Lista2         = $row["cc_tipoli2"];
        $TipoParidad    = $row["cc_tparid"];
        $TipoCliente    = $row["cc_ticte"];
        $AgenteCodigo   = $row["va_age"];   

        $CategoCodigo  = $row["va_cat"];
        $CategoNombre  = $row["categonombre"];
        $SubcategoCodigo = $row["va_scat"];
        $SubcategoNombre = $row["subcategonombre"];

        $ClteFil       = $row["va_num"].$row["va_fil"];
        $ClteFilCatego = $row["va_num"].$row["va_fil"].$row["va_cat"];
        $ClteFilCategoSubcatego = $row["va_num"].$row["va_fil"].$row["va_cat"].$row["va_scat"];

        $arrCategorias = array();
        $arrSubcatego  = array();

      }

      // Cambio en Categoria
      if($ClteFilCatego != $row["va_num"].$row["va_fil"].$row["va_cat"]){
        
        array_push($arrCategorias, [
          "CategoriaCodigo" => $CategoCodigo,
          "CategoriaNombre" => $CategoNombre,
          "Subcategorias"   => $arrSubcatego
        ]);

        $CategoCodigo     = $row["va_cat"];
        $CategoNombre     = $row["categonombre"];       
        $SubcategoCodigo  = $row["va_scat"];
        $SubcategoNombre  = $row["subcategonombre"];

        $ClteFilCatego = $row["va_num"].$row["va_fil"].$row["va_cat"];
        $ClteFilCategoSubcatego = $row["va_num"].$row["va_fil"].$row["va_cat"].$row["va_scat"];

        $arrSubcatego = array();
      }

      // Cambio en SubCategoria
      if($ClteFilCategoSubcatego != $row["va_num"].$row["va_fil"].$row["va_cat"].$row["va_scat"]){
        $SubcategoCodigo = $row["va_scat"];
        $SubcategoNombre = $row["subcategonombre"];

        $ClteFilCategoSubcatego = $row["va_num"].$row["va_fil"].$row["va_cat"].$row["va_scat"];
      }

      array_push($arrSubcatego, [
        "SubcategoriaCodigo" => $SubcategoCodigo,
        "SubcategoriaNombre" => $SubcategoNombre,
        "Piezas1"            => intval($row["va_pza"]),
        "Gramos1"            => floatval($row["va_can"]),
        "ImporteVenta1"      => floatval($row["e1_imp"]),
        "PorcentajeImporte1" => floatval($row["porc_imp"]),
        "ValorAgregado1"     => floatval($row["e1_va"]),
        "PorcentajeValorAgregado1" => floatval($row["porc_va"]),
        "Piezas2"            => intval($row["va_pza2"]),
        "Gramos2"            => floatval($row["va_can2"]),
        "ImporteVenta2"      => floatval($row["e1_imp2"]),
        "PorcentajeImporte2" => floatval($row["porc_imp2"]),
        "ValorAgregado2"     => floatval($row["e1_va2"]),
        "PorcentajeValorAgregado2" => floatval($row["porc_va2"])
      ]);
    
    } // foreach($data as $row)   
     

    // Ultimo registro
    array_push($arrCategorias, [
      "CategoriaCodigo" => $CategoCodigo,
      "CategoriaNombre" => $CategoNombre,
      "Subcategorias"   => $arrSubcatego
    ]);
    
    $arrClientes = [
      "ClienteCodigo" => trim($ClienteCodigo),
      "ClienteFilial" => trim($ClienteFilial),
      "ClienteNombre" => trim($ClienteNombre),
      "ClienteStatus" => $ClienteStatus,
      "Lista1"        => $Lista1,
      "Lista2"        => $Lista2,
      "TipoParidad"   => $TipoParidad,
      "TipoCliente"   => $TipoCliente,
      "AgentCodigo"   => $AgenteCodigo,
      "Categorias"    => $arrCategorias
    ];

    // Se agrega el array del nuevo cliente a la seccion "contenido"
    array_push($contenido, $arrClientes);
    
  } // count($data)>0

  
  // Presentación final del reporte
  $contenido = [
    "Clientes" => $contenido,
    "ClientesConVenta"  => $dataCltesconVenta,
    "TotalGeneralCategorias" => $dataTotalCatego
  ];
  
  return $contenido; 

} 

/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn){
  $sqlCmd = "DROP TABLE IF EXISTS catego;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS subcatego;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS tmp_tot1;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS tmp_tot2;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS tmp_pzas1;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_imp1;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS tmp_pzasimp1;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_pzasimpnum1;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_1per;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_pzas2;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_imp2;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS tmp_pzasimp2;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_pzasimpnum2;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_2per;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_union;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_repo;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 

  $sqlCmd = "DROP TABLE IF EXISTS tmp_repoimpo;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute(); 
  
}