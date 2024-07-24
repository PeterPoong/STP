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
        if (!file_exists(database_path('backups'))) {
            mkdir(database_path('backups'), 0755, true);
        }

        $oldCoreMetaData = DB::table('stp_core_metas')->get()->toArray();
        file_put_contents(database_path('backups/old_coreMetaData.json'), json_encode($oldCoreMetaData));

        $oldSchoolData = DB::table('stp_schools')->get()->toArray();
        file_put_contents(database_path('backups/old_schoolData.json'), json_encode($oldSchoolData));
    }
}
