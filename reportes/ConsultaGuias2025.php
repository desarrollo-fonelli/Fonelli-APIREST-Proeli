<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * ConsultaGuias2025 - corresponde a "Consulta de Paquetes" de Proeli
 * --------------------------------------------------------------------------
 * dRendon 30.05.2025 
 * Consulta ajustada a los datos de Proeli en mayo de 2025
 * Se basa en "ConsultaGuias.php"
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "ConsultaGuias2025.php";

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
$ClienteCodigo  = null;   // Id del cliente
$ClienteFilial  = null;   // Filial del cliente
$OficinaDesde   = null;   // Código Oficina en que se registra el pedido 
$OficinaHasta   = null;   // Código Oficina en que se registra el pedido
$Paquete        = null;   // Folio del paquete
$DocTipo        = null;   // Tipo de documento (Factura, Remision, Prefactura, Traspaso, Orden de Retorno)
$DocSerie       = null;   // Serie del documento
$DocFolio       = null;   // Folio del documento
$PedLetra       = null;   // Letra del pedido
$Pedido         = null;   // Pedido del cliente
$OrdenCompra    = null;   // Orden de Compra del cliente (Liverpool)
$Carrier        = null;   // Id del Carrier que transporta el paquete
$FechaDesde     = null;   // Fecha inicial de los paquetes
$DocumSinGuia   = null;   // Indica si se incluyen documentos sin guia
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

  if (!isset($_GET["DocTipo"])) {
    throw new Exception("El parametro obligatorio 'DocTipo' no fue definido.");
  } else {
    $DocTipo = $_GET["DocTipo"];
    if (! in_array($DocTipo, ['Todos', 'Factura', 'Remision', 'Prefactura', 'Traspaso', 'OrdenRetorno'])) {
      throw new Exception("El valor '" . $DocTipo . "' para 'DocTipo' no es válido");
    }
  }

  if (!isset($_GET["FechaDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaDesde' no fue definido.");
  } else {
    $FechaDesde = $_GET["FechaDesde"];
    if (!ValidaFormatoFecha($FechaDesde)) {
      throw new Exception("El parametro 'FechaInic' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }

  if (isset($_GET["DocumSinGuia"])) {
    $DocumSinGuia = $_GET["DocumSinGuia"];
  } else {
    $DocumSinGuia = "N";
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
  "ClienteCodigo",
  "ClienteFilial",
  "OficinaDesde",
  "OficinaHasta",
  "Paquete",
  "DocTipo",
  "DocSerie",
  "DocFolio",
  "PedLetra",
  "Pedido",
  "Traspaso",
  "OrdenCompra",
  "Carrier",
  "FechaDesde",
  "DocumSinGuia",
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

if (isset($_GET["Paquete"])) {
  $Paquete = $_GET["Paquete"];
}

// DocTipo es parámetro obligatorio
switch ($DocTipo) {
  case 'Factura':
    if (isset($_GET["DocSerie"])) {
      $DocSerie = $_GET["DocSerie"];
    } else {
      $mensaje = "Debe indicar una 'Serie' cuando busque una 'Factura'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    if (isset($_GET["DocFolio"])) {
      $DocFolio = $_GET["DocFolio"];
    } else {
      $mensaje = "Debe indicar un numero de 'Factura'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    break;

  case 'Remision':
    if (isset($_GET["DocSerie"]) && $_GET["DocSerie"] != "") {
      $mensaje = "La 'Remisión' no debe incluir 'Serie'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    $DocSerie = '  ';
    if (isset($_GET["DocFolio"])) {
      $DocFolio = $_GET["DocFolio"];
    } else {
      $mensaje = "Debe indicar un numero de 'Remisión'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    break;

  case 'Prefactura':
    if (isset($_GET["DocSerie"])) {
      $DocSerie = $_GET["DocSerie"];
    } else {
      $mensaje = "Debe indicar una 'Serie' cuando busque una 'PreFactura'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    if (isset($_GET["DocFolio"])) {
      $DocFolio = $_GET["DocFolio"];
    } else {
      $mensaje = "Debe indicar un numero de 'PreFactura'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    break;

  case 'Traspaso':
    if (isset($_GET["DocSerie"]) && $_GET["DocSerie"] != "") {
      $mensaje = "Los 'Traspasos' no deben incluir 'Serie'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    $DocSerie = '  ';
    if (isset($_GET["DocFolio"])) {
      $DocFolio = $_GET["DocFolio"];
    } else {
      $mensaje = "Debe indicar un numero de 'Traspaso'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    break;

  case 'OrdenRetorno':
    if (isset($_GET["DocSerie"]) && $_GET["DocSerie"] != "") {
      $mensaje = "La 'OrdenRetorno' no deben incluir 'Serie'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    $DocSerie = '  ';
    if (isset($_GET["DocFolio"])) {
      $DocFolio = $_GET["DocFolio"];
    } else {
      $mensaje = "Debe indicar un numero de 'OrdenRetorno'";
      http_response_code(400);
      echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
      exit;
    }
    break;
}

if (isset($_GET["Pedido"])) {
  $Pedido = $_GET["Pedido"];

  if (isset($GET["PedLetra"])) {
    $PedLetra = $GET["PedLetra"];
  } else {
    $PedLetra = "C";
  }
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
    $ClienteCodigo,
    $ClienteFilial,
    $OficinaDesde,
    $OficinaHasta,
    $Paquete,
    $DocTipo,
    $DocSerie,
    $DocFolio,
    $PedLetra,
    $Pedido,
    $OrdenCompra,
    $Carrier,
    $FechaDesde,
    $DocumSinGuia,
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
 */
function SelectConsultaGuias(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $OficinaDesde,
  $OficinaHasta,
  $Paquete,
  $DocTipo,
  $DocSerie,
  $DocFolio,
  $PedLetra,
  $Pedido,
  $OrdenCompra,
  $Carrier,
  $FechaDesde,
  $DocumSinGuia,
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

  if (isset($Paquete)) {
    $Paquete = str_pad(trim($Paquete), 6, " ", STR_PAD_LEFT);
  }
  if ($DocTipo != 'Todos') {
    if (isset($DocFolio)) {
      $DocFolio = str_pad(trim($DocFolio), 6, " ", STR_PAD_LEFT);
    }
  }
  if (isset($Pedido)) {
    $Pedido = str_pad(trim($Pedido), 6, " ", STR_PAD_LEFT);
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
    //AND a.gu_fecha >= :FechaDesde ";
  }

  if (isset($DocumSinGuia) && $DocumSinGuia == 'S') {
    $where .= "AND (a.gu_fecha IS NULL OR a.gu_fecha >= :FechaDesde) ";
  } else {
    $where .= "AND a.gu_fecha >= :FechaDesde ";
  }
  //$where .= "AND a.gu_fecha >= :FechaDesde ";

  if (isset($Paquete)) {
    $where .= "AND a.gu_paq = :Paquete ";
  }

  // Asumo que en el frontend se comprobó que la serie
  // y el folio se indicaron correctamente
  if ($DocTipo != 'Todos') {
    switch ($DocTipo) {
      case 'Factura':
        $where .= "AND a.gu_serie = :DocSerie AND trim(a.gu_apl) = trim(:DocFolio) ";
        break;
      case 'Remision':
        $where .= "AND trim(a.gu_apl) = trim(:DocFolio) ";
        break;
      case 'Prefactura':
        $where .= "AND a.gu_seriepf = :DocSerie AND trim(a.gu_prefac) = trim(:DocFolio) ";
        break;
      case 'Traspaso':
        $where .= "AND trim(a.gu_trasp) = trim(:DocFolio) ";
        break;
      case 'OrdenRetorno':
        $where .= "AND trim(a.gu_ordret) = trim(:DocFolio) ";
        break;
    }
  }

  if (isset($PedLetra)) {
    $where .= "AND trim(a.gu_letra) = trim(:PedLetra) ";
  }

  if (isset($Pedido)) {
    $where .= "AND trim(a.gu_ped) = trim(:Pedido) ";
  }

  if (isset($OrdenCompra)) {
    $where .= "AND trim(a.gu_numeoc) = trim(:OrdenCompra) ";
  }
  if (isset($Carrier)) {
    $where .= "AND a.gu_carrier = :Carrier ";
  }

  if ($DocumSinGuia == "N") {
    $where .= "AND a.gu_paq <> '      ' ";
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

    # Crea tablas temporales: Carriers
    $sqlCmd = "CREATE TEMPORARY TABLE carriers AS 
    SELECT trim(t_gpo) as idcarrier, t_descr AS carriernom 
      FROM var020 WHERE t_tica = '35' AND t_gpo <> ''
      ORDER BY t_gpo";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();


    # Construye la Instrucción SELECT de forma dinámica ------------------------
    $sqlCmd = "SELECT a.gu_paq,a.gu_fecha,a.gu_status,a.gu_guia,
      a.gu_carrier,d.carriernom,a.gu_fecrec,a.gu_operec,a.gu_observ,
      a.gu_num,a.gu_fil,a.gu_ope,a.gu_of,
      CASE
        WHEN a.gu_prefac <> '' THEN 'Prefactura'
        WHEN a.gu_apl <> '' THEN 
          CASE 
            WHEN a.gu_serie <> '' THEN 'Factura'
            ELSE 'Remision'
          END
        WHEN a.gu_trasp <> '' THEN 'Traspaso'
        WHEN a.gu_ordret <> '' THEN 'OrdenRetorno'
      END AS doctipo,
      a.gu_serie,a.gu_apl,a.gu_seriepf,a.gu_prefac,
      a.gu_trasp,a.gu_ordret,a.gu_feex,a.gu_feve,a.gu_imp,a.gu_iva,a.gu_pzas,
      a.gu_can,a.gu_letra,a.gu_ped,a.gu_numeoc,gu_fechoc,gu_feccon,
      a.gu_boddes,a.gu_bodrec,a.gu_contra,
      trim(c.cc_raso) cc_raso, trim(c.cc_suc) cc_suc, c.cc_plazo, c.cc_age
      FROM guias10 a
      LEFT JOIN cli010 c ON c.cc_num = a.gu_num AND c.cc_fil = a.gu_fil
      LEFT JOIN carriers d ON a.gu_carrier = d.idcarrier
      $where
      ORDER BY a.gu_paq";

    //var_dump($sqlCmd);

    # Preparación de la consulta y agregación de parámetros
    unset($oSQL);
    $oSQL = $conn->prepare($sqlCmd);

    if (isset($ClienteCodigo)) {
      $oSQL->bindParam(":ClienteCodigo", $ClienteCodigo, PDO::PARAM_STR);
      $oSQL->bindParam(":ClienteFilial", $ClienteFilial, PDO::PARAM_STR);
    }
    $oSQL->bindParam(":OficinaDesde", $OficinaDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":OficinaHasta", $OficinaHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":FechaDesde", $FechaDesde, PDO::PARAM_STR);

    if (isset($Paquete)) {
      $oSQL->bindParam(":Paquete", $Paquete, PDO::PARAM_STR);
    }
    //$oSQL->bindParam(":DocTipo", $DocTipo, PDO::PARAM_STR);
    if ($DocTipo != 'Todos') {
      if (isset($DocSerie)) {
        if ($DocTipo != 'Remision' && $DocTipo != 'Traspaso' && $DocTipo != 'OrdenRetorno') {
          $oSQL->bindParam(":DocSerie", $DocSerie, PDO::PARAM_STR);
        }
      }
      if (isset($DocFolio)) {
        $oSQL->bindParam(":DocFolio", $DocFolio, PDO::PARAM_STR);
      }
    }
    if (isset($Pedido)) {
      $oSQL->bindParam(":PedLetra", $PedLetra, PDO::PARAM_STR);
      $oSQL->bindParam(":Pedido", $Pedido, PDO::PARAM_STR);
    }
    if (isset($OrdenCompra)) {
      $oSQL->bindParam(":OrdenCompra", $OrdenCompra, PDO::PARAM_STR);
    }
    if (isset($Carrier)) {
      $oSQL->bindParam(":Carrier", $Carrier, PDO::PARAM_STR);
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
  $paquetes = array();
  $guiaDocums = array();

  $totPiezas  = 0;
  $totGramos  = 0.0;
  $totImporte = 0.0;
  $totIva     = 0.0;

  // Detalle de documentos con saldo
  if (count($data) > 0) {

    $paqueteGroup = $data[0]["gu_paq"];
    //$paqueteGroup = "";

    $Paquete     = trim($data[0]["gu_paq"]);
    $FechaPaq    = (is_null($data[0]["gu_fecha"]) ? "" : $data[0]["gu_fecha"]);
    $Status      = $data[0]["gu_status"];
    $Guia        = trim($data[0]["gu_guia"]);
    $Carrier     = (is_null($data[0]["gu_carrier"]) ? "" : $data[0]["gu_carrier"]);
    $CarrierNom  = (is_null($data[0]["carriernom"]) ? "" : $data[0]["carriernom"]);
    $FechaRecep  = (is_null($data[0]["gu_fecrec"]) ? "" : $data[0]["gu_fecrec"]);
    $Observac    = trim($data[0]["gu_observ"]);
    $totPiezas   = intval($data[0]["gu_pzas"]);
    $totGramos   = round(floatval($data[0]["gu_can"]), 2);
    $totImporte  = round(floatval($data[0]["gu_imp"]), 2);
    $totIva      = round(floatval($data[0]["gu_iva"]), 2);

    foreach ($data as $row) {

      // Cambio de paquete
      if ($row["gu_paq"] != $paqueteGroup) {

        array_push($paquetes, [
          "Paquete"     => $Paquete,
          "FechaPaq"    => $FechaPaq,
          "Status"      => $Status,
          "Guia"        => $Guia,
          "Carrier"     => $Carrier,
          "CarrierNom"  => $CarrierNom,
          "FechaRecep"  => $FechaRecep,
          "Observac"    => $Observac,
          "TotPiezas"   => $totPiezas,
          "TotGramos"   => round($totGramos, 2),
          "TotImporte"  => round($totImporte, 2),
          "TotIva"      => round($totIva, 2),
          "GuiaDocums"  => $guiaDocums
        ]);

        $guiaDocums = array();
        $paqueteGroup = $row["gu_paq"];

        $Paquete     = trim($row["gu_paq"]);
        $FechaPaq    = (is_null($row["gu_fecha"]) ? "" : $row["gu_fecha"]);
        $Status      = $row["gu_status"];
        $Guia        = trim($row["gu_guia"]);
        $Carrier     = (is_null($row["gu_carrier"]) ? "" : $row["gu_carrier"]);
        $CarrierNom  = (is_null($row["carriernom"]) ? "" : $row["carriernom"]);
        $FechaRecep  = (is_null($row["gu_fecrec"]) ? "" : $row["gu_fecrec"]);
        $Observac    = trim($row["gu_observ"]);
        $totPiezas   = 0;
        $totGramos   = 0.0;
        $totImporte  = 0.0;
        $totIva      = 0.0;
      }

      // Totales por paquete
      $totPiezas  += $row["gu_pzas"];
      $totGramos  += $row["gu_can"];
      $totImporte += $row["gu_imp"];
      $totIva     += $row["gu_iva"];

      // Se crea fila del array de paquetes (guias)

      switch ($row["doctipo"]) {
        case "Factura":
          $docSerie = $row["gu_serie"];
          $docFolio = $row["gu_apl"];
          break;
        case "Remision":
          $docSerie = $row["gu_serie"];
          $docFolio = $row["gu_apl"];
          break;
        case "Prefactura":
          $docSerie = $row["gu_seriepf"];
          $docFolio = $row["gu_prefac"];
          break;
        case "Traspaso":
          $docSerie = "";
          $docFolio = $row["gu_trasp"];
          break;
        case "OrdenRetorno":
          $docSerie = "";
          $docFolio = $row["gu_ordret"];
          break;
        default:
          $docSerie = "";
          $docFolio = "";
      }

      array_push($guiaDocums, [
        "Oficina"     => $row["gu_of"],
        "DocTipo"     => $row["doctipo"],
        "DocSerie"    => $docSerie,
        "DocFolio"    => $docFolio,
        "DocFecha"    => (is_null($row["gu_feex"]) ? "" : $row["gu_feex"]),
        "DocFecVenc"  => (is_null($row["gu_feve"]) ? "" : $row["gu_feve"]),
        "DocImporte"  => round(floatval($row["gu_imp"]), 2),
        "DocIva"      => round(floatval($row["gu_iva"]), 2),
        "DocPiezas"   => intval($row["gu_pzas"]),
        "DocGramos"   => round(floatval($row["gu_can"]), 2),
        "PedLetra"    => $row["gu_letra"],
        "Pedido"      => $row["gu_ped"],
        "OrdenComp"   => trim($row["gu_numeoc"]),
        "OrdCompFecha"     => (is_null($row["gu_fechoc"]) ? "" : $row["gu_fechoc"]),
        "OrdCompFechaCon"  => (is_null($row["gu_feccon"]) ? "" : $row["gu_feccon"]),
        "OrdCompBodDest"   => trim($row["gu_boddes"]),
        "OrdCompBodRec"    => trim($row["gu_bodrec"]),
        "OrdCompContraRec" => trim($row["gu_contra"])
      ]);
    }

    // ultimo renglon
    array_push($paquetes, [
      "Paquete"     => trim($row["gu_paq"]),
      "FechaPaq"    => (is_null($row["gu_fecha"]) ? "" : $row["gu_fecha"]),
      "Status"      => $row["gu_status"],
      "Guia"        => trim($row["gu_guia"]),
      "Carrier"     => (is_null($row["gu_carrier"]) ? "" : $row["gu_carrier"]),
      "CarrierNom"  => (is_null($row["carriernom"]) ? "" : $row["carriernom"]),
      "FechaRecep"  => (is_null($row["gu_fecrec"]) ? "" : $row["gu_fecrec"]),
      "Observac"    => trim($row["gu_observ"]),
      "TotPiezas"   => intval($totPiezas),
      "TotGramos"   => round(floatval($totGramos), 2),
      "TotImporte"  => round(floatval($totImporte), 2),
      "TotIva"      => round(floatval($totIva), 2),
      "GuiaDocums"  => $guiaDocums
    ]);

    $contenido = [
      "ClienteCodigo"   => $data[0]["gu_num"],
      "ClienteFilial"   => $data[0]["gu_fil"],
      "ClienteNombre"   => trim($data[0]["cc_raso"]),
      "ClienteSucursal" => trim($data[0]["cc_suc"]),
      "Paquetes"        => $paquetes
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
