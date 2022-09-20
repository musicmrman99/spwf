<?php
namespace html;

/**
 * A psuedo-enum for the type of tag(s) an element should use when converting to
 * string.
 * 
 * An UNCLOSED tag looks like:
 *   <tag attr="value">
 * 
 * A SELF_CLOSING tag looks like:
 *   <tag attr="value />
 * 
 * A tag that uses a CLOSING_TAG looks like:
 *   <tag attr="value>content</tag>
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
abstract class TagClosingMethod {
    const UNCLOSED = "unclosed";
    const SELF_CLOSING = "self-closing";
    const CLOSING_TAG = "closing-tag";
}
