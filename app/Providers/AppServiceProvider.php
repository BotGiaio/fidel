<?php

namespace App\Providers;

use Akaunting\Module\Facade as Module;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Schema::defaultStringLength(191);
        try {
            $settings = [];

            //Set the list of added modules
            $modules = [];
            foreach (Module::all() as $key => $module) {
                array_push($modules, $module->get('alias'));

            }
            $settings['modules'] = $modules;

            $moneyList = [];
            $rawMoney = config('money');
            foreach ($rawMoney as $key => $value) {
                $moneyList[$key] = $value['name'].' - '.$value['symbol'].' - '.$key;
            }

            //Setup for money list
            config(['config.env.2' => [
                'name' => 'Localization',
                'slug' => 'localizatino',
                'icon' => 'ni ni-world-2',
                'fields' => [
                    ['title' => 'Default language', 'help' => 'If you make change, make sure you first have added the new language in Translations and you have done the translations.', 'key' => 'APP_LOCALE', 'value' => 'en', 'ftype' => 'select', 'data' => config('languages')],
                    ['title' => 'List of available language on the landing page', 'help' => 'Define a list of Language short code and the name. If only one language is listed, the language picker will not show up', 'key' => 'FRONT_LANGUAGES', 'value' => 'EN,English,FR,French'],
                    ['title' => 'Time zone', 'key' => 'TIME_ZONE', 'value' => 'Europe/Berlin', 'ftype' => 'select', 'data' => config('timezones')],
                    ['title' => 'Default currency', 'key' => 'CASHIER_CURRENCY', 'value' => 'usd', 'ftype' => 'select', 'data' => $moneyList],
                    ['title' => 'Money conversion', 'help' => 'Some currencies need this field to be unselected. By default it should be selected', 'key' => 'DO_CONVERTION', 'value' => 'true', 'ftype' => 'bool'],
                    ['title' => 'Time format', 'key' => 'TIME_FORMAT', 'value' => 'AM/PM', 'ftype' => 'select', 'data' => ['AM/PM' => 'AM/PM', '24hours ' => '24 Hours']],
                    ['title' => 'Date and time display', 'key' => 'DATETIME_DISPLAY_FORMAT', 'value' => 'd M Y h:i A'],
                    ['title' => 'Working time display format', 'help' => "For 24h use 'E HH:mm' and for AM/PM use 'E h:mm a'", 'key' => 'DATETIME_WORKING_HOURS_DISPLAY_FORMAT_NEW', 'value' => 'E HH:mm'],
                    ['title' => 'Default phone country code ', 'help' => '2 characters -  ISO 3166-1 alpha-2 format', 'key' => 'DEFAULT_COUNTRY', 'value' => 'US'],
                ],
            ]]);

            //Setup subscribe methods
            $subscriptionsModules = [];
            $subscriptionsModules['Stripe'] = 'Stripe'; // Stripe is default
            $subscriptionsModules['Local'] = 'Local bank transfers'; // Stripe is default

            //Templates
            $templatesModules = [];
            $templatesModules['defaulttemplate'] = __('Default template');

            //Modules with dashboard info
            $modulesWithDashboardInfo = [];

            foreach (Module::all() as $key => $module) {
                if ($module->get('isSubscriptionModule')) {
                    $subscriptionsModules[$module->get('name')] = $module->get('name');
                }
                if ($module->get('isTemplateModule')) {
                    $templatesModules[$module->get('alias')] = $module->get('name');
                }
                if ($module->get('hasDashboardInfo')) {
                    array_push($modulesWithDashboardInfo, $module->get('alias'));
                }
            }
            config(['config.env.1.fields.0.data' => $subscriptionsModules]);
            //config(['config.env.0.fields.7.data' => $templatesModules]);
            $settings['modulesWithDashboardInfo'] = $modulesWithDashboardInfo;

            config([
                'global' => $settings,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
