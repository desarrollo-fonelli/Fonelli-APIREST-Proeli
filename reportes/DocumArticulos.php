<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Artículos incluidos en un documento de venta o inventario (facturas, 
 * remisiones, prefacturas, traspaso interoficinas, orden de retorno)
 * --------------------------------------------------------------------------
 * dRendon 20.05.2025
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
const K_SCRIPTNAME  = "DocumArticulos.php";

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
$DocTipo        = null;     // Tipo de documento (Factura, Remision, Prefactura, Traspaso, Orden de Retorno)
$DocSerie       = null;     // Serie del documento 
$DocFolio       = null;     // Folio del documento 
$DocFecha       = null;     // Fecha del documento
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

  if (!isset($_GET["DocTipo"])) {
    throw new Exception("El parametro obligatorio 'DocTipo' no fue definido.");
  } else {
    $DocTipo = $_GET["DocTipo"];
    if (! in_array($DocTipo, ['Todos', 'Factura', 'Remision', 'Prefactura', 'Traspaso', 'OrdenRetorno'])) {
      throw new Exception("El valor '" . $DocTipo . "' para 'DocTipo' no es válido");
    }
  }

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
      if (isset($_GET["DocSerie"]) && trim($_GET["DocSerie"]) != '') {
        $mensaje = "La 'Remision' no debe incluir 'Serie'";
        http_response_code(400);
        echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
        exit;
      }
      $DocSerie = '  ';
      if (isset($_GET["DocFolio"])) {
        $DocFolio = $_GET["DocFolio"];
      } else {
        $mensaje = "Debe indicar un numero de 'Remision'";
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
      if (isset($_GET["DocSerie"]) && trim($_GET["DocSerie"]) != '') {
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
      if (isset($_GET["DocSerie"]) && trim($_GET["DocSerie"]) != '') {
        $mensaje = "La 'OrdenRetorno' no debe incluir 'Serie'";
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

  if (!isset($_GET["DocFecha"])) {
    throw new Exception("El parametro obligatorio 'DocFecha' no fue definido.");
  } else {
    $DocFecha = $_GET["DocFecha"];
    if (!ValidaFormatoFecha($DocFecha)) {
      throw new Exception("El parametro 'DocFecha' no tiene el formato 'yyyy-mm-dd' o la fecha es incorrecta.");
    }
  }

  // if (!isset($_GET["DocSerie"])) {
  //   throw new Exception("El parametro obligatorio 'DocSerie' no fue definido.");
  // } else {
  //   $DocSerie = $_GET["DocSerie"];
  // }
  // if (!isset($_GET["DocFolio"])) {
  //   throw new Exception("El parametro obligatorio 'DocFolio' no fue definido.");
  // } else {
  //   $DocFolio = $_GET["DocFolio"];
  // }

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
  "DocTipo",
  "DocSerie",
  "DocFolio",
  "DocFecha"
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

# Llama la funcion que ejecuta la consulta 
try {
  $data = SelectArticulos(
    $TipoUsuario,
    $Usuario,
    $ClienteCodigo,
    $ClienteFilial,
    $DocTipo,
    $DocSerie,
    $DocFolio,
    $DocFecha
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
    "Contenido"   => $dataCompuesta
    //"Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina]
  ];
} catch (Exception $e) {
  $response = [
    "Codigo"      => K_API_ERRSQL,
    "Mensaje"     => $conn->get_last_error(),
    "Contenido"   => []
    //"Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina]
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
function SelectArticulos(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $DocTipo,
  $DocSerie,
  $DocFolio,
  $DocFecha
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

  $strClienteCodigo = str_pad($ClienteCodigo, 6, " ", STR_PAD_LEFT);
  $strClienteFilial = str_pad($ClienteFilial, 3, " ", STR_PAD_LEFT);
  $strDocFolio   = str_pad($DocFolio, 6, " ", STR_PAD_LEFT);

  # Se conecta a la base de datos
  //require_once "../db/conexion.php";  <-- el script se leyó previamente
  $conn = DB::getConn();

  # Construyo dinamicamente la condicion WHERE
  # Se asume que se indico un tipo de documento
  # De acuerdo al tipo de documento, se va a buscar en la tabla respectiva
  # update: debido a que se debe buscar en tablas diferentes, voy a escribir toda
  # la clausula SELECT según el tipo de documento
  # NOTA TECNICA:
  # Para relacionar la tabla de artículos utilizo una subconsulta en el JOIN
  # porque en proeli están repetidas varias claves de artículo, lo cual ocasiona
  # que la consulta devuelva filas repetidas
  $sqlCmdDoc = "";
  $where = "";

  switch ($DocTipo) {
    case 'Factura':
      $sqlCmdDoc = "SELECT 'Factura' doctipo,a.mo_serie docserie,a.mo_doc docfolio,
        a.mo_lin linpt,a.mo_clave itemcode,c.c_descr descripc,a.mo_mov codigomovinv,
        a.mo_fecdo fechamovinv,
        (a.mo_pzas*-1) piezas,(a.mo_can*-1) gramos, a.mo_letra letra,a.mo_ref ref 
      FROM inv040 a 
      LEFT JOIN  (SELECT DISTINCT ON (c_lin, c_clave) * FROM inv010) c 
             ON c.c_lin=a.mo_lin AND c.c_clave=a.mo_clave
      WHERE a.mo_serie = :DocSerie AND trim(a.mo_doc) = trim(:strDocFolio) AND a.mo_fecdo = :DocFecha 
      ORDER BY a.mo_rengl";
      break;

    case 'Remision':
      $sqlCmdDoc = "SELECT 'Remsion' doctipo,a.mo_serie docserie,a.mo_doc docfolio,
        a.mo_lin linpt,a.mo_clave itemcode,c.c_descr descripc,a.mo_mov codigomovinv,
        a.mo_fecdo fechamovinv,
        (a.mo_pzas*-1) piezas,(a.mo_can*-1) gramos, a.mo_letra letra,a.mo_ref ref 
      FROM inv040 a 
      LEFT JOIN  (SELECT DISTINCT ON (c_lin, c_clave) * FROM inv010) c 
             ON c.c_lin=a.mo_lin AND c.c_clave=a.mo_clave
      WHERE a.mo_serie = '  ' AND trim(a.mo_doc) = trim(:strDocFolio) AND a.mo_fecdo= :DocFecha 
      ORDER BY a.mo_rengl";
      break;

    case 'Prefactura':
      # En el caso de Liverpool las guias se asocian con el folio de traspaso de
      # inventario, no con un documento de venta,
      # por lo que se agrega una condición para que no aparezcan las dos partidas
      # del mismo, solo se consideran "salidas" de inventario (cantidades negativas).
      # AND a.mo_pzas <= 0 ";   <-- Esta condición no se aplica al usar tabla de prefacturas

      $sqlCmdDoc = "SELECT 'Prefactura' doctipo,a.pe_serie docserie, a.pe_nufact docfolio,
      a.pe_lin linpt,pe_clave itemcode,c.c_descr descripc,'' codigomovinv,
      '' fechamovinv,a.pe_canpe piezas,a.pe_grape gramos,a.pe_letra letra,a.pe_ped ref
      FROM pre100 a 
      LEFT JOIN (SELECT DISTINCT ON (c_lin, c_clave) * FROM inv010) c 
             ON c.c_lin=a.pe_lin AND c.c_clave=a.pe_clave 
      WHERE a.pe_serie = :DocSerie AND trim(a.pe_nufact) = trim(:strDocFolio) 
      ORDER BY pe_rengl";
      break;

    case 'Traspaso':
      $sqlCmdDoc = "SELECT 'Traspaso' doctipo,a.mo_serie docserie,a.mo_doc docfolio,
        a.mo_lin linpt,a.mo_clave itemcode,c.c_descr descripc,a.mo_mov codigomovinv,
        a.mo_fecdo fechamovinv,
        (a.mo_pzas*-1) piezas,(a.mo_can*-1) gramos, a.mo_letra letra,a.mo_ref ref 
      FROM inv040 a 
      LEFT JOIN  (SELECT DISTINCT ON (c_lin, c_clave) * FROM inv010) c 
             ON c.c_lin=a.mo_lin AND c.c_clave=a.mo_clave
      WHERE a.mo_serie = '  ' AND trim(a.mo_doc) = trim(:strDocFolio) AND a.mo_fecdo = :DocFecha 
          AND a.mo_mov = '13'
      ORDER BY a.mo_rengl";
      break;

    case 'OrdenRetorno':
      $sqlCmdDoc = "SELECT 'OrdenRetorno' doctipo,'  ' docserie,a.dor_folio docfolio,
        a.dor_lin linpt,a.dor_clave itemcode,c.c_descr descripc,'' codigomovinv,
        a.dor_fecha fechamovinv, 
        a.dor_pzas piezas,a.dor_grms gramos,a.dor_serie letra,a.dor_ref ref 
      FROM cli135 a 
      LEFT JOIN  (SELECT DISTINCT ON (c_lin, c_clave) * FROM inv010) c 
             ON c.c_lin=a.dor_lin AND c.c_clave=a.dor_clave
      WHERE trim(a.dor_folio) = trim(:strDocFolio) ";
      break;
  }


  // if (in_array($TipoUsuario, ["A"])) {
  //   // Solo aplica filtro cuando el usuario es un agente
  //   $where .= "AND a.pe_age = :strUsuario ";
  // }

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();


  try {

    # Instrucción SELECT se definio anteriormente, en base al tipo de documento

    //var_dump($sqlCmd);

    $oSQL = $conn->prepare($sqlCmdDoc);

    switch ($DocTipo) {
      case 'Factura':
        $oSQL->bindParam(":DocSerie", $DocSerie, PDO::PARAM_STR);
        $oSQL->bindParam(":strDocFolio", $strDocFolio, PDO::PARAM_STR);
        $oSQL->bindParam(":DocFecha", $DocFecha, PDO::PARAM_STR);
        break;
      case 'Remision':
        $oSQL->bindParam(":strDocFolio", $strDocFolio, PDO::PARAM_STR);
        $oSQL->bindParam(":DocFecha", $DocFecha, PDO::PARAM_STR);
        break;
      case 'Prefactura':
        $oSQL->bindParam(":DocSerie", $DocSerie, PDO::PARAM_STR);
        $oSQL->bindParam(":strDocFolio", $strDocFolio, PDO::PARAM_STR);
        break;
      case 'Traspaso':
        $oSQL->bindParam(":strDocFolio", $strDocFolio, PDO::PARAM_STR);
        $oSQL->bindParam(":DocFecha", $DocFecha, PDO::PARAM_STR);
        break;
      case 'OrdenRetorno':
        $oSQL->bindParam(":strDocFolio", $strDocFolio, PDO::PARAM_STR);
        break;
    }

    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetchAll(PDO::FETCH_ASSOC);

    //

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
  $articulos = array();
  $articuloRow = array();

  if (count($data) > 0) {

    foreach ($data as $row) {

      // Se crea un array con los nodos requeridos
      $articuloRow = [
        "LinPT"       => $row["linpt"],
        "ItemCode"    => trim($row["itemcode"]),
        "Descripc"    => trim($row["descripc"]),
        "Piezas"      => intval($row["piezas"]),
        "Gramos"      => floatval($row["gramos"]),
        "Letra"       => $row["letra"],
        "Ref"         => $row["ref"],
        "CodigoMovInv" => $row["codigomovinv"],
        "FechaMovInv" => $row["fechamovinv"] ? $row["fechamovinv"] : null
      ];

      // Se agrega el array a la seccion "contenido"
      array_push($articulos, $articuloRow);
    }   // foreach($data as $row)

    $contenido = [
      "DocTipo"  => $data[0]["doctipo"],
      "DocSerie" => $data[0]["docserie"],
      "DocFolio" => $data[0]["docfolio"],
      "DocModelos" => $articulos
    ];
  } // count($data)>0

  return $contenido;
}
