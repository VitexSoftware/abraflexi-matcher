<?php

/**
 * flexibee-send-unsent
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
define('EASE_APPNAME', 'OdeslatNeodeslane');
require_once '../vendor/autoload.php';
$shared = new \Ease\Shared();
if (file_exists('../client.json')) {
    $shared->loadConfig('../client.json', true);
}

$invoicer = new \FlexiPeeHP\FakturaVydana();

$invoicer->logBanner(constant('EASE_APPNAME'));
$invoicer->addStatusMessage(_('Send unsent mails'), $invoicer->sendUnsent() == 202 ? 'success' : 'warning' );
