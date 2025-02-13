<?php

namespace Webkul\Installer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as BagistoDatabaseSeeder;
use Webkul\Installer\Events\ComposerEvents;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class Installer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bagisto:install 
        { --skip-env-check : Skip env check. }
        { --skip-admin-creation : Skip admin creation. }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrate and seed command, publish assets and config, link storage';

    /**
     * Install and configure bagisto.
     */
    public function handle()
    {
        $getSeederDetails = ! $this->option('skip-env-check')
            ? $this->checkForEnvFile()
            : [];

        $this->call('key:generate');

        $this->warn('Step: Migrating all tables into database...');
        $this->call('migrate:fresh');

        $this->warn('Step: Seeding basic data for Bagisto kickstart...');
        $this->info(app(BagistoDatabaseSeeder::class)->run([
            'default_locale'     => $getSeederDetails['defaultLocale'] ?? 'en',
            'allowed_locales'    => $getSeederDetails['allowedLocales'] ?? ['en'],
            'default_currency'   => $getSeederDetails['defaultCurrency'] ?? 'USD',
            'allowed_currencies' => $getSeederDetails['allowedCurrencies'] ?? ['USD'],
        ]));

        $this->warn('Step: Linking storage directory...');
        $this->call('storage:link');

        $this->warn('Step: Optimizing...');
        $this->call('optimize');

        if (! $this->option('skip-admin-creation')) {
            $this->createAdminCredential();
        }

        ComposerEvents::postCreateProject();
    }

    /**
     *  Checking .env file and if not found then create .env file.
     *
     *  @return ?array
     */
    protected function checkForEnvFile()
    {
        if (! file_exists(base_path('.env'))) {
            $this->info('Creating the environment configuration file.');

            File::copy('.env.example', '.env');
        } else {
            $this->info('Great! your environment configuration file already exists.');
        }

        return $this->createEnvFile();
    }

    /**
     * Create a new .env file.
     * Then ask for env configuration details and set the environment
     * On .env file so that we can easily migrate to our db.
     *
     * @return void|array
     */
    protected function createEnvFile()
    {
        try {
            // Updating App URL
            $this->updateEnvVariable('APP_URL', 'Please enter the <bg=green>application URL</>', env('APP_URL', 'http://localhost:8000'));

            // Updating App Name
            $this->updateEnvVariable('APP_NAME', 'Please enter the <bg=green>application name</>', env('APP_NAME', 'Bagisto'));

            // Updating App Default Locales
            $defaultLocale = $this->updateEnvChoice('APP_LOCALE', 'Please select the <bg=green>default application locale</>', $this->locales());

            config(['app.locale' => $this->getEnvAtRuntime('APP_LOCALE')]);

            // Updating App Default Timezone
            $this->envUpdate('APP_TIMEZONE', date_default_timezone_get());
            $this->info('Your Default Timezone is ' . date_default_timezone_get());

            // Updating App Default Currencies
            $defaultCurrency = $this->updateEnvChoice('APP_CURRENCY', 'Please select the <bg=green>default currency</>', $this->currencies());
            config(['app.currency' => $this->getEnvAtRuntime('APP_CURRENCY')]);

            // Updating App Allowed Locales
            $allowedLocales = $this->allowedChoice('Please choose the <bg=green>allowed locales</> for your channels', $this->locales());

            // Updating App Allowed Currencies
            $allowedCurrencies = $this->allowedChoice('Please choose the <bg=green>allowed currencies</> for your channels', $this->currencies());

            // Updating Database Configuration
            $this->askForDatabaseDetails();

            $allowedLocales = array_values(array_unique(array_merge(
                [$defaultLocale],
                array_keys($allowedLocales)
            )));

            $allowedCurrencies = array_values(array_unique(array_merge(
                [$defaultCurrency ?? 'USD'],
                array_keys($allowedCurrencies)
            )));

            return [
                'defaultLocale'     => $defaultLocale,
                'allowedLocales'    => $allowedLocales,
                'defaultCurrency'   => $defaultCurrency,
                'allowedCurrencies' => $allowedCurrencies,
            ];
        } catch (\Exception $e) {
            $this->error('Error in creating .env file, please create it manually and then run `php artisan migrate` again.');
        }
    }

    /**
     * Add the database credentials to the .env file.
     */
    protected function askForDatabaseDetails()
    {
        $databaseDetails = [
            'DB_CONNECTION' => select(
                'Please select the <bg=green>database connection</>',
                ['mysql', 'pgsql', 'sqlsrv']
            ),

            'DB_HOST'       => text(
                label: 'Please enter the <bg=green>database host</>',
                default: env('DB_HOST', '127.0.0.1'),
                required: true
            ),

            'DB_PORT'       => text(
                label: 'Please enter the <bg=green>database port</>',
                default: env('DB_PORT', '3306'),
                required: true
            ),

            'DB_DATABASE' => text(
                label: 'Please enter the <bg=green>database name</>',
                default: env('DB_DATABASE', ''),
                required: true
            ),

            'DB_PREFIX' => text(
                label: 'Please enter the <bg=green>database prefix</>',
                default: env('DB_PREFIX', ''),
                hint: 'or press enter to continue'
            ),

            'DB_USERNAME' => text(
                label: 'Please enter your <bg=green>database username</>',
                default: env('DB_USERNAME', ''),
                required: true
            ),

            'DB_PASSWORD' => password(
                label: 'Please enter your <bg=green>database password</>',
                required: true
            ),
        ];

        if (
            ! $databaseDetails['DB_DATABASE']
            || ! $databaseDetails['DB_USERNAME']
            || ! $databaseDetails['DB_PASSWORD']
        ) {
            return $this->error('Please enter the database credentials.');
        }

        foreach ($databaseDetails as $key => $value) {
            if ($value) {
                $this->envUpdate($key, $value);
            }
        }
    }

    /**
     * Check key in `.env` file because it will help to find values at runtime.
     */
    protected static function getEnvAtRuntime(string $key): string|bool
    {
        if ($data = file(base_path('.env'))) {
            foreach ($data as $line) {
                $line = preg_replace('/\s+/', '', $line);

                $rowValues = explode('=', $line);

                if (strlen($line) !== 0) {
                    if (strpos($key, $rowValues[0]) !== false) {
                        return $rowValues[1];
                    }
                }
            }
        }

        return false;
    }

    /**
     * create a new admin credentials.
     */
    protected function createAdminCredential()
    {
        // Here! Asking for an admin name, email, and password
        $adminName = text(
            label: 'Enter the <bg=green>name of the admin user</>',
            default: 'Example',
            required: true
        );

        $adminEmail = text(
            label: 'Enter the <bg=green>email address of the admin user</>',
            default: 'admin@example.com',
            required: true
        );

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('The email address you entered is not valid please try again.');

            return $this->createAdminCredential();
        }

        $adminPassword = text(
            label: 'Configure the <bg=green>password</> for the admin user',
            default: 'admin123',
            required: true);

        $password = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 10]);

        try {
            DB::table('admins')->updateOrInsert(
                ['id' => 1],
                [
                    'name'     => $adminName,
                    'email'    => $adminEmail,
                    'password' => $password,
                    'role_id'  => 1,
                    'status'   => 1,
                ]
            );

            $filePath = storage_path('installed');

            File::put($filePath, 'Bagisto is successfully installed');

            $this->info('-----------------------------');
            $this->info('Congratulations!');
            $this->info('The installation has been finished and you can now use Bagisto.');
            $this->info('Go to ' . env('APP_URL') . '/admin' . ' and authenticate with:');
            $this->info('Email: ' . $adminEmail);
            $this->info('Password: ' . $adminPassword);
            $this->info('Cheers!');

            Event::dispatch('bagisto.installed');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * method for asking the details of .env files
     */
    protected function updateEnvVariable(string $key, string $question, string $defaultValue): void
    {
        $input = text(
            label: $question,
            default: $defaultValue,
            required: true
        );

        $this->envUpdate($key, $input ?: $defaultValue);
    }

    /**
     * Method for asking choice based on the list of options.
     *
     * @return string
     */
    protected function updateEnvChoice(string $key, string $question, array $choices)
    {
        $choice = select($question, $choices);

        $this->envUpdate("$key", $choice);

        return $choice;
    }

    /**
     * Function for getting allowed choices based on the list of options.
     */
    protected function allowedChoice(string $question, array $choices)
    {
        $selectedValues = multiselect(
            label: $question,
            options: array_values($choices),
            required: true
        );

        // Create an associative array with selected keys and their corresponding values
        $selectedChoices = [];
        foreach ($selectedValues as $selectedValue) {
            foreach ($choices as $key => $value) {
                if ($selectedValue === $value) {
                    $selectedChoices[$key] = $value;
                    break;
                }
            }
        }

        return $selectedChoices;
    }

    /**
     * Update the .env values
     */
    protected static function envUpdate(string $key, string $value): void
    {
        $data = file_get_contents(base_path('.env'));

        // Check if $value contains spaces, and if so, add double quotes
        if (preg_match('/\s/', $value)) {
            $value = '"' . $value . '"';
        }

        $data = preg_replace("/$key=(.*)/", "$key=$value", $data);

        file_put_contents(base_path('.env'), $data);
    }

    /**
     * Static Locales List
     */
    protected static function locales(): array
    {
        return [
            'ar'    => 'Arabic',
            'bn'    => 'Bengali',
            'de'    => 'German',
            'en'    => 'English',
            'es'    => 'Spanish',
            'fa'    => 'Persian',
            'fr'    => 'French',
            'he'    => 'Hebrew',
            'hi_IN' => 'Hindi',
            'it'    => 'Italian',
            'ja'    => 'Japanese',
            'nl'    => 'Dutch',
            'pl'    => 'Polish',
            'pt_BR' => 'Brazilian Portuguese',
            'ru'    => 'Russian',
            'sin'   => 'Sinhala',
            'tr'    => 'Turkish',
            'uk'    => 'Ukrainian',
            'zh_CN' => 'Chinese',
        ];
    }

    /**
     * Static Currencies List
     */
    protected static function currencies(): array
    {
        return [
            'CNY' => 'Chinese Yuan',
            'AED' => 'Dirham',
            'EUR' => 'Euro',
            'INR' => 'Indian Rupee',
            'IRR' => 'Iranian Rial',
            'AFN' => 'Israeli Shekel',
            'JPY' => 'Japanese Yen',
            'GBP' => 'Pound Sterling',
            'RUB' => 'Russian Ruble',
            'SAR' => 'Saudi Riyal',
            'TRY' => 'Turkish Lira',
            'USD' => 'US Dollar',
            'UAH' => 'Ukrainian Hryvnia',
        ];
    }
}
