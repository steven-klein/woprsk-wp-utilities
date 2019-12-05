<?php
/**
 * @package WP_Utilities
 */

namespace woprsk\WP\Debug;

class Log
{
    private $enabled;
    private $logLocation;
    private $clearOnLoad;
    private $saveQueries;
    private $includeStackTrace;
    private $logPrefix;

    private $actionLogPrefix;
    private $defaultErrorHandler;

    public function __construct(
        $enabled = false,
        $logLocation = '',
        $clearOnLoad = false,
        $saveQueries = false,
        $includeStackTrace = false,
        $logPrefix = "WP DEBUG"
    ) {
        $this->enabled = $enabled;
        $this->logLocation = $logLocation;
        $this->clearOnLoad = $clearOnLoad;
        $this->saveQueries = $saveQueries;
        $this->includeStackTrace = $includeStackTrace;
        $this->logPrefix = $logPrefix;
        $this->enable();
    }

    protected function enable()
    {
        if ($this->enable !== true) {
            return false;
        }

        $this->setActionLogPrefix();
        $this->setErrorHandler();
        $this->setLogDirectory($this->logLocation);

        if ($this->clearOnLoad === true) {
            $this->clearDebugLog();
        }


        if ($this->saveQueries === true) {
            $this->saveQueries();
        }
    }

    /**
     * output to debug log.
     * @param  string|array|object  $log something to send to the log.
     * @return void       just writes to the debug log.
     */
    public static function log()
    {
        if ($this->enabled === true) {
            $args = func_get_args();
            if (count($args) > 0) {
                foreach ($args as $arg) {
                    error_log(sprintf("%s%s: %s", $this->logPrefix, $this->actionLogPrefix, print_r($arg, true)));
                }
            }
        }
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!($errno & error_reporting())) {
            return true;
        }

        switch ($errno) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $errors = "NOTICE";
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $errors = "WARNING";
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $errors = "ERROR";
                break;
            default:
                $errors = "ERROR";
                break;
        }

        error_log(sprintf("PHP %s%s: %s in %s on line %d", $errors, $this->actionLogPrefix, $errstr, $errfile, $errline));

        if ($this->includeStackTrace) {
            error_log(sprintf("PHP STACKTRACE: %s", print_r(debug_backtrace(2), true)));
        }

        return $this->defaultErrorHandler;
    }

    private static function setErrorHandler()
    {
        $this->defaultErrorHandler = set_error_handler([$this, "errorHandler"], error_reporting());
    }

    private function setActionLogPrefix()
    {
        $this->actionLogPrefix = \wp_doing_ajax() ? " (WP_AJAX)" : (\wp_doing_cron() ? " (WP_CRON)" : "");
    }

    private function setLogDirectory($logFileLocation = "")
    {
        if (empty($logFileLocation) || !is_string($logFileLocation)) {
            return false;
        }

        if (substr_compare($logFileLocation, ".log", strlen($logFileLocation) - strlen(".log"), strlen(".log")) !== 0) {
            $logFileLocation = \trailingslashit($logFileLocation) . "debug.log";
        }

        if (!file_exists($logFileLocation)) {
            $f = @fopen($logFileLocation, 'w');
            if ($f !== false) {
                fclose($f);
            }
        }

        if (is_writeable($logFileLocation)) {
            $set = ini_set("error_log", $logFileLocation);
            return $logFileLocation;
        }
    }

    public function clearDebugLog()
    {
        if (!\wp_doing_ajax() && !\wp_doing_cron()) {
            $debugLogPath = ini_get('error_log');
            $f = @fopen($debugLogPath, "r+");
            if ($f !== false) {
                ftruncate($f, 0);
                fclose($f);
            }
        }
    }

    public static function saveQueries()
    {
        \add_action('shutdown', [$this, 'saveQueriesLog']);
    }

    public function saveQueriesLog()
    {
        global $wpdb;

        $saveQueries = array_reduce($wpdb->queries, function ($data, $query) {
            $data['count']++;
            $data['time'] += $query[1];
            return $data;
        }, ['queries' => $wpdb->queries, 'count' => 0, 'time' => 0]);

        $this->log("SAVEQUERIES => ", \apply_filters("spklein_wp_debug_savequeries", $saveQueries));
    }
}
