<?php

namespace BfwMailer;

/**
 * Class that carries the mailer options
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
class MailerOptions 
{
    /**
     * @var \PHPMailer\PHPMailer\PHPMailer $default_phpmailer : Default PHPMailer object 
     * (for default sending and header options)
     */
    public $default_phpmailer;
    
    /**
     * @var integer $max_sendingAttempts : maximum sending attemps
     */
    public $max_sendingAttempts;
    
    /**
     * @var integer $sent_email_ttl : sent email time to live in days
     */
    public $sent_email_ttl;
    
    /**
     * Constructor
     * @param \BFW\Config $config
     */
    public function __construct(\BFW\Config $config) {

        $this->default_phpmailer = $config->getValue('default_phpmailer');
        $default_phpmailer = new \PHPMailer\PHPMailer\PHPMailer();
        
        // if $this->default_phpmailer has not been really initialised 
        // by the user, then we set it to null.
        if ($this->default_phpmailer == $default_phpmailer) {
            $this->default_phpmailer = null;
        }

        // Clamp max sending attempts value between 1 and 20
        $this->max_sendingAttempts = max(1, min(20, $config->getValue('max_sendingAttempts')));
        
        // Clamp TTL value between 0 and 730 days and set it in seconds
        $this->sent_email_ttl = max(0, min(730, $config->getValue('sent_email_ttl'))) * 86400;
    }
}
