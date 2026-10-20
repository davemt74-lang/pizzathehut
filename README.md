# Pizza Lab v1.5.3 — AI Experiment Proposals + Private Evidence Gallery

**Recipe library:** Unlimited formulas, editable process steps, dough batches, chef attribution, evidence, and analytics.

- `proposals.php`: user-initiated Claude proposals with explicit ingredient changes and pending review. Both active roles can approve or reject. Approval atomically creates a new experimental recipe, copies its process steps, and links an experiment. It does not change the baseline recipe or previous batch snapshots. Suggested process edits are guidance only, not automatically executed. Stale proposals are rejected if their source recipe changes.
- `gallery.php`: authenticated, paged media gallery with top, bottom, side, crumb and scanner view filters, plus recipe, batch and test selectors. Images remain in private upload storage and are delivered through authenticated `media.php`.
- Existing installations: back up database, config.php and uploads; deploy new files without overwriting private data, then visit `upgrade.php` as Super Admin. Fresh installs use schema.sql.

**Validation:** Source checks and CI do not replace a live MySQL upgrade, Anthropic API test, or real iPhone camera/voice acceptance. Camera images are not a temperature or crispness sensor.
## v1.5.2 — Evidence-grounded Agent Chat (proposed until PR merged)

Open **Agent Chat** from the main navigation or **Ask agent about batch** from a dough batch. Choose all recipes, any recipe or variation, or a specific hand-labeled batch. Ask a question in ordinary language; Claude Haiku 5.5 (or the saved admin model setting) receives only recorded process and pizza measurements. Reports include observed findings, plausible hypotheses, controlled next tests, evidence IDs, confidence and limitations. Reports are saved privately per asking account with their exact evidence snapshot; no changes are made automatically to formulas, timers, or batch records. A 30-second per-user throttle limits calls. The report covers the most recent 24 selected batches, and up to six recent pizzas per batch; this is a sampling limit, not proof of absence of older data. iPhone/Claude/MySQL deployment remains unverified until tested on a live host.

# Pizza Lab v1.4.1 — Batch Maker Attribution & Dough Timeline

**Standalone PHP 8.2+/MySQL app** for mobile/desktop browser, HTTPS required for camera/microphone.

## Batch made-by and record provenance (v1.4.1)

The **Made by** selector on Create/Edit Dough Batch identifies the chef who actually mixed the dough. It defaults to the signed-in member and can point to a different active team member when a coworker is entering the data. Every new batch separately stores `recorded_by_user_id`, the account that created that batch record; changing Made by does not rewrite the recorder. The batch library, detail, and JSON export show these details. Maker and recorder IDs refer to existing user records by foreign key. Disabling a team account preserves historical batch attribution; accounts referenced by a batch cannot be hard-deleted.

Existing batches are **not retroactively assigned** to anyone. They show `Not recorded` until the user deliberately selects a maker on Edit Batch. Running `upgrade.php` safely adds nullable attribution columns and indexes; it preserves all existing batch recipe snapshots and production evidence. To compare chefs later, aggregate by `made_by_user_id`, never by who last edited a batch. Batch Made by indicates operator-entered attribution, not biometric verification or a timestamped signed production record.

## Accounts and team permissions (v1.4.0)

The one-time installer creates the **Super Admin**. After installation, open **Team members** (visible only to Super Admin) to create as many **Team Member** logins as needed. Set each member's name, email, and initial password (12+ characters), then give the password to that person privately. Members can update their own password under **Voice & settings**.

All signed-in active team members may create and edit recipes, recipe process steps and variations, dough batches, proofing/temperature logs, pizza evidence, uploads, cook timers, voice profiles, and request the Claude analysis using the server's shared API configuration. Only Super Admin may create/edit/disable team accounts, reset their passwords, and modify the shared Anthropic model/API key. No team member can promote themselves or edit the Super Admin account. Disabled users cannot sign in or use authenticated API endpoints, including if their earlier session cookie is still present. Team account changes are recorded without storing plaintext passwords. Authentication checks are enforced server-side, not just by hidden navigation links.

