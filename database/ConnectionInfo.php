<?php
namespace database;

/**
 * Represents the arguments to `new PDO(...)`.
 * 
 * Allows you to separate the definition of the info from its usage (for
 * security and easier configuration).
 * 
 * @author William Taylor (19009576)
 */
interface ConnectionInfo {
    /**
     * Return an array of parameters (eg. connection string, username, password)
     * that `new \PDO(...)` will accept.
     */
    public function get();
}
