<?php
/**
 * Initialisation script for the module database
 * @author Aetiom <aetiom@protonmail.com>
 * @package bfw-mailer
 * @version 1.1
 */

//Default: Run by BFW install module script
$shiftingActions = '          >';
$shiftingTxt     = '               ';

//Run by user with cli.php
if (defined('CLI_MODE')) {
    $shiftingActions = ' >';
    $shiftingTxt     = '   ';
}

/**
 * Closure to display tip for re-execute this script
 * 
 * @return boolean
 */
$displayHelpToReRunCmd = function() use ($shiftingTxt) {
    echo ",\n"
        .$shiftingTxt.'then execute that command : '
        ."\033[1;35mphp ".ROOT_DIR.'cli.php -f '.basename(__FILE__, '.php')
        ."\033[0m\n";
    
    return false;
};

$app = \BFW\Application::getInstance();

// Load bfw-sql config file and check it
echo $shiftingActions.' Verifying bfw-sql configuration... ';

try {
    $bfwsql = $app->getModuleList()->getModuleByName('bfw-sql');
} catch (\Exception $e) {
    // Display error and tip message if the bfw-sql module is missing
    echo "\033[1;31m Fail !\n"
        .$shiftingTxt."\033[1;33m  ".$e->getMessage()."\n"
        .$shiftingTxt."\033[1;36m".'  Tip : install and configure bfw-sql module';
    
    return $displayHelpToReRunCmd();
}

// If bfw-sql is not load, try to load it
if ($bfwsql->isLoaded() === false) {
    $bfwsql->loadModule();
}

// If bfw-sql is loaded but not runned, try to run it
if ($bfwsql->isLoaded() === true && $bfwsql->isRun() === false) {
    $bfwsql->runModule();
}

// If bfw-sql does'nt want to load or run, error.
if ($bfwsql->isLoaded() !== true || $bfwsql->isRun() !== true) {
    // Display error and tip message if the bfw-sql module is not active
    echo "\033[1;31m Fail !\n"
        .$shiftingTxt.'bfw-sql module is not running'."\033[0m\n"
        .$shiftingTxt."\033[1;36m".'Tip : activate and configure bfw-sql'
        .' module (app/configs/bfw-sql/config.php)';
    
    return $displayHelpToReRunCmd();
}

// Check if bfw-sql is configured
if ($bfwsql->listBases === []) {
    // Display error and tip message if the config file is "empty" (or likewise)
    echo "\033[1;31m Fail !\n"
        .$shiftingTxt.'bfw-sql module is not configurated correctly'."\033[0m\n"
        .$shiftingTxt."\033[1;36m".'Tip : configure bfw-sql module'
        .' (app/configs/bfw-sql/config.php)';
    
    return $displayHelpToReRunCmd();
}

// bfw-sql configuration is good
echo "\033[1;32m Done\033[0m\n";

// Create and init our tables into the user database
echo $shiftingActions.' Create and/or initialize database tables :'."\n";


echo $shiftingActions.'> bfwmailer_content ...';

try {
    $db_content = new \BfwMailer\modeles\Content();
    $db_content->create_table();
    echo "\033[1;32m Done\033[0m\n";
} catch (\Exception $e) {
    echo "\033[1;31m Fail !\033[0m\n";
    echo $shiftingTxt.$e."\n";
}

echo $shiftingActions.'> bfwmailer_outbox ...';

try {
    $db_outbox = new \BfwMailer\modeles\Outbox();
    $db_outbox->create_table();
    echo "\033[1;32m Done\033[0m\n";
} catch (\Exception $e) {
    echo "\033[1;31m Fail !\033[0m\n";
    echo $shiftingTxt.$e."\n";
}

echo $shiftingActions.'> bfwmailer_sentbox ...';

try {
    $db_sendbox = new \BfwMailer\modeles\Sentbox();
    $db_sendbox->create_table();
    echo "\033[1;32m Done\033[0m\n";
} catch (\Exception $e) {
    echo "\033[1;31m Fail !\033[0m\n";
    echo $shiftingTxt.$e."\n";
}


// If we don't have any error during the processing, 
// we try to delete the symlink of this script present into the /cli directory

// Define symlink for this script
$db_initscript = basename(__FILE__); // Get our scriptname
$link = CLI_DIR.$db_initscript;

echo $shiftingActions.' Delete symbolic link to '
    ."\033[0;36m".$db_initscript."\033[0m ...";

if (is_link($link)) {
    if (unlink($link)) {
        echo "\033[1;32m Done\033[0m";
    } else {
        echo "\033[1;31m Fail !\033[0m";
    }
} else {
    echo "\033[1;33m Not found\033[0m";
}

echo "\n";
