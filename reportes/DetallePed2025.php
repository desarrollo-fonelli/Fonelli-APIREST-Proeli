<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Detalle de Pedido nueva versión 2025
 * --------------------------------------------------------------------------
 * dRendon 09.05.2025
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
const K_SCRIPTNAME  = "DetallePed2025.php";

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

$conn = null;   // Cierra conexión

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
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

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
    a.pe_cansu,a.pe_grasu,trim(a.pe_serie) pe_serie,trim(a.pe_nufact) pe_nufact,a.pe_fete,a.pe_canpro,
    a.pe_gpo,a.pe_cat,a.pe_scat,
    a.pe_cp3,a.pe_cp35,
    a.pe_cp4,a.pe_cp45,a.pe_cp5,a.pe_cp55,a.pe_cp6,a.pe_cp65,a.pe_cp7,a.pe_cp75,a.pe_cp8,
    a.pe_cp85,a.pe_cp9,a.pe_cp95,a.pe_cp10,a.pe_cp105,a.pe_cp11,a.pe_cp115,a.pe_cp12,a.pe_cp125,
    a.pe_cp13,a.pe_cp135,a.pe_cp14,a.pe_cp145,a.pe_cp15,a.pe_cp155,
    a.pe_mpx pe_mpx,a.pe_cpx,trim(b.c_descr) cpt_descr,c.pe_fepep,c.pe_canpe pe_canpep,
    c.pe_grape pe_grapep,c.pe_canpro,c.pe_grapro,c.pe_fecterm,
    g.pe_canpro pzascompra,g.pe_grapro grmscompra,
    SUBSTRING(d.t_param FROM 17 FOR 1) medidas,
    SUBSTRING(d.t_param FROM 20 FOR 1) intext,
    e.so_orden ordprod,e.so_letrao ordprodletra,e.so_sobre ordprodsobre,
    concat(e.so_alm) ubicacprod,f.p_descr ubicacprodnomb,
    h.e_alm e_alm, j.p_descr ubicacexisnomb
    FROM ped100 a 
    LEFT JOIN inv010 b ON b.c_lin=a.pe_lin AND b.c_clave=a.pe_clave 
		LEFT JOIN ped150 c ON c.pe_letra=a.pe_letra AND c.pe_ped=a.pe_ped
					AND c.pe_lin=a.pe_lin AND c.pe_clave=a.pe_clave AND c.pe_rengl=a.pe_rengl
    LEFT JOIN var020 d ON d.t_tica='05' AND d.t_gpo=a.pe_lin
    LEFT JOIN op002 e ON e.so_letra=a.pe_letra AND e.so_ped=a.pe_ped AND e.so_renglp=a.pe_rengl 
          AND e.so_status='A' 
    LEFT JOIN maq012 f ON f.p_clave=e.so_alm 
    LEFT JOIN ped160 g ON g.pe_letra=a.pe_letra AND g.pe_ped=a.pe_ped AND g.pe_rengl=a.pe_rengl
    LEFT JOIN mat042 h ON h.e_orden=e.so_orden AND h.e_letrao=' ' AND h.e_sobre='  0'
 		      AND (h.e_sacneto > 0 OR h.e_sacpzas >0)
    	LEFT JOIN maq012 j ON j.p_clave=h.e_alm
    $where 
    ORDER BY CAST(a.pe_rengl AS integer)";

  //  var_dump($sqlCmd);

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
  $pedidos    = array();
  $ubicacion  = "";

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Trabajo con estas variables para no complicar la asignación
      // de elementos en el array
      $CantidadPedidoProduccion = intval(is_null($row["pe_canpep"]) ? 0 : $row["pe_canpep"]);
      $CantidadProducida = intval(is_null($row["pe_canpro"]) ? 0 : $row["pe_canpro"]);
      $DiferenciaProducido = intval($CantidadPedidoProduccion - $CantidadProducida);
      $PzasCompra = intval(is_null($row["pzascompra"]) ? 0 : $row["pzascompra"]);
      $SumaPzasProdCompra = intval($CantidadProducida + $PzasCompra);

      $ubicacion = "";

      if (!is_null($row["ubicacprod"]) && trim($row["ubicacprod"]) != "") {
        $ubicacion = trim($row["ubicacprodnomb"]);
      } else {

        if (!is_null($row["e_alm"])) {
          $ubicacion = trim($row["ubicacexisnomb"]);
        } else {
          if ($row["intext"] == 'I' && !is_null($row["ordprod"])) {
            $ubicacion = "ALMAC MP";
          }
        }
      }

      // Se crea un array con los nodos requeridos
      $pedidos = [
        "PedidoFila" => $row["pe_rengl"],
        "ArticuloLinea" => $row["pe_lin"],
        "ArticuloCodigo" => $row["pe_clave"],
        "ArticuloDescripc" => $row["cpt_descr"],
        "PedidoStatus" => $row["pe_status"],
        "ArticuloCategoria"  => $row["pe_cat"],
        "ArticuloSubcategoria" => $row["pe_scat"],
        "IntExt" => $row["intext"],
        "FechaPedido"  => $row["pe_fepe"],
        "CantidadPedida" => intval($row["pe_canpe"]),
        "FechaSurtido" => (is_null($row["pe_fecs"]) ? "" : $row["pe_fecs"]),
        "CantidadSurtida" => intval($row["pe_cansu"]),
        "DiferenciaSurtido" => intval($row["pe_canpe"] - $row["pe_cansu"]),
        "FacturaSerie" => $row["pe_serie"],
        "FacturaFolio" => $row["pe_nufact"],
        "FechaTerminacionArticulo" => $row["pe_fete"],
        "Medidas" => $row["medidas"] == "1" ? true : false,
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
        "cpx" => intval($row["pe_cpx"]),
        "FechaPedidoProduccion" => (is_null($row["pe_fepep"]) ? "" : $row["pe_fepep"]),
        "CantidadPedidoProduccion" => $CantidadPedidoProduccion,
        "CantidadProducida" => $CantidadProducida,
        "DiferenciaProducido" => $DiferenciaProducido,
        "FechaProduccionArticulo" => (is_null($row["pe_fecterm"]) ? "" : $row["pe_fecterm"]),
        "PzasCompra" => $PzasCompra,
        "GrmsCompra" => floatval(is_null($row["grmscompra"]) ? 0.00 : $row["grmscompra"]),
        "SumaPzasProdCompra" => $SumaPzasProdCompra,
        "OrdProd" => (is_null($row["ordprod"]) ? "" : $row["ordprod"]),
        "OrdProdLetra" => (is_null($row["ordprodletra"]) ? "" : $row["ordprodletra"]),
        "OrdProdSobre" => (is_null($row["ordprodsobre"]) ? "" : trim($row["ordprodsobre"])),
        "UbicacProd" => (is_null($row["ubicacprod"]) ? "" : trim($row["ubicacprod"])),
        "UbicacProdNomb" => (is_null($row["ubicacprodnomb"]) ? "" : trim($row["ubicacprodnomb"])),
        "UbicacExis" => (is_null($row["e_alm"]) ? "" : trim($row["e_alm"])),
        "UbicacExisNomb" => (is_null($row["ubicacexisnomb"]) ? "" : trim($row["ubicacexisnomb"])),
        "Ubicacion" => $ubicacion
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
