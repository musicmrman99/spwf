<?php
namespace html;

use format\TopFormattable;
use format\MidFormattable;

/**
 * A transparent mutable Element.
 * 
 * An element that produces no additional text when formatted and whose contents
 * can be set, appended to, and cleared.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
class MutableElement implements TopFormattable, MidFormattable, Node {
    /* Q: Why not make MutableElement extend Element?
     * A: This is a generic problem that subtyping is unsuitable for either way
     * around:
     * 1. "Is an ImmutableX a kind of MutableX?"
     *    No. The mutation methods are not applicable to ImmutableX, and
     *    overriding them in the subclass to do nothing breaks the Liskov
     *    Substitution Principle (LSP).
     * 
     * 2. "Is a MutableX a kind of ImmutableX?"
     *    No. MutableX would have to mutate ImmutableX's state, which:
     *    - Breaks the interface of ImmutableX ("I made a kind of ImmutableX,
     *      but it changed state?!"), thus breaking the LSP.
     *    - Means internal changes to implementation of ImmutableX or MutableX
     *      would cascade to the other, which breaks the Open-Closed Principle.
     * 
     * You can compose either way around, of course. In this implementation, a
     * MutableElement is composed of one ImmutableElement at a time.
     */

    private $wrapElem;

    /**
     * Construct a MutableElement.
     * 
     * @param mixed $content The initial content of the MutableElement.
     */
    public function __construct($content = null) {
        $this->setContent($content);
    }

    /**
     * Set the contents of the MutableElement.
     * 
     * @param mixed $content The object to set the contents of the
     * MutableElement to.
     */
    public function setContent($content) {
        $this->wrapElem = new Element(null, null, $content, [
            "content-spacing" => ContentSpacing::SPACED_WRAPPER
        ]);
    }

    /**
     * Append the given content to the current content of the MutableElement.
     * 
     * @param mixed $content The content to be appended.
     */
    public function appendContent($content) {
        $curContent = $this->wrapElem->getContent();

        if (!is_array($curContent)) $curContent = [$curContent];
        if (!is_array($content)) $content = [$content];

        $this->setContent(array_merge($curContent, $content));
    }

    /**
     * Clear the contents of the MutableElement.
     */
    public function clearContent() {
        $this->setContent(null);
    }

    /* Implement Node
    -------------------------------------------------- */

    /**
     * Return the content of the MutableElement.
     * 
     * @return mixed The content of the MutableElement.
     */
    public function getContent() {
        return $this->wrapElem->getContent();
    }

    /* Implement TopFormattable and MidFormattable
    -------------------------------------------------- */

    /**
     * Implements the (string) cast. Has the same effect as toString().
     * 
     * @return string A string representation of the HTML tree, starting from
     *   this ElementTemplate as the root.
     */
    public function __toString() {
        return $this->wrapElem->toString();
    }

    /**
     * Return the MutableElement as a formatted string, taking into account the
     * contextual indent level given.
     * 
     * @param int $indent (Optional) The current indent level of the HTML tree.
     *   Defaults to 0, ie. format as as the top-level object (to implement
     *   TopFormattable).
     * @return string A string representation of the HTML tree starting from
     *   this ElementTemplate as the root.
     * 
     * @see Element::toString()
     */
    public function toString($indent = 0) {
        return $this->wrapElem->toString($indent);
    }
}
