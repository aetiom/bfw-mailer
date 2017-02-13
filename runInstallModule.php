<?php
/**
 * Install script for the module
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

// Get the module path and set the database initscript name
$modulePath = realpath(__DIR__).'/';
$db_initscript = 'bfwmailer_init_db.php';
$process_q_script = 'bfwmailer_process_q.php';


//$result .= "\033[0m\n";
echo "\n".'     > Copy script '."\033[0;36m".$process_q_script."\033[0m into /src/cli/";

// We check that our destination file does not exist
if(!file_exists(CLI_DIR.$process_q_script)) {
    // We copy our script into our destination path
    if(copy($modulePath.$process_q_script, CLI_DIR.$process_q_script)) {
        echo "...\033[1;32m Done";
    } else {
        // Display error in case of symlink fail
        echo "...\033[1;31m Fail !";
    }
} else {
    // Dispay warning if the destination file (link) already exists
    echo "...\033[1;33m Already exist.";
}

echo "\033[0m\n";
echo '     > Create symbolic link to '."\033[0;36m".$db_initscript."\033[0m into /src/cli/";

// We check that our destination file does not exist
if(!file_exists(CLI_DIR.$db_initscript)) {
    // We create our symbolic link between our destinatation and our source file
    if(symlink($modulePath.$db_initscript, CLI_DIR.$db_initscript)) {
        echo "...\033[1;32m Done";
    } else {
        // Display error in case of symlink fail
        echo "...\033[1;31m Fail !";
    }
} else {
    // Dispay warning if the destination file (link) already exists
    echo "...\033[1;33m Already exist.";
}

echo "\033[0m\n";

// Execute the cli.php script with our own initscript into parameters
echo '     > Execute script '."\033[0;36m".$db_initscript."\033[0m".' ...'."\n";
echo "\033[0m\n";

require_once($db_initscript);