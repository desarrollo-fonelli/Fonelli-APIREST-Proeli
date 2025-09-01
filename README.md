#### MED_FONELLI_APIPortal
# Control de Cambios

Este proyecto incluye los servicios (endpoints) que se consumen por la aplicación web de Fonelli que presenta información de las operaciones de venta y cobranza, cuyos usuarios son clientes y ejecutivos de venta.

---
### v2.15.1 | 28.08.2025 | Ajustes después de la revisión
#### Cambios:
1. CotizCrear: Ahora se guardan el código de la lista de precios, el tipo de paridad y los comentarios del documento.
2. CotizListar: Se incluyen los campos mencionados en el JSON que se retorna.

---
### v2.15.0 | 27.08.2025 | CotizCrear | CotizListar
#### Nuevo:
1. Se crea el servicio POST para agregar datos generales y filas al documento de cotización.
2. Se crea el servicio GET para obtener la lista de Cotizaciones registradas.

#### Cambios:
1. Indicador de Devoluciones: Voy a tomar los gramos devueltos de la secciópn de datos generales, no de los renglones.

| Servicio               | Descripcion |
|------------------------|---------------------------------
| CotizCrear.php         | INSERT en tablas de Cotizaciones
| CotizListar.php        | Acción GET para obtener lista de Cotizaciones registradas
| IndicadVenta2025.php   | Cambié la consulta para obtener los gramos devueltos.

---
### v2.14.0 | 12.08.2025 | Ajustes Prepedidos
#### Cambios:
1. Se agrega columna para plazo documentado y se agregan nodos para subtotales y gran total en el JSON devuelto

| Servicio               | Descripcion |
|------------------------|-----------------------------
| PrepedidosRepo.php     | ajustes varios
| PrepedRepoDetalle.php  | ajustes varios

---
### v2.13.0 | 07.08.2025 | IndicadVenta2025
#### Nuevo:
1. Se crea el servicio para crear indicadores de venta versión 2025.
2. Se crea el servicio para presentar acumulados de venta dividiendo importe bruto de valor agregado

| Servicio               | Descripcion |
|------------------------|-----------------------------
| IndicadVenta2025.php   | Obtiene indicadores de Venta versión 2025
| IndicadVentaAcum.php   | Acumulados de venta dividiendo importe bruto de valor agregado

---
### v2.12.0 | 29.07.2025 | PrepedidosRepo | PrepedRepoDetalle
#### Nuevo:
1. Se crea el servicio que devuelve el conjunto de datos de Prepedidos, de acuerdo a los criterios de filtro.
2. Se crea el servicio que devuelve los artículos incluidos en un prepedido

| Servicio               | Descripcion |
|------------------------|-----------------------------
| PrepedidosRepo.php     | Lista resumida de prepedidos 
| PrepedRepoDetalle.php  | Artículos incluidos en un prepedido 

---
### v2.11.0 | 17.07.2025 | ArticulosBusqueda
#### Nuevo:
1. Se crea un servicio dedicado a buscar artículos semejantes al código introducido o con errores de tipografía, devolviendo datos mínimos.
---
### v2.10.0 | 14.07.2025 | ArticulosLista | CalcPrec2025
#### Nuevo:
1. Se crea el servicio que devuelve una lista de artículos incluidos en las líneas de producto seleccionadas. Se incluye el precio calculado de cada artículo.
2. Se crea un script para el cálculo de precio de acuerdo a los parámetros 2025

| Servicio           | Descripcion |
|--------------------|--------------------------------------------|
| ArticulosLista.php | Busca artículos que se van a incluir en el reporte     |
| CalcPrec2025.php   | Rutina para Cálculo de Precios    |
---
### v2.9.1 | 11.07.2025 | CltesDocVenta
#### Issues:
1. Al solicitar datos con el "TipoUsuario"=Agente, el mecanismo de autenticación devuelve un error.
1.1 Resuelto: no estaba asignada la variable que recibe el código del agente.
2. Al acceder como "TipoUsuario"=Cliente, el código de "Usuario" no se recibe en el formato correcto.
2.1 Resuelto: Este problema lo ocasiona el Frontend. Se hicieron las correcciones respectivas.
---
### v2.9.0 | 10.07.2025 | CltesDocVenta | ArticuloPrecio
#### Nuevo:
1. Se crea servicio para obtener los datos básicos de Cliente que se utilizan en documentos de venta, para cálculo de precios y condiciones de crédito, devolviendo el JSON correspondiente.
2. Se crea servicio para obtener los datos esenciales del artículo que se va a incluir en documentos de venta, incluyendo el precio calculado y datos relacionados.

