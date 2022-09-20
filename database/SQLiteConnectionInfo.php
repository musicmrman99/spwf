<?php
namespace database;

/**
 * Represents the info required to create a connection to a mysql database.
 * 
 * @author William Taylor (19009576)
 */
class SQLiteConnectionInfo implements ConnectionInfo {
    private $filename;

    /**
     * Construct the ConnectionInfo.
     * 
     * @param string $filename The path to the database file.
     */
    public function __construct($filename) {
        $this->filename = $filename;
    }

    /**
     * Get the arguments in the order they should be passed into the new PDO
     * connection as an array.
     * 
     * Example:
     * ```
     * $connectionInfo = new SQLiteConnectionInfo(
     *   "db/name.sqlite", "myuser", "mypassword"
     * );
     * $connection = new \PDO(...$connectionInfo->get());
     * ```
     * 
     * @return array The array to unpack into the `new \PDO()` call
     */
    public function get() {
        return ["sqlite:{$this->filename}", null, null];
    }
}
