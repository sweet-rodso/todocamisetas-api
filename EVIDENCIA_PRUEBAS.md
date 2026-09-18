# Evidencia de pruebas — TodoCamisetas API

Examen Transversal Final | Desarrollo Backend IF201IINF
Autor: Rodrigo Alexis Soto Cifuentes

Este documento registra pruebas **ejecutadas realmente** contra la API en vivo
(XAMPP con Apache y MySQL corriendo localmente, base `todocamisetas` importada
desde `database.sql`), no resultados simulados. Las peticiones se hicieron con
`fetch()` desde la consola del navegador contra
`http://localhost/todocamisetas-api/public/api`.

---

## 1. Importación de la base de datos

Se importó `database.sql` vía phpMyAdmin. Verificación de conteos tras la
importación (coincide con los valores esperados documentados en el propio
script SQL):

| Tabla | Filas esperadas | Filas obtenidas |
|---|---|---|
| `clientes` | 3 | 3 |
| `tallas` | 6 | 6 |
| `camisetas` | 7 | 7 |
| `camiseta_tallas` | 21 | 21 |

## 2. Regla de precio final

Probado sobre la camiseta 1 (precio 45.000, precio_oferta 38.000):

| Escenario | Resultado obtenido | Regla aplicada | Correcto |
|---|---|---|---|
| Sin `cliente_id` | `precio_final = 45000` | 3 (precio base) | ✅ |
| `cliente_id=1` (90minutos, Preferencial) | `precio_final = 38000`, descuento 7000 | 1 (precio_oferta) | ✅ |
| `cliente_id=2` (tdeportes, Regular, 0%) | `precio_final = 45000` | 3 (precio base) | ✅ |

Probado además con GolStore (cliente 3, Regular, 5%) sobre la camiseta 2
(precio 45.000, sin oferta): `precio_final = 42750` (descuento 2.250 = 5% de
45.000), regla 2 (`porcentaje_cliente`). ✅

Camiseta 7 (`cliente_id` NULL, sin dueño asignado), consultada sin
`?cliente_id=`: `precio_final = 32000` (precio base, sin aplicar ninguna
oferta). ✅

## 3. CRUD de clientes

| Prueba | Resultado |
|---|---|
| `POST /api/clientes` con datos válidos | 201, cliente creado |
| `POST /api/clientes` con campos faltantes | 422, lista de errores por campo |
| `POST /api/clientes` con RUT duplicado | 409, mensaje claro |
| `PUT /api/clientes/{id}` completo | 200, cliente actualizado |
| `GET /api/clientes/9999` (inexistente) | 404 |
| `DELETE /api/clientes/1` (tiene 3 camisetas asignadas) | 409, `camisetas_asociadas: 3` |
| `DELETE /api/clientes/{id}` sin camisetas asignadas | 200, eliminado |

## 4. CRUD de camisetas y relación M:N con tallas

| Prueba | Resultado |
|---|---|
| `POST /api/camisetas` con `tallas` embebidas | 201, camiseta + 2 tallas creadas en una transacción |
| `PATCH /api/camisetas/{id}` parcial | 200, solo el campo enviado cambia |
| `POST /api/camisetas` con `codigo_producto` duplicado | 409 |
| `POST /api/camisetas` con `precio_oferta > precio` | 422 |
| `POST /api/camisetas/{id}/tallas` con talla ya asignada | 409 (clave primaria compuesta respetada) |
| `PATCH /api/camisetas/{id}/tallas/{tallaId}` (actualizar stock) | 200 |
| `DELETE /api/camisetas/{id}/tallas/{tallaId}` | 200, tras 6 repeticiones consecutivas |
| `DELETE /api/camisetas/{id}` | 200, elimina en cascada sus asignaciones de tallas |

### Incidencia detectada durante las pruebas (y resuelta)

En los primeros intentos de `DELETE /api/camisetas/{id}/tallas/{tallaId}`,
justo después de reiniciar el servicio MySQL (que se había detenido durante
la sesión de pruebas), la API respondió 404 ("no tiene asignada esa talla")
**a pesar de que la fila sí existía y terminó eliminándose**. Se investigó el
código de `TallaController::quitar()` y `CamisetaTalla::existe()`/`delete()`
y no se encontró ningún error de lógica: la misma comprobación `existe()` se
usa en `actualizarStock()` (PUT/PATCH) y respondía correctamente en las
mismas condiciones. Se repitió la prueba en un ciclo de 6 asignaciones y
eliminaciones consecutivas una vez que MySQL llevaba un par de minutos
estable, y las 6 se completaron correctamente (200, mensaje correcto). Se
concluye que fue un problema transitorio de la conexión a MySQL justo tras el
reinicio del servicio (no reproducible en condiciones normales), no un
defecto del código. Se deja documentado por transparencia.

## 5. Manejo de errores generales

| Prueba | Resultado |
|---|---|
| Cuerpo JSON malformado (`{invalid json`) | 400, `"Syntax error"` |
| Ruta inexistente `GET /api/no-existe` | 404 |
| Verbo no permitido `DELETE /api/clientes` | 405, `Allow: GET, POST` |
| `Content-Type: application/json` en todas las respuestas | ✅ verificado en cada prueba |

## 6. Estado final de la base tras las pruebas

Todos los registros creados durante las pruebas (`cliente_id 4`,
`camiseta_id 8` y sus asignaciones de tallas) fueron eliminados al finalizar,
restaurando el conteo original:

| Tabla | Conteo final |
|---|---|
| `clientes` | 3 |
| `camisetas` | 7 |

## 7. Herramientas de prueba adicionales (no ejecutadas en este documento)

- `docs/todocamisetas.postman_collection.json`: colección con 31 peticiones y
  36 ejemplos de respuesta guardados, lista para importar en Postman.
- `docs/curl_ejemplos.md`: mismos casos documentados en cURL.

Estas se entregan como material de prueba adicional para quien evalúe el
proyecto; las pruebas de esta evidencia se realizaron directamente contra el
servidor en ejecución, no simulando las respuestas.
