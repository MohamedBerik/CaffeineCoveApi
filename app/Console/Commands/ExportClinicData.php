<?php

//railway run php artisan clinic:export 1
//railway run php artisan clinic:export 1 --excel

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class ExportClinicData extends Command
{
    protected $signature = 'clinic:export
                            {company_id : The ID of the company/clinic to export}
                            {--excel : Also generate an Excel file (.xlsx) with one sheet per table}';

    protected $description = 'Export all data for a specific clinic/company into a compressed file';

    private array $excludeTables = [
        'migrations',
        'password_resets',
        'failed_jobs',
        'personal_access_tokens',
        'platform_settings',
    ];

    public function handle()
    {
        $companyId = (int) $this->argument('company_id');
        $withExcel = $this->option('excel');

        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            $this->error("❌ Company with ID {$companyId} not found.");
            return 1;
        }

        $this->info("🔍 Exporting data for company: {$company->name} (ID: {$companyId})");

        // 1. جلب الجداول
        $tables = $this->getCompanyTables();
        if (empty($tables)) {
            $this->warn("⚠️ No tables found with company_id column.");
            return 1;
        }

        // 2. مجلد مؤقت
        $tempDir = storage_path("app/export_{$companyId}_" . now()->timestamp);
        if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            $this->error("❌ Could not create temporary directory.");
            return 1;
        }

        // 3. تصدير JSON + تجميع البيانات
        $exportData = [];
        $totalRows  = 0;

        foreach ($tables as $table) {
            $rows = DB::table($table)->where('company_id', $companyId)->get();
            if ($rows->isEmpty()) continue;

            $rowsArray = $rows->toArray();
            $exportData[$table] = $rowsArray;
            $totalRows += count($rowsArray);

            $json = json_encode($rowsArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents("{$tempDir}/{$table}.json", $json);

            $this->info("✔️ {$table}: " . count($rowsArray) . " rows");
        }

        // 4. إنشاء Excel إذا طُلب
        if ($withExcel) {
            $excelPath = "{$tempDir}/clinic_{$companyId}_data.xlsx";
            $this->generateExcel($exportData, $excelPath);
            $this->info("✔️ Excel file created.");
        }

        // 5. README
        $this->generateReadme($company->name, $companyId, $exportData, $totalRows, $tempDir);

        if ($totalRows === 0) {
            $this->warn("⚠️ No data found for company ID {$companyId}.");
            $this->deleteDirectory($tempDir);
            return 1;
        }

        // 6. نسخ ملفات الأشعة
        $this->copyRadiologyFiles($companyId, $tempDir);

        // 7. ضغط الملفات
        $zipFileName = "clinic_{$companyId}_export_" . now()->format('Y-m-d_His') . ".zip";
        $zipPath = storage_path("app/{$zipFileName}");

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempDir) + 1);

                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        } else {
            $this->error("❌ Failed to create ZIP file.");
            $this->deleteDirectory($tempDir);
            return 1;
        }

        $this->deleteDirectory($tempDir);

        $this->info("✅ Export completed: " . storage_path("app/{$zipFileName}"));
        $this->info("📦 Total tables: " . count($exportData) . " | Total rows: {$totalRows}");

        return 0;
    }

    /**
     * إنشاء ملف Excel بورقة لكل جدول
     */
    private function generateExcel(array $exportData, string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // نزيل الورقة الافتراضية

        $sheetIndex = 0;
        foreach ($exportData as $table => $rows) {
            if (empty($rows)) continue;

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle(substr($table, 0, 31)); // طول الورقة الأقصى 31 حرفًا

            // رأس الأعمدة
            $columns = array_keys((array) $rows[0]);
            $col = 'A';
            foreach ($columns as $column) {
                $sheet->setCellValue($col . '1', $column);
                $col++;
            }

            // البيانات
            $rowNum = 2;
            foreach ($rows as $row) {
                $row = (array) $row;
                $col = 'A';
                foreach ($columns as $column) {
                    $sheet->setCellValue($col . $rowNum, $row[$column] ?? '');
                    $col++;
                }
                $rowNum++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    /**
     * جلب جميع الجداول التي تحتوي على عمود company_id
     */
    private function getCompanyTables(): array
    {
        $allTables = DB::select('SHOW TABLES');
        $tables = [];

        foreach ($allTables as $tableObj) {
            $table = reset($tableObj); // اسم الجدول

            // تجاهل الجداول المحددة
            if (in_array($table, $this->excludeTables)) {
                continue;
            }

            if (Schema::hasColumn($table, 'company_id')) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * نسخ مجلد radiology الخاص بالعيادة إلى المجلد المؤقت (إن وجد)
     */
    private function copyRadiologyFiles(int $companyId, string $destDir): void
    {
        $srcDir = public_path("storage/radiology/{$companyId}");

        if (!is_dir($srcDir)) {
            return;
        }

        $destPath = "{$destDir}/radiology_files";
        if (!mkdir($destPath, 0755, true) && !is_dir($destPath)) {
            return;
        }

        // نسخ الملفات باستثناء المجلدات الفرعية (حسب الهيكل)
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $relativePath = substr($file->getRealPath(), strlen($srcDir) + 1);
            $target = "{$destPath}/{$relativePath}";

            if ($file->isDir()) {
                mkdir($target, 0755, true);
            } else {
                copy($file->getRealPath(), $target);
            }
        }

        $this->info("✔️ Radiology files copied.");
    }

    /**
     * حذف مجلد ومحتوياته
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    private function generateReadme(string $companyName, int $companyId, array $exportData, int $totalRows, string $tempDir): void
    {
        $lines = [];
        $lines[] = "============================================";
        $lines[] = " Clinic Data Export";
        $lines[] = "============================================";
        $lines[] = "";
        $lines[] = "Company Name : {$companyName}";
        $lines[] = "Company ID   : {$companyId}";
        $lines[] = "Export Date  : " . now()->toDateTimeString();
        $lines[] = "Total Tables : " . count($exportData);
        $lines[] = "Total Rows   : {$totalRows}";
        $lines[] = "";
        $lines[] = "Files Included:";
        $lines[] = "---------------";

        foreach ($exportData as $table => $rows) {
            $lines[] = sprintf("  %-35s  (%d rows)", $table . '.json', count($rows));
        }

        $lines[] = "";
        $lines[] = "The 'radiology_files' folder (if present) contains the uploaded radiology images for this clinic.";
        $lines[] = "";
        $lines[] = "These JSON files can be imported into another system using a custom script or database tool.";

        file_put_contents("{$tempDir}/README.txt", implode("\n", $lines));
    }
}
