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
        // Define the tables and their respective backup files
        $tables = [
            'stp_core_metas' => 'old_coreMetaData.json',
            'stp_countries' => 'old_countriesData.json',
            'stp_states' => 'old_statesData.json',
            'stp_cities' => 'old_citiesData.json',
            'stp_schools' => 'old_schoolData.json',
            'stp_courses_categories' => 'old_courses_categories.json',
            'stp_qualifications' => 'old_stp_qualifications.json',
            'stp_courses' => 'old_coursesData.json',
            'stp_tags' => 'old_tags.json',
            'stp_featureds' => 'old_featuredsData.json'
        ];

        // Process each table and its corresponding backup file
        foreach ($tables as $table => $filename) {
            $this->restoreTableData($table, database_path('backups/' . $filename));
        }
    }

    /**
     * Restore the data of a table from a JSON backup in chunks.
     *
     * @param string $table
     * @param string $filePath
     * @return void
     */
    private function restoreTableData(string $table, string $filePath): void
    {
        // Open the backup file for reading
        $file = fopen($filePath, 'r');

        // Read the first character (start of JSON array)
        fseek($file, 1);

        $chunkSize = 100; // Number of records per chunk
        $data = [];
        $buffer = '';

        // Read data in chunks
        while (($line = fgets($file)) !== false) {
            // Remove the trailing comma and newline character
            $line = rtrim($line, ",\n");

            // Accumulate the line into the buffer
            $buffer .= $line;

            // Try decoding to ensure the line completes a valid JSON object
            $decoded = json_decode($buffer, true);

            if ($decoded !== null) {
                $data[] = $decoded; // Add the decoded JSON object to the chunk
                $buffer = ''; // Reset the buffer

                // If the chunk size is reached, insert data and clear the array
                if (count($data) >= $chunkSize) {
                    DB::table($table)->insert($data);
                    $data = [];
                }
            }
        }

        // Insert any remaining data
        if (!empty($data)) {
            DB::table($table)->insert($data);
        }

        // Close the file after reading
        fclose($file);
    }
}
