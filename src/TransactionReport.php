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

namespace SpojeNet\FioApi;

require_once '../vendor/autoload.php';

\define('APP_NAME', 'AbraFlexi Transaction Reporter');

$options = getopt('o::e::', ['output::environment::']);

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ABRAFLEXI_BANK'], \array_key_exists('environment', $options) ? $options['environment'] : '../.env');
$localer = new \Ease\Locale('cs_CZ', '../i18n', 'abraflexi-reminder');

$destination = \array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');
$reportFile = \Ease\Shared::cfg('RESULT_FILE', 'transaction-report.json');
$exitCode = 0;
$status = 'success';
$message = '';
$payments = [];

try {
    $banker = new \AbraFlexi\Matcher\BankProbe();

    if (strtolower(\Ease\Shared::cfg('APP_DEBUG', 'false')) === 'true') {
        $banker->logBanner(\Ease\Shared::appName().' v'.\Ease\Shared::appVersion());
    }

    $banker->setScope(\Ease\Shared::cfg('REPORT_SCOPE', 'yesterday'));

    $payments = [
        'source' => \Ease\Logger\Message::getCallerName($banker),
        'account' => $banker->accuntNumber(),
        'in' => [],
        'out' => [],
        'in_total' => 0,
        'out_total' => 0,
        'in_sum_total' => 0,
        'out_sum_total' => 0,
        'iban' => $banker->getIban(),
        'from' => $banker->getSince()->format('Y-m-d'),
        'to' => $banker->getUntil()->format('Y-m-d'),
    ];

    $transactionList = $banker->transactionsFromTo();

    foreach ($transactionList as $transaction) {
        $direction = ($transaction['typPohybuK'] === 'typPohybu.prijem');
        $payments[$direction ? 'in' : 'out'][$transaction['id']] = $transaction['sumCelkem'];
        $payments[$direction ? 'in_sum_total' : 'out_sum_total'] += $transaction['sumCelkem'];
        ++$payments[$direction ? 'in_total' : 'out_total'];
    }

    $written = file_put_contents($destination, json_encode($payments, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
    
    if ($written) {
        $banker->addStatusMessage(sprintf(_('Saving result to %s'), $destination), 'success');
        $message = sprintf('Successfully processed %d transactions (in: %d, out: %d)', 
            $payments['in_total'] + $payments['out_total'], 
            $payments['in_total'], 
            $payments['out_total']);
    } else {
        $banker->addStatusMessage(sprintf(_('Failed to save result to %s'), $destination), 'error');
        $status = 'error';
        $message = 'Failed to write output file';
        $exitCode = 3;
    }
} catch (\AbraFlexi\Exception $e) {
    $status = 'error';
    $message = 'AbraFlexi connection error: ' . $e->getMessage();
    $exitCode = 2;
    if (isset($banker)) {
        $banker->addStatusMessage($message, 'error');
    }
} catch (\Exception $e) {
    $status = 'error';
    $message = 'Unexpected error: ' . $e->getMessage();
    $exitCode = 1;
    if (isset($banker)) {
        $banker->addStatusMessage($message, 'error');
    }
}

// Generate MultiFlexi report
$report = [
    'producer' => APP_NAME,
    'status' => $status,
    'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
    'message' => $message,
    'metrics' => [
        'transactions_total' => ($payments['in_total'] ?? 0) + ($payments['out_total'] ?? 0),
        'transactions_in' => $payments['in_total'] ?? 0,
        'transactions_out' => $payments['out_total'] ?? 0,
        'amount_in' => $payments['in_sum_total'] ?? 0,
        'amount_out' => $payments['out_sum_total'] ?? 0,
    ],
];

if ($exitCode === 0 && !empty($reportFile)) {
    $report['artifacts'] = [
        'transaction-report' => [$reportFile]
    ];
}

// Write MultiFlexi report
$reportPath = dirname($destination) . '/transaction-report.multiflexi.report.json';
if ($reportPath === './transaction-report.multiflexi.report.json') {
    $reportPath = 'transaction-report.multiflexi.report.json';
}
file_put_contents($reportPath, json_encode($report, \JSON_PRETTY_PRINT));

exit($exitCode);
