<?php
/**
 * Router - Enrutador REST basado en expresiones regulares
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Implementa el requisito de la Tarea 2: un enrutamiento en PHP puro que,
 * segun el metodo HTTP y la ruta solicitada, invoca METODOS ESTATICOS de
 * los controladores.
 *
 * Cada ruta se registra como un par (metodo HTTP, expresion regular). Al
 * llegar una peticion se recorre la tabla de rutas y se compara la URI con
 * cada patron mediante preg_match(). Los grupos de captura de la expresion
 * regular se convierten en los parametros que recibe el controlador.
 *
 * Ejemplo:
 *   Patron  : #^/api/camisetas/(\d+)$#
 *   Peticion: GET /api/camisetas/5
 *   Resultado: CamisetaController::show(['id' => '5'])
 *
 * La tabla completa de rutas con su expresion regular esta documentada en
 * el informe y puede consultarse en tiempo de ejecucion en GET /api.
 */
class Router
{
    /** @var array<int, array{metodo:string, patron:string, destino:string, descripcion:string}> */
    private array $rutas = [];

    /**
     * Registra una ruta en la tabla de enrutamiento.
     *
     * @param string $metodo      Metodo HTTP (GET, POST, PUT, PATCH, DELETE)
     * @param string $patron      Expresion regular que debe calzar con la URI
     * @param string $destino     Controlador y metodo estatico, formato "Clase@metodo"
     * @param string $descripcion Proposito del endpoint (se usa para documentar)
     */
    public function registrar(string $metodo, string $patron, string $destino, string $descripcion = ''): void
    {
        $this->rutas[] = [
            'metodo'      => strtoupper($metodo),
            'patron'      => $patron,
            'destino'     => $destino,
            'descripcion' => $descripcion,
        ];
    }

    // Atajos por metodo HTTP, para que el registro de rutas se lea con claridad.
    public function get(string $patron, string $destino, string $desc = ''): void    { $this->registrar('GET', $patron, $destino, $desc); }
    public function post(string $patron, string $destino, string $desc = ''): void   { $this->registrar('POST', $patron, $destino, $desc); }
    public function put(string $patron, string $destino, string $desc = ''): void    { $this->registrar('PUT', $patron, $destino, $desc); }
    public function patch(string $patron, string $destino, string $desc = ''): void  { $this->registrar('PATCH', $patron, $destino, $desc); }
    public function delete(string $patron, string $destino, string $desc = ''): void { $this->registrar('DELETE', $patron, $destino, $desc); }

    /**
     * Devuelve la tabla de rutas registradas (usada por el endpoint GET /api).
     */
    public function tablaDeRutas(): array
    {
        return array_map(static function (array $ruta): array {
            return [
                'metodo'      => $ruta['metodo'],
                'expresion'   => $ruta['patron'],
                'controlador' => $ruta['destino'],
                'proposito'   => $ruta['descripcion'],
            ];
        }, $this->rutas);
    }

    /**
     * Resuelve la peticion entrante y ejecuta el controlador correspondiente.
     *
     * Devuelve 404 si ninguna ruta calza y 405 si la ruta existe pero fue
     * invocada con un metodo HTTP distinto al permitido. Distinguir ambos
     * casos entrega un mensaje de error mucho mas util al consumidor de la API.
     */
    public function despachar(): void
    {
        $metodo = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->normalizarUri();

        $metodosPermitidos = [];

        foreach ($this->rutas as $ruta) {
            if (preg_match($ruta['patron'], $uri, $coincidencias)) {

                // La URI calza con el patron pero el verbo HTTP no corresponde.
                if ($ruta['metodo'] !== $metodo) {
                    $metodosPermitidos[] = $ruta['metodo'];
                    continue;
                }

                $parametros = $this->extraerParametros($coincidencias);
                $this->invocar($ruta['destino'], $parametros);
                return;
            }
        }

        if (!empty($metodosPermitidos)) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_unique($metodosPermitidos)));
            }
            Response::error(405, sprintf(
                'El metodo %s no esta permitido en esta ruta. Metodos disponibles: %s.',
                $metodo,
                implode(', ', array_unique($metodosPermitidos))
            ));
            return;
        }

        Response::error(404, sprintf('La ruta %s %s no existe en esta API.', $metodo, $uri));
    }

    /**
     * Obtiene la ruta solicitada sin el query string ni el subdirectorio
     * donde este instalado el proyecto.
     *
     * Con XAMPP la URL real es
     *   /todocamisetas-api/public/api/camisetas
     * y esta funcion la reduce a
     *   /api/camisetas
     * para que las expresiones regulares no dependan de la ubicacion fisica
     * del proyecto dentro de htdocs.
     */
    private function normalizarUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }

        $uri = rtrim($uri, '/');

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Convierte los grupos capturados por la expresion regular en un arreglo
     * de parametros con nombre.
     *
     * Se aceptan tanto grupos con nombre (?P<id>\d+) como grupos numerados,
     * que se mapean por convencion a 'id' y luego 'tallaId'.
     */
    private function extraerParametros(array $coincidencias): array
    {
        $parametros = [];

        foreach ($coincidencias as $clave => $valor) {
            if (!is_int($clave)) {
                $parametros[$clave] = $valor;
            }
        }

        if (empty($parametros)) {
            $posicionales = array_slice($coincidencias, 1);
            $nombres      = ['id', 'tallaId'];

            foreach ($posicionales as $indice => $valor) {
                $parametros[$nombres[$indice] ?? 'param' . $indice] = $valor;
            }
        }

        return $parametros;
    }

    /**
     * Ejecuta el metodo estatico del controlador indicado en el destino.
     *
     * @param string $destino    Formato "Clase@metodo"
     * @param array  $parametros Parametros extraidos de la ruta
     */
    private function invocar(string $destino, array $parametros): void
    {
        [$controlador, $accion] = explode('@', $destino);

        if (!class_exists($controlador)) {
            Response::error(500, sprintf('El controlador "%s" no se encuentra disponible.', $controlador));
            return;
        }

        if (!method_exists($controlador, $accion)) {
            Response::error(500, sprintf('El metodo "%s" no existe en el controlador "%s".', $accion, $controlador));
            return;
        }

        // Los controladores exponen metodos estaticos, tal como exige la Tarea 2.
        call_user_func([$controlador, $accion], $parametros);
    }
}
