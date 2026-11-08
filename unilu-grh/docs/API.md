# API v1 (lecture seule)

Base : `https://<serveur>/api/v1`. Authentification : en-tête `Authorization: Bearer <jeton>`. Les jetons sont créés
dans **Administration › Jetons d'API** pour un compte donné ; ils agissent avec **les mêmes permissions et le même
périmètre** que ce compte. Seule l'empreinte SHA-256 du jeton est conservée ; un jeton peut expirer et être révoqué.
Limite : 120 requêtes par minute et par jeton. Aucune donnée bancaire ni cause de décès n'est exposée.

| Méthode et chemin | Permission | Description |
|---|---|---|
| `GET /agents?q=&situation=&par_page=` | `employees.view` (données complètes) ou `employees.directory` (données minimales) | Liste paginée des agents du périmètre |
| `GET /agents/{matricule}` | `employees.view` sur l'agent | Fiche, carrière validée, affectations |
| `GET /structures` | compte authentifié | Structure organisationnelle |
| `GET /statistiques?year=&unit=&family=&category=&status=` | `dashboard.view` | Indicateurs du tableau de bord (périmètre appliqué) |
| `GET /patrimoine` | `assets.view` | Biens (code, désignation, unité, état) |

Exemple :

```bash
curl -H "Authorization: Bearer ugrh_xxxxxxxx" https://grh.unilu.local/api/v1/agents/UNILU-2026-00012
```

Réponses d'erreur : `401` jeton absent, invalide, expiré ou révoqué ; `403` hors habilitation ou hors périmètre ;
`404` inconnu ; `429` limite dépassée. L'API est versionnée (`/v1`) pour permettre des extensions sans rupture.
