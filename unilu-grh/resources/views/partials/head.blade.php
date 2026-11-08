{{-- En-tête de page : $title, $sub (facultatif), $crumbs [libellé => url] --}}
<div class="page-head">
    <div>
        @isset($crumbs)<div class="crumb">@foreach($crumbs as $l => $u)<a href="{{ $u }}">{{ $l }}</a> › @endforeach</div>@endisset
        <h1>{{ $title }}</h1>
        @isset($sub)<div class="sub">{!! $sub !!}</div>@endisset
    </div>
    @isset($actions)<div class="d-flex gap-2 flex-wrap">{!! $actions !!}</div>@endisset
</div>
