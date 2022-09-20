<?php
namespace router\exceptions;

/**
 * An error thrown when metadata is requested from a Resource for a HTTP method
 * that Resource does not support.
 * 
 * @author William Taylor (19009576)
 */
class UnsupportedMethod extends \Exception {}
