<?php
/**
 * Configuration file for bfw-advanced-log module
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

// Default PHPMailer for creating/sending emails
$default_phpmailer_obj = new PHPMailer();

return (object)[
    /**
     * @var PHPMailer default_phpmailer_obj : Default PHPMailer object 
     * (for default sending and header options).
     */
    'default_phpmailer_obj' => $default_phpmailer_obj,
    
    /**
     * @var integer maxSendingAttempts : Maximum sending attemps.
     */
    'maxSendingAttempts' => 9,    
    
    /**
     * @var integer refresh_interval : Refresh interval in minutes.
     */
    'refresh_interval' => 15,
    
    /**
     * @var integer sent_email_ttl : Sent email time to live in days. 
     * After that TTL, a sent email can be trashed/flushed.
     */
    'sent_email_ttl' => 390
];