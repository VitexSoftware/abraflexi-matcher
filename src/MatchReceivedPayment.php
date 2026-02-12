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

use Ease\Locale;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexi MatchReceivedPayment');

require_once '../vendor/autoload.php';

$options = getopt('o::e::', ['output::environment::'], $optind);

$envFile = \array_key_exists(1, $argv) && file_exists($argv[1]) ? $argv[1] : '../.env'; // Default fallback

if (isset($options['e'])) {
    $envFile = $options['e'];
} elseif (isset($options['environment'])) {
    $envFile = $options['environment'];
} elseif (isset($argv[$optind]) && file_exists($argv[$optind])) {
    $envFile = $argv[$optind];
    ++$optind;
}

\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], $envFile);
new Locale(Shared::cfg('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

$docId = $argv[$optind] ?? \Ease\Shared::cfg('DOCUMENTID');

$invoiceSteamer = new \AbraFlexi\Matcher\ParovacFaktur();

$report = ['matched' => [], 'unmatched' => []];
$exitcode = 0;
$destination = \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');

if (isset($options['o'])) {
    $destination = $options['o'];
} elseif (isset($options['output'])) {
    $destination = $options['output'];
}

if ($docId) {
    if (Shared::cfg('APP_DEBUG')) {
        $invoiceSteamer->banker->logBanner(Shared::appName());
        $invoiceSteamer->addStatusMessage(sprintf(_('Incoming bank %s matching begin'), $docId), 'debug');
    }

    if ($invoiceSteamer->banker->recordExists($docId)) {
        $invoiceSteamer->banker->loadFromAbraFlexi($docId);
    } else {
        $invoiceSteamer->addStatusMessage(sprintf(_('Payment %s not found'), $docId), 'error');

        exit(1);
    }

    if (!$invoiceSteamer->banker->getDataValue('id')) {
        $invoiceSteamer->addStatusMessage(sprintf(_('Payment %s not found'), $docId), 'error');

        exit(1);
    }

    $paymentData = $invoiceSteamer->banker->getData();

    // Use robust finding logic from ParovacFaktur::issuedInvoicesMatchingByBank
    $invoices = $invoiceSteamer->findInvoices($paymentData);
    $foundMatch = false;

    if (\count($invoices) && \count(current($invoices))) {
        foreach ($invoices as $invoiceID => $invoiceData) {
            if ($invoiceSteamer->issuedInvoiceMatchByBank($invoiceData, $invoiceSteamer->banker)) {
                $report['matched'][] = $invoiceData['kod'] ?? $invoiceID;
                $foundMatch = true;

                break;
            }
        }
    }

    if (!$foundMatch) {
        $report['unmatched'][] = $invoiceSteamer->banker->getDataValue('kod') ?? $docId;

        // Try to match partialy or what ever
        if ($invoiceSteamer->matchingByBank($invoiceSteamer->banker)) {
            $report['matched'][] = $invoiceData['kod'] ?? $invoiceID;
            $foundMatch = true;
            // Remove from unmatched
            array_pop($report['unmatched']);
        }
    }

    $invoiceSteamer->addStatusMessage(sprintf(_('Payment %s matching'), $docId), $foundMatch ? 'success' : 'warning');

    if (Shared::cfg('APP_DEBUG')) {
        $invoiceSteamer->addStatusMessage(_('Incomin bank matching done'), 'debug');
    }
} else {
    $invoiceSteamer->addStatusMessage(_('No DOCUMENTID provided. aborting'), 'error');
    $exitcode = 1;
}

// Build the report according to the schema
$finalReport = [
    'producer' => APP_NAME,
    'status' => $exitcode === 0 ? 'success' : 'error',
    'timestamp' => (new DateTime())->format(DateTime::ATOM),
    'message' => _('Payment matching completed'),
    'artifacts' => [
        'result' => [$destination],
    ],
    'metrics' => [
        'matched' => \count($report['matched'] ?? []),
        'unmatched' => \count($report['unmatched'] ?? []),
    ],
];

$written = file_put_contents($destination, json_encode($finalReport, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$invoiceSteamer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
