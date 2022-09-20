<?php
namespace router;

/**
 * An object that can be called to handle a Request, returning a Response.
 * 
 * @author William Taylor (19009576)
 */
interface RequestHandler {
    /**
     * A handler for one or more request 'slots'.
     * 
     * A request 'slot' here is a set of conditions on the request, eg. for a
     * particular endpoint, method, and content type.
     * 
     * Request handlers sometimes dispatch the given request to one of another
     * set of RequestHandlers.
     * 
     * @param Request $request The HTTP request to handle.
     * @return Response The response to return to the client.
     */
    public function __invoke($request);
}
