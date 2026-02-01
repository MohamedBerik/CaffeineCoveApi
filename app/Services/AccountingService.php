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

        return DB::transaction(function () use (
            $sourceModel,
            $description,
            $lines,
            $userId,
            $date
        ) {

            $entry = JournalEntry::create([
                'entry_date' => $date ?? now()->toDateString(),
                'description' => $description,
                'created_by' => $userId
            ]);

            if ($sourceModel) {
                $entry->source()->associate($sourceModel);
                $entry->save();
            }

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'] ?? 0,
                    'credit'           => $line['credit'] ?? 0,
                ]);
            }

            return $entry;
        });
    }
}
