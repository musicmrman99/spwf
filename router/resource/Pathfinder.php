<?php
namespace router\resource;

use util\Util;
use router\exceptions\URIError;

/**
 * A private class that parses out pre-specified parts of a path, and converts
 * them to boolean flags (present/not present).
 * 
 * @author William Taylor (19009576)
 */
class Path {
    private $serverRoot;
    private $appPrefix;

    private $isAbsolute;
    private $inApp;
    private $pathTail;

    private $realpathFn;

    /**
     * Split the given path into parts based on the server root and application
     * prefix.
     * 
     * @param string $serverRoot The server root.
     * @param string $appPrefix The application prefix (ie. the path fragment
     *   between the server root and the application root).
     * @param string $path The path to split.
     * @param callable $realpathFn (Optional) A function that behaves like
     *   realpath() if given a path starting from the filesystem root.
     */
    public function __construct(
            $serverRoot,
            $appPrefix,
            $path,
            $realpathFn = 'realpath'
    ) {
        $this->serverRoot = self::canonicalPathFragment($serverRoot);
        $this->appPrefix = self::canonicalPathFragment($appPrefix);
        $this->realpathFn = $realpathFn;

        // Make sure eg. '/home/unn_XXXXX/public_html/../some/path' is not
        // considered in the server root.
        $path = self::canonicalPathFragment($path);

        $this->isAbsolute = false;
        if (Util::hasPrefix($path, $this->serverRoot)) {
            $this->isAbsolute = true;
            $path = Util::removePrefix($path, $this->serverRoot);
        }

        if (strlen($path) > 0 && $path[0] === "/") {
            $path = substr($path, 1);
        }

        $this->inApp = false;
        if (Util::hasPrefix($path, $this->appPrefix)) {
            $this->inApp = true;
            $path = Util::removePrefix($path, $this->appPrefix);
        }

        if (strlen($path) > 0 && $path[0] === "/") {
            $path = substr($path, 1);
        }

        $this->pathTail = $path;
    }

    /**
     * Return true if this path is absolute and inside of the server root, false
     * otherwise.
     */
    public function isAbsolute() {
        return $this->isAbsolute;
    }

    /**
     * Return true if this path points to something in the application root,
     * false otherwise.
     */
    public function isInApp() {
        return $this->inApp;
    }

    /**
     * Set whether this path is absolute (ie. is prefixed with the server root).
     * 
     * @param bool $isAbsolute Whether this path is absolute.
     */
    public function setAbsolute($isAbsolute) {
        $this->isAbsolute = $isAbsolute;
    }

    /**
     * Set whether this path represents something in the application root or the
     * server root.
     * 
     * @param bool $isAbsolute whether this path represents something in the
     *   application root or the server root.
     */
    public function setInApp($inApp) {
        $this->inApp = $inApp;
    }

    public function setPathTail($pathTail) {
        $this->pathTail = $pathTail;
    }

    public function realpath() {
        $requiredOrderedParts = array_filter(
            [
                $this->isAbsolute ? $this->serverRoot : null,
                $this->inApp ? $this->appPrefix : null,
                $this->pathTail
            ],
            function ($part) {
                return $part !== null && $part !== "";
            }
        );

        $realpathFn = $this->realpathFn;
        return $realpathFn(implode("/", $requiredOrderedParts));
    }

    /* Public Static Utilities
    -------------------------------------------------- */

