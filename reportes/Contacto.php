<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');

/**
 * Respuesta a POST del formulario de contacto
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
# $DatosForm = '{"Nombre":"Carlos Salinas","Email":"buzon@dominio.com","Telefono":"554444-3333","Mensaje":"Favor de enviar información de argollas"}'

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
$arrCamposForm = array("Nombre","Email","Telefono","Mensaje");
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
  $errorCamposNoReconoc = "Datos no reconocidos: ". $errorCamposNoReconoc;   // quité K_SCRIPTNAME del mensaje
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => $errorCamposNoReconoc]);
  exit;
}

if(! in_array("Nombre", $keysDatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'Nombre'"]);
  exit;
}
if(! in_array("Email", $keysDatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'Email'"]);
  exit;
}
if(! in_array("Telefono", $keysDatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'Telefono'"]);
  exit;
}
if(! in_array("Mensaje", $keysDatosArray)){
  http_response_code(400);
  echo json_encode(["Code" => K_API_ERRPARAM, "Mensaje" => "Falta elemento 'Mensaje'"]);
  exit;
}


// Envía correo electrónico
require_once "class.phpmailer.php";
$resultado = envio_correo($DatosArray);

if($resultado == "ok"){
  $codigo = 0;
  $mensaje = "Correo enviado.";

  $response = [
    "Codigo"  => $codigo,
    "Mensaje" => $mensaje
  ];

  $response = json_encode($response);
  echo $response;

} else {
  $mensaje = $resultado;
}

# Se conecta a la base de datos
require_once "../db/conexion.php";

try {

  # Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> execute();

  $sqlCmd = "INSERT INTO logcontacto (fecha,nombre,email,telefono,mensaje,resultado) VALUES (
    current_timestamp, :nombre, :email, :telefono, :mensaje, :resultado)";
  $oSQL = $conn-> prepare($sqlCmd);
  $oSQL-> bindParam(":nombre", $DatosArray["Nombre"]);
  $oSQL-> bindParam(":email", $DatosArray["Email"]);
  $oSQL-> bindParam(":telefono", $DatosArray["Telefono"]);
  $oSQL-> bindParam(":mensaje", $DatosArray["Mensaje"]);
  $oSQL-> bindParam(":resultado", $mensaje);
  $oSQL-> execute();

} catch (Exception $e) {
  http_response_code(503);  // Service Unavailable
  $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage()];
  echo json_encode($response);
  exit;
}

return;

/**
 * Configura servicio SMTP y envía correo electronico notificando
 * la solicitud de información desde la página de Contacto
 */
  function envio_correo($DatosArray){

    /*
    $DatosArray["Nombre"];
    $DatosArray["Email"];
    $DatosArray["Telefono"];
    $DatosArray["Mensaje"];
    */

    $correo  = $DatosArray["Email"];
    $asunto  = "Solicitud de Contacto Fonelli";
    $mensaje = "Un prospecto esta solicitando informacion desde el Sitio Web<br>".
    "<br>Nombre: ". $DatosArray["Nombre"]. 
    "<br>Correo: ". $DatosArray["Email"].
    "<br>Teléfono: ". $DatosArray["Telefono"]."<br>". 
    "<br>Mensaje:". 
    "<br><strong>". $DatosArray["Mensaje"]."</strong><br>".
    "<br>-------".
    "<br>Este correo se generó automáticamente, favor de no responder.";  

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->IsHTML(true);
    $mail->CharSet    = 'UTF-8';
    $mail->Host       = 'outlook.office365.com';
    $mail->Port       = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth   = true;

    $mail->Username   = 'envios@agasys.com.mx';
    $mail->Password   = 'agasys2015.';
    $mail->SetFrom('envios@agasys.com.mx', 'FONELLI ATENCION A CLIENTES');
    $mail->addAddress(''.$correo.'', 'Receptor');
    //$mail->addCC('Sistemasuno@bzr.com.mx');

    $mail->Subject = ''.$asunto.'';
    $mail->Body    = ''.$mensaje.'';
    $mail->AltBody = 'Fonelli sitio web.';

    $resp = "ok";
    try {
      $mail->send();

    } catch(Exception $e) {
      $resp = 'Mailer Error - ' . $mail->ErrorInfo;
      http_response_code(500);
      echo json_encode(["Code" => K_API_FAILSEND, "Mensaje" => $resp]);
      //exit;
    }

    return $resp;

  }