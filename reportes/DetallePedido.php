<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Detalle de Pedido
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Constantes locales
const K_SCRIPTNAME  = "detallepedido.php";

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
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$PedidoLetra    = null;     // Letra del pedido de venta
$PedidoFolio    = null;     // Folio del pedido de venta
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
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME de los mensajes
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (!in_array($TipoUsuario, ["C", "A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
    }
  }

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

  if (!isset($_GET["PedidoLetra"])) {
    throw new Exception("El parametro obligatorio 'PedidoLetra' no fue definido.");
  } else {
    $PedidoLetra = $_GET["PedidoLetra"];
  }

  if (!isset($_GET["PedidoFolio"])) {
    throw new Exception("El parametro obligatorio 'PedidoFolio' no fue definido.");
  } else {
    $PedidoFolio = $_GET["PedidoFolio"];
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario", "Usuario", "ClienteCodigo", "ClienteFilial",
  "PedidoLetra", "PedidoFolio"
);

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_GET);
foreach ($arrParam as $param) {
  if (!in_array($param, $arrPermitidos)) {
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

/*
if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}
*/

# Ejecuta la consulta 
try {
  $data = SelectPedidos($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $PedidoLetra, $PedidoFolio);

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
    //"Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => $dataCompuesta
  ];
} catch (Exception $e) {
  $response = [
    "Codigo"      => K_API_ERRSQL,
    "Mensaje"     => $conn->get_last_error(),
    //"Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
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
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $Password
 * @param string $Status
 * @param string $AgenteCodigo
 * @return array
 */
function SelectPedidos($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $PedidoLetra, $PedidoFolio)
{
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
  $strPedidoFolio   = str_pad($PedidoFolio, 6, " ", STR_PAD_LEFT);

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Construyo dinamicamente la condicion WHERE
  $where = "
  WHERE a.pe_letra = :PedidoLetra AND a.pe_ped = :strPedidoFolio 
    AND a.pe_num = :strClienteCodigo AND a.pe_fil = :strClienteFilial 
  ";

  if (in_array($TipoUsuario, ["A"])) {
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND a.pe_age = :strUsuario ";
  }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Instrucción SELECT
  $sqlCmd = "SELECT a.pe_of,a.pe_lin,trim(a.pe_clave) pe_clave,a.pe_letra,trim(a.pe_ped) pe_ped,
    trim(a.pe_rengl) pe_rengl,a.pe_status,a.pe_fepe,a.pe_fecao,a.pe_canpe,a.pe_grape,a.pe_fecs,
    a.pe_cansu,a.pe_grasu,trim(a.pe_serie) pe_serie,trim(a.pe_nufact) pe_nufact,a.pe_fete,a.pe_canpro,a.pe_gpo,a.pe_cat,a.pe_scat,
    a.pe_cp4,a.pe_cp45,a.pe_cp5,a.pe_cp55,a.pe_cp6,a.pe_cp65,a.pe_cp7,a.pe_cp75,a.pe_cp8,a.pe_cp85,
    a.pe_cp9,a.pe_cp95,a.pe_cp10,a.pe_cp105,a.pe_cp11,a.pe_cp115,a.pe_cp12,a.pe_cp125,a.pe_cp13,
    a.pe_mpx pe_mpx,a.pe_cpx,trim(b.c_descr) cpt_descr,c.pe_fepep,c.pe_canpe pe_canpep,c.pe_grape pe_grapep,
    c.pe_canpro,c.pe_grapro,c.pe_fecterm
    FROM ped100 a 
    LEFT JOIN inv010 b ON b.c_lin=a.pe_lin AND b.c_clave=a.pe_clave 
		LEFT JOIN ped150 c ON c.pe_letra=a.pe_letra AND c.pe_ped=a.pe_ped
					AND c.pe_lin=a.pe_lin AND c.pe_clave=a.pe_clave AND c.pe_rengl=a.pe_rengl
    $where 
    ORDER BY a.pe_rengl";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn->prepare($sqlCmd);

    $oSQL->bindParam(":strClienteCodigo", $strClienteCodigo, PDO::PARAM_STR);
    $oSQL->bindParam(":strClienteFilial", $strClienteFilial, PDO::PARAM_STR);
    $oSQL->bindParam(":PedidoLetra", $PedidoLetra, PDO::PARAM_STR);
    $oSQL->bindParam(":strPedidoFolio", $strPedidoFolio, PDO::PARAM_STR);

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

  $conn = null;   // Cierra conexión

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
  $pedidos    = array();

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea un array con los nodos requeridos
      $pedidos = [
        "PedidoFila" => $row["pe_rengl"],
        "ArticuloLinea" => $row["pe_lin"],
        "ArticuloCodigo" => $row["pe_clave"],
        "ArticuloDescripc" => $row["cpt_descr"],
        "PedidoStatus" => $row["pe_status"],
        "ArticuloCategoria"  => $row["pe_cat"],
        "ArticuloSubcategoria" => $row["pe_scat"],
        "FechaPedido"  => $row["pe_fepe"],
        "CantidadPedida" => intval($row["pe_canpe"]),
        "FechaSurtido" => $row["pe_fecs"],
        "CantidadSurtida" => intval($row["pe_cansu"]),
        "DiferenciaSurtido" => intval($row["pe_canpe"] - $row["pe_cansu"]),
        "FacturaSerie" => $row["pe_serie"],
        "FacturaFolio" => $row["pe_nufact"],
        "FechaTerminacionArticulo" => $row["pe_fete"],
        "CantidadMedida4" => intval($row["pe_cp4"]),
        "CantidadMedida4_5" => intval($row["pe_cp45"]),
        "CantidadMedida5" => intval($row["pe_cp5"]),
        "CantidadMedida5_5" => intval($row["pe_cp55"]),
        "CantidadMedida6" => intval($row["pe_cp6"]),
        "CantidadMedida6_5" => intval($row["pe_cp65"]),
        "CantidadMedida7" => intval($row["pe_cp7"]),
        "CantidadMedida7_5" => intval($row["pe_cp75"]),
        "CantidadMedida8" => intval($row["pe_cp8"]),
        "CantidadMedida8_5" => intval($row["pe_cp85"]),
        "CantidadMedida9" => intval($row["pe_cp9"]),
        "CantidadMedida9_5" => intval($row["pe_cp95"]),
        "CantidadMedida10" => intval($row["pe_cp10"]),
        "CantidadMedida10_5" => intval($row["pe_cp105"]),
        "CantidadMedida11" => intval($row["pe_cp11"]),
        "CantidadMedida11_5" => intval($row["pe_cp115"]),
        "CantidadMedida12" => intval($row["pe_cp12"]),
        "CantidadMedida12_5" => intval($row["pe_cp125"]),
        "CantidadMedida13" => intval($row["pe_cp13"]),
        "MedidaEspecial" => $row["pe_mpx"],
        "CantidadMedidaEspecial" => intval($row["pe_cpx"]),
        "FechaPedidoProduccion" => $row["pe_fepep"],
        "CantidadPedidoProduccion" => intval(is_null($row["pe_canpep"]) ? 0 : $row["pe_canpep"]),
        "CantidadProducida" => intval(is_null($row["pe_canpro"]) ? 0 : $row["pe_canpro"]),
        "DiferenciaProducido" => intval($row["pe_canpep"] - $row["pe_canpro"]),
        "FechaProduccionArticulo" => $row["pe_fecterm"]
      ];

      // Se agrega el array a la seccion "contenido"
      array_push($contenido, $pedidos);
    }   // foreach($data as $row)

    $contenido = [
      "PedidoLetra" => $data[0]["pe_letra"],
      "PedidoFolio" => $data[0]["pe_ped"],
      "PedidoArticulos" => $contenido
    ];
  } // count($data)>0

  return $contenido;
}
