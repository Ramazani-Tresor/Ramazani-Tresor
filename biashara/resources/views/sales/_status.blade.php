@if($sale->isCancelled())
    <span class="status status-muted">Annulée</span>
@elseif((float) $sale->amount_due > 0)
    <span class="status status-warn">Crédit</span>
@elseif((float) $sale->returned_total > 0)
    <span class="status status-info">Retour partiel</span>
@else
    <span class="status status-ok">Payée</span>
@endif
