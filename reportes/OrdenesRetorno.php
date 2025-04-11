<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista de Ordenes de Retorno
 * --------------------------------------------------------------------------
 * dRendon 06.02.2025
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
const K_SCRIPTNAME  = "OrdenesRetorno.php";

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
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$AgenteCodigo   = null;     // Id del agente de ventas o gerente
$Folio          = null;     // Folio Orden de Retorno
$Referencia     = null;     // Referencia del cliente
$Status         = null;     // Status del cliente
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
  "AgenteCodigo",
  "Folio",
  "Referencia",
  "Status",
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

if (isset($_GET["AgenteCodigo"])) {
  $AgenteCodigo = $_GET["AgenteCodigo"];
}

if (isset($_GET["Folio"])) {
  $Folio = $_GET["Folio"];
}

if (isset($_GET["Referencia"])) {
  $Referencia = $_GET["Referencia"];
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

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectOrdenesRetorno(
    $TipoUsuario,
    $Usuario,
    $ClienteCodigo,
    $ClienteFilial,
    $AgenteCodigo,
    $Folio,
    $Referencia,
    $Status,
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
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param int $AgenteCodigo
 * @param int $Folio
 * @param string $Referencia
 * @param string $Status
 * @param int $Pagina
 * @return array
 */

function SelectOrdenesRetorno(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $AgenteCodigo,
  $Folio,
  $Referencia,
  $Status,
  $Pagina
) {

  $arrData = array();   // Arreglo para almacenar los datos obtenidos
  $where = "";          // Variable para almacenar dinamicamente la clausula WHERE del SELECT

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

  if (isset($ClienteCodigo)) {
    $ClienteCodigo = str_pad(trim($ClienteCodigo), 6, " ", STR_PAD_LEFT);
    $ClienteFilial = str_pad(trim($ClienteFilial), 3, " ", STR_PAD_LEFT);
  }
  if (isset($AgenteCodigo)) {
    $AgenteCodigo = str_pad(trim($AgenteCodigo), 2, " ", STR_PAD_LEFT);
  }
  if (isset($Folio)) {
    $Folio = str_pad(trim($Folio), 6, " ", STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  // require_once "../db/conexion.php";   <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE

  if (isset($ClienteCodigo)) {
    $where = "a.or_num = :ClienteCodigo AND a.or_fil = :ClienteFilial";
  }
  if (isset($AgenteCodigo)) {
    if ($where <> "") {
      $where .= " AND ";
    }
    $where .= "a.or_age = :AgenteCodigo";
  }
  if (isset($Folio)) {
    if ($where <> "") {
      $where .= " AND ";
    }
    $where .= "a.or_folio = :Folio";
  }
  if (isset($Referencia)) {
    if ($where <> "") {
      $where .= " AND ";
    }
    $where .= "trim(a.or_refcia) = trim(:Referencia)";
  }
  if (isset($Status)) {
    if ($where <> "") {
      $where .= " AND ";
    }
    $where .= "a.or_status = :Status";
  }
  if (in_array($TipoUsuario, ["A"])) {
    // Solo aplica filtro cuando el usuario es un agente
    if ($where <> "") {
      $where .= " AND ";
    }
    $where .= "a.or_age = :strUsuario";
  }
  if ($where <> "") {
    $where = "WHERE " . $where;
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

    # Construye la Instruccion SELECT de forma dinámica ------------------------
    $sqlCmd = "SELECT a.*, 
      c.carriernom, trim(d.cc_raso) cc_raso, trim(d.cc_suc) cc_suc,
      e.gc_nom agentenom,f.gu_guia,f.gu_fecha 
      FROM cli130   a 
      LEFT JOIN carriers c ON a.or_carrier = c.idcarrier
      LEFT JOIN cli010   d ON (a.or_num = d.cc_num AND a.or_fil = d.cc_fil)
      LEFT JOIN var030   e ON a.or_age = e.gc_llave 
      LEFT JOIN guias10  f ON (a.or_folio = f.gu_ordret AND f.gu_ordret <> '')
    $where 
    ORDER BY a.or_folio ";

    # Preparación de la consulta y agregación de parámetros
    unset($oSQL);
    $oSQL = $conn->prepare($sqlCmd);
    if (isset($ClienteCodigo)) {
      $oSQL->bindParam(":ClienteCodigo", $ClienteCodigo, PDO::PARAM_STR);
      $oSQL->bindParam(":ClienteFilial", $ClienteFilial, PDO::PARAM_STR);
    }
    if (isset($AgenteCodigo)) {
      $oSQL->bindParam(":AgenteCodigo", $AgenteCodigo, PDO::PARAM_STR);
    }
    if (isset($Folio)) {
      $oSQL->bindParam(":Folio", $Folio, PDO::PARAM_STR);
    }
    if (isset($Referencia)) {
      $oSQL->bindParam(":Referencia", $Referencia, PDO::PARAM_STR);
    }
    if (isset($Status)) {
      $oSQL->bindParam(":Status", $Status, PDO::PARAM_STR);
    }
    if ($TipoUsuario == "A") {
      $oSQL->bindParam(":TipoUsuario", $TipoUsuario, PDO::PARAM_STR);
    }

    # Ejecución de la consulta -------------------------------------------------
    $oSQL->execute();
    //    $oSQL->debugDumpParams();

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
  $OrdenReto = array();

  // Detalle de documentos con saldo
  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea array, en este caso no hay nodos adicionales
      $OrdenReto = [
        "Folio"       => $row["or_folio"],
        "Status"      => $row["or_status"],
        "Agente"      => $row["or_age"],
        "ClienteCodigo" => $row["or_num"],
        "ClienteFilial" => $row["or_fil"],
        "GuiaEmi"     => $row["or_guiaemi"],
        "Carrier"     => $row["or_carrier"],
        "CarrierNom"  => $row["carriernom"],
        "Piezas"      => intval($row["or_pzas"]),
        "Gramos"      => floatval($row["or_grms"]),
        "Descripc"    => $row["or_descr"],
        "Kilataje"    => $row["or_kt"],
        "ValorMerc"   => floatval($row["or_imp"]),
        "TipoOrden"   => $row["or_tipo"],
        "TipoOrdDesc" => ($row["or_tipo"] == '1' ? "Devolución" : "Reparación"),
        "Referencia"  => $row["or_refcia"],
        "TipoDefec"   => $row["or_tipod"],
        "TipoDefDesc" => $row["or_obs"],
        "Email"       => $row["or_email"],
        "ClteNom"     => $row["cc_raso"],
        "AgenteNom"   => $row["agentenom"],
        "FechaSolic"  => (is_null($row["or_fecha"]) ? "" : $row["or_fecha"]),
        "FechaAutor"  => (is_null($row["or_fecaut"]) ? "" : $row["or_fecaut"]),
        "FechaEmis"   => (is_null($row["or_fecemi"]) ? "" : $row["or_fecemi"]),
        "FechaRecep"  => (is_null($row["or_fecrec"]) ? "" : $row["or_fecrec"]),
        "FechaLiber"  => (is_null($row["or_feclib"]) ? "" : $row["or_feclib"]),
        "FechaEnvio"  => (is_null($row["gu_fecha"]) ? "" : $row["gu_fecha"]),
        "Guia"        => $row["gu_guia"],
        "Serie"       => $row["or_serie"],
        "Documento"   => $row["or_ref"]
      ];

      // Se agrega el array del nuevo cliente a la seccion "contenido"
      array_push($contenido, $OrdenReto);
    }

    $contenido = [
      "ClienteCodigo" => $data[0]["or_num"],
      "ClienteFilial" => $data[0]["or_fil"],
      "ClienteNombre" => $data[0]["cc_raso"],
      "ClienteSucursal" => $data[0]["cc_suc"],
      "Ordenes"         => $contenido
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
