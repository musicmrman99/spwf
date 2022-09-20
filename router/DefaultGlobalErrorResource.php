<?php
namespace router;

use html\HTML;

/**
 * A GlobalErrorResource with only one response generator - a plain text
 * response with the relevant HTTP error code as status code and in content.
 * 
 * @author William Taylor (19009576)
 */
class DefaultGlobalErrorResource implements GlobalErrorResource {
    private static $defaultContentType = "text/plain";

    /**
     * Generate a basic error message based on the given error code that was
     * caused during processing of the given request.
     * 
     * @param ErroredRequest $request The errored request.
     * @return Response A simple error response for the given code.
     */
    public function __invoke($erroredRequest) {
        $code = $erroredRequest->expectedResponseStatusCode();
        return new Response(
            $code,
            ["Content-Type" => self::$defaultContentType],
            HTML::text("HTTP $code Error")
        );
    }

    /* Implement ErrorResource
    -------------------------------------------------- */

    /**
     * Return the default content type for errors.
     * 
     * @return string The default content type.
     */
    public function getDefaultContentType() {
        return self::$defaultContentType;
    }
}
