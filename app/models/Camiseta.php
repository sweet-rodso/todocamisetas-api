<?php
/**
 * Modelo Camiseta - Inventario de productos de TodoCamisetas
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Concentra el acceso a la tabla "camisetas" y la regla de negocio del
 * precio final. Las operaciones de escritura usan transacciones y todas
 * las consultas emplean sentencias preparadas.
 */
class Camiseta
{
    /**
     * Consulta base con los JOIN necesarios.
     *
     * Se trae el cliente en la misma consulta en lugar de hacer una
     * segunda llamada por cada camiseta. Esto evita el problema N+1:
     * listar 50 camisetas cuesta una consulta y no 51.
     */
    private static function selectBase(): string
    {
        return 'SELECT  c.id, c.titulo, c.club, c.pais, c.tipo, c.color,
                        c.precio, c.precio_oferta, c.detalles, c.codigo_producto,
                        c.cliente_id, c.creado_en, c.actualizado_en,
                        cl.nombre_comercial AS cliente_nombre,
                        cl.categoria        AS cliente_categoria
                FROM camisetas c
                LEFT JOIN clientes cl ON cl.id = c.cliente_id';
    }

    /**
     * Lista camisetas con filtros opcionales y paginacion.
     *
     * @param array $filtros ['club', 'tipo', 'cliente_id', 'pagina', 'por_pagina', 'precio_para_cliente']
     * @return array{data: array, meta: array}
     */
    public static function all(array $filtros = []): array
    {
        $db = getDbConnection();

        $pagina    = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = min(50, max(1, (int) ($filtros['por_pagina'] ?? 10)));
        $desde     = ($pagina - 1) * $porPagina;

        $condiciones = [];
        $parametros  = [];

        if (!empty($filtros['club'])) {
            $condiciones[]      = 'c.club = :club';
            $parametros[':club'] = $filtros['club'];
        }

        if (!empty($filtros['tipo'])) {
            $condiciones[]      = 'c.tipo = :tipo';
            $parametros[':tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['cliente_id'])) {
            $condiciones[]            = 'c.cliente_id = :cliente_id';
            $parametros[':cliente_id'] = (int) $filtros['cliente_id'];
        }

        $where = empty($condiciones) ? '' : ' WHERE ' . implode(' AND ', $condiciones);

        // Total de registros, para construir la metadata de paginacion.
        $conteo = $db->prepare('SELECT COUNT(*) FROM camisetas c' . $where);
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = self::selectBase() . $where . ' ORDER BY c.id ASC LIMIT :limite OFFSET :desde';
        $sentencia = $db->prepare($sql);

        foreach ($parametros as $clave => $valor) {
            $sentencia->bindValue($clave, $valor);
        }
        // LIMIT y OFFSET requieren enlace explicito como entero.
        $sentencia->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $sentencia->bindValue(':desde', $desde, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll();

        // Las tallas de todas las camisetas se cargan en UNA sola consulta.
        $tallasPorCamiseta = CamisetaTalla::deVariasCamisetas(array_column($filas, 'id'));

        // Cliente para el que se calcula el precio final (opcional).
        $cliente = isset($filtros['precio_para_cliente'])
            ? Cliente::find((int) $filtros['precio_para_cliente'])
            : null;

        $datos = [];
        foreach ($filas as $fila) {
            $datos[] = self::formatear($fila, $tallasPorCamiseta[$fila['id']] ?? [], $cliente);
        }

        return [
            'data' => $datos,
            'meta' => [
                'total'      => $total,
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'paginas'    => (int) ceil($total / $porPagina),
            ],
        ];
    }

    /**
     * Busca una camiseta por su id, incluyendo sus tallas.
     *
     * @param int       $id
     * @param array|null $cliente Cliente consultante, para calcular precio_final
     * @return array|null
     */
    public static function find(int $id, ?array $cliente = null): ?array
    {
        $db = getDbConnection();
        $sentencia = $db->prepare(self::selectBase() . ' WHERE c.id = :id LIMIT 1');
        $sentencia->execute([':id' => $id]);

        $fila = $sentencia->fetch();

        if (!$fila) {
            return null;
        }

        return self::formatear($fila, CamisetaTalla::deCamiseta($id), $cliente);
    }

    /**
     * Crea una camiseta y, opcionalmente, le asigna tallas en la misma
     * transaccion.
     *
     * Si la asignacion de tallas falla, tambien se revierte la insercion de
     * la camiseta: o se guarda todo el producto completo, o no se guarda nada.
     *
     * @param array $datos
     * @return array La camiseta creada
     */
    public static function create(array $datos): array
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare(
                'INSERT INTO camisetas
                    (titulo, club, pais, tipo, color, precio, precio_oferta,
                     detalles, codigo_producto, cliente_id)
                 VALUES
                    (:titulo, :club, :pais, :tipo, :color, :precio, :precio_oferta,
                     :detalles, :codigo_producto, :cliente_id)'
            );

            $sentencia->execute([
                ':titulo'          => $datos['titulo'],
                ':club'            => $datos['club'],
                ':pais'            => $datos['pais'],
                ':tipo'            => $datos['tipo'],
                ':color'           => $datos['color'],
                ':precio'          => $datos['precio'],
                ':precio_oferta'   => $datos['precio_oferta'] ?? null,
                ':detalles'        => $datos['detalles'] ?? null,
                ':codigo_producto' => $datos['codigo_producto'],
                ':cliente_id'      => $datos['cliente_id'] ?? null,
            ]);

            $id = (int) $db->lastInsertId();

            // Tallas enviadas junto al producto: [{"talla_id":1,"stock":10}, ...]
            if (!empty($datos['tallas']) && is_array($datos['tallas'])) {
                $asignar = $db->prepare(
                    'INSERT INTO camiseta_tallas (camiseta_id, talla_id, stock)
                     VALUES (:camiseta_id, :talla_id, :stock)'
                );

                foreach ($datos['tallas'] as $talla) {
                    $asignar->execute([
                        ':camiseta_id' => $id,
                        ':talla_id'    => (int) ($talla['talla_id'] ?? 0),
                        ':stock'       => (int) ($talla['stock'] ?? 0),
                    ]);
                }
            }

            $db->commit();

            return self::find($id);

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza una camiseta. Solo modifica los campos presentes en $datos,
     * de modo que sirve tanto para PUT como para PATCH.
     *
     * @param int   $id
     * @param array $datos
     * @return array|null
     */
    public static function update(int $id, array $datos): ?array
    {
        $db = getDbConnection();

        $asignaciones = [];
        $parametros   = [':id' => $id];

        $permitidos = [
            'titulo', 'club', 'pais', 'tipo', 'color', 'precio',
            'precio_oferta', 'detalles', 'codigo_producto', 'cliente_id',
        ];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[]          = "{$campo} = :{$campo}";
                $parametros[":{$campo}"] = $datos[$campo];
            }
        }

        if (empty($asignaciones)) {
            return self::find($id);
        }

        $db->beginTransaction();

        try {
            $sql = 'UPDATE camisetas SET ' . implode(', ', $asignaciones) . ' WHERE id = :id';
            $sentencia = $db->prepare($sql);
            $sentencia->execute($parametros);

            $db->commit();

            return self::find($id);

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina una camiseta.
     *
     * Sus filas en camiseta_tallas desaparecen automaticamente gracias al
     * ON DELETE CASCADE definido en la tabla pivote.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare('DELETE FROM camisetas WHERE id = :id');
            $sentencia->execute([':id' => $id]);

            $eliminadas = $sentencia->rowCount();
            $db->commit();

            return $eliminadas > 0;

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Indica si la camiseta existe.
     */
    public static function existe(int $id): bool
    {
        $db = getDbConnection();
        $sentencia = $db->prepare('SELECT COUNT(*) FROM camisetas WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Verifica si un codigo de producto (SKU) ya esta en uso.
     *
     * @param string   $codigo
     * @param int|null $exceptoId Id a ignorar al actualizar la propia camiseta
     */
    public static function codigoRegistrado(string $codigo, ?int $exceptoId = null): bool
    {
        $db  = getDbConnection();
        $sql = 'SELECT COUNT(*) FROM camisetas WHERE codigo_producto = :codigo';
        $parametros = [':codigo' => $codigo];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $exceptoId;
        }

        $sentencia = $db->prepare($sql);
        $sentencia->execute($parametros);

        return (int) $sentencia->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------
    // Regla de negocio: calculo del precio final
    // -----------------------------------------------------------------

    /**
     * Calcula el precio final de una camiseta para un cliente determinado.
     *
     * El caso plantea dos mecanismos de descuento, por lo que se define el
     * siguiente orden de precedencia (documentado en el informe):
     *
     *   1. Cliente Preferencial y camiseta con precio_oferta definido
     *      -> precio_final = precio_oferta
     *   2. Cliente con porcentaje_oferta mayor a cero
     *      -> precio_final = precio - (precio * porcentaje / 100)
     *   3. Cualquier otro caso (cliente Regular, sin oferta, o sin cliente)
     *      -> precio_final = precio base
     *
     * @param float      $precio       Precio base
     * @param float|null $precioOferta Precio de oferta del producto, si existe
     * @param array|null $cliente      Cliente consultante
     * @return array{precio_final: float, origen: string, descuento: float}
     */
    public static function calcularPrecioFinal(float $precio, ?float $precioOferta, ?array $cliente): array
    {
        // Caso 3 anticipado: no se indico cliente, se cobra precio de lista.
        if ($cliente === null) {
            return [
                'precio_final' => round($precio, 2),
                'origen'       => 'precio_base',
                'descuento'    => 0.0,
            ];
        }

        // Caso 1: oferta del producto para clientes preferenciales.
        if ($cliente['categoria'] === 'Preferencial' && $precioOferta !== null) {
            return [
                'precio_final' => round($precioOferta, 2),
                'origen'       => 'precio_oferta',
                'descuento'    => round($precio - $precioOferta, 2),
            ];
        }

        // Caso 2: descuento porcentual transversal del cliente.
        $porcentaje = (float) ($cliente['porcentaje_oferta'] ?? 0);
        if ($porcentaje > 0) {
            $descuento = $precio * ($porcentaje / 100);
            return [
                'precio_final' => round($precio - $descuento, 2),
                'origen'       => 'porcentaje_cliente',
                'descuento'    => round($descuento, 2),
            ];
        }

        // Caso 3: precio de lista.
        return [
            'precio_final' => round($precio, 2),
            'origen'       => 'precio_base',
            'descuento'    => 0.0,
        ];
    }

    /**
     * Arma la estructura JSON de una camiseta.
     *
     * @param array      $fila    Fila cruda de la base de datos
     * @param array      $tallas  Tallas asociadas
     * @param array|null $cliente Cliente consultante para el precio final
     */
    private static function formatear(array $fila, array $tallas = [], ?array $cliente = null): array
    {
        $precio       = (float) $fila['precio'];
        $precioOferta = $fila['precio_oferta'] !== null ? (float) $fila['precio_oferta'] : null;

        $calculo = self::calcularPrecioFinal($precio, $precioOferta, $cliente);

        $resultado = [
            'id'              => (int) $fila['id'],
            'titulo'          => $fila['titulo'],
            'club'            => $fila['club'],
            'pais'            => $fila['pais'],
            'tipo'            => $fila['tipo'],
            'color'           => $fila['color'],
            'precio'          => $precio,
            'precio_oferta'   => $precioOferta,
            'precio_final'    => $calculo['precio_final'],
            'detalles'        => $fila['detalles'],
            'codigo_producto' => $fila['codigo_producto'],
            'cliente'         => $fila['cliente_id'] === null ? null : [
                'id'        => (int) $fila['cliente_id'],
                'nombre'    => $fila['cliente_nombre'],
                'categoria' => $fila['cliente_categoria'],
            ],
            'tallas'          => $tallas,
            'creado_en'       => $fila['creado_en'],
            'actualizado_en'  => $fila['actualizado_en'],
        ];

        // Cuando se consulta en nombre de un cliente se explica de donde
        // sale el precio final, para que el frontend pueda mostrarlo.
        if ($cliente !== null) {
            $resultado['precio_detalle'] = [
                'tipo_cliente'  => $cliente['categoria'],
                'cliente_id'    => $cliente['id'],
                'origen'        => $calculo['origen'],
                'descuento'     => $calculo['descuento'],
            ];
        }

        return $resultado;
    }
}
