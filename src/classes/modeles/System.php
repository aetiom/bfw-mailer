<?php

namespace BfwMailer\modeles;

/**
 * Class that carries bfw-mailer system data
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class System extends AbstrModeles 
{
    // DB constants
    const DB_REF   = 'ref';
    const DB_VALUE = 'value';
    
    // DB Labels constants
    const L_VERSION      = 'db_version';
    const L_LAST_REFRESH = 'last_refresh_ts';
    const L_LAST_ROTATE  = 'last_rotate_ts';
    const L_LAST_FLUSH   = 'last_flush_ts';
    
    // DB Values constants
    const ACTUAL_DB_VERSION = '1';
    
    
    
    /**
     * @var string $tableName : table name
     */
    protected $tableName = 'bfwmailer_system';
    
    /**
     * @var $lastRefresh_ts : last refresh timestamp
     */
    protected $lastRefresh_ts;
    
    /**
     * @var $lastRotate_ts : last rotation timestamp
     */
    protected $lastRotate_ts;
    
    /**
     * @var $lastFlush_ts : last flush action timestamp
     */
    protected $lastFlush_ts;
    
    
    
    /**
     * Get last refresh timestamp
     * @return integer : last refresh timestamp
     */
    public function getLastRefresh() 
    {
        if ($this->lastRefresh_ts === null) {
            $this->getData();
        }
        
        return $this->lastRefresh_ts;
    }
    
    /**
     * Get last rotation timestamp
     * @return integer : last rotation timestamp
     */
    public function getLastRotation() 
    {
        if ($this->lastRotate_ts === null) {
            $this->getData();
        }
        
        return $this->lastRotate_ts;
    }
    
    /**
     * Get last flush action timestamp
     * @return integer : last flush action timestamp
     */
    public function getLastFlush() 
    {
        if ($this->lastFlush_ts === null) {
            $this->getData();
        }
        
        return $this->lastFlush_ts;
    }

    
    
    /**
     * Create table if it doesn't already exist
     * @param string $table_map_query : table map query to insert into the full query for table creation process
     */
    public function create_table($table_map_query = null) 
    {
        // prepare create table request
        $table_map_query = self::DB_REF." VARCHAR(255),".self::DB_VALUE." VARCHAR(255)";
        
        parent::create_table($table_map_query);
        
        $this->init_ref(self::L_VERSION, self::ACTUAL_DB_VERSION);
        $this->init_ref(self::L_LAST_REFRESH, '0');
        $this->init_ref(self::L_LAST_ROTATE, '0');
        $this->init_ref(self::L_LAST_FLUSH, '0');
    }
    
    
    
    
    /**
     * Update last refresh timestamp with actual time
     * @return boolean : true if updated, false otherwise
     */
    public function updateLastRefresh()
    {
        // Get the data before updating it
        if ($this->lastRefresh_ts === null) {
            $this->getData();
        }
        
        // Set our data to actual time
        $this->lastRefresh_ts = time();
        return $this->updateData(self::L_LAST_REFRESH, $this->lastRefresh_ts);
        
    }
        
    
    
    /**
     * Update last rotation timestamp with actual time
     * @return boolean : true if updated, false otherwise
     */
    public function updateLastRotation()
    {
        // Get the data before updating it
        if ($this->lastRotate_ts === null) {
            $this->getData();
        }
        
        // Set our data to actual time
        $this->lastRotate_ts = time();
        return $this->updateData(self::L_LAST_ROTATE, $this->lastRotate_ts);
    }
    
    
    
    
    /**
     * Update last flush action timestamp with actual time
     * @return boolean : true if updated, false otherwise
     */
    public function updateLastFlush()
    {
        // Get the data before updating it
        if ($this->lastFlush_ts === null) {
            $this->getData();
        }
        
        // Set our data to actual time
        $this->lastFlush_ts = time();
        return $this->updateData(self::L_LAST_FLUSH, $this->lastFlush_ts);
    }
    
    
    
    /**
     * Initialize a reference with a specific value
     * 
     * @param string         $ref   : database reference
     * @param string|integer $value : value of the reference
     */
    private function init_ref($ref, $value)
    {
        $req = $this->select()->from($this->tableName)->where(self::DB_REF.'=:ref', array('ref' => $ref));
        $res = $this->fetchSql($req, 'fetchRow');
        
        if (isset($res[self::DB_REF])) {
            $req = $this->update($this->tableName, array(self::DB_VALUE => $value))
                ->where(self::DB_REF.'=:ref', array('ref' => $ref))
                ->execute();
        }
        
        else {
            $data = array(self::DB_REF => $ref, self::DB_VALUE => $value);
            $req = $this->insert($this->tableName, $data)->execute();
        }
    }
    
    
    
    /**
     * Get data from the database and update instance variables
     */
    private function getData() {
        // Get system informations from database
        $req = $this->select()->from($this->tableName)->where(1);
        $res = $this->fetchSql($req);

        // Set system informations into this
        foreach ($res as $line) {
            
            if ($line[self::DB_REF] === self::L_LAST_REFRESH) {
                $this->lastRefresh_ts = intval($line[self::DB_VALUE]);
            }
            
            elseif ($line[self::DB_REF] === self::L_LAST_ROTATE) {
                $this->lastRotate_ts = intval($line[self::DB_VALUE]);
            }
            
            elseif ($line[self::DB_REF] === self::L_LAST_FLUSH) {
                $this->lastFlush_ts = intval($line[self::DB_VALUE]);
            }
        }
    }
    
    
    
    /**
     * Update reference into the database
     * 
     * @param string         $ref   : database reference
     * @param string|integer $value : value of the reference
     * @return boolean : true in case of success, false otherwise
     */
    private function updateData($ref, $value) {
        
        $req = $this->select()->from($this->tableName)->where(self::DB_REF.'=:ref', array('ref' => $ref));
        $res = $this->fetchSql($req, 'fetchRow');
        
        if ($res[self::DB_VALUE] !== strval($value)) {
            $req = $this->update($this->tableName, array(self::DB_VALUE => $value))
                    ->where(self::DB_REF.'=:data', array('data' => $ref))
                    ->execute();

            if ($req === false) {
                return false;
            }
        }
        
        return true;
    }
}
