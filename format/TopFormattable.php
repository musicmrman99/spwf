<?php
namespace format;

/**
 * Something that can be formatted from the top level (ie. not somewhere in the
 * middle of the tree).
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
interface TopFormattable {
    /**
     * Convert the object to its formatted string representation, including all
     * objects in its subtree.
     */
    public function toString();

    /**
     * Implements the (string) cast. Should call toString().
     */
    public function __toString();
}
