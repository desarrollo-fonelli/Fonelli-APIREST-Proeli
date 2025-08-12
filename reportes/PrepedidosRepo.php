<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista de Prepedidos 
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
const K_SCRIPTNAME  = "PrepedidosRepo.php";

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
$Token          = null;     // Token obtenido por el usuario al autenticarse
$OficinaDesde   = null;     // Código Oficina en que se registra el pedido 
$OficinaHasta   = null;     // Código Oficina en que se registra el pedido
$AgenteDesde    = null;
$AgenteHasta    = null;
$ClienteDesde   = null;     // Id del cliente
$FilialDesde    = null;     // Filial del cliente inicial
$ClienteHasta   = null;     // Filial del cliente
$FilialHasta    = null;     // Filial del cliente final
$FechaPrepDesde = null;     // Fecha de registro del pedido
$FechaPrepHasta = null;     // Fecha de registro del pedido
$FolioDesde     = null;     // Folio Prepedido del cliente
$FolioHasta     = null;     // Folio Prepedido del cliente
$OrdenCompra    = null;     // Orden de compra del cliente
$Status         = null;     // Status del cliente
$Documentados   = null;     // Indica si se incluyen los pedidos documentados
$Autorizados    = null;
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

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
    if (! in_array($TipoUsuario, ["C", "A", "G"])) {
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

  if (!isset($_GET["ClienteDesde"])) {
    throw new Exception("El parametro obligatorio 'ClienteDesde' no fue definido.");
  } else {
    $ClienteDesde = $_GET["ClienteDesde"];
  }

  if (!isset($_GET["FilialDesde"])) {
    throw new Exception("El parametro obligatorio 'FilialDesde' no fue definido.");
  } else {
    $FilialDesde = $_GET["FilialDesde"];
  }

  if (!isset($_GET["ClienteHasta"])) {
    throw new Exception("El parametro obligatorio 'ClienteHasta' no fue definido.");
  } else {
    $ClienteHasta = $_GET["ClienteHasta"];
  }

  if (!isset($_GET["FilialHasta"])) {
    throw new Exception("El parametro obligatorio 'FilialHasta' no fue definido.");
  } else {
    $FilialHasta = $_GET["FilialHasta"];
  }

  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "C") {
    if ((TRIM($ClienteDesde) . "-" . TRIM($FilialDesde)) != $Usuario or
      (TRIM($ClienteHasta) . "-" . TRIM($FilialHasta)) != $Usuario
    ) {
      throw new Exception("Error de autenticación");
    }
  }

  if (!isset($_GET["FechaPrepDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaPrepDesde' no fue definido.");
  } else {
    $FechaPrepDesde = $_GET["FechaPrepDesde"];
    if (!ValidaFormatoFecha($FechaPrepDesde)) {
      throw new Exception("El parametro 'FechaPrepDesde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }

  if (!isset($_GET["FechaPrepHasta"])) {
    throw new Exception("El parametro obligatorio 'FechaPrepHasta' no fue definido.");
  } else {
    $FechaPrepHasta = $_GET["FechaPrepHasta"];
    if (!ValidaFormatoFecha($FechaPrepHasta)) {
      throw new Exception("El parametro 'FechaPrepHasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }

  if (!isset($_GET["FolioDesde"])) {
    throw new Exception("El parámetro obligatorio 'FolioDesde' no fue definido");
  } else {
    $FolioDesde = $_GET["FolioDesde"];
  }

  if (!isset($_GET["FolioHasta"])) {
    throw new Exception("El parámetro obligatorio 'FolioHasta' no fue definido");
  } else {
    $FolioHasta = $_GET["FolioHasta"];
    if ($FolioDesde > $FolioHasta) {
      throw new Exception("Error: folio inicial es mayor al final");
    }
  }
  // Fin parámetros obligatorios

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "OficinaDesde",
  "OficinaHasta",
  "AgenteDesde",
  "AgenteHasta",
  "ClienteDesde",
  "FilialDesde",
  "ClienteHasta",
  "FilialHasta",
  "FechaPrepDesde",
  "FechaPrepHasta",
  "FolioDesde",
  "FolioHasta",
  "OrdenCompra",
  "Status",
  "Documentados",
  "Autorizados",
  "Pagina"
);

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

if (isset($_GET["OrdenCompra"])) {
  $OrdenCompra = $_GET["OrdenCompra"];
}

if (isset($_GET["Status"])) {
  $Status = $_GET["Status"];
  if (! in_array($Status, ["A", "I"])) {
    $mensaje = "Valor '" . $Status . "' NO permitido para 'Status'";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Documentados"])) {
  $Documentados = $_GET["Documentados"];
  if (! in_array($Documentados, ["S", "N"])) {
    $mensaje = "Valor '" . $Documentados . "' NO permitido para 'Documentados'";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Autorizados"])) {
  $Autorizados = $_GET["Autorizados"];
  if (! in_array($Autorizados, ["S", "N"])) {
    $mensaje = "Valor '" . $Autorizados . "' NO permitido para 'Autorizados'";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Llama la función que Ejecuta la consulta 
try {
  $data = SelectPedidos(
    $TipoUsuario,
    $Usuario,
    $OficinaDesde,
    $OficinaHasta,
    $AgenteDesde,
    $AgenteHasta,
    $ClienteDesde,
    $FilialDesde,
    $ClienteHasta,
    $FilialHasta,
    $FechaPrepDesde,
    $FechaPrepHasta,
    $FolioDesde,
    $FolioHasta,
    $OrdenCompra,
    $Status,
    $Documentados,
    $Autorizados,
    $Pagina
  );

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

$conn = null;   // Cierra conexión

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * @return array
 */
function SelectPedidos(
  $TipoUsuario,
  $Usuario,
  $OficinaDesde,
  $OficinaHasta,
  $AgenteDesde,
  $AgenteHasta,
  $ClienteDesde,
  $FilialDesde,
  $ClienteHasta,
  $FilialHasta,
  $FechaPrepDesde,
  $FechaPrepHasta,
  $FolioDesde,
  $FolioHasta,
  $OrdenCompra,
  $Status,
  $Documentados,
  $Autorizados,
  $Pagina
) {
  $where = "";    // Variable para almacenar dinamicamente la clausula WHERE del SELECT

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  switch ($TipoUsuario) {
    // Cliente 
    /*
    case "C":     <-- cuando el tipo es "Cliente", no se requiere "Usuario"
      $strUsuario = str_pad($Usuario, 6," ",STR_PAD_LEFT);
      break;
      */

    // Agente
    case "A":
      $strUsuario = str_pad($Usuario, 2, " ", STR_PAD_LEFT);
      break;
    // Gerente
    case "G":
      $strUsuario = str_pad($Usuario, 2, " ", STR_PAD_LEFT);
      break;
  }

  $strOficinaDesde = str_pad($OficinaDesde, 2, "0", STR_PAD_LEFT);
  $strOficinaHasta = str_pad($OficinaHasta, 2, "0", STR_PAD_LEFT);
  $strAgenteDesde = str_pad($AgenteDesde, 2, "0", STR_PAD_LEFT);
  $strAgenteHasta = str_pad($AgenteHasta, 2, "0", STR_PAD_LEFT);
  $strClteInic    = str_replace(' ', '0', str_pad($ClienteDesde, 6, " ", STR_PAD_LEFT) . str_pad($FilialDesde, 3, " ", STR_PAD_LEFT));
  $strClteFinal   = str_replace(' ', '0', str_pad($ClienteHasta, 6, " ", STR_PAD_LEFT) . str_pad($FilialHasta, 3, " ", STR_PAD_LEFT));
  $strFolioDesde  = str_pad($FolioDesde, 6, " ", STR_PAD_LEFT);
  $strFolioHasta  = str_pad($FolioHasta, 6, " ", STR_PAD_LEFT);
  $strAutorizados = ($Autorizados == "S") ? "X" : " ";

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE pe.pe_of >= :OficinaDesde AND pe.pe_of <= :OficinaHasta
  AND concat(replace(pe.pe_num,' ','0'),replace(pe.pe_fil,' ','0')) >= :strClteInic
  AND concat(replace(pe.pe_num,' ','0'),replace(pe.pe_fil,' ','0')) <= :strClteFinal 
  AND replace(pe.pe_age,' ','0') >= :AgenteDesde
  AND replace(pe.pe_age,' ','0') <= :AgenteHasta
  AND pe.pe_fepe >= :FechaPrepDesde AND pe.pe_fepe <= :FechaPrepHasta 
  AND pe.pe_ped >= :FolioDesde AND pe.pe_ped <= :FolioHasta ";

  if (isset($OrdenCompra)) {
    $where .= "AND pe.pe_numeoc = :OrdenCompra ";
  }

  if (isset($Status)) {
    $where .= "AND pe.pe_status = :Status ";
  }
  if (isset($Documentados)) {
    $where .= "AND pe.pe_docum = :Documentados ";
  }
  if (isset($Autorizados)) {
    $where .= "AND pe.pe_autod = :Autorizados ";
  }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Instrucción SELECT
  $sqlCmd = "SELECT pe.pe_of,MAX(ofc.s_nomsuc) s_nomsuc,pe.pe_letra,pe.pe_ped,
    MAX(pe.pe_status) pe_status,MAX(pe.pe_fepe) pe_fepe,MAX(pe.pe_feca) pe_feca,
    MAX(pe_age) pe_age,MAX(trim(ag.gc_nom)) gc_nom,
    MAX(pe.pe_num) pe_num,MAX(pe.pe_fil) pe_fil,MAX(trim(cc.cc_raso)) cc_raso,
    MAX(trim(cc.cc_suc)) cc_suc, SUM(pe.pe_canpe) pe_canpe,SUM(pe.pe_grape) pe_grape,
    SUM(
      CASE pe.pe_ticos
        WHEN '1' THEN pe.pe_canpe * pe.pe_penep
        WHEN '2' THEN pe.pe_grape * pe.pe_penep
        ELSE 0
      END
        ) AS totalfila,
    MAX(pe.pe_obs) pe_obs,MAX(pe.pe_numeoc) pe_numeoc,MAX(pe.pe_boddes) pe_boddes,
    MAX(pe.pe_docum) pe_docum,MAX(pe.pe_autod) pe_autod,MAX(pe.pe_cand) pe_cand,MAX(pe.pe_intd) pe_intd,
    SUM(pe.pe_cp3) cp3, SUM(pe.pe_cp35) cp35, SUM(pe.pe_cp4) cp4, SUM(pe.pe_cp45) cp45, 
    SUM(pe.pe_cp5) cp5, SUM(pe.pe_cp55) cp55, SUM(pe.pe_cp6) cp6, SUM(pe.pe_cp65) cp65,
    SUM(pe.pe_cp7) cp7, SUM(pe.pe_cp75) cp75, SUM(pe.pe_cp8) cp8, SUM(pe.pe_cp85) cp85,
    SUM(pe.pe_cp9) cp9, SUM(pe.pe_cp95) cp95, SUM(pe.pe_cp10) cp10, SUM(pe.pe_cp105) cp105,
    SUM(pe.pe_cp11) cp11, SUM(pe.pe_cp115) cp115, SUM(pe.pe_cp12) cp12, SUM(pe.pe_cp125) cp125,
    SUM(pe.pe_cp13) cp13, SUM(pe.pe_cp135) cp135, SUM(pe.pe_cp14) cp14, SUM(pe.pe_cp145) cp145,
    SUM(pe.pe_cp15) cp15, SUM(pe.pe_cp155) cp155, SUM(pe.pe_mpx) mpx, SUM(pe.pe_cpx) cpx
  FROM prep100 pe
  JOIN cli010 cc ON cc.cc_num = pe.pe_num AND cc.cc_fil = pe.pe_fil 
  JOIN var030 ag ON ag.gc_llave = pe.pe_age
  JOIN dirsdo ofc ON ofc.s_llave = pe.pe_of
  $where 
  GROUP BY pe.pe_of,pe.pe_letra,pe.pe_ped
  ORDER BY pe.pe_of,pe.pe_letra,pe.pe_ped";
  //exit($sqlCmd);

  try {
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":OficinaDesde", $strOficinaDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":OficinaHasta", $strOficinaHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":AgenteDesde", $strAgenteDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":AgenteHasta", $strAgenteHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":strClteInic", $strClteInic, PDO::PARAM_STR);
    $oSQL->bindParam(":strClteFinal", $strClteFinal, PDO::PARAM_STR);
    $oSQL->bindParam(":FechaPrepDesde", $FechaPrepDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":FechaPrepHasta", $FechaPrepHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":FolioDesde", $strFolioDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":FolioHasta", $strFolioHasta, PDO::PARAM_STR);

    if (isset($OrdenCompra)) {
      $oSQL->bindParam(":OrdenCompra", $OrdenCompra, PDO::PARAM_STR);
    }
    if (isset($Status)) {
      $oSQL->bindParam(":Status", $Status, PDO::PARAM_STR);
    }
    if (isset($Documentados)) {
      $oSQL->bindParam(":Documentados", $Documentados, PDO::PARAM_STR);
    }
    if (isset($Autorizados)) {
      $oSQL->bindParam(":Autorizados", $strAutorizados, PDO::PARAM_STR);
    }

    //$oSQL-> bindParam(":provocaerror", "",PDO::PARAM_STR);  usado para pruebas de control de errores

    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

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
function CreaDataCompuesta($data)
{
  $contenido  = array();
  $oficinas   = array();
  $oficina    = array();
  $pedidos    = array();
  $filaPedido = array();

  $sumaPedOfic = 0;
  $sumaPzasOfic = 0;
  $sumaGrmsOfic = 0;
  $sumaImpOfic = 0;

  if (count($data) > 0) {
    $OficinaCode  = $data[0]["pe_of"];
    $OficinaNom   = trim($data[0]["s_nomsuc"]);
    /*
    $sumaPzasOfic = $data[0]["pe_canpe"];
    $sumaGrmsOfic = $data[0]["pe_grape"];
    $sumaImpOfic  = $data[0]["totalfila"];
    */

    foreach ($data as $row) {

      // Cambio de oficina, asigna contenido
      if ($row["pe_of"] != $OficinaCode) {

        $oficina = [
          "OficinaCode" => $OficinaCode,
          "OficinaNom"  => $OficinaNom,
          "NumPedOfic"  => intval($sumaPedOfic),
          "PzasOfic"    => intval($sumaPzasOfic),
          "GrmsOfic"    => floatval($sumaGrmsOfic),
          "ImpOfic"     => floatval($sumaImpOfic),
          "Pedidos"     => $pedidos
        ];
        array_push($oficinas, $oficina);

        // Prepara variables para siguiente iteración
        $OficinaCode = $row["pe_of"];
        $OficinaNom  = trim($row["s_nomsuc"]);
        $pedidos = array();
        $filaPedido = array();

        $sumaPedOfic  = 0;
        $sumaPzasOfic = 0;
        $sumaGrmsOfic = 0;
        $sumaImpOfic  = 0;
      }

      // Se crea un array con los nodos requeridos
      $filaPedido = [
        "PedLetra"      => $row["pe_letra"],
        "PedFolio"      => $row["pe_ped"],
        "Status"        => $row["pe_status"],
        "PedFecha"      => $row["pe_fepe"],
        "FechaCanc"     => $row["pe_feca"],
        "AgenteCode"    => $row["pe_age"],
        "AgenteNom"     => $row["gc_nom"],
        "PedClteCode"   => $row["pe_num"],
        "PedClteFil"    => $row["pe_fil"],
        "PedClteNom"    => $row["cc_raso"],
        "PedClteSuc"    => $row["cc_suc"],
        "PedPiezas"     => intval($row["pe_canpe"]),
        "PedGramos"     => floatval($row["pe_grape"]),
        "PedImporte"    => floatval($row["totalfila"]),
        "Observac"      => trim($row["pe_obs"]),
        "OrdenCompra"   => trim($row["pe_numeoc"]),
        "TiendaDest"    => trim($row["pe_boddes"]),
        "Documentado"   => $row["pe_docum"],
        "DocAutoriz"    => $row["pe_autod"],
        "PlazoDias"     => intval($row["pe_cand"]),
        "PlazoTipo"     => $row["pe_intd"],
        "cp3" => intval($row["cp3"]),
        "cp35" => intval($row["cp35"]),
        "cp4" => intval($row["cp4"]),
        "cp45" => intval($row["cp45"]),
        "cp5" => intval($row["cp5"]),
        "cp55" => intval($row["cp55"]),
        "cp6" => intval($row["cp6"]),
        "cp65" => intval($row["cp65"]),
        "cp7" => intval($row["cp7"]),
        "cp75" => intval($row["cp75"]),
        "cp8" => intval($row["cp8"]),
        "cp85" => intval($row["cp85"]),
        "cp9" => intval($row["cp9"]),
        "cp95" => intval($row["cp95"]),
        "cp10" => intval($row["cp10"]),
        "cp105" => intval($row["cp105"]),
        "cp11" => intval($row["cp11"]),
        "cp115" => intval($row["cp115"]),
        "cp12" => intval($row["cp12"]),
        "cp125" => intval($row["cp125"]),
        "cp13" => intval($row["cp13"]),
        "cp135" => intval($row["cp135"]),
        "cp14" => intval($row["cp14"]),
        "cp145" => intval($row["cp145"]),
        "cp15" => intval($row["cp15"]),
        "cp155" => intval($row["cp155"]),
        "mpx" => $row["mpx"],
        "cpx" => intval($row["cpx"])
      ];

      array_push($pedidos, $filaPedido);

      $sumaPedOfic  = $sumaPedOfic + 1;
      $sumaPzasOfic = $sumaPzasOfic + $row["pe_canpe"];
      $sumaGrmsOfic = ROUND($sumaGrmsOfic + $row["pe_grape"], 2);
      $sumaImpOfic = ROUND($sumaImpOfic + $row["totalfila"], 2);
    }   // foreach($data as $row)

    // Último registro
    $oficina = [
      "OficinaCode" => $OficinaCode,
      "OficinaNom"  => $OficinaNom,
      "NumPedOfic"  => intval($sumaPedOfic),
      "PzasOfic"    => intval($sumaPzasOfic),
      "GrmsOfic"    => floatval($sumaGrmsOfic),
      "ImpOfic"     => intval($sumaImpOfic),
      "Pedidos"     => $pedidos
    ];
    array_push($oficinas, $oficina);


    // Contenido que se va a devolver, el array de oficinas contiene
    // también los pedidos
    $contenido = [
      "PrepedOficinas" => $oficinas,
    ];
  } // count($data)>0

  return $contenido;
}
