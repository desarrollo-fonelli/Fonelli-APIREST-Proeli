<?php
@session_start();

date_default_timezone_set('America/Mexico_City');

var_dump("Iniciando proceso: " . date("H:i:s"));

sleep(70);

var_dump("---------------------------");

die('Proceso terminado despue de sleep: ' . date("H:i:s"));
