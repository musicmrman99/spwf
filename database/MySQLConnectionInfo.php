<?php
namespace database;

/**
 * Represents the info required to create a connection to a mysql database.
 * 
 * @author William Taylor (19009576)
 */
class MySQLConnectionInfo implements ConnectionInfo {
    private $hostname;
    private $username;
    private $password;
    private $dbname;

    /**
     * Construct the ConnectionInfo.
     * 
     * @param string $hostname The hostname to connect to.
     * @param string $username The username to connect with.
     * @param string $password The password for that user.
     * @param string $dbname The name of the database on the host.
     */
    public function __construct(
        $hostname, $dbname, $username, $password
    ) {
        $this->hostname = $hostname;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Get the arguments in the order they should be passed into the new PDO
     * connection as an array.
     * 
     * Example:
     * ```
     * $connection_info = new MySQLConnectionInfo(
     *   "localhost", "mydatabase", "myuser", "mypassword"
     * );
     * $connection = new \PDO(...$connection_info->get());
     * ```
     * 
     * @return array The array to unpack into the `new \PDO()` call
     */
    public function get() {
        return [
            "mysql:host={$this->hostname};dbname={$this->dbname}",
            $this->username, $this->password
        ];
    }
}
