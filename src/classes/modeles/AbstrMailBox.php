<?php

namespace BfwMailer\modeles;

/**
 * Abstract class that carries mailbox data
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
abstract class AbstrMailBox extends AbstrEmailData 
{

    // DB constants (DB map)
    const DB_FROM     = 'send_from';
    const DB_REPLY    = 'reply_to';
    const DB_TO       = 'send_to';
    const DB_CC       = 'send_cc';
    const DB_BCC      = 'send_bcc';
    const DB_CONT_ID  = 'content_id';
    
     
    /**
     * Search email into the mailbox.
     * All addresses fields may be filled in line with the database format, like this : "addr0, name0; addr1, name1; ..."
     * 
     * @param string  $from    : from field
     * @param string  $reply   : reply to field
     * @param string  $to      : to field
     * @param string  $cc      : cc field
     * @param string  $bcc     : bcc field
     * @param integer $cont_id : content id
     * @return mixed : mailbox id corresponding to the found email, or false in case of non match 
     */
    public function search($from, $reply, $to, $cc, $bcc, $cont_id)
    {
        $mailbox = array (
            self::DB_FROM    => $from,
            self::DB_REPLY   => $reply,
            self::DB_TO      => $to,
            self::DB_CC      => $cc,
            self::DB_BCC     => $bcc,
            self::DB_CONT_ID => $cont_id
        );
 
        $req = $this->select()->from($this->tableName, '*')
                ->where(self::DB_FROM.    '=:'.self::DB_FROM.     ' AND '
                        .self::DB_REPLY.  '=:'.self::DB_REPLY.    ' AND '
                        .self::DB_TO.     '=:'.self::DB_TO.       ' AND '
                        .self::DB_CC.     '=:'.self::DB_CC.       ' AND '
                        .self::DB_BCC.    '=:'.self::DB_BCC.      ' AND '
                        .self::DB_CONT_ID.'=:'.self::DB_CONT_ID, $mailbox)
                ->limit(1);
        $result = $this->fetch_sql($req, 'fetchRow');

        if (isset($result[self::DB_ID])) {
            return $result[self::DB_ID];
        }

        return false;
    }
    
    
    
    /**
     * Determine if a specific content is used by the mailbox
     * 
     * @param integer $cont_id : content id to search
     * @return boolean : true if content is currently used, otherwise false
     */   
    public function is_content_used($cont_id) 
    {
        $req = $this->select()->from($this->tableName, '*')
                ->where(self::DB_CONT_ID.'=:cont_id', array ('cont_id' => $cont_id))
                ->limit(1);
        $result = $this->fetch_sql($req, 'fetchRow');

        if (empty($result)) {
            return true;
        }

        return false;
    }
    
    
    
    /**
     * Add email into the mailbox.
     * All addresses fields may be filled in line with the database format, like this : "addr0, name0; addr1, name1; ..."
     *  
     * @param string  $from     : from field
     * @param string  $reply    : reply to field
     * @param string  $to       : to field
     * @param string  $cc       : cc field
     * @param string  $bcc      : bcc field
     * @param integer $cont_id  : content id
     * @return mixed : mailbox id corresponding to the added email, or false in case of fail
     */
    public function add($from, $reply, $to, $cc, $bcc, $cont_id)
    {
        $mailbox = array (
            self::DB_FROM    => $from,
            self::DB_REPLY   => $reply,
            self::DB_TO      => $to,
            self::DB_CC      => $cc,
            self::DB_BCC     => $bcc,
            self::DB_CONT_ID => $cont_id
        );
        
        $req = $this->insert()->into($this->tableName, $mailbox)->execute();
        
        if (!empty($req)) {
            return $this->obtainLastInsertedId();
        }
        
        return false;
    }
    
    
    
    /**
     * Extract all emails with their own contents
     * 
     * @return array : array of email joined with content in case of success, empty array otherwise
     */
    public function retrieve_all() 
    {
        $content = new Content();
        $content_tn = $content->get_tableName();
        
        // DB_MAP pour la table content
        $content_map = array(Content::DB_SUBJECT, Content::DB_BODY,
            Content::DB_ALT_BODY, Content::DB_ATTACHMENTS);
        
        // Connexion à la bdd pour récupérer l'email actif avec la plus petite priorité (type)
        $req = $this->select()->from($this->tableName, '*')
                ->join($content_tn, $this->tableName.'.'.self::DB_CONT_ID.'='.
                        $content_tn.'.'.Content::DB_ID, $content_map)
                ->where(1)->order($this->tableName.'.'.self::DB_LAST_ACT.' ASC');

        return $this->fetch_sql($req);
    }
}