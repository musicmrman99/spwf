<?php
namespace html;

/**
 * An object that can contain zero or more scalars and/or other objects.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
interface Node {
    /**
     * Get the content of the node.
     * 
     * @return mixed The content of the node.
     */
    public function getContent();
}
