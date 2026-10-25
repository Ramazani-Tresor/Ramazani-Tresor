@switch($purchase->status)
    @case('draft')<span class="status status-warn">Brouillon</span>@break
    @case('validated')<span class="status status-ok">Validé</span>@break
    @default<span class="status status-muted">Annulé</span>
@endswitch
