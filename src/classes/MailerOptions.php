<?php
/**
 * Classes permettant de gérer l'envoi d'email
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

namespace BfwMailer;


/**
 * Class that carries the mailer options
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class MailerOptions 
{
    /**
     * @var \PHPMailer $default_phpmailer_obj : default PHPMailer object 
     * (for default sending and header options)
     */
    public $default_phpmailer_obj;
    
    /**
     * @var integer $maxSendingAttempts : maximum sending attemps
     */
    public $maxSendingAttempts;
    
    /**
     * @var integer $refresh_interval : refresh interval in minutes
     */
    public $refresh_interval;
    
    /**
     * @var integer $sent_email_ttl : sent email time to live in days
     */
    public $sent_email_ttl;
    
    /**
     * Constructor
     * @param \BFW\Config $config
     */
    public function __construct(\BFW\Config $config) {

        $this->default_phpmailer_obj = $config->getConfig('default_phpmailer_obj');
        $default_phpmailer = new \PHPMailer();
        
        // if $this->default_phpmailer_obj has not been really initialised 
        // by the user, then we set it to null.
        if ($this->default_phpmailer_obj == $default_phpmailer) {
            $this->default_phpmailer_obj = null;
        }

        $this->maxSendingAttempts = $config->getConfig('maxSendingAttempts');
        $this->refresh_interval = $config->getConfig('refresh_interval');
        $this->sent_email_ttl = $config->getConfig('sent_email_ttl');
    }
}
