# TodoCamisetas API

API RESTful desarrollada en **PHP puro (sin frameworks)** para el sistema de gestión de
inventario y relación con clientes B2B de **TodoCamisetas**, proveedor mayorista de
camisetas de fútbol con sede en Santiago, Chile.

**Examen Transversal Final** — Desarrollo Backend (IF201IINF)
Instituto Profesional San Sebastián
Autor: Rodrigo Alexis Soto Cifuentes

---

## 1. Instalación

1. Copiar la carpeta `todocamisetas-api` dentro de `C:\xampp\htdocs\`.
2. Iniciar **Apache** y **MySQL** desde el Panel de Control de XAMPP.
3. Abrir phpMyAdmin (`http://localhost/phpmyadmin`) e importar `database.sql`.
   Esto crea la base `todocamisetas`, sus 4 tablas y los datos de ejemplo.
4. Verificar la conexión en `config/database.php` (por defecto: host `127.0.0.1`,
   usuario `root`, sin contraseña — configuración estándar de XAMPP).
5. Comprobar que responde:

```
http://localhost/todocamisetas-api/public/api
```

Ese endpoint devuelve la versión de la API y la tabla completa de rutas registradas
con su expresión regular.

---

## 2. Estructura del proyecto

```
todocamisetas-api/
├── config/
│   └── database.php                  Conexión PDO singleton
├── app/
│   ├── core/
│   │   ├── Router.php                Enrutamiento por expresiones regulares
│   │   └── Response.php              Salida JSON + códigos HTTP
│   ├── models/
│   │   ├── Camiseta.php              CRUD + JOIN + regla de precio final
│   │   ├── Cliente.php               CRUD + reglas de oferta
│   │   ├── Talla.php                 CRUD del catálogo
│   │   └── CamisetaTalla.php         Tabla pivote (muchos a muchos)
│   └── controllers/
│       ├── CamisetaController.php    Métodos estáticos
│       ├── ClienteController.php
│       └── TallaController.php
├── public/
│   ├── index.php                     Front controller y tabla de rutas
│   └── .htaccess                     Reescritura de URLs
├── docs/
│   ├── openapi.yaml                  Documentación Swagger/OpenAPI
│   ├── todocamisetas.postman_collection.json
│   ├── curl_ejemplos.md              Ejemplos cURL de todos los endpoints
│   ├── diagrama_arquitectura.png
│   └── modelo_datos.png
├── database.sql
├── EVIDENCIA_PRUEBAS.md
└── README.md
```

### Función de cada componente

| Componente | Responsabilidad |
|---|---|
| `public/index.php` | Front controller: único punto de entrada. Carga dependencias, declara la tabla de rutas y delega en el Router. |
| `public/.htaccess` | Redirige toda petición que no sea un archivo real hacia `index.php`. |
| `app/core/Router.php` | Compara la URI con expresiones regulares e invoca el método estático del controlador que corresponda. Devuelve 404 si la ruta no existe y 405 si el verbo HTTP no aplica. |
| `app/core/Response.php` | Punto único de salida. Garantiza `Content-Type: application/json` en todas las respuestas y uniforma su estructura. |
| `app/controllers/` | Validan la entrada, aplican reglas de negocio y eligen el código HTTP. No contienen SQL. |
| `app/models/` | Único lugar donde se escribe SQL. Sentencias preparadas y transacciones. |
| `config/database.php` | Crea y reutiliza la conexión PDO (patrón singleton). |

---

## 3. Modelo de datos

Cuatro tablas en MySQL/InnoDB:

| Tabla | Rol |
|---|---|
| `clientes` | Tiendas minoristas B2B. Incluye `categoria` (Regular/Preferencial) y `porcentaje_oferta`. |
| `camisetas` | Inventario. Incluye `precio`, `precio_oferta` (nullable) y `codigo_producto` único. |
| `tallas` | Catálogo de tallas reutilizable entre productos. |
| `camiseta_tallas` | **Tabla pivote** que resuelve la relación muchos a muchos y guarda el `stock`. |

