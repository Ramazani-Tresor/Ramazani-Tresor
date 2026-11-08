{{-- Champ de formulaire : name, label, type (text|email|date|number|decimal|select|textarea|file|bool|password), options [valeur => libellé],
     value, required, col, help, placeholder, attrs (attributs bruts), id --}}
@php
    $type = $type ?? 'text';
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name).'_'.substr(md5($label.($col ?? '')), 0, 5);
    $val = old($name, $value ?? null);
    if ($val instanceof \DateTimeInterface) { $val = $val->format('Y-m-d'); }
    $req = ! empty($required);
@endphp
<div class="{{ $col ?? 'col-md-6' }}">
    @if($type === 'bool')
        <div class="form-check mt-md-4 pt-md-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="{{ $id }}" @checked($val)>
            <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
        </div>
    @else
        <label class="form-label" for="{{ $id }}">{{ $label }}@if($req) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
        @if($type === 'select')
            <select class="form-select @error($name) is-invalid @enderror" name="{{ $name }}" id="{{ $id }}" @required($req) {!! $attrs ?? '' !!}>
                @if(! $req || ! empty($placeholder))<option value="">{{ $placeholder ?? '—' }}</option>@endif
                @foreach($options ?? [] as $k => $o)
                    @if(is_array($o))
                        <option value="{{ $k }}" @selected((string) $val === (string) $k) @isset($o['group']) data-group="{{ $o['group'] }}" @endisset>{{ $o['label'] }}</option>
                    @else
                        <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $o }}</option>
                    @endif
                @endforeach
            </select>
        @elseif($type === 'textarea')
            <textarea class="form-control @error($name) is-invalid @enderror" name="{{ $name }}" id="{{ $id }}" rows="{{ $rows ?? 2 }}" @required($req) placeholder="{{ $placeholder ?? '' }}" {!! $attrs ?? '' !!}>{{ $val }}</textarea>
        @elseif($type === 'file')
            <input class="form-control @error($name) is-invalid @enderror" type="file" name="{{ $name }}" id="{{ $id }}" accept="{{ $accept ?? '.pdf,.jpg,.jpeg,.png' }}" @required($req)>
        @else
            <input class="form-control @error($name) is-invalid @enderror" type="{{ $type === 'decimal' ? 'number' : $type }}" @if($type === 'decimal') step="0.01" min="0" @endif name="{{ $name }}" id="{{ $id }}" value="{{ $type === 'password' ? '' : $val }}" @required($req) placeholder="{{ $placeholder ?? '' }}" {!! $attrs ?? '' !!}>
        @endif
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
        @isset($help)<div class="form-text">{{ $help }}</div>@endisset
    @endif
</div>
