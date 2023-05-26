<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Indicadores de Venta
 * --------------------------------------------------------------------------
  * dRendon 04.05.2023 
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
const K_SCRIPTNAME  = "IndicadoresVenta.php";

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
$FechaCorte   = null;     // Fecha de corte para considerar datos de venta
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

  # dRendon 04.05.2023 ********************
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
  # Fin dRendon 04.05.2023 ****************

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

  # dRendon 04.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "A") {
    if (TRIM($AgenteDesde) != $Usuario OR 
        TRIM($AgenteHasta) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["FechaCorte"])) {
    throw new Exception("El parametro obligatorio 'FechaCorte' no fue definido.");
  } else {
    $FechaCorte = $_GET["FechaCorte"];
    if(!ValidaFormatoFecha($FechaCorte)){
      throw new Exception("El parametro 'FechaCorte' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");  
    }
  }

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "AgenteDesde", "AgenteHasta", "FechaCorte", "Pagina");

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
  $data = SelectIndicadores($AgenteDesde,$AgenteHasta,$FechaCorte);
  
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
 * @param int $AgenteDesde
 * @param int $AgenteHasta
 * @param string $FechaCorte
 * @return array
 */
FUNCTION SelectIndicadores($AgenteDesde,$AgenteHasta,$FechaCorte)
{

  $arrData  = array();  // Array que se va a devolver
  $where    = "";       // Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $year     = "";
  $month    = "";

  $strAgenteDesde = str_pad($AgenteDesde, 2," ",STR_PAD_LEFT);
  $strAgenteHasta = str_pad($AgenteHasta, 2," ",STR_PAD_LEFT);

  $fecha = date("Y/n/d", strtotime($FechaCorte));
  $fecha_det = explode("/",$fecha);
  $month = $fecha_det[1];
  $year  = $fecha_det[0];
  $fechaInic = date("Y-m-d",strtotime($year."-".$month."-"."01"));
  $fechaFinal = $FechaCorte;

  // Doy un plazo de hasta Cinco minutos para completar cada consulta...
  set_time_limit(300);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";
  $conn = DB::getConn();

  try {

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # Ventas a la fecha 
    # -----------------------------------------------------------------
    #    dRendon 15/07/2022 Por alguna razon no se está aceptando el
    #    paso de parametros, por lo tanto uso las variables 'tal cual'
    $sqlCmd = "CREATE TEMPORARY TABLE indic_ventas AS  
    SELECT e1_age,
    SUM(
    CASE
    WHEN e1_fecha = '". $fechaFinal. "' THEN e1_va
    ELSE 0
    END ) diaria,
    SUM(e1_va) AS acumulada, max(b.tco_inferi) tco_inferi, max(b.tco_minimo) tco_minimo, 
    max(b.tco_meta) tco_meta, max(b.tco_infers) tco_infers,max(b.tco_minims) tco_minims,
    max(b.tco_metas) tco_metas,max(c.gc_nom) gc_nom
    FROM cli040 a
    LEFT JOIN var035 b ON CONCAT('". (string)$year . "',LPAD(trim('". (string)$month . "'),2,' '),e1_age)=CONCAT(b.tco_amo,b.tco_mes,b.tco_age)
    LEFT JOIN var030 c ON e1_age = gc_llave
    INNER JOIN var020 d ON concat('02',e1_cat,'1') = concat(d.t_tica,TRIM(d.t_gpo),SUBSTR(d.t_param,1,1))
    WHERE e1_fecha >= '". $fechaInic. "' AND e1_fecha <= '". $fechaFinal. "' 
      AND e1_cat <> 'Z' AND e1_cat <> 'Y'
      AND trim(e1_age) >= trim(:strAgenteDesde) AND trim(e1_age) <= trim(:strAgenteHasta)
    GROUP BY e1_age";

    $oSQL=$conn->prepare($sqlCmd);
    //$oSQL->bindParam(':fechaInic' , $fechaInic, PDO::PARAM_STR);
    //$oSQL->bindParam(':fechaFinal', $fechaFinal, PDO::PARAM_STR);
    //$oSQL->bindParam(":month"     , $month, PDO::PARAM_STR);
    //$oSQL->bindParam(":year"      , $year, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);

    $oSQL->execute();
    $numrows=$oSQL->rowCount();


    # Pedidos activos por agente
    # ------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE indic_pedidos AS
    SELECT pe_age,
    SUM(
      CASE
      WHEN PE_TICOS=2 THEN (PE_GRAPE*(PE_PENEP-PE_COSTO))-(PE_GRASU*(PE_PENES-PE_COSTO))
      ELSE (PE_CANPE*(PE_PENEP-PE_COSTO))-(PE_CANSU*(PE_PENES-PE_COSTO))
      END ) AS pedidos 
    FROM ped100 WHERE pe_status='A'
    GROUP BY pe_age";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();


    # Obtiene clientes inactivos (cc_num + cc_fil)
    # ----------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE cltes_inactivos AS    
    SELECT cc_num,MAX(cc_age) cc_age FROM cli010
    WHERE cc_age > ' 0' AND cc_status = 'I'
    GROUP BY cc_num";  
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    $sqlCmd = "CREATE TEMPORARY TABLE cltes_inactivos_group AS
    SELECT cc_age,COUNT(cc_age) AS numcltes FROM cltes_inactivos
    GROUP by cc_age";  
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    # Todos los Clientes por agente sin importar cc_status, agrupados por cc_num 
    # (las filiales cc_fil se agrupan en un solo cc_num)
    $sqlCmd = "CREATE TEMPORARY TABLE cltes_total AS 
    SELECT cc_num,MAX(cc_age) cc_age FROM cli010
    WHERE cc_age > ' 0'
    GROUP BY cc_num";  
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    # Cuenta numero de clientes por agente, sin importar su cc_status
    $sqlCmd = "CREATE TEMPORARY TABLE cltes_total_group AS
    SELECT cc_age,COUNT(cc_age) AS numcltes
    FROM cltes_total
    GROUP by cc_age";  
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    # Tabla que sera usada en la presentacion final
    $sqlCmd = "CREATE TEMPORARY TABLE resumen_inactivos AS
    SELECT a.cc_age,c.gc_nom,a.numcltes AS totalcltes,b.numcltes AS inactivos,
    d.tco_infers,d.tco_minims,d.tco_metas 
    FROM cltes_total_group a
    LEFT JOIN cltes_inactivos_group b ON a.cc_age=b.cc_age
    LEFT JOIN var030 c ON a.cc_age=c.gc_llave
    LEFT JOIN var035 d ON CONCAT( '". $year. "',lpad(trim('". $month. "'),2,' '),a.cc_age)=CONCAT(d.tco_amo,d.tco_mes,d.tco_age)
    WHERE trim(a.cc_age) >= trim(:agenteInic) AND trim(a.cc_age) <= trim(:agenteFinal)
    ORDER BY a.cc_age
    ";

    $oSQL=$conn->prepare($sqlCmd);
    //$oSQL->bindParam(':month', $month, PDO::PARAM_STR);
    //$oSQL->bindParam(':year' , $year, PDO::PARAM_STR);
    $oSQL->bindParam(':agenteInic' , $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(':agenteFinal', $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();


    # Obtiene Clientes por Lista de Precios
    # ---------------------------------------------------------------------
    
    # Estas tablas se usaron anteriormente, las voy a borrar y crear nuevamente
    $sqlCmd = "DROP TABLE IF EXISTS cltes_total";
    $drop=$conn->prepare($sqlCmd);
    $drop-> execute();
  
    $sqlCmd = "DROP TABLE IF EXISTS cltes_total_group";
    $drop=$conn->prepare($sqlCmd);
    $drop-> execute();
  
    # Todos los Clientes por agente sin importar cc_status, agrupados por cc_num 
    # (las filiales cc_fil se agrupan en un solo cc_num)
    $sqlCmd = "CREATE TEMPORARY TABLE cltes_total AS
    SELECT cc_num,MAX(cc_age) cc_age,MAX(cc_tipoli) cc_tipoli FROM cli010
    WHERE trim(cc_age) >= trim(:agenteInic) AND trim(cc_age) <= trim(:agenteFinal)
    GROUP BY cc_num";  
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(':agenteInic' ,$AgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(':agenteFinal',$AgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    # Cuenta numero de clientes por agente, sin importar su cc_status
    $sqlCmd = "CREATE TEMPORARY TABLE cltes_total_group AS
    SELECT cc_age,COUNT(cc_age) AS numcltes
    FROM cltes_total
    GROUP by cc_age";  
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    # Total de clientes por agente
    $sqlCmd = "SELECT * FROM cltes_total_group";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $arraycltesporagente=$oSQL->fetchAll(PDO::FETCH_ASSOC);  

    # Tipos de lista por cliente, ordenadas por "rango" (para cada agente)
    $sqlCmd = "CREATE TEMPORARY TABLE cltes_tipoli AS
    SELECT cc_age, c.gc_nom AS gc_nom, cc_num,SUBSTRING(t_param,1,2) AS rango,cc_tipoli 
    FROM cltes_total a
    LEFT JOIN var020 b ON CONCAT('1093',cc_tipoli)=CONCAT(t_tica,t_gpo,t_clave) 
    LEFT JOIN var030 c ON cc_age=gc_llave 
    ORDER BY cc_age,rango,cc_tipoli";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();

    # Pone en el array $tabla los registros obtenidos en cltes_tipoli
    $sqlCmd = "SELECT * FROM cltes_tipoli";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();
    $tabla = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    # Crea tabla para insertar registros que se van a presentar
    $sqlCmd = "CREATE TEMPORARY TABLE templp ( 
      agente character(2), nombre character(32), 
      rango1 character(20), cltesr1 integer, rango2 character(20), cltesr2 integer, 
      rango3 character(20), cltesr3 integer, rango4 character(20), cltesr4 integer)";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();

    # Procesa el array obtenido.
    $agente     = $tabla[0]["cc_age"];
    $nombre     = $tabla[0]["gc_nom"];
    $totalcltesporagente = cltesAgente($agente,$arraycltesporagente);
    $countcltes =  0;
    $listainic  = $tabla[0]["cc_tipoli"];
    $listafinal = $tabla[0]["cc_tipoli"];
    $numrango   =  1;
    $rango      = $tabla[0]["rango" ];
    $rango1     = $listainic. ' - '. $listafinal. ' '. number_format(1/$totalcltesporagente*100,0). "%";
    $rango2     = "";
    $rango3     = "";
    $rango4     = "";

    foreach($tabla as $row){

      $countcltes++;
      
      // Cambio de agente
      if($row["cc_age"] <> $agente){

        // Guarda registro del agente anterior
        $sqlCmd = "insert into templp (agente,nombre,rango1,rango2,rango3,rango4) 
        values (:agente,:nombre,:rango1,:rango2,:rango3,:rango4)";
        $oSQL=$conn->prepare($sqlCmd);
        $oSQL->bindParam(":agente",$agente);
        $oSQL->bindParam(":nombre",$nombre);
        $oSQL->bindParam(":rango1",$rango1);
        $oSQL->bindParam(":rango2",$rango2);
        $oSQL->bindParam(":rango3",$rango3);
        $oSQL->bindParam(":rango4",$rango4);
        $oSQL->execute();

        // Inicializa variables para el nuevo agente
        $agente     = $row["cc_age"];
        $nombre     = $row["gc_nom"];
        $totalcltesporagente = cltesAgente($agente,$arraycltesporagente);
        $countcltes =  1;
        $listainic  = $row["cc_tipoli"];
        $listafinal = $row["cc_tipoli"];
        $numrango   =  1;
        $rango      = $row["rango" ];
        $rango1     = $listainic. ' - '. $listafinal. ' '. number_format($countcltes/$totalcltesporagente*100,0). "%";
        $rango2     = "";
        $rango3     = "";
        $rango4     = "";
      }

      // Cambio de rango
      if($row["rango"] <> $rango) { 

        // Actualiza datos del rango anterior al cambio
        switch ( $numrango ) {
          case 1: $rango1 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-1)/$totalcltesporagente*100,0). "%"; break;
          case 2: $rango2 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-1)/$totalcltesporagente*100,0). "%"; break;
          case 3: $rango3 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-1)/$totalcltesporagente*100,0). "%"; break;
          case 4: $rango4 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-1)/$totalcltesporagente*100,0). "%"; break;
        }

        // Incrementa el numero de rango e inicializa listas que contiene
        $numrango++;
        $rango      = $row["rango"];
        $listainic  = $row["cc_tipoli"];
        $listafinal = $row["cc_tipoli"];
        $countcltes = 1;

        // Actualiza datos del rango actual
        switch ( $numrango ) {
          case 1: $rango1 = $listainic. ' - '. $listafinal. ' '. number_format($countcltes/$totalcltesporagente*100,0). "%"; break;
          case 2: $rango2 = $listainic. ' - '. $listafinal. ' '. number_format($countcltes/$totalcltesporagente*100,0). "%"; break;
          case 3: $rango3 = $listainic. ' - '. $listafinal. ' '. number_format($countcltes/$totalcltesporagente*100,0). "%"; break;
          case 4: $rango4 = $listainic. ' - '. $listafinal. ' '. number_format($countcltes/$totalcltesporagente*100,0). "%"; break;
        }      
      }

      // Actualiza datos del rango con la nueva lista final
      $listafinal = $row["cc_tipoli"];
      switch ( $numrango ) {
        case 1: $rango1 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-0)/$totalcltesporagente*100,0). "%"; break;
        case 2: $rango2 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-0)/$totalcltesporagente*100,0). "%"; break;
        case 3: $rango3 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-0)/$totalcltesporagente*100,0). "%"; break;
        case 4: $rango4 = $listainic. ' - '. $listafinal. ' '. number_format(($countcltes-0)/$totalcltesporagente*100,0). "%"; break;
      }

    }   // foreach

    // Guarda ultimo registro
    $sqlCmd = "insert into templp (agente,nombre,rango1,rango2,rango3,rango4) 
    values (:agente,:nombre,:rango1,:rango2,:rango3,:rango4)";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->bindParam(":agente",$agente);
    $oSQL->bindParam(":nombre",$nombre);
    $oSQL->bindParam(":rango1",$rango1);
    $oSQL->bindParam(":rango2",$rango2);
    $oSQL->bindParam(":rango3",$rango3);
    $oSQL->bindParam(":rango4",$rango4);
    $oSQL->execute();
    

    $sqlCmd = "CREATE TEMPORARY TABLE resumenlp AS
    SELECT * FROM templp";
    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();


    # Une tablas temporales para crear la tabla final
    # ---------------------------------------------------------------------
      // Tabla con informacion que sera presentada
                //      $sqlCmd = "SELECT a.*,b.pedidos FROM indic_ventas a
                //      LEFT JOIN indic_pedidos b ON e1_age=PE_AGE";

                //      $sqlCmd = "SELECT a.*,b.pedidos,c.numcltes AS inactivos,d.numcltes AS totalcltes 
                //      FROM indic_ventas a
                //      LEFT JOIN indic_pedidos b ON e1_age=PE_AGE
                //      RIGHT JOIN cltes_inactivos_group c ON e1_age=c.cc_age
                //      RIGHT JOIN cltes_total_group d ON e1_age=d.cc_age";

    $sqlCmd = "SELECT a.*,b.pedidos,c.*,d.*
    FROM indic_ventas a
    LEFT JOIN indic_pedidos b ON e1_age=pe_age
    LEFT JOIN resumen_inactivos c ON e1_age=cc_age
    LEFT JOIN resumenlp d ON e1_age=agente
    ORDER BY a.e1_age";

    $oSQL=$conn->prepare($sqlCmd);
    $oSQL->execute();
    $numrows=$oSQL->rowCount();
    $arrData=$oSQL->fetchAll(PDO::FETCH_ASSOC);


  } catch (Exception $e) {

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
  $contenido  = array();  

  if(count($data)>0){

    foreach($data as $row){
      array_push($contenido,
      [
        "AgenteCodigo"  => trim($row["e1_age"]),
        "AgenteNombre"  => trim($row["gc_nom"]),
        "ImporteVentas" => [
          "VentaDiaria"       => floatval($row["diaria"]),
          "VentasAcumuladas"  => floatval($row["acumulada"]),
          "LimiteInferior"    => floatval($row["tco_inferi"]),
          "DiferenciaLimiteInferior" => $row["tco_inferi"] - $row["acumulada"],
          "Minimo" => floatval($row["tco_minimo"]),
          "DiferenciaMinimo"  => $row["tco_minimo"] - $row["acumulada"],
          "Meta"   => floatval($row["tco_meta"]),
          "DiferenciaMeta"    => $row["tco_meta"] - $row["acumulada"],
          "ImportePedidos"    => floatval($row["pedidos"])
        ],
        "ClientesInactivos" => [
          "InactivosActual" => $row["inactivos"],
          "LimiteInferior"  => $row["tco_infers"],
          "DiferenciaLimiteInferior" => $row["inactivos"] - $row["tco_infers"],
          "Minimo"  => $row["tco_minims"],
          "DiferenciaMinimo" => $row["inactivos"] - $row["tco_minims"],
          "Meta"    => $row["tco_metas"],
          "DiferenciaMeta"  => $row["inactivos"] - $row["tco_metas"],
          "TotalClientes"   => $row["totalcltes"]
        ],
        "ClientesListas" => [
          "ListasRango1" => trim($row["rango1"]),
          "ListasRango2" => trim($row["rango2"]),
          "ListasRango3" => trim($row["rango3"]),
          "ListasRango4" => trim($row["rango4"])
        ]
      ]);

    }

  }

  return $contenido; 
}

/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn){

  $sqlcmd = "DROP TABLE IF EXISTS indic_ventas";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS indic_pedidos";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS cltes_inactivos";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS cltes_inactivos_group";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS resumen_inactivos";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS cltes_total";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS cltes_total_group";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS cltes_tipoli";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS indic_listaprec";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS templp";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();

  $sqlcmd = "DROP TABLE IF EXISTS resumenlp";
  $drop=$conn->prepare($sqlcmd);
  $drop-> execute();
}

/**
 * Obtiene total de clientes por agente
 */
function cltesAgente($agente, $arraycltesporagente) {
  $numcltes = 0;
  foreach($arraycltesporagente as $fila){
    if (trim($fila["cc_age"])==trim($agente)){
      $numcltes = $fila["numcltes"];
      break;
    }
  }
  return $numcltes;
}
