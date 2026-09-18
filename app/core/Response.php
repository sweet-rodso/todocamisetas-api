<?php
/**
 * Response - Emision centralizada de respuestas HTTP en formato JSON
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Toda salida de la API pasa por esta clase. Centralizar el envio en un
 * unico punto garantiza que ABSOLUTAMENTE TODAS las respuestas lleven la
 * cabecera Content-Type: application/json (requisito de la Tarea 4) y que
 * la estructura del JSON sea identica en todos los endpoints, de modo que
 * el frontend pueda procesarlas siempre de la misma forma.
 */
class Response
{
    /**
     * Respuesta exitosa.
     *
     * @param int   $codigo  Codigo HTTP (200, 201, ...)
     * @param array $datos   Cuerpo de la respuesta
     */
    public static function exito(int $codigo, array $datos): void
    {
        self::enviar($codigo, array_merge([
            'status' => 'success',
            'code'   => $codigo,
        ], $datos));
    }

    /**
     * Respuesta de error.
     *
     * @param int    $codigo   Codigo HTTP (400, 404, 409, 422, 500)
     * @param string $mensaje  Descripcion legible del problema
     * @param array  $extra    Informacion adicional, por ejemplo la lista de errores de validacion
     */
    public static function error(int $codigo, string $mensaje, array $extra = []): void
    {
        self::enviar($codigo, array_merge([
            'status'  => 'error',
            'code'    => $codigo,
            'message' => $mensaje,
        ], $extra));
    }

    /**
     * Escribe la cabecera, el codigo de estado y el cuerpo JSON.
     *
     * Se declara la cabecera aqui y no en el front controller para que
     * ningun controlador pueda devolver HTML o texto plano por descuido.
     */
    private static function enviar(int $codigo, array $cuerpo): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        http_response_code($codigo);

        echo json_encode(
            $cuerpo,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Lee y decodifica el cuerpo JSON de la peticion.
     *
     * Si el JSON viene malformado responde 400 y detiene la ejecucion,
     * evitando que el controlador trabaje con datos corruptos.
     *
     * @return array Arreglo asociativo con el cuerpo (vacio si no hay body)
     */
    public static function leerJson(): array
    {
        $crudo = file_get_contents('php://input');

        if ($crudo === false || trim($crudo) === '') {
            return [];
        }

        $datos = json_decode($crudo, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error(400, 'El cuerpo de la solicitud no es un JSON valido: ' . json_last_error_msg());
            exit();
        }

        return is_array($datos) ? $datos : [];
    }
}
