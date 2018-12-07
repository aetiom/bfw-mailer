<?php

namespace BfwMailer;

/**
 * Class that handles the mail queue
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
class QueueHandler {
    
    /**
     * @var integer $sent_ttl : email sent time to live
     */
    protected $sent_ttl;
    
    /**
     * @var Content $db_content : modele for email content data
     */
    protected $db_content;
    
    /**
     * @var Outbox $db_outbox : modele for outbox data (and email metadata)
     */
    protected $db_outbox;
    
    /**
     * @var Sentbox $db_sentbox : modele for sentbox data (and email metadata)
     */
    protected $db_sentbox;
    
    
    
    /**
     * Constructor
     * 
     * @param \BfwMailer\MailerOptions $options
     */
    public function __construct(MailerOptions $options) 
    {
        // Create our data instances
        $this->db_content = new \BfwMailer\modeles\Content();
        $this->db_outbox = new \BfwMailer\modeles\Outbox();
        $this->db_sentbox = new \BfwMailer\modeles\Sentbox();
        
        // Get sent email TTL value
        $this->sent_ttl = $options->sent_email_ttl;
    }
    
    
    
    /**
     * Enqueue email
     * 
     * @param \PHPMailer\PHPMailer\PHPMailer $email         : (as REF) PHPMailer object 
     *                                                        containing email informations 
     *                                                        (header, content, etc.)
     * @param \BfwMailer\SendingStatus       $sendingStatus : (as REF) Sending status
     * @return mixed : outbox id in case of success, false otherwise
     */
    public function enqueue (\PHPMailer\PHPMailer\PHPMailer &$email, SendingStatus &$sendingStatus) 
    {   
        // processing email content and retreiving content database id
        $cont_id = $this->process_content($email);
        
        // if content processing going well, add email metadata into outbox and
        // update the sending status of the whole email
        if ($cont_id !== false) {

            $out_id = $this->db_outbox->add( 
                $email->From.','.$email->FromName.';', 
                $this->serial_emailAttr($email->getReplyToAddresses()), 
                $this->serial_emailAttr($email->getToAddresses()), 
                $this->serial_emailAttr($email->getCcAddresses()), 
                $this->serial_emailAttr($email->getBccAddresses()), 
                $cont_id, $sendingStatus->priority);

            if ($out_id !== false) {
                $this->update_sendingStatus($sendingStatus, $out_id, $cont_id);
                return $out_id;
            }
        }
        
        return false;
    }
    
    
    /**
     * Dequeue next pending email and modify params objects content passed as references 
     * with next pending email information (header, content, etc.)
     * 
     * @param \PHPMailer\PHPMailer\PHPMailer $email               : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param \BfwMailer\SendingStatus       $sendingStatus       : (as REF) Sending status
     * @param integer                        $max_sendingAttempts : max sending attemps tolerated
     * @return boolean : true in case of success, false otherwise
     */
    public function dequeue (\PHPMailer\PHPMailer\PHPMailer &$email, SendingStatus &$sendingStatus, $max_sendingAttempts) 
    {
        // refreshing our scheduled elements before going further
        $this->db_outbox->refresh_scheduled(time());
        
        // get the next email to send from the queue
        $out_id = $this->db_outbox->get_nextPending($max_sendingAttempts);
        
        if ($out_id !== false) {
            
            // retrieving outbox and content data
            $outbox = $this->db_outbox->retrieve($out_id);
            $content = $this->db_content->retrieve($outbox[modeles\Outbox::DB_CONT_ID]);

            // making email sending status
            $sendingStatus->priority      = $outbox[modeles\Outbox::DB_PRIORITY];
            $sendingStatus->state         = $outbox[modeles\Outbox::DB_STATE];
            $sendingStatus->error         = $outbox[modeles\Outbox::DB_ERROR];
            $sendingStatus->attempts      = $outbox[modeles\Outbox::DB_ATTEMPTS];
            $sendingStatus->lastAction_ts = $outbox[modeles\Outbox::DB_LAST_ACT];

            // making email metadata
            $this->add_emailAttr($email, $outbox[modeles\Outbox::DB_FROM], 'From');
            $this->add_emailAttr($email, $outbox[modeles\Outbox::DB_REPLY], 'ReplyTo');
            $this->add_emailAttr($email, $outbox[modeles\Outbox::DB_TO], 'Address');
            $this->add_emailAttr($email, $outbox[modeles\Outbox::DB_CC], 'CC');
            $this->add_emailAttr($email, $outbox[modeles\Outbox::DB_BCC], 'BCC');

            // constructing email content
            if ($content !== false) {
                $email->Subject = $content[modeles\Content::DB_SUBJECT];
                $email->Body    = $content[modeles\Content::DB_BODY];
                $email->AltBody = $content[modeles\Content::DB_ALT_BODY];
                
                // if we got an alternative body or our body and out alt. body aren't the same, we force the use of HTML
                if ($email->AltBody !== '' || $email->Body !== '' && $email->Body !== $email->AltBody) {
                    $email->Body = html_entity_decode($email->Body);
                    $email->isHTML();
                }

                $this->add_emailAttr($email, $content[modeles\Content::DB_ATTACHMENTS], 'Attachment');
                return $out_id;
                
            } else {
                $sendingStatus->lastAction_ts = time();
                $sendingStatus->error = 'Content (id='.$outbox[modeles\Outbox::DB_CONT_ID].') not found';
                $sendingStatus->state = SendingStatus::STATE_FAILED;

                $this->update_sendingStatus($sendingStatus, $out_id, $outbox[modeles\Outbox::DB_CONT_ID]);
            }

        }
        
        // flushing old sent emails and old failed emails
        $this->clean_db(time() - $this->sent_ttl);
        return false;
    }
    
    
    
    
    /**
     * Archive an email into sentbox
     * 
     * @param \PHPMailer\PHPMailer\PHPMailer $email     : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param integer                        $outbox_id : outbox id if the email comes from the outbox (default : null)
     * @return integer|boolean : return sentbox in case of success, false if it fails
     */
    public function archive (\PHPMailer\PHPMailer\PHPMailer &$email, $outbox_id = null) 
    {   
        // processing email content and retreiving content database id
        $cont_id = $this->process_content($email);

        // if content processing going well, add email metadata into sentbox,
        // update the last action timestamp of the whole email 
        // and remove the email metadata from the outbox
        if ($cont_id !== false) {

            $send_id = $this->db_sentbox->add( 
                $email->From.','.$email->FromName.';', 
                $this->serial_emailAttr($email->getReplyToAddresses()), 
                $this->serial_emailAttr($email->getToAddresses()), 
                $this->serial_emailAttr($email->getCcAddresses()), 
                $this->serial_emailAttr($email->getBccAddresses()), 
                $cont_id);

            if ($send_id !== false) { 
                
                $this->db_sentbox->update_lastAction($send_id);
                $this->db_content->update_lastAction($cont_id);
                
                if ($outbox_id !== null) {
                    $this->db_outbox->remove($outbox_id);
                }
                
                return $send_id;
            }
        }
        
        return false;
    }
    
    
    
    /**
     * Update the sending status of an email into the database
     * 
     * @param \BfwMailer\SendingStatus $sendingStatus : sending status to push into the database
     * @param integer                  $outbox_id     : outbox id
     * @param integer                  $content_id    : content id (default : null)
     */
    public function update_sendingStatus(SendingStatus $sendingStatus, $outbox_id, $content_id = null)
    {
        if ($content_id === null) {
            $outbox = $this->db_outbox->retrieve($outbox_id);
            $content_id = $outbox[modeles\Outbox::DB_CONT_ID];
        }
        
        $this->db_outbox->update_status($outbox_id, $sendingStatus->state, 
                $sendingStatus->error, $sendingStatus->attempts);  

        $this->db_outbox->update_lastAction($outbox_id, $sendingStatus->lastAction_ts);
        $this->db_content->update_lastAction($content_id, $sendingStatus->lastAction_ts);
    }
    
    
    
    /**
     * Return all email (in queue and sent) with content
     * 
     * @param string $source : source of the fetch, can only take value of 'outbox' or 'sentbox'
     * @return array : fetched data
     */
    public function fetch_emails($source='outbox')
    {
        if ($source === 'outbox') {
            return $this->db_outbox->retrieve_all();
        } elseif ($source === 'sentbox') {
            return $this->db_sentbox->retrieve_all();
        }
        
        return array();
    }
    
    
    
    /**
     * Processing content of an email by searching if and returning it's identifier 
     * or by creating it if it doesn't exist, and returning it's identifier
     * 
     * @param \PHPMailer\PHPMailer\PHPMailer $email : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @return integer : content id of the processed content
     */
    private function process_content(\PHPMailer\PHPMailer\PHPMailer &$email) {
        // Convert array attachments into a string
        $attachments = $this->serial_emailAttr($email->getAttachments());
        
        // Nous vérifions que le contenu de l'email à envoyé n'existe pas déjà en base de donnée
        $cont_id = $this->db_content->search($email->Subject, $email->Body, $email->AltBody, $attachments);
        
        // Si le contenu de l'email n'existe pas déjà, nous le créons
        if ($cont_id === false) {
            $cont_id = $this->db_content->add($email->Subject, $email->Body, $email->AltBody, $attachments);
        }
        
        return $cont_id;
    }
    

    
    /**
     * Flush sent and failed emails from mailboxes and content database
     * 
     * @param integer $timestamp : timestamp limit
     * @return boolean : false if no flush have been done, true otherwise
     */
    private function clean_db($timestamp)
    {
        // check if sent TLL and timestamp is set and higher than 0
        if ($this->sent_ttl <= 0 || $timestamp === null || $timestamp <= 0) {
            return false;
        }
        
        $flush_sent = $this->db_sentbox->flush($timestamp);
        $flush_fail = $this->db_outbox->flush($timestamp);
        $flush_cont = $this->db_content->flush($timestamp);
        
        if ($flush_sent === false && $flush_fail === false && $flush_cont === false) {
            return false;
        }
        
        return true;
    }
    
    
    
    /**
     * Format and add an attribute that coming from the database to an email
     * 
     * @param \PHPMailer\PHPMailer\PHPMailer $email   : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param string                         $db_data : line of data coming from the database
     * @param string                         $attr    : attribut to add to the email, can take those values : From, Address, ReplyTo, CC, BCC, Attachment
     * @return boolean : return true in case of success, false otherwise
     */
    private function add_emailAttr(\PHPMailer\PHPMailer\PHPMailer &$email, $db_data, $attr = 'Address')
    {
        $method = 'add';
        
        if (empty($db_data) && $attr !== 'From' && $attr !== 'Address' && $attr !== 'ReplyTo'
                && $attr !== 'CC' && $attr !== 'BCC' && $attr !== 'Attachment') {
            return false;
        }
        
        if ($attr === 'From') {
           $method = 'set' ;
        }

        // we explode our db data in lines using ';' as a separator
        $array_data = explode(';', (string)$db_data);
        
        foreach ($array_data as $line) {
            if (strpos($line, ',') !== false) {
                $a = explode(',', $line);
            } else {
                $a = array($line, '');
            }
            
            try {
                $email->{$method.$attr}($a[0], $a[1]);
            } catch (\Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
                return false; 
            }
        }
        
        return true;
    }

    
    
    /**
     * Serialize email attribute by transforming two dimensional array into a single string
     * Separate first level array values by ";" and second by ","
     * 
     * @param array $array
     * @return string
     */
    private function serial_emailAttr(array $array) 
    {
        $inline = '';
        
        foreach ($array as $val) {
            if (is_array($val)) {
                $inline .= implode(',', $val).';';
            } else {
                $inline .= $val.';';
            }
        }
        
        return $inline;
    }
}