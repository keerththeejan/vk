<?php
declare(strict_types=1);

/**
 * Schema helpers for dynamic product table inserts.
 */
final class ProductSchema
{
    /** @var array<string, bool> */
    private static array $tableCache = [];

    /** @var array<string, list<string>> */
    private static array $columnCache = [];

    public static function tableExists(PDO $pdo, string $table): bool
    {
        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            self::$tableCache[$table] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$tableCache[$table] = false;
        }
        return self::$tableCache[$table];
    }

    /** @return list<string> */
    public static function columns(PDO $pdo, string $table): array
    {
        if (isset(self::$columnCache[$table])) {
            return self::$columnCache[$table];
        }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            self::$columnCache[$table] = array_map(static fn(array $c): string => (string) $c['Field'], $cols);
        } catch (Throwable) {
            self::$columnCache[$table] = [];
        }
        return self::$columnCache[$table];
    }

    public static function insertFiltered(PDO $pdo, string $table, array $payload): ?int
    {
        $columns = self::columns($pdo, $table);
        if ($columns === []) {
            return null;
        }
        $filtered = array_intersect_key($payload, array_flip($columns));
        if ($filtered === []) {
            return null;
        }
        $names = array_keys($filtered);
        $quoted = implode(', ', array_map(static fn(string $n): string => "`{$n}`", $names));
        $placeholders = implode(', ', array_map(static fn(string $n): string => ':' . $n, $names));
        $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$quoted}) VALUES ({$placeholders})");
        $stmt->execute($filtered);
        return (int) $pdo->lastInsertId();
    }

    public static function queryOptions(PDO $pdo, string $sql): array
    {
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }
}
