<?php

function CalcNormalNulis2MNtipoF1()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  global $NormalEquivalente, $TipoParidad;
  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO1) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR1);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO2) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA2);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR2);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO3) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA3);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR3);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO4) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA4);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR4);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO5) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA5);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR5);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO6) <> "") {
    seekCompoTipoF1($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA6);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR6);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO7) <> "") {
    seekCompoTipoF1($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA7);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR7);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO8) <> "") {
    seekCompoTipoF1($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA8);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR8);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO9) <> "") {
    seekCompoTipoF1($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA9);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR9);
      }
    }
  }
}

function CalcEspecNulis2MNtipoF1()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  global $NormalEquivalente, $TipoParidad;
  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO1) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR1);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO2) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA2);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR2);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO3) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA3);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR3);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO4) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA4);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR4);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO5) <> "") {
    $rowCOM = seekCompoTipoF1($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA5);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR5);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO6) <> "") {
    seekCompoTipoF1($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA6);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR6);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO7) <> "") {
    seekCompoTipoF1($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA7);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR7);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO8) <> "") {
    seekCompoTipoF1($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA8);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR8);
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  unset($rowCOM);
  if (trim($C_CO9) <> "") {
    seekCompoTipoF1($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA9);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR9);
      }
    }
  }
}

function sumaInsumosGpo1()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  global $NormalEquivalente, $TipoParidad;
  #$NormalEquivalente="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO1 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA1);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR1);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA1);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR1);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA1);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR1);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA1);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR1);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA2);
          $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR2);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA2);
          $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR2);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA2);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR2);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA2);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR2);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA3);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR3);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA3);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR3);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA3);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR3);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA3);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR3);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA4);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR4);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA4);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR4);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA4);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR4);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA4);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR4);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA5);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR5);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA5);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR5);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA5);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR5);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA5);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR5);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA6);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR6);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA6);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR6);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA6);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR6);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA6);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR6);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA7);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR7);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA7);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR7);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA7);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR7);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA7);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR7);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO8 <> 0) {
    $rowCOM->seekCompoTipoGpo1($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA8);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR8);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA8);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR8);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA8);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR8);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA8);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR8);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_grupo"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }
        if ($TipoParidad == "N") {
          $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA9);
          $TPG = $TPG + ($rowCOM["co_l1"] * $C_GR9);
        } else {
          $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA9);
          $TPG = $TPG + ($rowCOM["co_l1e"] * $C_GR9);
        }
        if ($NormalEquivalente == "E") {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1e ni C_GR1e
          #como es de esperarse
          if ($TipoParidad == "N") {
            $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA9);
            $TPGE = $TPGE + ($rowCOM["co_l1"] * $C_GR9);
          } else {
            $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA9);
            $TPGE = $TPGE + ($rowCOM["co_l1e"] * $C_GR9);
          }
        }
      }
    }
  }
}

function CalcNormalNulis2MNtipoF2()
{

  echo "Entrando a CalcNormalNulis2MNtipoF2()";

  $W_FACORO = 0;
  $W_FACMAQ = 0;

  echo "C_CO1=" . $C_CO1;
  #unset( $rowCOM );
  if (trim($C_CO1) <> "") {
    seekCompoTipoF2($C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_GRUPO"] <> "0") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["CO_FACORO"];
        }
        if ($rowCOM["CO_FACMAQ"] <> 0) {
          $W_FACMAQ = $rowCOM["CO_FACMAQ"];
        }

        $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR1);
      }
    }
  }
}


#Busca la clave de componente en la tabla COMPON (m.p.) y devuelve
#el registro en caso de encontrarla
# 03/may/2022 Se agrega la linea de componentes en busquedas
function seekCompoTipoF1($CO_LIN, $CO_CLAVE)
{
  $con = DB::getConn();
  unset($rowCOM); 

  $sqlCmd = "SELECT * FROM compon WHERE co_lin = :lin AND trim(co_clave)= trim(:clave) AND co_grupo <> '0' ";
  $oCOM = $con->prepare($sqlCmd);
  $oCOM->bindParam(":lin", $CO_LIN, PDO::PARAM_STR);
  $oCOM->bindParam(":clave", $CO_CLAVE, PDO::PARAM_STR);
  $oCOM->execute();
  $numResCOM = $oCOM->rowCount();
  if ($numResCOM > 0) {
    $rowCOM = $oCOM->fetch(PDO::FETCH_ASSOC);
    return $rowCOM;
  } else {
    return null;
  }
}

