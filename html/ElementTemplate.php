<?php
namespace html;

use format\TopFormattable;
use format\MidFormattable;

/**
 * A transparent Element that allows components (ie. named MidFormattable
 * objects) in its subtree to be retrieved using their name, without having to
 * traverse the subtree.
 * 
 * Retrieving named components directly avoids the need for knowledge of the
 * subtree's structure. This is particularly useful for Element tree factories
 * that require some mutable components. It is assumed that all provided
 * components are present in the subtree (whose root is the wrapped element).
 * 
 * ElementTemplates produce no additional text when formatted.
 * 
 * @see html\MutableElement
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
class ElementTemplate implements TopFormattable, MidFormattable, Node {
    /* Q1: Why not make ElementTemplate extend Element?
     * A1: For consistency with DocumentTemplate.
     * 
     * Q2: Why does ElementTemplate need a managed wrapper Element?
     * A2: The only other ways of behaving like an Element is inheriting from
     * Element (rejected in A1), or taking an Element (or perhaps a
     * MidFormattable&Node) as a parameter. If we take an Element as a
     * parameter, then this type must always contain an Element (and not
     * primitive types), but an Element doesn't require an Element as its
     * contents (it would never work if it did, as that would be a recursive
     * definition with no base-case). So the question is, "should an
     * ElementTemplate behave like a wrapper for an Element, or behave like an
     * Element?"
     * 
     * It is better to "look like an Element" for consistency across the
     * interface, expecially as ElementTemplate is likely to be used in object
     * literals, like:
     * 
     *   $stuff = new ElementTemplate(
     *     HTML::div(
     *       ...
     *     ),
     *     $components
     *   );
     * 
     * This makes the interface easier to learn, but requires a wrapper element
     * to do the work under the hood.
     */

    private $wrapElem;
    private $components;

    /**
     * Create an ElementTemplate.
     * 
     * @param mixed $content The initial content of the ElementTemplate.
     * @param array<string,mixed> $components The list of components within the
     * subtree of the ElementTemplate.
     */
    public function __construct($content, $components) {
        $this->wrapElem = new Element(null, null, $content, [
            "content-spacing" => ContentSpacing::SPACED_WRAPPER
        ]);
        $this->components = $components;
    }

    /**
     * Return the list of components in this template.
     * 
     * @return array<string,mixed> The components in this template, indexed by
     * name.
     */
    public function getComponents() {
        return $this->components; // Will copy (array)
    }

    /**
     * Return the component with the given name.
     * 
     * @param string $name The name of the component to return.
     * @return mixed The component with that name.
     */
    public function getComponent($name) {
        return $this->components[$name]; // Won't copy (object)
    }

    /* Implement Node
    -------------------------------------------------- */

    /**
     * Return the content of the ElementTemplate.
     * 
     * @return mixed The content of the ElementTemplate.
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
        return $this->wrapElem->__toString();
    }

    /**
     * Return the ElementTemplate as a formatted string, taking into account the
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
