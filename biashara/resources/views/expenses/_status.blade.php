@switch($expense->status)
    @case('approved')<span class="status status-ok">Validée</span>@break
    @case('rejected')<span class="status status-muted">Rejetée</span>@break
    @default<span class="status status-warn">En attente</span>
@endswitch
