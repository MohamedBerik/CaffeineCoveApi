<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Tenant;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Create a balanced journal entry
     */
    public static function createEntry(
        $sourceModel,
        string $description,
        array $lines,
        $userId = null,
        $date = null
    ): JournalEntry {

        // ✅ التحقق من توازن القيد
        $totalDebit  = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \Exception('Journal entry is not balanced. Debit: ' . $totalDebit . ', Credit: ' . $totalCredit);
        }

        // ✅ تحديد company_id
        $companyId = $sourceModel->company_id ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Cannot determine company_id. Source model: ' . get_class($sourceModel));
        }

        return DB::transaction(function () use (
            $sourceModel,
            $description,
            $lines,
            $userId,
            $date,
            $companyId,
            $totalDebit,
            $totalCredit,
        ) {

            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_date' => $date ?? now()->toDateString(),
                'description' => $description,
                'created_by' => $userId,
            ]);

            // ✅ ربط القيد بالمصدر (Morph)
            if ($sourceModel && $sourceModel->exists) {
                $entry->source()->associate($sourceModel);
                $entry->save();
            }

            // ✅ إنشاء الأسطر المحاسبية
            foreach ($lines as $line) {
                $account = Account::query()
                    ->where('id', $line['account_id'])
                    ->first();

                if (!$account) {
                    throw new \Exception('Account not found: ' . $line['account_id']);
                }

                JournalLine::create([
                    'company_id'       => $companyId,
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $account->id,
                    'debit'            => $line['debit'] ?? 0,
                    'credit'           => $line['credit'] ?? 0,
                ]);
            }

            // ✅ تسجيل النشاط
            // ✅ تسجيل النشاط – نجلب كائن المستخدم إذا كان موجوداً
            $user = null;
            if ($userId) {
                $user = \App\Models\User::find($userId);
            }

            ActivityLogger::log(
                $companyId,
                $user,
                'journal_entry.created',
                JournalEntry::class,
                $entry->id,
                [
                    'description' => $description,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'source_type' => $sourceModel ? get_class($sourceModel) : null,
                    'source_id' => $sourceModel?->id,
                ]
            );
            return $entry;
        });
    }

    /**
     * Create a simple journal entry with one debit and one credit
     */
    public static function createSimpleEntry(
        $sourceModel,
        string $description,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        $userId = null,
        $date = null
    ): JournalEntry {
        return self::createEntry(
            $sourceModel,
            $description,
            [
                ['account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amount],
            ],
            $userId,
            $date
        );
    }

    /**
     * Reverse a journal entry
     */
    public static function reverseEntry(JournalEntry $originalEntry, $userId = null): JournalEntry
    {
        $lines = $originalEntry->lines->map(function ($line) {
            return [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ];
        })->toArray();

        return self::createEntry(
            $originalEntry->source,
            'Reversal of entry #' . $originalEntry->id . ': ' . $originalEntry->description,
            $lines,
            $userId,
            now()->toDateString()
        );
    }

    /**
     * Get account balance
     */
    public static function getAccountBalance(int $accountId, $asOfDate = null): float
    {
        $query = JournalLine::query()
            ->where('account_id', $accountId);

        if ($asOfDate) {
            $query->whereHas('entry', function ($q) use ($asOfDate) {
                $q->whereDate('entry_date', '<=', $asOfDate);
            });
        }

        $debits = (clone $query)->sum('debit');
        $credits = (clone $query)->sum('credit');

        // ✅ تحديد نوع الحساب لتحديد طبيعة الرصيد
        $account = Account::query()->find($accountId);

        if (!$account) {
            return 0;
        }

        $balance = $debits - $credits;

        // حسابات الأصول والمصروفات طبيعتها مدينة
        if (in_array($account->type, ['asset', 'expense'])) {
            return $balance;
        }

        // حسابات الخصوم والإيرادات وحقوق الملكية طبيعتها دائنة
        return -$balance;
    }

    /**
     * Get trial balance for a company
     */
    public static function getTrialBalance(?int $companyId = null, $asOfDate = null): array
    {
        $companyId = $companyId ?? Tenant::id();

        if (!$companyId) {
            throw new \Exception('Company ID is required for trial balance');
        }

        $query = JournalLine::query()
            ->where('company_id', $companyId)
            ->with('account');

        if ($asOfDate) {
            $query->whereHas('entry', function ($q) use ($asOfDate) {
                $q->whereDate('entry_date', '<=', $asOfDate);
            });
        }

        $lines = $query->get();

        $trialBalance = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $accountId = $line->account_id;

            if (!isset($trialBalance[$accountId])) {
                $trialBalance[$accountId] = [
                    'account_id' => $accountId,
                    'account_code' => $line->account->code,
                    'account_name' => $line->account->name,
                    'account_type' => $line->account->type,
                    'debit' => 0,
                    'credit' => 0,
                ];
            }

            $trialBalance[$accountId]['debit'] += $line->debit;
            $trialBalance[$accountId]['credit'] += $line->credit;

            $totalDebit += $line->debit;
            $totalCredit += $line->credit;
        }

        return [
            'accounts' => array_values($trialBalance),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
        ];
    }
}
