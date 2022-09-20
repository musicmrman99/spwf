<?php
/**
 * A simple routing framework.
 * 
 * Package Overview
 * --------------------------------------------------
 * 
 * Normal Operation
 * --------------------
 * 
 * A Router routes a Request for an endpoint to Resources. Resources (and
 * ErrorResources, see below) are callable handlers that must take a Request and
 * return a Response. That Response is then sent back to the client by the
 * Router. To use a Router, first register Resources, ErrorResources, and any
 * new GlobalErrorResource with the Router, before finally dispatching the
 * request being routed.
 * 
 * Error Handling
 * --------------------
 * 
 * If an endpoint has no corresponding Resource, or a Resource throws a
 * HTTPError while handling the Request, then an ErroredRequest is constructed
 * and passed to all the ErrorResources registered (based on the request) in
 * turn until one provides an error back to the Router (which will provide it
 * back to the client).
 * 
 * Each endpoint can have an ErrorResource registered for it, which are
 * consulted first upon encountering an error. Each error code of a HTTPError
 * can also have an ErrorResource registered for it, which are consulted second
 * if the endpoint has no registered ErrorResource, or the registered
 * ErrorResource throws an exception during handling.
 * 
 * If both the endpoint and HTTPError code either have no registed
 * ErrorResource, or the registered ErrorResource throws an exception during
 * handling, then the registered GlobalErrorResource (of which
 * DefaultGlobalErrorResource is the default) is used to provide an error back
 * to the client. GlobalErrorResources must not throw errors or exceptions
 * themselves, so cannot fail.
 * 
 * Implementing a Resource
 * --------------------
 * 
 * BasicResource is a general-purpose implementation of Resource. To use a
 * BasicResource, the user registers each HTTP method supported by that Resource
 * to a RequestHandler (a callable) that takes a Request, and builds and returns
 * a Response.
 * 
 * ContentTypeSelector is useful when a BasicResource (or any other Resource
 * implementation) requires a way of switching between requested content types
 * (MIME types) to return in the Response. To use a ContentTypeSelector, the
 * user registers one or more content types to callables that take a Request,
 * and generate and return a Response in the relevant format.
 * 
 * If you wish to add HTTP headers to the response, the static utility method
 * BasicResource::addHeaders() can be used to add the given headers (usually at
 * the end of a Dispatcher::funcToPipe*() chain).
 * 
 * Between Router, BasicResource, ContentTypeSelector, and JWTAuthenticator, a
 * range of HTTP errors are handled for the user. These include:
 * - 401 (Unauthorised)
 * - 404 (Not Found)
 * - 405 (Method Not Allowed)
 * - 406 (Not Acceptable)
 * - 500 (Internal Server Error)
 * - 501 (Not Implemented)
 * 
 * Resource implementations may throw the following HTTP errors:
 * - 400 (Bad Request), if the request body or parameters are malformed
 * - 422 (Unprocessable Entity), if the request otherwise makes no contextual or
 *     semantic sense
 * - any other Resource-specific return codes ...
 */

namespace router;

require_once "panic.php";

use Exception;
use util\Util;
use dispatcher\Dispatcher;
use dispatcher\exceptions\UndispatchableError;
use router\exceptions\HTTPError;

/**
 * Allows registering endpoints (and endpoint regexes) to Resources and
 * dispatching to the appropriate Resource based on the endpoint in the Request.
 * 
 * Allows registering error handlers to endpoints (and endpoint regexes) and
 * error codes, and registering a global error handler. Each of these in turn is
 * attempted to be dispatched to. If any are not register, or throw an
 * exception, the next in the list is tried. The global error handler cannot
 * fail.
 * 
 * Also allows creating an 'aggregate resource', for which other resources are
 * passed to its constructor. This is commonly useful for documentation of APIs.
 * 
 * @author William Taylor (19009576)
 */
class Router {
    /* Q: Why does Router take a Request in its constructor? Surely a Router
     *   should be able to route multiple requests, not be bound to a single
     *   request?
     * A: That is PHP's fault. For any kind of server system there are two
     * design options:
     * 
     * 1. Having an 'initialisation' phase, where a request is not applicable,
     *    and a 'handling' phase where a request is applicable. Failure during
     *    the first phase should use a generic error handler that logs the issue
     *    and exits, in the second phase should use a request error handler that
     *    logs the issue, returns an error response to the client and exits.
     * 
     * 2. Having only a 'handling' phase, during which any error must be logged,
     *   the client responded to, and the system exited.
     * 
     * A PHP process handles one request - there is no headless initialisation
     * phase in PHP. Everywhere in a PHP process MUST be considered a request
     * phase and a response MUST be returned to the client, including if there's
     * an error.
     * 
     * The Router constructor requires the Request because:
     * 
     * 1. To provide the maximal coverage of code that will return a nice error
     *    message if there is an error, a nice error handler should be set as
     *    early as possible. In most programs, the earliest location at which
     *    all the resources are available to construct a nice error handler is
     *    as soon as the Router is created.
     * 
     * 2. All generic classes/functions for nice error handling need to have the
     *    Request information to generate a nice response, and the Router must
     *    pass that information to them.
     */

