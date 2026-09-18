<?php
/**
 * TallaController - CRUD del catalogo de tallas y de la relacion M:N
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Atiende dos grupos de rutas:
 *
 * A) Catalogo de tallas
 *   GET    /api/tallas               -> index()
 *   GET    /api/tallas/{id}          -> show()
 *   POST   /api/tallas               -> store()
 *   PUT    /api/tallas/{id}          -> update()
 *   PATCH  /api/tallas/{id}          -> update()
 *   DELETE /api/tallas/{id}          -> destroy()
 *
 * B) Relacion muchos a muchos camiseta <-> talla
 *   GET    /api/camisetas/{id}/tallas                 -> deCamiseta()
 *   POST   /api/camisetas/{id}/tallas                 -> asignar()
 *   PUT    /api/camisetas/{id}/tallas/{tallaId}       -> actualizarStock()
 *   PATCH  /api/camisetas/{id}/tallas/{tallaId}       -> actualizarStock()
 *   DELETE /api/camisetas/{id}/tallas/{tallaId}       -> quitar()
 *
 * En todas las rutas del grupo B se comprueba primero que la camiseta
 * exista, devolviendo 404 en caso contrario.
 */
class TallaController
{
    // =================================================================
    // A) Catalogo de tallas
    // =================================================================

