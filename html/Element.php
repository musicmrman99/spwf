<?php
namespace html;

use util\Util;
use format\TopFormattable;
use format\MidFormattable;

/**
 * An immutable HTML element.
 * 
 * An element contains the formatting data for HTML elements. Each element can
 * contain other data, including:
 * 
 * - null (equivilent to "")
 * - A string (which may contain arbitrary HTML)
 * - An int, float, boolean, etc.
 * - An Element or other MidFormattable
 * - An array of any dimentionality/nesting, containing any combination of the
 *   above, where each element of the top-level array is a separate child of the
 *   Element.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
class Element implements TopFormattable, MidFormattable, Node {
    private $pre;
    private $content;
    private $post;
    private $strOptions;

    /**
     * Construct a new Element object.
     * 
     * @param string $type (Optional) The type of element to create. See the
     *   HTML specification for a full list of valid types. If not given or
     *   null, then this element will have no tags or attrs (if attrs given,
     *   they will be ignored), but may contain content (ie. it's a TEXT
     *   element) and will space as usual.
     * 
     * @param array $attrs (Optional) The attributes of the element.
     *   Any attribute values that are not strings will be converted to strings.
     * 
     * @param mixed $content (Optional) The content of the element.
     *   If $content is:
     *   - null, an empty string is used
     *   - a string, it is indented if needed and inserted directly
     *   - an array, each element is indented if needed and inserted, separated
     *     by newlines
     * 
     * @param array $strOptions (Optional) Options for stringifying the element.
     *   'tag-closing-method' (TagClosingMethod) - Whether and how to close the
     *     element's opening tag. See TagClosingMethod for the different
     *     methods.
     * 
     *   'content-spacing' (ContentSpacing) - Whether to place the element's
     *     content on a separate line to its opening and closing tags and
     *     whether to indent its children. See ContentSpacing for the different
     *     types of spacing.
     * 
     *     Note: Only has any effect when the tag closing method is
     *           TagClosingMethod::CLOSING_TAG (as the element only has any
     *           content in that case).
     */
    public function __construct(
            $type = null,
            $attrs = null,
            $content = null,
            $strOptions = null
    ) {
        // Initialise fields
        $this->pre = "";
        $this->content = "";
        $this->post = "";
        $this->strOptions = [
            "tag-closing-method" => (
                !empty($strOptions["tag-closing-method"]) ?
                    $strOptions["tag-closing-method"] :
                    TagClosingMethod::CLOSING_TAG // Default
            ),
            "content-spacing" => (
                !empty($strOptions["content-spacing"]) ?
                    $strOptions["content-spacing"] :
                    ContentSpacing::UNSPACED // Default
            )
        ];

        // Alias the method
        $method = $this->strOptions["tag-closing-method"];

        // Only set pre/post if $type was given
        if ($type !== null) {
            // Join the attributes as 'name="value"' pairs; eg. (note the first
            // space):
            //   ' name1="value1" name2="value2"'
            $attrsStr = (isset($attrs) ?
                " " . Util::attrsStr($attrs, " ", "=", '"') :
                "");

            // Generate and reassign $pre, $content and $post as needed
            if ($method === TagClosingMethod::UNCLOSED) {
                $this->pre = "<$type$attrsStr>";

            } elseif ($method === TagClosingMethod::SELF_CLOSING) {
                $this->pre = "<$type$attrsStr />";

            } elseif ($method === TagClosingMethod::CLOSING_TAG) {
                $this->pre = "<$type$attrsStr>";
                $this->post = "</$type>";
            }
        }

        // Set content regardless of whether type was given ...
        if ($method === TagClosingMethod::CLOSING_TAG) {
            $this->content = $content;
        }
    }

    /* Implement Node
    -------------------------------------------------- */

    /**
     * Return the content of the Element.
     * 
     * @return mixed The content of the Element.
     */
    public function getContent() {
        return $this->content;
    }

    /* Implement TopFormattable and MidFormattable
    -------------------------------------------------- */

    /**
     * Implements the (string) cast. Has the same effect as toString().
     * 
     * @return string A string representation of the HTML tree, starting from
     *   this Element as the root.
     */
    public function __toString() {
        return $this->toString();
    }

    /* Spacing matrix:
     * 
     * Notes
     * ----------
     * 
     * - The string representation of each object contains only the characters
     *   of itself and its children - not whitespace before or after it.
     * 
     * - Round brackets indicate object responsibility - they are conceptually
     *   between the characters (like a cursor), not characters themselves
     * 
     * - An always-spaced <grandparent> is shown to demonstrate how spacing fits
     *   into the recursive tree. <grandparent>'s object formatting
     *   responsibilites are not shown.
     * 
     * Matrix
     * ----------
     * 
     * - parent unspaced / children unspaced:
     *     <grandparent>
     *         (<parent>(<child-A>(content)</child-A>)(<child-B>(content)</child-B>)</parent>)
     *     </grandparent>
     * 
     * - parent unspaced / children spaced:
     *     <grandparent>
     *         (<parent>(<child-A>
     *             (content)
     *         </child-A>)(<child-B>
     *             (content)
     *         </child-B>)</parent>)
     *     </grandparent>
     * 
     * - parent spaced / children unspaced:
     *     <grandparent>
     *         (<parent>
     *             (<child-A>(content)</child-A>)
     *             (<child-B>(content)</child-B>)
     *         </parent>)
     *     </grandparent>
     * 
     * - parent spaced / children spaced:
     *     <grandparent>
     *         (<parent>
     *             (<child-A>
     *                 (content)
     *             </child-A>)
     *             (<child-B>
     *                 (content)
     *             </child-B>)
     *         </parent>)
     *     </grandparent>
     */

    /**
     * Return the Element as a formatted string, taking into account the
     * contextual indent level given.
     * 
     * @param int $indent (Optional) The current indent level of the HTML tree.
     *   Defaults to 0, ie. format as as the top-level object (to implement
     *   TopFormattable).
     * @return string A string representation of the HTML tree starting from
     *   this Element as the root.
     */
    public function toString($indent = 0) {
        /* Single-Line Elements (unclosed/self-closing tags)
        -------------------- */

        // If this only has a $pre, then just return that, with its indent.
        $method = $this->strOptions["tag-closing-method"];
        if (
                $method === TagClosingMethod::UNCLOSED ||
                $method === TagClosingMethod::SELF_CLOSING
        ) {
            return (
                //"(". // For testing - produces HTML like the spacing matrix
                $this->pre
                //.")" // For testing
            );
        }

        /* (Potentially) Multi-Line Elements (with closing tag)
        -------------------- */

        $spacing = $this->strOptions["content-spacing"];

        // Determine the child indent
        // ContentSpacing::SPACED_WRAPPER, ::SPACED_NOT_INDENTED, ::SPACED
        $childIndent = $indent;
        if ($spacing === ContentSpacing::SPACED) { // +1 indent level
            $childIndent += 1;
        }

        // Determine the child indent string based on the child indent
        $childSep = ""; // ContentSpacing::UNSPACED => ""
        if (
                $spacing === ContentSpacing::SPACED_WRAPPER || // + separator spacing
                $spacing === ContentSpacing::SPACED_NOT_INDENTED ||
                $spacing === ContentSpacing::SPACED
        ) {
            $childSep = "\n".str_repeat("\t", $childIndent);
        }

        // Determine pre/post spacing
        // Note: The first child is special-cased (the parent specifies the indent
        //       separately to the child separator) because of SPACED_WRAPPER.
        $preSpacing = "";
        $postSpacing = "";

        if (
            $spacing === ContentSpacing::SPACED_NOT_INDENTED || // + pre/post spacing
            $spacing === ContentSpacing::SPACED
        ) {
            $preSpacing = "\n";
            $postSpacing = "\n".str_repeat("\t", $indent);
        }

        if ($spacing === ContentSpacing::SPACED_NOT_INDENTED) {
            $preSpacing .= str_repeat("\t", $indent);
        } elseif ($spacing === ContentSpacing::SPACED) {
            $preSpacing .= str_repeat("\t", $childIndent);
        }

        // Get the string representations of the children of this Element
        $contentParts = $this->splitContent($this->content, $childIndent);

        // Fit the whole thing together
        return (
            //"(". // For testing - produces HTML like the spacing matrix
            $this->pre . $preSpacing .
            implode($childSep, $contentParts) .
            $postSpacing . $this->post
            //.")" // For testing
        );
    }

    /**
     * Split the given content into distinct children of this Element.
     * 
     * Split the given content into an array of formatted strings representing
     * the distinct children of this Element and return the resulting array.
     * 
     * Specifically, if content is:
     * - An array (including a tree of arrays), return an array of pre-formatted
     *   strings, each representing a single child of this Element.
     * - Anything else (including null, int, string, Element, any other type of
     *   MidFormattable, etc.), return a 1-element (ie. 1-child) array
     *   containing the formatted string representation of that content.
     * 
     * @param mixed $content The content to split into children.
     * @param int $indent The current indent level of the HTML tree.
     * @return array<mixed> The children of this Element (based on $content)
     */
    private function splitContent($content, $indent) {
        // Base Case: If null.
        if (!isset($content)) {
            return [""];
        }

        // Recursive Case: If a MidFormattable, recurse back into toString()
        if ($content instanceof MidFormattable) {
            return [$content->toString($indent)];
        }

        // Recursive case: If an array, flatten and map to a list of strings
        elseif (is_array($content)) {
            // Flatten the array tree to an array.
            $flattenedContentParts = Util::arrayFlattenRecursive($content);

            // Null and the empty string are useless in HTML output - they just
            // add unnecessary whitespace (blank lines and indents).
            $noBlankContentParts = array_filter(
                $flattenedContentParts,
                function ($value) {
                    return $value !== null && $value !== "";
                }
            );

            // Map to string form of each element (might as well recurse and
            // extract the first element for that, as it saves on duplicating
            // type-switching logic, though of course $elem cannot be an array,
            // as we flattened the array tree).
            return array_map(
                function ($elem) use ($indent) {
                    // Preserve indent - an array is not a new indent level
                    $elemStr = $this->splitContent($elem, $indent);
                    return $elemStr[0]; // Get the first (and only) element
                },
                $noBlankContentParts
            );
        }

        // Base Case: If $content is anything other than null, an Element or an
        // array, try to convert it to a string. Also, sanitise that string to
        // avoid XSS attacks.
        else {
            return [filter_var(strval($content), FILTER_SANITIZE_STRING)];
        }
    }
}
