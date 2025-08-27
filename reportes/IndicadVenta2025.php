<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Indicadores de Venta de acuerdo al formato manejado en 2024 y 2025
 * --------------------------------------------------------------------------
 * Creación; dRendon 30.07.2025
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "IndicadVenta2025.php";

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
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje]);
  exit;
}

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {

  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME del mensaje
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (! in_array($TipoUsuario, ["A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
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
    if (
      TRIM($AgenteDesde) != $Usuario or
      TRIM($AgenteHasta) != $Usuario
    ) {
      throw new Exception("Error de autenticación");
    }
  }
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["FechaCorte"])) {
    throw new Exception("El parametro obligatorio 'FechaCorte' no fue definido.");
  } else {
    $FechaCorte = $_GET["FechaCorte"];
    if (!ValidaFormatoFecha($FechaCorte)) {
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
foreach ($arrParam as $param) {
  if (! in_array($param, $arrPermitidos)) {
    if (strlen($mensaje) > 1) {
      $mensaje .= ", ";
    }
    $mensaje .= $param;
  }
}
if (strlen($mensaje) > 0) {
  $mensaje = "Parametros no reconocidos: " . $mensaje;   // quité K_SCRIPTNAME del mensaje
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
  $data = SelectIndicadores($AgenteDesde, $AgenteHasta, $FechaCorte);

  # Asigna código de respuesta HTTP 
  http_response_code(200);

  # Compone el objeto JSON que devuelve el endpoint
  $numFilas = count($data);
  $totalPaginas = ceil($numFilas / K_FILASPORPAGINA);

  if ($numFilas > 0) {
    $codigo = K_API_OK;
    $mensaje = "success";
  } else {
    $codigo = K_API_NODATA;
    $mensaje = "data not found";
  }

  $dataCompuesta = CreaDataCompuesta($data);

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

/*******************************************************************************
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * 
 * @return array
 */
function SelectIndicadores($AgenteDesde, $AgenteHasta, $FechaCorte)
{

  $arrData  = array();  // Array que se va a devolver
  $where    = "";       // Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $year     = "";
  $month    = "";

  $strAgenteDesde = str_pad($AgenteDesde, 2, " ", STR_PAD_LEFT);
  $strAgenteHasta = str_pad($AgenteHasta, 2, " ", STR_PAD_LEFT);

  $fecha = date("Y/n/d", strtotime($FechaCorte));
  $fecha_det = explode("/", $fecha);
  $month = $fecha_det[1];
  $year  = $fecha_det[0];
  $fechaInic = date("Y-m-d", strtotime($year . "-" . $month . "-" . "01"));
  $fechaFinal = $FechaCorte;

  // Doy un plazo de hasta Cinco minutos para completar cada consulta...
  set_time_limit(300);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";
  $conn = DB::getConn();

  try {

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # --------------------------------------------------------------------------
    # Creo las tablas que voy a usar como Resumen por Agente e indicador
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TABLE IF NOT EXISTS indicad_venta (
      gc_llave character(2),
      gc_nom character(32),
      indicad_id integer,
      indicad_descr character(32),
      objetivo numeric(14,2),
      resultado numeric(14,2),
      porc_result numeric(9,2),
      porc_cump numeric(9,2)
    )";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "TRUNCATE TABLE indicad_venta";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "CREATE TABLE IF NOT EXISTS indicad_result (
      gc_llave character(2),
      gc_nom character(32),
      eficiencia numeric(9,2)
    )";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "TRUNCATE TABLE indicad_result";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # --------------------------------------------------------------------------
    # 1. Ventas por agente a la fecha de corte: 
    # --------------------------------------------------------------------------
    # Por alguna razon no se está aceptando el paso de parametros,
    # por lo tanto uso las variables 'tal cual'
    $sqlCmd = "CREATE TEMPORARY TABLE ventas AS  
    SELECT a.e1_age, SUM(a.e1_imp) AS e1_imp,SUM(a.e1_va) AS e1_va     
    FROM cli040 a
    LEFT JOIN var030 c ON a.e1_age = c.gc_llave
    INNER JOIN var020 d ON concat('02',e1_cat,'1') = concat(d.t_tica,TRIM(d.t_gpo),SUBSTR(d.t_param,1,1))
    WHERE a.e1_fecha >= '" . $fechaInic . "' AND a.e1_fecha <= '" . $fechaFinal . "' 
      AND a.e1_cat <> 'Z' AND a.e1_cat <> 'Y' AND a.e1_cat <> 'X' 
      AND trim(a.e1_age) >= trim(:strAgenteDesde) AND trim(a.e1_age) <= trim(:strAgenteHasta)
    GROUP BY a.e1_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $strMes = str_pad(trim($month), 2, " ", STR_PAD_LEFT);

    $sqlCmd = "CREATE TEMPORARY TABLE venta_agente AS
    SELECT ag.gc_llave,ag.gc_nom,
      ob.tco_meta objetivo,COALESCE(vt.e1_va, 0) resultado, 
      CASE 
        WHEN COALESCE(ob.tco_meta, 0) = 0 THEN 0
        ELSE ROUND(vt.e1_va/ob.tco_meta*100, 2)
      END AS porc_result, 
      (CASE
        WHEN COALESCE(ob.tco_meta, 0) = 0 THEN 0.00
        WHEN vt.e1_va > ob.tco_meta THEN 100.00
        ELSE ROUND(vt.e1_va/ob.tco_meta*100,2) 
      END) * (COALESCE(esc.maximo, 0)/100) AS porc_cump
    FROM var030 ag
    LEFT JOIN ventas vt ON ag.gc_llave=vt.e1_age
    LEFT JOIN var035 ob ON ob.tco_amo='$year' AND ob.tco_mes='$strMes' AND ob.tco_age=ag.gc_llave
    LEFT JOIN indicad_escala esc ON esc.id = 1 
    WHERE trim(ag.gc_llave) >= trim(:strAgenteDesde) AND trim(ag.gc_llave) <= trim(:strAgenteHasta)
      AND ag.gc_status='A' 
    ORDER BY gc_llave";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump) 
    SELECT gc_llave,gc_nom,
      1 as indicad_id,'Venta' as indicad_descr,
      objetivo,resultado,porc_result,porc_cump
    FROM venta_agente";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # --------------------------------------------------------------------------
    # 2. Indicadores de rentabilidad
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE rentab_agente AS
    SELECT ag.gc_llave,ag.gc_nom,
      COALESCE(vt.e1_imp, 0) objetivo,COALESCE(vt.e1_va, 0) resultado, 
      CASE
        WHEN COALESCE(vt.e1_imp, 0) = 0 THEN 0
        ELSE ROUND(vt.e1_va/vt.e1_imp*100,2)
      END AS porc_result,
      ROUND((CASE
        WHEN COALESCE(vt.e1_imp, 0) = 0 THEN 0.00
        WHEN vt.e1_va > vt.e1_imp THEN 100.00 
        ELSE ROUND(vt.e1_va/vt.e1_imp*100,2) 
      END) / COALESCE(esc.maximo, 0) * 100,2) AS porc_cump
    FROM var030 ag
    LEFT JOIN ventas vt ON ag.gc_llave=vt.e1_age
    LEFT JOIN indicad_escala esc ON esc.id = 2 
    WHERE trim(ag.gc_llave) >= trim(:strAgenteDesde) AND trim(ag.gc_llave) <= trim(:strAgenteHasta)
      AND ag.gc_status='A' 
    ORDER BY gc_llave";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump) 
    SELECT gc_llave,gc_nom,
      2 as indicad_id,'Rentabilidad' as indicad_descr,
      objetivo,resultado,porc_result,
      CASE
        WHEN porc_cump > 100.00 THEN 100.00
        ELSE porc_cump
      END AS porc_cump
    FROM rentab_agente";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    # --------------------------------------------------------------------------
    # 3. Cartera Vencida
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE saldocltes AS
    SELECT edc.sc_age,
    SUM(
      CASE
        WHEN COALESCE(par.ti_parx, 0) > 0 THEN ROUND(edc.sc_saldo * par.ti_parx, 2)
        ELSE edc.sc_saldo
      END 
    ) AS sc_saldo,
    SUM(
      CASE 
        WHEN COALESCE(par.ti_parx, 0) > 0 THEN ROUND(edc.sc_saldove * par.ti_parx, 2)
        ELSE edc.sc_saldove
      END
    ) AS sc_saldove
    FROM edocta edc
    LEFT JOIN var020 car ON car.t_tica='10' AND car.t_gpo='88' AND car.t_clave=edc.sc_tica
    LEFT JOIN inv100 par ON par.ti_llave=SUBSTR(car.t_param,1,1)
    WHERE trim(edc.sc_age) >= trim(:strAgenteDesde) AND trim(edc.sc_age) <= trim(:strAgenteHasta)
      AND edc.sc_saldo <> 0 
      AND SUBSTR(car.t_param,2,1)='1'
    GROUP BY edc.sc_age ORDER BY edc.sc_age;";
    //       AND edc.sc_feex <= '2025-08-02' 
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "CREATE TEMPORARY TABLE cartera_vencida AS
    SELECT sal.sc_age, ag.gc_nom, sal.sc_saldo objetivo, sal.sc_saldove resultado,
      CASE
        WHEN sal.sc_saldo <> 0 THEN ROUND(sal.sc_saldove/sal.sc_saldo*100,2)
        ELSE 0
      END porc_result,
      0 porc_cump
    FROM saldocltes sal
    JOIN var030 ag ON ag.gc_llave = sal.sc_age 
    WHERE trim(ag.gc_llave) >= trim(:strAgenteDesde) AND trim(ag.gc_llave) <= trim(:strAgenteHasta)
      AND ag.gc_status = 'A'
    ORDER BY sal.sc_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump) 
    SELECT sc_age,gc_nom,
      3 as indicad_id,'Cartera Vencida' as indicad_descr,
      objetivo,resultado,porc_result,      
      CASE 
      WHEN porc_result < COALESCE(esc.maximo, 0) THEN 100.00
      WHEN porc_result <= COALESCE(esc.minimo, 0) AND porc_result >= COALESCE(esc.maximo, 0) THEN 
        ROUND(porc_result/esc.minimo*100,2)
      ELSE 0
      END AS porc_cump
    FROM cartera_vencida
    LEFT JOIN indicad_escala esc ON esc.id = 3 ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    # --------------------------------------------------------------------------
    # 4. Cobranza (clientes que tienen más de 15 días con documentos vencidos)
    # --------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE diascltevenc AS
    SELECT sac.sc_age,sac.sc_num,sac.sc_fil, 
    MIN(CASE WHEN sac.sc_saldo>0 THEN (sc_feve::date - CURRENT_DATE) ELSE 0 END ) AS dias
    FROM edocta sac
    LEFT JOIN cli010 cli ON cli.cc_num=sac.sc_num AND cli.cc_fil=sac.sc_fil
    LEFT JOIN var020 car ON car.t_tica='10' AND car.t_gpo='88' AND car.t_clave=sac.sc_tica
    WHERE trim(sac.sc_age) >= trim(:strAgenteDesde) AND trim(sac.sc_age) <= trim(:strAgenteHasta)
    AND cli.cc_status='I'
    AND SUBSTR(car.t_param,2,1)='1'
    GROUP BY sac.sc_age,sac.sc_num,sac.sc_fil
    ORDER BY sac.sc_age,dias";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "CREATE TEMPORARY TABLE cltesvenc AS
    SELECT sc_age, count(*) resultado, max(ag.gc_nom) gc_nom,
    MAX(COALESCE(esc.minimo, 0)) AS objetivo
    FROM diascltevenc
    LEFT JOIN var030 ag ON ag.gc_llave=sc_age
    LEFT JOIN indicad_escala esc ON esc.id = 4
    WHERE dias < -15
    GROUP BY sc_age ORDER BY sc_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump) 
    SELECT ag.gc_llave,ag.gc_nom,
      4 as indicad_id, 'Cobranza' as indicad_descr,
      COALESCE(esc.minimo, 0) objetivo, COALESCE(cl.resultado, 0) resultado,
      CASE
        WHEN COALESCE(resultado,0) = 0 THEN 100
        WHEN COALESCE(esc.minimo, 0) <> 0 THEN ROUND(resultado::numeric/esc.minimo*100,2)
        ELSE 0.00
      END AS porc_result,
      CASE
        WHEN COALESCE(resultado,0) = 0 THEN 100
        WHEN resultado <= COALESCE(esc.minimo, 0) THEN 100.00	
        ELSE 0.00
      END AS porc_cump
    FROM var030 ag
    LEFT JOIN cltesvenc cl ON ag.gc_llave = cl.sc_age
    LEFT JOIN indicad_escala esc ON esc.id = 4
    WHERE trim(ag.gc_llave) >= trim(:strAgenteDesde) AND trim(ag.gc_llave) <= trim(:strAgenteHasta)
      AND ag.gc_status = 'A' 
    ORDER BY ag.gc_llave";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();


    # --------------------------------------------------------------------------
    # 5. Cartera Activa 
    # --------------------------------------------------------------------------
    $fechaTemp = new DateTime($FechaCorte);
    $fechaTemp = $fechaTemp->sub(new DateInterval('P4M'));    // 4 meses anteriores
    $fechaBase = $fechaTemp->format('Y-m-d');

    $sqlCmd = "CREATE TEMPORARY TABLE cltesconventa AS
    SELECT e1_age,e1_num,e1_fil,count(*) numctes 
    FROM cli040 
    WHERE e1_fecha >= '$fechaBase' AND e1_fecha <= '$FechaCorte'
    GROUP BY e1_age,e1_num,e1_fil 
    ORDER BY e1_age,e1_num,e1_fil";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "CREATE TEMPORARY TABLE cltesagenteconventa AS
    SELECT vt.e1_age,count(*) numcltes, max(ag.gc_nom) gc_nom
    FROM cltesconventa vt
    JOIN var030 ag ON ag.gc_llave = vt.e1_age 
    GROUP BY vt.e1_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "CREATE TEMPORARY TABLE cltesagente AS
    SELECT cc_age, count(*) numcltes
    FROM cli010
    GROUP BY cc_age ORDER BY cc_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "CREATE TEMPORARY TABLE cartera_activa AS
    SELECT cc.cc_age,e1.gc_nom,cc.numcltes objetivo,e1.numcltes resultado,
    CASE
      WHEN cc.numcltes <> 0 THEN ROUND(e1.numcltes::numeric / cc.numcltes*100,2) 
      ELSE 0
    END as porc_result,    
    0 as porc_cump
    FROM cltesagente cc 
    LEFT JOIN cltesagenteconventa e1 ON cc.cc_age=e1.e1_age
    WHERE trim(e1.e1_age) >= trim(:strAgenteDesde) AND trim(e1.e1_age) <= trim(:strAgenteHasta)
    ORDER BY e1.e1_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump)
      SELECT cc_age, gc_nom,
      5 as indicad_id, 'Cartera Activa' as indicad_descr,
      objetivo, resultado, porc_result, 
      CASE
        WHEN esc.maximo = 0 THEN 0.00
        WHEN porc_result >= esc.maximo THEN 100.00
        ELSE ROUND(porc_result::numeric/esc.maximo*100,2)
      END AS porc_cump
      FROM cartera_activa
      JOIN indicad_escala esc ON esc.id = 5 ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    # --------------------------------------------------------------------------
    # 6. Pedidos activos a la fecha actual
    # --------------------------------------------------------------------------
    $anio = intval($year);
    $mesSigte = intval($month) + 1;
    if ($mesSigte > 12) {
      $mesSigte = 1;
      $anio = $anio + 1;
    }
    $strAnio = strval($anio);
    $strMesSigte = str_pad(strval($mesSigte), 2, " ", STR_PAD_LEFT);

    $sqlCmd = "CREATE TEMPORARY TABLE pedidos_activos AS
    SELECT pe.pe_age,
    MAX(
      CASE 
        WHEN COALESCE(co.tco_meta, 0) > 0 THEN co.tco_meta
        ELSE 0.00
      END 
    )  tco_meta,
    SUM(
      CASE
        WHEN pe.PE_TICOS=2 THEN (pe.PE_GRAPE*(pe.PE_PENEP-pe.PE_COSTO))-(pe.PE_GRASU*(pe.PE_PENES-pe.PE_COSTO))
        ELSE (pe.PE_CANPE*(pe.PE_PENEP-pe.PE_COSTO))-(pe.PE_CANSU*(pe.PE_PENES-pe.PE_COSTO))
      END 
    ) AS importe
    FROM ped100 pe 
    LEFT JOIN var035 co ON co.tco_amo='$strAnio' AND co.tco_mes='$strMesSigte' AND co.tco_age=pe.pe_age
    WHERE pe.pe_status='A'
      AND trim(pe.pe_age) >= trim(:strAgenteDesde) AND trim(pe.pe_age) <= trim(:strAgenteHasta)
    GROUP BY pe.pe_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    $sqlCmd = "CREATE TEMPORARY TABLE pedidos_agente AS
    SELECT pe.pe_age,ag.gc_nom,pe.tco_meta objetivo,pe.importe resultado,
      CASE
        WHEN pe.tco_meta = 0 THEN 0
        WHEN pe.importe > pe.tco_meta THEN 100
        ELSE ROUND(pe.importe/pe.tco_meta*100, 2)
      END AS porc_result,
      0 porc_cump
    FROM pedidos_activos pe
    LEFT JOIN var030 ag ON ag.gc_llave=pe.pe_age
    WHERE ag.gc_status= 'A'
    ORDER BY pe.pe_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump) 
    SELECT pe_age,gc_nom,
      6 as indicad_id,'Pedidos' as indicad_descr,
      objetivo,resultado, porc_result, 
      CASE
        WHEN esc.maximo = 0 THEN 0.00
        WHEN porc_result >= esc.maximo THEN 100.00
        ELSE ROUND(porc_result::numeric/esc.maximo*100,2)
      END AS porc_cump
    FROM pedidos_agente
    JOIN indicad_escala esc ON esc.id = 6 ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # --------------------------------------------------------------------------
    # 7. Devoluciones
    # --------------------------------------------------------------------------

    // Resume gramos por Kilataje por cada agente
    /*
    $sqlCmd = "CREATE TEMPORARY TABLE agente_kilataje AS
    SELECT ord.or_age, SUBSTR(lnp.t_param,11,2) ktje, SUM(dor.dor_grms) AS sum_grms
    FROM cli130 ord
    JOIN cli135 dor ON dor.dor_folio=ord.or_folio
    LEFT JOIN dateli.var020 lnp ON lnp.t_tica='05' AND lnp.t_gpo=dor.dor_lin
    WHERE ord.or_fecha >= '$fechaInic' AND ord.or_fecha <= '$fechaFinal'
      AND ord.or_tipo='1' 
      AND trim(ord.or_age) >= trim(:strAgenteDesde) AND trim(ord.or_age) <= trim(:strAgenteHasta)
    GROUP BY ord.or_age,SUBSTR(lnp.t_param,11,2) ORDER BY ord.or_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();
    */
    $sqlCmd = "CREATE TEMPORARY TABLE agente_kilataje AS
    SELECT ord.or_age, ord.or_kt ktje, SUM(ord.or_grms) AS sum_grms
    FROM cli130 ord
    WHERE ord.or_fecha >= '$fechaInic' AND ord.or_fecha <= '$fechaFinal'
      AND ord.or_tipo='1' 
      AND trim(ord.or_age) >= trim(:strAgenteDesde) AND trim(ord.or_age) <= trim(:strAgenteHasta)
    GROUP BY ord.or_age,ord.or_kt ORDER BY ord.or_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();

    // Obtengo precio por gramo según kilataje
    $sqlCmd = "CREATE TEMPORARY TABLE precio_kilataje AS
    SELECT co_clave,co_descr,co_facoro,co_kilataj,co_l1grc 
    FROM compon 
    WHERE co_facoro > 0 AND trim(co_clave) <> '95' AND CAST(co_clave AS integer) < '100' ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // Uno las tablas para calcular el importe del metal
    $sqlCmd = "CREATE TEMPORARY TABLE agente_devoluc AS
    SELECT ag.*,kt.co_l1grc, round(ag.sum_grms*kt.co_l1grc,2) importe 
    FROM agente_kilataje ag
    LEFT JOIN precio_kilataje kt ON kt.co_kilataj = CAST(ag.ktje as integer)
    ORDER BY ag.or_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // Resumen final importe gramos
    $sqlCmd = "CREATE TEMPORARY TABLE resum_agtedevoluc AS
    SELECT or_age, SUM(importe) importe
    FROM agente_devoluc
    GROUP BY or_age";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // Inserta filas en tabla de indicadores
    $sqlCmd = "INSERT INTO indicad_venta (gc_llave,gc_nom,indicad_id,indicad_descr,
      objetivo, resultado, porc_result, porc_cump) 
    SELECT ag.gc_llave,ag.gc_nom,
      7 as indicad_id,'Devoluciones' as indicad_descr,      
      ren.objetivo,
      COALESCE(dev.importe, 0) resultado,
      CASE 
        WHEN COALESCE(ren.objetivo, 0) = 0 THEN 0.00
        WHEN COALESCE(dev.importe, 0) = 0 THEN 0.00
        ELSE ROUND(dev.importe::numeric/ren.objetivo*100,2)
      END AS porc_result, 
      
    CASE
        WHEN COALESCE(dev.importe, 0)=0 THEN 100.00
        WHEN COALESCE(ren.objetivo, 0) = 0 THEN 0.00
        WHEN dev.importe > ren.objetivo THEN 0.00
        WHEN ROUND(dev.importe::numeric/ren.objetivo*100,2) > COALESCE(esc.minimo, 0) THEN 0.00

        WHEN ROUND(dev.importe::numeric/ren.objetivo*100,2) >= esc.minimo 
         AND ROUND(dev.importe::numeric/ren.objetivo*100,2) < esc.maximo 
        THEN ROUND(ROUND(dev.importe::numeric/ren.objetivo*100,2)/esc.minimo*100,2)
        ELSE ROUND((1 - ROUND(dev.importe::numeric/ren.objetivo*100,2)/esc.minimo)*100,2)
      END AS porc_cump


    FROM var030 ag
    LEFT JOIN rentab_agente ren ON ren.gc_llave = ag.gc_llave
    LEFT JOIN resum_agtedevoluc dev ON dev.or_age = ag.gc_llave
    LEFT JOIN indicad_escala esc ON esc.id = 7
    WHERE trim(ag.gc_llave) >= trim(:strAgenteDesde) AND trim(ag.gc_llave) <= trim(:strAgenteHasta)
      AND ag.gc_status='A'
    ORDER BY ag.gc_llave";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":strAgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strAgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->execute();


    # --------------------------------------------------------------------------
    # Tabla con resultado final de la evaluacion
    # --------------------------------------------------------------------------
    $sqlCmd = "INSERT INTO indicad_result (gc_llave,gc_nom,eficiencia)
      SELECT gc_llave,MAX(gc_nom) gc_nom, ROUND(SUM(porc_cump)/7,2) eficiencia 
      FROM indicad_venta
      GROUP BY gc_llave ORDER BY gc_llave";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    $sqlCmd = "SELECT * FROM indicad_result";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    //$resultAgente = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    # --------------------------------------------------------------------------
    # Tabla devuelta por el servicio
    # --------------------------------------------------------------------------
    // $sqlCmd = "SELECT * FROM indicad_venta";
    // $oSQL = $conn->prepare($sqlCmd);
    // $oSQL->execute();
    // $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    $sqlCmd = "SELECT res.gc_llave,trim(res.gc_nom) gc_nom,res.eficiencia,
      vt.indicad_id,vt.indicad_descr,vt.objetivo,vt.resultado,
      vt.porc_result,vt.porc_cump
    FROM indicad_result res
    LEFT JOIN indicad_venta vt ON vt.gc_llave = res.gc_llave
    ORDER BY gc_llave,indicad_id";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);


    // -------

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

/*******************************************************************************
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn)
{

  $tablasTemp = [
    "ventas",
    "venta_agente",
    "rentab_agente",
    "saldocltes",
    "cartera_vencida",
    "diascltevenc",
    "cltesvenc",
    "cltesconventa",
    "cltesagenteconventa",
    "cltesagente",
    "cartera_activa",
    "pedidos_activos",
    "pedidos_agente",
    "devoluc",
    "devoluc_agente"
  ];

  foreach ($tablasTemp as $tabla) {
    $sqlcmd = "DROP TABLE IF EXISTS $tabla";
    $drop = $conn->prepare($sqlcmd);
    $drop->execute();
  }
}

/*******************************************************************************
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
function CreaDataCompuesta($data)
{
  $contenido  = array();
  $agentes    = array();
  $agente     = array();
  $indicadores = array();
  $indicador  = array();

  if (count($data) > 0) {

    $gc_llave   = $data[0]["gc_llave"];
    $gc_nom     = $data[0]["gc_nom"];
    $eficiencia = floatval($data[0]["eficiencia"]);

    foreach ($data as $row) {

      // Cambio de agente
      if ($row["gc_llave"] != $gc_llave) {
        $agente = [
          "AgteCodigo"    => $gc_llave,
          "AgteNombre"    => $gc_nom,
          "AgteEficienc"  => $eficiencia,
          "Indicadores"   => $indicadores
        ];
        array_push($agentes, $agente);

        $gc_llave   = $row["gc_llave"];
        $gc_nom     = $row["gc_nom"];
        $eficiencia = floatval($row["eficiencia"]);
        $indicadores = array();
      }

      $indicador = [
        "IndicadId"     => intval($row["indicad_id"]),
        "IndicadDesc"   => trim($row["indicad_descr"]),
        "Objetivo"      => floatval($row["objetivo"]),
        "Resultado"     => floatval($row["resultado"]),
        "PorcResult"    => floatval($row["porc_result"]),
        "PorcCump"      => floatval($row["porc_cump"])
      ];
      array_push($indicadores, $indicador);
    }

    // Ultimo registro
    $agente = [
      "AgteCodigo"    => $gc_llave,
      "AgteNombre"    => $gc_nom,
      "AgteEficienc"  => $eficiencia,
      "Indicadores"   => $indicadores
    ];
    array_push($agentes, $agente);


    $contenido = ["IndicadoresVenta" => $agentes];
  } // if (count($data) > 0)

  return $contenido;
}
