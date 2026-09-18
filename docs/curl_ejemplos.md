# Ejemplos cURL — TodoCamisetas API

Examen Transversal Final | Desarrollo Backend IF201IINF
Instituto Profesional San Sebastián — Rodrigo Alexis Soto Cifuentes

Todos los ejemplos asumen que Apache y MySQL están iniciados en XAMPP y que
`database.sql` ya fue importado.

```
BASE = http://localhost/todocamisetas-api/public
```

> En Windows, PowerShell interpreta `curl` como un alias de `Invoke-WebRequest`.
> Use `curl.exe` (con extensión) o ejecute estos comandos en Git Bash / CMD.

---

## 0. Descubrimiento de la API

### Ver la tabla de rutas registradas

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api"
```

**Respuesta 200 OK**

```json
{
  "status": "success",
  "code": 200,
  "api": "TodoCamisetas API",
  "version": "1.0.0",
  "autor": "Rodrigo Alexis Soto Cifuentes",
  "rutas": [
    {
      "metodo": "GET",
      "expresion": "#^/api/camisetas$#",
      "controlador": "CamisetaController@index",
      "proposito": "Lista camisetas con filtros (club, tipo, cliente_id) y paginacion"
    }
  ]
}
```

---

## 1. Camisetas — CRUD

### 1.1 Listar camisetas (GET)

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas?pagina=1&por_pagina=10"
```

**200 OK**

```json
{
  "status": "success",
  "code": 200,
  "total": 7,
  "meta": { "total": 7, "pagina": 1, "por_pagina": 10, "paginas": 1 },
  "data": [ { "id": 1, "titulo": "Camiseta Local 2025 - Seleccion Chilena", "precio": 45000, "precio_final": 45000, "tallas": [] } ]
}
```

### 1.2 Filtrar por club

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas?club=Colo%20Colo"
```

### 1.3 Ver una camiseta — cliente **Preferencial** (precio de oferta)

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas/1?cliente_id=1"
```

**200 OK** — `90minutos` es Preferencial y la camiseta tiene `precio_oferta`:

```json
{
  "status": "success",
  "code": 200,
  "data": {
    "id": 1,
    "precio": 45000,
    "precio_oferta": 38000,
    "precio_final": 38000,
    "precio_detalle": {
      "tipo_cliente": "Preferencial",
      "cliente_id": 1,
      "origen": "precio_oferta",
      "descuento": 7000
    }
  }
}
```

### 1.4 Ver la misma camiseta — cliente **Regular** (precio base)

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas/1?cliente_id=2"
```

**200 OK** — `tdeportes` es Regular, por lo que no accede a la oferta:

```json
{
  "status": "success",
  "code": 200,
  "data": {
    "id": 1,
    "precio": 45000,
    "precio_oferta": 38000,
    "precio_final": 45000,
    "precio_detalle": {
      "tipo_cliente": "Regular",
      "cliente_id": 2,
      "origen": "precio_base",
      "descuento": 0
    }
  }
}
```

### 1.5 Camiseta inexistente

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas/999"
```

**404 Not Found**

```json
{ "status": "error", "code": 404, "message": "No existe una camiseta con el ID 999." }
```

### 1.6 Crear camiseta (POST)

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Camiseta Local 2025 - Audax Italiano",
    "club": "Audax Italiano",
    "pais": "Chile",
    "tipo": "Local",
    "color": "Verde y Blanco",
    "precio": 39000,
    "precio_oferta": 33000,
    "detalles": "Modelo oficial temporada 2025",
    "codigo_producto": "AUD2025L",
    "cliente_id": 2,
    "tallas": [ { "talla_id": 2, "stock": 20 }, { "talla_id": 3, "stock": 15 } ]
  }'
```

**201 Created**

```json
{
  "status": "success",
  "code": 201,
  "message": "Camiseta creada correctamente.",
  "data": { "id": 8, "codigo_producto": "AUD2025L", "precio": 39000 }
}
```

### 1.7 Crear sin campos obligatorios

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas" \
  -H "Content-Type: application/json" \
  -d '{ "club": "Colo Colo" }'
```

