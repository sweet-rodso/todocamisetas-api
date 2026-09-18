<?php
/**
 * Front Controller - Punto de entrada unico de la API
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 * Instituto Profesional San Sebastian
 * Autor: Rodrigo Alexis Soto Cifuentes
 *
 * Todas las peticiones pasan por este archivo gracias a la reescritura de
 * URLs definida en .htaccess. Aqui se cargan las dependencias, se declara
 * la tabla de rutas con sus expresiones regulares y se delega el despacho
 * al Router.
 *
 * Tener un unico punto de entrada permite centralizar las cabeceras CORS,
 * el manejo de errores y el registro de rutas, en lugar de repetir esa
 * logica en cada archivo PHP del proyecto.
 */

// --- Cabeceras CORS: permiten que el frontend consuma la API ----------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Las peticiones de verificacion previa del navegador se responden aqui.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// --- Carga de dependencias -------------------------------------------
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../app/core/Response.php';
require_once __DIR__ . '/../app/core/Router.php';

require_once __DIR__ . '/../app/models/Cliente.php';
require_once __DIR__ . '/../app/models/Talla.php';
require_once __DIR__ . '/../app/models/CamisetaTalla.php';
require_once __DIR__ . '/../app/models/Camiseta.php';

require_once __DIR__ . '/../app/controllers/ClienteController.php';
require_once __DIR__ . '/../app/controllers/CamisetaController.php';
require_once __DIR__ . '/../app/controllers/TallaController.php';

$router = new Router();

// =====================================================================
// TABLA DE RUTAS
// ---------------------------------------------------------------------
// Cada ruta declara la expresion regular con la que se compara la URI.
// Los grupos de captura (\d+) corresponden a los identificadores que
// recibe el controlador.
//
// IMPORTANTE: las rutas mas especificas se registran ANTES que las mas
// generales. Si /api/camisetas/(\d+)$ se registrara primero, nunca se
// alcanzarian las rutas anidadas de tallas.
// =====================================================================

// --- Camisetas: relacion con tallas (rutas anidadas, van primero) -----
$router->get(
    '#^/api/camisetas/(?P<id>\d+)/tallas$#',
    'TallaController@deCamiseta',
    'Lista las tallas asignadas a una camiseta con su stock'
);
$router->post(
    '#^/api/camisetas/(?P<id>\d+)/tallas$#',
    'TallaController@asignar',
    'Asigna una talla del catalogo a una camiseta'
);
$router->put(
    '#^/api/camisetas/(?P<id>\d+)/tallas/(?P<tallaId>\d+)$#',
    'TallaController@actualizarStock',
    'Actualiza el stock de una talla en una camiseta'
);
$router->patch(
    '#^/api/camisetas/(?P<id>\d+)/tallas/(?P<tallaId>\d+)$#',
    'TallaController@actualizarStock',
    'Actualiza parcialmente el stock de una talla en una camiseta'
);
$router->delete(
    '#^/api/camisetas/(?P<id>\d+)/tallas/(?P<tallaId>\d+)$#',
    'TallaController@quitar',
    'Quita una talla de una camiseta'
);

// --- Camisetas: CRUD -------------------------------------------------
$router->get(
    '#^/api/camisetas$#',
    'CamisetaController@index',
    'Lista camisetas con filtros (club, tipo, cliente_id) y paginacion'
);
$router->get(
    '#^/api/camisetas/(?P<id>\d+)$#',
    'CamisetaController@show',
    'Obtiene una camiseta con sus tallas y el precio final segun cliente_id'
);
$router->post(
    '#^/api/camisetas$#',
    'CamisetaController@store',
    'Crea una camiseta, opcionalmente con sus tallas'
);
$router->put(
    '#^/api/camisetas/(?P<id>\d+)$#',
    'CamisetaController@update',
    'Actualiza por completo una camiseta'
);
$router->patch(
    '#^/api/camisetas/(?P<id>\d+)$#',
    'CamisetaController@update',
    'Actualiza parcialmente una camiseta'
);
$router->delete(
    '#^/api/camisetas/(?P<id>\d+)$#',
    'CamisetaController@destroy',
    'Elimina una camiseta y en cascada sus asignaciones de tallas'
);

