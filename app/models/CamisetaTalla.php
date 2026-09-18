<?php
/**
 * Modelo CamisetaTalla - Relacion MUCHOS A MUCHOS entre camisetas y tallas
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Gestiona la tabla pivote camiseta_tallas, que ademas de resolver la
 * relacion almacena un atributo propio de la asociacion: el stock
 * disponible de esa camiseta en esa talla concreta.
 *
 * La clave primaria compuesta (camiseta_id, talla_id) impide que una misma
 * talla se asigne dos veces al mismo producto, y las claves foraneas con
 * ON DELETE CASCADE garantizan que no queden asignaciones huerfanas.
 */
class CamisetaTalla
{
    /**
     * Devuelve todas las tallas asignadas a una camiseta, con su stock.
     *
     * @param int $camisetaId
     * @return array
     */
    public static function deCamiseta(int $camisetaId): array
    {
        $db = getDbConnection();
        $sentencia = $db->prepare(
            'SELECT  t.id AS talla_id, t.codigo, t.descripcion,
                     ct.stock, ct.creado_en, ct.actualizado_en
             FROM camiseta_tallas ct
             INNER JOIN tallas t ON t.id = ct.talla_id
             WHERE ct.camiseta_id = :camiseta_id
             ORDER BY t.id ASC'
        );
        $sentencia->execute([':camiseta_id' => $camisetaId]);

        return array_map([self::class, 'formatear'], $sentencia->fetchAll());
    }

    /**
     * Devuelve las tallas de varias camisetas en UNA sola consulta.
     *
     * Se usa al listar el inventario: en lugar de ejecutar una consulta por
     * cada camiseta (problema N+1), se traen todas las asignaciones de golpe
     * y se agrupan en memoria. Con 50 camisetas esto pasa de 51 consultas a 2.
     *
     * @param array $camisetaIds
     * @return array<int, array> Tallas agrupadas por camiseta_id
     */
    public static function deVariasCamisetas(array $camisetaIds): array
    {
        if (empty($camisetaIds)) {
            return [];
        }

        $ids = array_map('intval', $camisetaIds);

        // Se construyen marcadores nombrados para mantener la consulta preparada.
        $marcadores = [];
        $parametros = [];
        foreach ($ids as $indice => $id) {
            $marcador = ':id' . $indice;
            $marcadores[]           = $marcador;
            $parametros[$marcador]  = $id;
        }

        $db = getDbConnection();
        $sentencia = $db->prepare(
            'SELECT  ct.camiseta_id,
                     t.id AS talla_id, t.codigo, t.descripcion,
                     ct.stock, ct.creado_en, ct.actualizado_en
             FROM camiseta_tallas ct
             INNER JOIN tallas t ON t.id = ct.talla_id
             WHERE ct.camiseta_id IN (' . implode(', ', $marcadores) . ')
             ORDER BY ct.camiseta_id ASC, t.id ASC'
        );
        $sentencia->execute($parametros);

        $agrupadas = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $agrupadas[$fila['camiseta_id']][] = self::formatear($fila);
        }

        return $agrupadas;
    }

    /**
     * Busca una asignacion concreta camiseta-talla.
     *
     * @return array|null Null si esa camiseta no tiene asignada esa talla
     */
    public static function find(int $camisetaId, int $tallaId): ?array
    {
        $db = getDbConnection();
        $sentencia = $db->prepare(
            'SELECT  t.id AS talla_id, t.codigo, t.descripcion,
                     ct.stock, ct.creado_en, ct.actualizado_en
             FROM camiseta_tallas ct
             INNER JOIN tallas t ON t.id = ct.talla_id
             WHERE ct.camiseta_id = :camiseta_id AND ct.talla_id = :talla_id
             LIMIT 1'
        );
        $sentencia->execute([
            ':camiseta_id' => $camisetaId,
            ':talla_id'    => $tallaId,
        ]);

        $fila = $sentencia->fetch();

        return $fila ? self::formatear($fila) : null;
    }

    /**
     * Asigna una talla a una camiseta con su stock inicial.
     *
     * @return array La asignacion creada
     */
    public static function create(int $camisetaId, int $tallaId, int $stock): array
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare(
                'INSERT INTO camiseta_tallas (camiseta_id, talla_id, stock)
                 VALUES (:camiseta_id, :talla_id, :stock)'
            );
            $sentencia->execute([
                ':camiseta_id' => $camisetaId,
                ':talla_id'    => $tallaId,
                ':stock'       => $stock,
            ]);

            $db->commit();

            return self::find($camisetaId, $tallaId);

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza el stock de una talla ya asignada a una camiseta.
     *
     * @return array|null La asignacion actualizada
     */
    public static function update(int $camisetaId, int $tallaId, int $stock): ?array
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare(
                'UPDATE camiseta_tallas
                 SET stock = :stock
                 WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id'
            );
            $sentencia->execute([
                ':stock'       => $stock,
                ':camiseta_id' => $camisetaId,
                ':talla_id'    => $tallaId,
            ]);

            $db->commit();

            return self::find($camisetaId, $tallaId);

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Quita una talla de una camiseta.
     *
     * @return bool True si se elimino la asignacion
     */
    public static function delete(int $camisetaId, int $tallaId): bool
    {
        $db = getDbConnection();
        $db->beginTransaction();

        try {
            $sentencia = $db->prepare(
                'DELETE FROM camiseta_tallas
                 WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id'
            );
            $sentencia->execute([
                ':camiseta_id' => $camisetaId,
                ':talla_id'    => $tallaId,
            ]);

            $eliminadas = $sentencia->rowCount();
            $db->commit();

            return $eliminadas > 0;

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Indica si la talla ya esta asignada a esa camiseta.
     *
     * Se consulta antes de insertar para devolver un 409 con un mensaje
     * claro en lugar de dejar que estalle la clave primaria compuesta.
     */
    public static function existe(int $camisetaId, int $tallaId): bool
    {
        $db = getDbConnection();
        $sentencia = $db->prepare(
            'SELECT COUNT(*) FROM camiseta_tallas
             WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id'
        );
        $sentencia->execute([
            ':camiseta_id' => $camisetaId,
            ':talla_id'    => $tallaId,
        ]);

        return (int) $sentencia->fetchColumn() > 0;
    }

    private static function formatear(array $fila): array
    {
        return [
            'talla_id'       => (int) $fila['talla_id'],
            'codigo'         => $fila['codigo'],
            'descripcion'    => $fila['descripcion'],
            'stock'          => (int) $fila['stock'],
            'creado_en'      => $fila['creado_en'],
            'actualizado_en' => $fila['actualizado_en'],
        ];
    }
}
