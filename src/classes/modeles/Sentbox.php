<?php

namespace BfwMailer\modeles;

/**
 * Class that carries sentbox data
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
class Sentbox extends AbstrMailBox 
{
    /**
     * @var string $tableName : table name
     */
    protected $tableName = 'bfwmailer_sentbox';
    
    
    
    /**
     * Create table if it doesn't already exist
     * @param string $table_map_query : table map query to insert into the full query for table creation process
     */
    public function create_table($table_map_query = null) 
    {
        
        // prepare create table request
        $table_map_query = self::DB_ID.  " ID_PRIMARY_KEY_AI, "
                .self::DB_LAST_ACT. " INTEGER, "
                .self::DB_FROM.     " TEXT, "
                .self::DB_REPLY.    " TEXT, "
                .self::DB_TO.       " TEXT, "
                .self::DB_CC.       " TEXT, "
                .self::DB_BCC.      " TEXT, "
                .self::DB_CONT_ID.  " INTEGER UNSIGNED"; 

        parent::create_table($table_map_query);
    }
    
    
    
    /**
     * Flush content of the mailbox regarding email last action timestamps.
     * Method will delete all deprecated contents that is older than timestamp.
     * 
     * @param integer $timestamp : timestamp limit
     */
    public function flush($timestamp) 
    {
        if (!$this->is_flush_needed($timestamp)) {
            return false;
        }
        
        // Delete data from table if last action was performed before $timestamp limit
        $req = $this->delete($this->tableName)
                ->where(self::DB_LAST_ACT.'<=:limit', array(':limit' => $timestamp))
                ->execute();
        
        if (empty($req)) {
            throw new \Exception('flush failed in table '.$this->tableName.' for timestamp '.$timestamp);
        }
        
        return true;
    }
}
