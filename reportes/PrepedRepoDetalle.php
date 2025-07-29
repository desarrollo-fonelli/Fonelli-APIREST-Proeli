<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Detalle de Prepedido 
 * --------------------------------------------------------------------------
 * dRendon 24.07.2025
 *  Artículos incluidos en el prepedido solicitado
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
const K_SCRIPTNAME  = "PrepedidosRepoDeta.php";

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
  "TipoUsuario",
  "Usuario",
  "ClienteCodigo",
  "ClienteFilial",
  "PedidoLetra",
  "PedidoFolio"
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

/*
if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}
*/

# Llama la rutina que ejecuta la consulta SQL
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

$conn = null;   // Cierra conexión

echo $response;

return;

/**
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
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
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  $where = "
  WHERE ped.pe_letra = :PedidoLetra AND ped.pe_ped = :strPedidoFolio 
    AND ped.pe_num = :strClienteCodigo AND ped.pe_fil = :strClienteFilial 
  ";

  if (in_array($TipoUsuario, ["A"])) {
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND ped.pe_age = :strUsuario ";
  }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Instrucción SELECT
  $sqlCmd = "SELECT ped.pe_letra,trim(ped.pe_ped) pe_ped,ped.pe_lin,trim(ped.pe_clave) pe_clave,
    trim(ped.pe_rengl) pe_rengl,ped.pe_status,ped.pe_canpe,ped.pe_grape,ped.pe_ticos,ped.pe_penep,
    CASE ped.pe_ticos
      WHEN '1' THEN ped.pe_canpe * ped.pe_penep
      WHEN '2' THEN ped.pe_grape * ped.pe_penep
      ELSE 0
    END AS totalfila,
    trim(ped.pe_alta) pe_alta, trim(ped.pe_obs) pe_obs,
    ped.pe_cp3,ped.pe_cp35,
    ped.pe_cp4,ped.pe_cp45,ped.pe_cp5,ped.pe_cp55,ped.pe_cp6,ped.pe_cp65,ped.pe_cp7,ped.pe_cp75,ped.pe_cp8,
    ped.pe_cp85,ped.pe_cp9,ped.pe_cp95,ped.pe_cp10,ped.pe_cp105,ped.pe_cp11,ped.pe_cp115,ped.pe_cp12,ped.pe_cp125,
    ped.pe_cp13,ped.pe_cp135,ped.pe_cp14,ped.pe_cp145,ped.pe_cp15,ped.pe_cp155,
    ped.pe_mpx pe_mpx,ped.pe_cpx,
    trim(itm.c_descr) c_descr,
    SUBSTRING(lpt.t_param FROM 17 FOR 1) medidas,
    SUBSTRING(lpt.t_param FROM 20 FOR 1) intext    
    FROM prep100 ped 
    LEFT JOIN (SELECT DISTINCT ON (c_lin,c_clave) * FROM inv010) itm
           ON itm.c_lin=ped.pe_lin AND itm.c_clave=ped.pe_clave 
    LEFT JOIN var020 lpt 
           ON lpt.t_tica='05' AND lpt.t_gpo=ped.pe_lin
    $where 
    ORDER BY CAST(ped.pe_rengl AS integer)";

  // var_dump($sqlCmd);

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
  $items      = array();
  $item       = array();

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea un array con los nodos requeridos
      $item = [
        "Renglon" => $row["pe_rengl"],
        "ItemLinea" => $row["pe_lin"],
        "ItemCodigo" => $row["pe_clave"],
        "ItemDescripc" => $row["c_descr"],
        "ItemStatus" => $row["pe_status"],
        "Piezas" => intval($row["pe_canpe"]),
        "Gramos" => floatval($row["pe_grape"]),
        "Precio" => floatval($row["pe_penep"]),
        "TipoCosto" => $row["pe_ticos"],
        "Importe" => round(floatval($row["totalfila"]), 2),
        "Medidas" => $row["medidas"] == "1" ? true : false,
        "IntExt" => $row["intext"],
        "ItemAlta" => $row["pe_alta"],
        "Observac" => $row["pe_obs"],
        "cp3" => intval($row["pe_cp3"]),
        "cp35" => intval($row["pe_cp35"]),
        "cp4" => intval($row["pe_cp4"]),
        "cp45" => intval($row["pe_cp45"]),
        "cp5" => intval($row["pe_cp5"]),
        "cp55" => intval($row["pe_cp55"]),
        "cp6" => intval($row["pe_cp6"]),
        "cp65" => intval($row["pe_cp65"]),
        "cp7" => intval($row["pe_cp7"]),
        "cp75" => intval($row["pe_cp75"]),
        "cp8" => intval($row["pe_cp8"]),
        "cp85" => intval($row["pe_cp85"]),
        "cp9" => intval($row["pe_cp9"]),
        "cp95" => intval($row["pe_cp95"]),
        "cp10" => intval($row["pe_cp10"]),
        "cp105" => intval($row["pe_cp105"]),
        "cp11" => intval($row["pe_cp11"]),
        "cp115" => intval($row["pe_cp115"]),
        "cp12" => intval($row["pe_cp12"]),
        "cp125" => intval($row["pe_cp125"]),
        "cp13" => intval($row["pe_cp13"]),
        "cp135" => intval($row["pe_cp135"]),
        "cp14" => intval($row["pe_cp14"]),
        "cp145" => intval($row["pe_cp145"]),
        "cp15" => intval($row["pe_cp15"]),
        "cp155" => intval($row["pe_cp155"]),
        "mpx" => $row["pe_mpx"],
        "cpx" => intval($row["pe_cpx"])
      ];

      // Se agrega el array a la seccion "contenido"
      array_push($items, $item);
    }   // foreach($data as $row)

    $contenido = [
      "PedLetra" => $data[0]["pe_letra"],
      "PedFolio" => $data[0]["pe_ped"],
      "PedItems" => $items
    ];
  } // count($data)>0

  return $contenido;
}
