<?php
namespace html;

/**
 * A psuedo-enum for the spacing relationship between a parent element and its
 * children. Only some combinations conform to style guidelines.
 * 
 * There are four spacing types:
 * - An UNSPACED indent - no pre/post or seperator spacing + 0 indent level. It
 *   looks like:
 *     <a>(content)(content)</a>
 * 
 * - A SPACED_WRAPPER indent - UNSPACED + separator spacing. It looks like:
 *     <a>(content)
 *     (content)</a>
 * 
 * - A SPACED_NOT_INDENTED indent - SPACED_WRAPPER + pre/post spacing. It looks
 *   like:
 *     <a>
 *     (content)
 *     (content)
 *     </a>
 * 
 * - A SPACED indent - SPACED_NOT_INDENTED + 1 indent level. It
 *   looks like:
 *     <a>
 *       (content)
 *       (content)
 *     </a>
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
abstract class ContentSpacing {
    const UNSPACED = "unspaced";
    const SPACED_WRAPPER = "spaced-wrapper";
    const SPACED_NOT_INDENTED = "spaced-not-indented";
    const SPACED = "spaced";
}
