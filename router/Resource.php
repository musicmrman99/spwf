<?php
namespace router;

/**
 * A top-level web resource.
 * 
 * This is a RequestHandler, so can be called to handle a request. Resources
 * usually dispatch the given request to the relevent HTTP method-specific
 * handler.
 * 
 * @author William Taylor (19009576)
 */
interface Resource extends RequestHandler {
    /**
     * Return the default content type (MIME type) for this resource, or null to
     * have no default type.
     * 
     * The returned type must be used used if the client doesn't request any
     * specific type(s).
     * 
     * @return string The default content type to use, or null to have no
     *   default type.
     */
    public function getDefaultContentType();
}