| Servicio              | Descripcion |
|-----------------------|--------------------------------------------|
| CltesDocVenta.php     | Datos cliente para documentos de venta     |
| ArticuloPrecio.php    | Datos artículo para documentos de venta    |

### v2.8.0 | 18.06.2025 | Ordenes de Retorno | Consulta de Guias
#### Nuevo:
1. Se crea el servicio para obtener los datos generales de las Ordenes de Retorno, devolviendo el JSON correspondiente.
2. Se escribe nuevamente el servicio para obtener los artículos de la Orden de Retorno.
3. Se ajustan los criterios de búsqueda para mejorar la interactividad entre los controles del formulario.
3.1 Ahora se permite indicar algún documento, orden de compra, pedido, etc. sin tener que indicar un cliente.

|Servicio                 | Descripcion |
|-------------------------|--------------------------------------------|
| OrdenesRetorno.php      | Lista de Ordenes de Retorno                |
| DetalleOrdenRetorno.php | Articulos incluidos en la Orden de Retorno |
| ConsultaGuias2025.php   | Consulta de Logistica (Paquetes, Guías)    |

### v2.7.0 | 30.05.2025 | ConsultaGuias2025
#### Nuevo:
1. Se crea el servicio para ejecutar la "Consulta de Guias" (versión 2025) y devolver el JSON correspondiente.

|Servicio                | Descripcion |
|------------------------|----------------------------------------------------|
| ConsultaGuias2025.php  | Lista de Paquetes (Guias) y documentos que incluye |
| DocumArticulos.php     | Articulos contenidos en un documento de venta o inventario |

### v2.6.5 | 2025-05-28 | DetallePed2025
#### Issues:
1. Se agrega lógica para ubicar correctamente el pedido cuando se tiene Orden de Producción sin ensobretar.

### v2.6.4 | 2025-05-26 | Relacion de Pedidos
#### Cambios:
1. Se agrega el número de Orden de Compra en consulta SQL y en JSON devuelto por el endpoint 

### v2.6.3 | 2025-05-26 | DocVentaDetalle
#### Issues:
1. En la consulta que busca artículos por documento, se consideran solamente artículos que son parte de un movimiento de "salida", lo cual es relevante en los traspasos de inventario, ya que las prefacturas se registran con este documento.

### v2.6.2 | 2025-05-09 | DetallePed2025 | DetallePedGuias | DocVentaDetalle
#### Nuevo:
1. Se crea nueva versión del servicio para devolver detalle del pedido solicitado: artículos y datos complementarios. También se incluyen los artículos externos recibidos (artículos comprados).
2. Se crea servicio para devolver guias de paquetes enviados asociados al pedido solicitado.
3. Se crea servicio para devolver los artículos incluidos en un documento de venta

### v2.6.1 | 2025-05-08 | Carpeta "Llamadas"
#### Nuevo:
1. Se copia del servidor on-line la carpeta "llamadas" que se desarrolló en Agasys para el proyecto de "Configura tu Joya"

### v2.6.0 | 2025-05-06 | ConsultaPedidos
#### Cambios:
1. Se agregan criterios de filtro: num. de pedido y orden de compra
2. Se agregan columnas en tabla resumida: orden de compra, tienda destino

### v2.5.0 | 2025-04-29 | ExistenciasPT | ListaAlmacenes
#### Nuevo:
1. Se agrega servicio para obtener lista de existencias de PT.
2. Se agrega servicio para obtener lista de almacenes de PT.

### v2.4.0 | 2025-04-22 | DescargarCFDI
#### Nuevo:
1. Se agrega servicio para descargar PDF del CFDI solicitado

### v2.3.0 | 2025-04-11 | ListaCFDIS: fac010
#### Nuevo:
1. Se agrega servicio para obtener una lista con los CFDIs de clientes, consultando la tabla fac010 (consolidada todas las oficinas)

### v2.2.0 | 2025-01-30 | Consulta de Guias: Paquetes y Ordenes de Retorno
#### Nuevo:
1. Se agregan las consultas para presentar Paquetes Enviados y Ordenes de Retorno

