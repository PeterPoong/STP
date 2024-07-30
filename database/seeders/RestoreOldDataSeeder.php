<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestoreOldDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $oldData = json_decode(file_get_contents(database_path('backups/old_coreMetaData.json')), true);
        DB::table('stp_core_metas')->insert($oldData);

        $oldCountriesData = json_decode(file_get_contents(database_path('backups/old_countriesData.json')), true);
        DB::table('stp_countries')->insert($oldCountriesData);

        $oldStatesData = json_decode(file_get_contents(database_path('backups/old_statesData.json')), true);
        DB::table('stp_states')->insert($oldStatesData);

        $oldCitiesData = json_decode(file_get_contents(database_path('backups/old_citiesData.json')), true);
        DB::table('stp_cities')->insert($oldCitiesData);

        $oldSchoolData = json_decode(file_get_contents(database_path('backups/old_schoolData.json')), true);
        DB::table('stp_schools')->insert($oldSchoolData);

        $oldCoursesCategoryData = json_decode(file_get_contents(database_path('backups/old_courses_categories.json')), true);
        DB::table('stp_courses_categories')->insert($oldCoursesCategoryData);

        $oldCoursesQualification = json_decode(file_get_contents(database_path('backups/old_stp_qualifications.json')), true);
        DB::table('stp_qualifications')->insert($oldCoursesQualification);

        $oldCoursesData = json_decode(file_get_contents(database_path('backups/old_coursesData.json')), true);
        DB::table('stp_courses')->insert($oldCoursesData);

        $oldTag = json_decode(file_get_contents(database_path('backups/old_tags.json')), true);
        DB::table('stp_tags')->insert($oldTag);

        $oldFeaturedData = json_decode(file_get_contents(database_path('backups/old_featuredsData.json')), true);
        DB::table('stp_featureds')->insert($oldFeaturedData);
    }
}
