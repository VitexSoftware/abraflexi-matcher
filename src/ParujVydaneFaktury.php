<?php

/**
 * abraflexi-matcher
 *
 * @copyright (c) 2018-2024, Vítězslav Dvořák
 */

use AbraFlexi\Matcher\OutgoingInvoice;
use Ease\Locale;
use Ease\Shared;

define('APP_NAME', 'AbraFlexi ParujVydaneFaktury');
require_once '../vendor/autoload.php';
$shared = Shared::singleton();
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], array_key_exists(1, $argv) ? $argv[1] : '../.env');
new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$invoiceSteamer = new OutgoingInvoice($shared->configuration);
$invoiceSteamer->setStartDay(30);

if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner(Shared::appName());
}

if ($shared->getConfigValue('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'), 'debug');
$invoiceSteamer->issuedInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'), 'debug');
