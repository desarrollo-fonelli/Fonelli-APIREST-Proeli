<?php

@session_start();

/**
 * Clase para conectarse a la base de datos y crear PDO
 */

class DB
{
  # ¿¿¿ES NECESARIO DECLARARLA ESTATICA???
  protected static $conn;

  private $driver, $host, $puerto, $usuario, $passw, $dbname, $charset ;

  public $schema;

  private function __construct()
  {

    $arrConfig = require_once "config.php";

    $this->driver  = $arrConfig["driver"];
    $this->host    = $arrConfig["host"];
    $this->puerto  = $arrConfig["puerto"];
    $this->usuario = $arrConfig["usuario"];
    $this->passw   = $arrConfig["passw"];
    $this->dbname  = $arrConfig["dbname"];
    $this->schema  = $arrConfig["schema"];
    $this->charset = $arrConfig["charset"];

    try {

      # dRendon: conexión específica para PostgreSQL:
      # self::$conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $passw);
      self::$conn = new PDO(
        "pgsql:host=$this->host;port=$this->puerto;dbname=$this->dbname;
        options='--client_encoding=$this->charset'",
        $this->usuario,
        $this->passw
      );
      self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$conn->setAttribute(PDO::ATTR_PERSISTENT, false);

    } catch (PDOException $e) {
      
      http_response_code(503);  // Service Unavailable

      //echo $e->getMessage();     
      //$response = json_encode(["Codigo" => "999", "Mensaje" => $e->getMessage()]);
      // dRendon 19.05.2022 
      // Para mejorar esta seccion, revisar 
      // https://www.ibm.com/docs/es/db2/11.1?topic=pdo-handling-db2-errors-warning-messages
      // https://www.php.net/manual/en/pdo.errorinfo.php

      $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => "error de conexion a la base de datos", "Contenido" => []];
      echo json_encode($response);
      exit;   

    }
    
  }

  public static function getConn()
  {
    if (!self::$conn) {
      new DB();
    }
    return self::$conn;
  }

}

/**
 * Establece la conexion
 */
$conn = DB::getConn();
