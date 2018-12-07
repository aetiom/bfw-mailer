<?php

namespace BfwMailer\modeles;

/**
 * Abstract class that carries email data
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
abstract class AbstrEmailData extends AbstrModeles
{
    
    // DB constants (DB map)
    const DB_ID          = 'id';
    const DB_LAST_ACT    = 'last_action_ts';
    
    
    
    /**
     * Retrieve data
     * 
     * @param integer|null $id : content id to search
     * @return mixed : data searched in case of success, false otherwise
     */
    public function retrieve($id = null)
    {
        if ($id === null) {
            $req = $this->select()->from($this->tableName);
            $result = $this->fetch_sql($req);
        } else {
            $req = $this->select()->from($this->tableName)->where(self::DB_ID.'=:id', array('id' => $id));
            $result = $this->fetch_sql($req, 'fetchRow');
        }
        
        return $result;
    }
    
    
    
    /**
     * Update data last action timestamp
     * 
     * @param integer $id        : data id 
     * @param integer $timestamp : last action timestamp
     * 
     * @throws \Exception
     * @return boolean : true in case of success, false otherwise
     */
    public function update_lastAction ($id, $timestamp = null) 
    {
        // if last action timestamp is not filled, take actual timestamp
        if ($timestamp === null || empty($timestamp)) {
            $timestamp = strval(time());
        } else {
            $timestamp = strval($timestamp);
        }
        
        $req = $this->select()->from($this->tableName)->where(self::DB_ID.'=:id', array('id' => $id));
        $result = $this->fetch_sql($req, 'fetchRow');

        if (!empty($result) && $result[self::DB_LAST_ACT] !== $timestamp) {
            $req = $this->update($this->tableName, array(self::DB_LAST_ACT => $timestamp))
                    ->where(self::DB_ID.'=:id', array(':id' => $id))
                    ->execute();


            if (empty($req)) {
                throw new \Exception('last action update failed in table '.$this->tableName.' for id '.$id);
            }
        }
    }
    
    
    
    /**
     * flush content regarding its last action timestamp
     */
    abstract public function flush($timestamp);
    
    
    
    /**
     * Remove data
     * 
     * @param integer $id : data id into the database
     * @throws \Exception
     */
    public function remove($id)
    {
        // Delete data from table if last action was performed before $timestamp limit
        $req = $this->delete($this->tableName)
                ->where(self::DB_ID.'=:id', array('id' => $id))
                ->execute();

        if (empty($req)) {
            throw new \Exception('removing failed in table '.$this->tableName.' for id '.$outbox_id);
        }
    }
    
    
    
    /**
     * Verify if a flush is needed
     * 
     * @param integer $timestamp : timestamp limit for flushing sent email
     * @return boolean : true in case of flush is needed, false otherwise
     */
    protected function is_flush_needed($timestamp) {
        
        // prepare the request
        $req = $this->select()->from($this->tableName)
                ->where(self::DB_LAST_ACT.'<=:limit', array(':limit' => $timestamp));

        if(empty($this->fetch_sql($req))) {
            return false;
        } 
        
        return true;
    }
}
