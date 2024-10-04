<?php

namespace App\Http\Controllers;

use Akaunting\Module\Facade as Module;
use App\Events\WebNotification;
use App\Exports\VendorsExport;
use App\Models\Company;
use App\Models\Plans;
use App\Traits\Fields;
use App\Traits\Modules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CompaniesController extends Controller
{
    use Fields;
    use Modules;

    private $imagePath = '';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (auth()->user()->hasRole('admin')) {
            $companies = Company::with('user');

            //With downloaod
            if (isset($_GET['downlodcsv'])) {
                $items = [];
                $vendorsToDownload = $companies->orderBy('id', 'desc')->get();
                foreach ($vendorsToDownload as $key => $vendor) {
                    $item = [
                        'vendor_name' => $vendor->name,
                        'vendor_id' => $vendor->id,
                        'created' => $vendor->created_at,
                        'owner_name' => $vendor->user->name,
                        'owner_email' => $vendor->user->email,
                    ];
                    array_push($items, $item);
                }

                return Excel::download(new VendorsExport($items), 'vendors_'.time().'.csv', \Maatwebsite\Excel\Excel::CSV);
            }

            return view('companies.index', [
                'parameters' => count($_GET) != 0,
                'hasCloner' => Module::has('cloner'),
                'allRes' => $companies->orderBy('id', 'desc')->pluck('name', 'id'),
                'companies' => $companies->orderBy('id', 'desc')->paginate(10)]);
        } else {
            return redirect()->route('dashboard');
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

    private function verifyAccess($company)
    {
        return auth()->user()->id == $company->user_id || auth()->user()->hasRole('admin');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function edit($company_id)
    {
        $company = Company::findOrFail($company_id);

        //Languages
        $available_languages = [];
        $default_language = null;

        //currency
        if (strlen($company->currency) > 1) {
            $currency = $company->currency;
        } else {
            $currency = config('settings.cashier_currency');
        }

        //App fields
        $appFields = $this->convertJSONToFields($this->vendorFields($company->getAllConfigs()));

        if ($this->verifyAccess($company)) {
            return view('companies.edit', [
                'hasCloner' => Module::has('cloner') && auth()->user()->hasRole(['admin', 'manager']),
                'company' => $company,
                'plans' => Plans::get()->pluck('name', 'id'),
                'available_languages' => $available_languages,
                'default_language' => $default_language,
                'currency' => $currency,
                'appFields' => $appFields,
            ]);
        }

        return redirect()->route('dashboard')->withStatus(__('No Access'));
    }

    public function updateApps(Request $request, Company $company): RedirectResponse
    {
        //Update custom fields
        if ($request->has('custom')) {
            $company->setMultipleConfig($request->custom);
        }

        return redirect()->route('admin.companies.edit', $company->id)->withStatus(__('Company successfully updated.'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Company  $company
     */
    public function update(Request $request, $companyid): RedirectResponse
    {

        $this->imagePath = config('app.images_upload_path');

        $company = Company::findOrFail($companyid);
        $company->name = strip_tags($request->name);
        $thereIsCompanyAddressChange = $company->address.'' != $request->address.'';

        $company->address = strip_tags($request->address);
        $company->phone = strip_tags($request->phone);

        $company->description = strip_tags($request->description);

        //Update subdomain only if rest is not older than 1 day
        if (Carbon::parse($company->created_at)->diffInDays(Carbon::now()) < 2) {
            $company->subdomain = $this->makeAlias(strip_tags($request->name));
        }

        if (auth()->user()->hasRole('admin')) {
            $company->is_featured = $request->is_featured != null ? 1 : 0;
        }

        if ($request->hasFile('company_logo')) {

            $company->logo = $this->saveImageVersions(
                $this->imagePath,
                $request->company_logo,
                [
                    ['name' => 'large', 'w' => 590, 'h' => 400],
                    ['name' => 'medium', 'w' => 295, 'h' => 200],
                    ['name' => 'thumbnail', 'w' => 200, 'h' => 200],
                ]
            );
        }

        if ($request->hasFile('company_cover')) {
            $company->cover = $this->saveImageVersions(
                $this->imagePath,
                $request->company_cover,
                [
                    ['name' => 'cover', 'w' => 2000, 'h' => 1000],
                    ['name' => 'thumbnail', 'w' => 400, 'h' => 200],
                ]
            );
        }

        //Change currency
        $company->currency = $request->currency;

        //Change do converstion
        $company->do_covertion = $request->do_covertion == 'true' ? 1 : 0;

        $company->update();

        //Update custom fields
        if ($request->has('custom')) {
            $company->setMultipleConfig($request->custom);
        }

        return redirect()->route('admin.companies.edit', $company->id)->withStatus(__('Company successfully updated.'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     */
    public function destroy($companyid): RedirectResponse
    {
        $company = Company::findOrFail($companyid);
        if (! auth()->user()->hasRole('admin')) {
            abort(403);
        }

        $company->active = 0;
        $company->save();

        return redirect()->route('admin.companies.index')->withStatus(__('Company successfully deactivated.'));
    }

    public function remove($companyid): RedirectResponse
    {
        if (config('settings.is_demo')) {
            return redirect()->route('admin.companies.index')->withStatus(__('Disabled in demo'));
        }
        $company = Company::findOrFail($companyid);
        if (! auth()->user()->hasRole('admin')) {
            abort(403);
        }

        $company->delete();

        return redirect()->route('admin.companies.index')->withStatus(__('Company successfully deleted.'));
    }

    private function makeCompanyActive(Company $company)
    {
        //Activate the company
        $company->active = 1;
        $company->subdomain = $this->makeAlias($company->name);
        $company->update();
    }

    public function activateCompany($companyid): RedirectResponse
    {
        $company = Company::findOrFail($companyid);
        $this->makeCompanyActive($company);

        return redirect()->route('admin.companies.index')->withStatus(__('Company successfully activated.'));
    }

    public function stopImpersonate(): RedirectResponse
    {

        Auth::user()->stopImpersonating();

        Session::forget('company_id');
        Session::forget('company_currency');
        Session::forget('company_convertion');

        return redirect()->route('home');
    }

    public function loginas($companyid): RedirectResponse
    {
        $company = Company::findOrFail($companyid);
        if (config('settings.is_demo', false)) {
            return redirect()->back()->withStatus('Not allowed in demo');
        }
        if ($this->verifyAccess($company)) {
            //Login as owner
            Session::put('impersonate', $company->user->id);

            //Set the company
            session(['company_id' => $company->id]);
            session(['company_currency' => $company->currency]);
            session(['company_convertion' => $company->do_covertion]);

            return redirect()->route('home');
        } else {
            abort(403);
        }
    }

    public function notify($type, $companyid, $message): JsonResponse
    {

        $company = Company::findOrFail($companyid);
        $CAN_USE_PUSHER = strlen(config('broadcasting.connections.pusher.app_id')) > 2 && strlen(config('broadcasting.connections.pusher.key')) > 2 && strlen(config('broadcasting.connections.pusher.secret')) > 2;
        $messageSend = false;
        $responseMessage = '';
        //Check if company has this notification enabled
        if ($company->getConfig('enable_notification_'.$type, true) && $CAN_USE_PUSHER) {
            event(new WebNotification($company, $message, $type));
            $responseMessage = $message;
            $messageSend = true;
        } else {
            $responseMessage = __('Notification not enabled');
            $messageSend = false;
        }

        //Respond in json
        return response()->json([
            'message' => $responseMessage,
            'messageSend' => $messageSend,
        ]);
    }

    public function share(): View
    {
        $url = auth()->user()->company->getLinkAttribute();

        return view('companies.share', ['url' => $url, 'name' => auth()->user()->company->name]);
    }
}
