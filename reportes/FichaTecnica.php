<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Ficha Técnica
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "FichaTecnica.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$arrClte  = [];     // arreglo asociativo con registros del cliente
$arrVtas1 = [];     // arreglo asociativo con ventas en el primer periodo
$arrVtas2 = [];     // arreglo asociativo con ventas en el segundo periodo
$arrResumCart = []; // arreglo asociativo con resumen por cartera
$arrPedidos   = []; // arreglo asociativo con pedidos activos
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario    = null;     // Tipo de usuario
$Usuario        = null;     // Id del usuario (cliente, agente o gerente)
$ClienteCodigo  = null;     // Código del cliente 
$FilialDesde    = null;     // Filial del cliente inicial
$FilialHasta    = null;     // Filial del cliente final
$Fecha1Desde    = null;     // Fecha inicial primer periodo
$Fecha1Hasta    = null;     // Fecha final primer periodo
$Fecha2Desde    = null;     // Fecha inicial segundo periodo
$Fecha2Hasta    = null;     // Fecha final segundo periodo
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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if(! in_array($TipoUsuario, ["C","A","G"])){
      throw new Exception("Valor '". $TipoUsuario ."' NO permitido para 'TipoUsuario'");
    }
    if($TipoUsuario == "A" && !isset($_GET["Usuario"])){
      throw new Exception("Debe indicar un valor para 'Usuario' cuando 'TipoUsuario' es 'A'");
    }
  }

  if (!isset($_GET["ClienteCodigo"])) {
    throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
  } else {
    $ClienteCodigo = $_GET["ClienteCodigo"];
  }

  if (!isset($_GET["FilialDesde"])) {
    throw new Exception("El parametro obligatorio 'FilialDesde' no fue definido.");
  } else {
    $FilialDesde = $_GET["FilialDesde"] ;
  }

  if (!isset($_GET["FilialHasta"])) {
    throw new Exception("El parametro obligatorio 'FilialHasta' no fue definido.");
  } else {
    $FilialHasta = $_GET["FilialHasta"] ;
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

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario","Usuario","ClienteCodigo","FilialDesde",
"FilialHasta","Fecha1Desde","Fecha1Hasta","Fecha2Desde","Fecha2Hasta","Pagina");

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

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
 
  $arrClte = SelectClte($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde, $FilialHasta);

  if(count($arrClte)>0){
    $arrVtas1 = SelectVentas($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde, 
    $FilialHasta,$Fecha1Desde,$Fecha1Hasta);

    $arrVtas2 = SelectVentas($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde, 
    $FilialHasta,$Fecha2Desde,$Fecha2Hasta);

    $arrResumCart = SelectCartera($TipoUsuario,$Usuario,$ClienteCodigo,
    $FilialDesde,$FilialHasta,$Fecha2Hasta);

    $arrPedidos = SelectPedidos($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde,$FilialHasta);
  }

  # Asigna código de respuesta HTTP por default
  http_response_code(200);

  # Compone el objeto JSON que devuelve el endpoint
  $numFilas = count($arrClte);
  //$totalPaginas = ceil($numFilas/K_FILASPORPAGINA);
  $totalPaginas = 1;

  if($numFilas > 0){
    $codigo = K_API_OK;
    $mensaje = "success";
  } else {
    $codigo = K_API_NODATA;
    $mensaje = "data not found";
  }

  $dataCompuesta = CreaDataCompuesta($arrClte, $arrVtas1, $arrVtas2, $arrResumCart, $arrPedidos);

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
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
FUNCTION CreaDataCompuesta($arrClte, $arrVtas1, $arrVtas2, $arrResumCart, $arrPedidos)
{

  //return ["End Point en fase de DESARROLLO..."];

  $contenido      = array();
  $nodoVtas1      = array();
  $nodoVtas2      = array();
  $nodoResumCart  = array();
  $nodoPedidos    = array();

  # Ventas primer periodo
  # ----------------------------------------------
  $arrCategorias = array();
  $arrSubcatego  = array();  
  
  if(count($arrVtas1) > 0){
    $CategoCodigo     = $arrVtas1[0]["e1_cat"];
    $CategoNombre     = $arrVtas1[0]["desc_cat"];
    $SubcategoCodigo  = $arrVtas1[0]["e1_scat"];
    $SubcategoNombre  = $arrVtas1[0]["desc_scat"]; 

    foreach($arrVtas1 as $row){

      if($CategoCodigo <> $row["e1_cat"]){    
        array_push($arrCategorias, [
          "CategoriaCodigo" => $CategoCodigo,
          "CategoriaNombre" => $CategoNombre,
          "Subcategorias"   => $arrSubcatego
        ]);

        $CategoCodigo     = $row["e1_cat"];
        $CategoNombre     = $row["desc_cat"];
        $SubcategoCodigo  = $row["e1_scat"];
        $SubcategoNombre  = $row["desc_scat"]; 
        $arrSubcatego     = array();
      }

      array_push($arrSubcatego, [
        "SubCategoriaCodigo"  => $row["e1_scat"],
        "SubcategoriaNombre"  => $row["desc_scat"],
        "Piezas"    => intval($row["sum_pza"]),
        "Gramos"    => floatval($row["sum_can"]),
        "Importe"   => floatval($row["sum_imp"]),
        "ValorAgregado" => floatval($row["sum_va"])
      ]);

    } // foreach($arrVtas1 as $row)

    // Ultimo registro
    array_push($arrCategorias, [
      "CategoriaCodigo" => $CategoCodigo,
      "CategoriaNombre" => $CategoNombre,
      "Subcategorias"   => $arrSubcatego
    ]);

    $nodoVtas1 = $arrCategorias;

  }

  # Ventas segundo periodo
  # ----------------------------------------------
  $arrCategorias = array();
  $arrSubcatego  = array();  
  
  if(count($arrVtas2) > 0){
    $CategoCodigo     = $arrVtas2[0]["e1_cat"];
    $CategoNombre     = $arrVtas2[0]["desc_cat"];
    $SubcategoCodigo  = $arrVtas2[0]["e1_scat"];
    $SubcategoNombre  = $arrVtas2[0]["desc_scat"]; 

    foreach($arrVtas2 as $row){

      if($CategoCodigo <> $row["e1_cat"]){    
        array_push($arrCategorias, [
          "CategoriaCodigo" => $CategoCodigo,
          "CategoriaNombre" => $CategoNombre,
          "Subcategorias"   => $arrSubcatego
        ]);

        $CategoCodigo     = $row["e1_cat"];
        $CategoNombre     = $row["desc_cat"];
        $SubcategoCodigo  = $row["e1_scat"];
        $SubcategoNombre  = $row["desc_scat"]; 
        $arrSubcatego     = array();
      }

      array_push($arrSubcatego, [
        "SubCategoriaCodigo"  => $row["e1_scat"],
        "SubcategoriaNombre"  => $row["desc_scat"],
        "Piezas"    => intval($row["sum_pza"]),
        "Gramos"    => floatval($row["sum_can"]),
        "Importe"   => floatval($row["sum_imp"]),
        "ValorAgregado" => floatval($row["sum_va"])
      ]);

    } // foreach($arrVtas2 as $row)

    // Ultimo registro
    array_push($arrCategorias, [
      "CategoriaCodigo" => $CategoCodigo,
      "CategoriaNombre" => $CategoNombre,
      "Subcategorias"   => $arrSubcatego
    ]);

    $nodoVtas2 = $arrCategorias;

  }


  # Resumen de Cartera
  # ---------------------------------------------
  if(count($arrResumCart) > 0){
    foreach($arrResumCart as $row){
      array_push($nodoResumCart, [
        "TipoCarteraCodigo"       => $row["sc_tica"],
        "TipoCarteraDescripc"     => $row["desc_cart"],
        "TipoCarteraSaldo"        => floatval($row["sum_saldo"]),
        "TipoCarteraSaldoVencido" => floatval($row["sum_saldovenc"])
      ]);
    }
  }

  # Pedidos activos
  # ---------------------------------------------
  if(count($arrPedidos) > 0){

    $nodoPedidos = [
      "PedidosNumero" => intval($arrPedidos["num_pedidos"]),
      "Piezas"        => floatval($arrPedidos["sum_canpe"]),
      "Gramos"        => floatval($arrPedidos["sum_grape"]),
      "Importe"       => floatval($arrPedidos["imp_piezas"] + $arrPedidos["imp_gramos"])
    ];
  
  }


  # Presentación final del reporte
  # ---------------------------------------------
  $contenido = [
    "ClienteCodigo"   => trim($arrClte["cc_num"]),
    "ClienteNombre"   => $arrClte["cc_raso"],
    "FilialInicial"   => $arrClte["fil_inic"],
    "FilialFinal"     => $arrClte["fil_final"],
    "Lista1Codigo"    => $arrClte["cc_tipoli"],
    "Lista1Descripc"  => $arrClte["desc_lista1"],
    "Lista2Codigo"    => $arrClte["cc_tipoli2"],
    "Lista2Descripc"  => $arrClte["desc_lista2"],
    "LimiteCredito"   => $arrClte["cc_limcre"],
    "PlazoPago"       => $arrClte["cc_plazo"]. ($arrClte["cc_plazo"] <> 0 ? " Días" : " CONTADO"),
    "FechaAlta"       => $arrClte["cc_alta"],
    "ClienteStatus"   => $arrClte["cc_status"],
    "TipoClienteCodigo"   => $arrClte["cc_ticte"],
    "TipoClienteDescripc" => $arrClte["desc_ticte"],
    "TipoParidadCodigo"   => $arrClte["cc_tparid"],
    "TipoParidadDescripc" => $arrClte["cc_tparid"] == "N" ? "NORMAL" : "ESPECIAL",
    "VentasAnioAnterior"  => $nodoVtas1,
    "VentasAnioActual"    => $nodoVtas2,
    "ResumenCartera"      => $nodoResumCart,
    "PedidosActivos"      => $nodoPedidos
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

  $sqlCmd = "DROP TABLE IF EXISTS esma;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   
  
  $sqlCmd = "DROP TABLE IF EXISTS esta;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS curSaldo;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS curSaldoDoc;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

  $sqlCmd = "DROP TABLE IF EXISTS curSaldoVenc;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();   

}

/**
 * Busca al cliente indicado (primera filial encontrada) y devuelve
 * un array con los datos que se van a mostrar en el front
 * # Va a devolver la primera filial del cliente 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $ClienteCodigo
 * @param int $FilialDesde
 * @param int $FilialHasta
 * @return array
 */
FUNCTION SelectClte($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde, $FilialHasta)
{
  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

  $where = "";      // Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $data  = array(); // Arreglo que se va a devolver

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

  $strClteInic   = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  try {

    # Se conecta a la base de datos
    require_once "../db/conexion.php";  

    # Handler para la conexión a la base de datos
    //$conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Obtiene datos del cliente.
    # Va a devolver la primera filial del cliente 
    # -------------------------------------------

    # Construyo dinamicamente la condicion WHERE
    //cc_num= LPAD(:pNum,6,' ') AND cc_fil BETWEEN LPAD(:pFilInic,3,' ') AND LPAD(:pFilFinal,3,' ') "
    $where = "WHERE concat(replace(a.cc_num,' ','0'),replace(a.cc_fil,' ','0')) >= :strClteInic
    AND concat(replace(a.cc_num,' ','0'),replace(a.cc_fil,' ','0')) <= :strClteFinal ";

    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND a.cc_age = :strUsuario ";
    }

    # Preparo la consulta y la ejecuto
    $sqlCmd="SELECT a.cc_num, $FilialDesde AS fil_inic, $FilialHasta as fil_final,
    trim(a.cc_raso) as cc_raso,a.cc_tipoli,a.cc_tipoli2,
    a.cc_limcre,a.cc_plazo,a.cc_alta,a.cc_status,a.cc_ticte,a.cc_tparid,
    a.cc_tipos,a.cc_asuci,a.cc_suci,a.cc_ofi,a.cc_limcrec,
    trim(b.t_descr) as desc_lista1,trim(c.t_descr) as desc_lista2,trim(d.t_descr) AS desc_ticte 
		FROM cli010 a 
		LEFT JOIN var020 b ON (b.t_tica='10' AND b.t_gpo='93' AND b.t_clave=cc_tipoli)
		LEFT JOIN var020 c ON (c.t_tica='10' AND c.t_gpo='93' AND c.t_clave=cc_tipoli2)
		LEFT JOIN var020 d ON (d.t_tica='91' AND d.t_gpo=cc_ticte)
    $where
    ORDER BY cc_num,cc_fil";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
    }

    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    

    if($numRows < 1){
      $conn = null;
      return [];
    }		

    // Va a devover un solo renglon
    $data = $oSQL->fetch(PDO::FETCH_ASSOC);

  } catch (Exception $e) {    
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  return $data;
}

