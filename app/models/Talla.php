<?php
/**
 * Modelo Talla - Catalogo de tallas disponibles
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Las tallas viven en su propia tabla para poder reutilizarlas entre
 * productos. La asociacion concreta entre una camiseta y una talla, junto
 * con su stock, se gestiona en el modelo CamisetaTalla.
 */
class Talla
{
    /**
     * Lista el catalogo completo de tallas.
     */
    public static function all(): array
    {
        $db = getDbConnection();
        $sentencia = $db->query(
            'SELECT id, codigo, descripcion, creado_en, actualizado_en
             FROM tallas
             ORDER BY id ASC'
        );

        return array_map([self::class, 'formatear'], $sentencia->fetchAll());
    }

    /**
     * Busca una talla por su id.
     *
     * @return array|null Null si no existe
     */
    public static function find(int $id): ?array
    {
        $db = getDbConnection();
        $sentencia = $db->prepare(
            'SELECT id, codigo, descripcion, creado_en, actualizado_en
             FROM tallas WHERE id = :id LIMIT 1'
        );
        $sentencia->execute([':id' => $id]);

        $fila = $sentencia->fetch();

        return $fila ? self::formatear($fila) : null;
    }

    /**
     * Crea una nueva talla en el catalogo.
     */
    public static function create(array $datos): array
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare(
                'INSERT INTO tallas (codigo, descripcion) VALUES (:codigo, :descripcion)'
            );
            $sentencia->execute([
                ':codigo'      => $datos['codigo'],
                ':descripcion' => $datos['descripcion'],
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
     * Actualiza una talla existente.
     */
    public static function update(int $id, array $datos): ?array
    {
        $db = getDbConnection();

        $asignaciones = [];
        $parametros   = [':id' => $id];

        foreach (['codigo', 'descripcion'] as $campo) {
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
            $sql = 'UPDATE tallas SET ' . implode(', ', $asignaciones) . ' WHERE id = :id';
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
     * Elimina una talla del catalogo.
     *
     * Por el ON DELETE CASCADE de camiseta_tallas, al borrar una talla se
     * eliminan tambien todas sus asignaciones a productos.
     */
    public static function delete(int $id): bool
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare('DELETE FROM tallas WHERE id = :id');
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
     * Indica si la talla existe.
     */
    public static function existe(int $id): bool
    {
        $db = getDbConnection();
        $sentencia = $db->prepare('SELECT COUNT(*) FROM tallas WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Verifica si un codigo de talla ya existe.
     *
     * @param int|null $exceptoId Id a ignorar al actualizar la propia talla
     */
    public static function codigoRegistrado(string $codigo, ?int $exceptoId = null): bool
    {
        $db  = getDbConnection();
        $sql = 'SELECT COUNT(*) FROM tallas WHERE codigo = :codigo';
        $parametros = [':codigo' => $codigo];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $exceptoId;
        }

        $sentencia = $db->prepare($sql);
        $sentencia->execute($parametros);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Cuenta en cuantas camisetas esta asignada la talla.
     */
    public static function contarCamisetas(int $id): int
    {
        $db = getDbConnection();
        $sentencia = $db->prepare('SELECT COUNT(*) FROM camiseta_tallas WHERE talla_id = :id');
        $sentencia->execute([':id' => $id]);

        return (int) $sentencia->fetchColumn();
    }

    private static function formatear(array $fila): array
    {
        return [
            'id'             => (int) $fila['id'],
            'codigo'         => $fila['codigo'],
            'descripcion'    => $fila['descripcion'],
            'creado_en'      => $fila['creado_en'],
            'actualizado_en' => $fila['actualizado_en'],
        ];
    }
}
