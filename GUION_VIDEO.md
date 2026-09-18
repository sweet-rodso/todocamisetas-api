# Guion del video explicativo — Examen Transversal Final

**Proyecto:** TodoCamisetas API
**Asignatura:** Desarrollo Backend (IF201IINF)
**Estudiante:** Rodrigo Alexis Soto Cifuentes

---

## Antes de grabar — lista de verificación

- [ ] Apache y MySQL iniciados en XAMPP (ambos en verde).
- [ ] `database.sql` importado; la base `todocamisetas` tiene 4 tablas con datos.
- [ ] Colección `docs/todocamisetas.postman_collection.json` importada en Postman.
- [ ] Editor de código abierto con el proyecto (VS Code, PhpStorm o similar).
- [ ] phpMyAdmin abierto en una pestaña.
- [ ] Cerrar pestañas, notificaciones y ventanas ajenas al proyecto.
- [ ] Probar el micrófono grabando 10 segundos y escuchándolos.
- [ ] Grabar en 1080p si es posible. OBS Studio ya está instalado en el equipo.

**Duración objetivo: 8 a 12 minutos.** La rúbrica premia que se expliquen *todas*
las funcionalidades y el control de errores, no que el video sea corto.

---

## Estructura del video

| Bloque | Tiempo | Indicador de la rúbrica |
|---|---|---|
| 1. Presentación | 0:00 – 0:40 | — |
| 2. Arquitectura y estructura de carpetas | 0:40 – 2:30 | 1.1.1 |
| 3. Modelo de datos en phpMyAdmin | 2:30 – 4:00 | 1.1.1 |
| 4. Enrutamiento con expresiones regulares | 4:00 – 5:30 | 1.2.1 |
| 5. CRUD de camisetas y clientes | 5:30 – 7:30 | 2.1.1 / 2.2.3 / 3.1.1 |
| 6. Relación muchos a muchos (tallas) | 7:30 – 9:00 | 3.1.1 |
| 7. Precio final según cliente | 9:00 – 10:30 | 3.2 |
| 8. Control de errores | 10:30 – 11:30 | 1.2.1 / 2.2.3 |
| 9. Cierre | 11:30 – 12:00 | — |

---

## Bloque 1 — Presentación (0:00 – 0:40)

**En pantalla:** el README.md abierto, o el diagrama de arquitectura.

> "Hola, soy Rodrigo Soto Cifuentes y este es mi Examen Transversal Final de
> Desarrollo Backend. Voy a presentar la API RESTful que desarrollé para
> TodoCamisetas, un proveedor mayorista de camisetas de fútbol que necesita
> gestionar su inventario y su relación con tiendas minoristas.
>
> La API está hecha en PHP puro, sin frameworks, tal como pide el enunciado, y
> se conecta a una base de datos MySQL. Permite administrar camisetas, clientes
> y tallas, y calcula dinámicamente el precio final de cada producto según el
> cliente que lo consulta."

---

## Bloque 2 — Arquitectura y estructura de carpetas (0:40 – 2:30)

**En pantalla:** `docs/diagrama_arquitectura.png` y luego el árbol de carpetas
en el editor.

> "Empiezo por la arquitectura. Usé el patrón MVC con un front controller.
>
> Todas las peticiones entran por `public/index.php`. El `.htaccess` redirige
> cualquier URL que no sea un archivo real hacia ese archivo, así tengo un
> único punto de entrada donde centralizo las cabeceras CORS y el manejo de
> errores, en vez de repetir esa lógica en cada archivo.
>
> Desde ahí el `Router` decide qué controlador atiende la petición. Los
> controladores validan los datos y eligen el código HTTP, pero no escriben
> SQL. Todo el SQL vive en los modelos. Y los modelos se conectan a MySQL a
> través de `config/database.php`."

**Mostrar cada carpeta en el editor mientras se explica:**

