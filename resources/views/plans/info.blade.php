<div class="card-header border-0">
    <h3 class="mb-0">{{ __('Current Plans') }}</h3>
</div>

<div class="card-body">
    @if (session('status'))
        <div role="alert" class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        @forelse ($plans as $plan)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $plan->name }}</h5>
                    </div>
                    <div class="card-body">
                        <p>@money($plan->price, config('settings.site_currency', 'usd'))</p>
                    </div>
                </div>
            </div>
        @empty
            <p>{{ __('No plans available.') }}</p>
        @endforelse
    </div>
</div>
