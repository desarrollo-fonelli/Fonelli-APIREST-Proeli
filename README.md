#### MED_FONELLI_APIPortal
# Control de Cambios

Este proyecto incluye los servicios (endpoints) que se consumen por la aplicación web de Fonelli que presenta información de las operaciones de venta y cobranza, cuyos usuarios son clientes y ejecutivos de venta.

### v2.8.0 | 18.06.2025 | Ordenes de Retorno
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

