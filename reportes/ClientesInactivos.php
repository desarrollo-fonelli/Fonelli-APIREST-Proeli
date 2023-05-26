<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Clientes Inactivos
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
const K_SCRIPTNAME  = "ClientesInactivos.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con datos que se devuelven de las consultas
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario  = null;     // Tipo de usuario
$Usuario      = null;     // Id del usuario (cliente, agente o gerente)
$Token        = null;     // Token obtenido por el usuario al autenticarse
$AgenteDesde  = null;     // Id del agente inicial
$AgenteHasta  = null;     // Id del agente final
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
    if(! in_array($TipoUsuario, ["A","G"])){
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

  # dRendon 05.05.2023 ********************
  # Ahora se va a verificar la identidad del usuario por medio del Token
  # recibido en el Header con Key "Auth" (PHP lo interpreta como "HTTP_AUTH")
  if(!isset($_SERVER["HTTP_AUTH"])) {
    throw new Exception("No se recibio el Token de autenticacion");
  } else {
    $Token = $_SERVER["HTTP_AUTH"];
  }
  // ValidaToken está en ./include/funciones.php
  if (!ValidaToken($conn, $TipoUsuario, $Usuario, $Token)) {    
    throw new Exception("Error de autenticacion.");
  }
  # Fin dRendon 05.05.2023 ****************
  
  if (!isset($_GET["AgenteDesde"])) {
    throw new Exception("El parametro obligatorio 'AgenteDesde' no fue definido.");
  } else {
    $AgenteDesde = $_GET["AgenteDesde"];
  }

  if (!isset($_GET["AgenteHasta"])) {
    throw new Exception("El parametro obligatorio 'AgenteHasta' no fue definido.");
  } else {
    $AgenteHasta = $_GET["AgenteHasta"];
  }

  # dRendon 05.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "A") {
    if (TRIM($AgenteDesde) != $Usuario OR 
        TRIM($AgenteHasta) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }
  # Fin dRendon 05.05.2023 ****************


} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "AgenteDesde", "AgenteHasta", "Pagina");

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
if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
# --------------------------------------------------------------
try {
  $data = SelectData($AgenteDesde,$AgenteHasta,$Pagina);
  
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

$conn = null;   // Cierra conexión

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @param int $AgenteDesde
 * @param int $AgenteHasta
 * @param int $Pagina
 * @return array
 */
FUNCTION SelectData($AgenteDesde,$AgenteHasta,$Pagina)
{

  $arrData  = array();  // Array que se va a devolver
  $where    = "";       // Variable para almacenar dinamicamente la clausula WHERE del SELECT

  $strAgenteDesde = str_pad($AgenteDesde, 2," ",STR_PAD_LEFT);
  $strAgenteHasta = str_pad($AgenteHasta, 2," ",STR_PAD_LEFT);

  // Doy un plazo de hasta Cinco minutos para completar la consulta...
  set_time_limit(300);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";    <-- el script se leyó previamente
  $conn = DB::getConn();

  try {

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    /**
     * Obtengo el detalle de los registros que se van a reportar
     * Voy a utilizar la tabla "edocta" porque ya tiene el resumen de los documentos con saldo <> 0
     */

      # Borra tablas temporales
      BorraTemporales($conn);

      $sqlCmd = "CREATE TEMPORARY TABLE previo AS
      SELECT sac.sc_age, sac.sc_num, sac.sc_fil, MAX(sac.sc_tica) sc_tica,
      MIN(CASE WHEN sac.sc_saldo>0 THEN (sc_feve::date - CURRENT_DATE) ELSE 0 END) AS dias,
      SUM(CASE WHEN sac.sc_tica='1' THEN sac.sc_saldo ELSE 0 END) AS ctacorrmn,
      SUM(CASE WHEN sac.sc_tica='2' THEN sac.sc_saldo ELSE 0 END) AS ctacorroro, 
      SUM(CASE WHEN sac.sc_tica='3' THEN sac.sc_saldo ELSE 0 END) AS ctacorrdlls,
      SUM(CASE WHEN sac.sc_tica='6' THEN sac.sc_saldo ELSE 0 END) AS ctadocmn,
      SUM(CASE WHEN sac.sc_tica='7' THEN sac.sc_saldo ELSE 0 END) AS ctadocoro, 
      SUM(CASE WHEN sac.sc_tica='8' THEN sac.sc_saldo ELSE 0 END) AS ctadocdlls,
      SUM(CASE WHEN sac.sc_tica='1' AND (sac.sc_feve::date < CURRENT_DATE) THEN sac.sc_saldo ELSE 0 END) as ctacorrmnvenc,
      SUM(CASE WHEN sac.sc_tica='2' AND (sac.sc_feve::date < CURRENT_DATE) THEN sac.sc_saldo ELSE 0 END) as ctacorrorovenc,
      SUM(CASE WHEN sac.sc_tica='3' AND (sac.sc_feve::date < CURRENT_DATE) THEN sac.sc_saldo ELSE 0 END) as ctacorrdllsvenc,
      SUM(CASE WHEN sac.sc_tica='6' AND (sac.sc_feve::date < CURRENT_DATE) THEN sac.sc_saldo ELSE 0 END) as ctadocmnvenc,
      SUM(CASE WHEN sac.sc_tica='7' AND (sac.sc_feve::date < CURRENT_DATE) THEN sac.sc_saldo ELSE 0 END) as ctadocorovenc,
      SUM(CASE WHEN sac.sc_tica='8' AND (sac.sc_feve::date < CURRENT_DATE) THEN sac.sc_saldo ELSE 0 END) as ctadocdllsvenc
      FROM edocta sac
      LEFT JOIN cli010 cli ON cli.cc_num=sac.sc_num AND cli.cc_fil=sac.sc_fil
      LEFT JOIN var020 b ON t_tica='10' AND t_gpo='88' AND t_clave=sac.sc_tica
      WHERE sc_age>=lpad(:agenteInic,2,' ') AND sc_age<=lpad(:agenteFinal,2,' ')
        AND cc_status='I'
        AND SUBSTRING(b.t_param,2,1) = '1' 
      GROUP BY sac.sc_age,sac.sc_num,sac.sc_fil 
      ORDER BY sc_age,dias";

      $oSQL = $conn->prepare($sqlCmd);
      $oSQL-> bindParam(":agenteInic" ,$strAgenteDesde);
      $oSQL-> bindParam(":agenteFinal",$strAgenteHasta);
      $oSQL-> execute();
      $numRows = $oSQL->rowCount();

      // Si no encuentra registros devuelve un array vacío
      if($numRows<=0){
        BorraTemporales($conn);
        unset($oSQL);
        unset($conn);
        return [];
      }

      $sqlCmd = "CREATE TEMPORARY TABLE detalle AS
      SELECT sac.*,trim(age.gc_nom) gc_nom,
      trim(cli.cc_raso) cc_raso, trim(cli.cc_suc) cc_suc, cli.cc_tipoli, cli.cc_tipoli2, cli.cc_plazo 
      FROM previo sac
      LEFT JOIN cli010 cli ON cli.cc_num=sac.sc_num AND cli.cc_fil=sac.sc_fil
      LEFT JOIN var030 age ON age.gc_llave=sc_age
      WHERE ctacorrmn<>0 OR ctacorroro<>0 OR ctacorrdlls<>0 
      OR ctadocmn<>0 OR ctadocoro<>0 OR ctadocdlls<>0 ";

      $oSQL = $conn->prepare($sqlCmd);
      $oSQL-> execute();
      $numRows = $oSQL->rowCount();

      /**
       * Agrego la columna que indica si se tienen pedidos y paso a un array
       * la data obtenida
       */
      $sqlcmd = "SELECT d.*, 
      (SELECT 'X' AS pedido FROM peda 
      WHERE pe_num=sc_num AND pe_fil=sc_fil 
        AND pe_status='A' LIMIT 1) AS pedido  
      FROM detalle d ORDER BY sc_age,dias,sc_num,sc_fil";

      $oSQL = $conn->prepare($sqlcmd);
      $oSQL ->execute();
      $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

      //var_dump($arrData); exit;

      unset($oSQL);

  } catch (Exception $e) {

    BorraTemporales($conn);
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  BorraTemporales($conn);
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
  $contenido    = array();
  $arrAgentes   = array();
  $arrClientes  = array();
  $arrSaldos    = array();
  $arrVencidos  = array();

  if(count($data)>0){
    $AgenteCodigo   = trim($data[0]["sc_age"]);
    $AgenteNombre   = $data[0]["gc_nom"];
    $ClienteCodigo  = trim($data[0]["sc_num"]);
    $ClienteFilial  = trim($data[0]["sc_fil"]);
    $ClienteNombre  = $data[0]["cc_raso"];
    $ClienteSucursal= $data[0]["cc_suc"];
    $Lista1 = $data[0]["cc_tipoli"];
    $Lista2 = $data[0]["cc_tipoli2"];
    $Plazo  = $data[0]["cc_plazo"];
    $DiasAtraso = $data[0]["dias"];
    $PedidosActivos = (is_null($data[0]["pedido"]) ? "" : $data[0]["pedido"]);
    $CtaCorrMN    = $data[0]["ctacorrmn"];
    $CtaCorrORO   = $data[0]["ctacorroro"];
    $CtaCorrDLLS  = $data[0]["ctacorrdlls"];
    $CtaDocMN     = $data[0]["ctadocmn"];
    $CtaDocORO    = $data[0]["ctadocoro"];
    $CtaDocDLLS   = $data[0]["ctadocdlls"];
    $CtaCorrMNvenc  = $data[0]["ctacorrmnvenc"];
    $CtaCorrOROvenc = $data[0]["ctacorrorovenc"];
    $CtaCorrDLLSvenc  = $data[0]["ctacorrdllsvenc"];
    $CtaDocMNvenc   = $data[0]["ctadocmnvenc"];
    $CtaDocOROvenc  = $data[0]["ctadocorovenc"];
    $CtaDocDLLSvenc = $data[0]["ctadocdllsvenc"];
    

    $NumFil = $data[0]["sc_num"]. $data[0]["sc_fil"];

    foreach($data as $row){
      
      // Cambio de agente
      if($row["sc_age"] <> $AgenteCodigo){        

        array_push($arrClientes, [
          "ClienteCodigo" => trim($ClienteCodigo),
          "ClienteFilial" => trim($ClienteFilial),
          "ClienteNombre" => $ClienteNombre,
          "ClienteSucursal" => $ClienteSucursal,
          "Lista1"  => $Lista1,
          "Lista2"  => $Lista2,
          "Plazo"   => $Plazo,
          "DiasAtraso" => $DiasAtraso,
          "PedidosActivos" => $PedidosActivos,          
          "SaldosCarteraCliente"  => $arrSaldos,
          "VencidosSaldosCartera" => $arrVencidos
        ]);

        array_push($arrAgentes, [
          "AgenteCodigo"  => $AgenteCodigo,
          "AgenteNombre"  => $AgenteNombre,
          "Clientes"      => $arrClientes
        ]);

        //array_push($contenido, $arrAgentes);

        $AgenteCodigo = trim($row["sc_age"]);
        $AgenteNombre = $row["gc_nom"];
        $ClienteCodigo  = trim($row["sc_num"]);
        $ClienteFilial  = trim($row["sc_fil"]);
        $ClienteNombre  = $row["cc_raso"];
        $ClienteSucursal  = $row["cc_suc"];
        $Lista1 = $row["cc_tipoli"];
        $Lista2 = $row["cc_tipoli2"];
        $Plazo  = $row["cc_plazo"];
        $DiasAtraso = $row["dias"];
        $PedidosActivos = (is_null($row["pedido"]) ? "" : $row["pedido"]);

        $CtaCorrMN    = $row["ctacorrmn"];
        $CtaCorrORO   = $row["ctacorroro"];
        $CtaCorrDLLS  = $row["ctacorrdlls"];
        $CtaDocMN     = $row["ctadocmn"];
        $CtaDocORO    = $row["ctadocoro"];
        $CtaDocDLLS   = $row["ctadocdlls"];
        $CtaCorrMNvenc  = $row["ctacorrmnvenc"];
        $CtaCorrOROvenc = $row["ctacorrorovenc"];
        $CtaCorrDLLSvenc  = $row["ctacorrdllsvenc"];
        $CtaDocMNvenc   = $row["ctadocmnvenc"];
        $CtaDocOROvenc  = $row["ctadocorovenc"];
        $CtaDocDLLSvenc = $row["ctadocdllsvenc"];
        
        $NumFil = $row["sc_num"]. $row["sc_fil"];

        $arrClientes  = array();
        $arrSaldos    = array();
        $arrVencidos  = array();
    
      } // Fin Cambio de agente

      // Cambio de Cliente
      if($row["sc_num"].$row["sc_fil"] <> $NumFil){
        
        if($CtaCorrMN <> 0){
          array_push($arrSaldos, [
            "TipoCarteraCodigo"   => "1",
            "TipoCarteraDescripc" => "CtaCorr_MN",
            "TotalAgenteSaldoTipoCartera" => floatval($CtaCorrMN)]);
        }
        if($CtaCorrORO <> 0){
          array_push($arrSaldos, [
            "TipoCarteraCodigo"   => "2",
            "TipoCarteraDescripc" => "CtaCorr_ORO",
            "TotalAgenteSaldoTipoCartera" => floatval($CtaCorrORO)]);  
        }
        if($CtaCorrDLLS <> 0){
          array_push($arrSaldos, [
            "TipoCarteraCodigo"   => "3",
            "TipoCarteraDescripc" => "CtaCorr_DLLS",
            "TotalAgenteSaldoTipoCartera" => floatval($CtaCorrDLLS)]);
        }
        if($CtaDocMN <> 0){
          array_push($arrSaldos, [
            "TipoCarteraCodigo"   => "6",
            "TipoCarteraDescripc" => "CtaDoc_MN",
            "TotalAgenteSaldoTipoCartera" => floatval($CtaDocMN)]);
        }
        if($CtaDocORO <> 0){
          array_push($arrSaldos, [
            "TipoCarteraCodigo"   => "7",
            "TipoCarteraDescripc" => "CtaDoc_ORO",
            "TotalAgenteSaldoTipoCartera"  => floatval($CtaDocORO)]);
        }
        if($CtaDocDLLS <> 0){
          array_push($arrSaldos, [
            "TipoCarteraCodigo"   => "8",
            "TipoCarteraDescripc" => "CtaDoc_USD",
            "TotalAgenteSaldoTipoCartera"  => floatval($CtaDocDLLS)]);
        }

        if($CtaCorrMNvenc <> 0){
          array_push($arrVencidos, [
            "TipoCarteraCodigo"   => "1",
            "TipoCarteraDescripc" => "CtaCorr_MN",
            "TotalAgenteVencidoTipoCartera" => floatval($CtaCorrMNvenc)]);
        }
        if($CtaCorrOROvenc <> 0){
          array_push($arrVencidos, [
            "TipoCarteraCodigo"   => "2",
            "TipoCarteraDescripc" => "CtaCorr_ORO",
            "TotalAgenteVencidoTipoCartera" => floatval($CtaCorrOROvenc)]);  
        }
        if($CtaCorrDLLSvenc <> 0){
          array_push($arrVencidos, [
            "TipoCarteraCodigo"   => "3",
            "TipoCarteraDescripc" => "CtaCorr_DLLS",
            "TotalAgenteVencidoTipoCartera" => floatval($CtaCorrDLLSvenc)]);
        }
        if($CtaDocMNvenc <> 0){
          array_push($arrVencidos, [
            "TipoCarteraCodigo"   => "6",
            "TipoCarteraDescripc" => "CtaDoc_MN",
            "TotalAgenteVencidoTipoCartera" => floatval($CtaDocMNvenc)]);
        }
        if($CtaDocOROvenc <> 0){
          array_push($arrVencidos, [
            "TipoCarteraCodigo"   => "7",
            "TipoCarteraDescripc" => "CtaDoc_ORO",
            "TotalAgenteVencidoTipoCartera"  => floatval($CtaDocOROvenc)]);
        }
        if($CtaDocDLLSvenc <> 0){
          array_push($arrVencidos, [
            "TipoCarteraCodigo"   => "8",
            "TipoCarteraDescripc" => "CtaDoc_USD",
            "TotalAgenteVencidoTipoCartera"  => floatval($CtaDocDLLSvenc)]);
        }

        array_push($arrClientes, [
          "ClienteCodigo" => $ClienteCodigo,
          "ClienteFilial" => $ClienteFilial,
          "ClienteNombre" => $ClienteNombre,
          "ClienteSucursal" => $ClienteSucursal,
          "Lista1"  => $Lista1,
          "Lista2"  => $Lista2,
          "Plazo"   => $Plazo,
          "DiasAtraso" => $DiasAtraso,
          "PedidosActivos" => $PedidosActivos,
          "SaldosCarteraCliente"  => $arrSaldos,
          "VencidosSaldosCartera" => $arrVencidos
        ]);

        $ClienteCodigo  = trim($row["sc_num"]);
        $ClienteFilial  = trim($row["sc_fil"]);
        $ClienteNombre  = $row["cc_raso"];
        $ClienteSucursal  = $row["cc_suc"];
        $Lista1 = $row["cc_tipoli"];
        $Lista2 = $row["cc_tipoli2"];
        $Plazo  = $row["cc_plazo"];
        $DiasAtraso = $row["dias"];
        $PedidosActivos = (is_null($row["pedido"]) ? "" : $row["pedido"]);

        $CtaCorrMN    = $row["ctacorrmn"];
        $CtaCorrORO   = $row["ctacorroro"];
        $CtaCorrDLLS  = $row["ctacorrdlls"];
        $CtaDocMN     = $row["ctadocmn"];
        $CtaDocORO    = $row["ctadocoro"];
        $CtaDocDLLS   = $row["ctadocdlls"];
        $CtaCorrMNvenc  = $row["ctacorrmnvenc"];
        $CtaCorrOROvenc = $row["ctacorrorovenc"];
        $CtaCorrDLLSvenc  = $row["ctacorrdllsvenc"];
        $CtaDocMNvenc   = $row["ctadocmnvenc"];
        $CtaDocOROvenc  = $row["ctadocorovenc"];
        $CtaDocDLLSvenc = $row["ctadocdllsvenc"];
    
        $NumFil  = $row["sc_num"]. $row["sc_fil"];

        $arrSaldos = array();
        $arrVencidos = array();

      }
    }

    // Ultimo registro

    if($CtaCorrMN <> 0){
      array_push($arrSaldos, [
        "TipoCarteraCodigo"   => "1",
        "TipoCarteraDescripc" => "CtaCorr_MN",
        "TotalAgenteSaldoTipoCartera" => floatval($CtaCorrMN)]);
    }
    if($CtaCorrORO <> 0){
      array_push($arrSaldos, [
        "TipoCarteraCodigo"   => "2",
        "TipoCarteraDescripc" => "CtaCorr_ORO",
        "TotalAgenteSaldoTipoCartera" => floatval($CtaCorrORO)]);  
    }
    if($CtaCorrDLLS <> 0){
      array_push($arrSaldos, [
        "TipoCarteraCodigo"   => "3",
        "TipoCarteraDescripc" => "CtaCorr_DLLS",
        "TotalAgenteSaldoTipoCartera" => floatval($CtaCorrDLLS)]);
    }
    if($CtaDocMN <> 0){
      array_push($arrSaldos, [
        "TipoCarteraCodigo"   => "6",
        "TipoCarteraDescripc" => "CtaDoc_MN",
        "TotalAgenteSaldoTipoCartera" => floatval($CtaDocMN)]);
    }
    if($CtaDocORO <> 0){
      array_push($arrSaldos, [
        "TipoCarteraCodigo"   => "7",
        "TipoCarteraDescripc" => "CtaDoc_ORO",
        "TotalAgenteSaldoTipoCartera"  => floatval($CtaDocORO)]);
    }
    if($CtaDocDLLS <> 0){
      array_push($arrSaldos, [
        "TipoCarteraCodigo"   => "8",
        "TipoCarteraDescripc" => "CtaDoc_USD",
        "TotalAgenteSaldoTipoCartera"  => floatval($CtaDocDLLS)]);
    }

    if($CtaCorrMNvenc <> 0){
      array_push($arrVencidos, [
        "TipoCarteraCodigo"   => "1",
        "TipoCarteraDescripc" => "CtaCorr_MN",
        "TotalAgenteVencidoTipoCartera" => floatval($CtaCorrMNvenc)]);
    }
    if($CtaCorrOROvenc <> 0){
      array_push($arrVencidos, [
        "TipoCarteraCodigo"   => "2",
        "TipoCarteraDescripc" => "CtaCorr_ORO",
        "TotalAgenteVencidoTipoCartera" => floatval($CtaCorrOROvenc)]);  
    }
    if($CtaCorrDLLSvenc <> 0){
      array_push($arrVencidos, [
        "TipoCarteraCodigo"   => "3",
        "TipoCarteraDescripc" => "CtaCorr_DLLS",
        "TotalAgenteVencidoTipoCartera" => floatval($CtaCorrDLLSvenc)]);
    }
    if($CtaDocMNvenc <> 0){
      array_push($arrVencidos, [
        "TipoCarteraCodigo"   => "6",
        "TipoCarteraDescripc" => "CtaDoc_MN",
        "TotalAgenteVencidoTipoCartera" => floatval($CtaDocMNvenc)]);
    }
    if($CtaDocOROvenc <> 0){
      array_push($arrVencidos, [
        "TipoCarteraCodigo"   => "7",
        "TipoCarteraDescripc" => "CtaDoc_ORO",
        "TotalAgenteVencidoTipoCartera"  => floatval($CtaDocOROvenc)]);
    }
    if($CtaDocDLLSvenc <> 0){
      array_push($arrVencidos, [
        "TipoCarteraCodigo"   => "8",
        "TipoCarteraDescripc" => "CtaDoc_USD",
        "TotalAgenteVencidoTipoCartera"  => floatval($CtaDocDLLSvenc)]);
    }

    array_push($arrClientes, [
      "ClienteCodigo" => $ClienteCodigo,
      "ClienteFilial" => $ClienteFilial,
      "ClienteNombre" => $ClienteNombre,
      "ClienteSucursal" => $ClienteSucursal,
      "Lista1"  => $Lista1,
      "Lista2"  => $Lista2,
      "Plazo"   => $Plazo,
      "DiasAtraso" => $DiasAtraso,
      "PedidosActivos" => $PedidosActivos,
      "SaldosCarteraCliente"  => $arrSaldos,
      "VencidosSaldosCartera" => $arrVencidos
    ]);

    array_push($arrAgentes, [
      "AgenteCodigo"  => $AgenteCodigo,
      "AgenteNombre"  => $AgenteNombre,
      "Clientes"      => $arrClientes
    ]);


    //array_push($contenido, $arrAgentes);
    $contenido = $arrAgentes;

  }

  //  $contenido = ["EndPoint en fase de: DESARROLLO..."];

  return $contenido; 
}

/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn){

  $sqlcmd = "DROP TABLE IF EXISTS previo";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS detalle";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS totales_agente";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS total_general";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS encab_agente";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS dataset";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

}
