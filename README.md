#### MED_FONELLI_APIPortal
# Control de Cambios

Este proyecto incluye los servicios (endpoints) que se consumen por la aplicación web de Fonelli que presenta información de las operaciones de venta y cobranza, cuyos usuarios son clientes y ejecutivos de venta.

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

