<?php
/**
 * En este script se concentran funciones comunes utilizadas por los
 * diferentes scripts de la aplicación
 */

/**
 * Valida la cadena recibida como parámetro, devolviendo true
 * si representa una fecha aceptada
 * 
 * El formato de la cadena recibida debe ser yyyy-mm-dd
 */
 FUNCTION ValidaFormatoFecha($fecha){

  $aaaa = "";
  $mm = "";
  $dd = "";
  
  $arrValores = explode('-', $fecha);
  
  if (count($arrValores) != 3){
    return false;
  }

  if (strlen($arrValores[0]) == 4){
    $aaaa = str_pad(trim($arrValores[0]), 4, "0", STR_PAD_LEFT);
  } else {
    return false;
  }

  if (strlen($arrValores[1]) == 2 || strlen($arrValores[1]) == 1){
    $mm = str_pad(trim($arrValores[1]), 2, "0", STR_PAD_LEFT);
  } else {
    return false;
  }

  if (strlen($arrValores[2]) == 2 || strlen($arrValores[2] == 1)){
    $dd = str_pad(trim($arrValores[2]), 2, "0", STR_PAD_LEFT);
  } else {
    return false;
  }
  
  // A la funcion checkdate() de PHP se le envían los parámetros mm, dd, yyyy
  if (checkdate($mm, $dd, $aaaa)){
    return true;
  } else {
    return false;
  }

 }

 /**
  * Valiida el Token del usuario que hace la peticion
  * dRendon 04.05.2023
  */
FUNCTION ValidaToken($conn, $TipoUsuario, $Usuario, $Token) {
 
  $sqlCmd = null;
  $oSQL = null;
  $numRows = null;
  $row = null;

  //  require_once "../db/conexion.php";

  // Hay que definir dinamicamente el schema <---------------------------------
  $sqlCmd = "SET SEARCH_PATH TO dateli;";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->execute();

  // Busca usuario en tabla "tokens" y obtiene la cadena registrada al loggearse
  $sqlCmd = "SELECT usr_tipo,usr_code,usr_token FROM tokens 
  WHERE usr_tipo = :usr_tipo AND usr_code = :usr_code";
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":usr_tipo", $TipoUsuario, PDO::PARAM_STR);
  $oSQL->bindParam(":usr_code", $Usuario, PDO::PARAM_STR);
  $oSQL->execute();
  $numRows = $oSQL->rowCount();

  if ($numRows > 0){
    $row = $oSQL->fetch(PDO::FETCH_ASSOC);
    $usr_token = TRIM($row["usr_token"]);
    
    if ($Token == $usr_token) {
      return true;
    } else {
      return false;
    }
  } else {
    return false;
  }

}