<?php
/**
 * @package WP_Utilities
 */

namespace woprsk\WP\Mail;

class SMTP
{
    // phpmailer vars.
    private $WP_MAIL_SMTP_HOST;
    private $WP_MAIL_SMTP_PORT;
    private $WP_MAIL_SMTP_SECURE;
    private $WP_MAIL_SMTP_AUTH;
    private $WP_MAIL_SMTP_USERNAME;
    private $WP_MAIL_SMTP_PASSWORD;

    // enable error logging
    private $WP_MAIL_LOG_ERRORS;

    public function __construct($host = '', $port = 587, $auth = false, $username = '', $password = '', $security = 'tls', $debug = false)
    {
        // setup vars.
        $this->WP_MAIL_SMTP_HOST = $host;
        $this->WP_MAIL_SMTP_PORT = $port;
        $this->WP_MAIL_SMTP_AUTH = $auth;
        $this->WP_MAIL_SMTP_USERNAME = $username;
        $this->WP_MAIL_SMTP_PASSWORD = $password;
        $this->WP_MAIL_SMTP_SECURE = $security;
        $this->WP_MAIL_LOG_ERRORS = $debug;
    }

    /**
     * add various hooks for woo subscriptions.
     * @return void  binds actions and filters.
     */
    public function setupHooks()
    {
        if (!function_exists('add_action')) {
            return false;
        }

        // log mail failures.
        if ($this->WP_MAIL_LOG_ERRORS === true) {
            \add_action('wp_mail_failed', [$this, 'logMailFail']);
        }

        // hit the wordpress phpmailer instance.
        return \add_action('phpmailer_init', [$this, 'phpmailerInit']);
    }

    /**
     * write the wperror to the log.
     * @param  object  $WP_Error wordpress error object
     * @return void
     */
    public function logMailFail($WP_Error)
    {
        $this->writeLog($WP_Error->errors['wp_mail_failed'][0]);
    }

    /**
     * write to log filters
     * @return void
     */
    protected function writeLog($log)
    {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }

    /**
     * setup a phpmailer instance for sending mail.
     * @param  object  $phpmailer phpmailer instance
     * @return object             phpmailer instance
     */
    public function phpmailerInit($phpmailer)
    {

        // Define that we are sending with SMTP
        $phpmailer->isSMTP();

        // The hostname of the mail server
        $phpmailer->Host = self::$WP_MAIL_SMTP_HOST;

        // SMTP port number - likely to be 25, 465 or 587
        $phpmailer->Port = self::$WP_MAIL_SMTP_PORT;

        // The encryption system to use - ssl (deprecated) or tls
        $phpmailer->SMTPSecure = self::$WP_MAIL_SMTP_SECURE;

        // if using auth, also set the username and password.
        if (self::$WP_MAIL_SMTP_AUTH !== false) {
            // Use SMTP authentication (true|false)
            $phpmailer->SMTPAuth = self::$WP_MAIL_SMTP_AUTH;

            // Username to use for SMTP authentication
            $phpmailer->Username = self::$WP_MAIL_SMTP_USERNAME;

            // Password to use for SMTP authentication
            $phpmailer->Password = self::$WP_MAIL_SMTP_PASSWORD;
        }
    }
}
