<?php

namespace BfwMailer\modeles;

/**
 * Abstract class for all modeles into bfw-mailer
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */
abstract class AbstrModeles extends \BfwSql\AbstractModeles
{
    
    /**
     * @var boolean $secure_data : Secure raw data before inserting it into database
     * (doing a pdo quote on all external stored datas)
     */
    protected $secure_data = true;
    
    
    
    /**
     * Get table name
     * @return string : table name
     */
    public function get_tableName() 
    {
        return $this->tableName;
    }
        
    
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        $config = \BFW\Application::getInstance()->getModuleList()->getModuleByName('bfw-mailer')->getConfig();
        $this->secure_data = $config->getValue('secure_data');
    }
    
    

    /**
     * Create table containing a particular table map if it doesn't already exist
     * @param string $table_map_query : table map query to insert into the full query for table creation process
     */
    public function create_table($table_map_query) 
    {
        
        $type = $this->getSqlConnect()->getType();
        
        // generate the right automatic key, depending on the db/connexion type
        if ($type === 'pgsql') {
            $auto_key = 'SERIAL PRIMARY KEY';
        } elseif ($type === 'sqlite') {
            $auto_key = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        } else {
            $auto_key = 'INTEGER PRIMARY KEY AUTO_INCREMENT';
        }
        
        // replace the primary key keyword by the righ automatic key
        $new_query = str_replace('ID_PRIMARY_KEY_AI', $auto_key, $table_map_query);
        
        // execute creation query into the table in "if not exists" mode
        $this->query("CREATE TABLE IF NOT EXISTS ".$this->tableName." (".$new_query.");");
    }
    
    
    
    /**
     * Secure Data before inserting it into database
     * 
     * @param string  $data : data value
     * @param string  $type : data type (integer, boolean, email)
     * @param boolean $html : use htmlentities
     * 
     * @return mixed data value formated and secured
     */
    protected function secureData($data, $type='', $html=false)
    {
        if ($this->secure_data) {
            return \BfwMailer\Helpers\Secure::secureData($data, $type, $html);
        }
        
        return $data;
    }
    
    
    
    /**
     * Fetching, verifying and returning data on a Sql Fetch
     * 
     * @param SqlSelect $req       : query instance
     * @param string    $fetchType : fetch type. Can contain fetchRow or fetchAll.
     * 
     * @throws \Exception
     * @return boolean|array : false si erreur, tableau de résultat sinon
     */
    protected function fetch_sql($req, $fetchType = 'fetchAll')
    {
        if($fetchType != 'fetchAll' && $fetchType != 'fetchRow') {
            return false;
        }

        try {
            $res = $req->getExecuter()->{$fetchType}();
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            return false;
        }

        if ($res) {
            return $res;
        } else {
            return array();
        }
    }
}