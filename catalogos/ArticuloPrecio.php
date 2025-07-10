<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * ArticuloPrecio.php
 * --------------------------------------------------------------------------
 * Recuperación de un artículo específico, incluyendo precio calculado.
 * Requiere que se pasen todos los parámetros necesarios para realizar el
 * cálculo:  lista de precios, paridad, datos relacionados con el artículo 
 * en sí mismo, etc.
 * Creación: 30.06.2025 | dRendon
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Funciones genericas de uso comun
require_once "../include/funciones.php";

# Constantes locales
const K_SCRIPTNAME  = "consultaprecios.php";

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
$ClienteCodigo  = null;     // Id del cliente - necesario para evitar suplantación por clientes abusivos
$ClienteFilial  = null;     // Filial del cliente
$ItemLinea      = null;     // Linea de producto del artículo
$ItemCode       = null;     // Código del artículo
$ListaPrecCode  = null;     // Codigo de lista de precios (cc_tipoli)
$ParidadTipo    = null;     // Tipo de paridad N=Normal | E=Especial | P=Premium
$PiezasCosto    = null;     // En alguna formulación se requieren las piezas y gramos del artículo para calcular el costo
$GramosCosto    = null;     // ver nota de fila anterior
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

# Variables usadas en el script
# Debes usar "global" en las funciones donde las vas a utilizar
$itemGramos     = 0.00;     // Peso promedio del artículo según componente de tipo "metal"
$precioVenta    = 0.00;     // Precio usado para la venta
$precioCosto    = 0.00;     // Costo directo aplicado al precio
$listPrecTipo   = "";       // 1=Directa 2=Por componente
$listPrecMoneda = "";       // Moneda asociada a la lista de precios
$tipoCosteo     = "";       // 1=Pieza 2=Gramo

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

  # **** dRendon 04.05.2023 ********************
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
  # **** Fin dRendon 04.05.2023 ****************

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

  if (!isset($_GET["ItemLinea"])) {
    throw new Exception("El parametro obligatorio 'ItemLinea' no fue definido.");
  } else {
    $ItemLinea = $_GET["ItemLinea"];
  }

  if (!isset($_GET["ItemCode"])) {
    throw new Exception("El parametro obligatorio 'ItemCode' no fue definido.");
  } else {
    $ItemCode = $_GET["ItemCode"];
  }

  if (!isset($_GET["ListaPrecCode"])) {
    throw new Exception("El parametro obligatorio 'ListaPrecCode' no fue definido.");
  } else {
    $ListaPrecCode = $_GET["ListaPrecCode"];
  }

  if (!isset($_GET["ParidadTipo"])) {
    throw new Exception("El parametro obligatorio 'ParidadTipo' no fue definido.");
  } else {
    $ParidadTipo = $_GET["ParidadTipo"];
    if (!in_array($ParidadTipo, ["N", "E", "P"])) {
      throw new Exception("Valor '" . $ParidadTipo . "' NO permitido para 'ParidadTipo'");
    }
  }


  # **** dRendon 04.05.2023 ********************
  # Cuando aplique, se debe impedir la consulta de códigos diferentes al del usuario autenticado
  # Verificando en este nivel ya no es necesario cambiar el código restante
  if ($TipoUsuario == "C") {
    if ((TRIM($ClienteCodigo) . "-" . TRIM($ClienteFilial)) != $Usuario) {
      throw new Exception("Error de autenticación");
    }
  }
  # **** Fin dRendon 04.05.2023 ****************

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
  "ItemLinea",
  "ItemCode",
  "ListaPrecCode",
  "ParidadTipo",
  "PiezasCosto",
  "GramosCosto",
  "Pagina"
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
if (isset($_GET["PiezasCosto"])) {
  $PiezasCosto = (int)$_GET["PiezasCosto"];
}

