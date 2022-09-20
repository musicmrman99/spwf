<?php
namespace router;

/**
 * A top-level web resource that can be called to handle one or more types of
 * error encountered while processing a request.
 * 
 * @author William Taylor (19009576)
 */
interface ErrorResource extends ErroredRequestHandler {
    /**
     * Return the default content type (MIME type) for this error resource.
     * 
     * The returned type must be used used if the client doesn't request any
     * specific type(s).
     * 
     * @return string The default content type to use.
     */
    public function getDefaultContentType();
}
