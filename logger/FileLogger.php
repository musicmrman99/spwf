<?php
namespace logger;

/**
 * Basic logger implementation that uses a flat file. Opens and closes the file
 * on every log - be warned!
 * 
 * Will create the parent directory(ies) for the specified logfile path if
 * needed.
 * 
 * @author  William Taylor (19009576)
 * @license MIT
 */
class FileLogger implements Logger {
    private $logfile;

    /**
     * Initialise the file logger.
     * 
     * @param string $logfile An absolute path to the logfile to use.
     */
    public function __construct($logfile) {
        $this->logfile = $logfile;
    }

    /**
     * Append a message to the log file.
     * 
     * @param string $message The message to log.
     */
    public function log($message) {
        // Strip line breaks (these are the delimination character) and form the
        // final output message.
        $message = str_replace(["\r\n", "\n", "\r"], '', $message);
        $message = implode('|', [date('Y-m-d,G:i:s'), $message]) . PHP_EOL;

        /* See:
         * - https://www.php.net/manual/en/function.dirname.php
         * - https://www.php.net/manual/en/function.file-exists.php
         * - https://www.php.net/manual/en/function.mkdir.php
         * - https://www.php.net/manual/en/function.fopen.php
         * - https://www.php.net/manual/en/function.fwrite.php
         * - https://www.php.net/manual/en/function.fclose.php
         */

        // Try opening, writing to, and closing the file, avoiding continuing
        // to try if an error occurs.
        $success = true;

        $parentDirectory = dirname($this->logfile);
        if (!file_exists($parentDirectory)) {
            $ret = mkdir($parentDirectory, 0755, true);
            if (!$ret) $success = false;
        }

        if ($success) {
            // Emits E_WARNING on failure
            $fileHandle = fopen($this->logfile, "ab");
            if (!$fileHandle) $success = false;
        }

        if ($success) {
            $ret = fwrite($fileHandle, $message);
            if ($ret === false) $success = false;
        }

        // Close the file if needed, even if error
        if ($fileHandle) {
            $ret = fclose($fileHandle);
            if (!$ret) $success = false;
        }

        // If error, return false
        return $success;
    }
}
