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

$labeler = new FlexiPeeHP\Stitek();
$labeler->logBanner(constant('EASE_APPNAME'));
$labeler->addStatusMessage(_('Adding labels'), 'warning');
$labeler->createNew('PREPLATEK', ['banka']);
$labeler->createNew('CHYBIFAKTURA', ['banka']);
$labeler->createNew('NEIDENTIFIKOVANO', ['banka']);
$labeler->addStatusMessage(_('Adding labels done'), 'debug');
