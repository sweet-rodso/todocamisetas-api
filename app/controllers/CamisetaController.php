<?php
/**
 * CamisetaController - CRUD del inventario de camisetas
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Todos los metodos son ESTATICOS y son invocados por el Router.
 *
 * Rutas atendidas:
 *   GET    /api/camisetas         -> index()
 *   GET    /api/camisetas/{id}    -> show()    (acepta ?cliente_id= para el precio final)
 *   POST   /api/camisetas         -> store()
 *   PUT    /api/camisetas/{id}    -> update()
 *   PATCH  /api/camisetas/{id}    -> update()
 *   DELETE /api/camisetas/{id}    -> destroy()
 */
class CamisetaController
{
    /** Campos obligatorios al crear un producto. */
    private const OBLIGATORIOS = ['titulo', 'club', 'pais', 'tipo', 'color', 'precio', 'codigo_producto'];

    /**
     * GET /api/camisetas
     *
     * Filtros: ?club= ?tipo= ?cliente_id= ?pagina= ?por_pagina=
     * Si se envia ?cliente_id=, el listado incluye el precio final que le
     * corresponde a ese cliente.
     */
    public static function index(array $parametros = []): void
    {
        try {
            $filtros = [
                'club'       => $_GET['club']       ?? null,
                'tipo'       => $_GET['tipo']       ?? null,
                'cliente_id' => $_GET['cliente_id'] ?? null,
                'pagina'     => $_GET['pagina']     ?? 1,
                'por_pagina' => $_GET['por_pagina'] ?? 10,
            ];

            // Si se consulta en nombre de un cliente, se valida que exista.
            if (!empty($_GET['cliente_id'])) {
                $clienteId = (int) $_GET['cliente_id'];

                if ($clienteId <= 0) {
                    Response::error(400, 'El parametro "cliente_id" debe ser un numero entero positivo.');
                    return;
                }

                if (!Cliente::existe($clienteId)) {
                    Response::error(404, "No existe un cliente con el ID {$clienteId}.");
                    return;
                }

                $filtros['precio_para_cliente'] = $clienteId;
            }

            $resultado = Camiseta::all($filtros);

            Response::exito(200, [
                'total' => $resultado['meta']['total'],
                'meta'  => $resultado['meta'],
                'data'  => $resultado['data'],
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al listar las camisetas: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/camisetas/{id}?cliente_id=N
     *
     * Devuelve la camiseta con sus tallas y el precio final calculado para
     * el cliente indicado. Sin cliente_id se devuelve el precio de lista.
     */
    public static function show(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            $cliente = null;

            if (!empty($_GET['cliente_id'])) {
                $clienteId = (int) $_GET['cliente_id'];

                if ($clienteId <= 0) {
                    Response::error(400, 'El parametro "cliente_id" debe ser un numero entero positivo.');
                    return;
                }

                $cliente = Cliente::find($clienteId);

                if ($cliente === null) {
                    Response::error(404, "No existe un cliente con el ID {$clienteId}.");
                    return;
                }
            }

            $camiseta = Camiseta::find($id, $cliente);

            if ($camiseta === null) {
                Response::error(404, "No existe una camiseta con el ID {$id}.");
                return;
            }

            Response::exito(200, ['data' => $camiseta]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al consultar la camiseta: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/camisetas
     *
     * Permite enviar las tallas junto al producto:
     *   "tallas": [{"talla_id": 1, "stock": 20}, {"talla_id": 2, "stock": 15}]
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
            if (Camiseta::codigoRegistrado($cuerpo['codigo_producto'])) {
                Response::error(409, "El codigo de producto {$cuerpo['codigo_producto']} ya esta en uso.");
                return;
            }

            // El cliente asignado debe existir.
            if (!empty($cuerpo['cliente_id']) && !Cliente::existe((int) $cuerpo['cliente_id'])) {
                Response::error(422, 'Los datos enviados no son validos.', [
                    'errors' => ["No existe un cliente con el ID {$cuerpo['cliente_id']}."],
                ]);
                return;
            }

            // Las tallas enviadas deben existir en el catalogo.
            if (!empty($cuerpo['tallas'])) {
                $erroresTallas = self::validarTallas($cuerpo['tallas']);
                if (!empty($erroresTallas)) {
                    Response::error(422, 'Los datos enviados no son validos.', ['errors' => $erroresTallas]);
                    return;
                }
            }

            $camiseta = Camiseta::create($cuerpo);

            Response::exito(201, [
                'message' => 'Camiseta creada correctamente.',
                'data'    => $camiseta,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al crear la camiseta: ' . $e->getMessage());
        }
    }

    /**
     * PUT/PATCH /api/camisetas/{id}
     */
    public static function update(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            if (!Camiseta::existe($id)) {
                Response::error(404, "No existe una camiseta con el ID {$id}.");
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

            if (isset($cuerpo['codigo_producto']) && Camiseta::codigoRegistrado($cuerpo['codigo_producto'], $id)) {
                Response::error(409, "El codigo de producto {$cuerpo['codigo_producto']} ya esta en uso.");
                return;
            }

            if (!empty($cuerpo['cliente_id']) && !Cliente::existe((int) $cuerpo['cliente_id'])) {
                Response::error(422, 'Los datos enviados no son validos.', [
                    'errors' => ["No existe un cliente con el ID {$cuerpo['cliente_id']}."],
                ]);
                return;
            }

            $camiseta = Camiseta::update($id, $cuerpo);

            Response::exito(200, [
                'message' => 'Camiseta actualizada correctamente.',
                'data'    => $camiseta,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al actualizar la camiseta: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/camisetas/{id}
     *
     * Las asignaciones de tallas se eliminan solas por el ON DELETE CASCADE
     * de la tabla camiseta_tallas.
     */
    public static function destroy(array $parametros = []): void
    {
        $id = self::validarId($parametros);
        if ($id === null) {
            return;
        }

        try {
            if (!Camiseta::existe($id)) {
                Response::error(404, "No existe una camiseta con el ID {$id}.");
                return;
            }

            Camiseta::delete($id);

            Response::exito(200, [
                'message' => "Camiseta con ID {$id} eliminada correctamente.",
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al eliminar la camiseta: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // Validaciones internas
    // -----------------------------------------------------------------

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
     * @param bool $exigirTodos True para POST y PUT, false para PATCH
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

        if (isset($cuerpo['titulo']) && mb_strlen((string) $cuerpo['titulo']) > 200) {
            $errores[] = 'El campo "titulo" no puede superar los 200 caracteres.';
        }

        if (isset($cuerpo['codigo_producto']) && mb_strlen((string) $cuerpo['codigo_producto']) > 40) {
            $errores[] = 'El campo "codigo_producto" no puede superar los 40 caracteres.';
        }

        if (isset($cuerpo['precio'])) {
            if (!is_numeric($cuerpo['precio']) || (float) $cuerpo['precio'] <= 0) {
                $errores[] = 'El campo "precio" debe ser un numero mayor que cero.';
            }
        }

        // precio_oferta admite null (quitar la oferta) pero no valores invalidos.
        if (array_key_exists('precio_oferta', $cuerpo) && $cuerpo['precio_oferta'] !== null) {
            if (!is_numeric($cuerpo['precio_oferta']) || (float) $cuerpo['precio_oferta'] <= 0) {
                $errores[] = 'El campo "precio_oferta" debe ser un numero mayor que cero, o null para quitar la oferta.';
            } elseif (isset($cuerpo['precio']) && is_numeric($cuerpo['precio'])
                      && (float) $cuerpo['precio_oferta'] >= (float) $cuerpo['precio']) {
                $errores[] = 'El campo "precio_oferta" debe ser menor que el precio base.';
            }
        }

        if (isset($cuerpo['cliente_id']) && $cuerpo['cliente_id'] !== null) {
            if (!is_numeric($cuerpo['cliente_id']) || (int) $cuerpo['cliente_id'] <= 0) {
                $errores[] = 'El campo "cliente_id" debe ser un numero entero positivo o null.';
            }
        }

        if (isset($cuerpo['tallas']) && !is_array($cuerpo['tallas'])) {
            $errores[] = 'El campo "tallas" debe ser un arreglo de objetos con talla_id y stock.';
        }

        return $errores;
    }

    /**
     * Comprueba que cada talla enviada exista y traiga un stock valido.
     */
    private static function validarTallas(array $tallas): array
    {
        $errores = [];

        foreach ($tallas as $posicion => $talla) {
            if (!is_array($talla) || !isset($talla['talla_id'])) {
                $errores[] = "La talla en la posicion {$posicion} debe incluir el campo \"talla_id\".";
                continue;
            }

            $tallaId = (int) $talla['talla_id'];

            if ($tallaId <= 0 || !Talla::existe($tallaId)) {
                $errores[] = "No existe una talla con el ID {$tallaId} en el catalogo.";
            }

            if (isset($talla['stock']) && (!is_numeric($talla['stock']) || (int) $talla['stock'] < 0)) {
                $errores[] = "El stock de la talla {$tallaId} debe ser un numero entero mayor o igual a cero.";
            }
        }

        return $errores;
    }

    private static function manejarErrorBD(PDOException $e): void
    {
        if ($e->getCode() === '23000') {
            Response::error(409, 'La operacion infringe una restriccion de integridad de la base de datos (codigo duplicado o referencia inexistente).');
            return;
        }

        Response::error(500, 'Error de base de datos: ' . $e->getMessage());
    }
}