> "En `app/core` están el Router y la clase Response. Puse la salida JSON en
> una clase aparte para garantizar que absolutamente todas las respuestas
> lleven la cabecera `Content-Type: application/json`, sin depender de que yo
> me acuerde de ponerla en cada controlador.
>
> En `app/models` tengo cuatro modelos, uno por tabla. En `app/controllers`,
> tres controladores. Y en `docs` está toda la documentación: el archivo
> OpenAPI para Swagger, la colección de Postman y los ejemplos con cURL."

**Abrir `config/database.php`:**

> "Elegí PDO en vez de mysqli por tres razones: permite sentencias preparadas
> con parámetros nombrados, que eliminan el riesgo de inyección SQL; soporta
> transacciones de forma nativa, que necesito para las operaciones de
> escritura; y es agnóstico del motor, así que si algún día cambio de MySQL a
> otro gestor solo tengo que tocar esta línea del DSN.
>
> Además uso el patrón singleton: la conexión se crea una sola vez por
> petición y se reutiliza."

---

## Bloque 3 — Modelo de datos (2:30 – 4:00)

**En pantalla:** `docs/modelo_datos.png`, después phpMyAdmin.

> "El modelo tiene cuatro tablas.
>
> `clientes` guarda las tiendas minoristas. El campo clave acá es `categoria`,
> que define si el cliente es Regular o Preferencial. Lo definí como ENUM y no
> como VARCHAR para que sea la propia base de datos la que garantice que solo
> existan esos dos valores.
>
> `camisetas` es el inventario. El precio y el precio de oferta son DECIMAL,
> no FLOAT, porque los valores monetarios necesitan precisión exacta: FLOAT
> introduce errores de redondeo. `codigo_producto` es el SKU y tiene
> restricción UNIQUE.
>
> `tallas` es un catálogo aparte. Podría haber guardado las tallas como texto
> dentro de camisetas, pero eso duplicaría información y haría imposible
> consultar el stock por talla."

**Abrir la tabla `camiseta_tallas` en phpMyAdmin y mostrar su estructura:**

> "Y acá está la parte central: `camiseta_tallas`, la tabla pivote que resuelve
> la relación muchos a muchos. Una camiseta está disponible en varias tallas, y
> una talla pertenece a muchas camisetas.
>
> Tiene clave primaria compuesta por `camiseta_id` y `talla_id`, lo que impide
> asignar dos veces la misma talla al mismo producto. Las dos claves foráneas
> están con `ON DELETE CASCADE`: si borro una camiseta, sus asignaciones de
> talla desaparecen solas y no quedan filas huérfanas.
>
> Además guarda un atributo propio de la relación: el `stock` de esa camiseta
> en esa talla específica."

**Mostrar la relación con clientes:**

> "La relación entre clientes y camisetas es uno a muchos, con `ON DELETE
> RESTRICT`. Eso implementa a nivel de base de datos la regla de negocio de que
> no se puede eliminar un cliente que todavía tiene camisetas asignadas."

---

## Bloque 4 — Enrutamiento con expresiones regulares (4:00 – 5:30)

**En pantalla:** `public/index.php`, después `app/core/Router.php`.

> "El enrutamiento se hace con expresiones regulares. Acá en `index.php` está
> la tabla de rutas: 26 rutas en total. Cada una declara el método HTTP, la
> expresión regular y el controlador con su método estático."

**Señalar una ruta simple y una anidada:**

> "Por ejemplo, esta expresión —`#^/api/camisetas/(?P<id>\d+)$#`— captura el id
> como un grupo con nombre. El `\d+` obliga a que sea numérico, así que una
> URL como `/api/camisetas/abc` simplemente no calza con ninguna ruta.
>
> Y esta otra es la ruta anidada de tallas, con dos grupos de captura: el id de
> la camiseta y el de la talla.
>
> Un detalle importante: las rutas anidadas se registran **antes** que las
> generales. Si registrara `/api/camisetas/(\d+)$` primero, nunca se alcanzarían
> las rutas de tallas, porque el Router devuelve la primera coincidencia."

**Abrir `Router.php` y mostrar `despachar()`:**

