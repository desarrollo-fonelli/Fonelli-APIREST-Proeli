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
  //"host"      => "localhost",
  //"usuario"   => "postgres",
  //"passw"     => "1234",   //"P0stgr3SQL",
  //"dbname"    => "test",

  "driver"  => "pgsql",
  "host"    => "35.165.224.218",
  "puerto"  => "5432",
  "usuario" => "fonelliaws",
  "passw"   => "fone1234",
  "dbname"  => "postgres",
  "schema"  => "dateli",
  "charset" => "UTF8"
);