function seekCompoTipoGpo1($lin, $clave)
{

  //global $con;
  $con = DB::getConn();

  $sqlCmd = "SELECT * FROM compon WHERE co_lin = :lin AND trim(co_clave) = :clave AND co_grupo = '1' ";
  $oCOM = $con->prepare($sqlCmd);
  $oCOM->bindParam(":lin", $lin, PDO::PARAM_STR);
  $oCOM->bindParam(":clave", $clave, PDO::PARAM_STR);
  $oCOM->execute();
  $numResCOM = $oCOM->rowCount();
  if ($numResCOM > 0) {
    $rowCOM = $oCOM->fetch(PDO::FETCH_ASSOC);
    return ($rowCOM);
  } else {
    return null;
  }
}

function seekCompoTipoGpo2y3($lin, $clave)
{
  //global $con;
  $con = DB::getConn();

  $sqlCmd = "SELECT * FROM compon WHERE co_lin = :lin AND trim(co_clave) = :clave 
    AND ( co_grupo = '2' || co_grupo = '3' ) ";
  $oCOM = $con->prepare($sqlCmd);
  $oCOM->bindParam(":lin", $lin, PDO::PARAM_STR);
  $oCOM->bindParam(":clave", $clave, PDO::PARAM_STR);
  $oCOM->execute();
  $numResCOM = $oCOM->rowCount();
  if ($numResCOM > 0) {
    $rowCOM = $oCOM->fetch(PDO::FETCH_ASSOC);
    return ($rowCOM);
  } else {
    return null;
  }
}

function seekCompoGpo1Gpo2Gpo3($lin, $clave)
{

  //global $con;
  $con = DB::getConn();

  $sqlCmd = "SELECT * FROM compon WHERE co_lin = :lin AND trim(co_clave) = :clave 
      AND ( co_grupo = '1' || co_grupo = '2' || co_grupo = '3' ) ";
  $oCOM = $con->prepare($sqlCmd);
  $oCOM->bindParam(":lin", $lin, PDO::PARAM_STR);
  $oCOM->bindParam(":clave", $clave, PDO::PARAM_STR);
  $oCOM->execute();
  $numResCOM = $oCOM->rowCount();
  if ($numResCOM > 0) {
    $rowCOM = $oCOM->fetch(PDO::FETCH_ASSOC);
    return ($rowCOM);
  } else {
    return null;
  }
}

function seekCompo($lin, $clave)
{
  $con = DB::getConn();

  $sqlCmd = "SELECT * FROM compon WHERE co_lin = :lin AND trim(co_clave) = :clave";
  $oCOM = $con->prepare($sqlCmd);
  $oCOM->bindParam(":lin", $lin, PDO::PARAM_STR);
  $oCOM->bindParam(":clave", $clave, PDO::PARAM_STR);
  $oCOM->execute();
  $numResCOM = $oCOM->rowCount();
  if ($numResCOM > 0) {
    $rowCOM = $oCOM->fetch(PDO::FETCH_ASSOC);
    return ($rowCOM);
  } else {
    return null;
  }
}

