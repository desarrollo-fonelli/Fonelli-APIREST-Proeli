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
const K_SCRIPTNAME  = "Login.php";

# Declara variables generales
$codigo   = null;   // codigo devuelto en el json de respuesta
$mensaje  = "";     // mensaje que complementa el codigo de respuesta del endpoint
$data     = [];     // arreglo asociativo con la data devuelta por el comando SELECT
$dataJson = null;   // data en formato JSON 
$response = null;   // JSON devuelto por el endpoint conteniendo todos los nodos especificados
$sqlCmd   = "";     // comando SQL que se envía al engine de datos

$usr_tipo = null;   // Tipo de usuario: C=Cliente G=Gerente A=Agente
$usr_code = null;   // Ej "1721-0", "2", "18"
$usr_password = null;   // 6 caracteres por consistencia con PRoeli
$Autenticado = "";  // ver archivo "include/constantes.php"

# Comprueba Request Method
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod != "POST") {
  http_response_code(405);
  $mensaje = "Este EndPoint solo acepta verbos POST";   // quité K_SCRIPTNAME del mensaje
  echo json_encode(["Code" => K_API_FAILVERB, "Mensaje" => $mensaje]);
  exit;
}

# Por convención, se va va recibir en un parámetro la cadena de texto con la 
# estructura JSON que contiene los datos del formulario.
# Ejemplo de la cadena de texto recibida
# $DatosForm = '{"usr_tipo":"G","usr_code":"1721-0","usr_password":"123456"}'

