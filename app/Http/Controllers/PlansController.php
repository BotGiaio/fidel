<?php

namespace App\Http\Controllers;

use Akaunting\Module\Facade as Module;
use App\Models\Plans;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Stripe\Exception\InvalidRequestException;

class PlansController extends Controller
{
    public function current(): View
    {
        // The current plan -- access for owner only
        if (!auth()->user()->hasRole('owner')) {
            abort(403, 'Unauthorized action.');
        }

        $theSelectedProcessor = strtolower(config('settings.subscription_processor', 'stripe'));

        if (
            !($theSelectedProcessor == 'stripe' || $theSelectedProcessor == 'local') &&
            auth()->user()->plan_status != 'set_by_admin' &&
            !config('settings.is_demo') &&
            config('app.url') != 'http://localhost'
        ) {
            $className = '\Modules\\' . ucfirst($theSelectedProcessor) . 'Subscribe\Http\Controllers\App';
            $ref = new \ReflectionClass($className);
            $ref->newInstanceArgs()->validate(auth()->user());
        }

        $plans = config('settings.forceUserToPay', false) 
            ? Plans::where('id', '!=', intval(config('settings.free_pricing_id')))->get() 
            : Plans::all();

        // Uncomment the line below to see the plans for debugging purposes
        // dd($plans);

        $colCounter = [4, 12, 6, 4, 3, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4];

        $currentUserPlan = Plans::withTrashed()->find(auth()->user()->mplanid());
        $planAttribute = auth()->user()->company->getPlanAttribute();

        $data = [
            'col' => $colCounter[count($plans)],
            'plans' => $plans,
            'currentPlan' => $currentUserPlan,
            'planAttribute' => $planAttribute,
        ];

        if (
            $theSelectedProcessor == 'stripe' &&
            config('app.url') != 'http://localhost' &&
            !config('settings.is_demo')
        ) {
            $data['intent'] = auth()->user()->createSetupIntent();
        }

        $data['subscription_processor'] = $theSelectedProcessor;

        return view('plans.current', $data);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Plans $plans): View
    {
        $this->adminOnly();
        if (!$this->isExtended()) {
            return view('plans.extended');
        }

        return view('plans.index', ['plans' => $plans->paginate(10)]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->adminOnly();
        $theSelectedProcessor = strtolower(config('settings.subscription_processor', 'stripe'));

        return view('plans.create', [
            'allplugins' => $this->getAllPlugins(),
            'theSelectedProcessor' => $theSelectedProcessor
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->adminOnly();

        // Validate request
        $rules = [
            'name' => ['required'],
            'price' => ['numeric', 'required'],
            'description' => ['required'],
            'features' => ['required'],
            'stripe_id' => ['sometimes'],
        ];

        $request->validate($rules);

        $plan = new Plans;
        $plan->name = strip_tags($request->name);
        $plan->price = strip_tags($request->price);
        $plan->limit_items = strip_tags($request->limit_items);
        $plan->limit_views = strip_tags($request->limit_views);

        if (isset($request->subscribe)) {
            foreach ($request->subscribe as $key => $value) {
                $plan->$key = strip_tags($value);
            }
        }

        $plan->description = $request->description;
        $plan->features = $request->features;
        $plan->period = $request->period == 'monthly' ? 1 : 2;

        $plan->save();

        $this->updatePlanPlugins($plan, $request->pluginsSelector);

        return redirect()->route('plans.index')->withStatus(__('Plan successfully created!'));
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

    private function getAllPlugins()
    {
        $plugins = [];
        foreach (Module::all() as $key => $module) {
            if (is_array($module->get('vendor_fields'))) {
                array_push($plugins, $module);
            }
        }

        return $plugins;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Plans  $plan
     */
    public function edit(Plans $plan): View
    {
        $this->adminOnly();
        $theSelectedProcessor = strtolower(config('settings.subscription_processor', 'Stripe'));

        return view('plans.edit', [
            'allplugins' => $this->getAllPlugins(),
            'plan' => $plan,
            'theSelectedProcessor' => $theSelectedProcessor,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  Plans  $plan
     */
    public function update(Request $request, Plans $plan): RedirectResponse
    {
        $this->adminOnly();

        $plan->name = strip_tags($request->name);
        $plan->price = strip_tags($request->price);
        $plan->limit_items = strip_tags($request->limit_items);
        $plan->limit_views = strip_tags($request->limit_views);

        // Subscriptions plans
        if (isset($request->subscribe)) {
            foreach ($request->subscribe as $key => $value) {
                $plan->$key = strip_tags($value);
            }
        }

        // Default stripe
        if (isset($request->stripe_id)) {
            $plan->stripe_id = $request->stripe_id;
        }

        $plan->period = $request->period == 'monthly' ? 1 : 2;
        $plan->enable_ordering = $request->ordering == 'enabled' ? 1 : 2;
        $plan->limit_orders = $request->ordering == 'enabled' ? $request->limit_orders : 0;

        $plan->description = $request->description;
        $plan->features = $request->features;

        $plan->save();

        $this->updatePlanPlugins($plan, $request->pluginsSelector);

        return redirect()->route('plans.index')->withStatus(__('Plan successfully updated!'));
    }

    private function updatePlanPlugins($plan, $pluginsSelector)
    {
        if ($pluginsSelector) {
            $plan->setConfig('plugins', json_encode($pluginsSelector));
        } else {
            // Set to null
            $plan->setConfig('plugins', null);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Plans  $plan
     */
    public function destroy(Plans $plan): RedirectResponse
    {
        $this->adminOnly();
        $plan->delete();

        return redirect()->route('plans.index')->withStatus(__('Plan successfully deleted!'));
    }

    public function subscribe3dStripe(Request $request, Plans $plan, User $user): RedirectResponse
    {
        if ($request->success . '' == 'true') {
            // Assign user to plan
            $user->plan_id = $plan->id;
            $user->cancel_url = route('plans.cancel');

            $user->save();

            return redirect()->route('plans.current')->withStatus(__('Plan update!'));
        } else {
            return redirect()->route('plans.current')->withError($request->message)->withInput();
        }
    }

    public function cancelStripeSubscription(): RedirectResponse
    {
        auth()->user()->subscription('main')->cancelNow();
        auth()->user()->cancel_url = '';
        auth()->user()->plan_id = intval(config('settings.free_pricing_id'));
        auth()->user()->save();

        return redirect()->route('plans.current')->withError(__('Subscription canceled'));
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $plan = Plans::findOrFail($request->plan_id);

        if (config('settings.subscription_processor') == 'Stripe') {
            $plan_stripe_id = $plan->stripe_id;
            // Should we do a swap
            try {
                if (auth()->user()->subscribed('main')) {
                    // SWAP
                    auth()->user()->subscription('main')->swap($plan_stripe_id);
                    auth()->user()->cancel_url = route('plans.cancel');
                } else {
                    // NEW Stripe subscription
                    $payment_stripe = auth()->user()->newSubscription('main', $plan_stripe_id)
                        ->withMetadata([
                            'plan_id' => $plan->id,
                        ])
                        ->create($request->payment_method);
                }
            } catch (InvalidRequestException $e) {
                return redirect()->route('plans.current')->withError(__('Error while creating subscription: ') . $e->getMessage());
            } catch (PaymentFailure $e) {
                return redirect()->route('plans.current')->withError(__('Payment failed: ') . $e->getMessage());
            } catch (PaymentActionRequired $e) {
                return redirect()->route('plans.current')->withError(__('Payment action required: ') . $e->getMessage());
            } catch (IncompletePayment $e) {
                return redirect()->route('plans.current')->withError(__('Payment incomplete: ') . $e->getMessage());
            }
        }

        return redirect()->route('plans.current')->withStatus(__('Subscription updated!'));
    }
}
