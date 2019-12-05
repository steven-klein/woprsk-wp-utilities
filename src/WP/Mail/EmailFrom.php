<?php
/**
 * @package WP_Utilities
 */

namespace woprsk\WP\Mail;

class EmailFrom
{
    // reply_to that can be set programatically
    private $emailFrom = '';

    // the from name used in emails sent out.
    private $fromName = '';

    // email home url.  when set, urls in the email message that match the current home_url are replaced with this.
    private $emailHomeUrl = '';

    // setup the domain for this site.
    private $domain = '';

    /**
     * add email from filters during construct. apply them late.
     */
    public function __construct($emailFrom = '', $fromName = '', $emailHomeUrl = '')
    {
        $this->emailFrom = $emailFrom;
        $this->fromName = $fromName;
        $this->emailHomeUrl = $emailHomeUrl;
    }

    public function setupHooks()
    {
        if (!empty($this->emailFrom) && filter_var($this->emailFrom, FILTER_VALIDATE_EMAIL)) {
            \add_filter('wp_mail_from', [$this, 'wpMailFrom'], 999);
            \add_filter('wp_mail_from_name', [$this, 'wpMailFromName'], 999);
            \add_filter('wp_mail', [$this, 'setServerVar'], 999);
        }

        if (!empty($this->emailHomeUrl) && filter_var($this->emailHomeUrl, FILTER_VALIDATE_URL)) {
            \add_filter('wp_mail', [$this, 'wpMailBody'], 999);
        }

        // setup are commonly used variables.
        \add_action('wp_loaded', [$this, 'setupVars']);
    }

    /**
     * determine the root domain of the site we are working on and the from name.
     *
     * @return void
     */
    public function setupVars()
    {
        $this->domain = (\is_multisite()) ? \get_current_site()->domain : preg_replace('/^www\./', '', parse_url(\get_bloginfo('url'), PHP_URL_HOST));
        $this->fromName = (empty($this->fromName)) ? (\is_multisite()) ? \get_current_site()->site_name : \get_bloginfo('name') : $this->fromName;
    }

    /**
     * Set a no-reply from email address... sometimes wp leaves it empty.
     * @hooked wp_mail_from
     * @param  string $email original from email address passed by apply_filters('wp_mail_from')
     * @return string        replacement from email derived from site domain and no-reply.
     */
    public function wpMailFrom($email)
    {
        return $this->emailFrom;
    }

    /**
     * Set a from name based on the site name.
     * @hooked wp_mail_from_name
     * @param  string $name from name passed by apply_filter('wp_mail_from_name').
     * @return string       replacement from name derived from the site name.
     */
    public function wpMailFromName($name)
    {
        return $this->fromName;
    }

    /**
     * filter out references to wp_home in the email message
     * @hooked wp_mail
     * @param  array $atts email attributes - 'to', 'subject', 'message', 'headers', 'attachments'
     * @return array $atts
     */
    public function wpMailBody($atts)
    {
        if (!empty($this->emailHomeUrl) && isset($atts['message']) && is_string($atts['message'])) {
            // replace any uses of the home url with $emailHomeUrl
            $atts['message'] = str_replace(\home_url(), $this->emailHomeUrl, $atts['message']);
        }

        return $atts;
    }

    /**
     * WordPress insists on using the $_SERVER['SERVER_NAME'] variable for establishing the from email.
     * This is problematic since server name isn't set in environments like wp-cli.
     * Nastily, we will hit the wp_mail filter to update the $_SERVER var if necessary.
     * And provide an appropriate action to remove our changes as soon as we know we've cleared the error.
     * @hooked wp_mail
     *
     * @param array $atts
     * @return array $atts
     */
    public function setServerVar($atts)
    {
        global $_SERVER;

        if (!isset($_SERVER['SERVER_NAME'])) {
            // set the server var to our domain.
            // doesn't matter what it is, we'll unset it as soon as possible, and override the from email anyway, which is what wordpress uses it for.
            $_SERVER['SERVER_NAME'] = $this->domain;

            // hit the next available action/filter to reset our manipulation of the server var.
            \add_filter('wp_mail_from', [$this, 'unsetServerVar'], 1);
        }

        // return the attributes untouched.
        return $atts;
    }

    /**
     * undoes the changes made by $this->setServerVar().
     * wp_mail can be called at any given point in execution, it would be unwise to leave a globally modified variable when the rest of the application my also use the server_name in some way.
     * Nastily, we are hooking a filter like an action to cause a major side effect as no other actions are available.
     * @hooked wp_mail_from
     *
     * @param string $from_email
     * @return string $from_email
     */
    public function unsetServerVar($from_email)
    {
        // reset the $_SERVER var
        global $_SERVER;
        if (isset($_SERVER['SERVER_NAME'])) {
            unset($_SERVER['SERVER_NAME']);
        }

        // return $from_email untouched.
        return $from_email;
    }
}
