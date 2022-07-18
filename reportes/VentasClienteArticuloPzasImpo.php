<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Ventas por Cliente y Artículo (ordenado por piezas o importe)
 * ------------------------------------------------------------------------------
 * Este servicio maneja el caso en que el reporte se ordena por Piezas o Importe.
 * Cuando el reportes se ordena por Categoría se escribió el servicio VentasClienteArticulo.php
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ventasclientearticulopzasimpo.php";

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
$FechaDesde   = null;     // Fecha de registro inicial
$FechaHasta   = null;     // Fecha de registro final
$ClienteDesde = null;     // Código del cliente inicial
$FilialDesde  = null;     // Filial del cliente inicial
$ClienteHasta = null;     // Código del cliente final
$FilialHasta  = null;     // Filial del cliente final
$CategoriaDesde = null;   // Código de categoria inicial
$SubcategoDesde = null;   // Código de Subcategoria inicial
$CategoriaHasta = null;   // Código de categoria final
$SubcategoHasta = null;   // Código de Subcategoria final
$TipoArticulo = null;     // Tipo de artículo: L=Línea | E=Especiales
$TipoOrigen   = null;     // Origen de producción del artículo: I=inteno | E=externo
$OrdenReporte = null;     // Orden del reporte: C=Categoría | P=Piezas | I=Importe
$Presentacion = null;     // Presentación del reporte: D=Detallado | R=Resumido
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

  if (!isset($_GET["FechaDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaDesde' no fue definido.");
  } else {
    $FechaDesde = $_GET["FechaDesde"];
    if(!ValidaFormatoFecha($FechaDesde)){
      throw new Exception("El parametro 'FechaDesde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

  if (!isset($_GET["FechaHasta"])) {
    throw new Exception("El parametro obligatorio 'FechaHasta' no fue definido.");
  } else {
    $FechaHasta = $_GET["FechaHasta"];
    if(!ValidaFormatoFecha($FechaHasta)){
      throw new Exception("El parametro 'FechaHasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
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

  if (!isset($_GET["LineaDesde"])) {
    throw new Exception("El parametro obligatorio 'LineaDesde' no fue definido.");
  } else {
    $LineaDesde = $_GET["LineaDesde"] ;
  }

  if (!isset($_GET["LineaHasta"])) {
    throw new Exception("El parametro obligatorio 'LineaHasta' no fue definido.");
  } else {
    $LineaHasta = $_GET["LineaHasta"] ;
  }

  if (!isset($_GET["ClaveDesde"])) {
    throw new Exception("El parametro obligatorio 'ClaveDesde' no fue definido.");
  } else {
    $ClaveDesde = $_GET["ClaveDesde"] ;
  }

  if (!isset($_GET["ClaveHasta"])) {
    throw new Exception("El parametro obligatorio 'ClaveHasta' no fue definido.");
  } else {
    $ClaveHasta = $_GET["ClaveHasta"] ;
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

  if (!isset($_GET["OrdenReporte"])) {
    throw new Exception("El parametro obligatorio 'OrdenReporte' no fue definido.");
  } else {
    $OrdenReporte = $_GET["OrdenReporte"] ;
    if(! in_array($OrdenReporte, ["C","P","I"]) ){
      throw new Exception("Valor '". $OrdenReporte. "' NO permitido para 'OrdenReporte'");
    }  
  }

  if (!isset($_GET["Presentacion"])) {
    throw new Exception("El parametro obligatorio 'Presentacion' no fue definido.");
  } else {
    $Presentacion = $_GET["Presentacion"] ;
    //if(! in_array($Presentacion, ["D","R"]) )
    if(! in_array($Presentacion, ["R"]) ){
      throw new Exception("Valor '". $Presentacion. "' NO permitido para 'Presentacion'");
    }  
  }

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "OficinaDesde", "OficinaHasta",
"FechaDesde", "FechaHasta", "ClienteDesde", "FilialDesde", "ClienteHasta", "FilialHasta", 
"LineaDesde", "LineaHasta", "ClaveDesde", "ClaveHasta", "CategoriaDesde", "SubcategoDesde",
"CategoriaHasta", "SubcategoHasta", "TipoArticulo", "TipoOrigen", "OrdenReporte", 
"Presentacion", "Pagina");

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

if (isset($_GET["TipoArticulo"])) {
  $TipoArticulo = $_GET["TipoArticulo"];
  if(! in_array($TipoArticulo, ["L","E"]) ){
    $mensaje = "Valor '". $TipoArticulo. "' NO permitido para 'TipoArticulo'";
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

  $data = SelectCltePzasImporte($TipoUsuario,$Usuario,$OficinaDesde, 
  $OficinaHasta,$FechaDesde,$FechaHasta,$ClienteDesde,$FilialDesde, 
  $ClienteHasta,$FilialHasta,$LineaDesde,$LineaHasta,$ClaveDesde, 
  $ClaveHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
  $TipoArticulo,$TipoOrigen,$OrdenReporte,$Presentacion,$Pagina);

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
 * @param string $FechaDesde
 * @param string $FechaHasta
 * @param int $ClienteDesde
 * @param int $FilialDesde
 * @param int $ClienteHasta
 * @param int $FilialHasta
 * @param string $LineaDesde
 * @param string $LineaHasta
 * @param string $ClaveDesde
 * @param string $Clavehasta
 * @param string $CategoriaDesde
 * @param string $SubcategoDesde
 * @param string $CategoriaHasta
 * @param string $SubcategoHasta
 * @param string $TipoArticulo
 * @param string $TipoOrigen
 * @param string $OrdenReporte
 * @param string $Presentacion
 * @param string $Pagina
 * @return array
 */
FUNCTION SelectCltePzasImporte($TipoUsuario,$Usuario,$OficinaDesde, 
$OficinaHasta,$FechaDesde,$FechaHasta,$ClienteDesde,$FilialDesde, 
$ClienteHasta,$FilialHasta,$LineaDesde,$LineaHasta,$ClaveDesde, 
$ClaveHasta,$CategoriaDesde,$SubcategoDesde,$CategoriaHasta,$SubcategoHasta,
$TipoArticulo,$TipoOrigen,$OrdenReporte,$Presentacion,$Pagina) 
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

  $OficinaDesde  = str_pad($OficinaDesde, 2, "0", STR_PAD_LEFT);
  $OficinaHasta  = str_pad($OficinaHasta, 2, "0", STR_PAD_LEFT);
  $strClteInic   = str_replace(' ','0',str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  $strLinClaveDesde = $LineaDesde. $ClaveDesde;
  $strLinClaveHasta = $LineaHasta. $ClaveHasta;

  $strCategoDesde   = $CategoriaDesde. $SubcategoDesde;
  $strCategoHasta   = $CategoriaHasta. $SubcategoHasta;

  # Se conecta a la base de datos
  // require_once "../db/conexion.php";

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.va_of >= :OficinaDesde AND a.va_of <= :OficinaHasta
  AND a.va_fecha >= :FechaDesde AND a.va_fecha <= :FechaHasta 
  AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) >= :strClteInic
  AND concat(replace(a.va_num,' ','0'),replace(a.va_fil,' ','0')) <= :strClteFinal 
  AND concat(a.va_lin,a.va_clave) >= :strLinClaveDesde
  AND concat(a.va_lin,a.va_clave) <= :strLinClaveHasta
  AND concat(a.va_cat,a.va_scat) >= :strCategoDesde
  AND concat(a.va_cat,a.va_scat) <= :strCategoHasta
  AND concat(a.va_cat,a.va_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego) ";

  // Solo aplica filtro cuando el usuario es un agente
  if(in_array($TipoUsuario, ["A"])){
    $where .= "AND a.va_age = :strUsuario ";
  }

  $filtroLineaEspec = "";
  if(isset($TipoArticulo)){
    switch ($TipoArticulo){
      case "L":
        $filtroLineaEspec = " AND c.c_tipole = '01' ";
        break;
      case "E":
        $filtroLineaEspec = " AND c.c_tipole = '02' ";
        break;
    }
    $where .=  $filtroLineaEspec;
  }

  $filtroTipoie = "";
  if(isset($TipoOrigen)){
    switch ($TipoOrigen){
      case "I":
        $filtroTipoie = " AND SUBSTR(b.t_param,20,1) = 'I' ";
        break;
      case "E":
        $filtroTipoie = " AND SUBSTR(b.t_param,20,1) = 'E' ";
        break;
    }    
    $where .= $filtroTipoie;
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

    # Borra tablas temporales en caso de que existan
    $sqlCmd = "DROP TABLE IF EXISTS catego;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "DROP TABLE IF EXISTS subcatego;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "DROP TABLE IF EXISTS tmp1;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "DROP TABLE IF EXISTS tmp2;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

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

    # Crea tabla temporal resumida por cliente y categoria/subcategoria
    $sqlCmd = "CREATE TEMPORARY TABLE tmp1 AS 
    SELECT va_num,va_fil,va_cat,va_scat, SUM(va_pza+va_pzae) AS va_pza,
    SUM(va_can+va_cane) AS va_can,SUM(va_venta+va_ventae) AS va_venta
    FROM inv050 a
    LEFT JOIN var020 b ON CONCAT('05',a.va_lin)=concat(b.t_tica,b.t_gpo)
    LEFT JOIN inv010 c ON a.va_lin=c.c_lin AND a.va_clave=c.c_clave
    $where 
    GROUP BY va_num,va_fil,va_cat,va_scat 
    ORDER BY replace(va_num,' ','0'),replace(va_fil,' ','0'),va_cat,va_scat ";

    $oSQL = $conn->prepare($sqlCmd);

    $oSQL-> bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaDesde", $FechaDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaHasta", $FechaHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strLinClaveDesde", $strLinClaveDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strLinClaveHasta", $strLinClaveHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario", $strUsuario, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    

    // Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){

      $sqlCmd = "DROP TABLE IF EXISTS catego;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   
  
      $sqlCmd = "DROP TABLE IF EXISTS subcatego;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   
  
      $sqlCmd = "DROP TABLE IF EXISTS tmp1;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   
  
      $sqlCmd = "DROP TABLE IF EXISTS tmp2;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   

      $conn = null;   // Cierra la conexión 
      return [];

    }
    
    # Crea tabla con detalle del reporte agrupado por cliente, categoria y linea de producto
    # Utilizo la función agregada MAX para no incluir en la clausula GROUP BY todas las columnas
    unset($oSQL);
    $sqlCmd = "CREATE TEMPORARY TABLE tmp2 AS
    SELECT va_num,va_fil,va_cat,va_scat,TRIM(MAX(d.namecatego)) namecatego,
    TRIM(MAX(d.namesubcatego)) namesubcatego,va_lin,va_clave,TRIM(MAX(b.t_descr)) t_descr,
    TRIM(MAX(b.t_ref)) t_ref,TRIM(MAX(c.c_descr)) c_descr,MAX(c.c_tipole) c_tipole,
    sum(va_pza+va_pzae) as va_pza,sum(va_can+va_cane) as va_can,
    sum(va_venta+va_ventae) as va_venta 
    FROM inv050 a 
    LEFT JOIN var020 b ON CONCAT('05',a.va_lin)=concat(b.t_tica,b.t_gpo)
    LEFT JOIN inv010 c ON a.va_lin=c.c_lin AND a.va_clave= c.c_clave
    LEFT JOIN subcatego d ON a.va_cat=d.idCatego AND a.va_scat=d.idSubcatego
    INNER JOIN var020 e ON e.T_TICA='02' AND TRIM(e.T_GPO)=a.va_cat AND SUBSTR(e.T_PARAM,1,1)='1' 
    $where 
    GROUP BY va_cat,va_scat,va_num,va_fil,va_lin,va_clave
    ORDER BY va_cat,va_scat,replace(va_num,' ','0'),replace(va_fil,' ','0'),va_lin,va_clave";


    /* ------ OJO --- OJO --- OJO --- OJO ---
      Hay que ver si se debe retirar esta linea de la clausula WHERE    
      AND concat(a.va_cat,a.va_scat) IN (SELECT concat(idcatego,idsubcatego) as llave FROM subcatego)
      -> segun yo, con que se aplique en la primera consulta es suficiente
    */

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaDesde", $FechaDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":FechaHasta", $FechaHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL-> bindParam(":strLinClaveDesde", $strLinClaveDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strLinClaveHasta", $strLinClaveHasta, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoDesde", $strCategoDesde, PDO::PARAM_STR);
    $oSQL-> bindParam(":strCategoHasta", $strCategoHasta, PDO::PARAM_STR);

    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario", $strUsuario, PDO::PARAM_STR);
    }

    $oSQL-> execute();   
    $numRows = $oSQL->rowCount(); 

    // Si no hay registros con los criterios indicados, devuelve un array vacío
    if($numRows < 1){
      $sqlCmd = "DROP TABLE IF EXISTS catego;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   
  
      $sqlCmd = "DROP TABLE IF EXISTS subcatego;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   
  
      $sqlCmd = "DROP TABLE IF EXISTS tmp1;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   
  
      $sqlCmd = "DROP TABLE IF EXISTS tmp2;";
      $oSQL = $conn-> prepare($sqlCmd);
      $oSQL-> execute();   

      $conn = null;   // Cierra la conexión 
      return [];
    }

    $orderby = '';
    if($OrdenReporte == "P"){
      // Piezas
      $orderby = "ORDER BY a.va_cat,va_scat,a.va_pza DESC,replace(a.va_num,' ','0') DESC,replace(a.va_fil,' ','0') DESC,a.va_lin DESC,a.va_clave DESC";
    } elseif($OrdenReporte == "I") {
      // Importe
      $orderby = "ORDER BY a.va_cat,va_scat,a.va_venta DESC,replace(a.va_num,' ','0') DESC,replace(a.va_fil,' ','0') DESC,a.va_lin DESC,a.va_clave DESC";
    }

    // Une la tabla detallada con la de totales para presentar
    // datos finales.
    unset($oSQL);
    unset($arrData);

    $sqlCmd = "SELECT a.*, 
    round(a.va_pza::numeric(12,2)/b.va_pza::numeric(12,2)*100,2) AS porc_pza, 
    round(a.va_can/b.va_can*100,2) AS porc_can
    FROM tmp2 a LEFT JOIN tmp1 b 
      ON a.va_num = b.va_num AND a.va_fil = b.va_fil 
     AND a.va_cat = b.va_cat AND a.va_scat = b.va_scat 
     $orderby ";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL-> execute();

    $numrows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    // Borra tablas temporales
    $sqlCmd = "DROP TABLE IF EXISTS catego;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "DROP TABLE IF EXISTS subcatego;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "DROP TABLE IF EXISTS tmp1;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

    $sqlCmd = "DROP TABLE IF EXISTS tmp2;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();   

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
FUNCTION CreaDataCompuesta( $data )
{

  $contenido     = array();
  $arrCategorias = array();
  $arrSubcatego  = array();
  $arrDetalle    = array();

  // Organiza Reporte por Cliente
  if(count($data)>0){
    $CategoCodigo    = $data[0]["va_cat"];
    $CategoNombre    = $data[0]["namecatego"];
    $SubcategoCodigo = $data[0]["va_scat"];
    $SubcategoNombre = $data[0]["namesubcatego"];
    $CategoSubcatego = $data[0]["va_cat"]. $data[0]["va_scat"];

    foreach($data as $row) {
      // Cambio de categoria 
      if($row["va_cat"] != $CategoCodigo){

        array_push($arrSubcatego, [
          "SubcategoriaCodigo"  => $SubcategoCodigo,
          "SubcategoriaNombre"  => $SubcategoNombre,
          "Detalle"             => $arrDetalle]);

        //array_push($arrCategorias, [
        array_push($contenido, [
          "CategoriaCodigo" => $CategoCodigo,
          "CategoriaNombre" => $CategoNombre,
          "Subcategorias"   => $arrSubcatego
        ]);

        $SubcategoCodigo = $row["va_scat"];
        $SubcategoNombre = $row["namesubcatego"];
        $CategoCodigo    = $row["va_cat"];
        $CategoNombre    = $row["namecatego"];
        $CategoSubcatego = $row["va_cat"]. $row["va_scat"];

        $arrDetalle   = array();
        $arrSubcatego = array();
      }

      // Cambio de categoría + subcategoria
      if($row["va_cat"]. $row["va_scat"] != $CategoSubcatego){
        array_push($arrSubcatego, [
          "SubcategoriaCodigo"  => $SubcategoCodigo,
          "SubcategoriaNombre"  => $SubcategoNombre,
          "Detalle"             => $arrDetalle]);

        $SubcategoCodigo = $row["va_scat"];
        $SubcategoNombre = $row["namesubcatego"];
        $CategoSubcatego = $row["va_cat"]. $row["va_scat"];

        $arrDetalle = array();
      }

      array_push($arrDetalle, [
        "ClienteCodigo"     => trim($row["va_num"]),
        "ClienteFilial"     => trim($row["va_fil"]),
        "LineaCodigo"       => $row["va_lin"],
        "ArticuloCodigo"    => trim($row["va_clave"]),
        "ArticuloDescripc"  => trim($row["c_descr"]),
        "ArticuloTipo"      => $row["c_tipole"],
        "Piezas"            => intval($row["va_pza"]),
        "PiezasPorcentaje"  => floatval($row["porc_pza"]),
        "Gramos"            => floatval($row["va_can"]),
        "GramosPorcentaje"  => floatval($row["porc_can"]),
        "ImporteVenta"      => floatval($row["va_venta"])
      ]);

    } // foreach($data as $row)

    // Ultimo registro

    array_push($arrSubcatego, [
      "SubcategoriaCodigo"  => $SubcategoCodigo,
      "SubcategoriaNombre"  => $SubcategoNombre,
      "Detalle"             => $arrDetalle]);

    //array_push($arrCategorias, [
    array_push($contenido, [
      "CategoriaCodigo" => $CategoCodigo,
      "CategoriaNombre" => $CategoNombre,
      "Subcategorias"   => $arrSubcatego
    ]);

    //array_push($contenido, $arrCategorias);

  } // count($data)>0

  return $contenido; 
}