<?php
namespace database;

/**
 * Used to allow domain classes to be fetched into (with PDO::FETCH_CLASS) when
 * fetching objects from the database.
 * 
 * @author William Taylor (19009576)
 */
trait Bindable {
    /**
     * Bind the given value to the given attribute in $this object if the
     * attribute is in the given mask.
     * 
     * @param string $attr The name of the attribute to bind.
     * @param mixed $value The value to bind to the attribute.
     * @param array<string> $mask A list of attributes allowed to be bound.
     */
    public function bind($attr, $value, $mask) {
        if (in_array($attr, $mask)) {
            $this->$attr = $value;
        }
    }

    /**
     * Bind all [attribute => value] pairs in $map for which the attribute is in
     * the given mask to $this object.
     * 
     * @param array<string,mixed> $map A map of bindable attributes.
     * @param array<string> $mask An array of valid attributes in $map.
     *   Attributes not in this list will be ignored.
     */
    public function bindAll($map, $mask) {
        foreach ($map as $attr => $value) {
            $this->bind($attr, $value, $mask);
        }
    }
}