    private $request;
    private $pathfinder;
    private $errorLogger;

    private $resources;
    private $errorResources;
    private $errorCodeResources;
    private $globalErrorResource;

    /**
     * Make a new Router for the given request.
     * 
     * @param Request $request The request to build a router for.
     * @param Pathfinder $pathfinder The pathfinder to use.
     * @param Logger $logger The error logger to use.
     */
    public function __construct($request, $pathfinder, $errorLogger) {
        $this->request = $request;
        $this->pathfinder = $pathfinder;
        $this->errorLogger = $errorLogger;

        $this->resources = new Dispatcher();
        $this->errorResources = new Dispatcher();
        $this->errorCodeResources = new Dispatcher();

        $this->registerGlobalError(new DefaultGlobalErrorResource());
    }

    /* Registration and Dispatch
    -------------------------------------------------- */

    /**
     * Register a resource to be identified by an endpoint.
     * 
     * Optionally, also register an error resource for that endpoint.
     * 
     * Any number of endpoints can be handled by a single resource. However, a
     * single endpoint cannot be registered to multiple resources (or error
     * resources). Registering an endpoint to a second resource/error resource
     * will replace the first resource/error resource.
     * 
     * @param string $endpoint The endpoint to register as the identifier for
     *   the resource.
     * @param Resource $resource A resource to register to that endpoint.
     * @param ErrorResource $errorResource The ErrorResource to register to that
     *   endpoint.
     * 
     * @see Router::registerError()
     * @see Router::registerErrorCode()
     * @see Router::registerGlobalError()
     */
    public function register($endpoint, $resource, $errorResource = null) {
        $endpoint = $this->pathfinder->appPathFor($endpoint);

        $this->resources->register($endpoint, $resource);
        if ($errorResource !== null) {
            $this->registerError($endpoint, $errorResource);
        }
    }

    /**
     * Register an error resource to handle errors eminating from the resource
     * for the given endpoint.
     * 
     * It is possible to register an error resource for an endpoint that has no
     * registered resource. However, this will (obviously) be useless unless and
     * until a resource is registered for that endpoint that can emit errors to
     * handle.
     * 
     * An endpoint-specific error resource is looked up first after catching an
     * emitted error, before falling back to any registered error code-specific
     * error resource registered for the caught error.
     * 
     * Any number of endpoints can be handled by a single error resource.
     * However, a single endpoint cannot be registered to multiple error
     * resources. Registering an endpoint to a second error resource will
     * replace the first error resource.
     * 
     * This method is useful to register a different error resource for an
     * endpoint that already has a registered resource, without knowing (having
     * a reference to) the resource that endpoint is registered to.
     * 
     * @param string $endpoint The endpoint to register the error resource for.
     * @param ErrorResource $errorResource The ErrorResource to register to that
     *   endpoint.
     * 
     * @see Router::register()
     * @see Router::registerErrorCode()
     * @see Router::registerGlobalError()
     */
    public function registerError($endpoint, $errorResource) {
        $this->errorResources->register($endpoint, $errorResource);
    }

    /**
     * Register an error resource to handle the given HTTP error code.
     * 
     * These error-specific handlers are looked up after trying a registered
     * endpoint error resource, but before falling back to the registered global
     * error resource.
     * 
     * Any number of HTTP error codes can be handled by a single error resource.
     * However, a single error code cannot be registered to multiple error
     * resources. Registering an error code to a second error resource will
     * replace the first error resource.
     * 
     * @param int $code The HTTP error code to register an error resource for.
     * @param ErrorResource $errorResource The ErrorResource to register to that
     *   error code.
     * 
     * @see Router::register()
     * @see Router::registerError()
     * @see Router::registerGlobalError()
     */
    public function registerErrorCode($code, $errorResource) {
        $this->errorCodeResources->register($code, $errorResource);
    }

    /**
     * Register a resource to handle all errors not handled by any specific
     * error resource.
     * 
     * This also sets global PHP exception handler to the given handler.
     * 
     * @param GlobalErrorResource $globalErrorResource the global error resource
     *   to register for this router.
     * 
     * @see Router::register()
     * @see Router::registerError()
     * @see Router::registerErrorCode()
     */
    public function registerGlobalError($globalErrorResource) {
        $this->globalErrorResource = $globalErrorResource;

        /*
        !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            WARNING: MODIFIES GLOBAL STATE
        !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        */
        set_exception_handler(function ($exception) {
            try {
                // Do not abort the 'nice' error handling process just because
                // we can't log the error.
                $this->errorLogger->log($exception);

                $errRequest = new ErroredRequest(
                    $this->request,
                    null, // Unknown Resource, possibly no Resource
                    new HTTPError(500,
                        "Unknown internal error",
                        $exception->getMessage()
                        ."\n{$exception->getTraceAsString()}"
                    )
                );
                $resource = $this->globalErrorResource; // PHP <=5.6
                $response = $resource($errRequest);
                $response->send();

            // If the nice way of handling the error/exception is the thing that
            // raised the error/exception in the first place, then revert to the
            // basic handler.
            } catch (Exception $e) {
                basicExceptionHandler($exception);
            }
        });
    }

