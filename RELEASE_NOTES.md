# Pizza Lab v1.5.0 — Recipe experiments & evidence analytics

- Unlimited recipe-agnostic experiment registry: create from any recipe, atomically clone its formula and editable production steps, set hypothesis and objective. Variations are separately named actual recipes and can spawn their own batches.
- Independent, recipe-wide descriptive averages and sample counts for crispness, rim rise and bottom color. Missing values remain unmeasured. Comparisons never claim causation.
- Experiment detail shows recipe formula and linked batches, counts and measured averages for both reference and variation. No invented metrics or AI predictions.
- Existing Gelato v1.0 and Stonefellows v1.0 recipes, production timelines, attribution, photos, optional evidence, timers, and team member permissions preserved.
- Schema adds `pizza_experiments`; `upgrade.php` creates table idempotently for existing installations. Fresh installs use updated schema.
- PHP/JS, legacy contracts and new experiment contract checks pass. Real MySQL, iPhone camera/mic, Claude, migrations on production copy, and UI workflow need live verification.

## Deployment

Back up database, `config.php` and private uploads first. Deploy with existing config/private data preserved. Run `upgrade.php` signed in as super admin. `install.php` is for fresh installations only. This is a standalone local deploy, **not a GitHub-merged application release**.

## GitHub repository integration (CI-only hardening)

- Adds a three-version PHP source validation matrix and Node 22 checks for all local contracts.
- Excludes installer-generated credentials and private media from source control; CI rejects accidental private files.
- CI does not represent an end-to-end MySQL, deployed HTTPS, Anthropic, or iPhone certification.
