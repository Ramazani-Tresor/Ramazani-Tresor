<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif; background: #E9EEF6; }
        .sheet { background: #fff; margin: 16px auto; padding: 10px; }
        .receipt-80 .sheet { width: 80mm; padding: 3mm 4mm; font-size: 11.5px; }
        .receipt-58 .sheet { width: 58mm; padding: 2mm 2.5mm; font-size: 10px; }
        .receipt-a4 .sheet { width: 210mm; min-height: 297mm; padding: 16mm 15mm; font-size: 12.5px; }
        .r-head { text-align: center; margin-bottom: 6px; }
        .receipt-a4 .r-head { text-align: left; display: block; border-bottom: 3px solid #0B1F3A; padding-bottom: 10px; margin-bottom: 14px; }
        .r-logo { max-height: 48px; max-width: 60%; margin-bottom: 4px; }
        .r-shop { font-weight: 700; font-size: 1.25em; }
        .receipt-a4 .r-shop { font-size: 20px; color: #0B1F3A; }
        .doc-title { text-align: center; font-weight: 700; font-size: 1.1em; margin: 6px 0; letter-spacing: .03em; }
        .receipt-a4 .doc-title { text-align: right; font-size: 22px; color: #1E6FD9; margin-top: -60px; }
        .dup { border: 2px solid #000; text-align: center; font-weight: 700; letter-spacing: .25em; padding: 2px; margin: 4px 0; }
        .receipt-a4 .dup { display: inline-block; padding: 4px 16px; float: right; clear: right; }
        .meta { margin: 6px 0; }
        .meta td { padding: 1px 0; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        .items th { text-align: left; border-bottom: 1px dashed #000; font-size: .9em; padding: 2px 0; }
        .items td { padding: 2px 0; vertical-align: top; }
        .receipt-a4 .items th { background: #0B1F3A; color: #fff; padding: 6px; border: 0; }
        .receipt-a4 .items td { padding: 6px; border-bottom: 1px solid #DCE5F2; }
        .r { text-align: right; white-space: nowrap; }
        .sep { border-top: 1px dashed #000; margin: 5px 0; }
        .totals td { padding: 1px 0; }
        .totals .grand td { font-weight: 700; font-size: 1.2em; border-top: 1px solid #000; padding-top: 3px; }
        .receipt-a4 .totals { width: 45%; margin-left: 55%; margin-top: 10px; }
        .foot { text-align: center; margin-top: 8px; }
        .small { font-size: .85em; color: #333; }
        .toolbar { text-align: center; margin: 12px; font-family: "Segoe UI", Arial, sans-serif; }
        .toolbar a, .toolbar button { display: inline-block; margin: 3px; padding: 8px 14px; border-radius: 8px; border: 1px solid #1E6FD9; background: #fff; color: #1E6FD9; text-decoration: none; font-size: 14px; cursor: pointer; }
        .toolbar .on { background: #1E6FD9; color: #fff; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; }
            .receipt-80 .sheet, .receipt-58 .sheet { width: 100%; }
        }
        @page { margin: 0; }
        .receipt-a4 { } @page a4 { size: A4; margin: 0; }
    </style>
    @if(!empty($pdf))
        <style>body { background: #fff; } .sheet { margin: 0; width: 100% !important; min-height: 0 !important; } .receipt-a4 .doc-title { margin-top: 0; }</style>
    @endif
</head>
<body class="receipt-{{ $format }}">
@if(empty($pdf))
    <div class="toolbar">
        @if($formats ?? true)
            @foreach(['80' => '80 mm', '58' => '58 mm', 'a4' => 'A4'] as $f => $label)
                <a href="{{ request()->fullUrlWithQuery(['format' => $f, 'print' => null]) }}" class="{{ $format == $f ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        @endif
        <button type="button" onclick="window.print()">Imprimer</button>
        @yield('toolbar')
    </div>
@endif
<div class="sheet">
    @yield('body')
</div>
@if(!empty($autoPrint))<script>window.addEventListener('load', () => window.print());</script>@endif
</body>
</html>
