<?php
namespace router\resource;

use router\exceptions\UnsupportedMethod;

/**
 * Class of utility functions (static methods) for implementing
 * {@see WithMetadata}.
 * 
 * @author William Taylor (19009576)
 */
class MetadataUtils {
    /**
     * Return a list of all methods specified by HTTP that REST APIs use.
     */
    public static function getAllMethods() {
        return ["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"];
    }

    /**
     * Return true if the given method can take query parameters.
     * 
     * @param string $method The HTTP method to check.
     * @return bool True if the given method can take query parameters.
     */
    public static function methodAllowsParams($method) {
        return in_array($method, ["GET", "HEAD", "DELETE"]);
    }

    /**
     * Return true if the given method can take a request body.
     * 
     * @param string $method The HTTP method to check.
     * @return bool True if the given method can take a request body.
     */
    public static function methodAllowsBody($method) {
        return in_array($method, ["POST", "PUT", "PATCH", "DELETE"]);
    }

    /**
     * Throw an exception if the given method cannot take query parameters.
     * 
     * @param string $method The HTTP method to check.
     * @throws UnsupportedMethod If the given method cannot take query
     *   parameters.
     */
    public static function checkParamsAllowedFor($method) {
        if (!self::methodAllowsParams($method)) {
            throw new UnsupportedMethod(
                "$method method cannot have query parameters"
            );
        }
    }

    /**
     * Throw an exception if the given method cannot takea request body.
     * 
     * @param string $method The HTTP method to check.
     * @throws UnsupportedMethod If the given method cannot takea request body.
     */
    public static function checkBodyAllowedFor($method) {
        if (!self::methodAllowsBody($method)) {
            throw new UnsupportedMethod(
                "$method method cannot have a body"
            );
        }
    }

    /**
     * Throw an exception if the given resource does not support the given
     * method.
     * 
     * @param WithMetadata $resource The resource (that supports documentation
     *   metadata) to check.
     * @param string $method The HTTP method to check is supported.
     * 
     * @throws UnsupportedMethod If the given resource does not support the
     *   given method.
     */
    public static function checkSupportsMethod($resource, $method) {
        $name = $resource->getName();
        $supportedMethods = $resource->getSupportedMethods();

        if (!in_array($method, $supportedMethods)) {
            $supportedMethodsStr = implode(", ", $supportedMethods);
            throw new UnsupportedMethod(
                "Resource '$name' does not support HTTP method '$method',"
                ." only: $supportedMethodsStr"
            );
        }
    }
}
