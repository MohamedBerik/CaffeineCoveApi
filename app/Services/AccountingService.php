<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public static function createEntry(
        $sourceModel,
        string $description,
        array $lines,
        $userId = null,
        $date = null
    ) {

        $totalDebit  = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \Exception('Journal entry is not balanced');
        }

        if (! $sourceModel || ! isset($sourceModel->company_id)) {
            throw new \Exception('Source model must have company_id');
        }

        $companyId = $sourceModel->company_id;

        return DB::transaction(function () use (
            $sourceModel,
            $description,
            $lines,
            $userId,
            $date,
            $companyId
        ) {

            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'entry_date' => $date ?? now()->toDateString(),
                'description' => $description,
                'created_by' => $userId
            ]);

            $entry->source()->associate($sourceModel);
            $entry->save();

            foreach ($lines as $line) {

                $account = \App\Models\Account::where('company_id', $companyId)
                    ->where('id', $line['account_id'])
                    ->firstOrFail();

                JournalLine::create([
                    'company_id'       => $companyId,
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $account->id,
                    'debit'            => $line['debit'] ?? 0,
                    'credit'           => $line['credit'] ?? 0,
                ]);
            }

            return $entry;
        });
    }
}
