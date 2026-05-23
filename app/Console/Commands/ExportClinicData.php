<?php
//railway run php artisan clinic:export 1
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExportClinicData extends Command
{
    protected $signature = 'clinic:export
                            {company_id : The ID of the company/clinic to export}
                            {--format=json : Export format: json or sql}';

    protected $description = 'Export all data for a specific clinic/company into a compressed file';

    /**
     * قائمة الجداول التي سيتم تجاهلها (مثل الجداول العامة للمنصة)
     */
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
        $format = $this->option('format');

        // التحقق من وجود الشركة
        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            $this->error("❌ Company with ID {$companyId} not found.");
            return 1;
        }

        $this->info("🔍 Exporting data for company: {$company->name} (ID: {$companyId})");

        // 1. جلب أسماء جميع الجداول التي تحتوي على عمود company_id (باستثناء المستثناة)
        $tables = $this->getCompanyTables();

        if (empty($tables)) {
            $this->warn("⚠️ No tables found with company_id column.");
            return 1;
        }

        // 2. إنشاء مجلد مؤقت لتجميع الملفات
        $tempDir = storage_path("app/export_{$companyId}_" . now()->timestamp);
        if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            $this->error("❌ Could not create temporary directory.");
            return 1;
        }

        // 3. تصدير البيانات من كل جدول
        $exportData = [];
        $totalRows  = 0;
        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->where('company_id', $companyId)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $rowsArray = $rows->toArray();
            $exportData[$table] = $rowsArray;
            $totalRows += count($rowsArray);


            // حفظ كل جدول كملف JSON منفصل داخل المجلد المؤقت
            $json = json_encode($rowsArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents("{$tempDir}/{$table}.json", $json);

            $this->info("✔️ {$table}: " . count($rowsArray) . " rows");
        }

        $this->generateReadme($company->name, $companyId, $exportData, $totalRows, $tempDir);

        if ($totalRows === 0) {
            $this->warn("⚠️ No data found for company ID {$companyId}.");
            $this->deleteDirectory($tempDir);
            return 1;
        }

        // 4. نسخ مجلد الراديولوجي (إن وجد)
        $this->copyRadiologyFiles($companyId, $tempDir);

        // 5. إنشاء ملف مضغوط
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

        // 6. حذف المجلد المؤقت
        $this->deleteDirectory($tempDir);

        $this->info("✅ Export completed: " . storage_path("app/{$zipFileName}"));
        $this->info("📦 Total tables: " . count($exportData) . " | Total rows: {$totalRows}");

        return 0;
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
