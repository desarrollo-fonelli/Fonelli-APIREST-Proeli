<?php

/**
 * RecalcPrecPedServicio.php
 * -----------------------------------------------------------------------------
 * Recupera los pedidos activos, calcula precio-venta, precio-costo y valor 
 * agregado para cada producto, usando las paridades, listas de precios y lista
 * de materiales que se tienen en la base de datos y al final actualiza las filas 
 * en la tabla de pedidos.
 * -----------------------------------------------------------------------------
 * dRendon | 05.03.2026 | Creación del script
 */

declare(strict_types=1);

set_time_limit(60 * 5);

date_default_timezone_set('America/Mexico_City');

//use PDO;
//use PDOException;
//use Exception;
require_once "../include/LoggerService.php";

class RecalcPrecPedServicio
{
  private PDO $conn;
  private LoggerService $logger;

  public function __construct(PDO $oDB)
  {
    # Se conecta a la base de datos
    $this->conn = $oDB;

    # Se conecta al servicio de logs
    $this->logger = new LoggerService();
    $this->logger->logInfo("Servicio RecalcPrecPedServicio iniciado");
  }

  public function updatePrecioPedidos(): array
  {

    try {

      $sqlCmd = "SET SEARCH_PATH TO dateli";
      $oSqlCmd = $this->conn->prepare($sqlCmd);
      $oSqlCmd->execute();

      # Obtiene pedidos activos
      # -----------------------------------------------
      $sqlCmd = "SELECT ped.pe_num,ped.pe_fil,ped.pe_ped,ped.pe_fepe,ped.pe_rengl,
      ped.pe_lin,ped.pe_clave,ped.pe_canpe,ped.pe_grape,ped.pe_prep,ped.pe_penep,ped.pe_costo,
      ped.pe_tipoie,ped.pe_ticos,ped.pe_tipoli,ped.pe_nulis,ped.pe_timon,ped.pe_par,
      ped.pe_preptm,ped.pe_penepat,ped.pe_costotm,
      SUBSTR(linpt.t_param,16,1) AS formulac, clt.cc_tparid AS paridad,clt.cc_nacext,
      SUBSTR(tfac.t_param,1,1) AS tipofac
      FROM ped100 ped
      LEFT JOIN var020 linpt ON linpt.t_tica='05' AND linpt.t_gpo=pe_lin
      LEFT JOIN var020 tfac ON tfac.t_tica='10' AND tfac.t_gpo='93' AND tfac.t_clave=ped.pe_tipoli
      LEFT JOIN cli010 clt ON clt.cc_num=ped.pe_num AND clt.cc_fil=ped.pe_fil
      WHERE ped.pe_status='A'  AND SUBSTR(linpt . t_param, 16, 1) = '4' AND pe_nulis='2'
      ORDER BY ped.pe_ped, ped.pe_rengl";
      //AND SUBSTR(tfac.t_param,1,1)='3'
      //AND SUBSTR(linpt.t_param,16,1) = '4' 
      //AND pe_tipoie='E' 
      //LIMIT 300";

      $oSqlCmd = $this->conn->prepare($sqlCmd);
      $oSqlCmd->execute();
      $pedFilas = $oSqlCmd->fetchAll(PDO::FETCH_ASSOC);

      $actualizados = 0;

      // $this->db->beginTransaction();
      $this->conn->beginTransaction();

      # TODO: Hay que actualizar todos los campos de precio, costo y valor agregado,
      #       también el tipo de cambio utilizado
      foreach ($pedFilas as $fila) {

        // ped.pe_tipoli = clave de lista de precios
        // ped.pe_nulis 1=Directa, 2=Componente
        // ped.pe_timon = 1=MXN, 2=ORO, 3=USD
        // ped.pe_par = tipo de cambio para la moneda
        // clte.cc_tparid N=Normal, E=Especial, P=Premium
        $result = $this->calcPrecItem(
          $this->conn,
          $fila['pe_lin'],
          $fila['pe_clave'],
          $fila['pe_canpe'],
          $fila['pe_grape'],
          $fila['pe_tipoli'],
          $fila['pe_nulis'],
          $fila['pe_timon'],
          $fila['formulac'],
          $fila['pe_ticos'],
          $fila['paridad'],
          $fila['tipofac'],
          $fila['pe_tipoie'],
          $fila['cc_nacext']
        );



        /*
        ## Actualiza el pedido con los precios calculados
        $sqlUpdate = "UPDATE ped100 SET pe_prep = :PrecioVenta, pe_costo = :PrecioCosto, pe_penep = :ValorAgregado, pe_preptm = :PrecioVentaTM, pe_costotm = :PrecioCostoTM
          WHERE pe_ped = :Pedido AND pe_rengl = :Renglon";
        $oSqlUpdate = $this->conn->prepare($sqlUpdate);
        $oSqlUpdate->bindParam(":PrecioVenta", $result['prec_venta'], PDO::PARAM_STR);
        $oSqlUpdate->bindParam(":PrecioCosto", $result['prec_costo'], PDO::PARAM_STR);
        $oSqlUpdate->bindParam(":ValorAgregado", $result['valor_agregado'], PDO::PARAM_STR);
        $oSqlUpdate->bindParam(":PrecioVentaTM", $result['prec_venta'], PDO::PARAM_STR);
        $oSqlUpdate->bindParam(":PrecioCostoTM", $result['prec_costo'], PDO::PARAM_STR);
        $oSqlUpdate->bindParam(":Pedido", $fila['pe_ped'], PDO::PARAM_STR);
        $oSqlUpdate->bindParam(":Renglon", $fila['pe_rengl'], PDO::PARAM_STR);
        $oSqlUpdate->execute();
*/


        $actualizados++;

        // registra en log cada item procesado
        $logMessage = implode('|', [
          $fila['pe_ped'],
          $fila['pe_num'] . '-' . $fila['pe_fil'],
          $fila['pe_tipoli'],
          $fila['paridad'],
          $fila['pe_lin'],
          $fila['pe_clave'],
          $fila['formulac'],
          $fila['pe_ticos'],
          $fila['tipofac'],
          $result['prec_venta'],
          $result['prec_costo'],
          $result['valor_agregado'],
          $fila['pe_canpe'],
          $fila['pe_grape']
        ]);

        $this->logger->logInfo($logMessage);
      }

      $this->conn->commit();

      $this->logger->logInfo("Fin: " . date('Y-m-d H:i:s') . "   rows_updated: " . $actualizados);
      return ["status" => "success", "rows_updated" => $actualizados];
      //
    } catch (Exception $e) {
      $this->logger->logError(
        "Error en updatePrecioPedidos: ",
        ["exception" => $e, "Ped/Row/PT" => $fila['pe_ped'] . "/" . $fila['pe_rengl'] . " - " . $fila['pe_lin'] . "-" . $fila['pe_clave']]
      );

      $this->conn->rollBack();
      throw $e;
    }
  }

