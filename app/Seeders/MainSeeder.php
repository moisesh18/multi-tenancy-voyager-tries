<?php

namespace App\Seeders;

use Illuminate\Database\Seeder;
use TCG\Voyager\Traits\Seedable;

class MainSeeder extends Seeder
{
    use Seedable;

    protected $seedersPath;

    /**
     * Auto generated seed file.
     */
    public function run()
    {
        $this->seedersPath = database_path('seeds/tenants').'/';
        $migrations = glob($this->seedersPath . '*.php');
        $classes = array_map('basename', $migrations);
        foreach ($classes as $class) {
            $class_name = pathinfo($class, PATHINFO_FILENAME);
            $this->seed($class_name);
          }
    }
}