# Hay que comprobar que se pasen los parametros obligatorios
# OJO: Los nombres de parametro son sensibles a mayusculas/minusculas
try {

  if (!isset($_POST["DatosForm"])) {
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

# Comprueba los elementos incluidos en el JSON
$DatosArray = json_decode($DatosForm, true);   // true indica que la cadena genera un array asociativo
if (is_null($DatosArray)) {
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Error en JSON - " . json_last_error_msg()]);
  exit;
}

$errorCamposNoReconoc = "";
$errorFaltanCampos    = "";
$arrCamposForm = array("usr_tipo", "usr_code", "usr_password");
$keysDatosArray = array_keys($DatosArray);
foreach ($keysDatosArray as $campo) {
  if (!in_array($campo, $arrCamposForm)) {
    if (strlen($errorCamposNoReconoc) > 1) {
      $errorCamposNoReconoc .= ", ";
    }
    $errorCamposNoReconoc .= $campo;
  }
}

if(in_array("usuario",$keysDatosArray)){

  $usr_tipo = "S";
  $usr_code = $DatosArray["usuario"];
  $usr_password = $DatosArray["password"];

} else {

  if (!in_array("usr_code", $keysDatosArray)) {
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'usr_code'"]);
    exit;
  }
  if (!in_array("usr_password", $keysDatosArray)) {
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'usr_password'"]);
    exit;
  }
  if (!in_array("usr_tipo", $keysDatosArray)) {
    http_response_code(400);
    echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'usr_tipo'"]);
    exit;
  }  

  $usr_tipo  = trim($DatosArray["usr_tipo"]);
  $usr_code  = trim($DatosArray["usr_code"]);
  $usr_password = trim($DatosArray["usr_password"]);  
}


# Controla el Proceso de Autenticación
try {

  // Compara credenciales contra datos registrados para el usuario
  // Ver valores posibles en "include/constantes.php"
  $Autenticado = ValidaCredenciales($usr_tipo, $usr_code, $usr_password);

  // En vez de <exito> | <fracaso> se va a devolver el código http
  if ($Autenticado["auth"] == K_AUTH_OK) {
    header('Auth:' . $Autenticado["token"]);
    http_response_code(200);
    $codigo = K_API_OK;
  } else {
    http_response_code(401);  // Código HTTP definido por JMaravilla 21/jul/2022    
    $codigo = -1;
  }
} catch (Exception $e) {
  http_response_code(503);  // Service Unavailable
  $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage()];
  echo json_encode($response);
  exit;
}

$response = json_encode(["Codigo" => $codigo, "Mensaje" => $Autenticado["auth"], "Auth" => $Autenticado["token"]]);

echo $response;

return;


/**
 * Compara credenciales y devuelve resultado de la autenticación
 * Ver valores posibles en "include/constantes.php"
 */
function ValidaCredenciales($usr_tipo, $usr_code, $usr_password)
{

  $token  = null;   // Token generado
  $nombre = null;   // Nombre del usuario

  # Se conecta a la base de datos
  require_once "../db/conexion.php";

  $auth = "";    // Indica resultado de la autenticación

  if ($usr_tipo == "" or $usr_code == "" or $usr_password == "") {
    // Registra en bitácora el intento de autenticación
    $auth = K_AUTH_DATA_EMPTY;
  }

  if ($auth == "") {
    // Hay que definir dinamicamente el schema <---------------------------------
    $sqlCmd = "SET SEARCH_PATH TO dateli;";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();

    // Busca al usuario de acuerdo a su tipo
    switch ($usr_tipo) {
      case "G":
        $sqlCmd = "SELECT gc_nom AS nombre, gc_status AS estado, gc_passw AS passw FROM var031 
          WHERE TRIM(gc_llave) = :usr_code";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->bindParam("usr_code", $usr_code, PDO::PARAM_STR);
        $oSQL->execute();
        $numRows = $oSQL->rowCount();
        break;

      case "A":
        $sqlCmd = "SELECT gc_nom AS nombre, gc_status AS estado, gc_passw AS passw FROM var030 
          WHERE TRIM(gc_llave) = :usr_code";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->bindParam("usr_code", $usr_code, PDO::PARAM_STR);
        $oSQL->execute();
        $numRows = $oSQL->rowCount();
        break;
      case "C":
        $sqlCmd = "SELECT cc_raso AS nombre, cc_status AS estado, cc_passw AS passw FROM cli010 
          WHERE CONCAT(TRIM(cc_num),'-',TRIM(cc_fil)) = :usr_code";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->bindParam("usr_code", $usr_code, PDO::PARAM_STR);
        $oSQL->execute();
        $numRows = $oSQL->rowCount();
        break;
      case "S":
        $sqlCmd = "SELECT usuario AS nombre, estado AS estado, password AS passw FROM usuarios
        WHERE usuario = :usr_code";
        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->bindParam("usr_code", $usr_code, PDO::PARAM_STR);
        $oSQL->execute();
        $numRows = $oSQL->rowCount();
        break;
    }

    if ($numRows < 1) {
      // Registra en bitácora el intento de autenticación
      $auth = K_AUTH_USER_NOREG;
    }

    if ($auth == "") {
      $row = $oSQL->fetch(PDO::FETCH_ASSOC);

      $nombre = trim($row["nombre"]);

      if (strtoupper(trim($row["passw"])) != strtoupper(trim($usr_password))) {
        $auth = K_AUTH_PASSW_FAIL;
      } else {

        // Verifica el status solo para Gerentes y Agentes
        if (trim($row["estado"]) != "A") {
          if (in_array($usr_tipo, ["G", "A"])) {
            $auth = K_AUTH_USER_INACT;
          }
        }
      }
    }
  }

  if ($auth == "") {
    // Si las credenciales del usuario son corectas, genera el Token
    $token = hash("sha256", $nombre . random_int(1, 9999), false);

    // Registra token en tabla de usuarios autenticados
    RegistraToken($conn, $usr_tipo, $usr_code, $nombre, $token);

    $auth = K_AUTH_OK;
  }

  // Registra en bitácora el intento de autenticación
  RegistraLogin($conn, $usr_code . " : " . $nombre, $auth);

  $conn = null;

  return ["auth" => $auth, "token" => $token];
}


/**
 * Registra token en tabla de usuarios autenticados
 */
function RegistraToken($conn, $usr_tipo, $usr_code, $nombre, $token)
{
  $IP_origen = $_SERVER['REMOTE_ADDR'];  //. ":". $_SERVER['REMOTE_PORT'];

  // Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  // Busca clave de usuario en tabla de TOKENS, para sobrescribirlo o crear un nuevo registro  
  $sqlCmd = "SELECT usr_tipo,usr_code FROM tokens 
    WHERE usr_tipo = :usr_tipo AND usr_code = :usr_code";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":usr_tipo", $usr_tipo, PDO::PARAM_STR);
  $oSQL->bindParam(":usr_code", $usr_code, PDO::PARAM_STR);
  $oSQL->execute();
  $numRows = $oSQL->rowCount();

  // Si existe un registro, lo borra
  if ($numRows > 0) {
    $sqlCmd = "";
    $oSQL = null;
    $numRows = 0;

    $sqlCmd = "DELETE FROM tokens 
      WHERE usr_tipo = :usr_tipo AND usr_code = :usr_code
    ";
    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":usr_tipo", $usr_tipo, PDO::PARAM_STR);
    $oSQL->bindParam(":usr_code", $usr_code, PDO::PARAM_STR);
    $oSQL->execute();
  }

  // Inserta un registro con los datos del usuario
  $sqlCmd = "";
  $oSQL = null;
  $numRows = 0;

  $sqlCmd = "INSERT INTO tokens (usr_tipo,usr_code,usr_token,fecha_token,ip_origen,usr_nombre) 
    VALUES (:usr_tipo, :usr_code, :usr_token, current_timestamp, :ip_origen, :nombre)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":usr_tipo", $usr_tipo, PDO::PARAM_STR);
  $oSQL->bindParam(":usr_code", $usr_code, PDO::PARAM_STR);
  $oSQL->bindParam(":usr_token", $token, PDO::PARAM_STR);
  $oSQL->bindParam(":ip_origen", $IP_origen, PDO::PARAM_STR);
  $oSQL->bindParam(":nombre", $nombre, PDO::PARAM_STR);
  $oSQL->execute();
}


/**
 * Registra en bitácora el intento de autenticación
 */
function RegistraLogin($conn, $usuario, $Autenticado)
{
  $IP_origen = $_SERVER['REMOTE_ADDR'];  //. ":". $_SERVER['REMOTE_PORT'];

  // Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  // Inserta registro en base de datos
  $sqlCmd = "INSERT INTO loglogin (fecha,usuario,ip_origen,resultado) 
  VALUES (current_timestamp, :usuario, :ip_origen, :resultado)";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":usuario", $usuario, PDO::PARAM_STR);
  $oSQL->bindParam(":ip_origen", $IP_origen, PDO::PARAM_STR);
  $oSQL->bindParam(":resultado", $Autenticado, PDO::PARAM_STR);
  $oSQL->execute();
}
