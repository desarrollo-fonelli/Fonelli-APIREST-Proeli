<?php

/**
 * Códigos de Respuesta de la API
 */
  const K_API_OK          =  0;   // ok
  const K_API_NODATA      =  1;   // la consulta no encontro coincidencias
  const K_API_FAILAUTH    =  2;   // falla en la autenticacion de usuario
  const K_API_FAILVERB    =  3;   // verbo o acción no aceptada por el endpoint
  const K_API_ERRPARAM    =  4;   // error en parámetros recibidos
  const K_API_ERRCONNEX   = -1;   // error en conexión a la base de datos
  const K_API_ERRSQL      = -2;   // error en consulta SQL
  
/**
 * Configuración
 */
  const K_FILASPORPAGINA  = 25;

