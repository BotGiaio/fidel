<div class="pl-lg-4">
    <form id="company-form" method="post" action="{{ route('admin.companies.update', $company) }}" autocomplete="off" enctype="multipart/form-data">
        @csrf
        @method('put')
        <div class="row">
        <div class="col-md-6">
        <input type="hidden" id="rid" value="{{ $company->id }}"/>
        @include('partials.fields',['fields'=>[
            ['ftype'=>'input','name'=>"Company Name",'id'=>"name",'placeholder'=>"Company Name",'required'=>true,'value'=>$company->name],
            ['ftype'=>'input','name'=>"Company description",'id'=>"description",'placeholder'=>"Company description",'required'=>true,'value'=>$company->description],
            ['ftype'=>'input','name'=>"Company address",'id'=>"address",'placeholder'=>"Company address",'required'=>true,'value'=>$company->address],
            ['ftype'=>'input','name'=>"Company phone",'id'=>"phone",'placeholder'=>"Company phone",'required'=>true,'value'=>$company->phone],
        ]])
       
        @if(auth()->user()->hasRole('admin'))
            <br/>
            <div class="form-group">
                <label class="form-control-label" for="item_price">{{ __('Is Featured') }}</label>
                <label class="custom-toggle" style="float: right">
                    <input type="checkbox" name="is_featured" <?php if($company->is_featured == 1){echo "checked";}?>>
                    <span class="custom-toggle-slider rounded-circle"></span>
                </label>
            </div>
            <br/>
        @endif
        
        <br/>
        <div class="row">
            <?php
                $images=[];
                if(config('settings.show_company_logo'))
                {
                    $images=[
                        ['name'=>'company_logo','label'=>__('Company Image'),'value'=>$company->logom,'style'=>'width: 295px; height: 200px;','help'=>"JPEG 590 x 400 recomended"],
                        ['name'=>'company_cover','label'=>__('Company Cover Image'),'value'=>$company->coverm,'style'=>'width: 200px; height: 100px;','help'=>"JPEG 2000 x 1000 recomended"]
                    ];
                }
            ?>
            @foreach ($images as $image)
                <div class="col-md-6">
                    @include('partials.images',$image)
                </div>
            @endforeach
            
        </div>

    
        
        </div>
        <div class="col-md-6">
            @include('companies.partials.localisation')
        </div>

        </div>


        <div class="text-center">
            <button type="submit" class="btn btn-success mt-4">{{ __('Save') }}</button>
        </div>
        
    </form>
</div>