Relaciones:

- `clientes` **1 → N** `camisetas` mediante `camisetas.cliente_id`, con `ON DELETE RESTRICT`
  para impedir borrar un cliente que aún tiene productos asignados.
- `camisetas` **N ↔ N** `tallas` mediante `camiseta_tallas`, con clave primaria compuesta
  `(camiseta_id, talla_id)` y `ON DELETE CASCADE` en ambas claves foráneas.

Ver `docs/modelo_datos.png`.

---

## 4. Endpoints

Base: `http://localhost/todocamisetas-api/public`

### Camisetas

| Método | Ruta | Expresión regular | Propósito |
|---|---|---|---|
| GET | `/api/camisetas` | `#^/api/camisetas$#` | Listar con filtros y paginación |
| GET | `/api/camisetas/{id}` | `#^/api/camisetas/(?P<id>\d+)$#` | Ver una camiseta con su precio final |
| POST | `/api/camisetas` | `#^/api/camisetas$#` | Crear (admite tallas en el mismo cuerpo) |
| PUT | `/api/camisetas/{id}` | `#^/api/camisetas/(?P<id>\d+)$#` | Actualizar completo |
| PATCH | `/api/camisetas/{id}` | `#^/api/camisetas/(?P<id>\d+)$#` | Actualizar parcial |
| DELETE | `/api/camisetas/{id}` | `#^/api/camisetas/(?P<id>\d+)$#` | Eliminar |

### Clientes

| Método | Ruta | Expresión regular | Propósito |
|---|---|---|---|
| GET | `/api/clientes` | `#^/api/clientes$#` | Listar (filtro `?categoria=`) |
| GET | `/api/clientes/{id}` | `#^/api/clientes/(?P<id>\d+)$#` | Ver un cliente |
| GET | `/api/clientes/{id}/camisetas` | `#^/api/clientes/(?P<id>\d+)/camisetas$#` | Camisetas del cliente con precio final |
| POST | `/api/clientes` | `#^/api/clientes$#` | Crear |
| PUT | `/api/clientes/{id}` | `#^/api/clientes/(?P<id>\d+)$#` | Actualizar completo |
| PATCH | `/api/clientes/{id}` | `#^/api/clientes/(?P<id>\d+)$#` | Actualizar parcial |
| DELETE | `/api/clientes/{id}` | `#^/api/clientes/(?P<id>\d+)$#` | Eliminar (409 si tiene camisetas) |

### Tallas

| Método | Ruta | Expresión regular | Propósito |
|---|---|---|---|
| GET | `/api/tallas` | `#^/api/tallas$#` | Listar catálogo |
| GET | `/api/tallas/{id}` | `#^/api/tallas/(?P<id>\d+)$#` | Ver una talla |
| POST | `/api/tallas` | `#^/api/tallas$#` | Crear |
| PUT | `/api/tallas/{id}` | `#^/api/tallas/(?P<id>\d+)$#` | Actualizar completo |
| PATCH | `/api/tallas/{id}` | `#^/api/tallas/(?P<id>\d+)$#` | Actualizar parcial |
| DELETE | `/api/tallas/{id}` | `#^/api/tallas/(?P<id>\d+)$#` | Eliminar (cascada) |

### Relación camiseta ↔ tallas (muchos a muchos)

| Método | Ruta | Expresión regular | Propósito |
|---|---|---|---|
| GET | `/api/camisetas/{id}/tallas` | `#^/api/camisetas/(?P<id>\d+)/tallas$#` | Listar tallas y stock |
| POST | `/api/camisetas/{id}/tallas` | `#^/api/camisetas/(?P<id>\d+)/tallas$#` | Asignar una talla |
| PUT | `/api/camisetas/{id}/tallas/{tallaId}` | `#^/api/camisetas/(?P<id>\d+)/tallas/(?P<tallaId>\d+)$#` | Actualizar stock |
| PATCH | `/api/camisetas/{id}/tallas/{tallaId}` | `#^/api/camisetas/(?P<id>\d+)/tallas/(?P<tallaId>\d+)$#` | Actualizar stock parcial |
| DELETE | `/api/camisetas/{id}/tallas/{tallaId}` | `#^/api/camisetas/(?P<id>\d+)/tallas/(?P<tallaId>\d+)$#` | Quitar la talla |

