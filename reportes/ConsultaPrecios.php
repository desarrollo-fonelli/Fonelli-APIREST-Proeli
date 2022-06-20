<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Consulta de Precios
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Rutinas para cálculo de precio heredadas de Fonelli
//require_once "../include/preciosrutinas.php";     <----- esta en funcion SELECT... cuestiones de ambito de variables

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
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$Lista          = null;     // Número de Lista de Precios utilizada 1 | 2
$ParidadTipo    = null;     // Tipo de paridad N=Normal | E=Especial
$ArticuloLinea  = null;     // Linea de producto del artículo
$ArticuloCodigo = null;     // Código del artículo
$Pagina         = 1;        // Pagina devuelta del conjunto de datos obtenido

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "GET") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos GET";   // quité K_SCRIPTNAME del mensaje
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje ]);
  exit;
}

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {
  if (!isset($_GET["TipoUsuario"])) {
    throw new Exception("El parametro obligatorio 'TipoUsuario' no fue definido.");   // quité K_SCRIPTNAME del mensaje
  } else {
    $TipoUsuario = $_GET["TipoUsuario"];
    if(! in_array($TipoUsuario, ["C","A","G"])){
      throw new Exception("Valor '". $TipoUsuario ."' NO permitido para 'TipoUsuario'");
    }
    if($TipoUsuario = 'C'){
      if (!isset($_GET["ClienteCodigo"])) {
        throw new Exception("El parametro obligatorio 'ClienteCodigo' no fue definido.");
      } else {
        $ClienteCodigo = $_GET["ClienteCodigo"];
      }    
      if (!isset($_GET["ClienteFilial"])) {
        throw new Exception("El parametro obligatorio 'ClienteFilial' no fue definido.");
      } else {
        $ClienteFilial = $_GET["ClienteFilial"] ;
      }    
    }
  }
  
  if(!isset($_GET["Lista"])){
    throw new Exception("El parametro obligatorio 'Lista' no fue definido.");    
  } else {
    $Lista = $_GET["Lista"];
    if(! in_array($Lista, [1,2])){
      throw new Exception("Valor '". $Lista ."' NO permitido para 'Lista'");
    }
  }

  if(!isset($_GET["ParidadTipo"])){
    throw new Exception("El parametro obligatorio 'ParidadTipo' no fue definido.");    
  } else {
    $ParidadTipo = $_GET["ParidadTipo"];
    if(! in_array($ParidadTipo, ["N","E"])){
      throw new Exception("Valor '". $ParidadTipo ."' NO permitido para 'ParidadTipo'");
    }
  }

  if(!isset($_GET["ArticuloLinea"])){
    throw new Exception("El parametro obligatorio 'ArticuloLinea' no fue definido.");    
  } else {
    $ArticuloLinea = $_GET["ArticuloLinea"];
  }

  if(!isset($_GET["ArticuloCodigo"])){
    throw new Exception("El parametro obligatorio 'ArticuloCodigo' no fue definido.");    
  } else {
    $ArticuloCodigo = $_GET["ArticuloCodigo"];
  }

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("TipoUsuario", "Usuario", "ClienteCodigo", "ClienteFilial", 
"Lista", "ParidadTipo", "ArticuloLinea", "ArticuloCodigo", "Pagina");

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_GET);
foreach($arrParam as $param){
  if(! in_array($param, $arrPermitidos)){
    if(strlen($mensaje) > 1){
      $mensaje .= ", ";
    }
    $mensaje .= $param;
  }  
}
if(strlen($mensaje) > 0){
  $mensaje = "Parametros no reconocidos: ". $mensaje;   // quité K_SCRIPTNAME del mensaje
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
  if(in_array($TipoUsuario, ["A", "G"])){
    $mensaje = "Debe indicar 'Usuario' cuando 'TipoUsuario' es 'A' o 'G'";    // quité K_SCRIPTNAME del mensaje
    http_response_code(400);  
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;  
  }
}

if(isset($_GET["ClienteCodigo"])) {
  $ClienteCodigo = $_GET["ClienteCodigo"];
}

