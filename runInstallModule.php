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
if(!file_exists(BFW_PATH.'/src/cli/'.$process_q_script)) {
    
    // We check that our source file exists
    if(file_exists($modulePath.$process_q_script)) {
        
        // We copy our script into our destination path
        if(copy($modulePath.$process_q_script, BFW_PATH.'/src/cli/'.$process_q_script)) {
            echo "...\033[1;32m Done";
        }
        
        else {
            // Display error in case of symlink fail
            echo "...\033[1;31m Fail !";
        }
    }
    
    else {
        // Display error if the source file does not exist
        echo "...\033[1;31m File not found : Fail !";
    }
}

else {
    // Dispay warning if the destination file (link) already exists
    echo "...\033[1;33m Already exist.";
}


echo "\033[0m\n";
echo '     > Create symbolic link to '."\033[0;36m".$db_initscript."\033[0m into /src/cli/";

// We check that our destination file does not exist
if(!file_exists(BFW_PATH.'/src/cli/'.$db_initscript)) {
    
    // We check that our source file exists
    if(file_exists($modulePath.$db_initscript)) {
        
        // We create our symbolic link between our destinatation and our source file
        if(symlink($modulePath.$db_initscript, BFW_PATH.'/src/cli/'.$db_initscript)) {
            echo "...\033[1;32m Done";
        }
        
        else {
            // Display error in case of symlink fail
            echo "...\033[1;31m Fail !";
        }
    }
    
    else {
        // Display error if the source file does not exist
        echo "...\033[1;31m File not found : Fail !";
    }
}

else {
    // Dispay warning if the destination file (link) already exists
    echo "...\033[1;33m Already exist.";
}

echo "\033[0m\n";

// Execute the cli.php script with our own initscript into parameters
echo '     > Execute script '."\033[0;36m".$db_initscript."\033[0m".' ...'."\n";
echo "\033[0m\n";

exec('php '.BFW_PATH.'/cli.php -f '.str_replace('.php', '', $db_initscript), $outputs);

// Set the shifting for our initscript outputs too 
$shifting = '          ';

// Add our shifting + each line of initscript output + return to our result
foreach($outputs as $out) {
    echo $shifting.$out."\n";
}