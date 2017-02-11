<?php
/**
 * Initialisation script for the module database
 * @author Alexandre Moittié <contact@alexandre-moittie.com>
 * @package bfw-mailer
 * @version 1.0
 */

// Get our scriptname and set error flag to false
$db_initscript = basename(__FILE__);
$error = false;

// Load bfw-sql config file and check it
$result = "\033[0m".'> Verifying bfw-sql configuration';

$app = \BFW\Application::getInstance();

try {
    $bfwsql = $app->getModule('bfw-sql');
}

catch (\Exception $e) {
    // Display error and tip message if the bfw-sql config file is missing
    $result .= "...\033[1;31m Fail !\n";
    $result .= "\033[1;33m  ".$e->getMessage()."\n";
    $result .= "\033[1;36m".'  Tip : install and configure bfw-sql module';
    $error = true;
}
    
if ($error === false) {
    if ($bfwsql->isLoaded() !== false && $bfwsql->isRun() !== false) {
        $config = $bfwsql->getConfig()->getConfig('bases');
        
        if (!empty($config[0]->host) && !empty($config[0]->baseName) 
                && !empty($config[0]->user) && !empty($config[0]->baseType)) {

            // Create and init our tables into the user database
            $result .= "...\033[1;32m Done\033[0m\n";
            $result .= '> Create and/or initialize database tables :'."\033[0m\n";

            $db_system = new \BfwMailer\modeles\System();
            $db_system->create_table();

            $result .= '>> bfwmailer_system ...'."\033[1;32m Done\033[0m\n";

            $db_content = new \BfwMailer\modeles\Content();
            $db_content->create_table();

            $result .= '>> bfwmailer_content ...'."\033[1;32m Done\033[0m\n";

            $db_outbox = new \BfwMailer\modeles\Outbox();
            $db_outbox->create_table();

            $result .= '>> bfwmailer_outbox ...'."\033[1;32m Done\033[0m\n";

            $db_sentbox = new \BfwMailer\modeles\Sentbox();
            $db_sentbox->create_table();

            $result .= '>> bfwmailer_sentbox ...'."\033[1;32m Done\033[0m\n\n";
        }

        else {
            // Display error and tip message if the config file is "empty" (or likewise)
            $result .= "...\033[1;31m Fail !\n";
            $result .= '  bfw-sql module is not configurated correctly'."\033[0m\n";
            $result .= "\033[1;36m".'  Tip : configure bfw-sql module (app/configs/bfw-sql/config.php)';
            $error = true;
        }
    }

    else {
        // Display error and tip message if the bfw-sql module is not active
        $result .= "...\033[1;31m Fail !\n";
        $result .= '  bfw-sql module is not running'."\033[0m\n";
        $result .= "\033[1;36m".'  Tip : activate and configure bfw-sql module (app/configs/bfw-sql/config.php)';
        $error = true;
    }
}



// Define symlink for this script
$link = CLI_DIR.$db_initscript;

// If we don't have any error during the processing, 
// we try to delete the symlink of this script present into the /cli directory
if ($error === false) {
    $result .= '> Delete symbolic link to '."\033[0;36m".$db_initscript;
    
    if (is_link($link)) {
        if (unlink($link)) {
            $result .= "\033[0m ...\033[1;32m Done\033[0m";
        }

        else {
            $result .= "\033[0m ...\033[1;31m Fail !\033[0m";
        }
    }
    
    else {
        $result .= "\033[0m ...\033[1;33m Not found\033[0m";
    }
}

// If we have any error, we print a last tip to user
else {
    $result .= " ,\n".'  then execute that command : '."\033[1;35mphp ".ROOT_DIR.'cli.php -f '.basename($db_initscript, '.php');
}

$result .= "\033[0m\n";
echo $result;
