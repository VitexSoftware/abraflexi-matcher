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
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$odden = 0;
$date1 = new DateTime();
$date2 = new DateTime();
$daysOfYear = \AbraFlexi\FakturaVydana::overdueDays(new \AbraFlexi\Date(date('Y').'-01-01'));
$date2->modify('-'.Shared::cfg('MATCHER_DAYS_BACK', $daysOfYear).' days');
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

$matched = [];
$unmatched = [];

while ($odden < $doden) {
    $invoiceSteamer->setStartDay($odden++);
    $result = $invoiceSteamer->issuedInvoicesMatchingByBank();

    if (isset($result['matched'])) {
        $matched = array_merge($matched, $result['matched']);
    }

    if (isset($result['unmatched'])) {
        $unmatched = array_merge($unmatched, $result['unmatched']);
    }
}

$invoiceSteamer->addStatusMessage(_('Matching finished'));

$report = [
    'matched' => $matched,
    'unmatched' => $unmatched,
];
$exitcode = 0;
$destination = \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
