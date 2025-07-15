<?php

/**
 * CalcPrec2025.php
 * Función que realiza las consultas y cálculos para obtener el precio
 * de venta de los artículos de PT de acuerdo al procedimiento de Proeli
 * -------
 * dRendon 14.07.2025
 */


function CalcPrecio($conn, $itemLinea, $itemCode, $listaPrecCode, $paridadTipo)
{
  $precioVenta    = 0.00;     // Valor que se va a devolver
  $listPrecMoneda = '';
  $listaPrecTipo  = '';       // 1=Directa 2=Componentes
  $tipoCosteo     = '';       // 1=Piezas 2=Gramos
  $costoCompra    = 0.00;     // valor de compra del artículo
  $valorAgregado  = 0.00;     // valor agregado en lista de precios por componentes
  $itemGramos     = 0.00;     // Peso promedio del metal se usa en formulación 4
  $piezasCosto    = 1;        // Se requiere para calcular precio en formulación 4
  $insumosGpo2    = 0.00;     // Se requiere para formulación 4

  $articulo       = array();  // resultado de la consulta local que devuelve el articulo


  # OJO: De forma incorrecta, Proeli repite el código de artículo, por ello
  # voy a forzar que solo se devuelva un valor con "DISTINCT ON"
  $sqlCmd = "SELECT DISTINCT ON (itm.c_lin,itm.c_clave)
    itm.c_lin,itm.c_clave,itm.c_coscom,
    itm.c_co1,itm.c_ca1,itm.c_gr1,
    itm.c_co2,itm.c_ca2,itm.c_gr2,
    itm.c_co3,itm.c_ca3,itm.c_gr3,
    itm.c_co4,itm.c_ca4,itm.c_gr4,
    itm.c_co5,itm.c_ca5,itm.c_gr5,
    itm.c_co6,itm.c_ca6,itm.c_gr6,
    itm.c_co7,itm.c_ca7,itm.c_gr7,
    itm.c_co8,itm.c_ca8,itm.c_gr8,
    itm.c_co9,itm.c_ca9,itm.c_gr9,
    itm.c_co10,itm.c_ca10,itm.c_gr10,
    itm.c_co11,itm.c_ca11,itm.c_gr11,
    itm.c_co12,itm.c_ca12,itm.c_gr12,
    itm.c_co13,itm.c_ca13,itm.c_gr13,
    itm.c_co14,itm.c_ca14,itm.c_gr14,
    itm.c_co15,itm.c_ca15,itm.c_gr15,
    COALESCE(SUBSTR(lpt.t_param,20,1),'') intext,
    COALESCE(SUBSTR(lpt.t_param,16,1),'') formulac,
    COALESCE(SUBSTR(lpr.t_param,2,1),'') tipolista,
    COALESCE(SUBSTR(lpr.t_param,8,1),'') moneda
    FROM inv010 itm
    LEFT JOIN var020 lpt ON lpt.t_tica='05' AND lpt.t_gpo=itm.c_lin
    LEFT JOIN var020 lpr ON lpr.t_tica='10' AND lpr.t_gpo='93' AND lpr.t_clave= :ListaPrecCode
    WHERE itm.c_lin = :ItemLinea AND itm.c_clave = :ItemCode
    ORDER BY itm.c_lin, itm.c_clave ";

  # Instrucción SELECT se definio anteriormente, en base al tipo de documento
  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":ItemLinea", $itemLinea, PDO::PARAM_STR);
  $oSQL->bindParam(":ItemCode", $itemCode, PDO::PARAM_STR);
  $oSQL->bindParam(":ListaPrecCode", $listaPrecCode, PDO::PARAM_STR);

  # Voy a utilizar fetch() en vez de fetchall() porque requiero solo un registro
  # OJO: Se obtiene un array de una dimension solamente
  $oSQL->execute();
  $numRows = $oSQL->rowCount();
  $articulo = $oSQL->fetch(PDO::FETCH_ASSOC);
  //var_dump($articulo);
  if ($articulo === false) {
    return $precioVenta;
  }

  $tipoCosteo = ($articulo["formulac"] == "2") ? "2" : "1";
  $costoCompra = $articulo["c_coscom"];
  $listaPrecMoneda = $articulo["moneda"];
  $listaPrecTipo = $articulo["tipolista"];

  /********************************************************************************** 
   * Si el tipo de lista es "1 - Directa," el precio se obtiene de la lista indicada
   * y no se debe ejecutar otro proceso de cálculo.
   */
  if ($listaPrecTipo == '1') {
    $sqlCmd = "SELECT c_lista,c_lin,c_clave,c_venta,c_costo,c_sku
          FROM lispre 
          WHERE c_lista = :ListaPrecCode AND c_lin = :ItemLinea AND c_clave = :ItemCode
          LIMIT 1";

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->bindParam(":ListaPrecCode", $listaPrecCode, PDO::PARAM_STR);
    $oSQL->bindParam(":ItemLinea", $itemLinea, PDO::PARAM_STR);
    $oSQL->bindParam(":ItemCode", $itemCode, PDO::PARAM_STR);
    $oSQL->execute();
    $result = $oSQL->fetch(PDO::FETCH_ASSOC);

    if ($oSQL->rowCount() < 1) {
      //throw new Exception("Artículo NO registrado en Lista de Precios $strListaPrecCode");
      return 0.00;
    }

    # asigna datos tomados de la lista de ventas
    $precioVenta = $result["c_venta"];
    $precioCosto = $result["c_costo"];

    # se supone que "return" ocasiona que se ejecute el código
    # dentro del bloque "finally"
    return $precioVenta;
  }   // Termina Lista de Precios Directa


  /**************************************************************************
   * Si la lista de precios es "2 - por componente", se llama la función para 
   * el cálculo de precio.
   */
  # Se obtiene el "valor agregado" de la lista de precios por componente
  $sqlCmd = "SELECT r_lista,r_linea,r_facimp FROM inv300
        WHERE r_lista = :ListaPrecCode AND r_linea= :ItemLinea ";

  $oSQL = $conn->prepare($sqlCmd);
  $oSQL->bindParam(":ListaPrecCode", $listaPrecCode, PDO::PARAM_STR);
  $oSQL->bindParam(":ItemLinea", $itemLinea, PDO::PARAM_STR);
  $oSQL->execute();
  $result = $oSQL->fetch(PDO::FETCH_ASSOC);

  if ($oSQL->rowCount() < 1) {
    //throw new Exception("Línea NO registrada en Lista de Precios $strListaPrecCode");
    return 0.00;
  }
  $valorAgregado = $result["r_facimp"];

  for ($i = 1; $i <= 15; $i++) {
    if (empty($articulo["c_co{$i}"])) {
      continue;
    }

    $codComp = str_pad($articulo["c_co{$i}"], 4, " ", STR_PAD_LEFT);
    $sqlCmd = <<<SQLTXT
          SELECT com.co_clave,com.co_descr,
          com.co_grupo,com.co_facoro,com.co_facmaq,
          com.co_l1,com.co_l1d,com.co_l1e,com.co_l1de,com.co_l1p,com.co_l1dp,
          com.co_venta,com.co_ventad,com.co_ventae,com.co_ventade,com.co_ventap,com.co_ventadp
          FROM compon com
          WHERE com.co_clave = '{$codComp}' 
          SQLTXT;

    $oSQL = $conn->prepare($sqlCmd);
    $oSQL->execute();
    $com = $oSQL->fetch(PDO::FETCH_ASSOC);

    if ($com["co_grupo"] == "0") {
      continue;
    }

    // El "peso promedio" considera solo el peso del metal
    if ($com["co_facoro"] <> 0) {
      $itemGramos = (float)$articulo["c_ca{$i}"];
    }

    // seleccionamos el campo correspondiente a la paridad indicada para el cliente
    switch ($paridadTipo) {
      case "E":
        $col1 = "co_l1e";       // Paridad Especial MN
        $venta = "co_ventae";
        break;
      case "P":
        $col1 = "co_l1p";       // Paridad Premium MN
        $venta = "co_ventap";
        break;
      default:
        $col1 = "co_l1";        // Paridad Normal MN
        $venta = "co_venta";
        break;
    }

    // Suma el costo "estándar" de los componentes según la formulación indicada
    switch ($articulo["formulac"]) {
      // Formulación 1 y 2 <-- se van sumando los componentes de mp        
      case "1":
      case "2":
        if ($tipoCosteo == '1') {
          $precioVenta += $com[$col1] * $articulo["c_ca{$i}"];
        } else {
          $precioVenta += $com[$col1] * $articulo["c_gr{$i}"];
        }
        break;

      // Formulacion 4 <-- se utiliza el precio de venta de los consumibles
      // Esta formulación requiere las piezas y peso que se están capturando en el 
      // documento de venta, pero en las COTIZACIONES no se permite editar el peso
      // y llega en "cero2, por lo que se va a usar el peso promedio en ese caso.
      case "4":
        $GramosCosto = $itemGramos;
        $PiezasCosto = $piezasCosto;    // minuscula, se definio al inicio de la función

        // Suma insumos Grupo 1 + valor agregado * precio costo
        if ($com["co_grupo"] == "1") {
          if ($tipoCosteo == "1") {
            if ($valorAgregado <> 0) {
              if ($PiezasCosto <> 0) {
                $precioVenta = $precioVenta + (($com[$col1] + $valorAgregado) * round($GramosCosto / $PiezasCosto, 2));
              } else {
                $precioVenta = $precioVenta + ($com[$col1] + $valorAgregado);
              }
            }
          } else {
            if ($valorAgregado <> 0) {
              if ($PiezasCosto <> 0) {
                $precioVenta = $precioVenta + (($com[$col1] + $valorAgregado) * round($GramosCosto / $PiezasCosto, 2));
              } else {
                $precioVenta = $precioVenta + ($com[$col1] + $valorAgregado);
              }
            }
          }
        }

        // Suma insumos Grupo 2 y 3 a precio de venta          
        if ($com["co_grupo"] == "2" || $com["co_grupo"] == "3") {
          if ($tipoCosteo == "1") {
            if ($com["co_grupo"] == "2") {
              $insumosGpo2 += ($com[$venta] * $articulo["c_ca{$i}"]);
            } else {
              $insumosGpo2 += ($com[$col1] * $articulo["c_ca{$i}"]);
            }
          } else {
            if ($com["co_grupo"] == "2") {
              $insumosGpo2 += ($com[$venta] * $articulo["c_gr{$i}"]);
            } else {
              $insumosGpo2 += ($com[$col1] * $articulo["c_gr{$i}"]);
            }
          }
        }
    } //switch ($articulo["formulac"])

    //var_dump($i, $com["co_clave"], $com["co_grupo"], $com["co_facoro"], $com["co_l1"], $com["co_l1d"], $com["co_venta"]);
  }

  // Aplica valor agregado según la formulación indicada y hace cálculos finales
  switch ($articulo["formulac"]) {
    // Formulación 1: La suma del resultado (piezas * costo) para los componentes 
    // del grupo 1-metal y 2-piedra se multiplica por el valor agregado
    case "1":
      if ($valorAgregado > 0) {
        $precioVenta = $precioVenta * (1 + $valorAgregado / 100);
      }
      $precioVenta = round($precioVenta);
      break;

    // Formulación 2: A la suma del resultado (piezas * costo) para los componentes
    // del grupo 1-metal y 2-piedra se le agrega el importe del valor agregado
    case "2":
      $precioVenta += $valorAgregado;
      $precioVenta = round($precioVenta, 2);
      break;

    // Formulación 4: Sumo el importe del "costo" de componentes mas el "precio de venta" de las piedras
    case "4":
      $precioVenta += $insumosGpo2;
      $precioVenta = round($precioVenta, 2);
      break;

    // Formulación 5: ---
    case "5":
      # Busca las paridades del DOLLAR ti_llave='3'
      $sqlCmdParidad = "SELECT ti_par,ti_pare,ti_partp,ti_parx 
          FROM inv100 WHERE ti_llave='3' ";
      $oSQLParidad = $conn->prepare($sqlCmdParidad);
      $oSQLParidad->execute();
      $filaParidad = $oSQLParidad->fetch(PDO::FETCH_ASSOC);
      if ($filaParidad === false) {
        return;
      }

      $ParidadNormal = (float)$filaParidad["ti_par"];
      $ParidadEspecial = (float)$filaParidad["ti_pare"];
      $ParidadPremium = (float)$filaParidad["ti_partp"];
      $ParidadCapturada = (float)$filaParidad["ti_parx"];

      if ($paridadTipo == "N") {
        $precioVenta = round($costoCompra * $ParidadNormal * (1 + $valorAgregado / 100), 0);
      } elseif ($paridadTipo == "E") {
        $precioVenta = round($costoCompra * $ParidadEspecial * (1 + $valorAgregado / 100), 0);
      } elseif ($paridadTipo == "P") {
        $precioVenta = round($costoCompra * $ParidadCapturada, 0);
      } else {
        $precioVenta = 0.00;
      }
  }

  return $precioVenta;
}
