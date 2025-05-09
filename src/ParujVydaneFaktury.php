<?php

declare(strict_types=1);

/**
 * This file is part of the  AbraFlexi Matcher package.
 *
 * (c) Vítězslav Dvořák <https://vitexsoftware.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use AbraFlexi\Matcher\OutgoingInvoice;
use Ease\Locale;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi ParujVydaneFaktury');

require_once '../vendor/autoload.php';
$shared = Shared::singleton();
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$invoiceSteamer = new OutgoingInvoice($shared->configuration);
$invoiceSteamer->setStartDay(30);

if (Shared::cfg('APP_DEBUG')) {
    $startDay = $invoiceSteamer->getStartDay();
    $endDate = (new DateTime())->format('Y-m-d');
    $beginDate = (new DateTime())->modify("-{$startDay} days")->format('Y-m-d');

    $rangeInfo = sprintf(
        _('Processing incoming payments within the range: Begin %s  End %s (%d days)'),
        $beginDate,
        $endDate,
        $startDay,
    );
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
