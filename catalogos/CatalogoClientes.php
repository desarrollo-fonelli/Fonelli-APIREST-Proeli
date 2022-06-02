<?php
@session_start();
header('Content-type: application/json');

/*
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token');
*/

date_default_timezone_set('America/Mexico_City');

/**
 * Lista del Catalogo de Clientes 
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Constantes locales
const K_SCRIPTNAME  = "catalogoclientes.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

# Variables asociadas a los parámetros recibidos
$Usuario        = null;     // Id del usuario (cliente o agente)
$ClienteCodigo  = null;     // Id del cliente
$ClienteFilial  = null;     // Filial del cliente
$Password       = null;     // Contraseña asignada al cliente
$Status         = null;     // Status del cliente
$AgenteCodigo   = null;     // Codigo del agente de ventas asociado al cliente
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

/*
    19.may.2022 dRendon: Por ahora el Usuario va a ser un parámetro OPCIONAL

try {
  if (!isset($_GET["Usuario"])) {
    throw new Exception("El parametro obligatorio 'Usuario' no fue definido.");
  }
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}
*/

# Lista de parámetros aceptados por este endpoint
$arrPermitidos = array("Usuario", "ClienteCodigo", "ClienteFilial", 
"Password", "Status", "AgenteCodigo", "Pagina");

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
if (isset($_GET["ClienteCodigo"])) {
  $ClienteCodigo = $_GET["ClienteCodigo"];
}

if (isset($_GET["ClienteFilial"])) {
  if(isset($ClienteCodigo)) {
    $ClienteFilial = $_GET["ClienteFilial"];
  } else {
    $mensaje = "Si incluye 'ClienteFilial', debe indicar 'ClienteCodigo'.";   // quité K_SCRIPTNAME del mensaje
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }    
}

if (isset($_GET["Password"])) {
  if(isset($ClienteCodigo) && isset($ClienteFilial)){
    $Password =  $_GET["Password"];
  } else {
    $mensaje = "Si incluye un 'Password', debe indicar 'ClienteCodigo' y 'ClienteFilial'.";   // quité K_SCRIPTNAME del mensaje
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $mensaje]);
    exit;
  }  
}

if (isset($_GET["Status"])) {
  $Status = $_GET["Status"];
}

if (isset($_GET["AgenteCodigo"])) {
  $AgenteCodigo = $_GET["AgenteCodigo"];
}

if (isset($_GET["Pagina"])) {
  $Pagina = $_GET["Pagina"];
}

