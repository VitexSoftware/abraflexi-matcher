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
$shared->loadConfig('../matcher.json', true);

$banker = new \FlexiPeeHP\Banka();
$banker->logBanner(constant('EASE_APPNAME'));
$banker->addStatusMessage(_('Download online bank statements'));
if ($banker->stahnoutVypisyOnline()) {
    foreach (explode("\n", $banker->lastCurlResponse) as $row) {
        if (!empty(trim($row))) {
            $banker->addStatusMessage($row,'success');
        }
    }
} else {
    $banker->addStatusMessage('Bank Failure!', 'error');
}
