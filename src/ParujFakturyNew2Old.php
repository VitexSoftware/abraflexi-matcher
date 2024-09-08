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
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi ParujFakturyNewToOld');

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$odden = 0;
$date1 = new DateTime();
$date2 = new DateTime();
$daysOfYear = \AbraFlexi\FakturaVydana::overdueDays(new \DateTime(date('Y').'-01-01'));
$date2->modify('-'.\Ease\Shared::cfg('MATCHER_DAYS_BACK', $daysOfYear).' days');
$doden = $date2->diff($date1)->format('%a');
$invoiceSteamer = new OutgoingInvoice();

if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner();
}

if (Shared::cfg('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'));

    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage('Banka Offline!', 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Matching program started'), 'debug');

while ($odden < $doden) {
    $invoiceSteamer->setStartDay($odden++);
    $invoiceSteamer->issuedInvoicesMatchingByBank();
}

$invoiceSteamer->addStatusMessage(_('Matching finished'));
