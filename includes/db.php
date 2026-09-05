<?php
/**
 * GodsForum - PDO database layer.
 *
 * Every query in this project runs through prepared statements, which is what
 * keeps the forum safe from SQL injection. No string concatenation of user
 * input into SQL is allowed anywhere in the codebase.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        if (DEBUG_MODE) {
            exit('Database connection failed: ' . $e->getMessage());
        }
        exit('The forum database is unavailable. Please try again shortly.');
    }

    return $pdo;
}

/**
 * Run a prepared statement and return the statement object.
 *
 * @param array<string|int, mixed> $params
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $placeholder = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');

        $type = match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };

        $stmt->bindValue($placeholder, $value, $type);
    }

    $stmt->execute();

    return $stmt;
}

/**
 * Fetch a single row, or null.
 *
 * @param array<string|int, mixed> $params
 * @return array<string, mixed>|null
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch every row of a result set.
 *
 * @param array<string|int, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/**
 * Fetch the first column of the first row.
 *
 * @param array<string|int, mixed> $params
 */
function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

function db_insert_id(): int
{
    return (int) db()->lastInsertId();
}
