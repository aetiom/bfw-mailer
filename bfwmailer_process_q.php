<?php
/**
 * Script for sending email from the BFW Mailer queue (outbox)
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

/*
 * We advise you to add a cron job that execute that script each 10 seconds (or so
 * depending on your emailing volume) with command line : php cli.php bfwmailer_process_q
 */

// Retrieve mailer module from BFW Application
$mailer = \BFW\Application::getInstance()->getModule('mailer')->mailer;

// Processing mail queue by dequeuing, sending and archiving ONLY A SINGLE EMAIL from outbox
$mailer->process_queue();
