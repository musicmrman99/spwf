<?php
/**
 * @see Util
 */

namespace util;

/**
 * A collection of generic array, string, and function manipulation utilities
 * that extend those built into PHP.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 * Created: 2020-03-04
 * Updated: 2021-12-15
 */
class Util {
    /**
     * Not instanciable.
     */
    private function __construct() {}

    /* Arrays (Plain and Associative)
    -------------------------------------------------- */

    /**
     * Map an associative array to a plain array of 2-element arrays.
     * 
     * @param array<mixed,mixed> $assocArray An associative array.
     * @return array<mixed> $array A plain array of 2-element arrays.
     */
    public static function assocToPlain($assocArray) {
        return self::mapKeysValues(
            $assocArray,
            function ($key, $value) {
                return [$key, $value];
            },
            false
        );
    }

    /**
     * Map $obj using $callback, converting $obj from an object to an array if
     * needed.
     * 
     * @param mixed $obj The array/object to be converted/mapped.
     * @param callback $fn The mapping function to use.
     * @param boolean $reassociate Whether to reassociate the mapped value to
     *   the original key (true), or to instead build a plain array (false).
     *   Default: true.
     * 
     * @return array The mapped associative array.
     */
    public static function mapValues($obj, $fn, $reassociate = true) {
        $arr = [];
        foreach ($obj as $key => $value) {
            if ($reassociate) {
                $arr[$key] = call_user_func($fn, $value);
            } else {
                $arr[] = call_user_func($fn, $value);
            }
        }
        return $arr;
    }

    /**
     * Map $obj using $callback, converting $obj from an object to an array if
     * needed, where $callback is passed both the key and value for each
     * key-value pair in $obj.
     * 
     * @param mixed $obj The array/object to be converted/mapped.
     * @param callback $fn The mapping function to use.
     * @param boolean $reassociate Whether to reassociate the mapped key-value
     *   pair to the original key (true), or to instead build a plain array
     *   (false). Default: true.
     * 
     * @return array The mapped plain array.
     */
    public static function mapKeysValues($obj, $fn, $reassociate = true) {
        $arr = [];
        foreach ($obj as $key => $value) {
            if ($reassociate) {
                $arr[$key] = call_user_func($fn, $key, $value);
            } else {
                $arr[] = call_user_func($fn, $key, $value);
            }
        }
        return $arr;
    }

    /**
     * Filter $obj using $callback, converting $obj from an object to an array if
     * needed.
     * 
     * @param mixed $obj The array/object to be filtered.
     * @param callback $fn The filtering function to use.
     * @param boolean $reassociate Whether to reassociate the included value to
     *   the original key (true), or to instead build a plain array (false).
     *   Default: true.
     */
    public static function filterValues($obj, $fn, $reassociate = true) {
        $arr = [];
        foreach ($obj as $key => $value) {
            if (call_user_func($fn, $value)) {
                if ($reassociate) {
                    $arr[$key] = $value;
                } else {
                    $arr[] = $value;
                }
            }
        }
        return $arr;
    }

    /**
     * Filter $obj using $callback, converting $obj from an object to an array
     * if needed, where $callback is passed both the key and value for each
     * key-value pair in $obj.
     * 
     * @param mixed $obj The array/object to be filtered.
     * @param callback $fn The filtering function to use.
     * @param boolean $reassociate Whether to reassociate the included value to
     *   the original key (true), or to instead build a plain array (false).
     *   Default: true.
     */
    public static function filterKeysValues($obj, $fn, $reassociate = true) {
        $arr = [];
        foreach ($obj as $key => $value) {
            if (call_user_func($fn, $key, $value)) {
                if ($reassociate) {
                    $arr[$key] = $value;
                } else {
                    $arr[] = $value;
                }
            }
        }
        return $arr;
    }

    /**
     * A stable sort that acts like usort().
     * 
     * @param array<mixed,mixed> $array The associative array to be sorted.
     * @param callable $keyFn (Optional) A callable that takes two parameters
     *   and returns a number <0 if the first sorts before the second, 0 if they
     *   both sort the same, and >0 if the first sorts after the second. If not
     *   given, defaults to numeric difference (so only works on arrays of
     *   numbers - int, float, or a mix).
     * 
     * @return array The sorted array.
     * 
     * @author William Taylor (19009576)
     * @author Barmar (https://stackoverflow.com/users/1491895/barmar)
     */
    public static function sortValues(
            $array,
            $keyFn = null,
            $reassociate = false
    ) {
        return self::_sortKeysValues($array, $keyFn, $reassociate, false);
    }