function calcPesoPiedra()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  global $W_TCANIM;
  global $W_PARAML, $W_FACSER;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;

  if ($C_CO1 <> 0) {
    $rowCOM = seekCompo($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA1 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompo($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA2 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompo($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA3 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompo($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA4 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompo($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA5 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompo($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA6 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompo($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA7 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO8 <> 0) {
    $rowCOM = seekCompo($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA8 * $rowCOM["co_peso"]);
        }
      }
    }
  }
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompo($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_peso"] <> "0") {
        #SOLO SI EL PARAMETRO 9 DE LA LINEA DEL ARTICULO ESTA ACTIVO 
        #(SI ES IGUAL A "1") Y SI ES FORMULA 3 o 4

        #ACUERDATE QUE EN PHP EL INDICE INICIAL EN SUBSTR() ES "CERO",
        #MIENTRAS QUE FOXPRO EMPIEZA CON "UNO"...

        if (substr($W_PARAML, 8, 1) == "1" && $W_FACSER == 1) {
          #PESO PIEDRA:
          $W_TCANIM = $W_TCANIM + ($C_CA9 * $rowCOM["co_peso"]);
        }
      }
    }
  }
}

function sumaInsumosGpo1MasValorAgregadoEspecial()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  #$W_TCANIM <- peso piedra
  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  global $NormalEquivalente, $TipoParidad;
  global $W_TCANIM, $W_NOAUMENTO;
  global $rowListaPrecLinea;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO1 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA1 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR1 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA1 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR1 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA2 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR2 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA2 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR2 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA3 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR3 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA3 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR3 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA4 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR4 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA4 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR4 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA5 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR5 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA5 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR5 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA6 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR6 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA6 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR6 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA7 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR7 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA7 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR7 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO8 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA8 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR8 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA8 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR8 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA9 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR9 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_CA9 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1e"] + $W_FACTOR) * ($C_GR9 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }
}

function sumaInsumosGpo2ConPrecioVentayGrupo3Especial()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  #$W_TCANIM <- peso piedra
  global $NormalEquivalente, $TipoParidad;
  global $W_TCANIM, $W_NOAUMENTO;
  global $rowListaPrecLinea;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO1 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR1);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR1);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA1);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR1);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA1);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR1);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA2);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR2);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA2);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR2);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA2);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR2);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA2);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR2);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA3);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR3);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA3);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR3);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA3);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR3);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA3);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR3);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA4);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR4);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA4);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR4);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA4);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR4);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA4);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR4);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA5);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR5);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA5);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR5);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA5);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR5);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA5);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR5);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA6);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR6);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA6);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR6);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA6);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR6);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA6);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR6);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA7);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR7);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA7);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR7);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA7);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR7);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA7);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR7);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO8 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA8);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR8);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA8);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR8);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA8);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR8);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA8);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR8);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_ventae"] * $C_CA9);
        $TPG  = $TPG  + ($rowCOM["co_ventae"] * $C_GR9);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1e"] * $C_CA9);
        $TPG  = $TPG  + ($rowCOM["co_l1e"] * $C_GR9);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_ventae"] * $C_CA9);
          $TPGE  = $TPGE + ($rowCOM["co_ventae"] * $C_GR9);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1e"] * $C_CA9);
          $TPGE  = $TPGE + ($rowCOM["co_l1e"] * $C_GR9);
        }
      }
    }
  }
}

function sumaInsumosGpo1MasValorAgregadoNormal()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  #$W_TCANIM <- peso piedra
  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  global $NormalEquivalente, $TipoParidad;
  global $W_TCANIM, $W_NOAUMENTO;
  global $rowListaPrecLinea;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO1 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA1 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR1 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA1 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR1 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA2 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR2 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA2 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR2 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA3 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR3 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA3 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR3 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA4 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR4 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA4 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR4 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA5 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR5 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA5 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR5 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA6 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR6 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA6 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR6 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA7 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR7 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA7 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR7 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO8 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA8 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR8 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA8 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR8 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompoTipoGpo1($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      $W_FACTOR = $rowListaPrecLinea["r_facimp"];
      if ($W_FACTOR <> 0) {
        $TPRE = $TPRE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA9 - $W_TCANIM));
        $TPG  = $TPG + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR9 - $W_TCANIM));
        $W_NOAUMENTO = 1;
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_facoro"] <> 0) {
          $W_FACORO = $rowCOM["co_facoro"];
        }
        if ($rowCOM["co_facmaq"] <> 0) {
          $W_FACMAQ = $rowCOM["co_facmaq"];
        }

        $W_FACTOR = $rowListaPrecLinea["r_facimp"];
        if ($W_FACTOR <> 0) {
          #En Proeli se utiliza C_CA1 y C_GR1, np C_CA1E ni C_GR1E
          #como es de esperarse
          $TPREE = $TPREE + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_CA9 - $W_TCANIM));
          $TPGE  = $TPGE  + (($rowCOM["co_l1"] + $W_FACTOR) * ($C_GR9 - $W_TCANIM));
          $W_NOAUMENTO = 1;
        }
      }
    }
  }
}

