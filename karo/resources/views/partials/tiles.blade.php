@php
    // Tuiles d'accès rapide (design commun avec Biashara) — n'affiche que les modules autorisés.
    $tiles = collect([
        ['payments.entry', 'Saisir un bordereau', 'Versement bancaire d’un étudiant', 'bi-receipt', ['payments.create']],
        ['payments.control', 'Contrôler', 'Bordereaux à contrôler et valider', 'bi-check2-square', ['payments.check', 'payments.validate']],
        ['bank.index', 'Rapprocher', 'Relevés bancaires et confirmations', 'bi-bank', ['bank.import', 'bank.reconcile', 'bank.review']],
        ['students.index', 'Étudiants', 'Fiches et situations financières', 'bi-people', ['students.view']],
        ['exams.index', 'Examens', 'Listes d’autorisation financière', 'bi-mortarboard', ['exams.view']],
        ['reports.index', 'Rapports', 'Recouvrement, soldes, retards', 'bi-bar-chart-line', ['reports.view']],
    ])->filter(fn ($t) => collect($t[4])->contains(fn ($p) => can_do($p)))->values();
@endphp
@if($tiles->count() > 1)
<div class="tiles mb-4 no-print">
    @foreach($tiles as $i => [$route, $title, $desc, $icon])
        <a href="{{ route($route) }}" class="tile {{ $i === 0 ? 'primary' : '' }}">
            <span class="ico"><i class="bi {{ $icon }}"></i></span>
            <span><span class="t d-block">{{ $title }}</span><span class="d d-block">{{ $desc }}</span></span>
        </a>
    @endforeach
</div>
@endif
