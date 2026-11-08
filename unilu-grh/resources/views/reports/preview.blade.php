@extends('layouts.app')
@section('title', $r['title'])
@php $q = http_build_query($f); @endphp
@section('content')
@include('partials.head', ['title' => $r['title'], 'crumbs' => ['Éditions' => route('reports.index')], 'sub' => e($r['period']).' · '.count($r['rows']).' ligne(s) · référence '.e($r['reference']), 'actions' => '<a class="btn btn-light" href="'.route('reports.export', [$r['type'], 'pdf']).'?'.$q.'"><i class="bi bi-file-earmark-pdf"></i> PDF</a><a class="btn btn-light" href="'.route('reports.export', [$r['type'], 'xlsx']).'?'.$q.'"><i class="bi bi-file-earmark-excel"></i> Excel</a>'])
@if($r['summary'])<div class="card mb-3"><div class="card-body"><ul class="stat-list">@foreach($r['summary'] as $k => $v)<li><span>{{ $k }}</span><b>{{ $v }}</b></li>@endforeach</ul></div></div>@endif
<div class="card">@if($r['rows'])<div class="table-responsive"><table class="table table-sm table-stack"><thead><tr>@if($r['groups'])<th>Groupe</th>@endif @foreach($r['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead><tbody>
@foreach($r['rows'] as $i => $row)<tr>@if($r['groups'])<td data-l="Groupe" class="fw-600">{{ $r['groups'][$i] }}</td>@endif @foreach($row as $j => $v)<td data-l="{{ $r['columns'][$j] ?? '' }}">{{ $v }}</td>@endforeach</tr>@endforeach
</tbody></table></div>@else <div class="card-body">@include('partials.empty')</div> @endif</div>
@endsection
