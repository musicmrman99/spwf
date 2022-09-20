<?php
namespace router\resource;

/**
 * Adds documentation metadata support to a {@see Resource}.
 * 
 * @author William Taylor (19009576)
 */
interface WithMetadata {
    /**
     * Return the name of the Resource.
     * 
     * @return string The name of the resource.
     */
    public function getName();

    /**
     * Return the description of the Resource.
     * 
     * @return string The description of the resource.
     */
    public function getDescription();

    /**
     * Return an array of the HTTP methods this Resource supports.
     * 
     * @return array<string> The supported HTTP methods.
     */
    public function getSupportedMethods();

    /**
     * Return true if the given method requires authentication, false otherwise.
     * 
     * @param string $method The HTTP method to check.
     * @return bool True if the given method requires authentication, false
     *   otherwise.
     * @throws UnsupportedMethod If this Resource does not support the given
     *   method (ie. it is not returned by getSupportedMethods()).
     */
    public function isAuthenticated($method);

    /**
     * Return an array of the parameters the given method of this Resource
     * supports.
     * 
     * @param string $method The HTTP method to check.
     * @return array<array<string>> An array of (name, type, description)
     *   triplets representing the parameter information.
     * @throws UnsupportedMethod If this Resource does not support the given
     *   method (ie. it is not returned by getSupportedMethods()), or if the
     *   given method does not allow parameters. Methods that allow parameters
     *   are GET, HEAD, and DELETE.
     */
    public function getSupportedParamsFor($method);

    /**
     * Return a specification string for the given method.
     * 
     * The spec may or may not have any substructure, eg. it be a JSON schema,
     * or just a description.
     * 
     * @param string $method The HTTP method to check.
     * @return string A request body specification for the given method.
     * @throws UnsupportedMethod If this Resource does not support the given
     *   method (ie. it is not returned by getSupportedMethods()), or if the
     *   given method does not allow a body. Methods that allow a body are POST,
     *   PUT, PATCH, and DELETE.
     */
    public function getBodySpecFor($method);

    /**
     * Return a description string for the responses for a given method.
     * 
     * If the resource can provide multiple kinds of responses (eg. if query
     * parameters are given, or if the server handled the request in a
     * particular way), then the returned string may describe all of them to a
     * reasonable depth. Responses variations due to parameters may be described
     * in the relevant parameter description(s) instead, or both there and here,
     * possibly to different levels of detail.
     * 
     * If this returns an empty string, then the endpoint will never respond
     * (timeout).
     * 
     * @param string $method The HTTP method to check.
     * @return string A response description, possibly including multiple kinds
     *   of responses in different circumstances.
     * @throws UnsupportedMethod If this Resource does not support the given
     *   method (ie. it is not returned by getSupportedMethods()).
     */
    public function getResponseDescriptionFor($method);

    /**
     * Return zero or more Requests that can be dispatched to the Resource to
     * produce working examples for that Resource.
     * 
     * If a Request specifies the endpoint to use, then that will take priority.
     * If not, the endpoint it is registered to in the Router will be used (if
     * any).
     * 
     * IMPORTANT: Implementers must be careful of mutating server state. The
     * Requests returned by this method MUST be dispatchable to the Resource
     * multiple times without any *problematic* side-effects. This doesn't
     * require the Requests to be safe, only idempotent - even for Requests
     * whose method is not intrinsically idempotent, eg. POST. There are various
     * strategies to achieve this, the simplest being special-casing each
     * example Request within the Resource implementation to be a dry-run.
     * 
     * @param string $method The HTTP method to check.
     * @return array<Request> An array of functionally idempotent requests to
     *   use as examples for this Resource.
     * @throws UnsupportedMethod If this Resource does not support the given
     *   method (ie. it is not returned by getSupportedMethods()).
     */
    public function getExampleRequestsFor($method);
}
