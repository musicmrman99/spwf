<?php
namespace router\exceptions;

/**
 * An error thrown when a URI is required to point to an existing location, but
 * does not.
 * 
 * @author William Taylor (19009576)
 */
class URIError extends \Exception {
    public function __construct($message) {
        parent::__construct($message);
    }
}
