<?php

/**
 * php-abraflexi-matcher
 *
 * @copyright (c) 2022-2023, Vítězslav Dvořák
 */

use AbraFlexi\Matcher\OutcomingInvoice;
use Ease\Locale;
use Ease\Shared;

define('APP_NAME', 'AbraFlexi ParujPrijatouPokladnu');
require_once '../vendor/autoload.php';

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], array_key_exists(1, $argv) ? $argv[1] : '../.env');

new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$invoiceSteamer = new OutcomingInvoice();
if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner(Shared::appName());
}

if (Shared::cfg('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');
    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching begin'), 'debug');
$invoiceSteamer->outInvoicesMatchingByBank();
$invoiceSteamer->addStatusMessage(_('Outgoing Invoice matching done'), 'debug');