**422 Unprocessable Entity**

```json
{
  "status": "error",
  "code": 422,
  "message": "Los datos enviados no son validos.",
  "errors": [
    "El campo \"titulo\" es obligatorio.",
    "El campo \"pais\" es obligatorio.",
    "El campo \"tipo\" es obligatorio.",
    "El campo \"color\" es obligatorio.",
    "El campo \"precio\" es obligatorio.",
    "El campo \"codigo_producto\" es obligatorio."
  ]
}
```

### 1.8 Crear con SKU duplicado

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas" \
  -H "Content-Type: application/json" \
  -d '{ "titulo": "Prueba", "club": "X", "pais": "Chile", "tipo": "Local", "color": "Rojo", "precio": 1000, "codigo_producto": "SCL2025L" }'
```

**409 Conflict**

```json
{ "status": "error", "code": 409, "message": "El codigo de producto SCL2025L ya esta en uso." }
```

### 1.9 Actualizar completo (PUT)

```bash
curl -i -X PUT "http://localhost/todocamisetas-api/public/api/camisetas/8" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Camiseta Local 2025 - Audax Italiano (actualizada)",
    "club": "Audax Italiano",
    "pais": "Chile",
    "tipo": "Local",
    "color": "Verde",
    "precio": 41000,
    "codigo_producto": "AUD2025L"
  }'
```

### 1.10 Actualizar parcial (PATCH)

```bash
curl -i -X PATCH "http://localhost/todocamisetas-api/public/api/camisetas/8" \
  -H "Content-Type: application/json" \
  -d '{ "precio_oferta": 35000 }'
```

**200 OK** — solo cambia `precio_oferta`; el resto de los campos se conserva.

### 1.11 Eliminar camiseta (DELETE)

```bash
curl -i -X DELETE "http://localhost/todocamisetas-api/public/api/camisetas/8"
```

**200 OK**

```json
{ "status": "success", "code": 200, "message": "Camiseta con ID 8 eliminada correctamente." }
```

---

## 2. Clientes — CRUD

### 2.1 Listar clientes

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/clientes"
```

### 2.2 Filtrar por categoría

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/clientes?categoria=Preferencial"
```

### 2.3 Ver un cliente

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/clientes/1"
```

**200 OK**

```json
{
  "status": "success",
  "code": 200,
  "data": {
    "id": 1,
    "nombre_comercial": "90minutos",
    "rut": "76.543.210-8",
    "categoria": "Preferencial",
    "contacto": { "nombre": "Matias Herrera", "email": "compras@90minutos.cl" },
    "porcentaje_oferta": 10
  }
}
```

### 2.4 Listar las camisetas de un cliente

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/clientes/1/camisetas"
```

**200 OK** — cada camiseta trae el `precio_final` que le corresponde a ese cliente.

### 2.5 Crear cliente (POST)

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/clientes" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre_comercial": "Deportes Sur",
    "rut": "79.456.123-7",
    "direccion": "Puerto Montt, Los Lagos",
    "categoria": "Preferencial",
    "contacto_nombre": "Paula Mendez",
    "contacto_email": "compras@deportessur.cl",
    "porcentaje_oferta": 12
  }'
```

**201 Created**

### 2.6 Crear con correo inválido

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/clientes" \
  -H "Content-Type: application/json" \
  -d '{ "nombre_comercial": "Test", "rut": "11.111.111-1", "direccion": "Santiago", "contacto_nombre": "Juan", "contacto_email": "correo-malo" }'
```

**422 Unprocessable Entity**

```json
{
  "status": "error",
  "code": 422,
  "message": "Los datos enviados no son validos.",
  "errors": ["El campo \"contacto_email\" debe ser un correo electronico valido."]
}
```

### 2.7 Actualizar parcial (PATCH)

```bash
curl -i -X PATCH "http://localhost/todocamisetas-api/public/api/clientes/4" \
  -H "Content-Type: application/json" \
  -d '{ "porcentaje_oferta": 20 }'