    /**
     * Equivalent to realpath(), but for path fragments, and also works on files
     * and directories that do not exist.
     * 
     * Handles:
     * - OS directory separator
     * - duplicate separators
     * - . and ..
     * 
     * Based on "get_absolute_path()" from:
     *   https://www.php.net/manual/en/function.realpath.php#84012
     * 
     * @return string The absolute path of $path, without a trailing slash, and
     *   with a leading slash only if one is already present in $path.
     * 
     * @author Sven Arduwie
     * @author William Taylor (19009576)
     */
    public static function canonicalPathFragment($path) {
        // Minor hack for Windows vs. Unix paths - if $path starts with a slash,
        // it must already be 'nearly' absolute for this to work (ie. must start
        // at the filesystem root, but may contain '.' or '..').
        $initSlash = "";
        if (strlen($path) > 0 && $path[0] === "/") {
            $initSlash = "/";
        }

        $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), "strlen");
        $absolutes = array();
        foreach ($parts as $part) {
            if ("." == $part) continue;
            if (".." == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return $initSlash . implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}

/**
 * Allows finding absolute and relative paths and URLs for an application,
 * optionally checking for the existance of files or directories those paths and
 * URLs point to.
 * 
 * @author William Taylor (19009576)
 */
class Pathfinder {
    private $serverProtocol;
    private $serverHost;
    private $serverRoot;
    private $appPrefix;

    /**
     * Construct the pathfinder.
     * 
     * @throws URIError if any of its parameters have an invalid format.
     */
    public function __construct(
            $serverProtocol,
            $serverHost,
            $serverRoot,
            $appRoot
    ) {
        if (preg_match("[:/]", $serverProtocol)) {
            throw new URIError("Invalid protocol: '$serverProtocol'");
        }
        $this->serverProtocol = $serverProtocol;

        if (preg_match("[:/]", $serverHost)) {
            throw new URIError("Invalid host: '$serverHost'");
        }
        $this->serverHost = $serverHost;

        $this->serverRoot = realpath($serverRoot);
        if (!$this->serverRoot) {
            throw new URIError("Invalid server root path: '$serverRoot'");
        }

        $realAppRoot = realpath($appRoot);
        if (!$realAppRoot || !Util::hasPrefix($realAppRoot, $serverRoot)) {
            throw new URIError(
                "Invalid application root path path: '$appRoot'"
            );
        }
        $this->appPrefix = Util::removePrefix($realAppRoot, $serverRoot."/");
    }

    /**
     * Return the cannonical absolute path for the given path, or null if all
     * candidate paths are invalid.
     * 
     * If the path is:
     * - Already an absolute path, then return the normalised path to that file.
     * 
     * - Already a server path to a file in the application root, then return
     *   the normalised absolute path to that file.
     * 
     * - Valid relative to the application root, then return the normalised
     *   absolute path to that file.
     * 
     * - Valid relative to the server root, then return the normalised absolute
     *   path to that file.
     * 
     * - None of the above, return null (meaning "path not valid").
     * 
     * Absolute paths must refer to a file inside of the server root. Absolute
     * paths that refer to files outside of the server root will be treated as
     * relative to the server root, which will likely cause bugs. Use
     * {@see isAbsoluteServerPath()} if you need to check for this.
     * 
     * All paths are 'valid' if not enforcing existance, and paths that point to
     * an existing file or directory are 'valid' if enforcing existance.
     * 
     * @param string $path The path to cannonicalise.
     * @param bool $enforceExists (Optional) Whether to check if the file
     *   exists. If this is true, and the file this path represents does not
     *   exist, then return null. Defaults to false.
     * @return string The cannonicalised path, or null if the path does not
     *   point to an existing file.
     */
    public function internalPathFor($path, $enforceExists = false) {
        $realpath = $enforceExists ?
            "realpath" :
            [Path::class, "canonicalPathFragment"];

        $path = new Path($this->serverRoot, $this->appPrefix, $path, $realpath);

        // eg. /home/unn_XXXXX/public_html/app/prefix/some/path
        //     /home/unn_XXXXX/public_html/any/other/path
        if ($path->isAbsolute()) {
            return $path->realpath();
        }
        $path->setAbsolute(true);

        // eg. [/home/unn_XXXXX/public_html]/app/prefix/some/path
        if ($path->isInApp()) {
            return $path->realpath();
        }

        // eg. [/home/unn_XXXXX/public_html/app/prefix]/some/path
        // Guaranteed to be valid if not enforcing exists
        $path->setInApp(true);
        $relAppRoot = $path->realpath();
        if ($relAppRoot) return $relAppRoot;

        // eg. [/home/unn_XXXXX/public_html]/elsewhere/from/server/root
        $path->setInApp(false);
        $relServerRoot = $path->realpath();
        if ($relServerRoot) return $relServerRoot;

        // Path not valid (eg. not in server root, file not found, etc.)
        return null;
    }

    /**
     * Return the cannonical path relative to the server root for the given
     * path, or null if all candidate paths are invalid.
     * 
     * This will include an initial slash.
     * 
     * @param string $path The path to cannonicalise.
     * @param bool $enforceExists (Optional) Make an invalid path one that does
     *   not point to an existing file or directory. Defaults to false (ie. all
     *   paths are valid).
     * @return string The cannonicalised path, or null if the path is not valid.
     */
    public function serverPathFor($path, $enforceExists = false) {
        $internalPath = $this->internalPathFor($path, $enforceExists);
        if ($internalPath === null) return null; // Cascade null

        // Can assume that $internalPath will have the serverRoot as prefix, as
        // internalPathFor() enforces it.
        $relativePath = substr($internalPath, strlen($this->serverRoot));
        $serverPath = str_replace("\\", "/", $relativePath);
        return $serverPath;
    }

    /**
     * Return the cannonical path relative to the application root for the given
     * path, or null if all candidate paths are invalid (including if the path
     * is not in the application root).
     * 
     * This will include an initial slash.
     * 
     * @param string $path The path to cannonicalise.
     * @param bool $enforceExists (Optional) Make an invalid path one that does
     *   not point to an existing file or directory. Defaults to false (ie. all
     *   paths are valid).
     * @return string The cannonicalised path, or null if the path is not valid.
     */
    public function appPathFor($path, $enforceExists = false) {
        $serverPath = $this->serverPathFor($path, $enforceExists);
        if ($serverPath === null) return null; // Cascade null

        if (!Util::hasPrefix($serverPath, "/".$this->appPrefix)) return null;
        $appPath = substr($serverPath, strlen("/".$this->appPrefix));
        return $appPath;
    }

    /**
     * Return the URL for the given path, optionally appending the given query,
     * or null if all candidate URLs are invalid.
     * 
     * @param string $path The path to get the URL for.
     * @param array<string,string> $query (Optional) The query parameters to
     *   append, as key-value pairs.
     * @param bool $enforceExists (Optional) Make an invalid URL one that does
     *   not point to an existing file or directory. Defaults to false (ie. all
     *   URLs are valid).
     * @return string The constructed URL.
     */
    public function urlFor($path, $query = null, $enforceExists = false) {
        $proto = $this->serverProtocol;
        $host = $this->serverHost;

        $path = $this->serverPathFor($path, $enforceExists);
        if ($path === null) return null; // Cascade null

        $queryStr = ($query !== null) ?
            "?".Util::attrsStr($query, "&", "=") :
            "";

        return "{$proto}://{$host}{$path}{$queryStr}";
    }

    /* Extra Utilities
    ------------------------------ */

    // These may be useful to the client.

    /**
     * Return true if the given path is absolute and points to something inside
     * the server root, and false otherwise.
     * 
     * @param string $path The path to check.
     * @return bool Whether the path is absolute and points to something inside
     *   the server root.
     */
    public function isAbsoluteServerPath($path) {
        $path = new Path($this->serverRoot, $this->appPrefix, $path, $realpath);
        return $path->isAbsolute();
    }

    /**
     * Return true if the given path is relative to the server root and points
     * to something inside the application.
     * 
     * @param string $path The path to check.
     * @return bool Whether the path is relative to the server root and points
     *   to something inside the application.
     */
    public function isServerAppPath($path) {
        $path = new Path($this->serverRoot, $this->appPrefix, $path, $realpath);
        return !$path->isAbsolute() && $path->isInApp();
    }
}
