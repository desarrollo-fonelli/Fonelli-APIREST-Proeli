<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * ConsultaGuias - corresponde a "Consulta de Paquetes" de Proeli
 * --------------------------------------------------------------------------
 * dRendon 28.01.2025 
 *  Se crea el script
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ConsultaGuias.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con registros del estado de cuenta
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$TipoUsuario    = null;   // Tipo de usuario
$Usuario        = null;   // Id del usuario (cliente, agente o gerente)
$Token          = null;   // Token obtenido por el usuario al autenticarse
$OficinaDesde   = null;   // Código Oficina en que se registra el pedido 
$OficinaHasta   = null;   // Código Oficina en que se registra el pedido
$ClienteCodigo  = null;   // Id del cliente
$ClienteFilial  = null;   // Filial del cliente
$ClteCredito    = null;   // Filtra por credito o contado
$Paquete        = null;   // Folio del paquete
$FactSerie      = null;   // Serie de la factura
$Factura        = null;   // Factura del cliente
$Remision       = null;   // Remision del cliente
$PrefaSerie     = null;   // Serie de la prefactura
$Prefactura     = null;   // Folio de la Prefactura
$Pedido         = null;   // Pedido del cliente
$Traspaso       = null;   // Traspaso inter oficinas
$OrdenRetorno   = null;   // Folio Orden de Retorno
$Carrier        = null;   // Id del Carrier que transporta el paquete
$OrdenCompra    = null;   // Orden de Compra del cliente (Liverpool)
$Pagina         = 1;      // Pagina devuelta del conjunto de datos obtenido

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
    if ($TipoUsuario == 'C') {
      if (!isset($_GET["ClienteCodigo"])) {
        throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
      } else {
        $ClienteCodigo = $_GET["ClienteCodigo"];
      }
      if (!isset($_GET["ClienteFilial"])) {
        throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
      } else {
        $ClienteFilial = $_GET["ClienteFilial"];
      }
    }
  }

  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  } else {
    $Usuario = $_GET["Usuario"];
  }

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

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

  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "C") {
    if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }

  if (!isset($_GET["OficinaDesde"])) {
    //throw new Exception("El parametro obligatorio 'OficinaDesde' no fue definido.");
    $OficinaDesde = "  ";
  } else {
    $OficinaDesde = $_GET["OficinaDesde"];
    if ($OficinaDesde == "") {
      $OficinaDesde = "  ";
    }
  }
  if (!isset($_GET["OficinaHasta"])) {
    throw new Exception("El parametro obligatorio 'OficinaHasta' no fue definido.");
  } else {
    $OficinaHasta = $_GET["OficinaHasta"];
  }
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
  "ClienteCodigo",
  "ClienteFilial",
  "ClteCredito",
  "Paquete",
  "FactSerie",
  "Factura",
  "Remision",
  "PrefaSerie",
  "Prefactura",
  "Pedido",
  "Traspaso",
  "OrdenRetorno",
  "Carrier",
  "OrdenCompra",
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

# Hay que inicializarverificar parametros opcionales y en caso 
# que estos no se indiquen, asignar valores por omisión.
# (dichos valores se definieron al inicio del script, al declarar las variables)
if (isset($_GET["Usuario"])) {
  $Usuario = $_GET["Usuario"];
} else {
  if (in_array($TipoUsuario, ["A", "G"])) {
    $mensaje = "Debe indicar 'Usuario' cuando 'TipoUsuario' es 'A' o 'G'";    // quité K_SCRIPTNAME del mensaje
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["ClienteCodigo"])) {
  $ClienteCodigo = $_GET["ClienteCodigo"];
}

