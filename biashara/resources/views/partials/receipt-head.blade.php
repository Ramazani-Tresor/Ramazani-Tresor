<div class="r-head">
    @if(!empty($shop['shop_logo']))
        <img src="{{ !empty($pdf) ? public_path('storage/'.$shop['shop_logo']) : asset('storage/'.$shop['shop_logo']) }}" alt="" class="r-logo">
    @endif
    <div class="r-shop">{{ $shop['shop_name'] }}</div>
    @if($shop['shop_address'])<div>{{ $shop['shop_address'] }}</div>@endif
    @if($shop['shop_phone'] || $shop['shop_email'])<div>{{ trim($shop['shop_phone'].' · '.$shop['shop_email'], ' ·') }}</div>@endif
    @if($shop['shop_tax_id'])<div>{{ $shop['shop_tax_id'] }}</div>@endif
</div>
