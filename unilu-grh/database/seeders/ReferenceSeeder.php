<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\DocumentType;
use App\Models\Grade;
use App\Models\JobFunction;
use App\Models\KinshipDegree;
use App\Models\Mandate;
use App\Models\OrgUnit;
use App\Models\PrimeType;
use App\Models\Role;
use App\Models\SituationType;
use App\Models\StaffCategory;
use App\Models\SupportType;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Référentiels de départ, tous modifiables dans « Paramétrage ». Aucun montant n'est fixé : taux de primes et montants
 * d'aides sont à définir par l'administration. Les % de primes par situation sont des HYPOTHÈSES à valider (voir docs).
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $cat = fn ($family, $code, $name, $order) => StaffCategory::query()->firstOrCreate(['code' => $code], ['family' => $family, 'name' => $name, 'sort_order' => $order]);
        $acad = $cat('academic', 'CORPS_ACAD', 'Corps académique', 1);
        $sci = $cat('scientific', 'CORPS_SCI', 'Corps scientifique', 2);
        $adm = $cat('administrative', 'ADM', 'Personnel administratif', 3);
        $tech = $cat('administrative', 'TECH', 'Personnel technique', 4);
        $cat('administrative', 'OUV', 'Personnel ouvrier', 5);
        $cat('administrative', 'APPUI', 'Personnel d’appui', 6);
        $cat('administrative', 'SERV', 'Personnel de service', 7);

        $grade = fn ($family, $catId, $code, $name, $rank, $mandates = false) => Grade::query()->firstOrCreate(['code' => $code], ['family' => $family, 'staff_category_id' => $catId, 'name' => $name, 'rank' => $rank, 'has_mandates' => $mandates]);
        $grade('academic', $acad->id, 'PE', 'Professeur émérite', 40);
        $grade('academic', $acad->id, 'PO', 'Professeur ordinaire', 30);
        $grade('academic', $acad->id, 'PF', 'Professeur full', 20);
        $grade('academic', $acad->id, 'PA', 'Professeur associé', 10);
        $grade('scientific', $sci->id, 'CT', 'Chef de travaux', 20);
        $ass = $grade('scientific', $sci->id, 'ASS', 'Assistant', 10, true);
        // Grades administratifs (servent aussi de « catégorie administrative » des assistants de recherche)
        foreach ([['DIR', 'Directeur', 60], ['CD', 'Chef de division', 50], ['CB', 'Chef de bureau', 40], ['AB1', 'Attaché de bureau de 1re classe', 30], ['AB2', 'Attaché de bureau de 2e classe', 25], ['AGB1', 'Agent de bureau de 1re classe', 20], ['AGB2', 'Agent de bureau de 2e classe', 15], ['AA', 'Agent auxiliaire', 10], ['HUI', 'Huissier', 5]] as [$c, $n, $r]) {
            $grade('administrative', $adm->id, $c, $n, $r);
        }
        $grade('administrative', $tech->id, 'TECHN', 'Technicien', 20);
        // Mandats d'assistant : durée laissée vide (à paramétrer selon la réglementation applicable)
        Mandate::query()->firstOrCreate(['code' => 'ASS_M1'], ['grade_id' => $ass->id, 'name' => 'Premier mandat', 'sequence' => 1]);
        Mandate::query()->firstOrCreate(['code' => 'ASS_M2'], ['grade_id' => $ass->id, 'name' => 'Deuxième mandat', 'sequence' => 2]);

        foreach ([
            ['RECT', 'Recteur', 'administrative', true], ['VR', 'Vice-recteur', 'administrative', true], ['SGA', 'Secrétaire général académique', 'administrative', true],
            ['SGAD', 'Secrétaire général administratif', 'administrative', true], ['DOY', 'Doyen de faculté', 'administrative', true], ['VDOY', 'Vice-doyen', 'administrative', true],
            ['SECFAC', 'Secrétaire de faculté', 'administrative', true], ['CDEP', 'Chef de département', 'administrative', true], ['DIRC', 'Directeur de centre de recherche', 'administrative', true],
            ['CSERV', 'Chef de service', 'administrative', true], ['BIBL', 'Bibliothécaire', 'administrative', false], ['ENS', 'Enseignant', 'scientific', false],
            ['CHERCH', 'Chercheur', 'scientific', false], ['AGADM', 'Agent administratif', 'administrative', false], ['TECHF', 'Technicien', 'technical', false],
            ['OUVF', 'Ouvrier', 'technical', false], ['CHAUF', 'Chauffeur', 'technical', false], ['GARD', 'Agent de sécurité', 'other', false],
        ] as [$c, $n, $k, $resp]) {
            JobFunction::query()->firstOrCreate(['code' => $c], ['name' => $n, 'kind' => $k, 'is_responsibility' => $resp]);
        }

        // Types de situations : % de primes = HYPOTHÈSE initiale, modifiable par l'administration (règle 6).
        foreach ([
            ['CONGE_ANNUEL', 'Congé annuel', 'leave', 'on_leave', 100, false, false, true, null],
            ['CONGE_MALADIE', 'Congé de maladie', 'leave', 'on_leave', 100, false, false, true, null],
            ['CONGE_MATERNITE', 'Congé de maternité', 'leave', 'on_leave', 100, false, false, true, null],
            ['CONGE_EXCEPT', 'Congé exceptionnel', 'leave', 'on_leave', 100, false, false, true, null],
            ['DISPONIBILITE', 'Mise en disponibilité', 'availability', 'available', 0, true, false, true, null],
            ['SUSP_ADMIN', 'Suspension administrative', 'suspension', 'suspended', 0, false, false, true, null],
            ['SUSP_DISC', 'Suspension disciplinaire', 'suspension', 'suspended', 0, false, false, true, null],
            ['RETRAITE', 'Retraite', 'end', 'retired', 0, true, true, false, null],
            ['DEMISSION', 'Démission', 'end', 'resigned', 0, true, true, false, null],
            ['DEPART', 'Départ définitif (autre motif)', 'end', 'departed', 0, true, true, false, null],
        ] as [$c, $n, $k, $st, $pct, $closes, $ends, $needEnd, $max]) {
            SituationType::query()->firstOrCreate(['code' => $c], ['name' => $n, 'kind' => $k, 'resulting_status' => $st, 'prime_percent' => $pct, 'closes_assignment' => $closes, 'ends_employment' => $ends, 'requires_end_date' => $needEnd, 'max_days' => $max]);
        }

        // Types de primes : AUCUN taux n'est créé (montants à définir dans Paramétrage › Taux de primes).
        PrimeType::query()->firstOrCreate(['code' => 'PRIME_MENS'], ['name' => 'Prime mensuelle', 'periodicity' => 'monthly', 'prorata' => true]);
        PrimeType::query()->firstOrCreate(['code' => 'PRIME_INST'], ['name' => 'Prime institutionnelle par tranche', 'periodicity' => 'tranche', 'prorata' => true]);

        foreach ([['AGENT', 'Agent lui-même (décès de l’agent)'], ['CONJOINT', 'Conjoint'], ['ENFANT', 'Enfant'], ['PERE', 'Père'], ['MERE', 'Mère'], ['AUTRE', 'Autre membre de la famille']] as [$c, $n]) {
            KinshipDegree::query()->firstOrCreate(['code' => $c], ['name' => $n, 'support_amount' => 0]);
        }
        SupportType::query()->firstOrCreate(['code' => 'DEUIL'], ['name' => 'Assistance en cas de décès d’un proche', 'uses_kinship' => true]);
        SupportType::query()->firstOrCreate(['code' => 'DEUIL_AGENT'], ['name' => 'Assistance à la famille d’un agent décédé', 'uses_kinship' => true]);
        SupportType::query()->firstOrCreate(['code' => 'MALADIE'], ['name' => 'Soutien en cas de maladie', 'uses_kinship' => false]);
        SupportType::query()->firstOrCreate(['code' => 'EXCEPT'], ['name' => 'Assistance exceptionnelle', 'uses_kinship' => false]);
        SupportType::query()->firstOrCreate(['code' => 'AUTRE'], ['name' => 'Autre aide sociale', 'uses_kinship' => false]);

        foreach ([
            ['ENGAGEMENT', 'Acte d’engagement', true], ['NOMINATION', 'Décision de nomination', true], ['PROMOTION', 'Décision de promotion', true],
            ['DIPLOME', 'Diplôme', true], ['ATTESTATION', 'Attestation', true], ['CONTRAT', 'Contrat', true], ['DECISION', 'Décision administrative', false],
            ['CONGE', 'Document relatif aux congés', true], ['MUTATION', 'Document relatif aux mutations', true], ['SUSPENSION', 'Document relatif aux suspensions', false],
            ['DECES', 'Acte ou certificat de décès', false], ['IDENTITE', 'Pièce d’identité', false], ['PHOTO', 'Photo d’identité', false], ['AUTRE', 'Autre document', false],
        ] as [$c, $n, $v]) {
            DocumentType::query()->firstOrCreate(['code' => $c], ['name' => $n, 'visible_to_agent' => $v]);
        }

        foreach ([
            ['immovable', 'BAT', 'Bâtiment'], ['immovable', 'IMM', 'Immeuble'], ['immovable', 'LOC', 'Local'], ['immovable', 'SALLE', 'Salle de cours'],
            ['immovable', 'BUR', 'Bureau'], ['immovable', 'LABO', 'Laboratoire'], ['immovable', 'RES', 'Résidence universitaire'], ['immovable', 'INFRA', 'Autre infrastructure'],
            ['movable', 'BANC', 'Banc'], ['movable', 'TABLE', 'Table'], ['movable', 'CHAISE', 'Chaise'], ['movable', 'ARM', 'Armoire'],
            ['movable', 'ORDI', 'Ordinateur'], ['movable', 'IMPR', 'Imprimante'], ['movable', 'MATLAB', 'Matériel de laboratoire'], ['movable', 'VEH', 'Véhicule'],
            ['movable', 'MOTO', 'Moto'], ['movable', 'EQT', 'Équipement technique'],
        ] as [$k, $c, $n]) {
            AssetCategory::query()->firstOrCreate(['code' => $c], ['kind' => $k, 'name' => $n]);
        }

        OrgUnit::query()->firstOrCreate(['code' => 'RECT'], ['type' => 'rectorate', 'name' => 'Rectorat']);

        if (! User::query()->where('username', 'admin')->exists()) {
            $password = env('INITIAL_ADMIN_PASSWORD') ?: Str::password(16);
            $admin = User::create(['name' => 'Administrateur', 'username' => 'admin', 'password' => $password, 'must_change_password' => true]);
            UserRoleAssignment::create(['user_id' => $admin->id, 'role_id' => Role::query()->where('slug', 'super_admin')->value('id')]);
            if (! env('INITIAL_ADMIN_PASSWORD')) {
                $this->command?->warn("Compte « admin » créé. Mot de passe temporaire (à changer à la première connexion) : {$password}");
            }
        }
    }
}