> "El Router recorre la tabla comparando la URI con `preg_match`. Si encuentra
> coincidencia, extrae los parámetros de los grupos de captura y llama al
> método estático del controlador con `call_user_func`.
>
> Si la URI calza pero el verbo HTTP no corresponde, devuelvo 405 con la
> cabecera `Allow` indicando qué métodos sí se aceptan. Distinguir entre 404 y
> 405 le da información mucho más útil a quien consume la API."

**Mostrar en el navegador `http://localhost/todocamisetas-api/public/api`:**

> "De hecho, la propia API expone su tabla de rutas en este endpoint, así que
> se puede verificar sin salir del navegador."

---

## Bloque 5 — CRUD de camisetas y clientes (5:30 – 7:30)

**En pantalla:** Postman, carpeta "1. Camisetas (CRUD)".

Ejecutar **en este orden**, comentando cada respuesta:

1. **GET Listar camisetas**
   > "Listado paginado. Devuelve los datos más un bloque `meta` con el total y
   > el número de páginas. La paginación no es cosmética: evita traer la tabla
   > completa cuando el inventario crezca."

2. **POST Crear camiseta**
   > "Creo una camiseta nueva. Fíjense que en el mismo cuerpo puedo mandar el
   > arreglo de tallas con su stock. Eso se ejecuta dentro de una transacción:
   > si falla la asignación de una talla, se revierte también la inserción del
   > producto. O se guarda todo completo, o no se guarda nada."
   > Responde **201 Created**.

3. **PUT Actualizar camiseta** → 200 OK.

4. **PATCH Actualizar camiseta**
   > "Acá mando solo el precio de oferta. Los demás campos se conservan: el
   > modelo construye el SET dinámicamente con los campos que efectivamente
   > vienen en el cuerpo."

5. **DELETE Eliminar camiseta** → 200 OK.

**Cambiar a la carpeta "2. Clientes":**

6. **GET Listar clientes** y **GET Ver cliente**.

7. **GET Listar camisetas de un cliente**
   > "Este endpoint cruza las dos entidades: devuelve el catálogo asignado al
   > cliente, ya con el precio que le corresponde a él."

> "En todos los casos la respuesta tiene la misma estructura —`status`, `code`,
> `data`— y viaja con `Content-Type: application/json`, que se puede verificar
> en la pestaña Headers de Postman."

**Mostrar la pestaña Headers de alguna respuesta.**

---

## Bloque 6 — Relación muchos a muchos (7:30 – 9:00)

**En pantalla:** Postman, carpeta "4. Camiseta-Tallas".

1. **GET Listar tallas de una camiseta**
   > "Acá veo las tallas de la camiseta 1 con el stock de cada una. Esto sale
   > de un JOIN entre la tabla pivote y el catálogo de tallas."

2. **POST Asignar talla** → **201 Created**.

3. **POST Asignar la misma talla otra vez** → **409 Conflict**
   > "Si intento asignar una talla que ya está asignada, devuelvo 409 con un
   > mensaje que explica qué pasó y qué hacer. Podría haber dejado que estallara
   > la clave primaria compuesta, pero eso le llegaría al cliente como un error
   > 500 incomprensible."

4. **POST con `talla_id` inexistente** → **404 Not Found**.

5. **PUT Actualizar stock** → 200 OK.

6. **PUT con stock negativo** → **422**.

7. **DELETE Quitar talla** → 200 OK.

> "Y para cerrar la cascada: si elimino una camiseta completa, todas sus filas
> en `camiseta_tallas` se borran automáticamente por el `ON DELETE CASCADE`."

**Opcional pero recomendado:** eliminar una camiseta y mostrar en phpMyAdmin
que las filas del pivote desaparecieron.

---

## Bloque 7 — Precio final según el cliente (9:00 – 10:30)

Este es el bloque con más puntaje. **No apurarlo.**

**En pantalla:** Postman, después el código.

1. **GET `/api/camisetas/1?cliente_id=1`** (90minutos, Preferencial)
   > "La camiseta 1 tiene precio base 45.000 y precio de oferta 38.000.
   > Consulto como 90minutos, que es cliente Preferencial: el `precio_final`
   > que devuelve es 38.000, el precio de oferta."

