<?php
namespace router;

/**
 * A request whose handler emitted a HTTP error during processing.
 * 
 * @author William Taylor (19009576)
 */
class ErroredRequest extends Request {
    private $error;
    private $resource;

    /**
     * Create an object that represents a failed request.
     * 
     * @param Request $request The request whose processing caused an error.
     * @param Resource|ErrorResource $sourceResource The resource that
     *   originally attempted to process the request, or null if the error
     *   occured before a resource began processing the request, the error was
     *   caused by the lack of a resource to process the request, or the
     *   resource that originally attempted to process the request is otherwise
     *   unknown.
     * @param HTTPError $error The HTTP error thrown while processing the
     *   request.
     */
    public function __construct($request, $sourceResource, $error) {
        // Ugh - have to type these out (and keep them up-to-date) ...
        parent::__construct(
            $request->method(),
            $request->endpoint(),

            $request->params(),
            $request->privateParams(),
            $request->body(),
            $request->fragment(),

            $request->headers()
        );

        $this->sourceResource = $sourceResource;
        $this->error = $error;
    }

    /**
     * Return the HTTPError that caused this request to fail.
     * 
     * @return int The HTTPError that caused this request to fail.
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Return the resource object that caused the error, or null if none was
     * given.
     * 
     * @see __construct() for the reasons why one might not be given.
     */
    public function getSourceResource() {
        return $this->sourceResource;
    }

    /**
     * Return the expected response status code for this request, ie. the status
     * code of the HTTPError the request errored with.
     */
    public function expectedResponseStatusCode() {
        return $this->error->getCode();
    }
}