Documentación completa con request, response y errores: `docs/openapi.yaml`
(importable en [Swagger Editor](https://editor.swagger.io)).

---

## 5. Regla de precio final

El caso plantea dos mecanismos de descuento, por lo que se definió el siguiente
**orden de precedencia**, implementado en `Camiseta::calcularPrecioFinal()`:

1. Cliente **Preferencial** y camiseta con `precio_oferta` definido
   → `precio_final = precio_oferta`
2. Cliente con `porcentaje_oferta` mayor a cero
   → `precio_final = precio − (precio × porcentaje / 100)`
3. Cualquier otro caso (cliente Regular sin porcentaje, sin oferta, o sin `cliente_id`)
   → `precio_final = precio` (precio base de lista)

Ejemplo con los dos clientes del caso sobre la camiseta 1 (precio 45.000, oferta 38.000):

| Cliente | Categoría | Resultado |
|---|---|---|
| `90minutos` (id 1) | Preferencial | `precio_final = 38000` (regla 1) |
| `tdeportes` (id 2) | Regular, 0% | `precio_final = 45000` (regla 3) |

La respuesta incluye un bloque `precio_detalle` que indica de qué regla proviene el
precio, para que el frontend pueda mostrarlo al usuario.

---

## 6. Pruebas

- **Postman**: importar `docs/todocamisetas.postman_collection.json`
  (31 peticiones organizadas en 5 carpetas, con ejemplos de respuesta guardados).
- **cURL**: ver `docs/curl_ejemplos.md`.
- **Evidencia**: `EVIDENCIA_PRUEBAS.md` contiene los casos ejecutados con sus
  resultados reales y los errores detectados durante el desarrollo.

---

## 7. Decisiones técnicas

- **PHP puro con patrón MVC + front controller.** Sin frameworks, según exige el enunciado,
  pero manteniendo la separación de responsabilidades: los controladores no escriben SQL
  y los modelos no deciden códigos HTTP.
- **Enrutamiento por expresiones regulares** con métodos estáticos. Las rutas anidadas
  (`/api/camisetas/{id}/tallas`) se registran antes que las generales, porque el Router
  devuelve la primera coincidencia.
- **PDO con sentencias preparadas** en todas las consultas, lo que elimina el riesgo de
  inyección SQL.
- **Transacciones** en `create`, `update` y `delete`. Al crear una camiseta con sus tallas,
  si falla la asignación de una talla se revierte también la inserción del producto.
- **Prevención del problema N+1**: al listar camisetas, las tallas de todos los productos
  se cargan en una sola consulta (`CamisetaTalla::deVariasCamisetas`) en lugar de una
  consulta por camiseta.
- **Paginación** con `LIMIT`/`OFFSET` y tope de 50 registros por página.
- **Validación en dos capas**: a nivel de aplicación (mensajes claros, códigos 400/404/409/422)
  y a nivel de base de datos (`UNIQUE`, `CHECK`, claves foráneas) como red de seguridad.
- **Traducción de errores de integridad**: un `PDOException` con SQLSTATE `23000` se
  convierte en un 409 con mensaje explicativo, en lugar de un 500 genérico.
- **CORS habilitado** para permitir el consumo desde el frontend.

---

## 8. Uso de Inteligencia Artificial

Este proyecto fue desarrollado con apoyo de **Claude** (Anthropic) en la generación de la
estructura del código PHP/PDO, la documentación OpenAPI, la colección Postman y los
diagramas. El diseño del modelo de datos, las decisiones de arquitectura, la definición de
la regla de precedencia de precios y la validación del cumplimiento de los requisitos del
caso son responsabilidad del estudiante.