  /**
   * calcPrecItem
   * ---------------------------------------------------------------------------
   * Calcula el precio de venta, precio de costo y valor agregado para un producto
   *
   * @param PDO $conn             Conexión a la base de datos
   * @param string $itemLin
   * @param string $itemClave
   * @param integer $itemCanpe    Piezas pedidas
   * @param float $itemGrape      Gramos correspondientes a las piezas pedidas
   * @param string $itemTipoli    Clave de Lista de Precios
   * @param integer $itemNulis    1=Directa, 2=Componente
   * @param integer $itemTimon    1=MXN, 2=ORO, 3=USD
   * @param string $formulac      1,2,3,4,5
   * @param string $tipoCosteo    1=Piezas, 2=Gramo
   * @param string $paridad       N=Normal, E=Especial, P=Premium
   * @param string $tipofac       1=MN, 2=USD, 3=Maquila
   * @param string $tipoie        I=Interno, E=Externo
   * @param string $nacExt        N= Nacional, E=Extranjero
   * @return array
   */
  private function calcPrecItem(
    $conn,
    $itemLin = "",
    $itemClave = "",
    $itemCanpe = 0,
    $itemGrape = 0.00,
    $itemTipoli = "",
    $itemNulis = 0,
    $itemTimon = 0,
    $formulac = "",
    $tipoCosteo = "",
    $paridad = "",
    $tipofac = "",
    $tipoie = "",
    $nacExt = ""
  ): array {

    $prec_venta = 0.00;     // precio de venta calculado, valor devuelto por la función
    $prec_costo = 0.00;     // costo directo calculado, valor devuelto por la función 
    $precio = 0.00;         // variable local para calculos "intermedios"
    $costo = 0.00;          // variable local para calculos "intermedios"

    $costoCompra = 0.00;    // variable local para el costo de compra artículo PT
    $insumosGpo2 = 0.00;    // variable local 

    try {

      /**
       * A partir de ene-2025, se va a calcular el costo por componente para usarlo en 
       * todos los casos, sin importar si la lista es Directa o Por Componente
       */

      # Obtiene los componentes de MP del articulo de PT para calcular su costo
      # -----------------------------------------------------------------------
      # OJO: De forma incorrecta, Proeli repite el código de artículo, por ello
      # voy a forzar que solo se devuelva un valor con "DISTINCT ON"
      # Actualizacion -> por rendimiento, es mejor usar LIMIT 1
      // $sqlCmd = "SELECT DISTINCT ON (itm.c_lin,itm.c_clave)
      $sqlCmd = "SELECT itm.c_lin,itm.c_clave,
            itm.c_coscom,
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
            SUBSTR(linpt.t_param,9,1) AS resta_peso_piedra
          FROM inv010 itm
          LEFT JOIN var020 linpt ON linpt.t_tica='05' AND linpt.t_gpo=itm.c_lin
          WHERE itm.c_lin = :ItemLinea AND itm.c_clave = :ItemCode 
          LIMIT 1";

      $oSQL = $conn->prepare($sqlCmd);
      $oSQL->bindParam(":ItemLinea", $itemLin, PDO::PARAM_STR);
      $oSQL->bindParam(":ItemCode", $itemClave, PDO::PARAM_STR);
      $oSQL->execute();
      $itm = $oSQL->fetch(PDO::FETCH_ASSOC);

      if ($oSQL->rowCount() < 1) {
        throw new Exception("Artículo {$itemLin}-{$itemClave} NO registrado");
      }

      # El costo de compra y los gramos se utilizan para la formulación 5
      # ----------------------------------------------------------------
      $costoCompra = $itm["c_coscom"];
      $itemGramos = 0.00;

      # -------------------------------------------------------------------------------------------
      # Calcula el costo sumando los componentes de la lista de materiales
      # aplicando la paridad asignada al cliente y la formulación correspondiente al artículo de PT
      # -------------------------------------------------------------------------------------------
      // nacExt : CC_NACEXT N=Cliente Nacional, E=Cliente Extranjero
      // el cálculo de costo es el mismo para nacionales y extranjeros

      for ($i = 1; $i <= 15; $i++) {
        if (empty($itm["c_co{$i}"])) {
          continue;
        }

        $codComp = str_pad((string)$itm["c_co{$i}"], 4, " ", STR_PAD_LEFT);
        $sqlCmd = <<<SQLTXT
          SELECT com.co_clave,com.co_descr,
          com.co_grupo,com.co_facoro,com.co_facmaq,
          com.co_l1,com.co_l1d,com.co_l1e,com.co_l1de,
          com.co_l1p,com.co_l1dp,
          com.co_l1c,com.co_l1dc,
          com.co_venta,com.co_ventad,
          com.co_ventae,com.co_ventade,
          com.co_ventap,com.co_ventadp
          FROM compon com
          WHERE com.co_clave = '{$codComp}' 
          SQLTXT;

        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->execute();
        $com = $oSQL->fetch(PDO::FETCH_ASSOC);

        if (! $com) {
          // no encontró el componente
          continue;
        }

        if ($com["co_grupo"] == "0") {
          // Omite 0 = insumos "genericos" y mano de obra (no metal, no piedra)
          continue;
        }

        // El "peso promedio" considera solo el peso del metal
        if ($com["co_facoro"] <> 0) {
          $itemGramos = (float)$itm["c_ca{$i}"];
        }

        if ($com["co_grupo"] == "1" && $itm["resta_peso_piedra"] == "1" && $formulac == "4") {
          # Si el componente es del grupo 1 (metal) y la formulación es la 4, se resta el peso de la piedra
          # para evitar que el costo de la piedra se sume al costo del metal, ya que en esta formulación se utiliza el precio de venta de las piedras.
          # drendon: yo no veo donde se resta el peso de la piedra
          if ($itemCanpe <> 0) {
            if ($itemTimon == 1) {
              // Moneda nacional
              $costo +=  round($com["co_l1c"] * round($itemGrape / $itemCanpe, 2), 2);
            } else {
              // 3=USD, Proeli no maneja otras opciones
              $costo +=  round($com["co_l1dc"] * round($itemGrape / $itemCanpe, 2), 2);
            }
          }
          // else {
          // en proeli se aplica (COM->CO_L1C*ROUND(0,2)) lo cual resulta en cero
          //}

          // Si se procesó este caso, salta al siguiente componente
          continue;
        }

        if ($tipoCosteo == "1") {
          # costo por pieza

          if ($tipofac == "3") {
            // En maquila siempre se multiplica usando las piezas de la formulación, no las del pedido
            $costo += round($com["co_l1c"] * (float)$itm["c_ca{$i}"], 2);
          } else {
            if ($com["co_grupo"] == "1") {
              // metal - se dividen gramos promedio entre piezas porque el costo es unitario
              if ($itemTimon == 1) {
                // Moneda nacional
                $costo += round($com["co_l1c"] * round($itemGrape / $itemCanpe, 2), 2);
              } else {
                // 3=USD, Proeli no maneja otras opciones
                $costo += round($com["co_l1dc"] * round($itemGrape / $itemCanpe, 2), 2);
              }
            } else {
              // piedra o insumo grupo 2 o 3
              // unidades definidas en la especificacion
              if ($itemTimon == 1) {
                // Moneda nacional
                $costo += round($com["co_l1c"] * (float)$itm["c_ca{$i}"], 2);
              } else {
                $costo += round($com["co_l1dc"] * (float)$itm["c_ca{$i}"], 2);
              }
            }
          }
        } else {
          // $tipoCosteo == "2" (costo por gramo)

          if ($tipofac == "3") {
            // En maquila siempre se multiplica usando las piezas de la formulación, no las del pedido
            $costo += $com["co_l1c"] * (float)$itm["c_gr{$i}"];
          } else {
            if ($com["co_grupo"] == "1") {
              if ($itemTimon == 1) {
                $costo += $com["co_l1c"];
                // en proelis se aplica (COM->CO_L1C*ROUND(mCANP/mCANP,2)) pero el cociente es = 1
              } else {
                // 3=USD, Proeli no maneja otras opciones
                $costo += $com["co_l1dc"];
              }
            } else {
              if ($itemTimon == 1) {
                // moneda nacional
                $costo += $com["co_l1c"] * (float)$itm["c_gr{$i}"];
              } else {
                // 3=USD, Proeli no maneja otras opciones
                $costo += $com["co_l1dc"] * (float)$itm["c_gr{$i}"];
              }
            }
          }
        }
      }

      # Cuando la formulación es diferente a "5" se devuelve el costo directo calculado anteriormente.. 
      # Cuando es "5" se devuelve el precio de compra.
      $ParidadNormal = 0.00;
      $ParidadEspecial = 0.00;
      $ParidadPremium = 0.00;
      $ParidadCapturada = 0.00;

      if ($formulac <> "5") {
        // en el proceso de cálculo ya se consideró la moneda
        $prec_costo = round($costo, 2);
      } else {
        # Formulac = 5

        # Busca las paridades del DOLLAR ti_llave='3'
        $sqlCmdParidad = "SELECT ti_par,ti_pare,ti_partp,ti_parx 
              FROM inv100 WHERE ti_llave='3' ";
        $oSQLParidad = $conn->prepare($sqlCmdParidad);
        $oSQLParidad->execute();
        $filaParidad = $oSQLParidad->fetch(PDO::FETCH_ASSOC);

        if ($filaParidad) {
          $ParidadNormal = (float)$filaParidad["ti_par"];
          $ParidadEspecial = (float)$filaParidad["ti_pare"];
          $ParidadPremium = (float)$filaParidad["ti_partp"];
          $ParidadCapturada = (float)$filaParidad["ti_parx"];
        }

        if ($itemTimon == 1) {
          // Moneda nacional
          $prec_costo = round($costoCompra * $ParidadCapturada, 2);
        } else {
          // 3=USD, Proeli no maneja otras opciones
          // No es necesario modificar el costo de compra
          $prec_costo = round($costoCompra, 2);
        }
      }

      # -----------------------------------------------------------------------------------
      # Despues de calcular el costo directo, calculamos el precio de venta
      # -----------------------------------------------------------------------------------

      /**
       * Si el tipo de lista es "1 - Directa," el precio se obtiene de la lista indicada
       * y no se debe ejecutar otro proceso de cálculo.
       * Si la lista de precios es "2 - por componente", el cálculo de precio
       * se hace según la formulación  y el tipo de paridad.
       * -------------------------------------------------------------------------------
       */

      // Lista de Precios Directa
      if ($itemNulis == '1') {

        $sqlCmd = "SELECT c_lista,c_lin,c_clave,c_venta,c_costo,c_sku
          FROM lispre 
          WHERE c_lista = :ListaPrecCode AND c_lin = :ItemLinea AND c_clave = :ItemCode
          LIMIT 1";

        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->bindParam(":ListaPrecCode", $itemTipoli, PDO::PARAM_STR);
        $oSQL->bindParam(":ItemLinea", $itemLin, PDO::PARAM_STR);
        $oSQL->bindParam(":ItemCode", $itemClave, PDO::PARAM_STR);
        $oSQL->execute();
        $result = $oSQL->fetch(PDO::FETCH_ASSOC);

        if ($oSQL->rowCount() < 1) {
          throw new Exception("Artículo NO registrado en Lista de Precios $itemTipoli");
        }

        # asigna datos tomados de la lista de ventas
        # ------------------------------------------
        $prec_venta = $result["c_venta"];

        // Fin lista de precios directa

      } elseif ($itemNulis == '2') {  # Lista de Precios por Componente
        # Lista de precios por componente

        // seleccionamos el campo correspondiente a la paridad indicada para el cliente
        // OJO: cuando la lista de precios es de maquila, se debe aplicar la "paridad maquila".
        //      ($tipofac : 1=MN 2=USD 3=MAQUILA)
        $venta = "";
        $col1 = "";
        if ($tipofac == '3') {
          if ($itemTimon == 1) {
            $col1 = "co_l1grc";         // "Paridad" maquila
          } else {
            $col1 = "co_l1dgrc";        // "Paridad" maquila USD
          }
          switch ($paridad) {
            case "E":
              if ($itemTimon == 1) {
                $venta = "co_ventae";
              } else {
                $venta = "co_ventade";
              }
              break;
            case "P":
              if ($itemTimon == 1) {
                $venta = "co_ventap";
              } else {
                $venta = "co_ventadp";
              }
              break;
            default:
              if ($itemTimon == 1) {
                $venta = "co_venta";
              } else {
                $venta = "co_ventad";
              }
              break;
          }
        } else {
          switch ($paridad) {
            case "E":
              if ($itemTimon == 1) {
                $col1 = "co_l1e";       // Paridad Especial MN
                $venta = "co_ventae";
              } else {
                $col1 = "co_l1de";      // Paridad Especial USD
                $venta = "co_ventade";
              }
              break;
            case "P":
              if ($itemTimon == 1) {
                $col1 = "co_l1p";       // Paridad Premium MN
                $venta = "co_ventap";
              } else {
                $col1 = "co_l1dp";      // Paridad Premium USD
                $venta = "co_ventadp";
              }
              break;
            default:
              if ($itemTimon == 1) {
                $col1 = "co_l1";        // Paridad Normal MN
                $venta = "co_venta";
              } else {
                $col1 = "co_l1d";       // Paridad Normal USD
                $venta = "co_ventad";
              }
              break;
          }
        }

        # Se obtiene el "valor agregado" de la lista de precios por componente
        # --------------------------------------------------------------------
        $sqlCmd = "SELECT r_lista,r_linea,r_facimp FROM inv300
        WHERE r_lista = :ListaPrecCode AND r_linea= :ItemLinea ";

        $oSQL = $conn->prepare($sqlCmd);
        $oSQL->bindParam(":ListaPrecCode", $itemTipoli, PDO::PARAM_STR);
        $oSQL->bindParam(":ItemLinea", $itemLin, PDO::PARAM_STR);
        $oSQL->execute();
        $result = $oSQL->fetch(PDO::FETCH_ASSOC);

        if ($oSQL->rowCount() < 1) {
          throw new Exception("Línea NO registrada en Lista de Precios $itemTipoli");
        }
        $valorAgregado = $result["r_facimp"];

        /*****************************************************************************
         * Suma el costo "estándar" de los componentes según la formulación indicada.
         * Previamente se ha tomado en cuenta la moneda. Ya deben existir $itm y $com
         * --------------------------------------------------------------------------
         */
        for ($i = 1; $i <= 15; $i++) {

          if (empty($itm["c_co{$i}"])) {
            continue;
          }

          $codComp = str_pad((string)$itm["c_co{$i}"], 4, " ", STR_PAD_LEFT);
          $sqlCmd = <<<SQLTXT
            SELECT com.co_clave,com.co_descr,
            com.co_grupo,com.co_facoro,com.co_facmaq,
            com.co_l1,com.co_l1d,com.co_l1e,com.co_l1de,
            com.co_l1p,com.co_l1dp,
            co_l1c,co_l1dc,
            co_l1grc,co_l1dgrc,
            com.co_venta,com.co_ventad,
            com.co_ventae,com.co_ventade,
            com.co_ventap,com.co_ventadp
            FROM compon com
            WHERE com.co_clave = '{$codComp}' 
            SQLTXT;

          $oSQL = $conn->prepare($sqlCmd);
          $oSQL->execute();
          $com = $oSQL->fetch(PDO::FETCH_ASSOC);

          if (! $com) {
            // no encontró el componente
            continue;
          }

          if ($com["co_grupo"] == "0") {
            // Omite 0 = insumos "genericos" y mano de obra (no metal, no piedra)
            continue;
          }

          // El "peso promedio" considera solo el peso del metal
          if ($com["co_facoro"] <> 0) {
            $itemGramos = (float)$itm["c_ca{$i}"];
          }

          /**
           * Proeli calcula diferente cuando la lista de precios es "maquila" -> $tipofac='3' 
           * por eso se agrega la condición "if" correspondiente.
           * --------------------------------------------------------------------------------
           */
          if ($tipofac == '3') { # Lista de precios "Maquila"
            switch ($formulac) {
              case "1":
                if ($tipoCosteo == '1') {
                  $precio += $com[$col1] * $itm["c_ca{$i}"];
                } else {
                  $precio += $com[$col1] * $itm["c_gr{$i}"];
                }
                break;
              case "2":
                if ($com["co_grupo"] <> '1') {
                  continue 2; // el "2" es necesario para que el "continue" aplique al "switch" y no solo al "case"
                }

                if ($tipoCosteo == '1') {
                  $precio += $com[$col1] * $itm["c_ca{$i}"];
                } else {
                  $precio += $com[$col1] * $itm["c_gr{$i}"];
                }
                break;
              case "3":
                // Actualmente no se está usando la Formulación 3 en Proeli
                break;

              case "4":

                if ($valorAgregado <> 0) {
                  if ($com["co_grupo"] == "1") {
                    # Suma insumos Grupo 1 + valor agregado * precio costo
                    # Nota drendon: en proeli se calcula igual para costeo por pieza y por gramo
                    if ($itemCanpe <> 0) {
                      $prec_venta += (($com[$col1] + $valorAgregado) * round($itemGrape / $itemCanpe, 2));
                    } else {
                      $prec_venta += $prec_venta;
                    }
                  } elseif ($com["co_grupo"] == "2") {
                    # Suma insumos Grupo 2 a precio de venta
                    if ($tipoCosteo == '1') {
                      $prec_venta += round($com[$venta] * $itm["c_ca{$i}"], 2);
                    } else {
                      $prec_venta += round($com[$venta] * $itm["c_gr{$i}"], 2);
                    }
                  } else {
                    continue 2; // el "2" es necesario para que el "continue" aplique al "switch" y no solo al "case" 
                  }
                }

                break;
            }
          } else {  # Lista de precios <> "Maquila"
            switch ($formulac) {
              // Formulación 1 y 2 <-- se van sumando los componentes de mp        
              case "1":
              case "2":
                if ($tipoCosteo == '1') {
                  $precio += $com[$col1] * $itm["c_ca{$i}"];
                } else {
                  $precio += $com[$col1] * $itm["c_gr{$i}"];
                }
                break;

              case "3":
                // Formulación 3 NO se está utilizando en Proeli
                break;

              case "4":
                // Formulacion 4 <-- se utiliza el precio de venta de los consumibles
                // Esta formulación requiere las piezas y peso que se están capturando en el 
                // documento de venta, pero en las COTIZACIONES no se permite editar el peso
                // y llega en "cero2, por lo que se va a usar el peso promedio en ese caso.
                if ($itemGrape == 0) {
                  $itemGrape = (float)($itemGramos * $itemCanpe);
                }

                // Suma insumos Grupo 1 + valor agregado * precio costo
                if ($com["co_grupo"] == "1") {
                  if ($tipoCosteo == "1") {
                    if ($valorAgregado <> 0) {
                      if ($itemCanpe <> 0) {
                        $prec_venta = $prec_venta + (($com[$col1] + $valorAgregado) * round($itemGrape / $itemCanpe, 2));
                      } else {
                        $prec_venta = $prec_venta + ($com[$col1] + $valorAgregado);
                      }
                    }
                  } else {
                    if ($valorAgregado <> 0) {
                      if ($itemCanpe <> 0) {
                        $prec_venta = $prec_venta + (($com[$col1] + $valorAgregado) * round($itemGrape / $itemCanpe, 2));
                      } else {
                        $prec_venta = $prec_venta + ($com[$col1] + $valorAgregado);
                      }
                    }
                  }
                }

                // Suma insumos Grupo 2 y 3 a precio de venta          
                if ($com["co_grupo"] == "2" || $com["co_grupo"] == "3") {
                  if ($tipoCosteo == "1") {
                    if ($com["co_grupo"] == "2") {
                      $insumosGpo2 += ($com[$venta] * $itm["c_ca{$i}"]);
                    } else {
                      $insumosGpo2 += ($com[$col1] * $itm["c_ca{$i}"]);
                    }
                  } else {
                    if ($com["co_grupo"] == "2") {
                      $insumosGpo2 += ($com[$venta] * $itm["c_gr{$i}"]);
                    } else {
                      $insumosGpo2 += ($com[$col1] * $itm["c_gr{$i}"]);
                    }
                  }
                }
            } //switch ($arrData["formulac"])
          }
        } // fin for ($i = 1; $i <= 15; $i++)

        # Aplica valor agregado según la formulación indicada y hace cálculos finales
        # ---------------------------------------------------------------------------
        switch ($formulac) {
          case "1":
            // Formulación 1: La suma del resultado (piezas * costo) para los componentes 
            // del grupo 1-metal y 2-piedra se multiplica por el valor agregado
            $precio = $precio * (1 + $valorAgregado / 100);
            $prec_venta = round($precio, 0);
            break;

          case "2":
            // Formulación 2: A la suma del resultado (piezas * costo) para los componentes
            // del grupo 1-metal y 2-piedra se le agrega el importe del valor agregado
            $precio += $valorAgregado;
            $prec_venta = round($precio, 2);
            break;

          // Formulación 4: Sumo el importe del "costo" de componentes mas el "precio de venta" de las piedras
          case "4":
            $prec_venta += $insumosGpo2;
            $prec_venta = round($prec_venta, 2);
            break;

          // Formulación 5: ---
          case "5":
            // se debe comprobar la existencia de paridades. La consulta SQL está 
            // al inicio del proceso
            if ($filaParidad === false) {
              $prec_venta = round($costoCompra, 0);    // valor arbitrario drendon
              break;
            }

            if ($paridad == "N") {
              $prec_venta = round($costoCompra * $ParidadNormal * (1 + $valorAgregado / 100), 0);
            } elseif ($paridad == "E") {
              $prec_venta = round($costoCompra * $ParidadEspecial * (1 + $valorAgregado / 100), 0);
            } elseif ($paridad == "P") {
              $prec_venta = round($costoCompra * $ParidadCapturada, 0);
            } else {
              $prec_venta = 0.00;
            }
        }
        // Fin Tipo de lista de precios por componente
      }
    } catch (Exception $e) {
      throw $e;
      // http_response_code(503);  // Service Unavailable
      // $response = ["Codigo" => K_API_ERRCONNEX, "Mensaje" => $e->getMessage(), "Contenido" => []];
      // echo json_encode($response);
      // exit;
    }

    # Agrego esta corrección para controlar los valores de retorno,
    # porque Proeli no tiene integridad referencial
    # y tiene un comportamiento incosistente con registros borrados.
    if (($prec_venta - $prec_costo) < 0 || $prec_costo == 0) {
      $prec_costo = 0.00;
      $valorAgregado = 0.00;
    } else {
      $valorAgregado = round($prec_venta - $prec_costo, 2);
    }

    return [
      "prec_venta" => $prec_venta,
      "prec_costo" => $prec_costo,
      "valor_agregado" => $valorAgregado
    ];
  }
}
