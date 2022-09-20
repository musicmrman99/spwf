<?php
namespace format;

/**
 * Something that can be formatted as part of a tree traversal. These must keep
 * certain formatting information while traversing the tree during formatting.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
interface MidFormattable {
    /**
     * Convert the object to its formatted string representation, including all
     * objects in its subtree, based on formatting information given from context
     * during tree traversal.
     * 
     * @param int $indent The current indent level of the object tree.
     * @return string A formatted string representation of the object and its
     * subtree.
     */
    public function toString($indent);
}
