<?php
namespace router\exceptions;

/**
 * A 'soft' error thrown by a Resource or ErrorResource to indicate a HTTP
 * error, such as a client error (4xx) or server error (5xx).
 * 
 * These errors should be caught and handled appropriately.
 * 
 * @author William Taylor (19009576)
 */
class HTTPError extends \Exception {
    private $detailedReason;

    public function __construct($code, $reason = null, $detailedReason = null) {
        parent::__construct($reason !== null ? $reason : "Unknown", $code);

        $this->detailedReason = $detailedReason !== null ?
            $detailedReason :
            "Not provided";
    }

    /**
     * Return the user-readable reason the resource provided for this HTTP
     * error.
     * 
     * @return string The reason for this HTTP error.
     */
    public function getReason() {
        return $this->getMessage();
    }

    /**
     * Return the developer-readable reason the resource provided for this HTTP
     * error.
     * 
     * @return string The reason for this HTTP error usable by a developer.
     */
    public function getDetailedReason() {
        return $this->detailedReason;
    }
}
