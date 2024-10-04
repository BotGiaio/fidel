<?php

namespace App\Http\Controllers;

use Akaunting\Module\Facade as Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Image;

class SettingsController extends Controller
{
    protected static $currencies;

    protected static $jsfront;

    protected $imagePath = '/uploads/settings/';

    private function validateAccess()
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(404);
        }
    }

    /**
     * Loads and sets the current environment
     */
    public function getCurrentEnv()
    {
        $envConfigs = config('config.env');

        //Extra fields from included modules
        $extraFields = [];
        foreach (Module::all() as $key => $module) {
            if ($module->get('global_fields')) {
                $extraFields = array_merge($extraFields, $module->get('global_fields'));
            }

        }
        $envConfigs['3']['fields'] = array_merge($extraFields, $envConfigs['3']['fields']);

        //Since 2.2.x there is custom modules
        $envMerged = [];
        foreach ($envConfigs as $key => $group) {
            $theMegedGroupFields = [];
            foreach ($group['fields'] as $key => $field) {
                if (! (isset($field['onlyin']) && $field['onlyin'] != config('settings.app_project_type'))) {

                    $shouldBeAdded = true;

                    //Hide on specific env config
                    if (isset($field['hideon'])) {
                        $hideOn = explode(',', $field['hideon']);
                        foreach ($hideOn as $hideSpecific) {
                            if (config('settings.app_code_name', '') == $hideSpecific) {
                                $shouldBeAdded = false;
                            }
                        }
                    }
                    if ($shouldBeAdded) {
                        array_push($theMegedGroupFields, [
                            'ftype' => isset($field['ftype']) ? $field['ftype'] : 'input',
                            'type' => isset($field['type']) ? $field['type'] : 'text',
                            'id' => 'env['.$field['key'].']',
                            'name' => isset($field['title']) && $field['title'] != '' ? $field['title'] : $field['key'],
                            'placeholder' => isset($field['placeholder']) ? $field['placeholder'] : '',
                            'value' => env($field['key'], $field['value']),
                            'required' => false,
                            'separator' => isset($field['separator']) ? $field['separator'] : null,
                            'additionalInfo' => isset($field['help']) ? $field['help'] : null,
                            'data' => isset($field['data']) ? $field['data'] : [],
                        ]);
                    }

                }
            }
            array_push($envMerged, [
                'name' => $group['name'],
                'slug' => $group['slug'],
                'icon' => $group['icon'],
                'fields' => $theMegedGroupFields,
            ]);
        }

        return $envMerged;
    }

    /**
     * Show the settings edit screen
     */
    public function index()
    {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('module:migrate', ['--force' => true]);

        if (auth()->user()->hasRole('admin')) {

            $curreciesArr = [];
            static::$currencies = require __DIR__.'/../../../config/money.php';

            foreach (static::$currencies as $key => $value) {
                array_push($curreciesArr, $key);
            }

            $jsfront = '';
            $jsback = '';
            $cssfront = '';
            $cssback = '';
            try {
                $jsfront = File::get(base_path('public/byadmin/front.js'));

                $jsback = File::get(base_path('public/byadmin/back.js'));
                $cssfront = File::get(base_path('public/byadmin/front.css'));

                $cssback = File::get(base_path('public/byadmin/back.css'));
            } catch (\Throwable $th) {
                //throw $th;
            }

            return view('settings.index', [
                'currencies' => $curreciesArr,
                'jsfront' => $jsfront,
                'jsback' => $jsback,
                'cssfront' => $cssfront,
                'cssback' => $cssback,
                'envConfigs' => $this->getCurrentEnv(),
            ]);
        } else {
            return redirect()->route('dashboard')->withStatus(__('No Access'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        //
    }

    public function setEnvironmentValue(array $values)
    {

        $envFile = app()->environmentFilePath();
        $str = "\n";
        $str .= file_get_contents($envFile);
        $str .= "\n"; // In case the searched variable is in the last line without \n
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
                if ($envValue == trim($envValue) && strpos($envValue, ' ') !== false) {
                    $envValue = '"'.$envValue.'"';
                }

                $keyPosition = strpos($str, "{$envKey}=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);

                // If key does not exist, add it
                if ((! $keyPosition && $keyPosition != 0) || ! $endOfLinePosition || ! $oldLine) {
                    $str .= "{$envKey}={$envValue}\n";
                } else {
                    if ($envKey == 'DB_PASSWORD') {
                        $str = str_replace($oldLine, "{$envKey}=\"{$envValue}\"", $str);
                    } else {
                        $str = str_replace($oldLine, "{$envKey}={$envValue}", $str);
                    }

                }
            }
        }

        $str = substr($str, 1, -1);
        if (! file_put_contents($envFile, $str)) {
            return false;
        }

        return true;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        if (config('settings.is_demo')) {
            //Demo, don;t allow
            return redirect()->route('admin.settings.index')->withStatus(__('Settings not allowed to be updated in DEMO mode!'));
        }

        if ($request->hasFile('site_logo')) {
            $LOGO_URL = $this->saveImageVersions(
                $this->imagePath,
                $request->site_logo,
                [
                    ['name' => 'logo', 'type' => 'png'],
                ]
            );
            $envs = $request->env;
            $envs['LOGO_URL'] = $this->imagePath.''.$LOGO_URL.'_logo.jpg';
            $request->merge(['env' => $envs]);
        }

        $this->setEnvironmentValue($request->env);
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Cache::flush();

        //Update the custom js and css files created by admin
        fwrite(fopen(__DIR__.'/../../../public/byadmin/front.js', 'w'), str_replace('tagscript', 'script', $request->jsfront));
        fwrite(fopen(__DIR__.'/../../../public/byadmin/back.js', 'w'), str_replace('tagscript', 'script', $request->jsback));
        fwrite(fopen(__DIR__.'/../../../public/byadmin/front.css', 'w'), str_replace('tagscript', 'script', $request->cssfront));
        fwrite(fopen(__DIR__.'/../../../public/byadmin/back.css', 'w'), str_replace('tagscript', 'script', $request->cssback));
        fwrite(fopen(__DIR__.'/../../../public/byadmin/frontmenu.js', 'w'), str_replace('tagscript', 'script', $request->jsfrontmenu));
        fwrite(fopen(__DIR__.'/../../../public/byadmin/frontcss.css', 'w'), str_replace('tagscript', 'script', $request->cssfrontmenu));

        if ($request->hasFile('favicons')) {
            $imAC256 = Image::make($request->favicons->getRealPath())->fit(512, 512);
            $imgAC192 = Image::make($request->favicons->getRealPath())->fit(192, 192);
            $imgMS150 = Image::make($request->favicons->getRealPath())->fit(150, 150);

            $imgApple = Image::make($request->favicons->getRealPath())->fit(120, 120);
            $img32 = Image::make($request->favicons->getRealPath())->fit(32, 32);
            $img16 = Image::make($request->favicons->getRealPath())->fit(16, 16);

            $imAC256->save(public_path().'/android-chrome-512x512.png');
            $imgAC192->save(public_path().'/android-chrome-192x192.png');
            $imgMS150->save(public_path().'/mstile-150x150.png');

            $imgApple->save(public_path().'/apple-touch-icon.png');
            $img32->save(public_path().'/favicon-32x32.png');
            $img16->save(public_path().'/favicon-16x16.png');

        }

        return redirect()->route('admin.settings.index')->withStatus(__('Settings successfully updated!'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        //
    }

    public function landing(): View
    {

        $locale = Cookie::get('lang') ? Cookie::get('lang') : config('settings.app_locale');
        if (isset($_GET['lang'])) {
            //3. Change locale to the new local
            app()->setLocale($_GET['lang']);
            $locale = $_GET['lang'];
            session(['applocale_change' => $_GET['lang']]);
        }

        $this->validateAccess();

        $availableLanguagesENV = config('settings.front_languages');
        $exploded = explode(',', $availableLanguagesENV);
        $availableLanguages = [];
        for ($i = 0; $i < count($exploded); $i += 2) {
            $availableLanguages[$exploded[$i]] = $exploded[$i + 1];
        }

        $sections = [];
        $landiingPageFunctions = explode(',', config('settings.landing_page_functions'));
        $landingPageTitles = explode(',', config('settings.landing_page_titles'));
        foreach ($landiingPageFunctions as $key => $value) {
            $sections[$landingPageTitles[$key]] = $value;
        }
        // $sections = ["Features"=>"feature", "Testimonials"=>"testimonial", "Processes"=>"process","FAQs"=>"faq","Blog links"=>"blog"];

        $currentEnvLanguage = isset(config('config.env')[2]['fields'][0]['data'][config('app.locale')]) ? config('config.env')[2]['fields'][0]['data'][config('app.locale')] : 'UNKNOWN';

        return view('settings.landing.index', [
            'sections' => $sections,
            'locale' => $locale,
            'availableLanguages' => $availableLanguages,
            'currentLanguage' => $currentEnvLanguage,
        ]);
    }

    //Validate
    public function activation($token)
    {
        $data = [
            'url' => config('app.url'),
            'date' => date('Y/m/d h:i:s'),
            'token' => $token,
        ];
        file_put_contents(storage_path('activation'), json_encode($data, JSON_THROW_ON_ERROR), FILE_APPEND|LOCK_EX);

        //Redirect to the login page
        return redirect()->route('login');
    }
}