FUNCTION SelectVentas($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde, 
$FilialHasta,$FechaDesde,$FechaHasta)
{
  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

  $where    = "";        // Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $arrESMA  = array();   // Arreglo con ventas registradas en tabla cli040
  $arrESTA  = array();   // Arreglo con ventas por artículo tabla inv050
  $data     = array();   // Arreglo que se va a devolver

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

  $strClteInic   = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  try {

    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  

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
    
    #	Obtengo ventas registradas en tabla cli040
    # -------------------------------------------------------------------------
    $where = "WHERE concat(replace(e1_num,' ','0'),replace(e1_fil,' ','0')) >= :strClteInic
    AND concat(replace(e1_num,' ','0'),replace(e1_fil,' ','0')) <= :strClteFinal
    AND (e1_fecha BETWEEN :pFechaDesde AND :pFechaHasta) 
    AND concat(e1_cat,e1_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND e1_age = :strUsuario ";
    }

    $sqlCmd="CREATE TEMPORARY TABLE esma AS
    SELECT e1_num, e1_cat, e1_scat, sum(e1_imp) as sum_imp, sum(e1_va) as sum_va 
    FROM cli040 
    $where
    GROUP BY e1_num,e1_cat,e1_scat ";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(":strClteInic",$strClteInic,PDO::PARAM_STR);
    $oSQL->bindParam(":strClteFinal",$strClteFinal,PDO::PARAM_STR);
    $oSQL->bindParam(':pFechaDesde',$FechaDesde,PDO::PARAM_STR);
    $oSQL->bindParam(':pFechaHasta',$FechaHasta,PDO::PARAM_STR);
    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
    }

    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    if($numRows > 0){
      $sqlCmd="SELECT * FROM esma ORDER BY e1_cat,e1_scat";
      $oSQL=$conn->prepare($sqlCmd);
      $oSQL->execute(); 
      $arrESMA = $oSQL->fetchAll(PDO::FETCH_ASSOC);      
    } else {
      BorraTemporales($conn);
      return [];
    }

    #	VENTAS POR ARTICULO (para obtener piezas)
    #	Obtengo ventas registradas en INV050
    #---------------------------------------------------------------------------
    $where = "WHERE concat(replace(va_num,' ','0'),replace(va_fil,' ','0')) >= :strClteInic
    AND concat(replace(va_num,' ','0'),replace(va_fil,' ','0')) <= :strClteFinal
    AND (va_fecha BETWEEN :pFechaDesde AND :pFechaHasta ) 
    AND concat(va_cat,va_scat) IN (SELECT concat(idCatego,idSubcatego) as llave FROM subcatego) ";
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND va_age = :strUsuario ";
    }

    $sqlCmd="CREATE TEMPORARY TABLE esta AS
    SELECT va_num, va_cat, va_scat, sum(va_can+va_cane) as sum_can,
    sum(va_pza+va_pzae) as sum_pza, sum(va_venta+va_ventae) as sum_venta 
    FROM inv050 
    $where
    GROUP BY va_num,va_cat,va_scat ";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(":strClteInic",$strClteInic,PDO::PARAM_STR);
    $oSQL->bindParam(":strClteFinal",$strClteFinal,PDO::PARAM_STR);
    $oSQL->bindParam(':pFechaDesde',$FechaDesde,PDO::PARAM_STR);
    $oSQL->bindParam(':pFechaHasta',$FechaHasta,PDO::PARAM_STR);
    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
    }

    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    if($numRows > 0){
      $sqlCmd="SELECT * FROM esta ORDER BY va_cat,va_scat";
      $oSQL=$conn->prepare($sqlCmd);
      $oSQL->execute(); 
      $arrESTA = $oSQL->fetchAll(PDO::FETCH_ASSOC);      
    } else {
      BorraTemporales($conn);
      return [];
    }
    
    # Crea tabla para devolver el array utilizado para la presentación
    $sqlCmd="SELECT e1_num,e1_cat,e1_scat,trim(c.nameCatego) as desc_cat,trim(c.nameSubcatego) as desc_scat,
    sum_pza,sum_can,sum_imp,sum_va
    FROM esma
    LEFT OUTER JOIN esta ON esta.va_cat=esma.e1_cat AND esta.va_scat=esma.e1_scat 
    LEFT OUTER JOIN subcatego c ON (TRIM(c.idCatego)=trim(esma.e1_cat) AND TRIM(c.idSubcatego)=TRIM(esma.e1_scat))  
    ORDER BY e1_cat,e1_scat" ;

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    if($numRows > 0){      
      $data = $oSQL->fetchAll(PDO::FETCH_ASSOC);      
    }             

  } catch (Exception $e) {    
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  BorraTemporales($conn); 

  return $data;
}

