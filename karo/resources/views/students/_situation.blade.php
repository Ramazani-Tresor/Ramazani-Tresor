{{-- Situation financière : $situation (BalanceService), $compact (bool) --}}
@php $t = $situation['totals']; @endphp
@forelse($t as $cur => $v)
    <div class="row g-2 mb-2">
        @foreach([['net', 'Net dû (après réductions)', ''], ['settled', 'Réglé et affecté', 'accent'], ['balance', 'Solde dû', 'alert-k'], ['exigible', 'Exigible à ce jour', 'warn-k'], ['credit', 'Crédit disponible', 'accent'], ['pending', 'Paiements en attente', '']] as [$k, $l, $cls])
            <div class="col-6 {{ ($compact ?? false) ? 'col-md-4' : 'col-md-4 col-xl-2' }}"><div class="card kpi {{ $cls }}"><div class="l">{{ $l }}</div><div class="v" style="font-size:1.1rem">{{ money($v[$k], $cur) }}</div></div></div>
        @endforeach
    </div>
    <p class="small text-muted-2 mb-3">{{ $cur }} — créances {{ money($v['charges'], $cur) }}, ajustements approuvés {{ money($v['adjustments'], $cur) }}, validés {{ money($v['validated'], $cur) }}, confirmés banque {{ money($v['bank_confirmed'], $cur) }}{{ \App\Support\Money::isPositive($v['arrears']) ? ', dont arriérés reportés '.money($v['arrears'], $cur) : '' }}. Solde dû et crédit sont présentés séparément.</p>
@empty
    @include('partials.empty', ['text' => 'Aucune créance ni aucun paiement pour cette année.'])
@endforelse
@if(count($situation['lines']))
<div class="table-responsive"><table class="table table-sm table-stack">
    <thead><tr><th>Créance</th><th>Frais</th><th class="num">Montant</th><th class="num">Ajustements</th><th class="num">Réglé</th><th class="num">Solde</th><th>Prochaine échéance</th><th>État</th>@if(!($compact ?? false))<th></th>@endif</tr></thead>
    <tbody>
    @foreach($situation['lines'] as $l)
        @php $c = $l['charge']; @endphp
        <tr>
            <td data-l="Créance" class="small nw">{{ $c->number }}@if($c->origin === 'carried_over')<br><span class="badge-st st-warn">Arriéré {{ $c->carriedFrom?->academicYear?->code }}</span>@endif</td>
            <td data-l="Frais">{{ $c->category->label }}{{ $c->session ? ' — '.$c->session->name : '' }}</td>
            <td data-l="Montant" class="num">{{ money($c->amount, $c->currency) }}</td>
            <td data-l="Ajustements" class="num">{{ \App\Support\Money::isZero($l['adjustments']) ? '—' : money($l['adjustments'], $c->currency) }}</td>
            <td data-l="Réglé" class="num">{{ money($l['settled'], $c->currency) }}</td>
            <td data-l="Solde" class="num fw-600">{{ money($l['balance'], $c->currency) }}</td>
            <td data-l="Échéance" class="small">{{ $l['nextDue'] ? $l['nextDue']->due_date->format('d/m/Y').' ('.money($l['nextDue']->amount, $c->currency).')' : '—' }}</td>
            <td data-l="État"><span class="badge-st st-{{ ['paid' => 'ok', 'partial' => 'info', 'unpaid' => 'muted', 'overdue' => 'danger'][$l['state']] }}">{{ \App\Services\BalanceService::stateLabel($l['state']) }}</span></td>
            @if(!($compact ?? false))
            <td class="text-end nw">
                @if(can_do('adjustments.request') && $c->academicYear->status === 'open')<button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#adjModal" data-charge="{{ $c->id }}" data-label="{{ $c->category->label }} ({{ money($l['balance'], $c->currency) }})" title="Réduction, bourse, exonération"><i class="bi bi-percent"></i></button>@endif
            </td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif
