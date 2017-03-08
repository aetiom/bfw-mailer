<?php

namespace BfwMailer\Helpers;

/**
 * Helpers to securize data
 */
class Secure  extends \BFW\Helpers\Secure {
    
    /**
     * Get the sqlSecure function declared in bfw config file if existing, 
     * else return the default one : ['\BfwSql\Helpers', 'protectDatas'].
     * 
     * @return boolean|string
     */
    public static function getSqlSecureMethod()
    {
        $secureMethod = parent::getSqlSecureMethod();
        if ($secureMethod !== false) {
            return $secureMethod;
        }

        return ['\BfwSql\Helpers\Secure', 'protectDatas'];
    }
}
