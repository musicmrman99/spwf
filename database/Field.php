<?php
namespace database;

/**
 * The data source for a value from the fetched record.
 * 
 * @see Database
 * @author William Taylor (19009576)
 */
class Field implements ArgumentSpec {
    private $name;
    private $fn;

    public function __construct($name, $fn = null) {
        $this->name = $name;
        $this->fn = $fn;
    }

    /**
     * @return string The name of this field
     */
    public function name() {
        return $this->name;
    }

    /* Implement ArgumentSpec
    -------------------- */

    public function getValue($record, $id) {
        if ($this->fn === null) return $record[$this->name];
        $fn = $this->fn;
        return $fn($record[$this->name]);
    }
}
