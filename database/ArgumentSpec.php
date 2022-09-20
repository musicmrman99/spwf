<?php
namespace database;

/**
 * A provider of a value for a given record.
 * 
 * Also provides the primary object ID value for that record.
 * 
 * @see Database
 * @author William Taylor (19009576)
 */
interface ArgumentSpec {
    /**
     * Given the record, return the data to use for this field/parameter of the
     * object constructor.
     * 
     * @param array<mixed> $record The raw values for this record, as extracted
     *   by PDO.
     * @param mixed $id The primary key value for this record, ie. the value for
     *   one of the fields.
     * @return mixed A value for the given record to pass to the constructor.
     */
    public function getValue($record, $id);
}