Role migration from v1.3.1: after backing up DB/config/private uploads, upload application files and **sign in as the original installer account**, then run `upgrade.php` once before inviting team members. This safely assigns Super Admin to the original account; newer accounts (if any) remain Team Members. All earlier recipes, batches and data are retained. This is a local deployment candidate and requires MySQL + device verification.

## Seeded recipes (on fresh install or after running upgrade.php)

- **Gelato v1.0 (current):** 10,000 g Caputo flour (specific Caputo type not confirmed), 4,800 g initial ice water, 500 g ice water + 30 g fresh compressed yeast, 500 g ice water + 220 g salt. Total water **5,800 g (58% hydration)**. No honey; oil applied at beginning of covered rest with quantity to be entered when producing batch. Mixing plan: 3 min initial mix, yeast mixture addition, 6 min mixing, salt mixture, 6–8 min more mixing, coat with oil, covered rest 35–50 min, ball and refrigerate. All durations are adjustable; batch records capture actual times.
- **Stonefellows v1.0 (NEW experimental):** 11,793.4 g Caputo Pizzeria Blue flour, 7,144.1 g **total** ice water (60.6%), 284 g salt, 220 g oil, 120 g honey, **85 g fresh compressed yeast**. Proposed 24 h refrigerated bulk proof split into two shallower bins, then divide into dough balls and refrigerate 48 h more. User-specified mixing sequence: combine the water, salt and fresh yeast, then add flour and mix 6 minutes; add the oil and honey, and mix another 8 minutes. Record actual temperature/times per batch. Water reserved for yeast/honey/salt must be drawn from the stated 7,144.1 g total—not extra.

Both recipes use editable process steps (`Recipes & variations → Process timeline`). Duplicating a recipe copies its process template. New batches snapshot the formula AND process so later recipe edits do not retroactively rewrite batch evidence. Existing pre-v1.3 batches with no saved process snapshot remain unknown rather than being assigned a fictional history.

## Batch tracking workflow

1. Open **Dough batches → Create batch**; choose Gelato v1.0 or Stonefellows v1.0. Write the generated `PL-XXXXXX` code on tape.
2. Enter measured mixing/bulk temperatures when available. Open the batch to see its frozen step-by-step process, with target vs actual start/finish time, amount (e.g. actual oil grams), temperature and notes.
3. **Add split container** for each distinct dough mass; each gets a short code such as `PL-XXXXXX-1`. Record initial dough weight/center temperature per container.
4. **Log dough temperature** daily on each container (or at whole-batch level), with observation timestamp and notes.
5. **Log balling / use** as actual counts/weights and dates. Separately log **waste** in grams, by reason and optional container. Partial dough use does not automatically close the batch.
6. Record optional pizza evidence linked to the batch (sauce and cheese weight, 1–10 moisture, stone temperature, cook time, rise/color/crispness ratings, photos). Batch analytics reflect only logged measurements.
7. Use **Export complete report (.json)** on the batch page to download a historical record of its formula, immutable process template, actual steps, temperatures, containers, pizza evidence, usage, waste, and analytics.

### Initial installation

Back up before updates. Upload the full ZIP onto a private HTTPS PHP 8.2+ server with PDO MySQL, cURL, Sodium, fileinfo and JSON. For a fresh install open `install.php`, provide MySQL settings and create the first administrator. Use a dedicated database account. Lock down the `private/` directory and the generated `config.php`; Apache deny rules are included, nginx requires its own deny rules. The app stores its Claude API key encrypted server-side; configure via Settings.

### Upgrading from v1.1/v1.2