    /**
     * A stable sort that acts like usort(), but with both keys and values being
     * sorted on.
     * 
     * @param array<mixed,mixed> $array The associative array to be sorted.
     * @param callable $keyFn (Optional) A callable that takes two parameters
     *   and returns a number <0 if the first sorts before the second, 0 if they
     *   both sort the same, and >0 if the first sorts after the second. If not
     *   given, defaults to numeric difference (so only works on arrays of
     *   numbers - int, float, or a mix).
     * 
     * @return array The sorted associative array.
     * 
     * @author William Taylor (19009576)
     * @author Barmar (https://stackoverflow.com/users/1491895/barmar)
     */
    public static function sortKeysValues(
            $array,
            $keyFn = null,
            $reassociate = false
    ) {
        return self::_sortKeysValues($array, $keyFn, $reassociate, true);
    }

    /**
     * sortValues() and sortKeysValues() were too similar to keep separate.
     */
    private static function _sortKeysValues(
            $array,
            $keyFn,
            $reassociate,
            $giveKeys
    ) {
        if ($keyFn === null) {
            $keyFn = $giveKeys ?
                function ($ak, $av, $bk, $bv) {
                    return $av - $bv;
                } :
                function ($a, $b) {
                    return $a - $b;
                };
        }

        // Modified from: https://stackoverflow.com/a/12678577/16967315
        $temp = [];
        $i = 0;
        foreach ($array as $key => $value) {
            $temp[] = [$i, $key, $value];
            $i++;
        }

        uasort($temp, function($a, $b) use ($keyFn, $giveKeys) {
            return $a[2] === $b[2] ? ($a[0] - $b[0]) : (
                $giveKeys ?
                    $keyFn($a[1], $a[2], $b[1], $b[2]) :
                    $keyFn($a[2], $b[2])
            );
        });

        $newArray = [];
        foreach ($temp as $elem) {
            if ($reassociate) {
                $newArray[$elem[1]] = $elem[2];
            } else {
                $newArray[] = $elem[2];
            }
        }

        return $newArray;
    }

