<?php
/*
 * AccountBalanceCalculator.php
 * Copyright (c) 2024 james@firefly-iii.org.
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

declare(strict_types=1);

namespace FireflyIII\Support\Models;

use FireflyIII\Models\Account;
use FireflyIII\Models\AccountBalance;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionJournal;
use Illuminate\Support\Facades\Log;

class AccountBalanceCalculator
{
    private function __construct()
    {
        // no-op
    }

    /**
     * Recalculate all balances for a given account.
     *
     * Je moet toch altijd wel alles doen want je weet niet waar een transaction journal invloed op heeft.
     * Dus dit aantikken per transaction journal is zinloos, beide accounts moeten gedaan worden.
     */
    public static function recalculateAll(): void
    {
        $object = new self();
        $object->recalculateLatest(null);
        // $object->recalculateJournals(null, null);
    }

    public static function recalculateForJournal(TransactionJournal $transactionJournal): void
    {
        $object = new self();
        foreach ($transactionJournal->transactions as $transaction) {
            $object->recalculateLatest($transaction->account);
            // $object->recalculateJournals($transaction->account, $transactionJournal);
        }
    }

    private function getAccountBalanceByAccount(int $account, int $currency): AccountBalance
    {
        $query                          = AccountBalance::where('title', 'balance')->where('account_id', $account)->where('transaction_currency_id', $currency);

        $entry                          = $query->first();
        if (null !== $entry) {
            // Log::debug(sprintf('Found account balance "balance" for account #%d and currency #%d: %s', $account, $currency, $entry->balance));

            return $entry;
        }
        $entry                          = new AccountBalance();
        $entry->title                   = 'balance';
        $entry->account_id              = $account;
        $entry->transaction_currency_id = $currency;
        $entry->balance                 = '0';
        $entry->save();
        // Log::debug(sprintf('Created new account balance for account #%d and currency #%d: %s', $account, $currency, $entry->balance));

        return $entry;
    }

    private function getAccountBalanceByJournal(string $title, int $account, int $journal, int $currency): AccountBalance
    {
        $query                          = AccountBalance::where('title', $title)->where('account_id', $account)->where('transaction_journal_id', $journal)->where('transaction_currency_id', $currency);

        $entry                          = $query->first();
        if (null !== $entry) {
            return $entry;
        }
        $entry                          = new AccountBalance();
        $entry->title                   = $title;
        $entry->account_id              = $account;
        $entry->transaction_journal_id  = $journal;
        $entry->transaction_currency_id = $currency;
        $entry->balance                 = '0';
        $entry->save();

        return $entry;
    }

    private function recalculateLatest(?Account $account): void
    {
        $query  = Transaction::groupBy(['transactions.account_id', 'transactions.transaction_currency_id', 'transactions.foreign_currency_id']);

        if (null !== $account) {
            $query->where('transactions.account_id', $account->id);
        }
        $result = $query->get(['transactions.account_id', 'transactions.transaction_currency_id', 'transactions.foreign_currency_id', \DB::raw('SUM(transactions.amount) as sum_amount'), \DB::raw('SUM(transactions.foreign_amount) as sum_foreign_amount')]);

        // reset account balances:
        $this->resetAccountBalancesByAccount('balance', $account);

        /** @var \stdClass $row */
        foreach ($result as $row) {
            $account             = (int) $row->account_id;
            $transactionCurrency = (int) $row->transaction_currency_id;
            $foreignCurrency     = (int) $row->foreign_currency_id;
            $sumAmount           = (string) $row->sum_amount;
            $sumForeignAmount    = (string) $row->sum_foreign_amount;
            $sumAmount           = '' === $sumAmount ? '0' : $sumAmount;
            $sumForeignAmount    = '' === $sumForeignAmount ? '0' : $sumForeignAmount;

            // first create for normal currency:
            $entry               = $this->getAccountBalanceByAccount($account, $transactionCurrency);
            $entry->balance      = bcadd((string) $entry->balance, $sumAmount);
            $entry->save();

            // then do foreign amount, if present:
            if ($foreignCurrency > 0) {
                $entry          = $this->getAccountBalanceByAccount($account, $foreignCurrency);
                $entry->balance = bcadd((string) $entry->balance, $sumForeignAmount);
                $entry->save();
            }
        }
        Log::debug(sprintf('Recalculated %d account balance(s)', $result->count()));
    }

    private function resetAccountBalancesByAccount(string $title, ?Account $account): void
    {
        if (null === $account) {
            $count = AccountBalance::whereNotNull('updated_at')->where('title', $title)->update(['balance' => '0']);
            Log::debug(sprintf('Set %d account balance(s) to zero.', $count));

            return;
        }
        $count = AccountBalance::where('account_id', $account->id)->where('title', $title)->update(['balance' => '0']);
        Log::debug(sprintf('Set %d account balance(s) of account #%d to zero.', $count, $account->id));
    }

    /**
     * Als je alles opnieuw doet, verzamel je alle transactions en het bedrag en zet je dat neer als "balance after
     * journal". Dat betekent, netjes op volgorde van datum en doorrekenen.
     *
     * Zodra je een transaction journal verplaatst (datum) moet je dat journal en alle latere journals opnieuw doen.
     * Maar dan moet je van de account wel een beginnetje hebben, namelijk de balance tot en met dat moment.
     *
     *  1. Dus dan search je eerst naar die SUM, som alle transactions eerder dan (niet inclusief) de journal.
     *  2. En vanaf daar pak je alle journals op of na de journal (dus ook de journal zelf) en begin je door te tellen.
     *  3. Elke voorbij gaande journal entry "balance_after_journal" geef je een update of voeg je toe.
     */
    private function recalculateJournals(?Account $account, ?TransactionJournal $transactionJournal): void
    {
        $query   = Transaction::groupBy(['transactions.account_id', 'transaction_journals.id', 'transactions.transaction_currency_id', 'transactions.foreign_currency_id']);
        $query->leftJoin('transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id');
        $query->orderBy('transaction_journals.date', 'asc');
        $amounts = [];
        if (null !== $account) {
            $query->where('transactions.account_id', $account->id);
        }
        if (null !== $account && null !== $transactionJournal) {
            $query->where('transaction_journals.date', '>=', $transactionJournal->date);
            $amounts = $this->getStartAmounts($account, $transactionJournal);
        }
        $result  = $query->get(['transactions.account_id', 'transaction_journals.id', 'transactions.transaction_currency_id', 'transactions.foreign_currency_id', \DB::raw('SUM(transactions.amount) as sum_amount'), \DB::raw('SUM(transactions.foreign_amount) as sum_foreign_amount')]);

        /** @var \stdClass $row */
        foreach ($result as $row) {
            $account                                 = (int) $row->account_id;
            $transactionCurrency                     = (int) $row->transaction_currency_id;
            $foreignCurrency                         = (int) $row->foreign_currency_id;
            $sumAmount                               = (string) $row->sum_amount;
            $sumForeignAmount                        = (string) $row->sum_foreign_amount;
            $journalId                               = (int) $row->id;

            // check for empty strings
            $sumAmount                               = '' === $sumAmount ? '0' : $sumAmount;
            $sumForeignAmount                        = '' === $sumForeignAmount ? '0' : $sumForeignAmount;

            // new amounts:
            $amounts[$account][$transactionCurrency] = bcadd($amounts[$account][$transactionCurrency] ?? '0', $sumAmount);
            $amounts[$account][$foreignCurrency]     = bcadd($amounts[$account][$foreignCurrency] ?? '0', $sumForeignAmount);

            // first create for normal currency:
            $entry                                   = self::getAccountBalanceByJournal('balance_after_journal', $account, $journalId, $transactionCurrency);
            $entry->balance                          = $amounts[$account][$transactionCurrency];
            $entry->save();

            // then do foreign amount, if present:
            if ($foreignCurrency > 0) {
                $entry          = self::getAccountBalanceByJournal('balance_after_journal', $account, $journalId, $foreignCurrency);
                $entry->balance = $amounts[$account][$foreignCurrency];
                $entry->save();
            }
        }

        // select transactions.account_id, transaction_journals.id, transactions.transaction_currency_id, transactions.foreign_currency_id, sum(transactions.amount), sum(transactions.foreign_amount)
        //
        // from transactions
        //
        // left join transaction_journals ON transaction_journals.id = transactions.transaction_journal_id
        //
        // group by account_id, transaction_journals.id, transaction_currency_id, foreign_currency_id
        // order by transaction_journals.date desc
    }

    private function getStartAmounts(Account $account, TransactionJournal $journal): array
    {
        exit('here we are');

        return [];
    }
}