2. **GET `/api/camisetas/1?cliente_id=2`** (tdeportes, Regular)
   > "Ahora la misma camiseta, pero consultando como tdeportes, que es cliente
   > Regular. El `precio_final` es 45.000, el precio base. La oferta existe,
   > pero este cliente no accede a ella."

> "Además devuelvo un bloque `precio_detalle` que dice de qué regla salió el
> precio y cuánto fue el descuento, para que el frontend pueda mostrárselo al
> usuario."

**Abrir `app/models/Camiseta.php`, método `calcularPrecioFinal()`:**

> "Acá está la lógica. El enunciado plantea dos mecanismos de descuento: el
> `precio_oferta` por producto y el `porcentaje_oferta` por cliente. Como
> podían entrar en conflicto, definí un orden de precedencia explícito y lo
> documenté:
>
> Primero: si el cliente es Preferencial y la camiseta tiene precio de oferta,
> gana el precio de oferta.
> Segundo: si no, y el cliente tiene un porcentaje de descuento, aplico ese
> porcentaje sobre el precio base.
> Tercero: en cualquier otro caso, precio de lista.
>
> Comprobar explícitamente que `precio_oferta` no sea NULL es importante,
> porque si no un producto sin oferta devolvería precio final cero."

**Mostrar el método `all()` o `selectBase()`:**

> "Sobre rendimiento: la consulta trae el cliente con un JOIN en la misma
> sentencia, en lugar de hacer una consulta extra por cada camiseta. Y las
> tallas de todo el listado se cargan en una sola consulta agrupada. Con 50
> camisetas eso pasa de 51 consultas a 2. Es el problema N+1, y así lo evito."

**Mostrar un método `create()` con transacción:**

> "Y todas las operaciones de escritura van dentro de una transacción con
> `beginTransaction`, `commit` y `rollBack` en el catch."

---

## Bloque 8 — Control de errores (10:30 – 11:30)

Ejecutar seguido, comentando el código HTTP de cada uno:

| Petición | Código esperado |
|---|---|
| POST camiseta sin campos obligatorios | **422** con lista de errores |
| POST camiseta con SKU repetido | **409** |
| GET `/api/camisetas/999` | **404** |
| DELETE cliente con camisetas asignadas | **409** con `camisetas_asociadas` |
| GET `/api/zapatillas` | **404** ruta inexistente |
| DELETE `/api/camisetas` (sin id) | **405** con cabecera `Allow` |
| POST con JSON malformado | **400** |

> "El manejo de errores fue una decisión de diseño, no un agregado. Cada
> situación tiene su código: 400 para peticiones mal formadas, 404 para lo que
> no existe, 409 para conflictos de integridad, 422 para validación y 405
> cuando la ruta existe pero el verbo no aplica.
>
> Y como red de seguridad, cualquier excepción de PDO con SQLSTATE 23000 —que
> son las violaciones de integridad— la traduzco a un 409 con mensaje
> explicativo en lugar de dejar que se convierta en un 500 genérico."

**Mostrar el método `manejarErrorBD()` en cualquier controlador.**

---

## Bloque 9 — Cierre (11:30 – 12:00)

**En pantalla:** `docs/openapi.yaml` en Swagger Editor, o el README.

> "Para terminar: toda la API está documentada en formato OpenAPI, con los 24
> endpoints, sus parámetros, los cuerpos de request y response y todos los
> códigos de error posibles. Se puede abrir en Swagger Editor.
>
> También dejé la colección de Postman lista para importar, con ejemplos de
> respuesta guardados, y un archivo con los ejemplos equivalentes en cURL.
>
> Eso es todo. Gracias."

---

## Errores a evitar al grabar

- **No leer el guion palabra por palabra.** Úsalo como mapa; habla natural.
- **No mostrar código sin explicar la decisión detrás.** La rúbrica valora que
  expliques *por qué*, no que leas el código en voz alta.
- **No saltarse el control de errores.** Aparece en dos indicadores distintos.
- **Aumentar el zoom del editor** (Ctrl + `+`) para que el código se lea en el video.
- **Si te equivocas, no reinicies todo**: corrige en voz alta y sigue. Es más
  natural y ahorra tiempo.
