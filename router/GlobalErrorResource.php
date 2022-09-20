<?php
namespace router;

/**
 * A web resource for handling any kind of error.
 * 
 * These MUST NOT throw any HTTPError exceptions when handling errors.
 * 
 * @author William Taylor (19009576)
 */
interface GlobalErrorResource extends ErrorResource {}
