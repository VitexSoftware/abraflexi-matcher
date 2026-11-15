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

namespace AbraFlexi\Matcher;

/**
 * Deduction of a paid advance (proforma) invoice from a tax document.
 *
 * An advance invoice ("Záloha - není daňový doklad") that is paid by an incoming payment is followed by
 * a tax document (an ordinary invoice) for the same goods and services, and the paid advance is deducted
 * from it: the tax document is settled by the deduction, nothing more is to pay. The customer holds both:
 * the advance paid by the payment and the tax document that the advance was deducted from.
 *
 * AbraFlexi does the deduction itself when the tax document is created with the "odpocty-zaloh" branch
 * (https://podpora.flexibee.eu/cs/articles/4729457-odpocet-zaloh-a-zdd-api): it adds the item
 * "Odpočet zálohy" and pays the document with it. This class prepares the data, it decides nothing
 * about the money.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
final class AdvanceDeduction
{
    /**
     * Document type kind of an advance invoice.
     */
    public const ADVANCE_KIND = 'typDokladu.zalohFaktura';

    /**
     * Document type of the tax document made when ABRAFLEXI_TAXDOC_DOCTYPE is not set.
     */
    public const DEFAULT_TAX_DOCUMENT_TYPE = 'FAKTURA';

    /**
     * Evidence of the invoice items: a link to it means "deducted from this document".
     */
    public const DEDUCTION_LINK_TARGET = 'faktura-vydana-polozka';

    /**
     * Columns of an advance invoice that the tax document takes over.
     */
    public const HEADER_COLUMNS = ['firma', 'varSym', 'specSym', 'konSym', 'mena', 'bankovniUcet', 'formaUhradyCis', 'popis', 'zavTxt', 'stredisko', 'cinnost', 'zakazka', 'clenKonVykDph'];

    /**
     * Columns of an item that the tax document takes over: what is sold, in what quantity and for how much.
     * Identifiers, accounting and the link to the advance stay behind.
     */
    public const ITEM_COLUMNS = ['kod', 'nazev', 'cenik', 'mnozMj', 'mj', 'cenaMj', 'typCenyDphK', 'typSzbDphK', 'typPolozkyK', 'sklad', 'slevaPol', 'stredisko', 'cinnost', 'zakazka', 'poznam'];

    private function __construct()
    {
    }

    /**
     * Is the document type an advance invoice?
     */
    public static function isAdvanceKind(mixed $typeKind): bool
    {
        return (string) $typeKind === self::ADVANCE_KIND;
    }

    /**
     * Was the advance already deducted from some document?
     *
     * The advance is linked to the items "Odpočet zálohy" of the documents it was deducted from, besides the
     * links to the payments. Deducting twice would leave the customer with two tax documents.
     *
     * @param array<int, array<string, mixed>> $links "vazby" of the advance
     */
    public static function isDeducted(array $links): bool
    {
        foreach ($links as $link) {
            if (self::targetOf($link['a'] ?? null, $link['a@ref'] ?? null) === self::DEDUCTION_LINK_TARGET
                || self::targetOf($link['b'] ?? null, $link['b@ref'] ?? null) === self::DEDUCTION_LINK_TARGET) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evidence a link points to: "faktura-vydana-polozka", "banka", ...
     *
     * @param mixed       $value a Relation object of the AbraFlexi library, or the plain value
     * @param null|string $ref   the "@ref" of the value, e.g. "/c/firma/faktura-vydana-polozka/714.json"
     */
    public static function targetOf(mixed $value, ?string $ref = null): ?string
    {
        if (\is_object($value) && isset($value->target) && $value->target !== '') {
            return (string) $value->target;
        }

        if (\is_object($value) && isset($value->ref) && \is_string($value->ref)) {
            $ref = $value->ref;
        }

        if ($ref !== null && preg_match('#/c/[^/]+/([^/]+)/[^/]+$#', $ref, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * The deduction branch of the tax document.
     *
     * @param string $advanceCode code of the advance, e.g. "VF2-0002/2026"
     * @param float  $amount      amount to deduct (what the advance is worth)
     *
     * @return array{odpocet: array{castkaMen: float, doklad: string}}
     */
    public static function deduction(string $advanceCode, float $amount): array
    {
        return ['odpocet' => ['castkaMen' => $amount, 'doklad' => 'code:'.preg_replace('/^code:/i', '', trim($advanceCode))]];
    }

    /**
     * Data of the tax document made from the paid advance.
     *
     * @param array<string, mixed>             $advance  the advance: its columns and "polozkyDokladu"
     * @param string                           $type     document type code of the tax document, e.g. "FAKTURA"
     * @param string                           $today    date of issue, Y-m-d
     * @param array<int, array<string, mixed>> $items    items of the advance (read apart when the advance does not carry them)
     *
     * @return array<string, mixed>
     */
    public static function taxDocument(array $advance, string $type, string $today, array $items = []): array
    {
        $data = [
            'typDokl' => 'code:'.preg_replace('/^code:/i', '', trim($type)),
            'datVyst' => $today,
            'duzpPuv' => $today,
            'duzpUcto' => $today,
            // the customer was sent the advance already, the tax document is mailed by whoever decides so
            'stavMailK' => 'stavMail.neodesilat',
        ];

        foreach (self::HEADER_COLUMNS as $column) {
            $value = self::plain($advance[$column] ?? null);

            if ($value !== null && $value !== '') {
                $data[$column] = $value;
            }
        }

        $rows = $items !== [] ? $items : (array) ($advance['polozkyDokladu'] ?? []);
        $copied = [];

        foreach ($rows as $row) {
            $item = [];

            foreach (self::ITEM_COLUMNS as $column) {
                $value = self::plain($row[$column] ?? null);

                if ($value !== null && $value !== '') {
                    $item[$column] = $value;
                }
            }

            // an item of the advance that is itself a deduction does not belong to the new document
            if (($item['typPolozkyK'] ?? '') === 'typPolozky.odpocetZalohy') {
                continue;
            }

            if ($item !== []) {
                $copied[] = $item;
            }
        }

        $data['polozkyDokladu'] = $copied;

        return $data;
    }

    /**
     * A value of the AbraFlexi library as plain text/number: a relation becomes "code:X", a date "Y-m-d".
     */
    private static function plain(mixed $value): int|float|string|bool|null
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_float($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (\is_array($value)) {
            return null;
        }

        return trim((string) $value);
    }
}
