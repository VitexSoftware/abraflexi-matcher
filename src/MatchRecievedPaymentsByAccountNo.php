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

\define('APP_NAME', 'AbraFlexi MatchRecievedPaymentsByAccountNo');

require_once '../vendor/autoload.php';
$shared = Shared::singleton();

$options = getopt('o::e::', ['output::environment::']);
Shared::init(
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MATCHER_DAYS_BACK'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : Shared::cfg('RESULT_FILE', 'match_accountno_report.json'));

$invoiceSteamer = new OutgoingInvoice($shared->configuration);
$invoiceSteamer->setStartDay((int) Shared::cfg('MATCHER_DAYS_BACK', 365));

if (Shared::cfg('APP_DEBUG')) {
    $invoiceSteamer->banker->logBanner(Shared::appName());
}

if ($shared->getConfigValue('MATCHER_PULL_BANK') === true) {
    $invoiceSteamer->addStatusMessage(_('pull account statements'), 'debug');

    if (!$invoiceSteamer->banker->stahnoutVypisyOnline()) {
        $invoiceSteamer->addStatusMessage(_('Bank Offline!'), 'error');
    }
}

$invoiceSteamer->addStatusMessage(_('Bank account number based matching begin'), 'debug');
$result = $invoiceSteamer->issuedInvoicesMatchingByAccountNo();
$invoiceSteamer->addStatusMessage(_('Bank account number based matching done'), 'debug');

$exitcode = 0;

// Build the report according to the schema
$report = [
    'producer' => APP_NAME,
    'status' => $exitcode === 0 ? 'success' : 'error',
    'timestamp' => (new DateTime())->format(DateTime::ATOM),
    'message' => _('Bank account number based matching completed'),
    'artifacts' => [
        'result' => [$destination],
    ],
    'metrics' => [
        'matched' => \count($result['matched'] ?? []),
        'unmatched' => \count($result['unmatched'] ?? []),
        'multiple' => \count($result['multiple'] ?? []),
        'overpaid' => \count($result['overpaid'] ?? []),
        'underpaid' => \count($result['underpaid'] ?? []),
    ],
];

$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
