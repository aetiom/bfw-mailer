<?php
/**
 * Configuration file for bfw-advanced-log module
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

// Default PHPMailer for creating/sending emails
$default_phpmailer = new PHPMailer();

return (object)[
    /**
     * @var PHPMailer default_phpmailer : Default PHPMailer object 
     * (for default sending and header options).
     */
    'default_phpmailer' => $default_phpmailer,
    
    /**
     * @var integer max_sendingAttempts : Maximum sending attemps.
     */
    'max_sendingAttempts' => 9,    
    
    /**
     * @var integer sent_email_ttl : Sent email time to live in days. 
     * After that TTL, a sent email can be trashed/flushed.
     */
    'sent_email_ttl' => 390
];