// --- Clientes --------------------------------------------------------
$router->get(
    '#^/api/clientes/(?P<id>\d+)/camisetas$#',
    'ClienteController@camisetas',
    'Lista las camisetas de un cliente con su precio final'
);
$router->get(
    '#^/api/clientes$#',
    'ClienteController@index',
    'Lista los clientes B2B, con filtro opcional por categoria'
);
$router->get(
    '#^/api/clientes/(?P<id>\d+)$#',
    'ClienteController@show',
    'Obtiene un cliente por su identificador'
);
$router->post(
    '#^/api/clientes$#',
    'ClienteController@store',
    'Crea un cliente B2B'
);
$router->put(
    '#^/api/clientes/(?P<id>\d+)$#',
    'ClienteController@update',
    'Actualiza por completo un cliente'
);
$router->patch(
    '#^/api/clientes/(?P<id>\d+)$#',
    'ClienteController@update',
    'Actualiza parcialmente un cliente'
);
$router->delete(
    '#^/api/clientes/(?P<id>\d+)$#',
    'ClienteController@destroy',
    'Elimina un cliente si no tiene camisetas asignadas'
);

// --- Tallas: catalogo ------------------------------------------------
$router->get(
    '#^/api/tallas$#',
    'TallaController@index',
    'Lista el catalogo de tallas'
);
$router->get(
    '#^/api/tallas/(?P<id>\d+)$#',
    'TallaController@show',
    'Obtiene una talla del catalogo'
);
$router->post(
    '#^/api/tallas$#',
    'TallaController@store',
    'Crea una talla en el catalogo'
);
$router->put(
    '#^/api/tallas/(?P<id>\d+)$#',
    'TallaController@update',
    'Actualiza por completo una talla'
);
$router->patch(
    '#^/api/tallas/(?P<id>\d+)$#',
    'TallaController@update',
    'Actualiza parcialmente una talla'
);
$router->delete(
    '#^/api/tallas/(?P<id>\d+)$#',
    'TallaController@destroy',
    'Elimina una talla y en cascada sus asignaciones'
);

// --- Raiz: descubrimiento de la API ----------------------------------
$router->get('#^/$#',    'ApiController@bienvenida', 'Informacion general de la API');
$router->get('#^/api$#', 'ApiController@bienvenida', 'Informacion general y tabla de rutas');

/**
 * Controlador auxiliar de descubrimiento.
 *
 * Permite comprobar que la API responde y consultar la tabla de rutas
 * registradas sin salir del navegador.
 */
class ApiController
{
    public static array $rutas = [];

    public static function bienvenida(array $parametros = []): void
    {
        Response::exito(200, [
            'api'     => APP_NAME,
            'version' => APP_VERSION,
            'autor'   => 'Rodrigo Alexis Soto Cifuentes',
            'mensaje' => 'API RESTful de gestion de inventario y clientes B2B.',
            'rutas'   => self::$rutas,
        ]);
    }
}

ApiController::$rutas = $router->tablaDeRutas();

// --- Despacho --------------------------------------------------------
// Se envuelve en try/catch para que ningun error inesperado (por ejemplo
// una base de datos apagada) devuelva HTML de PHP al cliente.
try {
    $router->despachar();

} catch (PDOException $e) {
    Response::error(500, 'No fue posible conectar con la base de datos. ' .
        'Verifique que MySQL este iniciado y que la base "todocamisetas" exista. Detalle: ' . $e->getMessage());

} catch (Throwable $e) {
    Response::error(500, 'Error inesperado en el servidor: ' . $e->getMessage());
}