Back up **database + `config.php` + `private/uploads`**. Replace application files without overwriting those two persistent paths. Sign in and run `upgrade.php` once, then verify the named recipes and new steps. The idempotent upgrade creates new tables, adds frozen process snapshots, and seeds missing named recipes. It renames and fills the unedited legacy current-recipe placeholder or the known 85 g trial only if its original seed values still match. Otherwise it creates a separate named recipe, preserving user-modified legacy formulas.

### Important limitations

- **No iPhone thermal sensor access**; dough/stone temperatures are entered from a probe/heat gun. Live camera provides visual cues, not calibrated temperature measurements.
- **All batch measurements except choosing a recipe are optional**. Missing values remain unknown in analysis. Oil quantity for Gelato v1.0 is purposely unset.
- Dates and times are entered in server-local time in this version. Verify server time zone before production; do not assume cross-time-zone synchronization.
- Claude requests, voice commands, camera permissions and timers require a supported active HTTPS browser. Voice timer commands work in foreground only; notifications are not guaranteed on lock screen.
- No automated food safety decisions; follow workplace refrigeration and food-safety rules.
- Source checks run without a MySQL service, iPhone device, or live Claude key. This is a **deployment candidate**, not full live production certification.

## Earlier feature coverage

## Dough batches

Each batch is tied to one **required** recipe or variation. Navigate to **Dough batches → Create dough batch**, choose its recipe, optionally enter a mixing timestamp, balling timestamp, container count and temperatures, and save. The server generates a unique, easy-to-write label such as `PL-K7M4X2`. Write it on masking tape and stick it on the container. If you split one mix into two bins, label them `CODE 1/2` and `CODE 2/2` to keep them under the same batch. The code is not a QR code.

The app takes a snapshot of the recipe when the batch is created. Editing the original recipe does not rewrite the batch's historical formula. If a batch already has linked pizza tests, the recipe cannot be changed. Recipe variations should be cloned as separate recipes and batches. Batch stage and events (refrigerated, balled, ready, used, temperature observations) may be updated. Event timestamps from the browser are entered in **server-local time** in this version; verify the server time zone before use.

When recording pizza evidence, choosing a batch automatically locks the pizza's recipe to the batch's recipe; the API rejects contradictory pairings. You may still record evidence without a batch or recipe. Within each batch, analytics show number of tests, actual averages of recorded values (not assumed defaults), and **optional chef-reported 1–10 rise, crispness and bottom-color scores**. The Claude vision prompt receives batch timeline and recipe snapshot when available. Summary statistics are correlations, not proven causes.

**Upgrading from v1.1.0:** Back up your database, overwrite the application files without replacing `config.php` or `private/uploads`, sign in and visit `upgrade.php` once; upgrades are idempotent. Fresh installs use `install.php`. Deleting or editing existing records is not part of this migration.

## PHP/MySQL application and setup

A mobile-first, private pizza dough development and wood-fired oven evidence system.

## Install

1. Create an HTTPS-enabled PHP 8.2+ hosting location. Install/enable PDO MySQL, cURL, Sodium, fileinfo, and JSON. MySQL or MariaDB is required. For uploads, set `upload_max_filesize=16M`, `post_max_size=20M` (or higher). The web server process needs write access to the app folder for creating `config.php` on first installation, and to `private/uploads` afterwards.
2. Upload the *contents* of this directory into your desired web root, or upload the ZIP then extract. **Do not expose `private/`, `config.php`, `schema.sql` as downloads.** Apache `.htaccess` protection is provided. For nginx, configure `location ^~ /private/ { deny all; }` and deny `/config.php`, `/bootstrap.php`, `/schema.sql`; ensure PHP files execute rather than download.
3. Open `https://your-domain/install.php`. Enter MySQL host, database name, user/password, and create the initial administrator. The installer will try to create the database if the MySQL user has sufficient privilege, or use an existing database. It installs the schema and seeds Gelato v1.0 and Stonefellows v1.0 with their process templates.
4. Remove write access to the application code/config after installation. Configure a dedicated database account and TLS/HTTPS. Add your Claude key under Settings; the key is encrypted at rest with a random, local Sodium secret.
5. On iPhone Safari, open the HTTPS app, grant camera and microphone permissions when needed, and optionally Add to Home Screen. Start an evidence record **before** live scan/recording.