if (isset($_GET["ClienteFilial"])) {
  $ClienteFilial = $_GET["ClienteFilial"];
}
if (isset($_GET["ClteCredito"])) {
  $ClteCredito = $_GET["ClteCredito"];
  if ($ClteCredito < "1" || $ClteCredito > "3") {
    $mensaje = "Valor no admitido para 'ClteCredito'";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Paquete"])) {
  $Paquete = $_GET["Paquete"];
}

if (isset($_GET["FactSerie"])) {
  $FactSerie = $_GET["FactSerie"];
  if (!isset($_GET["Factura"])) {
    $mensaje = "Debe indicar 'Factura' cuando indique una FactSerie";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}
if (isset($_GET["Factura"])) {
  $Factura = $_GET["Factura"];
  if (!isset($_GET["FactSerie"])) {
    $mensaje = "Debe indicar 'FactSerie' cuando indique una Factura";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Remision"])) {
  $Remision = $_GET["Remision"];
}

if (isset($_GET["PrefaSerie"])) {
  $PrefaSerie = $_GET["PrefaSerie"];
  if (!isset($_GET["Prefactura"])) {
    $mensaje = "Debe indicar 'Prefactura' cuando indique una PrefaSerie";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}
if (isset($_GET["Prefactura"])) {
  $Prefactura = $_GET["Prefactura"];
  if (!isset($_GET["PrefaSerie"])) {
    $mensaje = "Debe indicar 'PrefaSerie' cuando indique una Prefactura";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Pedido"])) {
  $Pedido = $_GET["Pedido"];
}

if (isset($_GET["Traspaso"])) {
  $Traspaso = $_GET["Traspaso"];
}

if (isset($_GET["OrdenRetorno"])) {
  $OrdenRetorno = $_GET["OrdenRetorno"];
}

if (isset($_GET["Carrier"])) {
  $Carrier = $_GET["Carrier"];
}

if (isset($_GET["OrdenCompra"])) {
  $OrdenCompra = $_GET["OrdenCompra"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectConsultaGuias(
    $TipoUsuario,
    $Usuario,
    $OficinaDesde,
    $OficinaHasta,
    $ClienteCodigo,
    $ClienteFilial,
    $ClteCredito,
    $Paquete,
    $FactSerie,
    $Factura,
    $Remision,
    $PrefaSerie,
    $Prefactura,
    $Pedido,
    $Traspaso,
    $OrdenRetorno,
    $Carrier,
    $OrdenCompra,
    $Pagina
  );

  # Asigna código de respuesta HTTP por default
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
 * 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $OficinaDesde
 * @param int $OficinaHasta
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @return array
 */
function SelectConsultaGuias(
  $TipoUsuario,
  $Usuario,
  $OficinaDesde,
  $OficinaHasta,
  $ClienteCodigo,
  $ClienteFilial,
  $ClteCredito,
  $Paquete,
  $FactSerie,
  $Factura,
  $Remision,
  $PrefaSerie,
  $Prefactura,
  $Pedido,
  $Traspaso,
  $OrdenRetorno,
  $Carrier,
  $OrdenCompra,
  $Pagina
) {

  $arrData = array();   // Arreglo para almacenar los datos obtenidos
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

  if (trim($OficinaDesde) <> "") {
    $OficinaDesde  = str_pad(trim($OficinaDesde), 2, "0", STR_PAD_LEFT);
  }
  $OficinaHasta  = str_pad(trim($OficinaHasta), 2, "0", STR_PAD_LEFT);

  if (isset($ClienteCodigo)) {
    $ClienteCodigo = str_pad(trim($ClienteCodigo), 6, " ", STR_PAD_LEFT);
    $ClienteFilial = str_pad(trim($ClienteFilial), 3, " ", STR_PAD_LEFT);
  }

  if (isset($Carrier)) {
    $Carrier = str_pad(trim($Carrier), 2, "0", STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  // require_once "../db/conexion.php";   <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.gu_of >= :OficinaDesde AND a.gu_of <= :OficinaHasta ";

  if (isset($ClienteCodigo)) {
    $where .= "AND a.gu_num = :ClienteCodigo AND a.gu_fil = :ClienteFilial ";
  }

  if (isset($ClteCredito)) {
    // 1=Credito 2=Contado
    if ($ClteCredito == 1) {
      $where .= "AND c.cc_plazo <> 0 ";
    } elseif ($ClteCredito == 2) {
      $where .= "AND c.cc_plazo = 0 ";
    }
  }
  if (isset($Paquete)) {
    $where .= "AND trim(a.gu_paq) = trim(:Paquete) ";
  }
  if (isset($Factura)) {
    $where .= "AND a.gu_serie = :FactSerie AND trim(a.gu_apl) = trim(:Factura) ";
  }
  if (isset($Remision)) {
    $where .= "AND a.gu_serie = '' AND trim(a.gu_apl) = trim(:Remision) ";
  }
  if (isset($Prefactura)) {
    $where .= "AND a.gu_seriepf = :PrefaSerie AND trim(a.gu_prefac) = trim(:Prefactura) ";
  }
  if (isset($Pedido)) {
    $where .= "AND trim(a.gu_ped) = trim(:Pedido) ";
  }
  if (isset($Traspaso)) {
    $where .= "AND trim(gu_trasp) = trim(:Traspaso) ";
  }
  if (isset($OrdenRetorno)) {
    $where .= "AND trim(a.gu_ordret) = trim(:OrdenRetorno) ";
  }
  if (isset($Carrier)) {
    $where .= "AND a.gu_carrier = :Carrier ";
  }
  if (isset($OrdenCompra)) {
    $where .= "AND trim(a.gu_numeoc) = trim(:OrdenCompra) ";
  }
  if (in_array($TipoUsuario, ["A"])) {
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND c.cc_age = :strUsuario ";
  }

  try {
    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # Borra tablas temporales
    BorraTemporales($conn);

    # Crea tablas temporales normalizadas para categorias y subcategorias
    $sqlCmd = "CREATE TEMPORARY TABLE carriers AS 
    SELECT trim(t_gpo) as idcarrier, t_descr AS carriernom 
      FROM var020 WHERE t_tica = '35' AND t_gpo <> ''
      ORDER BY t_gpo";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    # Construye la Instrucción SELECT de forma dinámica ------------------------
    $sqlCmd = "SELECT a.gu_paq,a.gu_fecha,a.gu_of,a.gu_seriepf,a.gu_prefac,
      a.gu_serie,a.gu_apl,a.gu_numeoc,a.gu_trasp,a.gu_ordret,a.gu_pzas,a.gu_can,
      a.gu_imp,a.gu_feex,a.gu_carrier,d.carriernom,a.gu_guia,a.gu_status,
      a.gu_fecrec,a.gu_observ,a.gu_letra,a.gu_ped,a.gu_num,a.gu_fil,
      trim(c.cc_raso) cc_raso, trim(c.cc_suc) cc_suc, c.cc_plazo, c.cc_age
      FROM guias10 a
      LEFT JOIN cli010 c ON c.cc_num = a.gu_num AND c.cc_fil = a.gu_fil
      LEFT JOIN carriers d ON a.gu_carrier = d.idcarrier
      $where
      ORDER BY a.gu_paq";

    # Preparación de la consulta y agregación de parámetros
    unset($oSQL);
    $oSQL = $conn->prepare($sqlCmd);
    /*
    if (isset($OficinaDesde)) {
      $oSQL->bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    }
    if (isset($OficinaHasta)) {
      $oSQL->bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);
    }
      */
    $oSQL->bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);

    if (isset($ClienteCodigo)) {
      $oSQL->bindParam(":ClienteCodigo", $ClienteCodigo, PDO::PARAM_STR);
      $oSQL->bindParam(":ClienteFilial", $ClienteFilial, PDO::PARAM_STR);
    }
    //    if (isset($ClteCredito)) {
    //      $oSQL->bindParam(":ClteCredito", $ClteCredito, PDO::PARAM_STR);
    //    }
    if (isset($Paquete)) {
      $oSQL->bindParam(":Paquete", $Paquete, PDO::PARAM_STR);
    }
    if (isset($FactSerie)) {
      $oSQL->bindParam(":FactSerie", $FactSerie, PDO::PARAM_STR);
    }
    if (isset($Factura)) {
      $oSQL->bindParam(":Factura", $Factura, PDO::PARAM_STR);
    }
    if (isset($Remision)) {
      $oSQL->bindParam(":Remision", $Remision, PDO::PARAM_STR);
    }
    if (isset($PrefaSerie)) {
      $oSQL->bindParam(":PrefaSerie", $PrefaSerie, PDO::PARAM_STR);
    }
    if (isset($Prefactura)) {
      $oSQL->bindParam(":Prefactura", $Prefactura, PDO::PARAM_STR);
    }
    if (isset($Pedido)) {
      $oSQL->bindParam(":Pedido", $Pedido, PDO::PARAM_STR);
    }
    if (isset($Traspaso)) {
      $oSQL->bindParam(":Traspaso", $Traspaso, PDO::PARAM_STR);
    }
    if (isset($OrdenRetorno)) {
      $oSQL->bindParam(":OrdenRetorno", $OrdenRetorno, PDO::PARAM_STR);
    }
    if (isset($Carrier)) {
      $oSQL->bindParam(":Carrier", $Carrier, PDO::PARAM_STR);
    }
    if (isset($OrdenCompra)) {
      $oSQL->bindParam(":OrdenCompra", $OrdenCompra, PDO::PARAM_STR);
    }
    if ($TipoUsuario == "A") {
      $oSQL->bindParam(":strUsuario", $strUsuario, PDO::PARAM_STR);
    }

    # Ejecución de la consulta -------------------------------------------------
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    //var_dump($numRows, $OficinaDesde, $OficinaHasta, $ClienteCodigo, $ClienteFilial);
    //$oSQL->debugDumpParams();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    BorraTemporales($conn);
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  # Borra tablas temporales y cierra conexión de datos
  BorraTemporales($conn);
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
function CreaDataCompuesta($data)
{

  $contenido = array();
  $guias = array();

  // Detalle de documentos con saldo
  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea array, en este caso no hay nodos adicionales
      $guias = [
        "Paquete"     => $row["gu_paq"],
        "FechaPaq"    => (is_null($row["gu_fecha"]) ? "" : $row["gu_fecha"]),
        "Oficina"     => $row["gu_of"],
        "SeriePref"   => $row["gu_seriepf"],
        "Prefactura"  => $row["gu_prefac"],
        "Serie"       => $row["gu_serie"],
        "Factura"     => $row["gu_apl"],
        "OrdenComp"   => $row["gu_numeoc"],
        "Traspaso"    => $row["gu_trasp"],
        "OrdenReto"   => $row["gu_ordret"],
        "Piezas"      => intval($row["gu_pzas"]),
        "Gramos"      => floatval($row["gu_can"]),
        "Importe"     => floatval($row["gu_imp"]),
        "FechaExpe"   => (is_null($row["gu_feex"]) ? "" : $row["gu_feex"]),
        "Carrier"     => $row["gu_carrier"],
        "CarrierNom"  => $row["carriernom"],
        "Guia"        => $row["gu_guia"],
        "Stat"        => $row["gu_status"],
        "FechaRece"   => (is_null($row["gu_fecrec"]) ? "" : $row["gu_fecrec"]),
        "Observac"    => $row["gu_observ"],
        "Letra"       => $row["gu_letra"],
        "Pedido"      => $row["gu_ped"],
        "Cliente"     => $row["gu_num"],
        "Filial"      => $row["gu_fil"],
      ];

      // Se agrega el array del nuevo cliente a la seccion "contenido"
      array_push($contenido, $guias);
    }

    $contenido = [
      "ClienteCodigo" => $data[0]["gu_num"],
      "ClienteFilial" => $data[0]["gu_fil"],
      "ClienteNombre" => $data[0]["cc_raso"],
      "ClienteSucursal" => $data[0]["cc_suc"],
      "Guias"         => $contenido
    ];
  }

  return $contenido;
}

/**
 * Borra tablas temporales en caso de que existan
 */
function BorraTemporales($conn)
{
  $sqlCmd = "DROP TABLE IF EXISTS carriers;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  return;
}
