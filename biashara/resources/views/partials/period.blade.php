{{-- Filtre de période réutilisable : $from, $to (Carbon) --}}
<div>
    <label class="form-label small mb-1">Du</label>
    <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
</div>
<div>
    <label class="form-label small mb-1">Au</label>
    <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
</div>
