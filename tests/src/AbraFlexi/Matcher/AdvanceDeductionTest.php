<?php

declare(strict_types=1);

/**
 * This file is part of the Invoice Matcher for AbraFlexi package
 *
 * https://github.com/VitexSoftware/abraflexi-matcher
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\AbraFlexi\Matcher;

use AbraFlexi\Matcher\AdvanceDeduction;
use PHPUnit\Framework\TestCase;

/**
 * A paid advance is deducted from a tax document, once.
 */
class AdvanceDeductionTest extends TestCase
{
    public function testOnlyAnAdvanceInvoiceIsAnAdvance(): void
    {
        $this->assertTrue(AdvanceDeduction::isAdvanceKind('typDokladu.zalohFaktura'));
        $this->assertFalse(AdvanceDeduction::isAdvanceKind('typDokladu.faktura'));
        $this->assertFalse(AdvanceDeduction::isAdvanceKind('typDokladu.zdd'));
        $this->assertFalse(AdvanceDeduction::isAdvanceKind(null));
    }

    public function testAdvanceLinkedOnlyToPaymentsIsNotDeducted(): void
    {
        $links = [
            ['a' => 'code:VF2-0002/2026', 'a@ref' => '/c/vitex_software/faktura-vydana/2242.json', 'b' => 'code:B+0001/2026', 'b@ref' => '/c/vitex_software/banka/62.json'],
        ];

        $this->assertFalse(AdvanceDeduction::isDeducted($links));
        $this->assertFalse(AdvanceDeduction::isDeducted([]));
    }

    public function testAdvanceLinkedToTheDeductionItemOfAnotherDocumentIsDeducted(): void
    {
        // what AbraFlexi returns: the item "Odpočet zálohy" (a) of the tax document and the advance (b)
        $links = [
            ['a' => 'code:VF2-0002/2026', 'a@ref' => '/c/vitex_software/faktura-vydana/2242.json', 'b' => 'code:B+0001/2026', 'b@ref' => '/c/vitex_software/banka/62.json'],
            ['a' => '714', 'a@ref' => '/c/vitex_software/faktura-vydana-polozka/714.json', 'b' => 'code:VF2-0002/2026', 'b@ref' => '/c/vitex_software/faktura-vydana/2242.json'],
        ];

        $this->assertTrue(AdvanceDeduction::isDeducted($links));
    }

    public function testRelationObjectsOfTheLibraryAreUnderstood(): void
    {
        $relation = new \AbraFlexi\Relation('714', 'faktura-vydana-polozka');
        $bank = new \AbraFlexi\Relation('code:B+0001/2026', 'banka');

        $this->assertSame('faktura-vydana-polozka', AdvanceDeduction::targetOf($relation));
        $this->assertTrue(AdvanceDeduction::isDeducted([['a' => $relation, 'b' => 'code:X']]));
        $this->assertFalse(AdvanceDeduction::isDeducted([['a' => $bank, 'b' => 'code:X']]));
    }

    public function testTargetOfAReference(): void
    {
        $this->assertSame('banka', AdvanceDeduction::targetOf('code:B+0001/2026', '/c/demo/banka/62.json'));
        $this->assertSame('faktura-vydana-polozka', AdvanceDeduction::targetOf('714', '/c/demo_de/faktura-vydana-polozka/714.json'));
        $this->assertNull(AdvanceDeduction::targetOf('714'));
        $this->assertNull(AdvanceDeduction::targetOf(null, null));
    }

    public function testDeductionBranch(): void
    {
        $this->assertSame(
            ['odpocet' => ['castkaMen' => 121.0, 'doklad' => 'code:VF2-0001/2026']],
            AdvanceDeduction::deduction('VF2-0001/2026', 121.0),
        );
        $this->assertSame('code:VF2-0001/2026', AdvanceDeduction::deduction('code:VF2-0001/2026', 1.0)['odpocet']['doklad']);
    }

    public function testTaxDocumentTakesOverTheCustomerAndTheItemsOfTheAdvance(): void
    {
        $advance = [
            'id' => 2242,
            'kod' => 'VF2-0002/2026',
            'firma' => 'code:POKUS',
            'varSym' => '200022026',
            'mena' => 'code:CZK',
            'popis' => 'Prodej služeb',
            'sumCelkem' => 32.0,
            'zbyvaUhradit' => 0.0,
            'stavUhrK' => 'stavUhr.uhrazeno',
            'typDokl' => 'code:ZÁLOHA',
            'stitky' => 'SYSTEM, API',
            'polozkyDokladu' => [
                [
                    'id' => 7671,
                    'kod' => 'MULTIFLEXI',
                    'nazev' => 'MultiFlexi (SaaS)',
                    'cenik' => 'code:MULTIFLEXI',
                    'mnozMj' => 1.0,
                    'mj' => 'code:MESIC',
                    'cenaMj' => 32.0,
                    'typPolozkyK' => 'typPolozky.katalog',
                    'typCenyDphK' => 'typCeny.bezDph',
                    'typSzbDphK' => '',
                    'sklad' => '',
                    'doklFak' => 2242,
                    'ucetni' => true,
                ],
            ],
        ];

        $data = AdvanceDeduction::taxDocument($advance, 'FAKTURA', '2026-11-16');

        $this->assertSame('code:FAKTURA', $data['typDokl']);
        $this->assertSame('code:POKUS', $data['firma']);
        $this->assertSame('200022026', $data['varSym']);
        $this->assertSame('2026-11-16', $data['datVyst']);
        $this->assertSame('stavMail.neodesilat', $data['stavMailK']);
        // what the advance is, not what it was paid with
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('kod', $data);
        $this->assertArrayNotHasKey('stavUhrK', $data);
        $this->assertArrayNotHasKey('stitky', $data);
        $this->assertCount(1, $data['polozkyDokladu']);

        $item = $data['polozkyDokladu'][0];
        $this->assertSame('MultiFlexi (SaaS)', $item['nazev']);
        $this->assertSame('code:MULTIFLEXI', $item['cenik']);
        $this->assertSame(32.0, $item['cenaMj']);
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('doklFak', $item);
        $this->assertArrayNotHasKey('ucetni', $item);
        $this->assertArrayNotHasKey('sklad', $item, 'an empty value is not sent');
    }

    public function testDeductionItemOfTheAdvanceIsNotCopied(): void
    {
        $advance = ['firma' => 'code:X', 'polozkyDokladu' => [
            ['nazev' => 'Služba', 'cenaMj' => 100.0, 'mnozMj' => 1.0, 'typPolozkyK' => 'typPolozky.obecny'],
            ['nazev' => 'Odpočet zálohy VF2-0009/2026', 'cenaMj' => 0.0, 'typPolozkyK' => 'typPolozky.odpocetZalohy'],
        ]];

        $items = AdvanceDeduction::taxDocument($advance, 'FAKTURA', '2026-11-16')['polozkyDokladu'];

        $this->assertCount(1, $items);
        $this->assertSame('Služba', $items[0]['nazev']);
    }

    public function testItemsReadApartAreUsedWhenTheAdvanceDoesNotCarryThem(): void
    {
        $data = AdvanceDeduction::taxDocument(['firma' => 'code:X'], 'code:FAKTURA', '2026-11-16', [['nazev' => 'A', 'cenaMj' => 5.0, 'mnozMj' => 2.0]]);

        $this->assertSame('code:FAKTURA', $data['typDokl']);
        $this->assertSame('A', $data['polozkyDokladu'][0]['nazev']);
    }
}
