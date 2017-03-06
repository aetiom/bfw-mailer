<?php

namespace BfwMailer;

/**
 * Class that manage the mailer and the sending/queueing process
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class Mailer {
    
    /**
     * @var \PHPMailer|null $email Current email being processed
     */
    protected $email = null;
    
    /**
     * @var SendingStatus|null $sendingStatus Sending status of the current email being processed
     */
    protected $sendingStatus = null;
    
    /**
     * @var QueueHandler|null $queueHandler Active queue handler
     */
    protected $queueHandler = null;
    
    /**
     * @var MailerOptions|null $options Mailer options
     */
    protected $options = null;
    
    
    
    /**
     * Get sending status for current email 
     * (usefull for retreiving error message, attempts, etc. after a failed sent)
     * 
     * @return SendingStatus $this->sendingStatus
     */
    public function get_sendingStatus()
    {
        return $this->sendingStatus;
    }
    
    
    
    /**
     * Constructor
     * 
     * @param \BFW\Config $email_config BFW Mailer Config
     */
    public function __construct(\BFW\Config $email_config) 
    {
        $this->options = new MailerOptions($email_config);
        $this->queueHandler = new QueueHandler($this->options);
        
        $this->email = new \PHPMailer();
        $this->sendingStatus = null;
    }
    
    
    
    /**
     * Queue email without sending it
     * 
     * @param \PHPMailer $email         : phpmailer email by reference
     * @param integer    $priority      : email priority (from SendingStatus)
     * @param integer    $scheduledTime : sending scheduled time 
     * 
     * @throws \Exception
     * @return boolean false on error - See the ErrorInfo property for details of the error.
     */
    public function queue_email(\PHPMailer &$email, $priority = \BfwMailer\SendingStatus::PRIO_DEFAULT, $scheduledTime = 0)
    {
        $this->initialize($email);
        
        if (is_numeric($priority)) {
            $this->sendingStatus->priority = $priority;
        }

        if (is_numeric($scheduledTime) && $scheduledTime !== 0) {
            $this->sendingStatus->state = SendingStatus::STATE_SCHEDULED;
            $this->sendingStatus->lastAction_ts = $scheduledTime;
        }

        try {
            if ($this->email->preSend()) {
                $email_id = $this->queueHandler->enqueue($this->email, $this->sendingStatus);

                if ($email_id !== false) {
                    return $email_id;
                }
            }
        } catch (\Exception $e) {
            $this->email = null;
            $this->sendingStatus = null;
            
            throw $e;
        }


        return false;
    }
    
    
    
    /**
     * Send email directly, then queue it after (see options for more details)
     * 
     * @param \PHPMailer $email   : phpmailer email by reference
     * @param boolean    $archive : if true, queue email after sending it (default = true)
     * 
     * @return boolean : true when email is sent successfully, false otherwise
     */
    public function send_email(\PHPMailer &$email, $archive = false) 
    {
        $this->initialize($email);

        $this->sendingStatus->priority = SendingStatus::PRIO_SYSTEM;
        $this->sendingStatus->state = SendingStatus::STATE_PENDING;

        $isSent = $this->send();
        
        if ($isSent) {
            $this->sendingStatus->state = SendingStatus::STATE_SUCCEEDED;
            
            if ($archive === true) {
                $this->sendingStatus->queue_id = $this->queueHandler->archive($this->email);
            }
            
            return true; 
        }
        
        return false;
    }
    
    
    
    /**
     * Process outbox and send the next email in queue if queue is not empty
     * 
     * @return mixed : true when a queued email is sent successfully, false when it's not, null if outbox is empty
     */
    public function process_queue() 
    {
        $this->initialize();
        
        $outbox_id = $this->queueHandler->dequeue($this->email, $this->sendingStatus, $this->options->max_sendingAttempts);
        $this->sendingStatus->queue_id = $outbox_id;
        
        if ($outbox_id !== false) {
            $isSent = $this->send($outbox_id);
            
            if ($isSent === true) { 
                $this->sendingStatus->queue_id = $this->queueHandler->archive($this->email, $outbox_id);
                return true; 
            } else { 
                return false;
            }
        }

        return null;
    }
    
    
    
    /**
     * Return the mailbox with metadata and content (sending queue, by default)
     * 
     * @param boolean $email_sent : if true, return email sent too (default = true)
     * @return array : Mailbox data in an array formated with modeles\Outbox $DB_* labels
     */
    public function get_mailbox($email_sent = true) 
    {
        $outbox = $this->queueHandler->fetch_emails();
        
        if ($email_sent === true) {
            $sentbox = $this->queueHandler->fetch_emails('sentbox');
            
            $f_outbox = $this->format_mailbox($outbox, 'outbox');
            $f_sentbox = $this->format_mailbox($sentbox, 'sentbox');
            
            return array_merge(array($keyMap), $f_outbox, $f_sentbox);
        }
        
        return $outbox;
    }
    
    
    
    /**
     * Initialize email and sending status by merging default config, and user config.
     * This is a requirement before sending or queuing email
     */
    private function initialize(\PHPMailer &$email_conf = null)
    {
        
        if ($email_conf !== null) {
            $this->email =  $email_conf;
            
            if ($this->options->default_phpmailer !== null) {
                $default = new \PHPMailer();
                $this->email = $this->merge_conf($email_conf, 
                        $this->options->default_phpmailer, $default);
            }
        } elseif ($this->options->default_phpmailer !== null) {
            $this->email = clone $this->options->default_phpmailer;
        } else {
            $this->email =  new \PHPMailer();
        }
        
        $this->sendingStatus = new SendingStatus();
        $this->sendingStatus->state = SendingStatus::STATE_PENDING;
    }
    
    
    
    /**
     * Send $this->email using phpmailer process
     * 
     * @param integer $outbox_id : outbox id if email comes from outbox (default = null)
     * @return boolean : true when email is sent successfully, false otherwise
     */
    private function send($outbox_id = null) 
    {
        $this->sendingStatus->lastAction_ts = time();
        $isSent = $this->email->send();
                
        if ($isSent === true) {
            $this->sendingStatus->state = SendingStatus::STATE_SUCCEEDED;
            $this->sendingStatus->lastAction_ts = time();
            
        // if we are not in send then archive behavior, update the sending status
        } elseif ($outbox_id !== null) {
            $this->sendingStatus->state = SendingStatus::STATE_FAILED;
            $this->sendingStatus->attempts += 1;
            
            if ($this->sendingStatus->attempts < $this->options->max_sendingAttempts) {
                $this->sendingStatus->lastAction_ts = time() + $this->sendingStatus->attempts * 900;
            }
        }
        
        if ($this->email->ErrorInfo !== null && $this->email->ErrorInfo !== '') {
            $this->sendingStatus->error = 'PHPMailer Error: ' . $this->email->ErrorInfo;
        }
        
        if ($outbox_id !== null) {
            $this->queueHandler->update_sendingStatus($this->sendingStatus, $outbox_id);
        }
        
        return $isSent;
    }
    
    
    
    /**
     * Merge three differents \PHPMailer objects.
     * 
     * The email_conf key values has priority over all, except if they're equal 
     * to default_conf. In that case, we set the key values to global_conf key values
     * 
     * If email_conf doesn't have a key that exist into global_conf, 
     * we add that key and we set its value to global_conf key value.
     * 
     * Keys and values are checked, kept and replaced one by one.
     * 
     * @param \PHPMailer $email_conf   : current email configuration object
     * @param \PHPMailer $global_conf  : user global configuration object
     * @param \PHPMailer $default_conf : default configuration object
     * @return \PHPMailer : the merged \PHPMailer
     */
    private function merge_conf(\PHPMailer $email_conf, \PHPMailer $global_conf, \PHPMailer $default_conf) 
    {
        $email_conf = (array)$email_conf;
        $global_conf = (array)$global_conf;
        $default_conf = (array)$default_conf;
        
        foreach ($global_conf as $key => $global_val) {
            if (!isset($email_conf[$key]) || $email_conf[$key] === $default_conf[$key]) {
                $email_conf[$key] = $global_val;
            }
        }
        return (object)$email_conf;
    }
    
    
    
    /**
     * Format mailbox data to a unique size/format (using outbox format for that)
     * 
     * @param array  $mb_data : mailbox raw data
     * @param string $mb_name : mailbox name
     * @return array : the mailbox formated
     */
    private function format_mailbox(array $mb_data, $mb_name)
    {
        $array = array();
        
        $keyMap = array ('mailbox', modeles\Outbox::DB_ID, modeles\Outbox::DB_STATE,
                        modeles\Outbox::DB_PRIORITY, modeles\Outbox::DB_LAST_ACT, 
                        modeles\Outbox::DB_FROM, modeles\Outbox::DB_REPLY, 
                        modeles\Outbox::DB_TO, modeles\Outbox::DB_CC, 
                        modeles\Outbox::DB_BCC, modeles\Outbox::DB_CONT_ID, 
                        modeles\Outbox::DB_ERROR, modeles\Outbox::DB_ATTEMPTS,
                        modeles\Content::DB_SUBJECT, modeles\Content::DB_BODY,
                        modeles\Content::DB_ALT_BODY, modeles\Content::DB_ATTACHMENTS);
        
        foreach ($mb_data as $mb) {
            // set the mailbox name as first data
            $line = array('mailbox' => $mb_name);

            foreach ($keyMap as $k) {
                foreach ($mb as $key => $val) {
                    // if the original key match the key of the keyMap, we set the data in the array
                    if ($key === $k) {
                        $line[$k] = $val;
                    }
                }
                
                // if no data has been set for the key, we set that data to NC (not concerned)
                if (!isset($line[$k])) {
                    $line[$k] = 'NC';
                }
            }
            
            // add the current line of data to the array before processing the next line
            $array[] = $line;
        }
        
        return $array;
    }
}