    /**
     * Merge all given associative arrays into a single array and return the new
     * array.
     * 
     * Keys of later arguments overwrite keys of earlier arguments.
     * 
     * @param array<array> ...$arrays The arrays to merge.
     * @return array The merged array.
     */
    public static function mergeAssociativeArrays(...$arrays) {
        $result = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    // Modified from: https://stackoverflow.com/a/1320156
    public static function arrayFlattenRecursive($arr) {
        $ret = array();
        // 'Use' the variable in the outer scope
        // See: https://www.php.net/manual/en/functions.anonymous.php#example-167
        array_walk_recursive($arr, function($a) use (&$ret) {
            // Append to array
            // See: https://www.php.net/manual/en/language.types.array.php#language.types.array.syntax.modifying
            $ret[] = $a;
        });
        return $ret;
    }

    // The next two are derived from: https://stackoverflow.com/a/39877269

    /**
     * Return true if the given function returns true for any element in the
     * array, or false otherwise.
     * 
     * @param mixed $obj The array/object whose elements are to be checked.
     * @param callable $fn The function that is given each element of the array
     *   in turn.
     * @return bool If the function returns true for any item in the array,
     *   return true. Otherwise, return false.
     */
    public static function any($obj, $fn) {
        foreach ($obj as $key => $value) {
            if($fn($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return true if the given function returns true for every element in the
     * array, or false otherwise.
     * 
     * @param mixed $obj The array/object whose elements are to be checked.
     * @param callable $fn The function that is given each element of the array
     *   in turn.
     * @return bool If the function returns true for every item in the array,
     *   return true. Otherwise, return false.
     */
    public static function every($obj, $fn) {
        foreach ($obj as $key => $value) {
            if(!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    /* Strings
    -------------------------------------------------- */

    /**
     * Return true if the given string starts with prefix.
     * 
     * @param string $str The string to check.
     * @param string $prefix The prefix to check for at the start of str.
     * @return bool True if the given string starts with prefix, otherwise false.
     */
    public static function hasPrefix($str, $prefix) {
        return substr($str, 0, strlen($prefix)) === $prefix;
    }

    /**
     * Return the given string without the given prefix.
     * 
     * The string is assumed to have the prefix. Use {@see hasPrefix()} if you
     * need to check for that.
     * 
     * @param string $str The string to remove the prefix from.
     * @param string $prefix The prefix to remove.
     * @return string The given string with the prefix removed.
     */
    public static function removePrefix($str, $prefix) {
        return substr($str, strlen($prefix));
    }

    /**
     * Create and return a string of key-value pairs from an associative array.
     * 
     * This will produce a string that consists of zero or more instances of the
     * following string, separated by $attrSep (where $key and $value are from
     * the $attrs parameter):
     *   '{key}{kvSep}{vEnclose}{value}{vEnclose}'
     * Eg.
     *   'key="value" key2="value2"'
     * 
     * @param array $attrs The source of the key-value pairs.
     * @param string $attrSep The separator character(s) to insert between each
     *   key-value pair. Default: ' ' (space).
     * @param string $kvSep The separator character(s) to insert between each
     *   key and value. Default: '=' (equals).
     * @param string $vEnclose The characters(s) to insert at either side of the
     *   value of each key-value pair. Default: '' (nothing);
     */
    public static function attrsStr(
            $attrs,
            $attrSep = null,
            $kvSep = null,
            $vEnclose = null
    ) {
        if ($attrSep === null) $attrSep = ' ';
        if ($kvSep === null) $kvSep = '=';
        if ($vEnclose === null) $vEnclose = '';

        $attrsStr = "";
        $firstElem = true;
        foreach ($attrs as $key => $value) {
            if (!$firstElem) {
                $attrsStr .= $attrSep;
            } else {
                $firstElem = false;
            }

            $attrsStr .=
                strval($key)."$kvSep$vEnclose".strval($value)."$vEnclose";
        }
        return $attrsStr;
    }

    /**
     * Create and return an associative array from a string of key-value pairs.
     * 
     * This will take a string that consists of zero or more instances of the
     * following string, separated by $attrSep (where $key and $value are from
     * the $attrs parameter):
     *   '{key}{kvSep}{vEnclose}{value}{vEnclose}'
     * Eg.
     *   'key="value" key2="value2"'
     * 
     * @param string $attrs The source of the key-value pairs.
     * @param string $attrSep The separator character(s) to split key-value
     *   pairs on. Default: ' ' (space).
     * @param string $kvSep The separator character(s) to split key and value
     *   on. Default: '=' (equals).
     * @param string $vEnclose The characters(s) that enclose the value of each
     *   key-value pair. Default: '' (nothing);
     */
    public static function parseAttrsStr(
            $attrsStr,
            $attrSep = null,
            $kvSep = null,
            $vEnclose = null
    ) {
        if ($attrSep === null) $attrSep = ' ';
        if ($kvSep === null) $kvSep = '=';
        if ($vEnclose === null) $vEnclose = '';

        $enclen = strlen($vEnclose);

        $attrs = [];
        $attrsSplit = explode($attrSep, $attrsStr);
        foreach ($attrsSplit as $kvPair) {
            list($key, $value) = explode($kvSep, $kvPair);
            $attrs[$key] = substr($value, $enclen, strlen($value) - $enclen);
        }
        return $attrs;
    }

    /**
     * Convert the given JSON string to an object tree.
     * 
     * Throw an exception if a decoding error is encountered.
     * 
     * @param string $jsonStr The string to convert.
     * @return string The converted string.
     */
    public static function toJSON($jsonStr) {
        return json_decode(
            $jsonStr,
            null,
            512,
            (
                JSON_INVALID_UTF8_SUBSTITUTE |
                JSON_OBJECT_AS_ARRAY |
                JSON_THROW_ON_ERROR
            )
        );
    }

    /* Functions and Callables
    -------------------------------------------------- */

    /**
     * Treats the first parameter given as if it's optional, and returns the
     * actual parameter list. This can be extracted with the list() PHP builtin.
     * 
     * Commonly used like so:
     *   function example($param1, $param2 = null) {
     *     // $thing1 will be null if $param2 is null
     *     list($thing1, $thing2) = Util::optionalFirstParam($param1, $param2);
     *     ...
     *   }
     *   function example($param1, $param2, $param3 = null) {
     *     // $thing1 will be null if $param3 is null
     *     list($thing1, $thing2, $thing3) =
     *       Util::optionalFirstParam($param1, $param2, $param3);
     *     ...
     *   }
     * 
     * @param mixed $params An array of actual parameters.
     * @return array An array of parameters, where if the last input parameter
     *   is null, then the parameter list is shifted one place to the right and
     *   a null inserted as the first element.
     */
    public static function optionalFirstParam(...$params) {
        $numParams = count($params);
        if ($numParams > 1 && $params[$numParams-1] === null) {
            return array_merge([null], array_slice($params, 0, $numParams-1));
        } else {
            return $params;
        }
    }
}
