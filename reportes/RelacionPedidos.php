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

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "relacionpedidos.php";

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
$OficinaDesde = null;     // Código Oficina en que se registra el pedido 
$OficinaHasta = null;     // Código Oficina en que se registra el pedido
$ClienteDesde = null;     // Código del cliente inicial
$FilialDesde  = null;     // Filial del cliente inicial
$ClienteHasta = null;     // Código del cliente final
$FilialHasta  = null;     // Filial del cliente final
$FechaPedidoDesde = null;     // Fecha de registro del pedido
$FechaPedidoHasta = null;     // Fecha de registro del pedido
$FechaCancelacDesde = null;   // Fecha de cancelación operativa
$FechaCancelacHasta = null;   // Fecha de cancelación operativa
$Status         = null;   // Status del pedido
$TipoPedido     = null;   // Tipo de pedido: pedido | servicio
$TipoOrigen     = null;   // Origen del pedido: inteno | externo
$SoloAtrasados  = null;   // Solo pedidos atrasados
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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME del mensaje
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if(! in_array($TipoUsuario, ["C","A","G"])){
      throw new Exception("Valor '". $TipoUsuario ."' NO permitido para 'TipoUsuario'");
    }
  }

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

  if (!isset($_GET["FechaPedidoDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaPedidoDesde' no fue definido.");
  } else {
    $FechaPedidoDesde = $_GET["FechaPedidoDesde"];
    if(!ValidaFormatoFecha($FechaPedidoDesde)){
      throw new Exception("El parametro 'FechaPedidoDesde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["FechaPedidoHasta"])) {
    throw new Exception("El parametro obligatorio 'FechaPedidoHasta' no fue definido.");
  } else {
    $FechaPedidoHasta = $_GET["FechaPedidoHasta"];
    if(!ValidaFormatoFecha($FechaPedidoHasta)){
      throw new Exception("El parametro 'FechaPedidoHasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["FechaCancelacDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaCancelacDesde' no fue definido.");
  } else {
    $FechaCancelacDesde = $_GET["FechaCancelacDesde"];
    if(!ValidaFormatoFecha($FechaCancelacDesde)){
      throw new Exception("El parametro 'FechaCancelacDesde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["FechaCancelacHasta"])) {
    throw new Exception("El parametro obligatorio 'FechaCancelacHasta' no fue definido.");
  } else {
    $FechaCancelacHasta = $_GET["FechaCancelacHasta"];
    if(!ValidaFormatoFecha($FechaCancelacHasta)){
      throw new Exception("El parametro 'FechaCancelacHasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "OficinaDesde", "OficinaHasta",
"ClienteDesde", "FilialDesde", "ClienteHasta", "FilialHasta", 
"FechaPedidoDesde", "FechaPedidoHasta", "FechaCancelacDesde", "FechaCancelacHasta",
"Status", "TipoPedido", "TipoOrigen", "SoloAtrasados", "Pagina");

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

if (isset($_GET["TipoPedido"])) {
  $TipoPedido = $_GET["TipoPedido"];
  if(! in_array($TipoPedido, ["P", "S", "E"]) ){
    $mensaje = "Valor '". $TipoPedido. "' NO permitido para 'TipoPedido'";
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

if (isset($_GET["SoloAtrasados"])) {
  $SoloAtrasados = $_GET["SoloAtrasados"];
  if(! in_array($SoloAtrasados, ["S"]) ){
    $mensaje = "Valor '". $SoloAtrasados. "' NO permitido para 'SoloAtrasados'";
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
  $data = SelectRelacionPedidos($TipoUsuario, $Usuario,$OficinaDesde,$OficinaHasta, 
  $ClienteDesde, $FilialDesde, $ClienteHasta, $FilialHasta, $FechaPedidoDesde, $FechaPedidoHasta,
  $FechaCancelacDesde,$FechaCancelacHasta,$Status,$TipoPedido,$TipoOrigen,$SoloAtrasados);

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
 * @param int $OficinaDesde
 * @param int $OficinaHasta
 * @param int $ClienteDesde
 * @param int $FilialDesde
 * @param int $ClienteHasta
 * @param int $FilialHasta
 * @param string $FechaPedidoDesde
 * @param string $FechaPedidoHasta
 * @param string $FechaCancelacDesde
 * @param string $FechaCancelacHasta
 * @param string $Status
 * @param string $TipoPedido
 * @param string $TipoOrigen
 * @param string $SoloAtrasados
 * @return array
 */
FUNCTION SelectRelacionPedidos($TipoUsuario, $Usuario,$OficinaDesde,$OficinaHasta, 
  $ClienteDesde, $FilialDesde, $ClienteHasta, $FilialHasta, $FechaPedidoDesde, $FechaPedidoHasta,
  $FechaCancelacDesde,$FechaCancelacHasta,$Status,$TipoPedido,$TipoOrigen,$SoloAtrasados) 
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
  
  $OficinaDesde  = str_pad($OficinaDesde, 2, "0", STR_PAD_LEFT);
  $OficinaHasta  = str_pad($OficinaHasta, 2, "0", STR_PAD_LEFT);
  $strClteInic   = str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT);
  $strClteFinal  = str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT);

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.pe_of >= :OficinaDesde AND a.pe_of <= :OficinaHasta
  AND concat(a.pe_num,a.pe_fil) >= :strClteInic
  AND concat(a.pe_num,a.pe_fil) <= :strClteFinal
  AND a.pe_fepe >= :FechaPedidoDesde AND a.pe_fepe <= :FechaPedidoHasta 
  AND a.pe_fecao >= :FechaCancelacDesde AND a.pe_fecao <= :FechaCancelacHasta ";

  if(in_array($TipoUsuario, ["A"])){
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND a.pe_age = :strUsuario ";
  }

  $filtroStatus = "";
  if(isset($Status)){
    $filtroStatus = "AND a.pe_status = :Status ";
  }

  $filtroTipope = "";
  if(isset($TipoPedido)){
    switch ($TipoPedido){
      case "P":
        $filtroTipope = "AND a.pe_tipope='01' ";
        break;
      case "S":
        $filtroTipope = "AND a.pe_tipope='02' ";
        break;
      case "E":
        $filtroTipope = "AND a.pe_tipope='04' ";
        break;
    }    
  }

  $filtroTipoie = "";
  if(isset($TipoOrigen)){
    switch ($TipoOrigen){
      case "I":
        $filtroTipoie="AND a.pe_tipoie='I' ";
        break;
      case "E":
        $filtroTipoie="AND a.pe_tipoie='E' ";
        break;
    }    
  }

  $filtroSoloatrasados = "";
  if(isset($SoloAtrasados)){    
    $filtroSoloatrasados="AND a.pe_fecao::date - current_date < 0 ";

    // Sobrescribe criterio "filtroStatus" porque presentar "SoloAtrasados" solo
    // aplica para pedidos Activos
    $Status = "A";  // Necesario para evitar error en Bindparam()
    $filtroStatus = "AND a.pe_status = :Status ";
    
  }

  try {
    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Borra tabla temporal en caso de que exista
    $sqlCmd = "DROP TABLE IF EXISTS pe_detalle;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   
    
    # Instrucción SELECT para renglones "brutos" que se van a acumular posteriormente
    $sqlCmd = "CREATE TEMPORARY TABLE pe_detalle AS
    SELECT a.pe_of,a.pe_tipope,a.pe_letra,trim(a.pe_ped) pe_ped,a.pe_fepe,a.pe_fecao,
    CASE WHEN a.pe_tipoie='I' THEN b.pe_fepep ELSE c.pe_fepep END AS pe_fepep,
    a.pe_status,a.pe_num,a.pe_fil,a.pe_age,a.pe_tipoie,coalesce(a.pe_canpe,0) pe_canpe,
    CASE WHEN a.pe_ticos = 1 THEN coalesce(a.pe_canpe,0)*coalesce(a.pe_penep,0) 
    ELSE coalesce(a.pe_grape,0)*coalesce(a.pe_penep,0) END AS imp_canpe,
    CASE WHEN a.pe_ticos = 1 THEN coalesce(a.pe_canpe,0)*(coalesce(a.pe_penep,0) - coalesce(a.pe_costo,0)) 
    ELSE coalesce(a.pe_grape,0)*(coalesce(a.pe_penep,0) - coalesce(a.pe_costo,0)) END AS va_imppe,
    coalesce(a.pe_cansu,0) pe_cansu,
    CASE WHEN a.pe_ticos = 1 THEN coalesce(a.pe_cansu,0)*coalesce(a.pe_penes,0)
    ELSE coalesce(a.pe_grasu,0)*coalesce(a.pe_penes,0) END AS imp_cansu,
    CASE WHEN a.pe_ticos = 1 THEN coalesce(a.pe_cansu,0)*(coalesce(a.pe_penes,0) - coalesce(a.pe_costo,0)) 
    ELSE coalesce(a.pe_grasu,0)*(coalesce(a.pe_penes,0) - coalesce(a.pe_costo,0)) END AS va_impsu,
    CASE WHEN a.pe_tipoie='I' THEN coalesce(b.pe_canpe,0) ELSE coalesce(c.pe_canpe,0) END AS pe_canpep,
    CASE WHEN a.pe_tipoie='I' THEN coalesce(b.pe_canpro,0) ELSE coalesce(c.pe_canpro,0) END AS pe_canpro,
    a.pe_ticos,a.pe_penep, a.pe_penes,a.pe_grape,a.pe_grasu 
    FROM ped100 a 
    LEFT JOIN ped150 b ON concat(a.pe_letra,a.pe_ped,a.pe_lin,a.pe_clave) = concat(b.pe_letra,b.pe_ped,b.pe_lin,b.pe_clave) 
    LEFT JOIN ped160 c ON concat(a.pe_letra,a.pe_ped,a.pe_lin,a.pe_clave) = concat(c.pe_letra,c.pe_ped,c.pe_lin,c.pe_clave) 
    $where $filtroStatus $filtroTipope $filtroTipoie $filtroSoloatrasados ";
    $sqlCmd .= "ORDER BY a.pe_of,a.pe_letra,a.pe_ped,a.pe_tipope;";
  
    //var_dump($sqlCmd);

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaPedidoDesde", $FechaPedidoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaPedidoHasta", $FechaPedidoHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaCancelacDesde", $FechaCancelacDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaCancelacHasta", $FechaCancelacHasta, PDO::PARAM_STR);
       
    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario", $strUsuario, PDO::PARAM_STR);
    }
    if(isset($Status)){
      $oSQL-> bindParam(":Status", $Status, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    

    // Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      $sqlCmd = "DROP TABLE IF EXISTS pe_detalle;";
      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->execute();
      $conn = null;   // Cierra la conexión 
      return [];
    }

    # Acumula por pedido generando array que se va a devolver

    // dRendon: Debido a restricciones de PostGreSQL, utilizo la funcion de agregacion max() 
    // para no incluir todos los campos en la clausula GROUP BY

    $sqlCmd = "SELECT a.pe_of,a.pe_tipope,a.pe_letra,trim(a.pe_ped) pe_ped,
    trim(max(a.pe_num)) pe_num, trim(max(a.pe_fil)) pe_fil,max(a.pe_fepe) pe_fepe,max(a.pe_fepep) pe_fepep,
    max(a.pe_fecao) pe_fecao,max(a.pe_status) pe_status,max(a.pe_tipoie) pe_tipoie,
    max(a.pe_fecao::date - current_date) dias_atras,
    max(99999 + (a.pe_fecao::date - current_date)) as dias_atras_order,
    trim(max(CONCAT(a.pe_of,' ',s_nomsuc))) AS sucursal, trim(max(t_descr)) AS tipopedido,
    SUM(a.pe_canpe) AS pe_canpe,
    SUM(a.pe_cansu) AS pe_cansu,
    (SUM(a.pe_canpe) - SUM(a.pe_cansu)) AS pe_difcia,
    SUM(a.imp_canpe) AS imp_canpe,
    SUM(a.va_imppe) AS va_imppe,
    SUM(a.imp_cansu) AS imp_cansu,
    SUM(a.va_impsu) AS va_impsu,
    (SUM(a.imp_canpe) - SUM(a.imp_cansu)) AS imp_dif,
    (SUM(a.va_imppe) - SUM(a.va_impsu)) AS va_impdif,
    SUM(coalesce(a.pe_canpep,0)) AS pe_canpep,
    SUM(coalesce(a.pe_canpro,0)) AS pe_canpro,
    (SUM(coalesce(a.pe_canpep,0)) - SUM(coalesce(pe_canpro,0))) AS canp_dif
    FROM pe_detalle a
    LEFT JOIN dirsdo ON a.pe_of=s_llave
    LEFT JOIN var020 ON CONCAT('1095',a.pe_tipope)=CONCAT(t_tica,t_gpo,t_clave)
    GROUP BY pe_of,pe_tipope,pe_letra,pe_ped
    ORDER BY pe_of,pe_tipope,dias_atras_order,pe_letra,pe_ped";

    $oSQL = $conn-> prepare($sqlCmd);
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
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
FUNCTION CreaDataCompuesta( $data )
{

  $contenido = array();
  $arrOficinas = array();
  $arrTiposPedido = array();
  $arrPedidos = array();
  $arrFila = array();

  // Detalle de documentos con saldo
  if(count($data)>0){

    $OficinaCodigo = $data[0]["pe_of"];
    $OficinaNombre = $data[0]["sucursal"];
    $TipoPedidoCodigo = $data[0]["pe_tipope"];
    $TipoPedido       = $data[0]["tipopedido"];
    $OficTipoPed   = $data[0]["pe_of"]. $data[0]["sucursal"]. $data[0]["pe_tipope"];
  
    foreach($data as $row) {
      
      // Cambio de Oficina
      if($row["pe_of"] != $OficinaCodigo){

        array_push($arrTiposPedido, [
          "TipoPedidoCodigo" => $TipoPedidoCodigo,
          "TipoPedido" => $TipoPedido,
          "Pedidos"    => $arrPedidos
        ]);

        $arrOficinas = [
          "OficinaFonelliCodigo" => $OficinaCodigo,
          "OficinaFonelliNombre" => $OficinaNombre,
          "TipoPedido" => $arrTiposPedido
        ];
        array_push($contenido, $arrOficinas);

        $OficinaCodigo = $row["pe_of"];
        $OficinaNombre = $row["sucursal"];
        $TipoPedidoCodigo = $row["pe_tipope"];
        $TipoPedido    = $row["tipopedido"];
        $OficTipoPed   = $row["pe_of"]. $row["sucursal"]. $row["pe_tipope"];

        $arrTiposPedido = array();
        $arrPedidos = array();

      }

      // Cambio en tipo de pedido
      if($OficTipoPed != $row["pe_of"]. $row["sucursal"]. $row["pe_tipope"]){
        array_push($arrTiposPedido, [
          "TipoPedidoCodigo" => $TipoPedidoCodigo,
          "TipoPedido" => $TipoPedido,
          "Pedidos"    => $arrPedidos
        ]);

        $TipoPedidoCodigo = $row["pe_tipope"];
        $TipoPedido       = $row["tipopedido"];
        $OficTipoPed   = $row["pe_of"]. $row["sucursal"]. $row["pe_tipope"];

        $arrPedidos = array();

      }

      $arrFila = [
        "PedidoLetra" => $row["pe_letra"],
        "PedidoFolio" => $row["pe_ped"],
        "ClienteCodigo" => $row["pe_num"],
        "ClienteFilial" => $row["pe_fil"],
        "FechaPedido" => $row["pe_fepe"],
        "FechaPedidoProduccion"=> $row["pe_fepep"],
        "FechaCancelacion"=> $row["pe_fecao"],
        "PedidoStatus"=> $row["pe_status"],
        "DiasAtraso"=> $row["dias_atras"],
        "CantidadPedida"=> $row["pe_canpe"],
        "CantidadPedidaImporte"=> $row["imp_canpe"],
        "CantidadSurtida"=> $row["pe_cansu"],
        "CantidadSurtidaImporte"=> $row["imp_cansu"],
        "DiferenciaCantidadSurtido"=> $row["pe_difcia"],
        "DiferenciaImporteSurtido"=> $row["imp_dif"],
        "CantidadPedidaProduccion"=> $row["pe_canpep"],
        "CantidadProducida"=> $row["pe_canpro"],
        "DiferenciaCantidadProducido"=> $row["canp_dif"],
        "InternoExterno"=> $row["pe_tipoie"]
      ];

      // Se agrega el array del nuevo cliente a la seccion "contenido"
      array_push($arrPedidos, $arrFila);

    }   

    array_push($arrTiposPedido, [
      "TipoPedidoCodigo" => $TipoPedidoCodigo,
      "TipoPedido" => $TipoPedido,
      "Pedidos"    => $arrPedidos
    ]);

    // Ultimo registro
    $arrOficinas = [
      "OficinaFonelliCodigo" => $OficinaCodigo,
      "OficinaFonelliNombre" => $OficinaNombre,
      "TipoPedido" => $arrTiposPedido
    ];
    array_push($contenido, $arrOficinas);

  }  // foreach($data as $row)


  /*
  $contenido = [
    "Pedidos" => $arrPedidos,
  ];
*/

  return $contenido; 

}