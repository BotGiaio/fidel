@extends('layouts.app', ['title' => __('Companies')])
@section('content')
    @include('companies.partials.modals')
    <div class="header  pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <h1 class="mb-3 mt--3">🏢 {{__('Companies')}}</h1>
              <div class="row align-items-center pt-2">
              </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">      
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col-8">
                                
                            </div>
                            <div class="col-4 text-right">
    
                                <a href="{{ route('admin.companies.index') }}?downlodcsv=true" class="btn btn-sm btn-outline-primary">{{ __('Export CSV') }}</a>
                                @if(auth()->user()->hasRole('admin') && config('settings.enable_import_csv'))
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-import-companies">{{ __('Import from CSV') }}</button>
                                @endif
                                
                            </div>
                        </div>
                        



                       


                    </div>

                    <div class="col-12">
                        @include('partials.flash')
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">{{ __('Name') }}</th>
                                    @if (config('settings.show_company_logo'))
                                         <th scope="col">{{ __('Logo') }}</th>
                                    @endif
                                   
                                    <th scope="col">{{ __('Owner') }}</th>
                                    <th scope="col">{{ __('Owner email') }}</th>
                                    <th scope="col">{{ __('Phone') }}</th>
                                    <th scope="col">{{ __('Creation Date') }}</th>
                                    <th scope="col">{{ __('Active') }}</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($companies as $company)
                                    <tr>
                                        @if(auth()->user()->hasRole('manager'))
                                            <td><a href="{{ route('admin.companies.loginas', $company) }}">{{ $company->name }}</a></td>
                                        @else
                                            <td><a href="{{ route('admin.companies.edit', $company) }}">{{ $company->name }}</a></td>
                                        @endif
                                       
                                        @if (config('settings.show_company_logo'))
                                            <td><img class="rounded" src={{ $company->icon }} width="50px" height="50px"></img></td>
                                        @endif
                                        
                                        <td>{{  $company->user?$company->user->name:__('Deleted') }}</td>
                                        <td>
                                            <a href="mailto: {{ $company->user?$company->user->email:""  }}">{{  $company->user?$company->user->email:__('Deleted')  }}</a>
                                        </td>
                                        <!-- phone -->
                                        <td>
                                            <a href="tel:{{ $company->phone }}">{{ $company->phone }}</a>
                                        </td>
                                        </td>
                                        <td>{{ $company->created_at->locale(Config::get('app.locale'))->isoFormat('LLLL') }}</td>
                                        <td>
                                           @if($company->active == 1)
                                                <span class="badge badge-success">{{ __('Active') }}</span>
                                           @else
                                                <span class="badge badge-warning">{{ __('Not active') }}</span>
                                           @endif
                                        </td>
                                        <td class="text-right">
                                            <div class="dropdown">
                                                <a class="btn btn-sm btn-icon-only" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                                                      </svg>
                                                      
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-right dropdown-menu-arrow">
                                                    <a class="dropdown-item" href="{{ route('admin.companies.edit', $company) }}">{{ __('Edit') }}</a>
                                                    <a class="dropdown-item" href="{{ route('admin.companies.loginas', $company) }}">{{ __('Login as') }}</a>
                                                    @if ($hasCloner)
                                                     <a class="dropdown-item" href="{{ route('admin.companies.create')."?cloneWith=".$company->id }}">{{ __('Clone it') }}</a>
                                                    @endif
                                                    <form action="{{ route('admin.companies.destroy', $company) }}" method="post">
                                                        @csrf
                                                        @method('delete')
                                                        @if($company->active == 0)
                                                            <a class="dropdown-item" href="{{ route('admin.company.activate', $company) }}">{{ __('Activate') }}</a>
                                                        @else
                                                            <button type="button" class="dropdown-item" onclick="confirm('{{ __("Are you sure you want to deactivate this company?") }}') ? this.parentElement.submit() : ''">
                                                                {{ __('Deactivate') }}
                                                            </button>
                                                        @endif
                                                    </form>
                                                    <a class="dropdown-item warning red" onclick="return confirm('Are you sure you want to delete this Company from Database? This will aslo delete all data related to it. This is irreversible step.')"  href="{{ route('admin.company.remove',$company)}}">{{ __('Delete') }}</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                           
                        </table>
                    <br /><br /><br />

                    </div>
                    <div class="card-footer py-4">
                        <nav class="d-flex justify-content-end" aria-label="...">
                            {{ $companies->links() }}
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        var resUrl="{{ route('admin.companies.edit', 0) }}";
    </script>
@endsection
