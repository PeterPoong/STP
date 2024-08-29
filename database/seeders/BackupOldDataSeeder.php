<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackupOldDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Ensure the backup directory exists
        if (!file_exists(database_path('backups'))) {
            mkdir(database_path('backups'), 0755, true);
        }

        // List of tables and their corresponding backup file names
        $tables = [
            'stp_core_metas' => 'old_coreMetaData.json',
            'stp_schools' => 'old_schoolData.json',
            'stp_countries' => 'old_countriesData.json',
            'stp_states' => 'old_statesData.json',
            'stp_cities' => 'old_citiesData.json',
            'stp_featureds' => 'old_featuredsData.json',
            'stp_courses' => 'old_coursesData.json',
            'stp_courses_categories' => 'old_courses_categories.json',
            'stp_qualifications' => 'old_stp_qualifications.json',
            'stp_tags' => 'old_tags.json'
        ];

        // Process each table one by one
        foreach ($tables as $table => $filename) {
            $backupFilePath = database_path('backups/' . $filename);
            $this->backupTableData($table, $backupFilePath);
        }
    }

    /**
     * Backup the data of a table in chunks.
     *
     * @param string $table
     * @param string $filePath
     * @return void
     */
    private function backupTableData(string $table, string $filePath): void
    {
        // Open the file in write mode
        $backupFile = fopen($filePath, 'w');
        fwrite($backupFile, '['); // Start the JSON array

        $firstRecord = true;
        foreach (DB::table($table)->cursor() as $row) {
            if (!$firstRecord) {
                fwrite($backupFile, ','); // Add a comma between JSON objects
            } else {
                $firstRecord = false;
            }

            fwrite($backupFile, json_encode($row)); // Write the JSON-encoded row
        }

        fwrite($backupFile, ']'); // End the JSON array
        fclose($backupFile);
    }
}
