<?php
namespace router\resource;

use dispatcher\Dispatcher;
use dispatcher\exceptions\UndispatchableError;
use router\RequestHandler;
use router\exceptions\HTTPError;

/**
 * A ContentTypeSelector is a handler for requests that dispatches to one of a
 * list of registered content generators, based on the 'Accept' request header.
 * 
 * @author William Taylor (19009576)
 */
class ContentTypeSelector implements RequestHandler {
    private $dispatch;
    private $defaultContentType;

    /**
     * Create a ContentTypeSelector with the given initial generator map.
     * 
     * @param array<string,RequestHandler> $generatorMap An association between
     *   MIME types and generators that create a Response in that format.
     * @param string $defaultContentType The default content type to use. No
     *   default if not given (will throw a HTTP 406 (Not Acceptable) error if
     *   no acceptable content type is found from the 'Accept' header, or if
     *   no 'Accept' header was provided).
     */
    public function __construct(
            $generatorMap = null,
            $defaultContentType = null
    ) {
        $this->dispatch = new Dispatcher($generatorMap, $defaultContentType);
        $this->defaultContentType = $defaultContentType;
    }

    /**
     * Register a generator for a particular MIME type for this resource.
     * 
     * @param string $method The MIME type to register a generator for.
     * @param RequestHandler $handler The generator to register for that MIME
     *   type.
     */
    public function register($contentType, $generator) {
        $this->dispatch->register($contentType, $generator);
    }

    /**
     * Dispatch to the relevant generator.
     * 
     * @param Request $request The HTTP request to handle.
     * @param mixed $args Any other data to pass down to the handlers.
     * 
     * @return Response The response to return to the client.
     * 
     * @throws HTTPError 406 (Not Acceptable) if there is no generator
     *   registered for any of the requested MIME types.
     */
    public function __invoke($request, ...$args) {
        // Generate content for the MIME type, passing down the request
        try {
            $acceptedContentTypes = $request->acceptedContentTypes();
            return $this->dispatch->toFirst(
                $acceptedContentTypes,
                array_merge([$request], $args)
            );

        } catch (UndispatchableError $e) {
            $contentTypeList = implode(", ", $acceptedContentTypes);

            if (count($acceptedContentTypes) === 0) {
                $message = "No content type(s) requested";
                $message .= $this->defaultContentType !== null ?
                    ", and the default of '{$default}' was not found" :
                    ", and the resource has no default content type";
            } else {
                $message = (
                    "None of the content types in '{$contentTypeList}' are"
                    ." supported by this resource"
                );
            }

            throw new HTTPError(406, $message);
        }
    }

    /**
     * Return the supported content (MIME) types.
     * 
     * @return array<string> The supported content types.
     */
    public function getSupportedTypes() {
        return $this->dispatch->getKeys();
    }
}