/**
 * Resumen de Cartera
 * a. Obtiene Saldo neto a la fecha de corte indicada
 * b. Obtiene Saldo vencido a la fecha de corte indicada
 * c. Combina ambas tablas para presentar la informacion
 */
FUNCTION SelectCartera($TipoUsuario,$Usuario,$ClienteCodigo,
$FilialDesde,$FilialHasta,$Fecha2Hasta)
{
  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

  $where  = "";         // Variable para almacenar dinamicamente la clausula WHERE del SELECT  
  $data   = array();    // Arreglo con datos que se van a devolver

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

  $strClteInic   = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));

  try {

    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  

    # Handler para la conexión a la base de datos
    $conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # Saldo general a la fecha de corte
    # ---------------------------------------------------
    $where = "WHERE concat(replace(sc_num,' ','0'),replace(sc_fil,' ','0')) >= :strClteInic
    AND concat(replace(sc_num,' ','0'),replace(sc_fil,' ','0')) <= :strClteFinal
    AND sc_feex <= :pFechaHasta ";
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND sc_age = :strUsuario ";
    }

    $sqlCmd="CREATE TEMPORARY TABLE curSaldo AS
    SELECT sc_num,sc_tica,sum(sc_imp+sc_iva) AS sum_saldo
    FROM cli020 
    $where    
    GROUP BY sc_num,sc_tica ";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(":strClteInic",$strClteInic,PDO::PARAM_STR);
    $oSQL->bindParam(":strClteFinal",$strClteFinal,PDO::PARAM_STR);
    $oSQL->bindParam(':pFechaHasta',$Fecha2Hasta,PDO::PARAM_STR);
    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario" , $strUsuario, PDO::PARAM_STR);
    }

    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    # Saldo por documento a la fecha de corte
    # ---------------------------------------------------
    $where = "WHERE concat(replace(sc_num,' ','0'),replace(sc_fil,' ','0')) >= :strClteInic
    AND concat(replace(sc_num,' ','0'),replace(sc_fil,' ','0')) <= :strClteFinal
    AND sc_feex <= :pFechaHasta ";
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND sc_age = :strUsuario ";
    }

    $sqlCmd="CREATE TEMPORARY TABLE curSaldoDoc AS
    SELECT sc_num,sc_fil,sc_tica,sc_serie,sc_apl,MAX(sc_feve) sc_feve,
    sum(sc_imp) as sum_imp,sum(sc_iva) as sum_iva,
    sum(sc_imp+sc_iva) as sum_saldo
    FROM cli020 
    $where
    GROUP BY sc_num,sc_fil,sc_tica,sc_serie,sc_apl 
    HAVING sum(sc_imp+sc_iva) <> 0";
    //HAVING sum_saldo<>0 ";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(":strClteInic",$strClteInic,PDO::PARAM_STR);
    $oSQL->bindParam(":strClteFinal",$strClteFinal,PDO::PARAM_STR);
    $oSQL->bindParam(':pFechaHasta',$Fecha2Hasta,PDO::PARAM_STR);
    if($TipoUsuario == "A"){
      $oSQL-> bindParam(":strUsuario" ,$strUsuario,PDO::PARAM_STR);
    }

    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    # Saldo vencido a la fecha de corte
    # --------------------------------------------------
    $sqlCmd="CREATE TEMPORARY TABLE curVenc AS
    SELECT sc_tica, sum(sum_saldo) as sum_saldovenc 
    FROM curSaldoDoc 
    WHERE sc_feve < :pFechaHasta
    GROUP BY sc_tica ";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(':pFechaHasta',$Fecha2Hasta,PDO::PARAM_STR);
    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    
    $sqlCmd="SELECT curSaldo.sc_tica,trim(var020.t_descr) as desc_cart,
    COALESCE(curSaldo.sum_saldo,0) sum_saldo, 
    COALESCE(curVenc.sum_saldovenc,0) sum_saldovenc 
    FROM curSaldo 
    LEFT JOIN curVenc ON curSaldo.sc_tica=curVenc.sc_tica
    LEFT OUTER JOIN var020 ON (var020.t_tica='10' AND var020.t_gpo='88' AND var020.t_clave=curSaldo.sc_tica) 
    ORDER BY sc_tica;";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    if($numRows > 0){      
      $data = $oSQL->fetchAll(PDO::FETCH_ASSOC); 
    }             

  } catch (Exception $e) {    
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }
    
  BorraTemporales($conn); 

  return $data;

}

