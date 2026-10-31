# Résultats des tests et limites connues

## 1. Exécution

```bash
php artisan test
```

Dernier résultat : **44 tests réussis, 620 assertions** (PHPUnit, base SQLite en mémoire,
PHP 8.3.6). Style vérifié avec Laravel Pint.

## 2. Correspondance avec les critères d'acceptation

| Critère du cahier des charges | Test(s) |
|---|---|
| Refus d'accès hors habilitation | `HttpAccessTest::test_refus_d_acces_hors_habilitation` |
| Administrateur technique sans approbation financière | `test_cumul_administration_technique_et_approbation_interdit` |
| Périmètre faculté, protection contre la manipulation d'identifiants (IDOR) | `test_perimetre_par_faculte_contre_la_manipulation_d_identifiants` |
| Affichage des étudiants après filtrage faculté/promotion | `test_affichage_des_etudiants_apres_filtrage_faculte_et_promotion` |
| Saisie d'un bordereau réparti (HTTP de bout en bout) | `test_saisie_http_d_un_bordereau_reparti` |
| Connexion 2FA, mot de passe temporaire | `test_connexion_avec_second_facteur`, `test_mot_de_passe_temporaire_doit_etre_change`, `SyncSecurityBackupTest::test_second_facteur_totp` (vecteurs RFC 6238) |
| Fonctionnement local sans ressource externe | `test_aucune_ressource_externe_fonctionnement_local` |
| Vérification publique avec données minimales ; annulation | `test_verification_publique_d_un_document_avec_donnees_minimales` |
| Import Excel valide, réimport sans doublon | `StudentImportTest::test_import_excel_valide_puis_repete_sans_doublon` |
| Import avec erreurs par ligne, confirmation explicite | `test_import_avec_erreurs_par_ligne_et_confirmation_explicite` |
| Mise à jour d'un dossier existant uniquement sur option | `test_mise_a_jour_d_un_dossier_existant_uniquement_sur_option` |
| Tarifs distincts selon la promotion | `FeesAndChargesTest::test_tarifs_distincts_selon_la_promotion` |
| Créances selon le statut (nouveau/ancien/finaliste), sans doublon | `test_creances_selon_le_statut_sans_doublon` |
| Tranches = montant | `test_tranches_doivent_correspondre_au_montant` |
| Barème approuvé par un tiers et versionné | `test_bareme_approuve_par_une_autre_personne_et_versionne` |
| Exonération approuvée par un tiers avec justificatif | `test_exoneration_approuvee_par_un_tiers_avec_justificatif` |
| Paiement partiel → solde exact | `PaymentWorkflowTest::test_paiement_partiel_laisse_un_solde_exact` |
| Versement réparti = un seul paiement | `test_versement_reparti_entre_plusieurs_frais_reste_un_paiement_unique` |
| Affectations > montant refusées | `test_affectations_superieures_au_montant_refusees` |
| Séparation saisie / contrôle / validation | `test_separation_saisie_controle_validation` |
| Doublons et idempotence | `test_detection_des_doublons_et_idempotence` |
| Cohérence devise / compte autorisé | `test_coherence_devise_et_compte_autorise` |
| Double affectation concurrente refusée | `test_double_affectation_concurrente_refusee_a_la_validation` |
| Contrepassation sans suppression | `test_contrepassation_sans_suppression` |
| Rapprochement exact automatique + confirmation bancaire | `ReconciliationTest::test_rapprochement_exact_automatique_et_confirmation_bancaire` |
| Rapprochement ambigu → décision humaine | `test_rapprochement_ambigu_soumis_a_decision_humaine` |
| Rapprochement partiel et multiple | `test_rapprochement_partiel_et_multiple` |
| Relevés qui se chevauchent non comptés deux fois | `test_releves_qui_se_chevauchent_non_comptes_deux_fois` |
| Ligne incertaine vérifiée avant rapprochement | `test_ligne_incertaine_verifiee_avant_rapprochement` |
| Rapport de couverture | `test_rapport_de_couverture` |
| Compensation d'un excédent | `CreditAndClosingTest::test_compensation_d_un_excedent` |
| Remboursement, pas de double utilisation | `test_remboursement_et_prevention_de_la_double_utilisation` |
| Report sur l'année suivante | `test_report_sur_l_annee_suivante` |
| Pas de compensation entre devises sans taux approuvé | `test_pas_de_compensation_entre_devises_sans_taux_approuve` |
| Clôture : écritures bloquées, report sans duplication | `test_cloture_bloque_les_ecritures_et_reporte_sans_duplication` |
| Reprise de synchronisation sans double paiement | `SyncSecurityBackupTest::test_reprise_de_synchronisation_sans_double_paiement` |
| Conflit explicite, droits revérifiés à la synchronisation | `test_conflit_explicite_et_droits_reverifies` |
| Journal d'audit protégé (détection d'altération) | `test_journal_d_audit_chaine_detecte_toute_alteration` |
| Sauvegarde chiffrée et restauration | `test_sauvegarde_chiffree_et_restauration` |
| Tous les écrans par rôle, exports PDF/Excel, portail | `ScreensTest::test_tous_les_ecrans` |
| Contrôle financier des examens et dérogation | `test_controle_financier_des_examens` |
| Contrat versionné et non respecté | `test_contrat_versionne_et_non_respecte` |

