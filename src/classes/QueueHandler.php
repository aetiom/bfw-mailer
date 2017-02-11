<?php

namespace BfwMailer;

/**
 * Class that handles the mail queue
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class QueueHandler {
    
    /**
     * @var integer $refresh_int : refresh interval
     */
    protected $refresh_int;
    
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
     * @var System $db_system : modele for bfw-mailer system data
     */
    protected $db_system;
    
    
    /**
     * Constructor
     * 
     * @param \BfwMailer\MailerOptions $options
     */
    public function __construct(MailerOptions $options) 
    {
        // Create our data instances
        $this->db_system = new \BfwMailer\modeles\System();
        $this->db_content = new \BfwMailer\modeles\Content();
        $this->db_outbox = new \BfwMailer\modeles\Outbox();
        $this->db_sentbox = new \BfwMailer\modeles\Sendbox();
        
        // Clamp interval values [max($minVal, min($maxVal, $val));]
        $refresh_int_raw = max(1, min(60, $options->refresh_interval));
        $sent_ttl_raw = max(0, min(730, $options->sent_email_ttl));
        
        // Set deltas in seconds from clamped interval
        $this->refresh_int = $refresh_int_raw * 60;
        $this->sent_ttl = $sent_ttl_raw * 86400;
    }
    
    
    /**
     * Enqueue email
     * 
     * @param \PHPMailer                $email         : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param \BfwMailer\SendindStatus  $sendingStatus : (as REF) Sending status
     * @return mixed : outbox id in case of success, false otherwise
     */
    public function enqueue (\PHPMailer &$email, SendindStatus &$sendingStatus) 
    {   
        // Nous traitons notre contenu
        $cont_id = $this->process_content($email);

        // Si nous avons un id, nous construisons le données de l'outbox et nous 
        // les poussons dans la table prévue à cet effet
        if ($cont_id !== false) {

            $out_id = $this->db_outbox->add( 
                $email->From.','.$email->FromName.';', 
                $this->getInline($email->getReplyToAddresses()), 
                $this->getInline($email->getToAddresses()), 
                $this->getInline($email->getCcAddresses()), 
                $this->getInline($email->getBccAddresses()), 
                $cont_id, $sendingStatus->priority);

            if ($out_id !== false) {
                $this->updateSendingStatus($sendingStatus, $out_id, $cont_id);
                return $out_id;
            }
        }
        
        return false;
    }
    
    
    /**
     * Dequeue next pending email and modify params objects content passed as references 
     * with next pending email information (header, content, etc.)
     * 
     * @param \PHPMailer                $email              : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param \BfwMailer\SendindStatus  $sendingStatus      : (as REF) Sending status
     * @param int                       $maxSendingAttempts : max sending attemps tolerated
     * @return boolean : true in case of success, false otherwise
     */
    public function dequeue (\PHPMailer &$email, SendindStatus &$sendingStatus, $maxSendingAttempts) 
    {
        // we refresh our scheduled elements before going further
        if ($this->db_system->getLastRefresh() + $this->refresh_int < time()) {
            $this->db_outbox->refreshScheduled(time() + round(0.2 * $this->refresh_int));           
            $this->db_system->updateLastRefresh();
        }
        
        // get the next email to send from the queue
        $out_id = $this->db_outbox->getNextPending($maxSendingAttempts);
        
        if ($out_id !== false) {
            
            $outbox = $this->db_outbox->retrieve($out_id);
            $content = $this->db_content->retrieve($outbox[modeles\Outbox::DB_CONT_ID]);

            // Nous construisons notre contenu pour injection dans la base
            $sendingStatus->priority      = $outbox[modeles\Outbox::DB_PRIORITY];
            $sendingStatus->state         = $outbox[modeles\Outbox::DB_STATE];
            $sendingStatus->error         = $outbox[modeles\Outbox::DB_ERROR];
            $sendingStatus->attempts      = $outbox[modeles\Outbox::DB_ATTEMPTS];
            $sendingStatus->lastAction_ts = $outbox[modeles\Outbox::DB_LAST_ACT];

            $this->addAttrToEmail($email, $outbox[modeles\Outbox::DB_FROM], 'From');
            $this->addAttrToEmail($email, $outbox[modeles\Outbox::DB_REPLY], 'ReplyTo');
            $this->addAttrToEmail($email, $outbox[modeles\Outbox::DB_TO], 'Address');
            $this->addAttrToEmail($email, $outbox[modeles\Outbox::DB_CC], 'CC');
            $this->addAttrToEmail($email, $outbox[modeles\Outbox::DB_BCC], 'BCC');

            if ($content !== false) {

                $email->Subject = $content[modeles\Content::DB_SUBJECT];
                $email->Body    = $content[modeles\Content::DB_BODY];
                $email->AltBody = $content[modeles\Content::DB_ALT_BODY];
                
                // if we got an alternative body or our body and out alt. body aren't the same, we force the use of HTML
                if ($email->AltBody !== '' || $email->Body !== '' && $email->Body !== $email->AltBody) {
                    $email->isHTML();
                }

                $this->addAttrToEmail($email, $content[modeles\Content::DB_ATTACHMENTS], 'Attachment');
                return $out_id;
            }

            else {
                $sendingStatus->lastAction_ts = time();
                $sendingStatus->error = 'Content (id='.$outbox[modeles\Outbox::DB_CONT_ID].') not found';
                $sendingStatus->state = SendindStatus::STATE_FAILED;

                $this->updateSendingStatus($sendingStatus, $out_id, $outbox[modeles\Outbox::DB_CONT_ID]);
            }

        }
        
        // check if sent_ttl is set and if the last flush action have been done more than a day before
        if ($this->sent_ttl > 0 && $this->db_system->getLastFlush() + 86400 < time()) {
            $this->flushSent(time() - $this->sent_ttl);
        }
        
        return false;
    }
    
    
    
    
    /**
     * Archive an email into sentbox
     * 
     * @param \PHPMailer $email     : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param integer    $outbox_id : outbox id if the email comes from the outbox (default : null)
     * @return integer|boolean : return sentbox in case of success, false if it fails
     */
    public function archive (\PHPMailer &$email, $outbox_id = null) 
    {   
        // Nous traitons notre contenu
        $cont_id = $this->process_content($email);

        // Si nous avons un id, nous construisons le données de l'outbox et nous 
        // les poussons dans la table prévue à cet effet
        if ($cont_id !== false) {

            $send_id = $this->db_sentbox->add( 
                $email->From.','.$email->FromName.';', 
                $this->getInline($email->getReplyToAddresses()), 
                $this->getInline($email->getToAddresses()), 
                $this->getInline($email->getCcAddresses()), 
                $this->getInline($email->getBccAddresses()), 
                $cont_id);

            if ($send_id !== false) { 
                
                $this->db_sentbox->updateLastAction($send_id);
                $this->db_content->updateLastAction($cont_id);
                
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
     * @param \BfwMailer\SendindStatus $sendingStatus : sending status to push into the database
     * @param integer                  $outbox_id     : outbox id
     * @param integer                  $content_id    : content id (default : null)
     */
    public function updateSendingStatus(SendindStatus $sendingStatus, $outbox_id, $content_id = null)
    {
        if ($content_id === null) {
            $outbox = $this->db_outbox->retrieve($outbox_id);
            $content_id = $outbox[modeles\Outbox::DB_CONT_ID];
        }
        
        $this->db_outbox->updateStatus($outbox_id, $sendingStatus->state, 
                $sendingStatus->error, $sendingStatus->attempts);  

        $this->db_outbox->updateLastAction($outbox_id, $sendingStatus->lastAction_ts);
        $this->db_content->updateLastAction($content_id, $sendingStatus->lastAction_ts);
    }
    
    
    
    /**
     * Return all email (in queue and sent) with content
     * 
     * @param string $source : source of the fetch, can only take value of 'outbox' or 'sentbox'
     * @return array : fetched data
     */
    public function fetchAll($source='outbox')
    {
        if ($source === 'outbox') {
            return $this->db_outbox->retrieveAll($this->db_content);
        }
        
        elseif ($source === 'sentbox') {
            return $this->db_sentbox->retrieveAll($this->db_content);
        }
        
        return array();
    }
    
    
    
    /**
     * Processing content of an email by searching if and returning it's identifier 
     * or by creating it if it doesn't exist, and returning it's identifier
     * 
     * @param \PHPMailer $email : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @return integer : content id of the processed content
     */
    private function process_content(\PHPMailer &$email) {
        // Convert array attachments into a string
        $attachments = $this->getInline($email->getAttachments());
        
        // Nous vérifions que le contenu de l'email à envoyé n'existe pas déjà en base de donnée
        $cont_id = $this->db_content->search($email->Subject, $email->Body, $email->AltBody, $attachments);
        
        // Si le contenu de l'email n'existe pas déjà, nous le créons
        if ($cont_id === false) {
            $cont_id = $this->db_content->add($email->Subject, $email->Body, $email->AltBody, $attachments);
        }
        
        return $cont_id;
    }
    


    
    
    /**
     * Flush sent message from sendbox and content database
     * 
     * @param int $timestamp : timestamp limit
     * @return boolean : false if timestamp is wrong, true otherwise
     */
    private function flushSent($timestamp)
    {
        // check if $timestamp is set and higher than 0
        if ($timestamp !== null && $timestamp > 0) {
            $this->db_sentbox->flush($timestamp);
            $this->db_content->flush($timestamp);
            
            $this->db_system->updateLastFlush();
            
            return true;
        }

        return false;
    }
    
    
    /**
     * Format and add an attribute that coming from the database to an email
     * 
     * @param \PHPMailer $email   : (as REF) PHPMailer object containing email informations (header, content, etc.)
     * @param string     $db_data : line of data coming from the database
     * @param string     $attr    : attribut to add to the email, can take those values : From, Address, ReplyTo, CC, BCC, Attachment
     * @return boolean : return true in case of success, false otherwise
     */
    private function addAttrToEmail(\PHPMailer &$email, $db_data, $attr = 'Address')
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
            }
            else {
                $a = array($line, '');
            }
            
            try {
                $email->{$method.$attr}($a[0], $a[1]);
            }

            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
                return false; 
            }
        }
        
        return true;
    }

    
    /**
     * Transform two dimensions array into a single string
     * Separate first level array values by ";" and second by ","
     * 
     * @param array $array
     * @return string
     */
    private function getInline(array $array) 
    {
        $inline = '';
        
        foreach ($array as $val) {
            if (is_array($val)) {
                $inline .= implode(',', $val).';';
            }
            
            else {
                $inline .= $val.';';
            }
        }
        
        return $inline;
    }
}