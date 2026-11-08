@php
    $gradeOpts = $grades->mapWithKeys(fn ($g) => [$g->id => $g->name.' — '.\App\Support\Labels::status('family', $g->family)])->all();
    $unitOpts = $units->mapWithKeys(fn ($x) => [$x->id => $x->name])->all();
    $posOpts = $positions->mapWithKeys(fn ($p) => [$p->id => ['label' => $p->title, 'group' => $p->org_unit_id]])->all();
    $modal = fn ($id, $title) => compact('id', 'title');
@endphp
@foreach([
    ['career', 'Nomination, promotion ou renouvellement de mandat', route('career.store', $employee)],
    ['assign', 'Nouvelle affectation', route('assignments.store', $employee)],
    ['situation', 'Congé ou situation administrative', route('situations.store', $employee)],
    ['mutation', 'Mutation', route('mutations.store', $employee)],
    ['qualif', 'Diplôme ou travail scientifique', route('qualifications.store', $employee)],
    ['doc', 'Pièce numérisée', route('documents.store', $employee)],
    ['death', 'Enregistrer un décès', route('deaths.store', $employee)],
] as [$id, $title, $action])
<div class="modal fade" id="m-{{ $id }}" tabindex="-1" aria-labelledby="m-{{ $id }}-t" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" method="post" action="{{ $action }}" enctype="multipart/form-data">@csrf
    <div class="modal-header"><h2 class="modal-title fs-5" id="m-{{ $id }}-t">{{ $title }} — {{ $employee->fullName() }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
    <div class="modal-body"><div class="row g-3">
    @switch($id)
        @case('career')
            <div class="col-12"><div class="alert alert-info small mb-0">Aucune promotion n’est automatique : la décision prend effet après validation par une autre personne habilitée, sur la base d’une décision formelle.</div></div>
            @include('partials.field', ['name' => 'type', 'label' => 'Type d’évolution', 'type' => 'select', 'required' => true, 'options' => \Illuminate\Support\Arr::except(\App\Support\Labels::options('career_type'), ['engagement']), 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'effective_date', 'label' => 'Date de prise d’effet', 'type' => 'date', 'required' => true, 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'new_grade_id', 'label' => 'Nouveau grade (actuel : '.($employee->grade?->name ?? '—').')', 'type' => 'select', 'options' => $gradeOpts, 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'new_category_id', 'label' => 'Nouvelle catégorie', 'type' => 'select', 'options' => $categories->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(), 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'new_mandate_id', 'label' => 'Nouveau mandat (actuel : '.($employee->mandate?->name ?? '—').')', 'type' => 'select', 'options' => $mandates->mapWithKeys(fn ($m) => [$m->id => $m->name])->all(), 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'new_mandate_end', 'label' => 'Fin du nouveau mandat', 'type' => 'date', 'col' => 'col-md-6', 'help' => 'Sinon calculée selon la durée paramétrée du mandat.'])
            @include('partials.field', ['name' => 'allow_family_change', 'label' => 'Le nouveau grade relève d’une autre famille (ex. chef de travaux → professeur associé)', 'type' => 'bool', 'col' => 'col-12'])
            @include('partials.decision')
            @include('partials.field', ['name' => 'observations', 'label' => 'Observations', 'type' => 'textarea', 'col' => 'col-12'])
            @break
        @case('assign')
            @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Structure d’accueil', 'type' => 'select', 'options' => $unitOpts, 'required' => true, 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'position_id', 'label' => 'Poste occupé', 'type' => 'select', 'options' => $posOpts, 'col' => 'col-md-6', 'attrs' => 'data-filter-by="org_unit_id"'])
            @include('partials.field', ['name' => 'job_function_id', 'label' => 'Fonction exercée', 'type' => 'select', 'options' => $functions->mapWithKeys(fn ($x) => [$x->id => $x->name])->all(), 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'start_date', 'label' => 'Date de début', 'type' => 'date', 'required' => true, 'col' => 'col-md-3'])
            @include('partials.field', ['name' => 'end_date', 'label' => 'Date de fin (si connue)', 'type' => 'date', 'col' => 'col-md-3'])
            @include('partials.field', ['name' => 'is_primary', 'label' => 'Affectation principale (clôture automatiquement la précédente)', 'type' => 'bool', 'value' => true, 'col' => 'col-12'])
            @include('partials.field', ['name' => 'reason', 'label' => 'Motif', 'required' => true, 'col' => 'col-12'])
            @include('partials.decision', ['required' => false, 'authorityName' => 'authority'])
            @break
        @case('situation')
            @include('partials.field', ['name' => 'situation_type_id', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => $situationTypes->mapWithKeys(fn ($t) => [$t->id => $t->name.' — primes '.$t->prime_percent.' %'.($t->ends_employment ? ' · fin des droits' : '')])->all(), 'col' => 'col-md-12'])
            @include('partials.field', ['name' => 'start_date', 'label' => 'Date de début', 'type' => 'date', 'required' => true, 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'planned_end_date', 'label' => 'Date de fin prévue', 'type' => 'date', 'col' => 'col-md-6', 'help' => 'Obligatoire pour les congés, suspensions et disponibilités.'])
            @include('partials.field', ['name' => 'reason', 'label' => 'Motif / justification', 'type' => 'textarea', 'required' => true, 'col' => 'col-12'])
            @include('partials.decision', ['required' => false, 'numberName' => 'decision_reference', 'authorityName' => 'authority'])
            @break
        @case('mutation')
            <div class="col-12"><div class="btn-group" role="group" aria-label="Nature">
                <input type="radio" class="btn-check" name="kind" id="mk-i" value="internal" checked><label class="btn btn-outline-primary" for="mk-i">Mutation interne (UNILU)</label>
                <input type="radio" class="btn-check" name="kind" id="mk-e" value="external"><label class="btn btn-outline-primary" for="mk-e">Mutation externe</label>
            </div></div>
            <div class="col-12" data-show-when="kind=internal"><div class="row g-3">
                @include('partials.field', ['name' => 'to_unit_id', 'label' => 'Structure d’accueil', 'type' => 'select', 'options' => $unitOpts, 'col' => 'col-md-6'])
                @include('partials.field', ['name' => 'to_position_id', 'label' => 'Poste', 'type' => 'select', 'options' => $posOpts, 'col' => 'col-md-6', 'attrs' => 'data-filter-by="to_unit_id"'])
                <div class="col-12 small text-muted-2">Les droits aux primes restent dus ; l’affectation actuelle est clôturée la veille de la date d’effet.</div>
            </div></div>
            <div class="col-12 d-none" data-show-when="kind=external"><div class="row g-3">
                @include('partials.field', ['name' => 'destination_institution', 'label' => 'Institution d’accueil', 'col' => 'col-12'])
                <div class="col-12"><div class="alert alert-warning small mb-0">À la validation : clôture de l’affectation, statut « Muté vers une autre institution », arrêt des nouvelles primes à la date d’effet et calcul des sommes restant dues. Le dossier reste conservé.</div></div>
            </div></div>
            @include('partials.field', ['name' => 'effective_date', 'label' => 'Date d’effet', 'type' => 'date', 'required' => true, 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'reason', 'label' => 'Motif', 'required' => true, 'col' => 'col-md-6'])
            @include('partials.decision', ['numberName' => 'decision_reference', 'authority' => false])
            @break
        @case('qualif')
            @include('partials.field', ['name' => 'nature', 'label' => 'Nature', 'type' => 'select', 'required' => true, 'options' => \App\Support\Labels::options('qualification'), 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'title', 'label' => 'Intitulé (diplôme, mémoire ou thèse)', 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'field', 'label' => 'Domaine scientifique', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'institution', 'label' => 'Institution de formation', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'defense_university', 'label' => 'Université de soutenance', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'defense_date', 'label' => 'Date de soutenance', 'type' => 'date', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'academic_year_id', 'label' => 'Année académique', 'type' => 'select', 'options' => \App\Models\AcademicYear::query()->orderByDesc('starts_on')->pluck('code', 'id')->all(), 'col' => 'col-md-4', 'help' => 'Déduite de la date si vide.'])
            @include('partials.field', ['name' => 'mention', 'label' => 'Mention', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'supervisor', 'label' => 'Directeur de recherche', 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'org_unit_id', 'label' => 'Faculté / département de rattachement', 'type' => 'select', 'options' => $unitOpts, 'col' => 'col-md-6', 'value' => $employee->activeAssignment?->org_unit_id])
            @include('partials.field', ['name' => 'document', 'label' => 'Justificatif (PDF, JPG, PNG)', 'type' => 'file', 'col' => 'col-12'])
            @break
        @case('doc')
            @include('partials.field', ['name' => 'document', 'label' => 'Fichier (PDF, JPG, PNG)', 'type' => 'file', 'required' => true, 'col' => 'col-12'])
            @include('partials.field', ['name' => 'title', 'label' => 'Intitulé', 'required' => true, 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'document_type_id', 'label' => 'Type', 'type' => 'select', 'options' => $documentTypes->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'col' => 'col-md-6'])
            @include('partials.field', ['name' => 'reference', 'label' => 'Référence', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'document_date', 'label' => 'Date', 'type' => 'date', 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'signing_authority', 'label' => 'Autorité signataire', 'col' => 'col-md-4'])
            @break
        @case('death')
            <div class="col-12"><div class="alert alert-warning small mb-0">L’enregistrement est validé par une autre personne. À la validation : statut « Décédé », clôture de l’affectation, arrêt des nouvelles primes à la date du décès, calcul des arriérés dus aux ayants droit et ouverture d’une procédure de soutien social. Le dossier n’est jamais effacé.</div></div>
            @include('partials.field', ['name' => 'death_date', 'label' => 'Date du décès', 'type' => 'date', 'required' => true, 'col' => 'col-md-6', 'attrs' => 'max="'.now()->toDateString().'"'])
            @include('partials.field', ['name' => 'death_place', 'label' => 'Lieu du décès', 'col' => 'col-md-6'])
            @if(auth()->user()->hasPermission('deaths.cause'))@include('partials.field', ['name' => 'cause', 'label' => 'Cause (uniquement si nécessaire et autorisée — confidentielle, chiffrée)', 'type' => 'textarea', 'col' => 'col-12'])@endif
            @include('partials.field', ['name' => 'contact_name', 'label' => 'Personne à contacter', 'value' => $employee->emergency_contact_name, 'col' => 'col-md-5'])
            @include('partials.field', ['name' => 'contact_phone', 'label' => 'Téléphone', 'value' => $employee->emergency_contact_phone, 'col' => 'col-md-3'])
            @include('partials.field', ['name' => 'contact_relation', 'label' => 'Lien', 'value' => $employee->emergency_contact_relation, 'col' => 'col-md-4'])
            @include('partials.field', ['name' => 'document', 'label' => 'Document officiel de constatation', 'type' => 'file', 'col' => 'col-12'])
            @break
    @endswitch
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>
@endforeach
