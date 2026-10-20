# Pizza Lab v1.5.3 — Release notes

- Add reviewed, pending AI formulation proposals based on any source recipe and recorded test data.
- Approval creates a new variation; rejection and approval preserve attribution.
- Numeric gram adjustments are allowlisted, validated, and checked against the original recipe fingerprint.
- Add private image gallery with view angle, recipe, batch and test filters.
- Add optional crumb photo upload and include it in saved image analysis.
- Add idempotent upgrade migration for `ai_recipe_proposals`.
- Pending deployment acceptance: real MySQL, Claude, and iPhone Safari.

## v1.5.4 — Workdays & visual crust comparison
- Dedicated multi-day Workday Tracker. The same batch can be logged on mixing, balling, proofing and service days; every activity has an explicit date, actor and notes.
- Workdays can link actual pizza tests for true per-shift metrics without assuming the pizza was baked on its database creation date.
- Optional pizza evidence stays optional; averages show counts of measured ratings.
- Side-by-side photographic comparisons by angle (top, bottom, side, crumb); private authorized image delivery.
- User-triggered Claude comparison of two pizza photos; saved evidence snapshots, source batch recipes, temperature and fermentation history and recorded workdays. AI describes visible differences and distinguishes measured data from hypotheses.
- No automatic editing of recipes; no fake temperature or crispness measurements from images.
- Install/upgrade schema tables: `workdays`, `workday_batch_events`, `workday_pizzas`, `visual_comparisons`. Existing records are untouched.
- Run `upgrade.php` as Super Admin after backing up your database and private uploads. Existing evidence remains unlinked until explicitly assigned.

Source-only validations do not certify the live database migration, Claude response or real iPhone camera.
