<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Lista de CFDIs de clientes
 * --------------------------------------------------------------------------
 * dRendon 11.04.2025 
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
const K_SCRIPTNAME  = "listacfdis.php";

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
$FechaInic      = null;     // Fecha Inicial para buscar documentos
$Pedido         = null;     // Pedido asociado a una o varias facturas
$TipoDoc        = null;     // Filtra por tipo de documento: Facturas | NCredito | Todos
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

  # dRendon 04.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "C") {
    if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["FechaInic"])) {
    throw new Exception("El parametro obligatorio 'FechaInic' no fue definido.");
  } else {
    $FechaInic = $_GET["FechaInic"];
    if (!ValidaFormatoFecha($FechaInic)) {
      throw new Exception("El parametro 'FechaInic' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
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
  "Pedido",
  "FechaInic",
  "TipoDoc",
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

if (isset($_GET["Pedido"])) {
  if ($_GET["Pedido"] > 0) {
    $Pedido = $_GET["Pedido"];
  }
}

if (isset($_GET["TipoDoc"])) {
  if (! in_array($_GET["TipoDoc"], ["FAC", "NCR"])) {
    $mensaje = "Valor '" . $TipoDoc . "' NO permitido para 'TipoDoc'";
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
  $TipoDoc = $_GET["TipoDoc"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectCFDIS(
    $TipoUsuario,
    $Usuario,
    $ClienteCodigo,
    $ClienteFilial,
    $FechaInic,
    $Pedido,
    $TipoDoc
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
 * 
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $FechaInic
 * @param string $Pedido
 * @param string $TipoDoc
 * @return array
 */
function SelectCFDIS(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $FechaInic,
  $Pedido,
  $TipoDoc
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

  $strClienteCodigo = str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT);
  $strClienteFilial = str_pad($ClienteFilial, 3, " ", STR_PAD_LEFT);

  // Doy un plazo de hasta Cinco minutos para completar cada consulta...
  set_time_limit(300);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  # 25.04.2025 dRendon:
  # Las Notas de Crédito en Proeli no guardan el UUID, por lo que no es un
  # criterio correcto.
  # Los documentos que tienen efecto fiscal son los que tiene en la serie (f1_serie)
  # alguno de los valores: F, FF, G, GG

  $where = "WHERE (COALESCE(a.f1_uuid, '') != '' OR a.f1_serie IN ('F', 'FF', 'G', 'GG') ) ";
  $where .= "AND a.f1_num = :strClienteCodigo AND a.f1_fil = :strClienteFilial ";

  if (in_array($TipoUsuario, ["A"])) {
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND a.f1_age = :strUsuario ";
  }

  if (isset($FechaInic)) {
    $where .= "AND a.f1_feex >= :FechaInic ";
  }

  if (isset($Pedido)) {
    //$where .= "AND c.pe_ped = LPAD(:Pedido,6,' ') ";
    $where .= "AND a.f1_ref = LPAD(:Pedido,6,' ') ";
  }

  /*    if (isset($TipoDoc)) {
          $where .= "AND a.TipoDoc = :TipoDoc ";
        }
  */

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Instrucción SELECT
  $sqlCmd = "SELECT f1_num,f1_fil,max(b.t_descr) t_descr,f1_serie,f1_apl,
    f1_feex,max(f1_imp+f1_iva) total,f1_ref,max(c.pe_ped) pe_ped,
    max(d.cc_raso) cc_raso,max(d.cc_suc) cc_suc, max(d.cc_rfc) cc_rfc  
    FROM fac010 a
    LEFT JOIN var020 b ON b.t_tica='10' AND b.t_gpo='80' AND b.t_clave = a.f1_mov
    LEFT JOIN ped100 c ON c.pe_serie=f1_serie AND c.pe_nufact=f1_apl
    LEFT JOIN cli010 d ON d.cc_num=f1_num AND d.cc_fil=f1_fil 
    $where 
    GROUP BY f1_num,f1_fil,f1_serie,f1_apl,f1_feex,f1_ref ORDER BY f1_feex";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn->prepare($sqlCmd);

    $oSQL->bindParam(":strClienteCodigo", $strClienteCodigo, PDO::PARAM_STR);
    $oSQL->bindParam(":strClienteFilial", $strClienteFilial, PDO::PARAM_STR);
    $oSQL->bindParam(":FechaInic", $FechaInic, PDO::PARAM_STR);

    if (isset($Pedido)) {
      $oSQL->bindParam(":Pedido", $Pedido, PDO::PARAM_STR);
    }

    #    if (isset($TipoDoc)) {
    #     $oSQL->bindParam(":TipoDoc", $TipoDoc, PDO::PARAM_STR);
    #    }

    if ($TipoUsuario == "A") {
      $oSQL->bindParam(":strUsuario", $strUsuario, PDO::PARAM_STR);
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

  $contenido = array();
  $cfdis     = array();
  $filas     = array();

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea un array con los nodos requeridos
      $cfdis = [
        "Documento" => $row["t_descr"],
        "Serie"     => $row["f1_serie"],
        "Folio"     => $row["f1_apl"],
        "Fecha"     => $row["f1_feex"],
        "Total"     => floatval($row["total"]),
        "Ref"       => $row["f1_ref"],
        "Pedido"    => $row["pe_ped"]
      ];

      // Se agrega el array a la seccion "contenido"
      //array_push($contenido, $cfdis);
      array_push($filas, $cfdis);
    }   // foreach($data as $row)

    $contenido = [
      "ClienteCodigo" => $data[0]["f1_num"],
      "ClienteFilial" => $data[0]["f1_fil"],
      "ClienteNombre" => $data[0]["cc_raso"],
      "Sucursal"      => $data[0]["cc_suc"],
      "ClienteRfc"    => $data[0]["cc_rfc"],
      "Cfdis"         => $filas
    ];
  } // count($data)>0

  return $contenido;
}
