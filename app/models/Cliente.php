<?php
/**
 * Modelo Cliente - Tiendas minoristas B2B de TodoCamisetas
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Todos los metodos son estaticos y se corresponden uno a uno con las
 * operaciones sobre la tabla "clientes". Las operaciones de escritura
 * (create, update, delete) se ejecutan dentro de una transaccion para que,
 * si algo falla a mitad de camino, la base de datos vuelva a su estado
 * anterior y no queden registros a medio guardar.
 */
class Cliente
{
    /** Columnas que se exponen en las respuestas de la API. */
    private const CAMPOS = 'id, nombre_comercial, rut, direccion, categoria,
                            contacto_nombre, contacto_email, porcentaje_oferta,
                            creado_en, actualizado_en';

    /**
     * Lista todos los clientes, con filtro opcional por categoria.
     *
     * @param array $filtros ['categoria' => 'Preferencial'|'Regular']
     * @return array
     */
    public static function all(array $filtros = []): array
    {
        $db  = getDbConnection();
        $sql = 'SELECT ' . self::CAMPOS . ' FROM clientes';
        $parametros = [];

        if (!empty($filtros['categoria'])) {
            $sql .= ' WHERE categoria = :categoria';
            $parametros[':categoria'] = $filtros['categoria'];
        }

        $sql .= ' ORDER BY nombre_comercial ASC';

        $sentencia = $db->prepare($sql);
        $sentencia->execute($parametros);

        return array_map([self::class, 'formatear'], $sentencia->fetchAll());
    }

    /**
     * Busca un cliente por su identificador.
     *
     * @param int $id
     * @return array|null Null si el cliente no existe
     */
    public static function find(int $id): ?array
    {
        $db = getDbConnection();
        $sentencia = $db->prepare('SELECT ' . self::CAMPOS . ' FROM clientes WHERE id = :id LIMIT 1');
        $sentencia->execute([':id' => $id]);

        $fila = $sentencia->fetch();

        return $fila ? self::formatear($fila) : null;
    }

    /**
     * Crea un nuevo cliente.
     *
     * @param array $datos
     * @return array El cliente recien creado
     * @throws PDOException si falla la insercion (por ejemplo, RUT duplicado)
     */
    public static function create(array $datos): array
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare(
                'INSERT INTO clientes
                    (nombre_comercial, rut, direccion, categoria,
                     contacto_nombre, contacto_email, porcentaje_oferta)
                 VALUES
                    (:nombre_comercial, :rut, :direccion, :categoria,
                     :contacto_nombre, :contacto_email, :porcentaje_oferta)'
            );

            $sentencia->execute([
                ':nombre_comercial'  => $datos['nombre_comercial'],
                ':rut'               => $datos['rut'],
                ':direccion'         => $datos['direccion'],
                ':categoria'         => $datos['categoria'] ?? 'Regular',
                ':contacto_nombre'   => $datos['contacto_nombre'],
                ':contacto_email'    => $datos['contacto_email'],
                ':porcentaje_oferta' => $datos['porcentaje_oferta'] ?? 0,
            ]);

            $id = (int) $db->lastInsertId();
            $db->commit();

            return self::find($id);

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza un cliente existente.
     *
     * Solo se modifican los campos presentes en $datos, lo que permite
     * atender tanto PUT (envio completo) como PATCH (envio parcial) con
     * una sola implementacion.
     *
     * @param int   $id
     * @param array $datos
     * @return array|null El cliente actualizado
     */
    public static function update(int $id, array $datos): ?array
    {
        $db = getDbConnection();

        $asignaciones = [];
        $parametros   = [':id' => $id];

        $permitidos = [
            'nombre_comercial', 'rut', 'direccion', 'categoria',
            'contacto_nombre', 'contacto_email', 'porcentaje_oferta',
        ];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[]        = "{$campo} = :{$campo}";
                $parametros[":{$campo}"] = $datos[$campo];
            }
        }

        if (empty($asignaciones)) {
            return self::find($id);
        }

        $db->beginTransaction();

        try {
            $sql = 'UPDATE clientes SET ' . implode(', ', $asignaciones) . ' WHERE id = :id';
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
     * Elimina un cliente.
     *
     * @param int $id
     * @return bool True si se elimino alguna fila
     */
    public static function delete(int $id): bool
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare('DELETE FROM clientes WHERE id = :id');
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
     * Indica si el cliente existe.
     */
    public static function existe(int $id): bool
    {
        $db = getDbConnection();
        $sentencia = $db->prepare('SELECT COUNT(*) FROM clientes WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Cuenta cuantas camisetas tiene asignadas el cliente.
     *
     * Se consulta antes de eliminar para poder devolver un 409 con un
     * mensaje claro, en lugar de dejar que estalle la restriccion de clave
     * foranea con un error 500 incomprensible para quien consume la API.
     */
    public static function contarCamisetas(int $id): int
    {
        $db = getDbConnection();
        $sentencia = $db->prepare('SELECT COUNT(*) FROM camisetas WHERE cliente_id = :id');
        $sentencia->execute([':id' => $id]);

        return (int) $sentencia->fetchColumn();
    }

    /**
     * Verifica si un RUT ya esta registrado por otro cliente.
     *
     * @param string   $rut
     * @param int|null $exceptoId Id a ignorar (util al actualizar el propio cliente)
     */
    public static function rutRegistrado(string $rut, ?int $exceptoId = null): bool
    {
        $db  = getDbConnection();
        $sql = 'SELECT COUNT(*) FROM clientes WHERE rut = :rut';
        $parametros = [':rut' => $rut];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $exceptoId;
        }

        $sentencia = $db->prepare($sql);
        $sentencia->execute($parametros);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Da forma a la fila devuelta por la base de datos.
     *
     * Los numeros se convierten explicitamente a int y float porque PDO,
     * al leer columnas DECIMAL, las entrega como cadena de texto y el
     * frontend espera valores numericos en el JSON.
     */
    private static function formatear(array $fila): array
    {
        return [
            'id'                => (int) $fila['id'],
            'nombre_comercial'  => $fila['nombre_comercial'],
            'rut'               => $fila['rut'],
            'direccion'         => $fila['direccion'],
            'categoria'         => $fila['categoria'],
            'contacto'          => [
                'nombre' => $fila['contacto_nombre'],
                'email'  => $fila['contacto_email'],
            ],
            'porcentaje_oferta' => (float) $fila['porcentaje_oferta'],
            'creado_en'         => $fila['creado_en'],
            'actualizado_en'    => $fila['actualizado_en'],
        ];
    }
}