if(isset($_GET["ClienteFilial"])) {
  $ClienteFilial = $_GET["ClienteFilial"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectPrecio($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $Lista, 
  $ParidadTipo, $ArticuloLinea, $ArticuloCodigo);

  # Asigna código de respuesta HTTP 
  http_response_code(200);

  # Compone el objeto JSON que devuelve el endpoint
  $numFilas = count($data);
  $totalPaginas = ceil($numFilas/K_FILASPORPAGINA);

  if($numFilas > 0){
    $codigo = K_API_OK;
    $mensaje = "success";
  } else {
    $codigo = K_API_NODATA;
    $mensaje = "data not found";
  }

  $dataCompuesta = CreaDataCompuesta( $data );

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
 * @param string $TipoUsuario
 * @param int $Usuario
 * @param int $ClienteCodigo
 * @param int $ClienteFilial
 * @param string $Lista
 * @param string $ParidadTipo
 * @param string $ArticuloLinea
 * @param string $ArticuloCodigo
 * @return array
 */
FUNCTION SelectPrecio($TipoUsuario, $Usuario, $ClienteCodigo, $ClienteFilial, $Lista,
$ParidadTipo, $ArticuloLinea, $ArticuloCodigo)
{
  // Se requieren en rutinas heredadas de Proeli
  global $NormalEquivalente,$TipoParidad;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;
 

  $where = "";    // Variable para almacenar dinamicamente la clausula WHERE del SELECT

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  switch($TipoUsuario){
    // Cliente 
    /*
    case "C":     <-- cuando el tipo es "Cliente", no se requiere "Usuario"
      $strUsuario = str_pad($Usuario, 6," ",STR_PAD_LEFT);
      break;
      */

    // Agente
    case "A":
      $strUsuario = str_pad($Usuario, 2," ",STR_PAD_LEFT);
      break;
    // Gerente
    case "G":
      $strUsuario = str_pad($Usuario, 2," ",STR_PAD_LEFT);
      break;      
  }
  
  /**                         ****************************************************
   * Falta considerar el caso en que NO se pasan código ni filial del cliente
   */

  $strClienteCodigo = str_pad($ClienteCodigo, 6," ",STR_PAD_LEFT);
  $strClienteFilial = str_pad($ClienteFilial, 3," ",STR_PAD_LEFT);
  $strArticuloCodigo = str_pad($ArticuloCodigo, 11, " ", STR_PAD_RIGHT);

  # Construyo dinamicamente la condicion WHERE
  $where = "WHERE a.pe_num = :strClienteCodigo AND a.pe_fil = :strClienteFilial ";
  
  if(in_array($TipoUsuario, ["A"])){
    // Solo aplica filtro cuando el usuario es un agente
    $where .= "AND a.pe_age = :strUsuario ";
  }

  //var_dump($sqlCmd);

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  try {

    # Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    # Obtiene valores generales. La tabla var010 solo tiene un registro
    $sqlCmd = "SELECT * FROM var010";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    
    if($numRows<1){
      http_response_code(400);
      throw new Exception("Error en Tabla 'var010'");
      exit;
    }
    $rowVar010 = $oSQL->fetch(PDO::FETCH_ASSOC);
    $decimales = $rowVar010["e_dcxc"];
    
    # Obtiene lista de precios asignada al cliente y otros datos
    $sqlCmd = "SELECT trim(a.cc_num) cc_num,trim(a.cc_fil) cc_fil,trim(a.cc_raso) cc_raso,
    a.cc_tipoli,a.cc_tipoli2,a.cc_pincre,a.cc_cata,a.cc_ticte,a.cc_tparid,cc_timon,
    trim(g.t_descr) lista1_descr,trim(h.t_descr) lista2_descr,trim(f.t_descr) tipoclte_descr
    FROM cli010 a 
    LEFT JOIN var020 f ON (f.t_tica = '91' AND f.t_gpo = cc_ticte)
    LEFT JOIN var020 g ON (g.t_tica = '10' AND g.t_gpo = '93' AND g.t_clave = cc_tipoli)
    LEFT JOIN var020 h ON (h.t_tica = '10' AND h.t_gpo = '93' AND h.t_clave = cc_tipoli2)
    WHERE a.cc_num = :strClienteCodigo AND a.cc_fil = :strClienteFilial";

    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":strClienteCodigo", $strClienteCodigo, PDO::PARAM_STR);
    $oSQL-> bindParam(":strClienteFilial", $strClienteFilial, PDO::PARAM_STR);

    $oSQL-> execute();
    $numRows = $oSQL->rowCount();    
    if($numRows<1){
      http_response_code(400);
      throw new Exception("Cliente no registrado: ". $ClienteCodigo. '-'. $ClienteFilial);
      exit;
    }
    $rowCli010 = $oSQL->fetch(PDO::FETCH_ASSOC);
    
    // Lista de precios utilizada
    if($Lista == "1"){
      $ListaCodigo = $rowCli010["cc_tipoli"];
      $ListaDescr  = $rowCli010["lista1_descr"];
    } else {
      $ListaCodigo = $rowCli010["cc_tipoli2"];
      $ListaDescr  = $rowCli010["lista2_descr"];
    }    

    $FactorIncremento  = $rowCli010["cc_pincre"];   // Incremento DAISA
    $NormalEquivalente = $rowCli010["cc_cata"];     // Codigo de articulo Normal | Equivalente
    $TipoParidad = $rowCli010["cc_tparid"];         // Paridad Normal | Especial
    $TipoCliente = $rowCli010["cc_ticte"];          // GR GF PF MF ...
    $TipoMoneda  = $rowCli010["cc_timon"];          // Tipo de moneda

    if($TipoParidad == "N"){
      $TipoParidadDescr = "NORMAL";
    } elseif($TipoParidad == "E") {
      $TipoParidadDescr = "ESPECIAL";
    } else {
      $TipoParidadDescr = "";
    }

    // Obtiene datos de la lista de precios
    $sqlCmd = "SELECT * FROM var020 WHERE t_tica= '10' AND t_gpo='93' AND t_clave= :ListaCodigo";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":ListaCodigo", $ListaCodigo, PDO::PARAM_STR);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    if($numRows < 1){
      http_response_code(400);
      throw new Exception("Lista de Precios no registrada: ". $ListaCodigo);
      exit;
    }
    $rowListaPrec = $oSQL-> fetch(PDO::FETCH_ASSOC);

    #OJO: El primer caracter en la cadena se enumero como CERO,
    #     en ProEli se enumera con UNO
    $W_NULIS   = substr($rowListaPrec["t_param"], 1, 1);   // 1=Lista Directa | 2=Lista por Componente
    $W_TIPOLIR = substr($rowListaPrec["t_param"], 5, 2);   // Codigo Lista de Referencia
    $W_TIMON   = substr($rowListaPrec["t_param"], 7, 1);   // Codigo de Moneda 1=MN | 2=ORO | 3=USD | ... | 7=PLATA
   
    // Obtiene datos asociados a la moneda
    $sqlCmd = "SELECT * FROM inv100 WHERE ti_llave = :timon";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":timon", $W_TIMON, PDO::PARAM_STR);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    if ($numRows < 1) {
      http_response_code(400);
      throw new Exception("Tipo de Moneda no registrada: ". $W_TIMON);
      exit;
    }
    $rowMoneda = $oSQL->fetch(PDO::FETCH_ASSOC);
    $paridadvalor = $rowMoneda["ti_par"];
    $redondear = $rowMoneda["ti_redon"] == 1 ? true : false ;

    // Datos asociados a la Línea de Producto
    $sqlCmd = "SELECT * FROM var020 WHERE t_tica = '05' AND t_gpo = :linea ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":linea", $ArticuloLinea, PDO::PARAM_STR);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    if ($numRows < 1) {
      http_response_code(400);
      throw new Exception("Linea de Producto no registrada: ". $ArticuloLinea);
      exit;
    }
    $rowLinea = $oSQL->fetch(PDO::FETCH_ASSOC);
    
    // ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
    // MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...
    $RedonFinal  = substr($rowLinea["t_param"],  1, 1);    // equivale a $W_RED  de Proeli
    $Formulacion = substr($rowLinea["t_param"], 15, 1);   // equivale a $W_TIPOF de Proeli
    $LineaDescripc = trim($rowLinea["t_descr"]);
    $W_PARAML = $rowLinea["t_param"];

    $W_FACSER = 0; 
    if ($Formulacion == "4") {
      $W_FACSER = 1;
    }

    $TipoCosteo = 1;            // 1=Piezas | 2=Gramos
    $W_FACTOR   = 0;

    if ($W_NULIS == "1") {
      $TipoCosteo = 1;

    } else {

      // Listas de Precio por Lineas
      $sqlCmd = "SELECT * FROM inv300 WHERE r_lista= :listaref AND r_linea= :linea";
      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->bindParam(":listaref", $W_TIPOLIR    , PDO::PARAM_STR);
      $oSQL->bindParam(":linea"   , $ArticuloLinea, PDO::PARAM_STR);
      $oSQL->execute();
      $numRows = $oSQL->rowCount();
      if ($numRows < 1) {
        http_response_code(400);
        throw new Exception("Lista de Precios por Linea no registrada: ". $W_TIPOLIR);
        exit;
      }
      $rowListaPrecLinea = $oSQL->fetch(PDO::FETCH_ASSOC);
      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($Formulacion == "1" || $Formulacion == "3" || $Formulacion == "4") {
        $TipoCosteo = 1;
      } else {
        $TipoCosteo = 2;
      }
    }
    
    // Datos asociados al articulo
    $sqlCmd = "SELECT * FROM inv010 WHERE c_lin= :linea AND c_clave= :clave";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":linea", $ArticuloLinea , PDO::PARAM_STR);
    $oSQL->bindParam(":clave", $ArticuloCodigo, PDO::PARAM_STR);
    $oSQL->execute();
    $numRows = $oSQL->rowCount();
    if ($numRows < 1) {
      http_response_code(400);
      throw new Exception("Articulo no registrado: ". $ArticuloLinea.'-'.$ArticuloCodigo);
      exit;
    }
    $rowArticulos = $oSQL->fetch(PDO::FETCH_ASSOC);
    $W_CODE = trim($rowArticulos["c_clavee"]);        // Codigo equivalente
    $W_CODBARH = trim($rowArticulos["c_codbarh"]);    // Codigo de barras
    $ArticuloDescr = trim($rowArticulos["c_descr"]);

    // Estas variables se utilizan en el codigo heredado de proeli (../include/preciosrutinas.php)
    $C_CO1 = $rowArticulos["c_co1"];
    $C_CO2 = $rowArticulos["c_co2"];
    $C_CO3 = $rowArticulos["c_co3"];
    $C_CO4 = $rowArticulos["c_co4"];
    $C_CO5 = $rowArticulos["c_co5"];
    $C_CO6 = $rowArticulos["c_co6"];
    $C_CO7 = $rowArticulos["c_co7"];
    $C_CO8 = $rowArticulos["c_co8"];
    $C_CO9 = $rowArticulos["c_co9"];
    
    $C_LCO1 = $rowArticulos["c_lco1"];
    $C_LCO2 = $rowArticulos["c_lco2"];
    $C_LCO3 = $rowArticulos["c_lco3"];
    $C_LCO4 = $rowArticulos["c_lco4"];
    $C_LCO5 = $rowArticulos["c_lco5"];
    $C_LCO6 = $rowArticulos["c_lco6"];
    $C_LCO7 = $rowArticulos["c_lco7"];
    $C_LCO8 = $rowArticulos["c_lco8"];
    $C_LCO9 = $rowArticulos["c_lco9"];
    
    $C_CA1 = $rowArticulos["c_ca1"];
    $C_CA2 = $rowArticulos["c_ca2"];
    $C_CA3 = $rowArticulos["c_ca3"];
    $C_CA4 = $rowArticulos["c_ca4"];
    $C_CA5 = $rowArticulos["c_ca5"];
    $C_CA6 = $rowArticulos["c_ca6"];
    $C_CA7 = $rowArticulos["c_ca7"];
    $C_CA8 = $rowArticulos["c_ca8"];
    $C_CA9 = $rowArticulos["c_ca9"];
    
    $C_GR1 = $rowArticulos["c_gr1"];
    $C_GR2 = $rowArticulos["c_gr2"];
    $C_GR3 = $rowArticulos["c_gr3"];
    $C_GR4 = $rowArticulos["c_gr4"];
    $C_GR5 = $rowArticulos["c_gr5"];
    $C_GR6 = $rowArticulos["c_gr6"];
    $C_GR7 = $rowArticulos["c_gr7"];
    $C_GR8 = $rowArticulos["c_gr8"];
    $C_GR9 = $rowArticulos["c_gr9"];
    
    $C_CA1E = $rowArticulos["c_ca1e"];
    $C_CA2E = $rowArticulos["c_ca2e"];
    $C_CA3E = $rowArticulos["c_ca3e"];
    $C_CA4E = $rowArticulos["c_ca4e"];
    $C_CA5E = $rowArticulos["c_ca5e"];
    $C_CA6E = $rowArticulos["c_ca6e"];
    $C_CA7E = $rowArticulos["c_ca7e"];
    $C_CA8E = $rowArticulos["c_ca8e"];
    $C_CA9E = $rowArticulos["c_ca9e"];
    
    $C_GR1E = $rowArticulos["c_gr1e"];
    $C_GR2E = $rowArticulos["c_gr2e"];
    $C_GR3E = $rowArticulos["c_gr3e"];
    $C_GR4E = $rowArticulos["c_gr4e"];
    $C_GR5E = $rowArticulos["c_gr5e"];
    $C_GR6E = $rowArticulos["c_gr6e"];
    $C_GR7E = $rowArticulos["c_gr7e"];
    $C_GR8E = $rowArticulos["c_gr8e"];
    $C_GR9E = $rowArticulos["c_gr9e"];
    

    #Variables utilizadas en los calculos
    $W_COSTO = 0;
    $W_VENTA = 0;
    $TPG     = 0;
    $TPRE    = 0;
    $TPGE    = 0;
    $TPREE   = 0;
    
    #Estas variables se utilizan en listas directas
    $W_PRE   = 0;   // Precio normal
    $W_PREE  = 0;   // Precio equivalente

    $Precio = 0;
    $PrecioEquivalente = 0;
    $ValorAgregado = 0;

    # Rutinas para cálculo de precio heredadas de Fonelli
    require_once "../include/preciosrutinas.php";

    #-------------------------------------------------------------------------
    #                           PARIDAD NORMAL
    #-------------------------------------------------------------------------
    if ($TipoParidad == "N"){
      #-----------------------------------
      #LISTA DIRECTA $W_NULIS == 1
      #-----------------------------------
      if ($W_NULIS == "1") {
        $sqlCmd = "SELECT * FROM lispre
        WHERE c_lista = :listacodigo AND c_lin = :linea AND c_clave = :codigo";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL -> bindParam(":listacodigo",$ListaCodigo,PDO::PARAM_STR);
        $oSQL -> bindParam(":linea" ,$ArticuloLinea ,PDO::PARAM_STR);
        $oSQL -> bindParam(":codigo",$ArticuloCodigo,PDO::PARAM_STR);
        $oSQL -> execute();
        $numRows = $oSQL->rowCount();
        if ($numRows < 1){
          http_response_code(400);
          throw new Exception("Articulo ". $ArticuloLinea. "-". $ArticuloCodigo. " no registrado en Lista Directa...");
          exit;
        }
        $rowLPDirecta = $oSQL->fetch(PDO::FETCH_ASSOC);
        $W_PRE = $rowLPDirecta["c_venta"];
        $Precio = $W_PRE;
        if($NormalEquivalente == "E" AND trim($W_CODE) <> ""){
          $W_PREE = $rowLPDirecta["c_ventae"];
          $PrecioEquivalente = $W_PREE;
        }
      }

      #-----------------------------------
      #LISTA POR COMPONENTE $W_NULIS == 2
      #-----------------------------------
      if ($W_NULIS == "2") {
        #MONEDA NACIONAL           <----------------- Hay que decidir si se usa $W_TIMON o $TipoMoneda
        if ($W_TIMON == "1") {

          switch ($Formulacion){

          #COSTEO PIEZA ( > Insumos Grupo 1 e Insumos Grupo 2 e Insumos Grupo 3) * Factor
          #------ HAYQ QUE REVISAR ESTE CASE, NO ES IGUAL A LOS DEMAS -------#
          case "1": 
            $W_COSTO=0;   // dRendon 23/jul/2019
            $W_VENTA=0;   // dRendon 23/jul/2019
          
            #Suma todos los insumos Grupo 1, Grupo 2 y Grupo 3
            $TPRE = 0;
            $TPG  = 0;
            $TPREE = 0;
            $TPGE = 0;
            #preciosrutinas.php
            CalcNormalNulis2MNtipoF1();

            // $W_FACTOR = $rowAGR["R_FACIMP"];   <----- se obtuvo anteriormente
            #MULTIPLICA POR FACTOR
            if ($W_FACTOR <> 0){
              $TPRE = $TPRE * (1+$W_FACTOR/100);
              $TPG  = $TPG  * (1+$W_FACTOR/100);
              $W_NOAUMENTO = 1;
            }
            if ($NormalEquivalente  == "E" AND trim($W_CODE) <> "" ) {
              $W_COSTOE=0;
              $W_VENTAE=0;
              $TPGE=0;
              $TPREE=0;
              #Suma Todos los Insumos Grupo, Grupo 2 y Grupo 3
              $TPRE = 0;
              $TPGE = 0;
              #preciosrutnas.php
              CalcNormalNulis2MNtipoF1Equivalente();

              // $W_FACTOR = $rowAGR["R_FACIMP"];    <---- se obtuvo anteriormente
              #MULTIPLICA POR FACTOR
              if ($W_FACTOR <> 0){
                $TPREE = $TPREE * (1+$W_FACTOR/100);
                $TPGE  = $TPGE  * (1+$W_FACTOR/100);
                $W_NOAUMENTO = 1;
              }
            }
            $ValorAgregado = $W_FACTOR;
            $Precio = $TPRE;
            $PrecioEquivalente = $TPREE;
            break;

          #COSTEO GRAMO ( > Insumos Grupo 1 ) + Valor Agregado
          case "2":
            $TPRE  = 0;
            $TPG   = 0;
            $TPREE = 0;
            $TPGE  = 0;

            #Suma todos los insumos Grupo 1
            #preciosrutinas.php
            sumaInsumosGpo1();

            // $W_FACTOR = $rowAGR["R_FACIMP"];    <---- se obtuvo anteriormente
            #Suma valor agregado
            if ($W_FACTOR <> 0) {
              $TPRE = $TPRE + $W_FACTOR;
              $TPG  = $TPG  + $W_FACTOR;
              $W_NOAUMENTO = 1;
            }
            if($NormalEquivalente =="E" && trim($W_CODE)<>""){
              #preciosrutinas.php
              #sumaInsumosGpo1();     <-- la suma se hizo en la primera pasada

              // $W_FACTOR = $rowAGR["R_FACIMP"];    <---- se obtuvo anteriormente
              #Suma Valor Agregado
              if ($W_FACTOR <> 0) {
                $TPREE = $TPREE + $W_FACTOR;
                $TPGE  = $TPGE  + $W_FACTOR;
                $W_NOAUMENTO = 1;
              }
            }
            $ValorAgregado = $W_FACTOR;
            $Precio = $TPG;
            $PrecioEquivalente = $TPGE;
            break;    


          #COSTEO GRAMO ( > Insumos Grupo 1 + Insumos Grupo 2 a Precio Venta + Insumos Grupo 3) + Valor Agregado
          case "3":                

            /* 
             * OJO: Esta formulación no se está utilizando a jun/2022
             */

            $TPRE  = 0;
            $TPG   = 0;
            $TPREE = 0;
            $TPGE  = 0;

            # Insumos Grupo 1 + Insumos Grupo 2 a Precio Venta + Insumos Grupo 3
            #preciosrutinas.php
            sumaInsumosGpo1Gpo2PrecVtaGpo3();

            #Suma valor agregado
            // $W_FACTOR = $rowAGR["R_FACIMP"];      <---- se obtuvo anteriormente
            if ($W_FACTOR <> 0) {
              $TPRE = $TPRE + $W_FACTOR;
              $TPG  = $TPG  + $W_FACTOR;
              $W_NOAUMENTO = 1;
            }
            if($NormalEquivalente =="E" && trim($W_CODE)<>""){
              #preciosrutinas.php
              #sumaInsumosGpo1();     <-- la suma se hizo en la primera pasada

              #Suma Valor Agregado
              // $W_FACTOR = $rowAGR["R_FACIMP"];      <---- se obtuvo anteriormente
              if ($W_FACTOR <> 0) {
                $TPREE = $TPREE + $W_FACTOR;
                $TPGE  = $TPGE  + $W_FACTOR;
                $W_NOAUMENTO = 1;
              }
            }

            break;    

          #COSTEO GRAMO   > (Insumos Grupo 1  + Valor Agregado) +  > Insumos Grupo 2 a Precio de Venta e Insumos Grupo 3
          case "4":

            #Calcula peso piedra para restarlo al peso del articulo
            #------------------------------------------------------
            $W_TCANIM = 0;                
            calcPesoPiedra();       #preciosrutinas.php

            #Suma todos los insumos Grupo1 + Valor Agregado, PARIDAD NORMAL
            sumaInsumosGpo1MasValorAgregadoNormal();
                    
            #Suma Todos los Insumos Grupo 2 a Precio de Venta e Grupo 3
            #(segun ATNCT020.prg)
            sumaInsumosGpo2ConPrecioVentayGrupo3Normal();                

            break;                
          }
        } else {
          #MONEDA USD                         <------------------
          switch ($Formulacion){
            case "1":
              #COSTEO PIEZA ( > Insumos Grupo 1 e Insumos Grupo 2 e Insumos Grupo 3) * Factor
              break;
            case "2":
              #COSTEO GRAMO ( > Insumos Grupo 1 ) + Valor Agregado
              break;
            case "3":
              #COSTEO GRAMO ( > Insumos Grupo 1 + Insumos Grupo 2 a Precio Venta + Insumos Grupo 3) + Valor Agregado
              break;
            case "4":
              #COSTEO GRAMO   > (Insumos Grupo 1  + Valor Agregado) +  > Insumos Grupo 2 a Precio de Venta e Insumos Grupo 3
              break;
          }
        }
      }

    }

    #-------------------------------------------------------------------------
    #                           PARIDAD ESPECIAL
    #-------------------------------------------------------------------------
    if ($TipoParidad == "E"){
      #-----------------------------------
      #LISTA DIRECTA $W_NULIS == 1
      #-----------------------------------
      if ($W_NULIS == "1") {
        $sqlCmd = "SELECT * FROM lispre
        WHERE c_lista = :listacodigo AND c_lin = :linea AND c_clave = :codigo";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL -> bindParam(":listacodigo",$ListaCodigo,PDO::PARAM_STR);
        $oSQL -> bindParam(":linea" ,$ArticuloLinea ,PDO::PARAM_STR);
        $oSQL -> bindParam(":codigo",$ArticuloCodigo,PDO::PARAM_STR);
        $oSQL -> execute();
        $numRows = $oSQL->rowCount();
        if ($numRows < 1){
          http_response_code(400);
          throw new Exception("Articulo ". $ArticuloLinea. "-". $ArticuloCodigo. " no registrado en Lista Directa...");
          exit;
        }
        $rowLPDirecta = $oSQL->fetch(PDO::FETCH_ASSOC);
        $W_PRE = $rowLPDirecta["c_venta"];
        $Precio = $W_PRE;
        if($NormalEquivalente == "E" AND trim($W_CODE) <> ""){
          $W_PREE = $rowLPDirecta["c_ventae"];
          $PrecioEquivalente = $W_PREE;
        }
      }

      #-----------------------------------
      #LISTA POR COMPONENTE $W_NULIS == 2
      #-----------------------------------
      if ($W_NULIS == "2") {
        #MONEDA NACIONAL           <----------------- Hay que decidir si se usa $W_TIMON o $TipoMoneda
        if ($W_TIMON == "1") {

          switch ($Formulacion){

            #COSTEO PIEZA ( > Insumos Grupo 1 e Insumos Grupo 2 e Insumos Grupo 3) * Factor
            case "1":
              
              #Suma todos los insumos descartando Grupo 0
              $TPRE  = 0;
              $TPG   = 0;
              $TPREE = 0;
              $TPGE  = 0;              
              #preciosrutinas.php
              CalcEspecNulis2MNtipoF1();

              //              $W_FACTOR = $rowAGR["R_FACIMP"];    <----- se obtuvo anteriormente

              #Suma valor agregado
              if ($W_FACTOR <> 0) {
                $TPRE = $TPRE * (1 + $W_FACTOR / 100);
                $TPG  = $TPG  + ($W_FACTOR / 100);
                $W_NOAUMENTO = 1;
              }
              if ($NormalEquivalente == "E" and trim($W_CODE) <> "") {
                #preciosrutinas.php
                #sumaInsumosGpo1(); <-- se hizo la suma en la primera pasada
                //            $W_FACTOR = $rowAGR["R_FACIMP"];    <----- se obtuvo anteriormente

                #Suma Valor Agregado
                if ($W_FACTOR <> 0) {
                  $TPREE = $TPREE * (1 + $W_FACTOR / 100);
                  $TPGE  = $TPGE  * ($W_FACTOR / 100);
                  $W_NOAUMENTO = 1;
                }
              }
              $ValorAgregado = $W_FACTOR;
              $Precio = $TPRE;
              $PrecioEquivalente = $TPREE;
              break;
    
            #COSTEO GRAMO ( > Insumos Grupo 1 ) + Valor Agregado
            case "2":
              $TPRE  = 0;
              $TPG   = 0;
              $TPREE = 0;
              $TPGE  = 0;
    
              #Suma todos los insumos Grupo 1
              #preciosrutinas.php
              sumaInsumosGpo1();
              
              // $W_FACTOR = $rowAGR["R_FACIMP"];       <---- se obtuvo anteriormente
              #Suma valor agregado
              if ($W_FACTOR <> 0) {
                $TPRE = $TPRE + $W_FACTOR;
                $TPG  = $TPG  + $W_FACTOR;
                $W_NOAUMENTO = 1;
              }
              if ($NormalEquivalente == "E" and trim($W_CODE) <> "") {
                #preciosrutinas.php
                #sumaInsumosGpo1(); <-- se hizo la suma en la primera pasada

                // $W_FACTOR = $rowAGR["R_FACIMP"];     <---- se obtuvo anteriormente
                #Suma Valor Agregado                
                if ($W_FACTOR <> 0) {
                  $TPREE = $TPREE + $W_FACTOR;
                  $TPGE  = $TPGE  + $W_FACTOR;
                  $W_NOAUMENTO = 1;
                }
              }
              $ValorAgregado = $W_FACTOR;
              $Precio = $TPG;
              $PrecioEquivalente = $TPGE;
              break;
      

            #COSTEO GRAMO ( > Insumos Grupo 1 + Insumos Grupo 2 a Precio Venta + Insumos Grupo 3) + Valor Agregado
            case "3":
              $TPRE  = 0;
              $TPG   = 0;
              $TPREE = 0;
              $TPGE  = 0;

              # Insumos Grupo 1 + Insumos Grupo 2 a Precio Venta + Insumos Grupo 3
              #preciosrutinas.php
              sumaInsumosGpo1Gpo2PrecVtaGpo3();

              #Suma valor agregado
              // $W_FACTOR = $rowAGR["R_FACIMP"];     <---- se obtuvo anteriormente
              if ($W_FACTOR <> 0) {
                $TPRE = $TPRE + $W_FACTOR;
                $TPG  = $TPG  + $W_FACTOR;
                $W_NOAUMENTO = 1;
              }
              if ($NormalEquivalente == "E" && trim($W_CODE) <> "") {
                #preciosrutinas.php
                #sumaInsumosGpo1();     <-- la suma se hizo en la primera pasada

                #Suma Valor Agregado
                // $W_FACTOR = $rowAGR["R_FACIMP"];     <---- se obtuvo anteriormente
                if ($W_FACTOR <> 0) {
                  $TPREE = $TPREE + $W_FACTOR;
                  $TPGE  = $TPGE  + $W_FACTOR;
                  $W_NOAUMENTO = 1;
                }
              }

              break;

            #COSTEO GRAMO   > (Insumos Grupo 1  + Valor Agregado) +  > Insumos Grupo 2 a Precio de Venta e Insumos Grupo 3
            case "4":

              #Calcula peso piedra para restarlo al peso del articulo
              #------------------------------------------------------
              $W_TCANIM = 0;
              calcPesoPiedra();       #preciosrutinas.php
    
              #Suma Todos los Insumos Grupo 1 + Valor Agregado, PARIDAD ESPECIAL
              sumaInsumosGpo1MasValorAgregadoEspecial();
    
              #Suma Todos los Insumos Grupo 2 con Precio de Venta y Grupo 3
              #(segun ATNCT020.prg)
              sumaInsumosGpo2ConPrecioVentayGrupo3Especial();
    
              break;
          

          }
        }
      }

    }

    #COMPONENTES QUE SE VAN A PRESENTAR EN LA TABLA DE LA PANTALLA
    #----------------------------------------------------------------------------
    $sqlCmd = "CREATE TEMPORARY TABLE listacompo (grupo char (1), lin char(2), clave char (4),
    descripcion char (32), piezas decimal(12,2),gramos decimal(12,2),
    piezase decimal(12,2),gramose decimal(12,2))";
    $cmdCreate = $conn->prepare($sqlCmd);
    $cmdCreate->execute();

    $rowCOM = seekCompo($C_LCO1, $C_CO1);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA1);
      $temp->bindParam(":gramos", $C_GR1);
      $temp->bindParam(":piezase", $C_CA1E);
      $temp->bindParam(":gramose", $C_GR1E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO1, $C_CO2);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA2);
      $temp->bindParam(":gramos", $C_GR2);
      $temp->bindParam(":piezase", $C_CA2E);
      $temp->bindParam(":gramose", $C_GR2E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO3, $C_CO3);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA3);
      $temp->bindParam(":gramos", $C_GR3);
      $temp->bindParam(":piezase", $C_CA3E);
      $temp->bindParam(":gramose", $C_GR3E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO4, $C_CO4);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA4);
      $temp->bindParam(":gramos", $C_GR4);
      $temp->bindParam(":piezase", $C_CA4E);
      $temp->bindParam(":gramose", $C_GR4E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO5, $C_CO5);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA5);
      $temp->bindParam(":gramos", $C_GR5);
      $temp->bindParam(":piezase", $C_CA5E);
      $temp->bindParam(":gramose", $C_GR5E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO6, $C_CO6);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA6);
      $temp->bindParam(":gramos", $C_GR6);
      $temp->bindParam(":piezase", $C_CA6E);
      $temp->bindParam(":gramose", $C_GR6E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO7, $C_CO7);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA7);
      $temp->bindParam(":gramos", $C_GR7);
      $temp->bindParam(":piezase", $C_CA7E);
      $temp->bindParam(":gramose", $C_GR7E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO8, $C_CO8);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA8);
      $temp->bindParam(":gramos", $C_GR8);
      $temp->bindParam(":piezase", $C_CA8E);
      $temp->bindParam(":gramose", $C_GR8E);
      $temp->execute();
    }
    $rowCOM = seekCompo($C_LCO9, $C_CO9);
    if ($rowCOM <> null) {
      $sqlCmd = "INSERT INTO listacompo (grupo,lin,clave,descripcion,piezas,gramos,
        piezase,gramose)
        VALUES (:grupo,:lin,:clave,:descripcion,:piezas,:gramos,:piezase,:gramose)";
      $temp = $conn->prepare($sqlCmd);
      $temp->bindParam(":grupo", $rowCOM["co_grupo"]);
      $temp->bindParam(":lin", $rowCOM["co_lin"]);
      $temp->bindParam(":clave", $rowCOM["co_clave"]);
      $temp->bindParam(":descripcion", $rowCOM["co_descr"]);
      $temp->bindParam(":piezas", $C_CA9);
      $temp->bindParam(":gramos", $C_GR9);
      $temp->bindParam(":piezase", $C_CA9E);
      $temp->bindParam(":gramose", $C_GR9E);
      $temp->execute();
    }

    $sqlCmd = "SELECT * FROM listacompo"; 
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL-> execute();
    $datacompo = $oSQL-> fetchAll(PDO::FETCH_ASSOC);
    
    $RedonGlobal;  // equivale a $W_REDO de Proeli

    $TipoCalcDescr = "";
    if($W_NULIS == 1){
      $TipoCalcDescr = "Directa";
    } elseif($W_NULIS == 2) {
      $TipoCalcDescr = "Por Componentes";
    }

    $TipoCosteoDescr = "";
    if($TipoCosteo == 1){
      $TipoCosteoDescr = "Costeo por Pieza";
    } elseif($TipoCosteo == 2){
      $TipoCosteoDescr = "Costeo por Gramo";
    }

    $arrData = array(
      "cc_num"  => $rowCli010["cc_num"],
      "cc_fil"  => $rowCli010["cc_fil"],
      "cc_raso" => $rowCli010["cc_raso"],
      "lista_numero"  => $Lista,
      "lista_codigo"  => $ListaCodigo,
      "lista_descr"   => $ListaDescr,
      "cc_tparid"     => $TipoParidad,
      "tparid_descr"  => $TipoParidadDescr,
      "c_codbarh"     => $W_CODBARH,
      "c_lin"         => $ArticuloLinea,
      "linea_descr"   => $LineaDescripc,
      "c_clave"       => $ArticuloCodigo,
      "cc_cata"       => $NormalEquivalente,
      "c_descr"       => $ArticuloDescr,
      "calc_descr"    => $TipoCalcDescr,
      "tipocosteo"    => $TipoCosteo,
      "tipocosteodescr" => $TipoCosteoDescr,
      "valor_agregado"  => $ValorAgregado,
      "precio"          => $Precio,
      "precio_equival"  => $PrecioEquivalente
    );
    
  } catch (Exception $e) {
    http_response_code(503);  // Service Unavailable
    $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
    echo json_encode($response);
    exit;
  }

  $conn = null;   // Cierra conexión

  # Falta tener en cuenta la paginacion

  array_push($arrData, $datacompo);
  
  return $arrData;

}

