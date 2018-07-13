<?php
/**
 * php-flexibee-matecher
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
require_once '../vendor/autoload.php';
$shared = new Ease\Shared();
$shared->loadConfig('../client.json');
$shared->loadConfig('../matcher.json');

//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n',
//    'flexibee-matcher');

$invoiceSteamer = new \FlexiPeeHP\Bricks\ParovacFaktur();

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'));
$invoiceSteamer->outInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'));
