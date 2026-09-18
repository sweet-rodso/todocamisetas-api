<?php
/**
 * ClienteController - CRUD de clientes B2B
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Todos los metodos son ESTATICOS y son invocados directamente por el
 * Router segun la expresion regular que haya calzado con la peticion.
 *
 * Rutas atendidas:
 *   GET    /api/clientes             -> index()
 *   GET    /api/clientes/{id}        -> show()
 *   POST   /api/clientes             -> store()
 *   PUT    /api/clientes/{id}        -> update()
 *   PATCH  /api/clientes/{id}        -> update()
 *   DELETE /api/clientes/{id}        -> destroy()
 *   GET    /api/clientes/{id}/camisetas -> camisetas()
 */
class ClienteController
{
    /** Campos que deben venir si o si al crear un cliente. */
    private const OBLIGATORIOS = [
        'nombre_comercial', 'rut', 'direccion',
        'contacto_nombre', 'contacto_email',
    ];

    /** Valores validos para la categoria (tipo) de cliente. */
    private const CATEGORIAS = ['Regular', 'Preferencial'];

    /**
     * GET /api/clientes
     * Lista todos los clientes. Admite ?categoria=Preferencial
     */
    public static function index(array $parametros = []): void
    {
        try {
            $filtros = [];

            if (isset($_GET['categoria'])) {
                if (!in_array($_GET['categoria'], self::CATEGORIAS, true)) {
                    Response::error(400, 'El parametro "categoria" solo acepta los valores Regular o Preferencial.');
                    return;
                }
                $filtros['categoria'] = $_GET['categoria'];
            }

            $clientes = Cliente::all($filtros);

            Response::exito(200, [
                'total' => count($clientes),
                'data'  => $clientes,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al listar los clientes: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/clientes/{id}
     */
    public static function show(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            $cliente = Cliente::find($id);

            if ($cliente === null) {
                Response::error(404, "No existe un cliente con el ID {$id}.");
                return;
            }

            Response::exito(200, ['data' => $cliente]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al consultar el cliente: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/clientes
     */
    public static function store(array $parametros = []): void
    {
        $cuerpo  = Response::leerJson();
        $errores = self::validar($cuerpo, true);

        if (!empty($errores)) {
            Response::error(422, 'Los datos enviados no son validos.', ['errors' => $errores]);
            return;
        }

        try {
            if (Cliente::rutRegistrado($cuerpo['rut'])) {
                Response::error(409, "El RUT {$cuerpo['rut']} ya esta registrado por otro cliente.");
                return;
            }

            $cliente = Cliente::create($cuerpo);

            Response::exito(201, [
                'message' => 'Cliente creado correctamente.',
                'data'    => $cliente,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al crear el cliente: ' . $e->getMessage());
        }
    }

    /**
     * PUT/PATCH /api/clientes/{id}
     *
     * PUT exige el registro completo; PATCH permite enviar solo los campos
     * que se quieren modificar.
     */
    public static function update(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            if (!Cliente::existe($id)) {
                Response::error(404, "No existe un cliente con el ID {$id}.");
                return;
            }

            $cuerpo = Response::leerJson();

            if (empty($cuerpo)) {
                Response::error(400, 'El cuerpo de la solicitud no puede venir vacio.');
                return;
            }

            $esPut   = ($_SERVER['REQUEST_METHOD'] === 'PUT');
            $errores = self::validar($cuerpo, $esPut);

            if (!empty($errores)) {
                Response::error(422, 'Los datos enviados no son validos.', ['errors' => $errores]);
                return;
            }

            if (isset($cuerpo['rut']) && Cliente::rutRegistrado($cuerpo['rut'], $id)) {
                Response::error(409, "El RUT {$cuerpo['rut']} ya esta registrado por otro cliente.");
                return;
            }

            $cliente = Cliente::update($id, $cuerpo);

            Response::exito(200, [
                'message' => 'Cliente actualizado correctamente.',
                'data'    => $cliente,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al actualizar el cliente: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/clientes/{id}
     *
     * Regla de negocio: no se puede eliminar un cliente que todavia tiene
     * camisetas asignadas. Se comprueba antes de borrar para responder un
     * 409 con un mensaje util en lugar de un error de clave foranea.
     */
    public static function destroy(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            if (!Cliente::existe($id)) {
                Response::error(404, "No existe un cliente con el ID {$id}.");
                return;
            }

            $camisetasAsociadas = Cliente::contarCamisetas($id);

            if ($camisetasAsociadas > 0) {
                Response::error(409, sprintf(
                    'No se puede eliminar el cliente porque tiene %d camiseta(s) asignada(s). ' .
                    'Reasigne o elimine primero esos productos.',
                    $camisetasAsociadas
                ), ['camisetas_asociadas' => $camisetasAsociadas]);
                return;
            }

            Cliente::delete($id);

            Response::exito(200, [
                'message' => "Cliente con ID {$id} eliminado correctamente.",
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al eliminar el cliente: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/clientes/{id}/camisetas
     *
     * Lista las camisetas asignadas al cliente calculando el precio final
     * con las reglas de oferta que le corresponden.
     */
    public static function camisetas(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            $cliente = Cliente::find($id);

            if ($cliente === null) {
                Response::error(404, "No existe un cliente con el ID {$id}.");
                return;
            }

            $resultado = Camiseta::all([
                'cliente_id'          => $id,
                'precio_para_cliente' => $id,
                'pagina'              => $_GET['pagina'] ?? 1,
                'por_pagina'          => $_GET['por_pagina'] ?? 10,
            ]);

            Response::exito(200, [
                'cliente' => [
                    'id'                => $cliente['id'],
                    'nombre_comercial'  => $cliente['nombre_comercial'],
                    'categoria'         => $cliente['categoria'],
                    'porcentaje_oferta' => $cliente['porcentaje_oferta'],
                ],
                'total'   => $resultado['meta']['total'],
                'meta'    => $resultado['meta'],
                'data'    => $resultado['data'],
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al listar las camisetas del cliente: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // Validaciones internas
    // -----------------------------------------------------------------

    /**
     * Valida y convierte el id recibido en la ruta.
     *
     * @return int|null Null si el id no es valido (ya se envio la respuesta)
     */
    private static function validarId(array $parametros): ?int
    {
        $id = isset($parametros['id']) ? (int) $parametros['id'] : 0;

        if ($id <= 0) {
            Response::error(400, 'El ID debe ser un numero entero positivo.');
            return null;
        }

        return $id;
    }

    /**
     * Valida el cuerpo de la peticion.
     *
     * @param array $cuerpo
     * @param bool  $exigirTodos True para POST y PUT, false para PATCH
     * @return array Lista de mensajes de error (vacia si todo esta correcto)
     */
    private static function validar(array $cuerpo, bool $exigirTodos): array
    {
        $errores = [];

        if ($exigirTodos) {
            foreach (self::OBLIGATORIOS as $campo) {
                $valor = $cuerpo[$campo] ?? null;
                if ($valor === null || trim((string) $valor) === '') {
                    $errores[] = "El campo \"{$campo}\" es obligatorio.";
                }
            }
        }

        if (isset($cuerpo['contacto_email']) && trim((string) $cuerpo['contacto_email']) !== '') {
            if (!filter_var($cuerpo['contacto_email'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El campo "contacto_email" debe ser un correo electronico valido.';
            }
        }

        if (isset($cuerpo['categoria']) && !in_array($cuerpo['categoria'], self::CATEGORIAS, true)) {
            $errores[] = 'El campo "categoria" solo acepta los valores Regular o Preferencial.';
        }

        if (isset($cuerpo['porcentaje_oferta'])) {
            $porcentaje = $cuerpo['porcentaje_oferta'];
            if (!is_numeric($porcentaje) || $porcentaje < 0 || $porcentaje > 100) {
                $errores[] = 'El campo "porcentaje_oferta" debe ser un numero entre 0 y 100.';
            }
        }

        if (isset($cuerpo['nombre_comercial']) && mb_strlen((string) $cuerpo['nombre_comercial']) > 150) {
            $errores[] = 'El campo "nombre_comercial" no puede superar los 150 caracteres.';
        }

        return $errores;
    }

    /**
     * Traduce las excepciones de PDO a respuestas HTTP comprensibles.
     *
     * El SQLSTATE 23000 agrupa las violaciones de integridad (clave unica
     * duplicada, clave foranea invalida). Devolver 409 en vez de un 500
     * generico le dice al cliente que el problema esta en sus datos.
     */
    private static function manejarErrorBD(PDOException $e): void
    {
        if ($e->getCode() === '23000') {
            Response::error(409, 'La operacion infringe una restriccion de integridad de la base de datos (dato duplicado o referencia inexistente).');
            return;
        }

        Response::error(500, 'Error de base de datos: ' . $e->getMessage());
    }
}
