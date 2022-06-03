<?php

use AbraFlexi\Stitek;
use Ease\Functions;
use Ease\Shared;

/**
 * php-abraflexi-matecher
 * 
 * @copyright (c) 2018-2021, Vítězslav Dvořák
 */
$autoloader = require_once '../vendor/autoload.php';
$shared = Shared::singleton();
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$labeler = new Stitek();
$labeler->logBanner(Functions::cfg('APP_NAME'));
$labeler->addStatusMessage(_('checking labels'), 'debug');

$updateCfg = false;
foreach (['PREPLATEK', 'CHYBIFAKTURA', 'NEIDENTIFIKOVANO'] as $label) {
    if (is_null($shared->getConfigValue('MATCHER_LABEL_' . $label))) {
        $shared->setConfigValue('LABEL_' . $label, $label);
        $labeler->addStatusMessage(sprintf(_('Cannot find MATCHER_LABEL_%s in config file ../.env. Using default value: %s'),
                        $label, $label), 'warning');
        $updateCfg = true;
    }
    if ($labeler->recordExists(['kod' => $shared->getConfigValue('MATCHER_LABEL_' . $label)]) === false) {
        $labeler->createNew($shared->getConfigValue('MATCHER_LABEL_' . $label), ['banka']);
        $labeler->addStatusMessage(sprintf(_('MATCHER_LABEL_%s: %s was created in AbraFlexi'),
                        $label, $shared->getConfigValue('MATCHER_LABEL_' . $label)), 'success');
    } else {
        $labeler->addStatusMessage(sprintf(_('MATCHER_LABEL_%s: %s exists in AbraFlexi'),
                        $label, $shared->getConfigValue('MATCHER_LABEL_' . $label)));
    }
}

/**
 * Environment valued to configuration string
 * 
 * @param array $config
 * 
 * @return string
 */
function cfg2env($config) {
    $env = [];
    foreach ($config as $key => $value) {
        $env[] = $key . '=' . $value . "\n";
    }
    return implode('',$env);
}

if ($updateCfg === true) {
    foreach ([
        "APP_NAME", 
        "EASE_MAILTO", 
        "EASE_LOGGER", 
        "MATCHER_LOCALIZE", 
        "MATCHER_PULL_BANK", 
        "MATCHER_DAYS_BACK", 
        "MATCHER_LABEL_PREPLATEK", 
        "MATCHER_LABEL_CHYBIFAKTURA", 
        "MATCHER_LABEL_NEIDENTIFIKOVANO"] as $cfg) {
        $cfg2save[$cfg] = $shared->getConfigValue($cfg);
    }
    if (file_put_contents('../.env', cfg2env($cfg2save))) {
        $labeler->addStatusMessage(_('../.env was updated'), 'success');
    } else {
        $labeler->addStatusMessage(_('../env was not updated'), 'error');
    }
}
$labeler->addStatusMessage(_('labels check done'), 'debug');
