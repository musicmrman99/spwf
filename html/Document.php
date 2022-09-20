<?php
namespace html;

use format\TopFormattable;

/**
 * An immutable HTML document.
 * 
 * It includes the HTML5 doctype and the root <html> element.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
class Document implements TopFormattable, Node {
    private $html;

    public function __construct($content) {
        $this->html = new Element(
            "html", ["lang" => "en"], $content, [
                "content-spacing" => ContentSpacing::SPACED_NOT_INDENTED
            ]
        );
    }

    /* Implement Node
    -------------------------------------------------- */

    /**
     * Return the content of the Document.
     * 
     * @return mixed The content of the Document.
     */
    public function getContent() {
        return $this->html->getContent();
    }

    /* Implement TopFormattable
    -------------------------------------------------- */

    /**
     * Implements the (string) cast. Has the same effect as toString().
     * 
     * @return string A string representation of this Document.
     */
    public function __toString() {
        return $this->toString();
    }

    /**
     * Return the Document as a formatted string.
     * 
     * @return string A string representation of this Document.
     * 
     * @see Element::toString()
     */
    public function toString() {
        return (
            "<!doctype html>\n" .
            $this->html->toString() . "\n"
        );
    }
}
