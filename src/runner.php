<?php
/**
 * Initialisation script for the module
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */

$config = $this->getConfig();

// Instanciate our mailer class
$this->mailer = new \BfwMailer\Mailer($config);