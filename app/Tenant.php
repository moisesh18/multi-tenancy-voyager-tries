<?php

namespace App;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Hash;
use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;

/**
 * @property Website website
 * @property Hostname hostname
 * @property User admin
 */
class Tenant
{
    public function __construct(Website $website = null, Hostname $hostname = null, User $admin = null)
    {
        $this->website = $website;
        $this->hostname = $hostname;
        $this->admin = $admin;
    }

    public static function getRootFqdn()
    {
        return Hostname::where('website_id', null)->first()->fqdn;
    }

    public static function delete($name)
    {
        // $baseUrl = env('APP_URL_BASE');
        // $name = "{$name}.{$baseUrl}";
        if ($tenant = Hostname::where('fqdn', $name)->firstOrFail()) {
            app(HostnameRepository::class)->delete($tenant, true);
            app(WebsiteRepository::class)->delete($tenant->website, true);
            return "Tenant {$name} successfully deleted.";
        }
    }

    public static function deleteById($id)
    {
        if ($tenant = Hostname::where('id', $id)->firstOrFail()) {
            app(HostnameRepository::class)->delete($tenant, true);
            app(WebsiteRepository::class)->delete($tenant->website, true);
            return "Tenant with id {$id} successfully deleted.";
        }
    }

    public static function deleteByFqdn($fqdn)
    {
        if ($tenant = Hostname::where('fqdn', $fqdn)->firstOrFail()) {
            app(HostnameRepository::class)->delete($tenant, true);
            app(WebsiteRepository::class)->delete($tenant->website, true);
            return "Tenant {$fqdn} successfully deleted.";
        }
    }

    public static function registerTenant($name, $email = null, $password = null): Tenant
    {
        // Convert all to lowercase
        $name = strtolower($name);
        $email = strtolower($email);

        // We rename temporary tenant migrations and seeders to avoid creating system tenant tables in the tenant database, and seed in tables that doesn't exists.
        $migrations = base_path() . '/database/migrations/';
        $migrations_to_preserve = glob($migrations . '*.php');
        $migrations_to_preserve = Tenant::addExtension($migrations_to_preserve);

        $seeders = base_path() . '/database/seeds/tenants/';
        $seeders_to_preserve = glob($seeders . '*.php');
        $seeders_to_preserve = Tenant::addExtension($seeders_to_preserve);

        $website = new Website;
        app(WebsiteRepository::class)->create($website);

        // associate the website with a hostname
        $hostname = new Hostname;
        // $baseUrl = env('APP_URL_BASE', 'localhost');
        // $hostname->fqdn = "{$name}.{$baseUrl}";
        $hostname->fqdn = $name;
        app(HostnameRepository::class)->attach($hostname, $website);

        // make hostname current
        app(Environment::class)->tenant($hostname->website);

        \Artisan::call('config:clear');
        \Artisan::call('voyager:install');

        Tenant::removeExtension($migrations_to_preserve);
        Tenant::removeExtension($seeders_to_preserve);

        \Artisan::call('tenancy:db:seed');

        // Cleanup Voyager dummy migrations from system migration folder
        $voyager_migrations = base_path() . '/vendor/tcg/voyager/publishable/database/migrations/*.php';
        $files_to_kill = glob($voyager_migrations);
        $files_to_kill = array_map('basename', $files_to_kill);
        foreach ($files_to_kill as $file) {
            $path = $migrations. '/'. $file;
            if(file_exists($path)){
                unlink($path);
            }
        }

        // Make the registered user the default Admin of the site.
        $admin = null;
        if ($email) {
            $admin = static::makeAdmin($name, $email, $password);
        }

        return new Tenant($website, $hostname, $admin);
    }

    private static function makeAdmin($name, $email, $password): User
    {
        $admin = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        // $admin->guard_name = 'web';
        $admin->setRole('admin')->save();

        return $admin;
    }

    public static function tenantExists($name)
    {
        // $name = $name . '.' . env('APP_URL_BASE');
        return Hostname::where('fqdn', $name)->exists();
    }

    public static function addExtension($files_to_preserve){
        foreach ($files_to_preserve as $index=>$file) {
            $files_to_preserve[$index] = $file . '.txt';
            rename($file, $files_to_preserve[$index]);
        }
        return $files_to_preserve;
    }

    public static function removeExtension($files_to_preserve){
        foreach ($files_to_preserve as $index=>$file) {
            $files_to_preserve[$index] = str_replace(".txt", "" ,$file);
            rename($file, $files_to_preserve[$index]);
        }
    }
}
