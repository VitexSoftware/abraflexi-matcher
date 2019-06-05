<?php
/**
 * php-flexibee-automatcher
 * 
 * @copyright (c) 2018-2019, Vítězslav Dvořák
 */
define('EASE_APPNAME', 'AutoMatcher');
require_once '../vendor/autoload.php';
$shared = new Ease\Shared();
$shared->loadConfig('../client.json');
$shared->loadConfig('../matcher.json');

//new \Ease\Locale($shared->getConfigValue('LOCALIZE'), '../i18n','flexibee-matcher');

$invoiceSteamer = new \FlexiPeeHP\Banka(null,$shared->configuration);
$invoiceSteamer->logBanner(constant('EASE_APPNAME'));

if ($shared->getConfigValue('PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}


$invoiceSteamer->addStatusMessage(_('Automatic Invoice matching begin'));
$invoiceSteamer->automatickeParovani();
$invoiceSteamer->addStatusMessage(_('Automatic Invoice matching done'). $invoiceSteamer->responseStats['updated']);