FUNCTION SelectPedidos($TipoUsuario,$Usuario,$ClienteCodigo,$FilialDesde,$FilialHasta)
{
  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

  $where  = "";         // Variable para almacenar dinamicamente la clausula WHERE del SELECT    
  $data   = array();    // Arreglo con datos que se van a devolver

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

  $strClteInic   = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialDesde , 3, " ", STR_PAD_LEFT));
  $strClteFinal  = str_replace(' ','0',str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT). str_pad($FilialHasta , 3, " ", STR_PAD_LEFT));


  try {

    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  

    # Handler para la conexión a la base de datos
    $conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # Obtiene pedidos activos
    # -------------------------------------------------------------
    $where = "WHERE concat(replace(pe_num,' ','0'),replace(pe_fil,' ','0')) >= :strClteInic
    AND concat(replace(pe_num,' ','0'),replace(pe_fil,' ','0')) <= :strClteFinal
    AND pe_fecs IS NULL AND pe_status='A' ";			
    if(in_array($TipoUsuario, ["A"])){
      // Solo aplica filtro cuando el usuario es un agente
      $where .= "AND pe_age = :strUsuario ";
    }

    $sqlCmd="SELECT count(distinct(concat(pe_letra,pe_ped))) as num_pedidos,
    SUM(pe_canpe) as sum_canpe, sum(pe_grape) as sum_grape,
    sum(
      CASE 
      WHEN pe_ticos=1 THEN pe_canpe*pe_penep
      ELSE 0
      END) imp_piezas, 
    sum(
      CASE
      WHEN pe_ticos=2 THEN pe_grape*pe_penep
      ELSE 0
      END) imp_gramos 
    FROM ped100 $where ";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(":strClteInic",$strClteInic,PDO::PARAM_STR);
    $oSQL->bindParam(":strClteFinal",$strClteFinal,PDO::PARAM_STR);    
    $oSQL->execute();
    $numRows=$oSQL->rowCount();

    if($numRows > 0){      
      $data = $oSQL->fetch(PDO::FETCH_ASSOC); 
    }                 
  
  } catch (Exception $e) {    
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }
    
  BorraTemporales($conn); 

  return $data;
}

