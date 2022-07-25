<?php

/**
 * Códigos de Respuesta de la API
 */
  const K_API_OK          =  0;   // ok
  const K_API_NODATA      =  1;   // la consulta no encontro coincidencias
  const K_API_FAILAUTH    =  2;   // falla en la autenticacion de usuario
  const K_API_FAILVERB    =  3;   // verbo o acción no aceptada por el endpoint
  const K_API_ERRPARAM    =  4;   // error en parámetros recibidos
  const K_API_FAILSEND    =  5;   // error enviando correo electronico
  const K_API_ERRCONNEX   = -1;   // error en conexión a la base de datos
  const K_API_ERRSQL      = -2;   // error en consulta SQL
  
/**
 * Configuración
 */
  const K_FILASPORPAGINA  = 25;

/** 
 * Códigos de respuesta al intentar autenticación
 */
  const K_AUTH_OK         = "Usuario Autenticado";
  const K_AUTH_DATA_EMPTY = "Credenciales incompletas";
  const K_AUTH_PASSW_FAIL = "Password incorrecto";
  const K_AUTH_USER_INACT = "Usuario no esta Activo";
  const K_AUTH_USER_NOREG = "Usuario no registrado";
  