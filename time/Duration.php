<?php
namespace time;

/**
 * An immutable duration.
 * 
 * Durations may be nagative.
 * 
 * @author William Taylor (19009576)
 */
final class Duration {
    private $duration;

    /**
     * Construct a duration.
     * 
     * @param int|float $duration The length of this duration in seconds.
     */
    private function __construct($duration) {
        $this->duration = $duration;
    }

    /**
     * Return the underlying duration value for serialization, persistence,
     * hashing, etc.
     * 
     * @return int The underlying time value.
     */
    public function get() {
        return $this->duration;
    }

    /* Arithmetic
    -------------------------------------------------- */

    /**
     * Return the duration of the sum of this duration and the given duration.
     * 
     * Eg. $fiveHours->plus(Duration::of(5, Duration::MINUTE))
     *   => a Duration of 5 hours and 5 minutes
     * 
     * @param Duration $duration The duration to add.
     * @return Duration The given duration added to this duration.
     */
    public function plus($duration) {
        return new self($this->duration + $duration->duration);
    }

    /**
     * Return the duration of this duration, minus the given duration.
     * 
     * Eg. $fiveHours->minus(Duration::of(5, Duration::MINUTE))
     *   => a Duration of 4 hours and 55 minutes
     * 
     * @param Duration $duration The duration to subtract.
     * @return Duration The given duration subtracted from this duration.
     */
    public function minus($duration) {
        return new self($this->duration - $duration->duration);
    }

    /**
     * Return the duration of this duration multiplied by the given value.
     * 
     * Eg. $fiveHours->multipliedBy(5)
     *   => a Duration of 25 hours
     * 
     * @param int|float $num The number to multiply this duration by.
     * @return Duration This duration multiplied by the given value.
     */
    public function multipliedBy($num) {
        return new self($this->duration * $num);
    }

    /**
     * Return the duration of this duration divided by the given value.
     * 
     * The resulting duration is not rounded.
     * 
     * Eg. $fiveHours->dividedBy(10)
     *   => a Duration of 30 minutes
     * 
     * @param int|float $num The number to divide this duration by.
     * @return Duration This duration divided by the given value.
     */
    public function dividedBy($num) {
        return new self($this->duration / $num);
    }

    /**
     * Return the negation of this duration.
     * 
     * Eg. $fiveHours->negation()
     *   => a Duration of -5 hours
     * 
     * @return Duration The negation of this duration.
     */
    public function negation() {
        return new self(-$this->duration);
    }

    /**
     * Return the reciprocal of this duration around the given unit value.
     * 
     * The resulting duration is not rounded.
     * 
     * Eg. $fiveHours->reciprocate(Duration::HOUR)
     *   => a Duration of 10 minutes
     * 
     * @param int $period A reference period, in seconds, used as the 'unit
     *   value' (as if it were 1) around which to reciprocate this duration.
     * @return Duration The reciprocal of this duration around the given
     *   reference unit.
     */
    public function reciprocal($period) {
        return new self(1 / ($this->duration()/$period) * $period);
    }

    /* Constants and Factories
    -------------------------------------------------- */

    const SECOND = 1;
    const MINUTE = 60;
    const HOUR   = 3600;
    const DAY    = 86400;
    const WEEK   = 604800;
    const YEAR   = 31536000;

    /**
     * Return a duration of the given number of the given reference period (in
     * seconds).
     * 
     * @param int $num The number of intervals of of the given period to
     *   subtract.
     * @param int $period The reference period, in seconds. Often one of the
     *   constants defined in this class.
     * 
     * @return Duration The given number of the given length of period.
     */
    public static function of($num, $period) {
        return new self($num * $period);
    }

    /**
     * Return the largest representable duration.
     * 
     * Timestamp::first().plus(Duration::max()) should give the largest possible
     * {@see Timestamp}, or at least a valid Timestamp in the future.
     * 
     * @return Duration The largest representable duration.
     */
    public static function max() {
        return new self(2147483647); // See 'Y2038 problem'
    }
}