```

### 2.8 Eliminar cliente **con** camisetas asignadas

```bash
curl -i -X DELETE "http://localhost/todocamisetas-api/public/api/clientes/1"
```

**409 Conflict** — regla de negocio que protege la integridad del inventario:

```json
{
  "status": "error",
  "code": 409,
  "message": "No se puede eliminar el cliente porque tiene 3 camiseta(s) asignada(s). Reasigne o elimine primero esos productos.",
  "camisetas_asociadas": 3
}
```

### 2.9 Eliminar cliente sin camisetas

```bash
curl -i -X DELETE "http://localhost/todocamisetas-api/public/api/clientes/4"
```

**200 OK**

---

## 3. Tallas — catálogo

### 3.1 Listar tallas

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/tallas"
```

### 3.2 Ver una talla

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/tallas/2"
```

### 3.3 Crear talla (POST)

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/tallas" \
  -H "Content-Type: application/json" \
  -d '{ "codigo": "XS", "descripcion": "Extra Small" }'
```

**201 Created**

### 3.4 Crear talla con código duplicado

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/tallas" \
  -H "Content-Type: application/json" \
  -d '{ "codigo": "M", "descripcion": "Medium" }'
```

**409 Conflict**

```json
{ "status": "error", "code": 409, "message": "Ya existe una talla con el codigo M." }
```

### 3.5 Actualizar talla (PUT)

```bash
curl -i -X PUT "http://localhost/todocamisetas-api/public/api/tallas/7" \
  -H "Content-Type: application/json" \
  -d '{ "codigo": "XS", "descripcion": "Extra Small (ajustado)" }'
```

### 3.6 Actualizar talla (PATCH)

```bash
curl -i -X PATCH "http://localhost/todocamisetas-api/public/api/tallas/7" \
  -H "Content-Type: application/json" \
  -d '{ "descripcion": "Talla extra pequena" }'
```

### 3.7 Eliminar talla (DELETE)

```bash
curl -i -X DELETE "http://localhost/todocamisetas-api/public/api/tallas/7"
```

**200 OK** — informa cuántas asignaciones se liberaron en cascada:

```json
{
  "status": "success",
  "code": 200,
  "message": "Talla con ID 7 eliminada correctamente.",
  "asignaciones_liberadas": 0
}
```

---

## 4. Camiseta ↔ Tallas — relación muchos a muchos

Esta es la sección que materializa la tabla pivote `camiseta_tallas`.

### 4.1 Listar las tallas de una camiseta

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas"
```

**200 OK**

```json
{
  "status": "success",
  "code": 200,
  "camiseta_id": 1,
  "total": 4,
  "data": [
    { "talla_id": 1, "codigo": "S",  "descripcion": "Small",       "stock": 20 },
    { "talla_id": 2, "codigo": "M",  "descripcion": "Medium",      "stock": 35 },
    { "talla_id": 3, "codigo": "L",  "descripcion": "Large",       "stock": 30 },
    { "talla_id": 4, "codigo": "XL", "descripcion": "Extra Large", "stock": 15 }
  ]
}
```

### 4.2 Camiseta inexistente

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/camisetas/999/tallas"
```

**404 Not Found**

```json
{ "status": "error", "code": 404, "message": "No existe una camiseta con el ID 999." }
```

### 4.3 Asignar una talla a una camiseta (POST)

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas" \
  -H "Content-Type: application/json" \
  -d '{ "talla_id": 5, "stock": 25 }'
```

**201 Created**

```json
{
  "status": "success",
  "code": 201,
  "message": "Talla asignada correctamente a la camiseta.",
  "camiseta_id": 1,
  "data": { "talla_id": 5, "codigo": "XXL", "descripcion": "Doble Extra Large", "stock": 25 }
}
```

### 4.4 Asignar una talla ya asignada

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas" \
  -H "Content-Type: application/json" \
  -d '{ "talla_id": 2, "stock": 10 }'
