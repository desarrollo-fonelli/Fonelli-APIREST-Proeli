<?php
@session_start();
header('Content-type: application/json');
date_default_timezone_set('America/Mexico_City');
$response = null; 
$mensaje = "";
$requestMethod = $_SERVER['REQUEST_METHOD'];

if($requestMethod !== "GET"){
    $mensaje = "No se admiten llamadas diferentes a GET";
    echo json_encode(["Code" => 0, "Mensaje" => $mensaje]);
    exit;
}

try {
        $url = "http://35.165.224.218:8089/categories";
      
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json",));
        $response = curl_exec($ch);
        echo $response;

} catch (Exception $e) {
    echo json_encode(["Code" => 0, "Mensaje" => $e->getMessage()]);
    exit;
  }
  

                                                                                                                                                                                                  
   


?>