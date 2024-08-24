<?php

use AbraFlexi\Stitek;
use Ease\Shared;

/**
 * php-abraflexi-matecher
 *
 * @copyright (c) 2018-2024, Vítězslav Dvořák
 */

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], array_key_exists(1, $argv) ? $argv[1] : (file_exists('../.env') ? '../.env' : null));
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$labeler = new Stitek();
if (Shared::cfg('APP_DEBUG')) {
    $labeler->logBanner();
}
$labeler->addStatusMessage(_('checking labels'), 'debug');
$updateCfg = false;
foreach (['PREPLATEK', 'CHYBIFAKTURA', 'NEIDENTIFIKOVANO'] as $label) {
    if (Shared::cfg('MATCHER_LABEL_' . $label)) {
        $labeler->addStatusMessage(sprintf(
            _('Cannot find MATCHER_LABEL_%s in config file ../.env. Using default value: %s'),
            $label,
            $label
        ), 'warning');
        $updateCfg = true;
    }
    if ($labeler->recordExists(['kod' => Shared::cfg('MATCHER_LABEL_' . $label, $label)]) === false) {
        $labeler->createNew(Shared::cfg('MATCHER_LABEL_' . $label, $label), ['banka']);
        $labeler->addStatusMessage(sprintf(
            _('MATCHER_LABEL_%s: %s was created in AbraFlexi'),
            $label,
            Shared::cfg('MATCHER_LABEL_' . $label)
        ), 'success');
    } else {
        $labeler->addStatusMessage(sprintf(
            _('MATCHER_LABEL_%s: %s exists in AbraFlexi'),
            $label,
            Shared::cfg('MATCHER_LABEL_' . $label)
        ));
    }
}

$labeler->addStatusMessage(_('labels check done'), 'debug');
