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