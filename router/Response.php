<?php
namespace router;

use util\Util;
use format\TopFormattable;

/**
 * A possible response from the server.
 * 
 * Includes response headers and body.
 * 
 * @author William Taylor (19009576)
 */
class Response {
    private $status;
    private $headers;
    private $body;

    /**
     * Create a possible response.
     * 
     * @param int $status The HTTP status code for the response.
     * @param array<string,string> $headers A list of header names and values to
     *   include.
     * @param TopFormattable $body A TopFormattable object whose formatted
     *   representation will be used as the response body.
     * 
     * @see addHeader() to add multiple headers with the same name.
     */
    public function __construct($status, $headers, $body) {
        $this->status = $status;
        $this->headers = Util::mapKeysValues(
            $headers,
            function ($key, $value) {
                return "$key: $value";
            }
        );
        $this->body = $body;
    }

    /**
     * Add a header with the given name and value to the list.
     * 
     * This will append a new header, even if another header with that name has
     * already been added. It is the caller's responsibility to ensure
     * uniqueness if they require it.
     * 
     * @param string $name The name of the header.
     * @param string $value The value of the header.
     */
    public function addHeader($name, $value) {
        $this->headers[] = "$name: $value";
    }

    /**
     * Return the status of this response.
     * 
     * @return int The status of this response.
     */
    public function status() {
        return $this->status;
    }

    /**
     * Return the headers of this response.
     * 
     * This will be returned as an array of "Name: Value" strings. This is to
     * allow multiple values for each header name, as is required by some
     * headers, eg. `Set-Cookie:`. 
     * 
     * @return array<string> The headers of this response.
     */
    public function headers() {
        return $this->headers;
    }

    /**
     * Return the body of this response.
     * 
     * @return string The body of this response.
     */
    public function body() {
        return $this->body;
    }

    /**
     * Send the response.
     * 
     * This has major side-effects on the PHP instance and cannot be undone, so
     * this will be almost the last thing you should do in a script.
     */
    public function send() {
        http_response_code($this->status);
        foreach ($this->headers as $header) {
            header($header);
        }
        echo $this->body;
    }
}
