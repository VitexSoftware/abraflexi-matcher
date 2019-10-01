<?php
/**
 * flexibee-pull-bank
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
define('EASE_APPNAME', 'StahniBanku');
require_once '../vendor/autoload.php';
$shared = new Ease\Shared();
$shared->loadConfig('../client.json', true);

$banker = new \FlexiPeeHP\Banka();
$banker->logBanner(constant('EASE_APPNAME'));
$banker->addStatusMessage(_('Download online bank statements'),'debug');
if (!$banker->stahnoutVypisyOnline()) {
    $banker->addStatusMessage('Bank Offline!', 'error');
}
