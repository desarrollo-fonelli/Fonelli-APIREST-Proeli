<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

#Variable global que tiene la ruta del archico JSON donde se guardan las configuraciones de contenido del portal 
$file = 'C:\Rep_SourceSafe\Agasys\POS Dormimundo\POS\med_fonelli_apiportal\Datos\template.json';
$target_dir = "C:\Rep_SourceSafe\Fonelli\MED_FONELLI_Portal_2\src\assets\\"; //image upload folder name


#Se validan los metodos con los que se puede consumir este servicio solo se permite GET y POST
#El Get se ocupara cuando el portal cargue por primera vez
#POST se ocupará para actualizar los valores.

$requestMethod = $_SERVER['REQUEST_METHOD'];


#Se valida que el método de acceso sea de tipo GET
if ($requestMethod == "GET") {

    try
    {

        #Se obtienen los datos del archivo  y se regresan en formato JSON
        $template = obtener_datos_json($file);

        if(!isset($template))
        {
            #En caso de que no exista el archivo se envía el errpr
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            $mensaje = "No se encontro el archivo JSON"; 
            echo json_encode(["Codigo" => 1, "Mensaje" => $mensaje ]);
            exit;
        }
   
        #Mensaje de respuesta con los datos del archivo JSON
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        $mensaje="Datos correctos";
        echo  json_encode(["Codigo" => 0, "Mensaje" => $mensaje, "Contenido"=>$template ]);;
        exit;

    }
    catch (Exception $e) 
    {
        
        http_response_code(400);
        echo json_encode(["Codigo" => -1, "Mensaje" => $e->getMessage()]);
        exit;

    }
}
#Se valida que el método de acceso sea de tipo POST
elseif ($requestMethod == "POST")
{

    try
    {

            #Se obtienen los datos del archivo  y se regresan en formato JSON
            $template = obtener_datos_json($file);
            if(!isset($template))
            {
                #En caso de que no exista el archivo se envía el errpr
                http_response_code(405);
                header('Content-Type: application/json; charset=utf-8');
                $mensaje = "No se encontro el archivo JSON"; 
                echo json_encode(["Codigo" => 1, "Mensaje" => $mensaje ]);
                exit;
            }

            #Se valida que llamada traiga el parametro de DatosForm en caso contrario se manda error
            if(!isset($_POST["DatosForm"])){
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                $mensaje = "No se recibió el parámetro DatosForm"; 
                echo json_encode(["Codigo" => 3, "Mensaje" => $mensaje ]);
                exit;   
            } 

            #Se Obtiene el paramétro y se deserializa
            $DatosForm = $_POST["DatosForm"];
            $Datos= json_decode($DatosForm,true);

            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada
            if(isset($Datos["Video"])) {
                $template["Video"] = $Datos["Video"];

                if(isset($_FILES["image"])){
                    #Se recibe video y se guarda en ruta          
                    $target_file = $target_dir . basename($_FILES["image"]["name"]);
                    $target_file = $target_dir ."videos\intro\\" . basename($_FILES["image"]["name"]);
            
                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 4, "Mensaje" => "No se pudo cargar el video"]);
                        exit;
                    }
                }

            }

     
            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada
            if(isset($Datos["BannerPrincipal"])){

                $template["BannerPrincipal"] = $Datos["BannerPrincipal"];

                $Cantidad = count($Datos["BannerPrincipal"]);

                for($Contador = 1; $Contador <= $Cantidad; $Contador=$Contador+1 )
                {
          
                    $Imagen="imageDesk".$Contador;

                    if(isset($_FILES[$Imagen])){
                        #Se recibe imagen 1 y se guarda en la ruta de banner principal
                        $target_file = $target_dir . basename($_FILES[$Imagen]["name"]);
                        $target_file = $target_dir ."images\banner_principal\desk\\" . basename($_FILES[$Imagen]["name"]);
           
                        if (!move_uploaded_file($_FILES[$Imagen]["tmp_name"], $target_file)) {
                            http_response_code(400);
                            echo json_encode(["Codigo" => 5, "Mensaje" => "No se pudo cargar la imagen ".$Contador ]);
                            exit;
                        }
                    }

                    $Imagen="imageMovil".$Contador;

                    if(isset($_FILES[$Imagen])){
                       #Se recibe imagen 1 y se guarda en la ruta de banner principal
                       $target_file = $target_dir . basename($_FILES[$Imagen]["name"]);
                       $target_file = $target_dir ."images\banner_principal\movil\\" . basename($_FILES[$Imagen]["name"]);
  
                        if (!move_uploaded_file($_FILES[$Imagen]["tmp_name"], $target_file)) {
                            http_response_code(400);
                            echo json_encode(["Codigo" => 6, "Mensaje" => "No se pudo cargar la imagen movil".$Contador]);
                            exit;
                        }              
                    }

                }
            }

            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada
            if(isset($Datos["Gif"])){

                $template["Gif"] = $Datos["Gif"];

                if(isset($_FILES["image1"])){
                    #Se recibe imagen 1 y se guarda en la ruta de los gif
                    $target_file = $target_dir . basename($_FILES["image1"]["name"]);
                    $target_file = $target_dir ."images\puntos\\" . basename($_FILES["image1"]["name"]);

                    if (!move_uploaded_file($_FILES["image1"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 7, "Mensaje" => "No se pudo cargar la imagen puntos 1"]);
                        exit;
                    }
                }

 

                if(isset($_FILES["image2"])){
                    #Se recibe imagen 2 y se guarda en la ruta de los gif
                    $target_file = $target_dir . basename($_FILES["image2"]["name"]);
                    $target_file = $target_dir ."images\puntos\\" . basename($_FILES["image2"]["name"]);
                 
                    if (!move_uploaded_file($_FILES["image2"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 8, "Mensaje" => "No se pudo cargar la imagen puntos 2"]);
                        exit;
                    }
                }

                if(isset($_FILES["image3"])){
                    #Se recibe imagen 3 y se guarda en la ruta de los gif
                    $target_file = $target_dir . basename($_FILES["image3"]["name"]);
                    $target_file = $target_dir ."images\puntos\\" . basename($_FILES["image3"]["name"]);
                                    
                    if (!move_uploaded_file($_FILES["image3"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 9, "Mensaje" => "No se pudo cargar la imagen puntos 3"]);
                        exit;
                    } 
                }
            }

          

            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada        
            if(isset($Datos["Nosotros"])){
                $template["Nosotros"] = $Datos["Nosotros"];

                if(isset($_FILES["image1"])){
                    #Se recibe imagen 1 y se guarda en la ruta de nosotros
                    $target_file = $target_dir . basename($_FILES["image1"]["name"]);
                    $target_file = $target_dir ."images\Nosotros\\" . basename($_FILES["image1"]["name"]);
             
                    if (!move_uploaded_file($_FILES["image1"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 10, "Mensaje" => "No se pudo cargar la imagen nosotros 1"]);
                        exit;
                    }
                }



                if(isset($_FILES["image2"])){
                    #Se recibe imagen 2 y se guarda en la ruta de nosotros
                    $target_file = $target_dir . basename($_FILES["image2"]["name"]);
                    $target_file = $target_dir ."images\Nosotros\\" . basename($_FILES["image2"]["name"]);
                
                    if (!move_uploaded_file($_FILES["image2"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 11, "Mensaje" => "No se pudo cargar la imagen nosotros 2"]);
                        exit;
                    }
                }

                if(isset($_FILES["image3"])){
                    #Se recibe imagen 3 y se guarda en la ruta de nosotros
                    $target_file = $target_dir . basename($_FILES["image3"]["name"]);
                    $target_file = $target_dir ."images\Nosotros\\" . basename($_FILES["image3"]["name"]);
                              
                    if (!move_uploaded_file($_FILES["image3"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 12, "Mensaje" => "No se pudo cargar la imagen nosotros 3"]);
                        exit;
                    } 
                }

                if(isset($_FILES["image4"])){
                    #Se recibe imagen 4 y se guarda en la ruta de nosotros
                    $target_file = $target_dir . basename($_FILES["image4"]["name"]);
                    $target_file = $target_dir ."images\Nosotros\\" . basename($_FILES["image4"]["name"]);
                                   
                    if (!move_uploaded_file($_FILES["image4"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 13, "Mensaje" => "No se pudo cargar la imagen nosotros 4"]);
                        exit;
                    } 
                }


            }   


            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada
            if(isset($Datos["ImagenFinal"])){
                $template["ImagenFinal"] = $Datos["ImagenFinal"];

                if(isset($_FILES["image"])){
                    #Se recibe imagen 4 y se guarda en la ruta de fin pagina
                    $target_file = $target_dir . basename($_FILES["image"]["name"]);
                    $target_file = $target_dir ."images\\fin_pagina\\" . basename($_FILES["image"]["name"]);
                                              
                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        http_response_code(400);
                        echo json_encode(["Codigo" => 14, "Mensaje" => "No se pudo cargar la imagen final"]);
                        exit;
                    } 
                }

    

            }

         

            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada
            if(isset($Datos["BannerDistribuidores"])){
                $template["BannerDistribuidores"] = $Datos["BannerDistribuidores"];


                $Cantidad = count($Datos["BannerDistribuidores"]);

                for($Contador = 1; $Contador <= $Cantidad; $Contador=$Contador+1 )
                {
                    $Imagen="imageDesk".$Contador;

                    if(isset($_FILES[$Imagen])){
                        #Se recibe imagen 1 y se guarda en la ruta de banner principal
                        $target_file = $target_dir . basename($_FILES[$Imagen]["name"]);
                        $target_file = $target_dir ."images\banner_distribuidor\desk\\" . basename($_FILES[$Imagen]["name"]);

                        if (!move_uploaded_file($_FILES[$Imagen]["tmp_name"], $target_file)) {
                            http_response_code(400);
                            echo json_encode(["Codigo" => 15, "Mensaje" => "No se pudo cargar la imagen ".$Contador]);
                            exit;
                        }
                    }



                    $Imagen="imageMovil".$Contador;

                    if(isset($_FILES[$Imagen])){
                        #Se recibe imagen 1 y se guarda en la ruta de banner principal
                        $target_file = $target_dir . basename($_FILES[$Imagen]["name"]);
                        $target_file = $target_dir ."images\banner_distribuidor\movil\\" . basename($_FILES[$Imagen]["name"]);

                        if (!move_uploaded_file($_FILES[$Imagen]["tmp_name"], $target_file)) {
                            http_response_code(400);
                            echo json_encode(["Codigo" => 16, "Mensaje" => "No se pudo cargar la imagen movil ".$Contador]);
                            exit;
                        }
                    }


                }                
            }

            #Se valida si viene el nodo de video para actualizar los valores del archivo con los de la llamada
            if(isset($Datos["BannerAsesores"])){
                $template["BannerAsesores"] = $Datos["BannerAsesores"];

                $Cantidad = count($Datos["BannerAsesores"]);

                for($Contador = 1; $Contador <= $Cantidad; $Contador=$Contador+1 )
                {
                    $Imagen="imageDesk".$Contador;

                    if(isset($_FILES[$Imagen])){
                        #Se recibe imagen 1 y se guarda en la ruta de banner principal
                        $target_file = $target_dir . basename($_FILES[$Imagen]["name"]);
                        $target_file = $target_dir ."images\banner_asesor\desk\\" . basename($_FILES[$Imagen]["name"]);

                        if (!move_uploaded_file($_FILES[$Imagen]["tmp_name"], $target_file)) {
                            http_response_code(400);
                            echo json_encode(["Codigo" => 17, "Mensaje" => "No se pudo cargar la imagen ".$Contador]);
                            exit;
                    }

                    }


                    $Imagen="imageMovil".$Contador;

                    if(isset($_FILES[$Imagen])){
                        #Se recibe imagen 1 y se guarda en la ruta de banner principal
                        $target_file = $target_dir . basename($_FILES[$Imagen]["name"]);
                        $target_file = $target_dir ."images\banner_asesor\movil\\" . basename($_FILES[$Imagen]["name"]);
 
                        if (!move_uploaded_file($_FILES[$Imagen]["tmp_name"], $target_file)) {
                            http_response_code(400);
                            echo json_encode(["Codigo" => 18, "Mensaje" => "No se pudo cargar la imagen movil ".$Contador]);
                             exit;
                        }
                    }
                }  

            }

            #Se guardan los valores  actualizados en el archivo
            file_put_contents($file,json_encode($template));

            #Se responde que los datos se actualizaron de manera correcta
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            $mensaje = "Datos actualizados"; 
            echo json_encode(["Codigo" => 0, "Mensaje" => $mensaje ]);
    }

    catch (Exception $e) 
    {
        http_response_code(400);
        echo json_encode(["Codigo" => -1, "Mensaje" => $e->getMessage()]);
        exit;
    }
}
#Se manda error si el metodo de acceso no es uno permitido.
else
    {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        $mensaje = "Esta API solo acepta verbos GET y POST";   // quité K_SCRIPTNAME del mensaje
        echo json_encode(["Codigo" =>2, "Mensaje" => $mensaje ]);
        exit;
    }

/*
*Función para obtener los datos del template del archivo json
*/
function obtener_datos_json($file)
{
    if(file_exists($file))
    {
        $data = file_get_contents($file);
        $template = json_decode($data,true);
    }

    return $template;

}

?>