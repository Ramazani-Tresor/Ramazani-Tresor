@extends('layouts.app')
@section('title', $title)
@php
    $show = function ($row, $name, $def) {
        $v = $row->$name;
        return match ($def[1]) {
            'bool' => $v ? 'Oui' : 'Non', 'select' => \App\Http\Controllers\ReferenceController::options($def[2])[$v] ?? $v,
            'model' => $v ? ($def[2]::query()->find($v)?->name ?? $v) : '—', 'date' => $v?->format('d/m/Y'), 'decimal' => $v !== null ? money($v) : '',
            default => \Illuminate\Support\Str::limit((string) $v, 50),
        };
    };
    $input = function ($name, $def, $value = null, $prefix = '') {
        [$label, $type] = $def;
        $p = ['name' => $name, 'label' => $label, 'value' => $value, 'col' => $type === 'bool' ? 'col-md-4' : ($type === 'textarea' ? 'col-12' : 'col-md-4'), 'id' => $prefix.$name];
        if ($type === 'select') { $p += ['type' => 'select', 'options' => \App\Http\Controllers\ReferenceController::options($def[2]), 'required' => true]; }
        elseif ($type === 'model') { $p += ['type' => 'select', 'options' => $def[2]::query()->orderBy('name')->pluck('name', 'id')->all()]; }
        else { $p += ['type' => $type, 'required' => in_array($name, ['code', 'name'], true) || $type === 'date']; }
        return view('partials.field', $p)->render();
    };
@endphp
@section('content')
@include('partials.head', ['title' => $title, 'crumbs' => ['Paramétrage' => route('settings.index')]])
<div class="row g-3">
<div class="col-lg-3">@include('settings._nav')</div>
<div class="col-lg-9">
    <div class="card mb-3"><div class="card-header">Ajouter</div><div class="card-body"><form method="post" action="{{ route('refs.store', $ref) }}" class="row g-2">@csrf
        @foreach($fields as $n => $def){!! $input($n, $def, $def[1] === 'bool' && in_array($n, ['is_active', 'requires_approval', 'requires_end_date', 'prorata'], true) ? true : null, 'new_') !!}@endforeach
        <div class="col-12"><button class="btn btn-primary btn-sm">Ajouter</button></div>
    </form></div></div>
    <div class="card"><div class="card-header">Valeurs ({{ $rows->count() }})</div>
    <div class="table-responsive"><table class="table table-sm table-stack align-middle"><thead><tr>@foreach($fields as $n => $def)<th>{{ $def[0] }}</th>@endforeach<th></th></tr></thead><tbody>
    @foreach($rows as $row)<tr class="{{ isset($row->is_active) && ! $row->is_active ? 'text-muted-2' : '' }}">@foreach($fields as $n => $def)<td data-l="{{ $def[0] }}">{{ $show($row, $n, $def) }}</td>@endforeach
        <td><button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#e{{ $row->id }}">Modifier</button></td></tr>
        <tr class="collapse" id="e{{ $row->id }}"><td colspan="{{ count($fields) + 1 }}"><form method="post" action="{{ route('refs.update', [$ref, $row->id]) }}" class="row g-2 p-2">@csrf @method('PUT')
            @foreach($fields as $n => $def){!! $input($n, $def, $row->$n, 'r'.$row->id.'_') !!}@endforeach
            <div class="col-12"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form></td></tr>
    @endforeach
    </tbody></table></div></div>
</div>
</div>
@endsection