## 3. Vérifications manuelles (navigateur)

Chromium via Playwright sur la base de démonstration : bureau (1366 px), tablette (820 px),
téléphone (390 px, aucun débordement horizontal), aucune erreur JavaScript, cycle hors connexion
complet (voir `SYNCHRONISATION.md`). Captures dans `docs/captures/`.

## 4. Limites connues (non réalisé ou non vérifié)

| Sujet | Situation |
|---|---|
| **MySQL** | Code écrit pour MySQL 8 (migrations, `mysqldump`/`mysql` pour les sauvegardes) mais **tous les tests ont tourné sur SQLite**. Les verrous `lockForUpdate` n'ont pas d'effet réel en SQLite : la protection concurrente est testée logiquement (revalidation au moment de la validation), pas sous charge concurrente réelle. Recette MySQL à faire. |
| **Windows** | Installation non testée sous Windows. |
| **OCR** | Les relevés **PDF texte** sont extraits localement (smalot/pdfparser) avec signalement des lignes incertaines. Les **PDF scannés (images) ne sont pas lus** : aucun moteur OCR n'est intégré. Contournement : demander à la banque un relevé XLSX/CSV ou un PDF texte, ou saisir/importer les lignes via le modèle `modele-releve-bancaire.xlsx`. |
| Formats de relevés | Correspondance de colonnes configurable à l'import ; les formats propres à chaque banque réelle n'ont pas pu être testés (aucun relevé réel disponible). |
| Notifications | Aucun envoi d'e-mail ni de SMS (fonctionnement local sans Internet). |
| Comptabilité générale | Pas d'export vers un logiciel comptable (écritures, plan comptable) ; exports Excel disponibles. |
| Purge des sauvegardes | Rotation/purge non automatisée (procédure manuelle documentée). |
| Chiffrement hors connexion | Les données IndexedDB du poste ne sont pas chiffrées par l'application. |
| Signature électronique | Contrats : génération PDF + archivage du scan signé ; pas de signature électronique qualifiée. |
| Charge | Pas de test de performance ; volumes de démonstration réduits (16 étudiants). |
| Skill « Sites » | Non disponible dans l'environnement ; non utilisé. |
| Skill `ui-ux-pro-max` | Fichier `.claude/skills/ui-ux-pro-max/SKILL.md` absent du dépôt ; charte appliquée depuis le cahier des charges. |
| Accessibilité | Bonnes pratiques appliquées (libellés, contrastes, focus) sans audit formel (WCAG) ni test avec lecteur d'écran. |
| Réglementation | Durées de conservation proposées à titre indicatif, à valider juridiquement. |
