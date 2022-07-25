<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Respuesta a POST del formulario para autenticar usuarios
 * --------------------------------------------------------------------------
 */

# En el script 'constantes.php' se definen:
# - los codigos de respuesta de la API
# - el numero de filas por pagina
require_once "../include/constantes.php";

# Constantes locales
const K_SCRIPTNAME  = "consultaprecios.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

$Autenticado    = "";  // ver archivo "include/constantes.php"

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "POST") {
  http_response_code(405);
  $mensaje = "Esta API solo acepta verbos POST";   // quité K_SCRIPTNAME del mensaje
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje ]);
  exit;
}

# Por convención, se va va recibir en un parámetro la cadena de texto con la 
# estructura JSON que contiene los datos del formulario.
# Ejemplo de la cadena de texto recibida
# $DatosForm = '{"usuario":"dataencriptada","password":"dataencriptada"}'

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {

  if(!isset($_POST["DatosForm"])){
    throw new Exception("El parametro obligatorio 'DatosForm' no fue recibido.");    
  } else {
    $DatosForm = $_POST["DatosForm"];
  }

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_FAILAUTH, "Mensaje" => $e->getMessage()]);
  exit;
}

# Lista de parámetros aceptados por este endpoint
# En este caso, el parámetro "DatosForm" es una cadena de texto en formato JSON
$arrPermitidos = array("DatosForm");

# Obtiene todos los parametros pasados en la llamada y verifica que existan
# en la lista de parámetros aceptados por el endpoint
$mensaje = "";
$arrParam = array_keys($_POST);
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

# Comprueba los elementos incluidos en el JSON
$DatosArray = json_decode($DatosForm, true);   // true indica que la cadena genera un array asociativo
if(is_null($DatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Error en JSON - ". json_last_error_msg()]);
  exit;
}

$errorCamposNoReconoc = "";
$errorFaltanCampos    = "";
$arrCamposForm = array("usuario","password");
$keysDatosArray= array_keys($DatosArray);
foreach($keysDatosArray as $campo){
  if(! in_array($campo, $arrCamposForm)){
    if(strlen($errorCamposNoReconoc) > 1){
      $errorCamposNoReconoc .= ", ";
    }
    $errorCamposNoReconoc .= $campo;
  }
}
if(strlen($errorCamposNoReconoc) > 0){
  $errorCamposNoReconoc = "Datos no reconocidos: ". $errorCamposNoReconoc;   
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $errorCamposNoReconoc]);
  exit;
}

if(! in_array("usuario", $keysDatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'usuario'"]);
  exit;
}
if(! in_array("password", $keysDatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'password'"]);
  exit;
}

$usuario  = trim($DatosArray["usuario"]);
$password = trim($DatosArray["password"]);

# Controla el Proceso de Autenticación
try {

  // Compara credenciales contra datos registrados para el usuario
  // Ver valores posibles en "include/constantes.php"
  $Autenticado = ValidaCredenciales($usuario, $password); 

  // En vez de <exito> | <fracaso> se va a devolver el código http
  if($Autenticado == K_AUTH_OK){
    http_response_code(200);
    $response = json_encode(["Codigo" => 0]);
  } else {
    http_response_code(401);  // Código HTTP definido por JMaravilla 21/jul/2022    
    $response = json_encode(["Codigo" => -1, "Mensaje" => $Autenticado]);
  }

} catch (Exception $e) {
  http_response_code(503);  // Service Unavailable
  $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage()];
  echo json_encode($response);
  exit;
}

echo $response;

return;


/**
 * Compara credenciales y devuelve resultado de la autenticación
 * Ver valores posibles en "include/constantes.php"
 */
FUNCTION ValidaCredenciales($usuario, $password)
{

  //$testigo = hash("sha256","SYSADMIN",false);
  //var_dump(strtoupper($testigo));
  //LA SIGUIENTE FUNCION NO TRABAJA CON HASHES QUE NO FUERON GENERADOS POR PHP
  //if (password_verify('SYSADMIN', 'cde01d2db10b2bc2d81a6dc738ccf16f7b99973a57737486903436a685f0e7fa')) {
  //  echo 'Password is valid!'; exit;
  //} else {
  //  echo 'Invalid password.'; exit;
  //}
  //ESCENARIO PROPUESTO:
  //"usuario"  se almacena en la BD como texto plano.
  //"password" se almacena encriptado.
  //"usuario"  se recibe como texto plano.
  //"password  se recibe encriptado.  
  //"usuario"  recibido se busca en la base de datos
  //"password" recibido encriptado se compara con el password almacenado encriptado


  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  $auth = "";    // Indica resultado de la autenticación

  if($usuario == "" OR $password == ""){
    // Registra en bitácora el intento de autenticación
    $auth = K_AUTH_DATA_EMPTY;
  }

  if($auth == ""){
    // Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> execute();

    // Busca al usuario
    $sqlCmd = "SELECT * FROM usuarios WHERE TRIM(usuario) = :usuario";
    $oSQL = $conn-> prepare($sqlCmd);
    $oSQL-> bindParam(":usuario",$usuario,PDO::PARAM_STR);
    $oSQL-> execute();
    $numRows = $oSQL->rowCount();
    if($numRows < 1){
      // Registra en bitácora el intento de autenticación
      $auth = K_AUTH_USER_NOREG;
    }

    if($auth == ""){
      $row = $oSQL->fetch(PDO::FETCH_ASSOC);

      if(strtoupper(trim($row["password"])) != strtoupper(trim($password))){
        $auth = K_AUTH_PASSW_FAIL;
      } else {
        if(trim($row["estado"]) != "Activo"){
          $auth = K_AUTH_USER_INACT;
        } else {  
          $auth = K_AUTH_OK;
        }    
      }
    }
  }

  // Registra en bitácora el intento de autenticación
  RegistraLogin($conn, $usuario, $auth);

  $conn = null;

  return $auth;

}


/**
 * Registra en bitácora el intento de autenticación
 */
FUNCTION RegistraLogin($conn, $usuario, $Autenticado) 
{
  $IP_origen = $_SERVER['REMOTE_ADDR'];  //. ":". $_SERVER['REMOTE_PORT'];

  // Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();
  
  // Inserta registro en base de datos
  $sqlCmd = "INSERT INTO loglogin (fecha,usuario,ip_origen,resultado) 
  VALUES (current_timestamp, :usuario, :ip_origen, :resultado)";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> bindParam(":usuario",$usuario,PDO::PARAM_STR);
  $oSQL-> bindParam(":ip_origen",$IP_origen,PDO::PARAM_STR);
  $oSQL-> bindParam(":resultado",$Autenticado,PDO::PARAM_STR);
  $oSQL-> execute();

}

