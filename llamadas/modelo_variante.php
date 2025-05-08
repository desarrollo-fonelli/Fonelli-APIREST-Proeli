<?php
@session_start();
error_reporting(0);
date_default_timezone_set('America/Mexico_City');
header('content-type: application/json; charset=utf-8');

$response = null; 
$mensaje = "";
$requestMethod = $_SERVER['REQUEST_METHOD'];

if($requestMethod !== "GET"){
    $mensaje = "No se admiten llamadas diferentes a GET";
    echo json_encode(["Code" => 0, "Mensaje" => $mensaje]);
    exit;
}

try {
  
  $dataObject = json_decode($_GET["data"]);
  $model = $dataObject->modelo;
  
        if(empty($model)){
          throw new Exception("El parametro 'modelo' no fue definido.");
        }

        $url = "http://35.165.224.218:8089/modelo-variante";
        $data = array("modelo" => $model);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        $response = curl_exec($ch);
        echo $response;
} catch (Exception $e) {
    echo json_encode(["Code" => 0, "Mensaje" => $e->getMessage()]);
    exit;
  }
  
?>