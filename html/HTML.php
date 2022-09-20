<?php
/**
 * A small library for creating and manipulating valid HTML elements and
 * documents, as well as formatting to human-readable output.
 * 
 * This library not designed for streaming speed (eg. it has a slow time-to-
 * first-byte), but rather focuses on ease of use, consistency, and canonical
 * representations.
 */

namespace html;

use util\Util;

// This class exists only to allow it to be auto-loaded. It contains only static
// functions and cannot be instanciated or extended.
/**
 * A collection of factory functions for various common kinds of immutable
 * Elements and trees of Elements, and for common Document trees.
 * 
 * These are designed to make your PHP code and HTML output easier to read.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
final class HTML {
    private function __construct() {}

    /* What Counts as 'Significant' Content?
    -------------------------------------------------- */

    /**
     * Predicate that determines if content is 'significant' enough to require
     * spacing of the parent.
     * 
     * @param mixed $content The content to check for 'significance'.
     * @return boolean Whether the content is 'significant'.
     */
    private static function isSignificant($content) {
        // Nulls are not significant (short-circuit)
        if ($content === null) return false;

        elseif (
                // Arrays with 2+ elements are significant
                is_array($content) && count($content) > 1 ||

                // Strings longer than 256 characters are significant
                is_string($content) && strlen($content) > 256
        )
            return true;

        // Nothing else is considered significant.
        else return false;
    }

    /* Formatting Utils
    -------------------------------------------------- */

    private static function spaced($type, $attrs, $content) {
        return new Element($type, $attrs, $content, [
            "content-spacing" => ContentSpacing::SPACED
        ]);
    }

    private static function significantSpaced($type, $attrs, $content) {
        return new Element($type, $attrs, $content, [
            "content-spacing" => (self::isSignificant($content) ? 
                ContentSpacing::SPACED :
                ContentSpacing::UNSPACED
            )
        ]);
    }

    private static function unclosed($type, $attrs) {
        return new Element($type, $attrs, null, [
            "tag-closing-method" => TagClosingMethod::UNCLOSED
        ]);
    }

    /* Attribute Utils
    -------------------------------------------------- */

    /**
     * Return the given string converted to a string that is valid for the `id`
     * attribute of a HTML element.
     * 
     * This involves:
     * - Converting all uppercase characters to lowercase
     * - Replace all spaces with dashes
     * - Removing all non-alphabetic characters from the start of the string
     * - Removing all characters that are not alphanumeric, '_', or '-'
     * 
     * Note that while the empty string is not a valid ID, this function will
     * return an empty string if an empty string is given.
     * 
     * @param string $string The string to convert into a valid HTML id.
     * @return string The string converted into a valid HTML id.
     */
    public static function id($string) {
        // Uppercase is valid, but isn't standard practice
        $string = strtolower($string);
        $string = str_replace(" ", "-", $string);
        $string = preg_replace("/^[^a-z]*/", "", $string, 1);
        $string = preg_replace("/[^a-z0-9_-]/", "", $string);
        return $string;
    }

    /* Text
    -------------------------------------------------- */

    /**
     * Create and return a new TEXT element.
     * 
     * A TEXT element is a pseudo-Element that is not a real Element - it is
     * just text, but wrapped in an Element object.
     * 
     * @param string $text The text content.
     * @return Element The created TEXT Element.
     */
    public static function text($text) {
        return new Element(null, null, $text);
    }

    /* Head
    -------------------------------------------------- */

    /**
     * Create and return a new <head> element.
     * 
     * @param mixed $content The content of the <head> element.
     * @return Element The created <head> Element.
     */
    public static function head($content) {
        return self::spaced("head", null, $content);
    }

    /* Metadata
    -------------------- */

    /**
     * Create and return a new <title> Element.
     * 
     * @param string $title The title for the Document.
     * @return Element The created <title> Element.
     */
    public static function title($title) {
        return new Element("title", null, $title);
    }

    /**
     * Create and return a new <meta> Element.
     * 
     * @param array<string,string> An associative array containing the
     *   attributes of the meta.
     * @return Element The created <meta> Element.
     */
    public static function meta($attrs) {
        return self::unclosed("meta", $attrs);
    }

    /* Common Meta tags
    ---------- */

    /**
     * Create and return a new <meta> Element for the Document's character set.
     * 
     * @param string $charset The character set of this Document.
     * @return Element The created charset <meta> Element.
     */
    public static function charset($charset) {
        return self::meta(["charset" => $charset]);
    }

    /**
     * Create and return a new <meta> Element for the Document's description.
     * 
     * @param string $description The description for the Document.
     * @return Element The created description <meta> Element.
     */
    public static function description($description) {
        return self::meta(["name" => "description", "content" => $description]);
    }

    /**
     * Create and return a new <meta> Element for the Document's viewport.
     * 
     * @param array<string,string> $viewAttrs The attributes to use for the
     *   viewport.
     * @return Element The created viewport <meta> Element.
     */
    public static function viewport($viewportAttrs) {
        return self::meta([
            "name" => "viewport",
            "content" => Util::attrsStr($viewportAttrs, ",")
        ]);
    }

    /* External Resources
    -------------------- */

    /**
     * Create and return a new Element that links to the external stylesheet at
     * $location (a relative or absolute URL).
     * 
     * @param string $location The location of the external stylesheet to link
     *   to.
     * @return Element The Element that represents the link (a <link> Element).
     */
    public static function linkCSS($location) {
        return self::unclosed("link", [
            "rel" => "stylesheet",
            "type" => "text/css",
            "href" => $location
        ]);
    }

    /**
     * Create and return a new Element that links to the external javascript at
     * $location (a relative or absolute URL).
     * 
     * @param string $location The location of the external javascript to link
     *   to.
     * @return Element The Element that represents the link (a <script>
     *   Element).
     */
    public static function linkJS($location) {
        return new Element("script", [
            "type" => "application/javascript",
            "src" => $location
        ]);
    }

    /**
     * Creates and returns a new <style> Element that embeds the given $css.
     * 
     * @param string $css The CSS to embed into the Document.
     * @return Element The created <style> Element.
     */
    public static function css($css) {
        return self::spaced("style", ["type" => "text/css"], $css);
    }

    /**
     * Creates and returns a new <script> Element that embeds the given $js.
     * 
     * @param string $js The JavaScript to embed into the Document.
     * @return Element The created <script> Element.
     */
    public static function js($js) {
        return self::spaced(
            "script", ["type" => "application/javascript"], $js
        );
    }

    /**
     * Create and return a new Element that links to the given $fonts on Google
     * Fonts.
     * 
     * This function DOES replace ' ' (space) with '+' (plus) before querying
     * the Google Fonts API. It DOES NOT provide string building for styles and
     * weights. To do this, after your font name, use a ':' (colon), then your
     * required style(s) and/or weight(s) separated by ',' (comma).
     * 
     * See https://developers.google.com/fonts/docs/getting_started for API
     * usage.
     * 
     * @param array<string> $fonts A list of font names to use.
     * @return Element The Element that represents the link (a <link> Element).
     */
    public static function linkGoogleFonts($fonts) {
        $encodedFonts = array_map(
            function ($font) {
                return urlencode(str_replace(" ", "+", $font));
            },
            $fonts
        );

        return linkCSS(
            "https://fonts.googleapis.com/css?family=" .
            implode("%7C", $encodedFonts) . // %7C = '|' (pipe) character
            "&display=swap"
        );
    }

    /* Core Structure
    -------------------------------------------------- */

    /**
     * Create and return a new <body> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   body.
     * @param mixed $content The content of the body.
     * @return Element The created <body> Element.
     */
    public static function body($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("body", $attrs, $content);
    }

    /**
     * Create and return a new <nav> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   Document's navigation section.
     * @param mixed $content The content of the Document's navigation section.
     * @return Element The created <nav> Element.
     */
    public static function nav($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("nav", $attrs, $content);
    }

    /**
     * Create and return a new <header> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   Document's header section.
     * @param mixed $content The content of the Document's header section.
     * @return Element The created <header> Element.
     */
    public static function header($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("header", $attrs, $content);
    }

    /**
     * Create and return a new <main> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   Document's main section.
     * @param mixed $content The content of the Document's main section.
     * @return Element The created <main> Element.
     */
    public static function main($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("main", $attrs, $content);
    }

    /**
     * Create and return a new <footer> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   Document's footer section.
     * @param mixed $content The content of the Document's footer section.
     * @return Element The created <footer> Element.
     */
    public static function footer($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("footer", $attrs, $content);
    }

    /**
     * Create and return a new <aside> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   aside.
     * @param mixed $content The content of the aside.
     * @return Element The created <aside> Element.
     */
    public static function aside($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("aside", $attrs, $content);
    }

    /* Generic
    -------------------------------------------------- */

    /* Basic Structure
    -------------------- */

    /**
     * Create and return an <div> (divider) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   divider.
     * @param mixed $content The content of the divider.
     * @return Element The created <div> Element.
     */
    public static function div($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::significantSpaced("div", $attrs, $content);
    }

    /**
     * Create and return an <span> (inline container) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   container. A container without attributes is rarely useful, but can be
     *   occasionally.
     * @param mixed $content The content of the container.
     * @return Element The created <span> Element.
     */
    public static function span($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::significantSpaced("span", $attrs, $content);
    }

    /* Basic Content
    -------------------- */

    /**
     * Create and return a <hN> (heading) element of the size given by $level.
     * 
     * @param int $level The size of the heading to create.
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   heading.
     * @param mixed $content The content of the heading.
     * @return Element The created <hN> Element.
     */
    public static function h($level, $attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);

        if ($level < 1) $level = 1;
        elseif ($level > 6) $level = 6;

        return new Element("h{$level}", $attrs, $content);
    }

    /**
     * Create and return a <p> (paragraph) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   paragraph.
     * @param mixed $content The content of the paragraph.
     * @return Element The created <p> Element.
     */
    public static function p($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("p", $attrs, $content);
    }

    /**
     * Create and return an <a> (anchor) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   anchor.
     * @param string $location The URL the anchor should link to.
     * @param mixed $content The content of the anchor.
     * @return Element The created <p> Element.
     */
    public static function a($attrs, $location, $content = null) {
        list($attrs, $location, $content) =
            Util::optionalFirstParam($attrs, $location, $content);
        if ($attrs === null) $attrs = [];

        return self::significantSpaced(
            "a", ["href" => $location] + $attrs, $content
        );
    }

    /**
     * Create and return an <img> (image) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   image.
     * @param string $location The relative or absolute URL of the source image.
     * @param string $altText The alternative text for the image.
     * @return Element The created <img> Element.
     */
    public static function img($attrs, $location, $altText = null) {
        list($attrs, $location, $altText) =
            Util::optionalFirstParam($attrs, $location, $altText);
        if ($attrs === null) $attrs = [];

        return self::unclosed(
            "img", ["src" => $location, "alt" => $altText] + $attrs
        );
    }

    /* Emphasis and Style
    -------------------- */

    /**
     * Create and return a <em> (emphasis) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   emphasised element.
     * @param mixed $content The content of the emphasised element.
     * @return Element The created <em> Element.
     */
    public static function em($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("em", $attrs, $content);
    }

    /**
     * Create and return a <strong> (strong emphasis) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   emphasised element.
     * @param mixed $content The content of the emphasised element.
     * @return Element The created <strong> Element.
     */
    public static function strong($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("strong", $attrs, $content);
    }

    /**
     * Create and return a <i> (italic) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   italic element.
     * @param mixed $content The content of the italic element.
     * @return Element The created <i> Element.
     */
    public static function i($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("i", $attrs, $content);
    }

    /**
     * Create and return a <b> (bold) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   bold element.
     * @param mixed $content The content of the bold element.
     * @return Element The created <b> Element.
     */
    public static function b($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("b", $attrs, $content);
    }

    /**
     * Create and return a <code> (code) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   code element.
     * @param mixed $content The content of the code element.
     * @return Element The created <code> Element.
     */
    public static function code($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("code", $attrs, $content);
    }

    /**
     * Create and return a <pre> (pre-formatted) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   pre-formatted element.
     * @param mixed $content The content of the pre-formatted element.
     * @return Element The created <pre> Element.
     */
    public static function pre($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("pre", $attrs, $content);
    }

    /* Lists
    -------------------- */

    /**
     * Create and return an <li> (list item) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   list item element.
     * @param mixed $content The content of the list item.
     * @return Element The created <li> Element.
     */
    public static function li($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::significantSpaced("li", $attrs, $content);
    }

    /**
     * Create and return an <ul> (unordered list) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   unordered list element (<li> elements will be created without any
     *   attributes).
     * @param array<mixed> $items The items of the list.
     * @return Element The created <ul> Element.
     */
    public static function ul($attrs, $items = null) {
        list($attrs, $items) = Util::optionalFirstParam($attrs, $items);
        return self::spaced(
            "ul", $attrs, Util::mapValues($items, [self::class,'li'])
        );
    }

    /**
     * Create and return an <ol> (ordered list) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   ordered list element (<li> elements will be created without any
     *   attributes).
     * @param array<mixed> $items The items of the list.
     * @return Element The created <ol> Element.
     */
    public static function ol($attrs, $items = null) {
        list($attrs, $items) = Util::optionalFirstParam($attrs, $items);
        return self::spaced(
            "ol", $attrs, Util::mapValues($items, [self::class,'li'])
        );
    }

    /* Tables
    -------------------- */

    /**
     * Create and return a table item (<td>).
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   table item element.
     * @param mixed $content The content of the item.
     * @return Element The created <td> Element.
     */
    public static function td($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("td", $attrs, $content);
    }

    /**
     * Create and return a table header (<th>).
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   table header element.
     * @param string $content The content of the header.
     * @return Element The created <th> Element.
     */
    public static function th($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return new Element("th", $attrs, $content);
    }

    /**
     * Create and return a table row (<tr>/<td>).
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   table row element (<td> elements will be created without any
     *   attributes).
     * @param mixed $row An array or object containing the data for each element
     *   of the row. In either case, only the values/properties are included in
     *   the row data. Accepts an array or object with values/property values of
     *   anything td() accepts.
     * 
     * @return Element The created <tr> Element.
     */
    public static function tr($attrs, $row = null) {
        list($attrs, $row) = Util::optionalFirstParam($attrs, $row);
        return self::spaced(
            "tr", $attrs, Util::mapValues($row, [self::class,'td'])
        );
    }

    /**
     * Create and return a table header row (<tr>/<th>).
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   table row element (<th> elements will be created without any
     *   attributes).
     * @param array<mixed> $headers An array containing the data for each header
     *   of the row. Accepts an array of anything th() accepts.
     * 
     * @return Element The created <tr> Element.
     */
    public static function trHeader($attrs, $headers = null) {
        list($attrs, $headers) = Util::optionalFirstParam($attrs, $headers);
        return self::spaced(
            "tr", $attrs, Util::mapValues($headers, [self::class,'th'])
        );
    }

    /**
     * Create a table from the given data.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   table element (<tr>, <th> and <td> elements will be created without any
     *   attributes).
     * @param array<mixed> $headers An array of items to use as the column
     *   headers. Accepts anything trHeader() accepts. Can be null (no header
     *   row).
     * @param array<mixed> $data An array of arrays or objects to create the
     *   table from. Accepts an array of anything tr() accepts (so likely an
     *   array of arrays).
     * 
     * @return Element The created <table> Element.
     */
    public static function table($attrs, $headers, $data = null) {
        list($attrs, $headers, $data) =
            Util::optionalFirstParam($attrs, $headers, $data);

        return self::spaced("table", $attrs, [
            $headers !== null ? self::trHeader($headers) : "",
            $data !== null ? Util::mapValues($data, [self::class,'tr']) : "",
        ]);
    }

    /**
     * Create a table from the given data.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   table element (<tr>, <th> and <td> elements will be created without any
     *   attributes).
     * @param array<mixed> $headers An array of items to use as the column
     *   headers. Accepts anything trHeader() accepts. Can be null (no header
     *   row).
     * @param array<mixed> $data An array of arrays or objects to create the
     *   table from. Accepts an array of anything tr() accepts (so likely an
     *   array of arrays).
     * 
     * @return Element The created <table> Element.
     */
    public static function rawTable($attrs, $headerRow, $rows = null) {
        list($attrs, $headerRow, $rows) =
            Util::optionalFirstParam($attrs, $headerRow, $rows);

        return self::spaced("table", $attrs, [
            $headerRow !== null ? $headerRow : "",
            $rows !== null ? $rows : "",
        ]);
    }

    /* Forms
    -------------------- */

    /**
     * Create and return a <label> (input label) Element that is separate to the
     * input it labels.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   label element.
     * @param string $name The name of the input to label.
     * @param string $label The label to give that input.
     * @return Element The created <label> Element.
     */
    public static function labelFor($attrs, $name, $label = null) {
        list($attrs, $name, $label) =
            Util::optionalFirstParam($attrs, $name, $label);
        if ($attrs === null) $attrs = [];

        return new Element("label", ["for" => $name] + $attrs, $label);
    }

    /**
     * Create and return a <label> (input label) Element that contains the input
     * it labels.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   label element.
     * @param string $label The label to give that input.
     * @param mixed $content The content of the label (expected to be an input
     *   Element).
     * @return Element The created <label> Element.
     */
    public static function label($attrs, $label, $content = null) {
        list($attrs, $label, $content) =
            Util::optionalFirstParam($attrs, $label, $content);
        return self::spaced("label", $attrs, [$label, $content]);
    }

    /**
     * Create and return an <input> (form input) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   input element.
     * @param string $name The name of the input (what will be submitted with
     *   the form).
     * @param string $type The type of the input (used for client-side
     *   validation).
     * @param string $value The initial value of the input. null for no value.
     * @return Element The created <input> Element.
     */
    public static function input($attrs, $name, $type, $value = null) {
        list($attrs, $name, $type, $value) =
            Util::optionalFirstParam($attrs, $name, $type, $value);
        if ($attrs === null) $attrs = [];

        $realAttrs = ["type" => $type] + $attrs;

        // Defaults to blank for the input's type
        if ($value !== null) $realAttrs["value"] = $value;

        // Some inputs don't need a name, eg. the submit button
        if ($name !== null) $realAttrs["name"] = $name;

        return self::unclosed("input", $attrs);
    }

    /**
     * Create and return an <option> (option in dropdown list) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   option element.
     * @param string $value The value to give the name of the containing
     *   <select> Element when the form is submitted if this option is selected
     *   in the list.
     * @param string $content The displayed text of the option.
     * @param bool $selected Whether this option is selected. (Note: Only one
     *   option can be selected in any given dropdown list at any one time.)
     * @return Element The created <option> Element.
     */
    public static function option($attrs, $value, $content, $selected = null) {
        list($attrs, $value, $content, $selected) =
            Util::optionalFirstParam($attrs, $value, $content, $selected);
        if ($attrs === null) $attrs = [];

        $attrs = ["value" => $value] + $attrs;
        if ($selected) $attrs["selected"] = true;

        return new Element("option", $attrs, $content);
    }

    /**
     * Create and return a <select> (dropdown list) Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   dropdown list element (<option> elements will be created without any
     *   attributes).
     * @param string $name The name of the dropdown list (what will be submitted
     *   with the form).
     * @param array<Element> $options The list of <option>s in this dropdown
     *   list.
     * @param string $default (Optional) The name of the <option> to select
     *   initially.
     */
    public static function select($attrs, $name, $options, $default = null) {
        list($attrs, $value, $content, $selected) =
            Util::optionalFirstParam($attrs, $name, $options, $default);
        if ($attrs === null) $attrs = [];

        return self::spaced(
            "select", ["name" => $name] + $attrs,
            Util::mapKeysValues(
                $options,
                function ($name, $displayedName) use ($default) {
                    return self::option(
                        $name, $displayedName, $name === $default
                    );
                },
                false
            )
        );
    }

    /**
     * Create and return a <fieldset> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   fieldset.
     * @param mixed $content The content of the fieldset.
     * 
     * @return Element The created <fieldset> Element.
     */
    public static function fieldset($attrs, $content = null) {
        list($attrs, $content) = Util::optionalFirstParam($attrs, $content);
        return self::spaced("fieldset", $attrs, $content);
    }

    /**
     * Create and return a <form> Element.
     * 
     * @param array<string,string> $attrs (Optional) The attributes to give the
     *   form element (the submit button <input> element will be created without
     *   any attributes).
     * @param string $method The method to use to submit the form. Usually "get"
     *   or "post".
     * @param string $action The relative or absolute path/URL to the file to
     *   send the data to to handle the form submission.
     * @param string $actionName The text to display on the submit button.
     * @param mixed $content The content of the form. Usually an <input>
     *   Element, an array of <input> Elements, or an array that contains
     *   <input> Elements (including <select> Elements).
     * 
     * @return Element The created <form> Element.
     */
    public static function form(
            $attrs,
            $method,
            $action,
            $actionName,
            $content = null
    ) {
        list($attrs, $method, $action, $actionName, $content) =
            Util::optionalFirstParam(
                $attrs, $method, $action, $actionName, $content
            );
        if ($attrs === null) $attrs = [];

        return self::spaced(
            "form",
            ["method" => $method, "action" => $action] + $attrs,
            [
                $content,
                self::input(null, "submit", $actionName)
            ]
        );
    }

    /* Document Templates
    -------------------------------------------------- */

    // Ironically, these are not DocumentTemplates.

    /**
     * Create and return a basic html Document.
     * 
     * @param string $title The title of the Document.
     * @param string $description The description of the Document.
     * @param array<mixed> $elems1 (Optional) The additional content of the head
     *   of the Document.
     * @param mixed $elems2 The contents of the body of the Document.
     * 
     * @return Document The created Document.
     */
    public static function basicDocument(
            $title,
            $description,
            $elems1,
            $elems2 = null
    ) {
        list($headElems, $bodyElems) =
            Util::optionalFirstParam($elems1, $elems2);

        // Return the document
        return new Document([
            self::head(array_merge(
                [
                    self::charset("UTF-8"),
                    self::title($title),
                    self::description($description),
                    self::viewport([
                        "width" => "device-width",
                        "initial-scale" => "1.0"
                    ])
                ],
                $headElems !== null ? $headElems : []
            )),

            self::body(["id" => "top"], $bodyElems)
        ]);
    }
}
