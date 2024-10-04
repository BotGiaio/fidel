@extends('layouts.app', ['title' => __('Share')])
@section('admin_title')
    {{__('Share')}}
@endsection
@section('title')
<title>{{$name}}</title>
@endsection
@if(config('settings.share_this_property'))
    @section('head')
        <script type="text/javascript" src="https://platform-api.sharethis.com/js/sharethis.js#property={{ config('settings.share_this_property') }}&product=sticky-share-buttons" async="async"></script>
    @endsection
@endisset

@section('content')
    <div class="header  pb-8 pt-5 pt-md-8">
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-xl-12 order-xl-1">
                <div class="card bg-secondary shadow">
                    <div class="card-body">
                        <h2 class="text-uppercase text-center text-muted mb-4">{{ __('Share your offer with your audience') }}</h2>
                        <div class="pl-lg-4">
                            <div class="sharethis-inline-share-buttons" data-url="{{ $url }}"></div>
                            <div class=" mt-4" style="width: 100%; justify-content: center; align-items: center; text-align: center;"  >
                    
                                <div>
                                    
                                
                                    <div class="input-group mb-3"  style="width: 100%; justify-content: center;">
                                        <input id="url" type="text" class="form-control" style="max-width:400px;" value="{{ $url }}" aria-describedby="button-addon2">
                                        <div class="input-group-append">
                                            
                                          <button onclick="copyToClipboard()" class="btn btn-outline-primary" type="button" id="button-addon2">
                                            <i id="check" class="ni ni-check-bold" style="display: none"></i>
                                            <span id="copy">{{ __('Copy')}}</span>
                                            </button>
                                        </div>
                                      </div>
                                </div>
                               
                            </div>
                           

                              
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('layouts.footers.auth')
    </div>
@endsection

@section('js')
<script>
    function copyToClipboard() {
    var copyText = document.getElementById("url");
    
    copyText.select();
    document.execCommand("copy");

    document.getElementById("check").style.display = "block";
    document.getElementById("copy").style.display = "none";
    }
</script>
@endsection
