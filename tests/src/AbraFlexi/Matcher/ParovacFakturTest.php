<?php

namespace Test\AbraFlexi\Matcher;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2018-04-17 at 19:11:15.
 */
class ParovacFakturTest extends \Test\Ease\SandTest
{

    /**
     * @var ParovacFaktur
     */
    protected $object;

    /**
     * Prepare Testing Invoice
     * 
     * @param array $initialData
     * 
     * @return \AbraFlexi\FakturaVydana
     */
    public function makeInvoice($initialData = [])
    {
        return \Test\AbraFlexi\FakturaVydanaTest::makeTestInvoice($initialData,
                        1, 'vydana');
    }

    /**
     * Prepare testing payment
     * 
     * @param array $initialData
     * 
     * @return \AbraFlexi\Banka
     */
    public function makePayment($initialData = [])
    {
        return \Test\AbraFlexi\BankaTest::makeTestPayment($initialData, 1);
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new \AbraFlexi\Matcher\ParovacFaktur(["LABEL_PREPLATEK" => 'PREPLATEK', "LABEL_CHYBIFAKTURA" => 'CHYBIFAKTURA',
            "LABEL_NEIDENTIFIKOVANO" => 'NEIDENTIFIKOVANO']);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        
    }

    public function testGetDocumentTypes()
    {
        $this->assertArrayHasKey('FAKTURA', $this->object->getDocumentTypes());
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::setStartDay
     */
    public function testSetStartDay()
    {
        $this->object->setStartDay(1);
        $this->assertEquals(1, $this->object->daysBack);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::getPaymentsToProcess
     */
    public function testGetPaymentsToProcess()
    {
        $this->object->getPaymentsToProcess(0); //Empty Restult
        $payment = $this->makePayment(['popis' => 'Test GetPaymentsToProcess AbraFlexi-Bricks']);
        $paymentsToProcess = $this->object->getPaymentsToProcess(1);
        $this->assertArrayHasKey($payment->getRecordID(), $paymentsToProcess,
                'Can\'t find Payment');
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::getInvoicesToProcess
     */
    public function testGetInvoicesToProcess()
    {
        $invoice = $this->makeInvoice(['popis' => 'Test InvoicesToProcess AbraFlexi-Bricks']);
        $invoicesToProcess = $this->object->getInvoicesToProcess(1);
        $this->assertArrayHasKey($invoice->getRecordID(), $invoicesToProcess,
                'Can\'t find Invoice');
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::inInvoicesMatchingByBank
     */
    public function testInInvoicesMatchingByBank()
    {
        $faktura = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('FAKTURA'),
            'popis' => 'InvoicesMatchingByBank AbraFlexi-Bricks Test']);
        $zaloha = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('ZÁLOHA'),
            'popis' => 'InvoicesMatchingByBank AbraFlexi-Bricks Test']);
        $dobropis = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('DOBROPIS'),
            'popis' => 'InvoicesMatchingByBank AbraFlexi-Bricks Test']);
        $this->object->setStartDay(-1);
        $this->object->outInvoicesMatchingByBank();
        $this->object->setStartDay(1);
        $paymentChecker = new \AbraFlexi\Banka(null,
                ['detail' => 'custom:sparovano']);
        $paymentsToCheck = $this->object->getPaymentsToProcess(1);
        $this->object->outInvoicesMatchingByBank();
        foreach ($paymentsToCheck as $paymentID => $paymentData) {
            $paymentChecker->loadFromAbraFlexi(\AbraFlexi\RO::code($paymentData['kod']));
            $this->assertEquals('true',
                    $paymentChecker->getDataValue('sparovano'), 'Matching error');
        }
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::invoicesMatchingByInvoices
     */
    public function testInvoicesMatchingByInvoices()
    {

        $faktura = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('FAKTURA'),
            'popis' => 'InvoicesMatchingByInvoices AbraFlexi-Bricks Test']);
        $zaloha = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('ZÁLOHA'),
            'popis' => 'InvoicesMatchingByInvoices AbraFlexi-Bricks Test']);
        $dobropis = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('DOBROPIS'),
            'popis' => 'InvoicesMatchingByInvoices AbraFlexi-Bricks Test']);
        $invoiceChecker = new \AbraFlexi\FakturaVydana(null,
                ['detail' => 'custom:sparovano']);
        $invoicesToCheck = $this->object->getPaymentsToProcess(1);
        if (empty($invoicesToCheck)) {
            $this->markTestSkipped(_('No invoices to Process. Please run '));
        } else {
            $this->object->invoicesMatchingByInvoices();
            foreach ($invoicesToCheck as $paymentID => $paymentData) {
                $invoiceChecker->loadFromAbraFlexi($paymentID);
                $this->assertEquals('true',
                        $invoiceChecker->getDataValue('sparovano'), 'Matching error');
            }
        }
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::settleCreditNote
     */
    public function testSettleCreditNote()
    {
        $dobropis = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('ODD'),
            'popis' => 'Test SettleCreditNote AbraFlexi-Bricks']);
        $payment = $this->makePayment();
        $this->assertEquals(1,
                $this->object->settleCreditNote($dobropis, $payment));
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::settleProforma
     */
    public function testSettleProforma()
    {
        $zaloha = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('ZÁLOHA'),
            'popis' => 'Test SettleProforma AbraFlexi-Bricks']);
        $payment = $this->makePayment();
        $this->object->settleProforma($zaloha, $payment->getData());
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::settleInvoice
     */
    public function testSettleInvoice()
    {
        $invoice = $this->makeInvoice(['typDokl' => \AbraFlexi\RO::code('FAKTURA'),
            'popis' => 'Test SettleInvoice AbraFlexi-Bricks PHPUnit']);
        $payment = $this->makePayment();
        $this->assertEquals(1, $this->object->settleInvoice($invoice, $payment));
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::invoiceCopy
     */
    public function testInvoiceCopy()
    {
        $invoice = $this->makeInvoice(['popis' => 'Test InvoiceCopy AbraFlexi-Bricks']);
        $this->object->invoiceCopy($invoice, ['poznam' => 'Copied By unitTest']);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::hotfixDeductionOfAdvances
     */
    public function testHotfixDeductionOfAdvances()
    {
        $varSym = \Ease\Functions::randomNumber(1111, 9999);
        $price = \Ease\Functions::randomNumber(11, 99);
        $invoice = $this->makeInvoice(['typDokl' => 'code:ZDD', 'varSym' => $varSym,
            'sumZklZakl' => $price]);
        $payment = $this->makePayment(['varSym' => $varSym, 'sumZklZakl' => $price]);
        $this->object->hotfixDeductionOfAdvances($invoice, $payment);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::findInvoices
     */
    public function testFindInvoices()
    {
        $this->makeInvoice(['varSym' => '123', 'poznam' => 'Test FindInvoices AbraFlexi-Bricks']);
        $this->makeInvoice(['specSym' => '356', 'poznam' => 'Test FindInvoices AbraFlexi-Bricks']);
        $this->object->findInvoices(['id' => '1', 'varSym' => '123']);
        $this->object->findInvoices(['id' => '2', 'specSym' => '356']);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::findPayments
     */
    public function testFindPayments()
    {
        $this->object->findPayments(['varSym' => '123']);
        $this->object->findPayments(['specSym' => '356']);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::findInvoice
     */
    public function testFindInvoice()
    {
        $found = $this->object->findInvoice(['varSym' => 123]);
        $found = $this->object->findInvoice(['specSym' => 456]);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::findPayment
     */
    public function testFindPayment()
    {
        $this->object->findPayment(['varSym' => 123]);
        $this->object->findPayment(['specSym' => 456]);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::findBestPayment
     */
    public function testFindBestPayment()
    {
        $varSym = \Ease\Functions::randomNumber(111111, 999999);
        $specSym = \Ease\Functions::randomNumber(1111, 9999);
        $price = \Ease\Functions::randomNumber(111, 999);
        $invoiceSs = $this->makeInvoice(['varSym' => $varSym, 'specSym' => $specSym,
            'sumCelkem' => $price]);
        $paymentSs = $this->makePayment(['specSym' => $specSym, 'sumCelkem' => $price]);
        $bestSSPayment = $this->object->findBestPayment([$paymentSs->getData()],
                $invoiceSs);
        $this->assertTrue(is_object($bestSSPayment));
        $invoiceVs = $this->makeInvoice(['varSym' => $varSym]);
        $paymentVs = $this->makePayment(['varSym' => $varSym]);
        $bestVSPayment = $this->object->findBestPayment([$paymentVs->getData()],
                $invoiceVs);
    }

    /**
     * @covers AbraFlexi\Bricks\ParovacFaktur::apiUrlToLink
     */
    public function testApiUrlToLink()
    {
        $this->assertEquals('<a href="' . constant('ABRAFLEXI_URL') . '/c/' . constant('ABRAFLEXI_COMPANY') . '/banka.json" target="_blank" rel="nofollow">https://demo.abraflexi.eu:5434/c/demo/banka.json</a>',
                $this->object->apiUrlToLink($this->object->banker->apiURL));
    }
}