function sumaInsumosGpo2ConPrecioVentayGrupo3Normal()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  #$W_TCANIM <- peso piedra
  global $NormalEquivalente, $TipoParidad;
  global $W_TCANIM, $W_NOAUMENTO;
  global $rowListaPrecLinea;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO1 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR1);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA1);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR1);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA1);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR1);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA1);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR1);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA2);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR2);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA2);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR2);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA2);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR2);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA2);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR2);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA3);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR3);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA3);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR3);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA3);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR3);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA3);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR3);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA4);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR4);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA4);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR4);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA4);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR4);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA4);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR4);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA5);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR5);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA5);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR5);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA5);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR5);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA5);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR5);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA6);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR6);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA6);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR6);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA6);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR6);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA6);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR6);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA7);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR7);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA7);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR7);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA7);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR7);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA7);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR7);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO8 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA8);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR8);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA8);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR8);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA8);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR8);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA8);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR8);
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompoTipoGpo2y3($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["co_facoro"] <> 0) {
        $W_FACORO = $rowCOM["co_facoro"];
      }
      if ($rowCOM["co_facmaq"] <> 0) {
        $W_FACMAQ = $rowCOM["co_facmaq"];
      }

      if ($rowCOM["co_grupo"] == "2") {
        $TPRE = $TPRE + ($rowCOM["co_venta"] * $C_CA9);
        $TPG  = $TPG  + ($rowCOM["co_venta"] * $C_GR9);
      } else {
        $TPRE = $TPRE + ($rowCOM["co_l1"] * $C_CA9);
        $TPG  = $TPG  + ($rowCOM["co_l1"] * $C_GR9);
      }

      if ($NormalEquivalente == "E") {
        if ($rowCOM["co_grupo"] == "2") {
          $TPREE = $TPREE + ($rowCOM["co_venta"] * $C_CA9);
          $TPGE  = $TPGE + ($rowCOM["co_venta"] * $C_GR9);
        } else {
          $TPREE = $TPREE + ($rowCOM["co_l1"] * $C_CA9);
          $TPGE  = $TPGE + ($rowCOM["co_l1"] * $C_GR9);
        }
      }
    }
  }
}

