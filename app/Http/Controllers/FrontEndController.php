<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;

class FrontEndController extends Controller
{
    public function register(): RedirectResponse
    {
        return redirect()->route('register');
    }

    public function index()
    {

        //1. Subdomain mode
        if ($this->getSubDomain()) {
            return $this->subdomainMode();
        }

        //1a. Custom domain mode
        $customDomain = $this->customDomainMode();
        if ($customDomain != '') {
            return $this->company($customDomain);
        }

        //2. Landing page
        //Check if landing is disabled
        if (config('settings.disable_landing_page', false)) {
            return redirect()->route('home');
        }
        $landingClassToUse = config('settings.landing_page');

        return (new $landingClassToUse())->landing();
    }

    /**
     * 2. Subdomain mode - directly show store.
     */
    public function subdomainMode()
    {
        $subDomain = $this->getSubDomain();
        if ($subDomain) {
            $company = Company::whereRaw('REPLACE(subdomain, "-", "") = ?', [str_replace('-', '', $subDomain)])->get();
            if (count($company) != 1) {
                //When Subdomain mode is disabled, show the error
                if (! config('settings.wildcard_domain_ready')) {
                    return view('companies.alertdomain', ['subdomain' => $subDomain]);
                } else {
                    abort(404);
                }

            }

            return $this->company($subDomain);
        }
    }

    /**
     * Gets subdomain.
     */
    public function getSubDomain()
    {
        $subdomain = substr_count(str_replace('www.', '', $_SERVER['HTTP_HOST']), '.') > 1 ? substr(str_replace('www.', '', $_SERVER['HTTP_HOST']), 0, strpos(str_replace('www.', '', $_SERVER['HTTP_HOST']), '.')) : '';
        if ($subdomain == '' | in_array($subdomain, config('settings.ignore_subdomains'))) {
            return false;
        }

        return $subdomain;
    }

    private function customDomainMode()
    {
        //1 - Make sure the module is installed
        if (! in_array('domain', config('global.modules', []))) {
            return '';
        }

        //2 - Extract the domain
        $domain = request()->getHost();

        //3 - Make sure, this is no the project domain itself,
        if (strpos(config('app.url'), $domain) !== false) {
            return '';
        }

        //4 - The extracted domain is in the list of custom values
        $theConfig = Config::where('value', 'like', '%'.$domain.'%')->first();
        if ($theConfig) {
            //5 - Return the company subdomain if company is active
            $vendor_id = $theConfig->model_id;

            $vendor = Company::where('id', $vendor_id)->first();
            if ($vendor) {
                return $vendor->subdomain;
            } else {
                return '';
            }

        } else {
            //By default return no domain
            return '';
        }
    }

    public function company($subdomain)
    {
        // Company page
        $pageClassToUse = config('settings.company_page');

        return (new $pageClassToUse())->companyLanding(Company::where('subdomain', $subdomain)->firstOrFail());
    }
}