    /**
     * GET /api/tallas
     */
    public static function index(array $parametros = []): void
    {
        try {
            $tallas = Talla::all();

            Response::exito(200, [
                'total' => count($tallas),
                'data'  => $tallas,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al listar las tallas: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/tallas/{id}
     */
    public static function show(array $parametros = []): void
    {
        $id = self::validarId($parametros, 'id');
        if ($id === null) {
            return;
        }

        try {
            $talla = Talla::find($id);

            if ($talla === null) {
                Response::error(404, "No existe una talla con el ID {$id}.");
                return;
            }

            Response::exito(200, ['data' => $talla]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al consultar la talla: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/tallas
     */
    public static function store(array $parametros = []): void
    {
        $cuerpo  = Response::leerJson();
        $errores = self::validarTalla($cuerpo, true);

        if (!empty($errores)) {
            Response::error(422, 'Los datos enviados no son validos.', ['errors' => $errores]);
            return;
        }

        try {
            if (Talla::codigoRegistrado($cuerpo['codigo'])) {
                Response::error(409, "Ya existe una talla con el codigo {$cuerpo['codigo']}.");
                return;
            }

            $talla = Talla::create($cuerpo);

            Response::exito(201, [
                'message' => 'Talla creada correctamente.',
                'data'    => $talla,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al crear la talla: ' . $e->getMessage());
        }
    }

    /**
     * PUT/PATCH /api/tallas/{id}
     */
    public static function update(array $parametros = []): void
    {
        $id = self::validarId($parametros, 'id');
        if ($id === null) {
            return;
        }

        try {
            if (!Talla::existe($id)) {
                Response::error(404, "No existe una talla con el ID {$id}.");
                return;
            }

            $cuerpo = Response::leerJson();

            if (empty($cuerpo)) {
                Response::error(400, 'El cuerpo de la solicitud no puede venir vacio.');
                return;
            }

            $esPut   = ($_SERVER['REQUEST_METHOD'] === 'PUT');
            $errores = self::validarTalla($cuerpo, $esPut);

            if (!empty($errores)) {
                Response::error(422, 'Los datos enviados no son validos.', ['errors' => $errores]);
                return;
            }

            if (isset($cuerpo['codigo']) && Talla::codigoRegistrado($cuerpo['codigo'], $id)) {
                Response::error(409, "Ya existe otra talla con el codigo {$cuerpo['codigo']}.");
                return;
            }

            $talla = Talla::update($id, $cuerpo);

            Response::exito(200, [
                'message' => 'Talla actualizada correctamente.',
                'data'    => $talla,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al actualizar la talla: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/tallas/{id}
     *
     * Se informa en la respuesta cuantas asignaciones se eliminaron en
     * cascada, para que el efecto de la operacion quede explicito.
     */
    public static function destroy(array $parametros = []): void
    {
        $id = self::validarId($parametros, 'id');
        if ($id === null) {
            return;
        }

        try {
            if (!Talla::existe($id)) {
                Response::error(404, "No existe una talla con el ID {$id}.");
                return;
            }

            $asignaciones = Talla::contarCamisetas($id);

            Talla::delete($id);

            Response::exito(200, [
                'message'                => "Talla con ID {$id} eliminada correctamente.",
                'asignaciones_liberadas' => $asignaciones,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al eliminar la talla: ' . $e->getMessage());
        }
    }

    // =================================================================
    // B) Relacion muchos a muchos camiseta <-> talla
    // =================================================================

    /**
     * GET /api/camisetas/{id}/tallas
     */
    public static function deCamiseta(array $parametros = []): void
    {
        $camisetaId = self::validarId($parametros, 'id');
        if ($camisetaId === null) {
            return;
        }

        try {
            if (!Camiseta::existe($camisetaId)) {
                Response::error(404, "No existe una camiseta con el ID {$camisetaId}.");
                return;
            }

            $tallas = CamisetaTalla::deCamiseta($camisetaId);

            Response::exito(200, [
                'camiseta_id' => $camisetaId,
                'total'       => count($tallas),
                'data'        => $tallas,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al listar las tallas de la camiseta: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/camisetas/{id}/tallas
     *
     * Cuerpo esperado: {"talla_id": 3, "stock": 25}
     */
    public static function asignar(array $parametros = []): void
    {
        $camisetaId = self::validarId($parametros, 'id');
        if ($camisetaId === null) {
            return;
        }

        try {
            if (!Camiseta::existe($camisetaId)) {
                Response::error(404, "No existe una camiseta con el ID {$camisetaId}.");
                return;
            }

            $cuerpo = Response::leerJson();

            $tallaId = isset($cuerpo['talla_id']) ? (int) $cuerpo['talla_id'] : 0;
            $errores = [];

            if ($tallaId <= 0) {
                $errores[] = 'El campo "talla_id" es obligatorio y debe ser un numero entero positivo.';
            }

            if (isset($cuerpo['stock']) && (!is_numeric($cuerpo['stock']) || (int) $cuerpo['stock'] < 0)) {
                $errores[] = 'El campo "stock" debe ser un numero entero mayor o igual a cero.';
            }

            if (!empty($errores)) {
                Response::error(422, 'Los datos enviados no son validos.', ['errors' => $errores]);
                return;
            }

            if (!Talla::existe($tallaId)) {
                Response::error(404, "No existe una talla con el ID {$tallaId} en el catalogo.");
                return;
            }

            if (CamisetaTalla::existe($camisetaId, $tallaId)) {
                Response::error(409, "La talla {$tallaId} ya esta asignada a la camiseta {$camisetaId}. Use PUT para modificar su stock.");
                return;
            }

            $asignacion = CamisetaTalla::create($camisetaId, $tallaId, (int) ($cuerpo['stock'] ?? 0));

            Response::exito(201, [
                'message'     => 'Talla asignada correctamente a la camiseta.',
                'camiseta_id' => $camisetaId,
                'data'        => $asignacion,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al asignar la talla: ' . $e->getMessage());
        }
    }

    /**
     * PUT/PATCH /api/camisetas/{id}/tallas/{tallaId}
     *
     * Cuerpo esperado: {"stock": 40}
     */
    public static function actualizarStock(array $parametros = []): void
    {
        $camisetaId = self::validarId($parametros, 'id');
        if ($camisetaId === null) {
            return;
        }

        $tallaId = self::validarId($parametros, 'tallaId');
        if ($tallaId === null) {
            return;
        }

        try {
            if (!Camiseta::existe($camisetaId)) {
                Response::error(404, "No existe una camiseta con el ID {$camisetaId}.");
                return;
            }

            if (!CamisetaTalla::existe($camisetaId, $tallaId)) {
                Response::error(404, "La camiseta {$camisetaId} no tiene asignada la talla {$tallaId}.");
                return;
            }

            $cuerpo = Response::leerJson();

            if (!isset($cuerpo['stock'])) {
                Response::error(422, 'Los datos enviados no son validos.', [
                    'errors' => ['El campo "stock" es obligatorio.'],
                ]);
                return;
            }

            if (!is_numeric($cuerpo['stock']) || (int) $cuerpo['stock'] < 0) {
                Response::error(422, 'Los datos enviados no son validos.', [
                    'errors' => ['El campo "stock" debe ser un numero entero mayor o igual a cero.'],
                ]);
                return;
            }

            $asignacion = CamisetaTalla::update($camisetaId, $tallaId, (int) $cuerpo['stock']);

            Response::exito(200, [
                'message'     => 'Stock actualizado correctamente.',
                'camiseta_id' => $camisetaId,
                'data'        => $asignacion,
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al actualizar el stock: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/camisetas/{id}/tallas/{tallaId}
     */
    public static function quitar(array $parametros = []): void
    {
        $camisetaId = self::validarId($parametros, 'id');
        if ($camisetaId === null) {
            return;
        }

        $tallaId = self::validarId($parametros, 'tallaId');
        if ($tallaId === null) {
            return;
        }

        try {
            if (!Camiseta::existe($camisetaId)) {
                Response::error(404, "No existe una camiseta con el ID {$camisetaId}.");
                return;
            }

            if (!CamisetaTalla::existe($camisetaId, $tallaId)) {
                Response::error(404, "La camiseta {$camisetaId} no tiene asignada la talla {$tallaId}.");
                return;
            }

            CamisetaTalla::delete($camisetaId, $tallaId);

            Response::exito(200, [
                'message' => "Talla {$tallaId} desasignada de la camiseta {$camisetaId}.",
            ]);

        } catch (PDOException $e) {
            self::manejarErrorBD($e);
        } catch (Throwable $e) {
            Response::error(500, 'Error al desasignar la talla: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // Validaciones internas
    // -----------------------------------------------------------------

    /**
     * Valida uno de los identificadores capturados en la ruta.
     *
     * @param string $clave 'id' o 'tallaId'
     */
    private static function validarId(array $parametros, string $clave): ?int
    {
        $id = isset($parametros[$clave]) ? (int) $parametros[$clave] : 0;

        if ($id <= 0) {
            Response::error(400, "El parametro \"{$clave}\" debe ser un numero entero positivo.");
            return null;
        }

        return $id;
    }

    /**
     * @param bool $exigirTodos True para POST y PUT, false para PATCH
     */
    private static function validarTalla(array $cuerpo, bool $exigirTodos): array
    {
        $errores = [];

        if ($exigirTodos) {
            foreach (['codigo', 'descripcion'] as $campo) {
                $valor = $cuerpo[$campo] ?? null;
                if ($valor === null || trim((string) $valor) === '') {
                    $errores[] = "El campo \"{$campo}\" es obligatorio.";
                }
            }
        }

        if (isset($cuerpo['codigo']) && mb_strlen((string) $cuerpo['codigo']) > 10) {
            $errores[] = 'El campo "codigo" no puede superar los 10 caracteres.';
        }

        if (isset($cuerpo['descripcion']) && mb_strlen((string) $cuerpo['descripcion']) > 80) {
            $errores[] = 'El campo "descripcion" no puede superar los 80 caracteres.';
        }

        return $errores;
    }

    private static function manejarErrorBD(PDOException $e): void
    {
        if ($e->getCode() === '23000') {
            Response::error(409, 'La operacion infringe una restriccion de integridad de la base de datos (asignacion duplicada o referencia inexistente).');
            return;
        }

        Response::error(500, 'Error de base de datos: ' . $e->getMessage());
    }
}
