<?php

@session_start();

/**
 * Estos headers solo son efectivos si se indican en el backend,
 * no tiene caso indicarlos en los servicios de angular.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Auth');
header('Content-type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Auth');
  http_response_code(200);
  exit;
}

// Relevante al actualizar y mostrar información
date_default_timezone_set('America/Mexico_City');

/**
 * Lista de Propuestas de Venta (Cotizaciones)
 * --------------------------------------------------------------------------
 * dRendon 26.08.2025
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
const K_SCRIPTNAME  = "CotizListar.php";

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
$AgenteCodigo   = null;
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente inicial
$FechaDesde     = null;     // Fecha de registro del pedido
$FechaHasta     = null;     // Fecha de registro del pedido
$FolioDesde     = null;     // Folio Prepedido del cliente
$FolioHasta     = null;     // Folio Prepedido del cliente
$Status         = null;     // Status del cliente
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "GET") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos GET";   // quité K_SCRIPTNAME del mensaje
  echo json_encode(["Codigo" => K_API_FAILVERB, "Mensaje" => $mensaje]);
  exit;
}

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {
  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  } else {
    $Usuario = $_GET["Usuario"];
  }

  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME del mensaje
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if (! in_array($TipoUsuario, ["C", "A", "G"])) {
      throw new Exception("Valor '" . $TipoUsuario . "' NO permitido para 'TipoUsuario'");
    }

    if ($TipoUsuario == "A") {
      if (!isset($_GET["AgenteCodigo"]) || $_GET["AgenteCodigo"] == 0) {
        throw new Exception("Debe indicar un valor para 'AgenteCodigo' cuando 'TipoUsuario' es 'A'");
      }
      if (trim($_GET["AgenteCodigo"]) != $Usuario) {
        throw new Exception("Error de autenticacion Agente");
      }
    }
    if (isset($_GET["AgenteCodigo"]) && $_GET["AgenteCodigo"] != 0) {
      $AgenteCodigo = $_GET["AgenteCodigo"];
    }

    if ($TipoUsuario == 'C') {
      if (!isset($_GET["ClienteCodigo"]) || $_GET["ClienteCodigo"] == 0) {
        throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
        // 
      } else {
        $ClienteCodigo = $_GET["ClienteCodigo"];
      }

      if (!isset($_GET["ClienteFilial"])) {
        throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
      } else {
        $ClienteFilial = $_GET["ClienteFilial"];
      }
      # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
      # Verificando en este nivel ya no es necesario cambiar el código restante
      if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
        throw new Exception("Error de autenticación usuario - cliente" + $Usuario);
      }
    } else {
      if (isset($_GET["ClienteCodigo"]) && $_GET["ClienteCodigo"] != 0) {
        $ClienteCodigo = $_GET["ClienteCodigo"];
        if (isset($_GET["ClienteFilial"])) {
          $ClienteFilial = $_GET["ClienteFilial"];
        } else {
          $ClienteFilial = '0';
        }
      }
    }

    // Fin validaciones por tipo de usuario
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
  # Fin dRendon 04.05.2023 ****************

  if (!isset($_GET["FechaDesde"])) {
    throw new Exception("El parametro obligatorio 'FechaDesde' no fue definido.");
  } else {
    $FechaDesde = $_GET["FechaDesde"];
    if (!ValidaFormatoFecha($FechaDesde)) {
      throw new Exception("El parametro 'FechaDesde' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }

  if (!isset($_GET["FechaHasta"])) {
    throw new Exception("El parametro obligatorio 'FechaHasta' no fue definido.");
  } else {
    $FechaHasta = $_GET["FechaHasta"];
    if (!ValidaFormatoFecha($FechaHasta)) {
      throw new Exception("El parametro 'FechaHasta' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
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
  echo json_encode(["Codigo" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array(
  "TipoUsuario",
  "Usuario",
  "AgenteCodigo",
  "ClienteCodigo",
  "ClienteFilial",
  "FechaDesde",
  "FechaHasta",
  "FolioDesde",
  "FolioHasta",
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
  echo json_encode(["Codigo" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
  exit;
}

if (isset($_GET["Status"])) {
  $Status = $_GET["Status"];
  if (! in_array($Status, ["A", "I"])) {
    $mensaje = "Valor '" . $Status . "' NO permitido para 'Status'";
    http_response_code(400);
    echo json_encode(["Codigo" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Llama la función que Ejecuta la consulta 
try {
  $data = ListarCotizaciones(
    $TipoUsuario,
    $Usuario,
    $AgenteCodigo,
    $ClienteCodigo,
    $ClienteFilial,
    $FechaDesde,
    $FechaHasta,
    $FolioDesde,
    $FolioHasta,
    $Status,
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

  //var_dump($data);   exit();

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
function ListarCotizaciones(
  $TipoUsuario,
  $Usuario,
  $AgenteCodigo,
  $ClienteCodigo,
  $ClienteFilial,
  $FechaDesde,
  $FechaHasta,
  $FolioDesde,
  $FolioHasta,
  $Status,
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

  // $strOficinaDesde = str_pad($OficinaDesde, 2, "0", STR_PAD_LEFT);
  // $strOficinaHasta = str_pad($OficinaHasta, 2, "0", STR_PAD_LEFT);
  $strAgenteCodigo = str_pad($AgenteCodigo, 2, "0", STR_PAD_LEFT);
  $strClteCodigo  = str_replace(' ', '0', str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT) . str_pad($ClienteFilial, 3, " ", STR_PAD_LEFT));
  $strFolioDesde  = str_pad($FolioDesde, 6, " ", STR_PAD_LEFT);
  $strFolioHasta  = str_pad($FolioHasta, 6, " ", STR_PAD_LEFT);


  # Se conecta a la base de datos
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE doc.fecha_doc >= :FechaDesde AND doc.fecha_doc <= :FechaHasta 
  AND doc.folio >= :strFolioDesde AND doc.folio <= :strFolioHasta ";

  if (isset($_GET["AgenteCodigo"]) and $_GET["AgenteCodigo"] != 0) {
    $where .= "AND replace(cli.cc_age,' ','0') = :strAgenteCodigo ";
  }

  if (isset($_GET["ClienteCodigo"]) && $_GET["ClienteCodigo"] != 0) {
    $where .= "AND concat(replace(doc.cliente_codigo,' ','0'),replace(doc.cliente_filial,' ','0')) = :strClteCodigo ";
  }

  if (isset($Status)) {
    $where .= "AND doc.status_doc = :Status ";
  }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  # Instrucción SELECT
  $sqlCmd = "SELECT doc.id,doc.folio,doc.fecha_doc,doc.status_doc,
    doc.cliente_codigo,doc.cliente_filial,doc.cliente_nombre,doc.cliente_sucursal,
    cli.cc_age, doc.lprec_codigo, doc.parid_tipo, trim(doc.comentarios) comentarios,
    det.fila,det.lineapt,det.itemcode,det.descripc,det.precio,det.costo,
    det.piezas,det.gramos,det.tipo_costeo,det.importe,det.kilataje,det.int_ext,
    det.lprec_dircomp
  FROM cotiz_doc doc
  JOIN cotiz_fila det ON doc.id=det.doc_id
  LEFT JOIN cli010 cli ON doc.cliente_codigo=cli.cc_num AND doc.cliente_filial=cli.cc_fil
    $where ORDER BY doc.folio,det.fila";
  //exit($sqlCmd);

  try {
    $oSQL = $conn->prepare($sqlCmd);

    $oSQL->bindParam(":FechaDesde", $FechaDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":FechaHasta", $FechaHasta, PDO::PARAM_STR);
    $oSQL->bindParam(":strFolioDesde", $strFolioDesde, PDO::PARAM_STR);
    $oSQL->bindParam(":strFolioHasta", $strFolioHasta, PDO::PARAM_STR);

    if (isset($_GET["AgenteCodigo"]) and $_GET["AgenteCodigo"] != 0) {
      $oSQL->bindParam(":strAgenteCodigo", $strAgenteCodigo, PDO::PARAM_STR);
    }
    if (isset($_GET["ClienteCodigo"]) && $_GET["ClienteCodigo"] != 0) {
      $oSQL->bindParam(":strClteCodigo", $strClteCodigo, PDO::PARAM_STR);
    }

    if (isset($Status)) {
      $oSQL->bindParam(":Status", $Status, PDO::PARAM_STR);
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
  $contenido    = array();
  $docs_vta     = array();
  $doc_vta      = array();
  $doc_filas    = array();
  $doc_filas    = array();

  $numDocs      = 0;
  $sumaPzasDoc  = 0;
  $sumaGrmsDoc  = 0;
  $sumaImpDoc   = 0;

  if (count($data) > 0) {
    $DocId          = $data[0]["id"];
    $folio          = $data[0]["folio"];
    $fecha          = $data[0]["fecha_doc"];
    $status         = $data[0]["status_doc"];
    $clienteCodigo  = $data[0]["cliente_codigo"];
    $clienteFilial  = $data[0]["cliente_filial"];
    $clienteNombre  = $data[0]["cliente_nombre"];
    $clienteSucursal = $data[0]["cliente_sucursal"];
    $agenteCodigo   = $data[0]["cc_age"];
    $ListaPreciosCodigo = $data[0]["lprec_codigo"];
    $ParidadTipo = $data[0]["parid_tipo"];
    $Comentarios = $data[0]["comentarios"];


    foreach ($data as $row) {

      // Cambio de documento, asigna contenido
      if ($row["folio"] != $folio) {

        $numDocs  = $numDocs + 1;

        $doc_vta = [
          "DocId"         => intval($DocId),
          "Folio"         => intval($folio),
          "Fecha"         => $fecha,
          "Status"        => $status,
          "ClienteCodigo" => trim($clienteCodigo),
          "ClienteFilial" => trim($clienteFilial),
          "ClienteNombre" => trim($clienteNombre),
          "ClienteSucursal" => trim($clienteSucursal),
          "AgenteCodigo"  => trim($agenteCodigo),
          "PzasDoc"       => intval($sumaPzasDoc),
          "GrmsDoc"       => floatval($sumaGrmsDoc),
          "ImporteDoc"    => floatval($sumaImpDoc),
          "ListaPreciosCodigo" => $ListaPreciosCodigo,
          "ParidadTipo"   => $ParidadTipo,
          "Comentarios"   => trim($Comentarios),
          "FilasDoc"      => $doc_filas
        ];
        array_push($docs_vta, $doc_vta);

        // Prepara variables para siguiente iteración
        $DocId          = $row["id"];
        $folio          = $row["folio"];
        $fecha          = $row["fecha_doc"];
        $status         = $row["status_doc"];
        $clienteCodigo  = $row["cliente_codigo"];
        $clienteFilial  = $row["cliente_filial"];
        $clienteNombre  = $row["cliente_nombre"];
        $clienteSucursal = $row["cliente_sucursal"];
        $agenteCodigo   = $row["cc_age"];
        $ListaPreciosCodigo = $row["lprec_codigo"];
        $ParidadTipo    = $row["parid_tipo"];
        $Comentarios    = $row["comentarios"];
        $doc_filas      = array();
        $doc_fila       = array();

        $sumaPzasDoc    = 0;
        $sumaGrmsDoc    = 0;
        $sumaImpDoc     = 0;
      }

      // Se crea un array para cada fila del documento
      $doc_fila = [
        "Fila"      => intval($row["fila"]),
        "LineaPT"   => $row["lineapt"],
        "ItemCode"  => trim($row["itemcode"]),
        "Descripc"  => trim($row["descripc"]),
        "Precio"    => floatval($row["precio"]),
        "Costo"     => floatval($row["costo"]),
        "Piezas"    => intval($row["piezas"]),
        "Gramos"    => floatval($row["gramos"]),
        "TipoCosteo" => $row["tipo_costeo"],
        "Importe"   => floatval($row["importe"]),
        "Kilataje"  => trim($row["kilataje"]),
        "IntExt"    => $row["int_ext"],
        "LPrecDirComp" => $row["lprec_dircomp"]
      ];

      array_push($doc_filas, $doc_fila);

      $sumaPzasDoc = $sumaPzasDoc + $row["piezas"];
      $sumaGrmsDoc = ROUND($sumaGrmsDoc + $row["gramos"], 2);
      $sumaImpDoc  = ROUND($sumaImpDoc + $row["importe"], 2);
    }   // foreach($data as $row)

    // Último registro
    $numDocs  = $numDocs + 1;
    $doc_vta = [
      "DocId"         => intval($DocId),
      "Folio"         => intval($folio),
      "Fecha"         => $fecha,
      "Status"        => $status,
      "ClienteCodigo" => trim($clienteCodigo),
      "ClienteFilial" => trim($clienteFilial),
      "ClienteNombre" => trim($clienteNombre),
      "ClienteSucursal" => trim($clienteSucursal),
      "AgenteCodigo"  => trim($agenteCodigo),
      "PzasDoc"       => intval($sumaPzasDoc),
      "GrmsDoc"       => floatval($sumaGrmsDoc),
      "ImporteDoc"    => floatval($sumaImpDoc),
      "ListaPreciosCodigo" => $ListaPreciosCodigo,
      "ParidadTipo"   => $ParidadTipo,
      "Comentarios"   => trim($Comentarios),
      "FilasDoc"      => $doc_filas
    ];
    array_push($docs_vta, $doc_vta);


    // Contenido que se va a devolver: el array de documentos con las filas anidadas
    $contenido = [
      "CotizDocumentos" => $docs_vta,
    ];
  } // count($data)>0

  return $contenido;
}
