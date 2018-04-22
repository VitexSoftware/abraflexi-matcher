<?php
/**
 * php-flexibee-matecher
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
require_once '../vendor/autoload.php';
define('EASE_LOGGER', 'syslog|console');
$shared = new Ease\Shared();
$shared->loadConfig('../client.json');
$shared->loadConfig('../matcher.json');


$invoiceSteamer = new \FlexiPeeHP\Bricks\ParovacFaktur();

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Invoice matching begin'));
$invoiceSteamer->invoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Invoice matching done'));