/**
 * Crea el JSON incluido en la seccion "Contenido", 
 * de acuerdo a la especificaion del endpoint, incluyendo
 * todos los nodos requeridos.
 * @param array data 
 * @return object
 */
FUNCTION CreaDataCompuesta( $data )
{

  $contenido    = array();
  $componentes  = array();
  $filaCompon   = array();

  if(count($data)>0){
    /*
      foreach($data as $row){

        // Se crea un array con los nodos requeridos
        $filaCompon = [
          "ComponenteGrupo"  => $data["grupo"],
          "ComponenteLinea"  => $data["lin"],
          "ComponenteCodigo" => trim($data["clave"]),
          "ComponenteDescripc" => trim($data["descripcion"]),
          "PiezasNormal"  => floatval($data["piezas"]),
          "Gramos Normal" => floatval($data["gramos"]),
          "PiezasEquivalente" => floatval($data["piezase"]),
          "GramosEquivalente" => floatval($data["gramose"])
        ];

        // Se agrega el array a la seccion "contenido"
        array_push($componentes, $filaCompon);

      }   // foreach($data as $row)
    */

    foreach($data as $row){

      if (is_array($row)){

        foreach($row as $compo){
          // Se crea un array con los nodos requeridos
          $filaCompon = [
            "ComponenteGrupo"  => $compo["grupo"],
            "ComponenteLinea"  => $compo["lin"],
            "ComponenteCodigo" => trim($compo["clave"]),
            "ComponenteDescripc" => trim($compo["descripcion"]),
            "PiezasNormal"  => floatval($compo["piezas"]),
            "Gramos Normal" => floatval($compo["gramos"]),
            "PiezasEquivalente" => floatval($compo["piezase"]),
            "GramosEquivalente" => floatval($compo["gramose"])
          ];

          // Se agrega el array a la seccion "contenido"
          array_push($componentes, $filaCompon);
        }

      }

    }   // foreach($data as $row)


    $contenido = [
      "ClienteCodigo" => $data["cc_num"],
      "ClienteFilial" => $data["cc_fil"],
      "ClienteNombre" => $data["cc_raso"],
      "ListaNumero"   => $data["lista_numero"],
      "TipoListaCodigo"      => $data["lista_codigo"],
      "TipoListaDescripc"    => $data["lista_descr"],
      "TipoParidadCodigo"    => $data["cc_tparid"],
      "TipoParidadDescripc"  => $data["tparid_descr"],
      "ArticuloCodigoBarras" => $data["c_codbarh"],
      "ArticuloLinea"        => $data["c_lin"],
      "LineaDescripc"        => $data["linea_descr"],
      "ArticuloCodigo"       => $data["c_clave"],
      "ArticuloDescripc"     => $data["c_descr"],
      "TipoCalculoDescripc"  => $data["calc_descr"],
      "TipoCosteoCodigo"     => $data["tipocosteo"],
      "TipoCosteoDescripc"   => $data["tipocosteodescr"],
      "ValorAgregado"        => floatval($data["valor_agregado"]),
      "CodigoNormalEquivalente" => $data["cc_cata"],
      "Precio"  => floatval($data["precio"]),
      "PrecioEquivalente" => floatval($data["precio_equival"]),
      "Componentes" => $componentes
    ];
  
  } // count($data)>0
  
  return $contenido; 

}
