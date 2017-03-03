<?php

namespace BfwMailer;

/**
 * Class that carries the sending status
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */
class SendingStatus {

    // STATE constants
    const STATE_SCHEDULED = 3;
    const STATE_PENDING = 2;
    const STATE_SUCCEEDED = 1;
    const STATE_FAILED = 0;
    
    // PRIORITY constants
    const PRIO_SYSTEM = 0;
    const PRIO_CONTACT = 3;
    const PRIO_DEFAULT = 6;
    const PRIO_NEWSLETTER = 9;

    /**
     * @var integer|null $queue_id : email queue identifier
     */
    public $queue_id = null;
    
    /**
     * @var integer $state : email state
     */
    public $state = self::STATE_PENDING;
    
    /**
     * @var integer $priority : email priority
     */
    public $priority = self::PRIO_DEFAULT;
    
    /**
     * @var string $error : email error message 
     */
    public $error = '';
    
    /**
     * @var integer $lastAction_ts : email last action timestamp
     */
    public $lastAction_ts = 0;
    
    /**
     * @var integer $attempts : email sending attempts
     */
    public $attempts = 0;
}