    /**
     * Dispatch the request to the resource registered for the requested
     * endpoint.
     * 
     * If no resource is registered for the requested endpoint, return an error.
     * Also handle all other errors caused by the resource and any called error
     * resources.
     */
    public function dispatch() {
        // Create a mutator like Dispatcher::keyMatchesRegisteredMutator(), but
        // that matches the regex-translated endpoint schemes (rather than the
        // raw schemes) against the request.
        $keyMatchesRegisteredSchemeMutator = function ($key) {
            return Util::filterValues(
                $this->resources->getKeys(),
                function ($registeredScheme) use ($key) {
                    $registeredSchemeRegex = preg_replace(
                        "|:[^/]*|", "[^/]*", $registeredScheme
                    );
                    return preg_match("|^$registeredSchemeRegex$|", $key);
                },
                false
            );
        };
        
        // Try normal dispatch
        try {
            // Get the scheme that matches the requested endpoint
            $key = $this->request->endpoint();
            $realKey = $this->resources->realKey($key, [
                $keyMatchesRegisteredSchemeMutator,
                $this->resources->firstOrDefaultMutator(null)
            ]);

            // If any scheme matched, try to set the endpoint scheme in the
            // request.
            if ($realKey !== null) {
                $success = $this->request->setEndpointScheme($realKey);
                if (!$success) {
                    throw new HTTPError(404,
                        "Resource at '{$this->request->endpoint()}' not found: "
                        ."validation against resource endpoint scheme "
                        ."'$realKey' failed"
                    );
                }
            }

            // Dispatch
            $response = $this->resources->toKey($realKey, [$this->request]);
            $response->send();
            return;

        } catch (HTTPError $e) {
            $erroredRequest = new ErroredRequest(
                $this->request,
                // Not an UndispatchableError, so a resource must have been
                // dispatched to.
                $this->resources->getValues([$realKey])[0],
                $e
            );

        } catch (UndispatchableError $e) {
            $erroredRequest = new ErroredRequest(
                $this->request,
                null, // No Resource
                new HTTPError(404,
                    "Resource at '{$this->request->endpoint()}' not found"
                )
            );
        }

        // If error, try endpoint-specific error resources
        try {
            $response = $this->errorResources->toKey(
                $endpoint, [$erroredRequest]
            );
            $response->send();
            return;
        }
        catch (Exception $e) {} // possibly UndispatchableError - not registered

        // If error, try error-specific error resources
        try {
            $response = $this->errorCodeResources->toKey(
                $code, [$erroredRequest]
            );
            $response->send();
            return;
        }
        catch (Exception $e) {} // possibly UndispatchableError - not registered

        // If they all aren't registered or otherwise error, then use the global
        // error resource to handle the error (which has a default registration
        // and isn't allowed to fail/error).
        $resource = $this->globalErrorResource; // PHP <=5.6
        $response = $resource($erroredRequest);
        $response->send();
    }

    /* Support Tools
    -------------------------------------------------- */

    /**
     * Create an aggregate resource.
     * 
     * An aggregate resource is one that is passed an [endpoint => Resource]
     * mapping of all endpoints that have the given prefix, and annother mapping
     * of all ErrorResources that could be triggered by that Resource, split by
     * type. This will either be a mapping of ["endpoint" => [ErrorResource...]]
     * or ["code" => [ErrorResource...], "global" => ErrorResource].
     * 
     * @param string $prefix The literal prefix to use to select registered
     *   Resources to pass to the aggregate Resource.
     * @param class $resourceClass The class of the Resource to instantiate.
     * @param array<mixed> $constructorArgs An array of arguments to pass to the
     *   aggregate Resource's constructor after the Resource mapping.
     * 
     * @return Resource The aggregated Resource - an instance of $resourceClass.
     */
    public function aggregateResource(
            $endpointRegex,
            $resourceClass,
            $constructorArgs = null
    ) {
        $serverEndpointRegex = $this->pathfinder->serverPathFor($endpointRegex);
        $endpointKeys = $this->resources->realKey($serverEndpointRegex, [
            $this->resources->funcKeyMutator(),
            $this->resources->registeredMatchesKeyMutator()
        ]);

        $resourceInfo = [
            "resources" => $this->resources->getMap($endpointKeys),
            "errorResources" => $this->errorResources->getMap($endpointKeys),
            "codeErrorResources" => $this->errorCodeResources,
            "globalErrorResource" => $this->globalErrorResource
        ];

        if ($constructorArgs === null) $constructorArgs = [];
        return new $resourceClass($resourceInfo, ...$constructorArgs);
    }
}
