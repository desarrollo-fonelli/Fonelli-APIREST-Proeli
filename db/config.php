<?php

/**
 * Define datos de conexión a la base de datos.
 * Lo mantengo en un script independiente para que no se modifique
 * por error el script con la clase que realiza la conexión.
 *
 * @author Daniel Rendón
 * @date 17/may/2022
 */

return array(
  "driver"    => "pgsql",
  "host"      => "localhost",
  "puerto"    => "5432",
  "usuario"   => "postgres",
  "passw"     => "P0stgr3SQL",
  "dbname"    => "test",
  "schema"    => "dateli",
  "charset"   => "UTF8"
);
