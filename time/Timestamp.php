<?php
namespace time;

/**
 * An immutable timestamp.
 * 
 * Can be added to, removed from, and stringified.
 * 
 * Some utility factories exist for making Timestamps of particular times.
 * 
 * @author William Taylor (19009576)
 */
class Timestamp {
    private $time;

    private function __construct($time) {
        $this->time = $time;
    }

    /**
     * Return the underlying time value for serialization, persistence, hashing,
     * etc.
     * 
     * @return int The underlying time value.
     */
    public function get() {
        return $this->time;
    }

    /* Arithmetic
    -------------------------------------------------- */

    /**
     * Return a new Timestamp of the time at this timestamp, plus the given
     * duration.
     * 
     * Eg. $timestamp->plus(Duration::of(3, Duration::DAY))
     * 
     * @param Duration $duration The duration to add.
     */
    public function plus($duration) {
        return new self($this->time + $duration->get());
    }

    /**
     * Return a new Timestamp of the time at this timestamp, minus the given
     * duration.
     * 
     * Eg. $timestamp->minus(Duration::of(3, Duration::DAY))
     * 
     * @param Duration $duration The duration to subtract.
     */
    public function minus($duration) {
        return $this->plus($duration->negation());
    }

    /* Factories
    -------------------------------------------------- */

    /**
     * Return a Timestamp representing the moment it is created.
     * 
     * @return Timestamp The current time.
     */
    public static function now() {
        return new self(time());
    }

    /**
     * Return a Timestamp representing the first representable time.
     * 
     * @return Timestamp The earliest representable time.
     */
    public static function first() {
        return new self(0);
    }
}
