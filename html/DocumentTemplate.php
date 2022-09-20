<?php
namespace html;

use format\TopFormattable;

/**
 * A Document that allows components (named MidFormattable objects) within it to
 * be retrieved using their name, without having to traverse the Document.
 * 
 * Retrieving named components directly avoids the need for knowledge of the
 * Document's structure. It is assumed that all provided components are present
 * in the Document.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
class DocumentTemplate implements TopFormattable, Node {
    /* Q: Why not make DocumentTemplate extend Document?
     * A: Because then you couldn't make a DocumentTemplate from Document
     * factory functions, as a Document can't be a child of another Document.
     */

    private $document;
    private $components;

    /**
     * Create a DocumentTemplate.
     * 
     * @param TopFormattable&Node $document The Document or other TopFormattable
     * Node to wrap.
     * @param array<string,mixed> $components The list of components
     * within the subtree of that Document/TopFormattable.
     */
    public function __construct($document, $components) {
        $this->document = $document;
        $this->components = $components;
    }

    /**
     * Return the list of components in this template.
     * 
     * @return array<string,mixed> The components in this template,
     * indexed by name.
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
     * Return the content of the DocumentTemplate.
     * 
     * @return mixed The content of the DocumentTemplate.
     */
    public function getContent() {
        return $this->document->getContent();
    }

    /* Implement TopFormattable
    -------------------------------------------------- */

    /**
     * Implements the (string) cast. Has the same effect as toString().
     * 
     * @return string A string representation of this DocumentTemplate.
     */
    public function __toString() {
        return $this->document->__toString();
    }

    /**
     * Return the DocumentTemplate as a formatted string.
     * 
     * @return string A string representation of this DocumentTemplate.
     * 
     * @see Document::toString()
     */
    public function toString() {
        return $this->document->toString();
    }
}
