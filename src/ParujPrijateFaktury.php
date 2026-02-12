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

use AbraFlexi\Matcher\IncomingInvoice;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi ParujPrijateFaktury');

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
$invoiceSteamer = new IncomingInvoice();

if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner();
}

if (Shared::cfg('MATCHER_PULL_BANK', false)) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');

    try {
        if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
            $invoiceSteamer->addStatusMessage(_('Bank Offline!'), 'error');
        }
    } catch (\Exception $exc) {
        $invoiceSteamer->addStatusMessage($exc->getMessage(), 'error');
    }
}

$begin = new DateTime();
$daterange = new DatePeriod(
    $begin->modify('-'.Shared::cfg('MATCHER_DAYS_BACK', 365).' days'),
    new DateInterval('P1D'),
    new DateTime(),
);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching begin'), 'debug');
$result = $invoiceSteamer->inInvoicesMatchingByBank($daterange);
$invoiceSteamer->addStatusMessage(_('Incoming Invoice matching done'), 'debug');

$report['matched'] = $result['matched'];
$report['unmatched'] = $result['unmatched'];
$exitcode = 0;

// Build the report according to the schema
$finalReport = [
    'producer' => APP_NAME,
    'status' => $exitcode === 0 ? 'success' : 'error',
    'timestamp' => (new DateTime())->format(DateTime::ATOM),
    'message' => _('Incoming invoice matching completed'),
    'artifacts' => [
        'result' => [$destination],
    ],
    'metrics' => [
        'matched' => \count($report['matched'] ?? []),
        'unmatched' => \count($report['unmatched'] ?? []),
    ],
];

$destination = \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');
$written = file_put_contents($destination, json_encode($finalReport, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
