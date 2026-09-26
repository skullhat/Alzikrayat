<?php
declare(strict_types=1);

/**
 * Class Database
 *
 * Creates one shared PDO connection to MySQL (Singleton pattern).
 * Every model asks this class for the connection, so the application
 * opens only one database connection per request.
 *
 */
final class Database
{
    private const DATABASE_HOST = '127.0.0.1';
    private const DATABASE_PORT = 3306;
    private const DATABASE_NAME = 'alzikrayat';
    private const DATABASE_USER = 'root';
    private const DATABASE_PASSWORD = '';

    /** @var PDO|null The single shared connection, created on first use. */
    private static ?PDO $sharedConnection = null;

    /**
     * Private constructor. Code outside this class cannot use "new Database()".
     */
    private function __construct()
    {
    }

    /**
     * Private clone method. The shared connection cannot be copied.
     */
    private function __clone()
    {
    }

    /**
     * Blocks unserializing, because it would create a second instance.
     *
     * @return void
     * @throws RuntimeException Always.
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('The database connection cannot be unserialized.');
    }

    /**
     * Returns the shared PDO connection. Creates it on the first call.
     *
     * Connection settings:
     * - utf8mb4 charset, so Arabic text and emoji are stored correctly.
     * - Exceptions for all database errors.
     * - Associative arrays as the default fetch mode.
     * - Native prepared statements (emulation turned off).
     * - MySQL session time zone equal to the PHP time zone.
     *
     * @return PDO The connected PDO object.
     * @throws RuntimeException When the connection fails. The message is safe
     *                          to show to users; the real error goes to the log.
     */
    public static function getConnection(): PDO
    {
        if (self::$sharedConnection instanceof PDO) {
            return self::$sharedConnection;
        }

        $dataSourceName = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            self::DATABASE_HOST,
            self::DATABASE_PORT,
            self::DATABASE_NAME
        );

        $connectionOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $connection = new PDO(
                $dataSourceName,
                self::DATABASE_USER,
                self::DATABASE_PASSWORD,
                $connectionOptions
            );

            // TIMESTAMP columns are converted using the session time zone.
            // We send PHP's current offset (for example +02:00) so the times
            // saved by MySQL and the times shown by PHP always match.
            $timeZoneStatement = $connection->prepare('SET time_zone = :timeOffset');
            $timeZoneStatement->execute([':timeOffset' => (new DateTime())->format('P')]);

            self::$sharedConnection = $connection;
        } catch (PDOException $connectionError) {
            error_log('[Alzikrayat] Database connection failed: ' . $connectionError->getMessage());
            throw new RuntimeException('Could not connect to the database. Please try again later.');
        }

        return self::$sharedConnection;
    }
}
