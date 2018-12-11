<?php
/**
 * Configuration file for bfw-mailer module
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */

// Default \PHPMailer\PHPMailer\PHPMailer for creating/sending emails
$default_phpmailer = new \PHPMailer\PHPMailer\PHPMailer();

return (object)[
    /**
     * @var \PHPMailer\PHPMailer\PHPMailer default_phpmailer : Default PHPMailer object 
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
    'sent_email_ttl' => 390,
    
    /**
     * @var boolean secure_data : Secure raw data before inserting it into database
     * (doing a pdo quote on all external stored datas)
     */
    'secure_data' => true
];