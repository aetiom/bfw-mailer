<?php
/**
 * Initialisation script for the module
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

$config = $this->getConfig();

// Instanciate our mailer class
$this->mailer = new \BfwMailer\Mailer($config);