```

**409 Conflict** — la clave primaria compuesta impide duplicados:

```json
{
  "status": "error",
  "code": 409,
  "message": "La talla 2 ya esta asignada a la camiseta 1. Use PUT para modificar su stock."
}
```

### 4.5 Asignar una talla que no existe en el catálogo

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas" \
  -H "Content-Type: application/json" \
  -d '{ "talla_id": 99, "stock": 5 }'
```

**404 Not Found**

```json
{ "status": "error", "code": 404, "message": "No existe una talla con el ID 99 en el catalogo." }
```

### 4.6 Asignar sin `talla_id`

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas" \
  -H "Content-Type: application/json" \
  -d '{ "stock": 10 }'
```

**422 Unprocessable Entity**

```json
{
  "status": "error",
  "code": 422,
  "message": "Los datos enviados no son validos.",
  "errors": ["El campo \"talla_id\" es obligatorio y debe ser un numero entero positivo."]
}
```

### 4.7 Actualizar el stock (PUT)

```bash
curl -i -X PUT "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas/5" \
  -H "Content-Type: application/json" \
  -d '{ "stock": 40 }'
```

**200 OK**

### 4.8 Actualizar el stock de una talla no asignada

```bash
curl -i -X PUT "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas/6" \
  -H "Content-Type: application/json" \
  -d '{ "stock": 10 }'
```

**404 Not Found**

```json
{ "status": "error", "code": 404, "message": "La camiseta 1 no tiene asignada la talla 6." }
```

### 4.9 Stock negativo

```bash
curl -i -X PUT "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas/5" \
  -H "Content-Type: application/json" \
  -d '{ "stock": -5 }'
```

**422 Unprocessable Entity**

```json
{
  "status": "error",
  "code": 422,
  "message": "Los datos enviados no son validos.",
  "errors": ["El campo \"stock\" debe ser un numero entero mayor o igual a cero."]
}
```

### 4.10 Quitar una talla de una camiseta (DELETE)

```bash
curl -i -X DELETE "http://localhost/todocamisetas-api/public/api/camisetas/1/tallas/5"
```

**200 OK**

```json
{ "status": "success", "code": 200, "message": "Talla 5 desasignada de la camiseta 1." }
```

---

## 5. Manejo de errores generales

### 5.1 Ruta inexistente

```bash
curl -i -X GET "http://localhost/todocamisetas-api/public/api/zapatillas"
```

**404 Not Found**

```json
{ "status": "error", "code": 404, "message": "La ruta GET /api/zapatillas no existe en esta API." }
```

### 5.2 Método HTTP no permitido

```bash
curl -i -X DELETE "http://localhost/todocamisetas-api/public/api/camisetas"
```

**405 Method Not Allowed** — la respuesta incluye la cabecera `Allow`:

```json
{
  "status": "error",
  "code": 405,
  "message": "El metodo DELETE no esta permitido en esta ruta. Metodos disponibles: GET, POST."
}
```

### 5.3 JSON malformado

```bash
curl -i -X POST "http://localhost/todocamisetas-api/public/api/tallas" \
  -H "Content-Type: application/json" \
  -d '{ codigo: sin comillas }'
```

**400 Bad Request**

```json
{
  "status": "error",
  "code": 400,
  "message": "El cuerpo de la solicitud no es un JSON valido: Syntax error"
}
```

---

## Resumen de códigos HTTP utilizados

| Código | Significado en esta API |
|---|---|
| 200 | Consulta, actualización o eliminación exitosa |
| 201 | Recurso creado (camiseta, cliente, talla, asignación) |
| 204 | Respuesta a la petición previa CORS (OPTIONS) |
| 400 | ID no válido, cuerpo vacío o JSON malformado |
| 404 | Recurso, ruta o relación inexistente |
| 405 | La ruta existe pero no admite ese método HTTP |
| 409 | Conflicto de integridad: SKU o RUT duplicado, talla ya asignada, cliente con camisetas |
| 422 | Error de validación de los datos enviados |
| 500 | Error interno o de base de datos |