# Ejecuta la consulta 
try {
  $data = SelectClientes($ClienteCodigo, $ClienteFilial, $Password, $Status, $AgenteCodigo);

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
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
    "Contenido"   => $dataCompuesta
  ];    

} catch (Exception $e) {
  /*
  $result_array = [
      "result" => $e,
      "error" => $conn->get_last_error(),
      "Code" => -1
  ];
*/
  $response = [
    "Codigo"      => K_API_ERRSQL,
    "Mensaje"     => $conn->get_last_error(),
    "Paginacion"  => ["NumFilas" => $numFilas, "TotalPaginas" => $totalPaginas, "Pagina" => $Pagina],
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
FUNCTION SelectClientes($ClienteCodigo, $ClienteFilial, $Password, $Status, $AgenteCodigo)
{
  $where = "";

  # En caso necesario, hay que formatear los parametros que se van a pasar a la consulta
  if(isset($ClienteCodigo)){
    $strClienteCodigo = str_pad($ClienteCodigo, 6," ",STR_PAD_LEFT);
  }
  if(isset($ClienteFilial)){
    $strClienteFilial = str_pad($ClienteFilial, 3," ",STR_PAD_LEFT);
  }  
  if(isset($AgenteCodigo)){
    $strAgenteCodigo = str_pad($AgenteCodigo, 2," ",STR_PAD_LEFT);
  }

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();

  # Construyo dinamicamente la condicion WHERE
  if(isset($ClienteCodigo)){
    $where = "WHERE a.cc_num = :strClienteCodigo ";
  }
  if(isset($ClienteFilial)){
    // Previamente se validó que existiera el numero de cliente
    $where .= "AND a.cc_fil = :strClienteFilial ";
  }

  if(isset($Password)){
    if($where == ""){
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= " TRIM(a.cc_passw) = :Password ";
  }
  if(isset($Status)){
    if($where == ""){
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= " TRIM(a.cc_status) = :Status ";
  }
  if(isset($AgenteCodigo)){
    if($where == ""){
      $where = "WHERE ";
    } else {
      $where .= "AND ";
    }
    $where .= "a.cc_age = :strAgenteCodigo ";
  }

  //var_dump($where);

  $sqlCmd = "SELECT trim(a.cc_num) cc_num,trim(a.cc_fil) cc_fil,trim(a.cc_raso) cc_raso,trim(a.cc_suc) cc_suc,
    trim(a.cc_passw) cc_passw,
    trim(a.cc_status)cc_status,trim(a.cc_age) cc_age,trim(a.cc_ger) cc_ger,trim(a.cc_ticte) cc_ticte,
    trim(a.cc_dir) cc_dir,trim(a.cc_nume) cc_nume,trim(a.cc_numi) cc_numi,trim(a.cc_col) cc_col,
    trim(a.cc_deleg) cc_deleg,trim(a.cc_pob) cc_pob,trim(a.cc_edo) cc_edo,a.cc_cp,trim(a.cc_pais) cc_pais,
    trim(a.cc_rfc) cc_rfc,trim(a.cc_lada) cc_lada,trim(a.cc_tel) cc_tel,
    trim(a.cc_tipoli) cc_tipoli,trim(a.cc_tipoli2) cc_tipoli2,a.cc_tfact,a.cc_tparid,
    CASE 
    WHEN a.cc_tparid = 'N' THEN 'NORMAL' 
    WHEN a.cc_tparid = 'E' THEN 'ESPECIAL' 
    ELSE ''
    END desc_paridad,
    trim(a.cc_banamex) cc_banamex,a.cc_limcre,CONCAT(a.cc_plazo, ' DIAS') cc_plazo,a.cc_alta,
    trim(a.cc_nombre1) cc_nombre1,trim(a.cc_apepat1) cc_apepat1,trim(a.cc_apemat1) cc_apemat1,trim(cc_email1) cc_email1,
    trim(a.cc_nombre2) cc_nombre2,trim(a.cc_apepat2) cc_apepat2,trim(a.cc_apemat2) cc_apemat2,trim(cc_email2) cc_email2,
    trim(a.cc_ofiatn) cc_ofiatn,
    trim(b.s_nomsuc) s_nomsuc, trim(c.t_descr) desc_ticte,
    trim(d.gc_nom) gc_nom, trim(e.gc_nom) gc_nomger, trim(f.t_descr) desc_quinto, trim(g.t_descr) desc_tipoli,
    trim(h.t_descr) desc_tipoli2, trim(i.t_descr) desc_zona, trim(j.t_descr) desc_ruta, trim(k.uso_descr) uso_descr,
    trim(l.ad_nombre) ad_nombre, trim(m.t_descr) desc_forpag, trim(n.pr_nom) pr_nom
    FROM cli010 a 
    LEFT JOIN dirsdo b ON b.S_LLAVE  = a.CC_OFIATN 
    LEFT JOIN var020 c ON (c.T_TICA = '91' AND c.T_GPO = a.CC_TICTE)
    LEFT JOIN var030 d ON d.GC_LLAVE = a.CC_AGE
    LEFT JOIN var031 e ON e.GC_LLAVE = a.CC_GER
    LEFT JOIN var020 f ON (f.T_TICA = '12' AND f.T_GPO = a.CC_QUINTO)
    LEFT JOIN var020 g ON (g.T_TICA = '10' AND g.T_GPO = '93' AND g.T_CLAVE = a.CC_TIPOLI) 
    LEFT JOIN var020 h ON (h.T_TICA = '10' AND h.T_GPO = '93' AND h.T_CLAVE = a.CC_TIPOLI2) 
    LEFT JOIN var020 i ON (i.T_TICA = '90' AND i.T_GPO = a.CC_ZONA AND i.T_CLAVE = '  ') 
    LEFT JOIN var020 j ON (j.T_TICA = '90' AND j.T_GPO = a.CC_ZONA AND j.T_CLAVE = a.CC_RUTA)
    LEFT JOIN usocfdi k ON k.USO_USO = a.CC_USOCFDI
    LEFT JOIN var070 l ON l.AD_ADENDA = a.CC_ADENDA 
    LEFT JOIN var020 m ON (m.T_TICA = '10' AND m.T_GPO = '19' AND m.T_CLAVE = a.CC_FORPAG) 
    LEFT JOIN prov10 n ON n.PR_NOM  = a.CC_PROVADM 
    $where 
    ORDER BY a.cc_num,a.cc_fil";

  //var_dump($sqlCmd);

  try {
    $oSQL = $conn-> prepare($sqlCmd);

    if(isset($ClienteCodigo)){
      $oSQL-> bindParam(":strClienteCodigo", $strClienteCodigo, PDO::PARAM_STR);
    }
    if(isset($ClienteFilial)){
      $oSQL-> bindParam(":strClienteFilial", $strClienteFilial, PDO::PARAM_STR);
    }
    if(isset($Password)){
      $oSQL-> bindParam(":Password" , $Password, PDO::PARAM_STR);
    }
    if(isset($Status)){
      $oSQL-> bindParam(":Status" , $Status, PDO::PARAM_STR);
    }
    if(isset($AgenteCodigo)){
      $oSQL-> bindParam(":strAgenteCodigo" , $strAgenteCodigo, PDO::PARAM_STR);
    }
    //$oSQL-> bindParam(":provocaerror", "",PDO::PARAM_STR);  usado para pruebas de control de errores

    $oSQL-> execute();
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
FUNCTION CreaDataCompuesta( $data )
{

  $contenido = array();
  $oFila = array();

  foreach($data as $row){

    // Se crea un array con los nodos requeridos
    $oFila = [
      "ClienteCodigo" => $row["cc_num"],
      "ClienteFilial" => $row["cc_fil"] ,
      "RazonSocial"   => $row["cc_raso"],
      "Password"      => $row["cc_passw"],

      "DatosGenerales" => [
        "Sucursal" => $row["cc_suc"],
        "Status" => $row["cc_status"],
        "TipoClienteCodigo" => $row["cc_ticte"],
        "TipoClienteDescripc" => $row["desc_ticte"],
        "Calle" => $row["cc_dir"],
        "NumExterior" => $row["cc_nume"],
        "NumInterior" => $row["cc_numi"],
        "Colonia" => $row["cc_col"],
        "AlcaldiaNombre" => $row["cc_deleg"],
        "CiudadPoblacion" => $row["cc_pob"],
        "EntidadNombre" => $row["cc_edo"],
        "CodigoPostal" => $row["cc_cp"],
        "PaisNombre" => $row["cc_pais"],
        "Rfc" => $row["cc_rfc"],
        "Lada" => $row["cc_lada"],
        "Telefono" => $row["cc_tel"],
        "AgenteCodigo" => $row["cc_age"],
        "AgenteNombre" => $row["gc_nom"]
      ],

      "Condiciones" => [
        "OficinaAtencionCodigo" => $row["cc_ofiatn"],
        "OficinaAtencionNombre" => $row["s_nomsuc"],
        "TipoListaPrecios" => $row["cc_tipoli"],
        "TipoListaPreciosDescripc" => $row["desc_tipoli"],
        "TipoListaPrecios2" => $row["cc_tipoli2"],
        "TipoListaPrecios2Descripc" => $row["desc_tipoli2"],
        "ParidadCodigo" => $row["cc_tparid"],
        "ParidadDescripc" => $row["desc_paridad"],
        "PagoRefBanamex" => $row["cc_banamex"],
        "LimiteCredito" => $row["cc_limcre"],
        "Plazo" => $row["cc_plazo"],
        "FechaAlta" => $row["cc_alta"]
      ],
    
      "Contactos" => [
        "Contacto1Nombre" => $row["cc_nombre1"],
        "Contacto1ApellidoPaterno" => $row["cc_apepat1"],
        "Contacto1ApellidoMaterno" => $row["cc_apemat1"],
        "Contacto1Email" => $row["cc_email1"],
        "Contacto2Nombre" => $row["cc_nombre2"],
        "Contacto2ApellidoPaterno" => $row["cc_apepat2"],
        "Contacto2ApellidoMaterno" => $row["cc_apemat2"],
        "Contacto2Email" => $row["cc_email2"]
      ]
    
    ];

    // Se agrega el array a la seccion "contenido"
    array_push($contenido, $oFila);

  }   // foreach($data as $row)

  return $contenido; 

}
