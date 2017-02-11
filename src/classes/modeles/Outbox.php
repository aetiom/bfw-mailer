<?php

namespace BfwMailer\modeles;

/**
 * Class that carries outbox data
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class Outbox extends AbstrMailBox 
{
    // DB constants (DB map)
    const DB_STATE    = 'state';
    const DB_PRIORITY = 'priority';
    const DB_ERROR    = 'error';
    const DB_ATTEMPTS = 'attempts';
    
    /**
     * @var string $tableName : table name
     */
    protected $tableName = 'bfwmailer_outbox';
    
    
    
    /**
     * Create table if it doesn't already exist
     * @param string $table_map_query : table map query to insert into the full query for table creation process
     */
    public function create_table($table_map_query = null) 
    {
        
        // prepare create table request
        $table_map_query = self::DB_ID.  " ID_PRIMARY_KEY_AI, "
            .self::DB_STATE.    " INTEGER UNSIGNED, "
            .self::DB_PRIORITY. " INTEGER UNSIGNED, "
            .self::DB_LAST_ACT. " INTEGER, "
            .self::DB_FROM.     " TEXT, "
            .self::DB_REPLY.    " TEXT, "
            .self::DB_TO.       " TEXT, "
            .self::DB_CC.       " TEXT, "
            .self::DB_BCC.      " TEXT, "
            .self::DB_CONT_ID.  " INTEGER UNSIGNED, "
            .self::DB_ERROR.    " VARCHAR(128), "
            .self::DB_ATTEMPTS. " INTEGER UNSIGNED";

        parent::create_table($table_map_query);
    }
    
    
    
    /**
     * Add email into outbox.
     * All addresses fields may be filled in line with the database format, like this : "addr0, name0; addr1, name1; ..."
     * 
     * @param string  $from    : from field
     * @param string  $reply   : reply to field
     * @param string  $to      : to field
     * @param string  $cc      : cc field
     * @param string  $bcc     : bcc field
     * @param integer $cont_id : content id
     * @param integer $prio    : priority, default = SendingStatus::PRIO_DEFAULT
     * @return mixed : outbox id corresponding to the added email, or false in case of fail
     */
    public function add($from, $reply, $to, $cc, $bcc, $cont_id, $prio = \BfwMailer\SendingStatus::PRIO_DEFAULT)
    {
        $email_id = parent::add($from, $reply, $to, $cc, $bcc, $cont_id);
        
        if ($email_id !== false) {
            // If email_id is not false, we set priority to the email previously added
            $req = $this->update($this->tableName, array (self::DB_PRIORITY => $prio))
                ->where(self::DB_ID.'=:id', array(':id' => $email_id));
            $res = $req->execute();
            
            if ($res !== false) {
                return $email_id;
            }
        }
        
        return false;
    }
    
    
    
    /**
     * Get the next email to send from the mailbox queue
     *
     * @param int $maxSendingAttempts : max sending attempts authorized
     * @return mixed : next email to send id if success, false otherwise
     */
    public function getNextPending($maxSendingAttempts)
    {
        // Nous construisons nos données pour le test sur l'outbox
        $outbox_test = array(
            ':pending'     => \BfwMailer\SendindStatus::STATE_PENDING, 
            ':failed'      => \BfwMailer\SendindStatus::STATE_FAILED, 
            ':maxAttempts' => $maxSendingAttempts,
            ':actualTime'  => time());
        
        // Connexion à la bdd pour récupérer l'email actif avec la plus petite priorité (type)
        $req = $this->select()->from($this->tableName)
                ->where('('.self::DB_STATE.    '=:pending OR '
                        .self::DB_STATE.       '=:failed) AND '
                        .self::DB_ATTEMPTS.'<:maxAttempts AND '
                        .self::DB_LAST_ACT.    '<=:actualTime', $outbox_test)
                ->order(self::DB_PRIORITY.' ASC')
                ->limit(1);
        $outbox = $this->fetchSql($req, 'fetchRow');
        
        if (isset($outbox[self::DB_ID])) {
            return $outbox[self::DB_ID];
        }
        
        return false;
    }
    
    
    
    /**
     * Update email status
     * 
     * @param integer $outbox_id  : email id
     * @param integer $state      : sending state
     * @param string  $error      : sending error message
     * @param integer $attempts   : sending attempts
     * @return boolean : true in case of success, false otherwise
     * @throws Exception
     */
    public function updateStatus ($outbox_id, $state, $error, $attempts) 
    {
        $mailbox_push = array (
            self::DB_STATE    => $state,
            self::DB_ERROR    => '"'.$error.'"',
            self::DB_ATTEMPTS => $attempts
        );

        $req = $this->update($this->tableName, $mailbox_push)
                ->where(self::DB_ID.'=:id', array(':id' => $outbox_id))
                ->execute();

        if ($req === false) {
            throw \Exception('outbox status update failed for email '.$outbox_id);
        }
    }
    
    
    
    /**
     * Refresh scheduled emails
     * 
     * @param int $timestamp : timestamp that will determine if an email must stay into scheduled state or not
     * @return boolean
     */
    public function refreshScheduled($timestamp = null) 
    {
        // if timestamp is not filled, take actual timestamp
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        // construct data for test purposes
        $mailbox_test = array(
            ':scheduled'  => \BfwMailer\SendindStatus::STATE_SCHEDULED,
            ':actualTime' => $timestamp
        );
        
        // construct data to push
        $mailbox_push = array (
            self::DB_STATE    => \BfwMailer\SendindStatus::STATE_PENDING,
            self::DB_LAST_ACT => time()
        );

        $req = $this->select()->from($this->tableName)
                ->where(self::DB_STATE.'=:scheduled AND '.self::DB_LAST_ACT.'<=:actualTime', $mailbox_test);
        $mailbox = $this->fetchSql($req);

        if (!empty($mailbox)) {
            foreach ($mailbox as $email) {
                $req = $this->update($this->tableName, $mailbox_push)
                        ->where(self::DB_ID.'=:id', array(':id' => $email[self::DB_ID]))
                        ->execute();

                if ($req === false) {
                    throw \Exception("scheduled email refresh failed while updating email ".$email[self::DB_ID]);
                }
            }
            return true;
        }
        return false;
    }
}