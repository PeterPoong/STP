<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestoreOldDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Restore smaller tables normally
        $this->restoreTableData('stp_core_metas', 'old_coreMetaData.json');
        $this->restoreTableData('stp_countries', 'old_countriesData.json');
        $this->restoreTableData('stp_schools', 'old_schoolData.json');
        $this->restoreTableData('stp_courses_categories', 'old_courses_categories.json');
        $this->restoreTableData('stp_qualifications', 'old_stp_qualifications.json');
        $this->restoreTableData('stp_courses', 'old_coursesData.json');
        $this->restoreTableData('stp_tags', 'old_tags.json');
        $this->restoreTableData('stp_featureds', 'old_featuredsData.json');

        // Restore larger tables with batch processing
        $this->restoreLargeTableData('stp_states', 'old_statesData.json');
        $this->restoreLargeTableData('stp_cities', 'old_citiesData.json');
    }

    /**
     * Restore table data in batches.
     */
    protected function restoreLargeTableData(string $table, string $filename): void
    {
        $filePath = database_path('backups/' . $filename);
        $data = json_decode(file_get_contents($filePath), true);

        $batchSize = 1000; // Adjust the batch size as needed
        $chunks = array_chunk($data, $batchSize);

        foreach ($chunks as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Restore table data normally.
     */
    protected function restoreTableData(string $table, string $filename): void
    {
        $filePath = database_path('backups/' . $filename);
        $data = json_decode(file_get_contents($filePath), true);

        DB::table($table)->insert($data);
    }
}
