{{-- Bloc « décision officielle » : numéro, date, autorité, pièce numérisée. Paramètres : $numberName, $required --}}
@php $req = $required ?? true; @endphp
<div class="col-12"><div class="form-section">Décision administrative</div></div>
@include('partials.field', ['name' => $numberName ?? 'decision_number', 'label' => 'Numéro / référence de la décision', 'required' => $req, 'col' => 'col-md-4'])
@include('partials.field', ['name' => 'decision_date', 'label' => 'Date de la décision', 'type' => 'date', 'required' => $req, 'col' => 'col-md-4'])
@if($authority ?? true)@include('partials.field', ['name' => $authorityName ?? 'signing_authority', 'label' => 'Autorité signataire', 'required' => $req, 'col' => 'col-md-4', 'placeholder' => 'ex. Le Recteur'])@endif
@include('partials.field', ['name' => 'document', 'label' => 'Pièce numérisée (PDF, JPG, PNG)', 'type' => 'file', 'col' => 'col-md-8', 'help' => 'Stockée de façon privée, jamais accessible publiquement.'])
@include('partials.field', ['name' => 'document_title', 'label' => 'Intitulé de la pièce', 'col' => 'col-md-4'])