if (isset($_GET["GramosCosto"])) {
  $GramosCosto = (float)$_GET["GramosCosto"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Llama la rutina par ejecutar la consulta 
try {
  $data = SelectArticPrecio(
    $TipoUsuario,
    $Usuario,
    $ClienteCodigo,
    $ClienteFilial,
    $ItemLinea,
    $ItemCode,
    $ListaPrecCode,
    $ParidadTipo,
    $PiezasCosto,
    $GramosCosto,
    $Pagina
  );

  # Asigna código de respuesta HTTP 
  http_response_code(200);

  # Compone el objeto JSON que devuelve el endpoint
  //$numFilas = 1;  // count($data);
  //$totalPaginas = ceil($numFilas / K_FILASPORPAGINA);

  /*
  if ($numFilas > 0) {
    $codigo = K_API_OK;
    $mensaje = "success";
  } else {
    $codigo = K_API_NODATA;
    $mensaje = "data not found";
  }
  */

  if (!$data) {
    $codigo = K_API_NODATA;
    if ($listPrecTipo == '1') {
      $mensaje = "Código de Modelo no localizado en Lista de Precios Directa " . $ListaPrecCode;
    } else {
      $mensaje = "Código de Modelo no registrado";
    }
  } else {
    $codigo = K_API_OK;
    $mensaje = "success";
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

/***********************************************************
 * Envía Consulta a la base de datos y devuelve un array con
 * los resultados obtenidos.
 * @return array
 */
function SelectArticPrecio(
  $TipoUsuario,
  $Usuario,
  $ClienteCodigo,
  $ClienteFilial,
  $ItemLinea,
  $ItemCode,
  $ListaPrecCode,
  $ParidadTipo,
  $PiezasCosto,
  $GramosCosto,
  $Pagina
) {

  # variables globales
  global $itemGramos, $precioVenta, $precioCosto,
    $listPrecTipo, $listPrecMoneda, $tipoCosteo;

  $arrData   = array();   // Arreglo para almacenar los datos obtenidos
  $where     = "";        // Variable para almacenar dinamicamente la clausula WHERE del SELECT
  $continuar = true;      // Bandera para continuar con la ejecución del código
  $errormsg  = "";        // Mensaje de error en caso de fallo
  $insumosGpo2 = 0.00;    // variable local 
  $costoCompra = 0.00;    // variable local para el costo de compra artículo PT

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
  $strListaPrecCode = str_pad($ListaPrecCode, 2, "0", STR_PAD_LEFT);
  $strItemLinea     = str_pad($ItemLinea, 2, "0", STR_PAD_LEFT);

  # Construyo dinamicamente la condicion WHERE
  # Se asume que se indico un tipo de documento
  # De acuerdo al tipo de documento, se va a buscar en la tabla respectiva
  # update: debido a que se debe buscar en tablas diferentes, voy a escribir toda
  # la clausula SELECT según el tipo de documento
  # NOTA TECNICA:
  # Para relacionar la tabla de artículos utilizo una subconsulta en el JOIN
  # porque en proeli están repetidas varias claves de artículo, lo cual ocasiona
  # que la consulta devuelva filas repetidas
  $where = "";

  // if (in_array($TipoUsuario, ["A"])) {
  //   // Solo aplica filtro cuando el usuario es un agente
  //   $where .= "AND a.pe_age = :strUsuario ";
  // }

  # Construcción cláusula WHERE
  $where = "WHERE itm.c_lin = :ItemLinea AND itm.c_clave = :ItemCode ";

  try {

    # Se conecta a la base de datos
    //require_once "../db/conexion.php";  <-- el script se leyó previamente
    $conn = DB::getConn();

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    # Instrucción SELECT
    # OJO: De forma incorrecta, Proeli repite el código de artículo, por ello
    # voy a forzar que solo se devuelva un valor
    $sqlCmdArticulos = "SELECT DISTINCT ON (itm.c_lin,itm.c_clave)
      itm.c_lin,itm.c_clave,itm.c_descr,itm.c_status,itm.c_coscom,
      SUBSTR(lpt.t_param,11,2) ktje, SUBSTR(lpt.t_param,20,1) intext,
      SUBSTR(lpt.t_param,17,1) medidas, SUBSTR(lpt.t_param,16,1) formulac 
      FROM inv010 itm
      LEFT JOIN var020 lpt ON lpt.t_tica='05' AND lpt.t_gpo=itm.c_lin
      $where 
      ORDER BY itm.c_lin, itm.c_clave ";

    # Instrucción SELECT se definio anteriormente, en base al tipo de documento
    $oSQL = $conn->prepare($sqlCmdArticulos);

    $oSQL->bindParam(":ItemLinea", $strItemLinea, PDO::PARAM_STR);
    $oSQL->bindParam(":ItemCode", $ItemCode, PDO::PARAM_STR);

    # Voy a utilizar fetch() en vez de fetchall() porque requiero solo un registro
    # OJO: Se obtiene un array de una dimension solamente
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    $arrData = $oSQL->fetch(PDO::FETCH_ASSOC);

    if ($arrData === false) {
      return;
    }

    //var_dump($numRows, $arrData);
    if ($arrData["formulac"] == '2') {
      $tipoCosteo = '2';   // gramos
    } else {
      $tipoCosteo = '1';   // piezas
    }

    $costoCompra = $arrData["c_coscom"];

    # Busca la lista de precios para determinar si es Directa o PorComponente
    $sqlCmd = "SELECT t_clave,t_descr,SUBSTR(t_param,2,1) tipolista,
        SUBSTR(t_param,8,1) moneda
        FROM var020 
        WHERE t_tica='10' AND t_gpo='93' AND t_clave = :ListaPrecCode
        ORDER BY t_tica,t_gpo,t_clave ";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":ListaPrecCode", $strListaPrecCode, PDO::PARAM_STR);
    $oSQL->execute();
    $result = $oSQL->fetch(PDO::FETCH_ASSOC);

    if ($oSQL->rowCount() < 1) {
      throw new Exception("Lista de Precios $strListaPrecCode no registrada");
    }
    $listPrecTipo   = $result["tipolista"];
    $listPrecMoneda = $result["moneda"];

    /** 
     * Si el tipo de lista es "1 - Directa," el precio se obtiene de la lista indicada
     * y no se debe ejecutar otro proceso de cálculo.
     * Si la lista de precios es "2 - por componente", se llama la función para 
     * el cálculo de precio.
     */
    if ($listPrecTipo == '1') {
      $sqlCmd = "SELECT c_lista,c_lin,c_clave,c_venta,c_costo,c_sku
          FROM lispre 
          WHERE c_lista = :ListaPrecCode AND c_lin = :ItemLinea AND c_clave = :ItemCode
          LIMIT 1";

      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->bindParam(":ListaPrecCode", $strListaPrecCode, PDO::PARAM_STR);
      $oSQL->bindParam(":ItemLinea", $strItemLinea, PDO::PARAM_STR);
      $oSQL->bindParam(":ItemCode", $ItemCode, PDO::PARAM_STR);
      $oSQL->execute();
      $result = $oSQL->fetch(PDO::FETCH_ASSOC);

      if ($oSQL->rowCount() < 1) {
        //throw new Exception("Artículo NO registrado en Lista de Precios $strListaPrecCode");
        return;
      }

      # asigna datos tomados de la lista de ventas
      $precioVenta = $result["c_venta"];
      $precioCosto = $result["c_costo"];

      # Busca el componente de metal para asignar el peso promedio
      # Hay que aventarse todo el rollo que sigue debido a la pesima
      # normalización de la base de datos de Proeli
      $sqlCmd = "SELECT itm.c_lin,itm.c_clave,
      itm.c_co1,itm.c_ca1,itm.c_gr1,
      itm.c_co2,itm.c_ca2,itm.c_gr2,
      itm.c_co3,itm.c_ca3,itm.c_gr3,
      itm.c_co4,itm.c_ca4,itm.c_gr4,
      itm.c_co5,itm.c_ca5,itm.c_gr5,
      itm.c_co6,itm.c_ca6,itm.c_gr6,
      itm.c_co7,itm.c_ca7,itm.c_gr7,
      itm.c_co8,itm.c_ca8,itm.c_gr8,
      itm.c_co9,itm.c_ca9,itm.c_gr9,
      itm.c_co10,itm.c_ca10,itm.c_gr10,
      itm.c_co11,itm.c_ca11,itm.c_gr11,
      itm.c_co12,itm.c_ca12,itm.c_gr12,
      itm.c_co13,itm.c_ca13,itm.c_gr13,
      itm.c_co14,itm.c_ca14,itm.c_gr14,
      itm.c_co15,itm.c_ca15,itm.c_gr15
      FROM inv010 itm
      WHERE itm.c_lin = :ItemLinea AND itm.c_clave = :ItemCode ";

      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->bindParam(":ItemLinea", $strItemLinea, PDO::PARAM_STR);
      $oSQL->bindParam(":ItemCode", $ItemCode, PDO::PARAM_STR);
      $oSQL->execute();
      $itm = $oSQL->fetch(PDO::FETCH_ASSOC);

      for ($i = 1; $i <= 15; $i++) {
        if (empty($itm["c_co{$i}"])) {
          continue;
        }

        $codComp = str_pad($itm["c_co{$i}"], 4, " ", STR_PAD_LEFT);
        $sqlCmd = <<<SQLTXT
            SELECT com.co_clave,com.co_descr,
            com.co_grupo,com.co_facoro,com.co_facmaq,
            com.co_l1,com.co_l1d,com.co_l1e,com.co_l1de,com.co_l1p,com.co_l1dp,
            com.co_venta,com.co_ventad,com.co_ventae,com.co_ventade,com.co_ventap,com.co_ventadp
            FROM compon com
            WHERE com.co_clave = '{$codComp}' 
            SQLTXT;

        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->execute();
        $com = $oSQL->fetch(PDO::FETCH_ASSOC);

        // El "peso promedio" considera solo el peso del metal
        if ($com["co_facoro"] <> 0) {
          $itemGramos = (float)$itm["c_ca{$i}"];
          break;
        }
      }
      # --- termina búsqueda del peso promedio del artículo ---

      # se supone que "return" ocasiona que se ejecute el código
      # dentro del bloque "finally"
      return;
    }   // Termina Lista de Precios Directa

    # ----------- Continua cuando la lista de precios es por COMPONENTE -------------

    # Se obtiene el "valor agregado" de la lista de precios por componente
    $sqlCmd = "SELECT r_lista,r_linea,r_facimp FROM inv300
        WHERE r_lista = :ListaPrecCode AND r_linea= :ItemLinea ";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":ListaPrecCode", $strListaPrecCode, PDO::PARAM_STR);
    $oSQL->bindParam(":ItemLinea", $strItemLinea, PDO::PARAM_STR);
    $oSQL->execute();
    $result = $oSQL->fetch(PDO::FETCH_ASSOC);

    if ($oSQL->rowCount() < 1) {
      //throw new Exception("Línea NO registrada en Lista de Precios $strListaPrecCode");
      return;
    }
    $valorAgregado = $result["r_facimp"];

    # Obtiene los componentes de MP para el artículo de PT, necesario para calcular
    # el costo, el cual se usa como base para el precio de venta
    $sqlCmd = "SELECT itm.c_lin,itm.c_clave,
        itm.c_co1,itm.c_ca1,itm.c_gr1,
        itm.c_co2,itm.c_ca2,itm.c_gr2,
        itm.c_co3,itm.c_ca3,itm.c_gr3,
        itm.c_co4,itm.c_ca4,itm.c_gr4,
        itm.c_co5,itm.c_ca5,itm.c_gr5,
        itm.c_co6,itm.c_ca6,itm.c_gr6,
        itm.c_co7,itm.c_ca7,itm.c_gr7,
        itm.c_co8,itm.c_ca8,itm.c_gr8,
        itm.c_co9,itm.c_ca9,itm.c_gr9,
        itm.c_co10,itm.c_ca10,itm.c_gr10,
        itm.c_co11,itm.c_ca11,itm.c_gr11,
        itm.c_co12,itm.c_ca12,itm.c_gr12,
        itm.c_co13,itm.c_ca13,itm.c_gr13,
        itm.c_co14,itm.c_ca14,itm.c_gr14,
        itm.c_co15,itm.c_ca15,itm.c_gr15
        FROM inv010 itm
        WHERE itm.c_lin = :ItemLinea AND itm.c_clave = :ItemCode ";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":ItemLinea", $strItemLinea, PDO::PARAM_STR);
    $oSQL->bindParam(":ItemCode", $ItemCode, PDO::PARAM_STR);
    $oSQL->execute();
    $itm = $oSQL->fetch(PDO::FETCH_ASSOC);

    for ($i = 1; $i <= 15; $i++) {
      if (empty($itm["c_co{$i}"])) {
        continue;
      }

      $codComp = str_pad($itm["c_co{$i}"], 4, " ", STR_PAD_LEFT);
      $sqlCmd = <<<SQLTXT
          SELECT com.co_clave,com.co_descr,
          com.co_grupo,com.co_facoro,com.co_facmaq,
          com.co_l1,com.co_l1d,com.co_l1e,com.co_l1de,com.co_l1p,com.co_l1dp,
          com.co_venta,com.co_ventad,com.co_ventae,com.co_ventade,com.co_ventap,com.co_ventadp
          FROM compon com
          WHERE com.co_clave = '{$codComp}' 
          SQLTXT;

      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->execute();
      $com = $oSQL->fetch(PDO::FETCH_ASSOC);

      if ($com["co_grupo"] == "0") {
        continue;
      }

      // El "peso promedio" considera solo el peso del metal
      if ($com["co_facoro"] <> 0) {
        $itemGramos = (float)$itm["c_ca{$i}"];
      }

      // seleccionamos el campo correspondiente a la paridad indicada para el cliente
      switch ($ParidadTipo) {
        case "E":
          $col1 = "co_l1e";       // Paridad Especial MN
          $venta = "co_ventae";
          break;
        case "P":
          $col1 = "co_l1p";       // Paridad Premium MN
          $venta = "co_ventap";
          break;
        default:
          $col1 = "co_l1";        // Paridad Normal MN
          $venta = "co_venta";
          break;
      }

      // Suma el costo "estándar" de los componentes según la formulación indicada
      switch ($arrData["formulac"]) {
        // Formulación 1 y 2 <-- se van sumando los componentes de mp        
        case "1":
        case "2":
          if ($tipoCosteo == '1') {
            $precioVenta += $com[$col1] * $itm["c_ca{$i}"];
          } else {
            $precioVenta += $com[$col1] * $itm["c_gr{$i}"];
          }
          break;

        // Formulacion 4 <-- se utiliza el precio de venta de los consumibles
        // Esta formulación requiere las piezas y peso que se están capturando en el 
        // documento de venta, pero en las COTIZACIONES no se permite editar el peso
        // y llega en "cero2, por lo que se va a usar el peso promedio en ese caso.
        case "4":
          if ($GramosCosto == 0) {
            $GramosCosto = (float)($itemGramos * $PiezasCosto);
          }

          // Suma insumos Grupo 1 + valor agregado * precio costo
          if ($com["co_grupo"] == "1") {
            if ($tipoCosteo == "1") {
              if ($valorAgregado <> 0) {
                if ($PiezasCosto <> 0) {
                  $precioVenta = $precioVenta + (($com[$col1] + $valorAgregado) * round($GramosCosto / $PiezasCosto, 2));
                } else {
                  $precioVenta = $precioVenta + ($com[$col1] + $valorAgregado);
                }
              }
            } else {
              if ($valorAgregado <> 0) {
                if ($PiezasCosto <> 0) {
                  $precioVenta = $precioVenta + (($com[$col1] + $valorAgregado) * round($GramosCosto / $PiezasCosto, 2));
                } else {
                  $precioVenta = $precioVenta + ($com[$col1] + $valorAgregado);
                }
              }
            }
          }

          // Suma insumos Grupo 2 y 3 a precio de venta          
          if ($com["co_grupo"] == "2" || $com["co_grupo"] == "3") {
            if ($tipoCosteo == "1") {
              if ($com["co_grupo"] == "2") {
                $insumosGpo2 += ($com[$venta] * $itm["c_ca{$i}"]);
              } else {
                $insumosGpo2 += ($com[$col1] * $itm["c_ca{$i}"]);
              }
            } else {
              if ($com["co_grupo"] == "2") {
                $insumosGpo2 += ($com[$venta] * $itm["c_gr{$i}"]);
              } else {
                $insumosGpo2 += ($com[$col1] * $itm["c_gr{$i}"]);
              }
            }
          }
      } //switch ($arrData["formulac"])

      //var_dump($i, $com["co_clave"], $com["co_grupo"], $com["co_facoro"], $com["co_l1"], $com["co_l1d"], $com["co_venta"]);
    }

    // Aplica valor agregado según la formulación indicada y hace cálculos finales
    switch ($arrData["formulac"]) {
      // Formulación 1: La suma del resultado (piezas * costo) para los componentes 
      // del grupo 1-metal y 2-piedra se multiplica por el valor agregado
      case "1":
        if ($valorAgregado > 0) {
          $precioVenta = $precioVenta * (1 + $valorAgregado / 100);
        }
        $precioVenta = round($precioVenta);
        break;

      // Formulación 2: A la suma del resultado (piezas * costo) para los componentes
      // del grupo 1-metal y 2-piedra se le agrega el importe del valor agregado
      case "2":
        $precioVenta += $valorAgregado;
        $precioVenta = round($precioVenta, 2);
        break;

      // Formulación 4: Sumo el importe del "costo" de componentes mas el "precio de venta" de las piedras
      case "4":
        $precioVenta += $insumosGpo2;
        $precioVenta = round($precioVenta, 2);
        break;

      // Formulación 5: ---
      case "5":
        # Busca las paridades del DOLLAR ti_llave='3'
        $sqlCmdParidad = "SELECT ti_par,ti_pare,ti_partp,ti_parx 
          FROM inv100 WHERE ti_llave='3' ";
        $oSQLParidad = $conn->prepare($sqlCmdParidad);
        $oSQLParidad->execute();
        $filaParidad = $oSQLParidad->fetch(PDO::FETCH_ASSOC);
        if ($filaParidad === false) {
          return;
        }

        $ParidadNormal = (float)$filaParidad["ti_par"];
        $ParidadEspecial = (float)$filaParidad["ti_pare"];
        $ParidadPremium = (float)$filaParidad["ti_partp"];
        $ParidadCapturada = (float)$filaParidad["ti_parx"];

        if ($ParidadTipo == "N") {
          $precioVenta = round($costoCompra * $ParidadNormal * (1 + $valorAgregado / 100), 0);
        } elseif ($ParidadTipo == "E") {
          $precioVenta = round($costoCompra * $ParidadEspecial * (1 + $valorAgregado / 100), 0);
        } elseif ($ParidadTipo == "P") {
          $precioVenta = round($costoCompra * $ParidadCapturada, 0);
        } else {
          $precioVenta = 0.00;
        }
    }

    // --- Fin de los cálculos -------

  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
    //
  } finally {
    # se supone que siempre se ejecuta... a menos que se tenga "exit" en los bloques previos
    return $arrData;
  }

  # Falta tener en cuenta la paginacion

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
  # variables globales
  global $itemGramos, $precioVenta, $precioCosto,
    $listPrecTipo, $listPrecMoneda, $tipoCosteo;

  $contenido = array();

  if ($data) {

    # el kilataje va en blanco cuando el metal del artículo es Plata
    # los gramos se refieren al peso promedio según el componente de tipo metal

    // Se crea un array con los nodos requeridos
    $contenido = [
      "LineaPT"     => $data["c_lin"],
      "ItemCode"    => trim($data["c_clave"]),
      "Descripc"    => trim($data["c_descr"]),
      "Kilataje"    => $data["ktje"],
      "IntExt"      => $data["intext"],
      "Medidas"     => ($data["medidas"] == "1") ? "S" : "N",
      "Formulac"    => $data["formulac"],
      "TipoCosteo"  => $tipoCosteo,
      "ItemGramos"  => floatval($itemGramos),
      "PrecioVenta" => floatval($precioVenta),
      "PrecioCosto" => floatval($precioCosto),
      "Moneda"      => $listPrecMoneda,
      "LPrecDirComp" => $listPrecTipo
    ];
  } // count($data)>0

  return $contenido;
}
