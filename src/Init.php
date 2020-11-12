<?php

use Ease\Shared;

/**
 * php-flexibee-matecher
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
$autoloader = require_once '../vendor/autoload.php';
$shared = new Shared();
if (file_exists('../client.json')) {
    $shared->loadConfig('../client.json', true);
}
$shared->loadConfig('../matcher.json', true);

$labeler = new AbraFlexi\Stitek();
$labeler->logBanner('EasePHP Bricks v? ' . constant('EASE_APPNAME'));
$labeler->addStatusMessage(_('checking labels'), 'debug');

$updateCfg = false;
foreach (['PREPLATEK', 'CHYBIFAKTURA', 'NEIDENTIFIKOVANO'] as $label) {
    if (is_null($shared->getConfigValue('LABEL_' . $label))) {
        $shared->setConfigValue('LABEL_' . $label, $label);
        $labeler->addStatusMessage(sprintf(_('Cannot find LABEL_%s in config file matcher.json. Using default value: %s'),
                        $label, $label), 'warning');
        $updateCfg = true;
    }
    if ($labeler->recordExists(['kod' => $shared->getConfigValue('LABEL_' . $label)]) === false) {
        $labeler->createNew($shared->getConfigValue('LABEL_' . $label), ['banka']);
        $labeler->addStatusMessage(sprintf(_('LABEL_%s: %s was created in FlexiBee'),
                        $label, $shared->getConfigValue('LABEL_' . $label)), 'success');
    } else {
        $labeler->addStatusMessage(sprintf(_('LABEL_%s: %s exists in FlexiBee'),
                        $label, $shared->getConfigValue('LABEL_' . $label)));
    }
}

if ($updateCfg === true) {
    foreach ([
"EASE_APPNAME", "EASE_MAILTO", "EASE_LOGGER", "LOCALIZE", "PULL_BANK",
 "DAYS_BACK", "LABEL_PREPLATEK", "LABEL_CHYBIFAKTURA", "LABEL_NEIDENTIFIKOVANO"] as $cfg) {
        $cfg2save[$cfg] = $shared->getConfigValue($cfg);
    }
    if (file_put_contents('../matcher.json',
                    json_encode($cfg2save, JSON_PRETTY_PRINT))) {
        $labeler->addStatusMessage(_('../matcher.json was updated'), 'success');
    } else {
        $labeler->addStatusMessage(_('../matcher.json was not updated', 'error'),
                'success');
    }
}
$labeler->addStatusMessage(_('labels check done'), 'debug');
