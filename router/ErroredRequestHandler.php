<?php
namespace router;

/**
 * An object that can be called to handle an ErroredRequest, returning a
 * Response.
 * 
 * @author William Taylor (19009576)
 */
interface ErroredRequestHandler extends RequestHandler {
    /**
     * A handler for errored requests (those where an error was encountered
     * during processing) stemming from one or more types of error.
     * 
     * ErroredRequestHandlers usually dispatch the given errored request to the
     * relevent error response generator based on the error code and possibly
     * parts of the original request.
     * 
     * @param ErroredRequest $request The errored request.
     * @return Response The error response to return to the client.
     */
    public function __invoke($erroredRequest);
}
