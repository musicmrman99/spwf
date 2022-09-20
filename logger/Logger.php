<?php
/**
 * A collection of simple loggers.
 * 
 * Currently implemented:
 * - FileLogger - Logs to a file.
 */

namespace logger;

/**
 * Basic logger interface.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
interface Logger {
    public function log($message);
}
