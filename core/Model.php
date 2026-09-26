<?php
declare(strict_types=1);

/**
 * Class Model
 *
 * Base class for all models (User, Photo, Comment).
 * It holds the PDO connection and gives small helper methods that
 * always use prepared statements with named placeholders.
 * Child models write their own SQL and call these helpers.
 */
abstract class Model
{
    /** @var PDO The database connection used by this model. */
    protected PDO $database;

    /**
     * Creates the model with a database connection.
     *
     * @param PDO|null $databaseConnection Optional connection. When it is null,
     *                                     the shared connection is used.
     * @throws RuntimeException When the shared connection cannot be created.
     */
    public function __construct(?PDO $databaseConnection = null)
    {
        $this->database = $databaseConnection ?? Database::getConnection();
    }

    /**
     * Prepares and runs one SQL query with named placeholders.
     *
     * Each value is bound with the correct PDO type (integer, boolean,
     * null, or string). Example: runQuery('SELECT * FROM photos WHERE id = :photoId',
     * ['photoId' => 5]).
     *
     * @param string               $sqlQuery        SQL text with named placeholders.
     * @param array<string, mixed> $queryParameters Placeholder names (with or without ":") and values.
     * @return PDOStatement The executed statement.
     * @throws PDOException When the query fails.
     */
    protected function runQuery(string $sqlQuery, array $queryParameters = []): PDOStatement
    {
        $statement = $this->database->prepare($sqlQuery);

        foreach ($queryParameters as $parameterName => $parameterValue) {
            $placeholderName = ':' . ltrim((string) $parameterName, ':');
            $statement->bindValue($placeholderName, $parameterValue, $this->detectParameterType($parameterValue));
        }

        $statement->execute();

        return $statement;
    }

    /**
     * Runs a query and returns the first row only.
     *
     * @param string               $sqlQuery        SQL text with named placeholders.
     * @param array<string, mixed> $queryParameters Placeholder values.
     * @return array<string, mixed>|null The row, or null when no row was found.
     * @throws PDOException When the query fails.
     */
    protected function fetchOneRow(string $sqlQuery, array $queryParameters = []): ?array
    {
        $foundRow = $this->runQuery($sqlQuery, $queryParameters)->fetch();

        return $foundRow === false ? null : $foundRow;
    }

    /**
     * Runs a query and returns all rows.
     *
     * @param string               $sqlQuery        SQL text with named placeholders.
     * @param array<string, mixed> $queryParameters Placeholder values.
     * @return array<int, array<string, mixed>> The rows. Empty array when nothing was found.
     * @throws PDOException When the query fails.
     */
    protected function fetchAllRows(string $sqlQuery, array $queryParameters = []): array
    {
        return $this->runQuery($sqlQuery, $queryParameters)->fetchAll();
    }

    /**
     * Returns the id created by the last INSERT on this connection.
     *
     * @return int The new AUTO_INCREMENT id.
     */
    protected function getLastInsertId(): int
    {
        return (int) $this->database->lastInsertId();
    }

    /**
     * Chooses the PDO parameter type for a PHP value.
     *
     * @param mixed $parameterValue The value that will be bound.
     * @return int One of the PDO::PARAM_* constants.
     */
    private function detectParameterType($parameterValue): int
    {
        if (is_int($parameterValue)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($parameterValue)) {
            return PDO::PARAM_BOOL;
        }
        if ($parameterValue === null) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }
}
