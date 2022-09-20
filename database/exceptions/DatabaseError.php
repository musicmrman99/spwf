<?php
namespace database\exceptions;

// From: https://www.php.net/manual/en/language.exceptions.extending.php
/**
 * Exception for errors associated with the Database class or PDO.
 * 
 * @author William Taylor (19009576)
 */
class DatabaseError extends \Exception {
    public function __construct($message, $previous = null) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Convert the exception to a human-readable string.
     * 
     * Uses the human-readable string for the previous exception if there was
     * one.
     */
    public function __toString() {
        $previous = $this->getPrevious();
        if ($previous !== null) {
            return strval($previous);
        }
        return "DatabaseError: {$this->message}\n";
    }
}
