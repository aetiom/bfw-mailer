<?php

namespace BfwMailer\modeles;

/**
 * Class that carries sentbox data
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class Sentbox extends AbstrMailBox 
{
    /**
     * @var string $tableName : table name
     */
    protected $tableName = 'bfwmailer_sendbox';
    
    
    
    /**
     * Create table if it doesn't already exist
     */
    public function create_table() 
    {
        
        // prepare create table request
        $create_query = self::DB_ID.  " ID_PRIMARY_KEY_AI, "
                .self::DB_LAST_ACT. " INTEGER, "
                .self::DB_FROM.     " TEXT, "
                .self::DB_REPLY.    " TEXT, "
                .self::DB_TO.       " TEXT, "
                .self::DB_CC.       " TEXT, "
                .self::DB_BCC.      " TEXT, "
                .self::DB_CONT_ID.  " INTEGER UNSIGNED"; 

        parent::create_table($create_query);
    }
}
