<?php
namespace router\resource;

use router\Resource;
use dispatcher\Dispatcher;
use dispatcher\exceptions\UndispatchableError;
use router\exceptions\HTTPError;

/**
 * A BasicResource is a Resource that dispatches requests to one of a list of
 * registered HTTP method handlers for this resource.
 * 
 * @author William Taylor (19009576)
 */
class BasicResource implements Resource {
    private $dispatch;

    /**
     * Create a basic resource with the given initial HTTP method handlers.
     * 
     * @param array<string,RequestHandler> $initialHandlers An association
     *   between HTTP methods and handlers for those methods for this resource.
     */
    public function __construct($initialHandlers = null) {
        $this->dispatch = new Dispatcher($initialHandlers);
    }

    /**
     * Register a HTTP method handler for this resource.
     * 
     * @param string $method The HTTP method register a handler for.
     * @param RequestHandler $handler The handler to register for that HTTP
     *   method.
     */
    public function register($method, $handler) {
        $this->dispatch->register($method, $handler);
    }

    /**
     * Dispatch to the relevant HTTP method handler.
     * 
     * @param Request $request The HTTP request to handle.
     * @param mixed $args Any other data to pass down to the handlers.
     * @return Response The response to return to the client.
     * @throws HTTPError 501 (Not Implemented) if there are no handlers
     *   registered, or 405 (Method Not Allowed) if there is no handler
     *   registered for the request's HTTP method.
     */
    public function __invoke($request, ...$args) {
        // If there are no handlers registered for this resource, then it's not
        // implemented.
        if ($this->dispatch->isEmpty()) {
            throw new HTTPError(501,
                "This resource is currently not implemented"
            );
        }

        // Handle that HTTP method, passing down the request
        try {
            return $this->dispatch->toKey(
                $request->method(), array_merge([$request], $args)
            );

        } catch (UndispatchableError $e) {
            throw new HTTPError(405,
                "This resource does not support the {$request->method()} method"
            );
        }
    }

    /* Implement Resource
    -------------------------------------------------- */

    /**
     * A BasicResource has no default content type, so return null.
     * 
     * @return null
     */
    public function getDefaultContentType() {
        return null;
    }

    /* Getters and Utilities (for subtypes especially)
    -------------------------------------------------- */

    /**
     * Return an array of the HTTP methods this Resource supports.
     * 
     * @return array<string> The supported HTTP methods.
     */
    public function getSupportedMethods() {
        return $this->dispatch->getKeys();
    }

    /**
     * Returns a function that adds the given headers to the Response given to
     * that function.
     * 
     * @param array<string,string> $headers The headers to add.
     * @return callable A function that adds the given headers to the Response
     *   it is given.
     */
    public static function addHeaders($headers) {
        return function ($response) use ($headers) {
            foreach ($headers as $key => $value) {
                $response->addHeader($key, $value);
            }
            return $response;
        };
    }
}