### Upgrading an existing installation

Back up your database, `private/uploads`, and `config.php`. Upload the new files without overwriting `config.php` or private media, then sign in and visit `upgrade.php` to migrate previously mandatory evidence fields to optional fields, retain cheese moisture and voice profiles, and add new batch tables, batch-to-recipe linkage, and chef ratings. Fresh installations do not need to run upgrade.

### Functional scope

- Admin login / one-time installer; protected media and encrypted Claude key.
- Recipe library, complete editable formulas, baker's percentages, duplicate-to-variation function.
- Gelato v1.0 complete current work formula with unknown oil-coating quantity recorded per batch.
- Stonefellows v1.0 experimental formula uses 85g compressed yeast and proposes 24h cold bulk + 48h balled.
- Evidence record for each pizza: all fields are optional, including recipe association and label (auto-generated), pizza size, ball weight, sauce grams, cheese grams, perceived cheese moisture 1–10, dough age, measured stone temperature, cook seconds, oven traffic load, and notes. Missing data remains NULL and is never silently filled with typical values. The AI explains which missing measurements limit its recommendations.
- Camera top/bottom photos, file uploads, iPhone microphone note recording, audio playback. Safari codec compatibility is feature-detected; no transcription in v1.
- Timer per selected test in active browser tab, with manual and **voice-activated** start/stop commands, pause/reset, adjustable pull-back/rotate and time-left announcements, spoken alerts, and event-source/profile attribution. Voice profile stores chef label, wake phrase, recognition language, preferred voice/rate and optional start alias. It does NOT biometrically verify or identify speakers.
- Live camera feed + manual scan + opt-in snapshot-driven Claude vision every 30 seconds, max 12 stored scan images per evidence test, max 12 live AI calls per evidence per hour, at least 20 sec apart. Not frame-by-frame video AI and not a thermal sensor. Camera frames are transmitted only on explicit scan or after enabling auto-scan. Costs depend on Claude account pricing.
- Stone heat-gun log with per-pizza readings, elapsed time and first recovery to within 10°F of measured launch temperature. Reads are not automatic and do not infer exact floor heat-flux.
- AI evidence analysis of up to three latest top/bottom/side photos, including limitations and suggestions; results persist per test.

### Real-time/browser limitations

- Safari web APIs do **not** expose an iPhone's numerical device temperature or an oven thermal reading.
- A standard iPhone camera cannot measure stone temperature; enter IR heat-gun readings manually. Different external IR devices require their own integration path (e.g., network bridge); no such integration is included.
- Live observations are *periodic snapshots* interpreted by a cloud AI service, not validated continuous frame analysis, internal doneness detection, or a food-safety determination.
- iOS can pause JavaScript timers, speech synthesis, and the camera if the screen locks or Safari moves to background; this release supports **foreground in-app reminders only**, not reliable push alarms while locked.
- Voice synthesis and recognition can conflict with camera/audio on some iOS versions; verify on your actual devices. Safari may end recognition sessions and cannot guarantee background listening. Press **Enable voice commands** inside Live Scanner while the page is open and microphone permission is granted.
- With the default profile, say **“Pizza, start timer”** or **“Pizza, stop timer”**. **“Pizza, rotate”** and **“Pizza, time left: …”** are agent-generated announcements, not accepted inbound user commands. Scheduled pull-back is a prompt to *check the visible puff*, never a claim the camera has detected it. The voice profile associates events with the selected account; it does not verify who spoke.
- Never put the phone next to flame or inside the oven. Protect it from radiant heat. Use the phone at a safe distance.
- AI API runs on your PHP host; server must allow outbound HTTPS to `api.anthropic.com`.

