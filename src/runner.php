<?php
/**
 * Initialisation script for the module
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

$config = $module->getConfig();

// Instanciate our mailer class
$module->mailer = new \BfwMailer\Mailer($config);