#------------------------------------------------------------------------------
function sumaInsumosGpo1Gpo2PrecVtaGpo3()
{
  # dRendon 03/may/2022 Se agrega la linea de componentes en busquedas

  global $con;
  #$W_CATA="E" significa que va a acumular variables relacionadas con el Codigo Equivalente
  #$W_TCANIM <- peso piedra
  global $W_CATA, $W_TPARID, $W_TCANIM, $W_NOAUMENTO;
  global $rowAGR;
  global $C_LCO1, $C_LCO2, $C_LCO3, $C_LCO4, $C_LCO5, $C_LCO6, $C_LCO7, $C_LCO8, $C_LCO9;
  global $C_CO1, $C_CO2, $C_CO3, $C_CO4, $C_CO5, $C_CO6, $C_CO7, $C_CO8, $C_CO9;
  global $C_CA1, $C_CA2, $C_CA3, $C_CA4, $C_CA5, $C_CA6, $C_CA7, $C_CA8, $C_CA9;
  global $C_GR1, $C_GR2, $C_GR3, $C_GR4, $C_GR5, $C_GR6, $C_GR7, $C_GR8, $C_GR9;
  global $C_CA1E, $C_CA2E, $C_CA3E, $C_CA4E, $C_CA5E, $C_CA6E, $C_CA7E, $C_CA8E, $C_CA9E;
  global $C_GR1E, $C_GR2E, $C_GR3E, $C_GR4E, $C_GR5E, $C_GR6E, $C_GR7E, $C_GR8E, $C_GR9E;
  global $TPRE, $TPG, $TPREE, $TPGE;

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO1 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO1, $C_CO1);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA1);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR1);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA1);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR1);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA1);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR1);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA1);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR1);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA1);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR1);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA1);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR1);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA1);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR1);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA1);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR1);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO2 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO2, $C_CO2);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA2);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR2);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA2);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR2);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA2);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR2);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA2);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR2);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA2);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR2);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA2);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR2);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA2);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR2);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA2);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR2);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO3 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO3, $C_CO3);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA3);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR3);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA3);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR3);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA3);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR3);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA3);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR3);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA3);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR3);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA3);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR3);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA3);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR3);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA3);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR3);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO4 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO4, $C_CO4);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA4);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR4);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA4);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR4);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA4);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR4);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA4);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR4);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA4);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR4);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA4);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR4);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA4);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR4);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA4);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR4);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO5 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO5, $C_CO5);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA5);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR5);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA5);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR5);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA5);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR5);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA5);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR5);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA5);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR5);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA5);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR5);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA5);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR5);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA5);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR5);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO6 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO6, $C_CO6);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA6);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR6);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA6);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR6);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA6);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR6);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA6);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR6);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA6);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR6);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA6);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR6);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA6);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR6);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA6);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR6);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO7 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO7, $C_CO7);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA7);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR7);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA7);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR7);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA7);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR7);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA7);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR7);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA7);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR7);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA7);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR7);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA7);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR7);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA7);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR7);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO8 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO8, $C_CO8);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA8);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR8);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA8);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR8);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA8);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR8);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA8);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR8);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA8);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR8);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA8);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR8);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA8);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR8);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA8);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR8);
          }
        }
      }
    }
  }

  $W_FACORO = 0;
  $W_FACMAQ = 0;
  if ($C_CO9 <> 0) {
    $rowCOM = seekCompoGpo1Gpo2Gpo3($C_LCO9, $C_CO9);
    if (isset($rowCOM)) {
      if ($rowCOM["CO_FACORO"] <> 0) {
        $W_FACORO = $rowCOM["CO_FACORO"];
      }
      if ($rowCOM["CO_FACMAQ"] <> 0) {
        $W_FACMAQ = $rowCOM["CO_FACMAQ"];
      }

      if ($rowCOM["CO_GRUPO"] == "2") {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_VENTA"] * $C_CA9);
          $TPG  = $TPG  + ($rowCOM["CO_VENTA"] * $C_GR9);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_VENTAE"] * $C_CA9);
          $TPG  = $TPG  + ($rowCOM["CO_VENTAE"] * $C_GR9);
        }
      } else {
        if ($W_TPARID == "N") {
          $TPRE = $TPRE + ($rowCOM["CO_L1"] * $C_CA9);
          $TPG  = $TPG  + ($rowCOM["CO_L1"] * $C_GR9);
        } else {
          $TPRE = $TPRE + ($rowCOM["CO_L1E"] * $C_CA9);
          $TPG  = $TPG  + ($rowCOM["CO_L1E"] * $C_GR9);
        }
      }

      if ($W_CATA == "E") {
        if ($rowCOM["CO_GRUPO"] == "2") {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_VENTA"] * $C_CA9);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTA"] * $C_GR9);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_VENTAE"] * $C_CA9);
            $TPGE  = $TPGE + ($rowCOM["CO_VENTAE"] * $C_GR9);
          }
        } else {
          if ($W_TPARID == "N") {
            $TPREE = $TPREE + ($rowCOM["CO_L1"] * $C_CA9);
            $TPGE  = $TPGE + ($rowCOM["CO_L1"] * $C_GR9);
          } else {
            $TPREE = $TPREE + ($rowCOM["CO_L1E"] * $C_CA9);
            $TPGE  = $TPGE + ($rowCOM["CO_L1E"] * $C_GR9);
          }
        }
      }
    }
  }
}