## Smoke tests

0. Create a new dough batch from the 85 g recipe. Copy its code onto two bin labels. Log balling, refrigeration and temperature events. Create pizza evidence from the batch and confirm the recipe fills itself. Try to submit a mismatched recipe using the API; it must reject the conflict. Edit the original recipe afterwards and verify the historical batch snapshot is unchanged. Test recipe filtering and the batch analytics page.

1. Fresh installation; first admin sign-in; no second install allowed.
2. View recipe variations, edit original current work recipe with *actual* quantities, duplicate an experimental recipe, verify percentages.
3. Create test evidence; upload top and bottom images, record an audio note, play it back.
4. Enter launch and finish/recovery stone readings, verify elapsed and recovery estimate.
5. Open live scanner with an active evidence record; allow HTTPS camera; manually snapshot to top/bottom; scan with Claude key; review findings.
6. Create a test with all evidence fields blank; ensure unknown remains unknown. Enter optional sauce weight, cheese weight, and a 1–10 moisture rating; save and reopen.
7. Configure a voice profile. On an HTTPS iPhone, enable the microphone in Live Scanner and say “Pizza, start timer”; then “Pizza, stop timer”. Confirm that “Pizza, rotate” and “Pizza, time left” are only announcements and cannot start/stop the timer. Check voice/source attribution in the event log.
8. Start an eight-minute timer, check visual/voice reminders, pause/reset, complete and inspect evidence.
9. Test on a real iPhone over HTTPS; validate camera/audio format support and tab foreground behavior.

## Security and deployment notes

- All mutation endpoints require session authentication and CSRF protection. Login uses password_hash and regenerates the PHP session ID.
- Media is stored with randomized names under `private/uploads/` and served only via `media.php` to a logged-in user.
- Server holds the API secret; the browser receives only a configured/unconfigured state.
- This is a **single-admin MVP**; complete real-device, database-backed acceptance testing is required before use in production. Multiple users/roles, externally authenticated devices, continuous background recording, push notifications, raw streaming computer vision, and browser-connected Bluetooth thermometers are not included.
- Rate limits are per evidence session, not full account/billing budgets; monitor Claude account usage.
- Use a dedicated private HTTPS host, never public shared credentials. Back up database + `private/uploads` + `config.php` together. A future hardened production iteration should include login brute-force protection and audit-logging before exposing the site broadly to the internet.

## v1.5: Experiments

The Experiments & analytics tab supports an unlimited number of recipes. Create an experiment from any source recipe, specify the objective and hypothesis, and the app creates a linked editable variation containing the source formula and its step timeline. Record dough batches and pizza evidence against the variation, then compare the source and variation using measured crispness, rise, bottom color and test counts. Correlation does not prove causation; missing readings stay null. Existing recipe and batch history is untouched. Full details in RELEASE_NOTES.md.

## v1.5.4 — Shift/workday tracking and photo comparison

**Workdays:** open `workdays.php` to create a dated shift, record the specific batch and action with its actual time, and link each pizza test baked that day. Batches can appear on multiple days; each pizza has at most one linked workday. Workdays deliberately do not infer usage from batch creation timestamps. New rows and historical unlinked pizzas remain unassumed. Team members and Super Admin have equal production recording access.

**Crust comparison:** open `visual_compare.php` to choose two pizza tests and a matching camera angle; compare actual photos and optional scored measurements. A user may request a rate-limited Claude visual comparison that preserves an evidence snapshot and the historical recipe used by each batch. Without a photo for both selected pizzas or without a configured server-side key, no AI call occurs. Claude can identify visible patterns, but not objectively determine taste, internal temperature or physical crispness from photographs.

**Installation:** `schema.sql` includes the four new tables; existing deployments should back up data, deploy without replacing `config.php` or uploads, and run `upgrade.php` as Super Admin. Historical pizza records are not assigned an invented shift or owner.
