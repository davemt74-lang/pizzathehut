# Pizza Lab v1.5.16C — Conversational Dough Production

- Guided dough creation available on request, not a mandatory primary experience.
- Recipe selection resolves one marked current or requires explicit chef choice; ingredient quantities come from saved recipe.
- Confirmed, deduplicated creation freezes the recipe and its process timeline, with maker/recorder attribution.
- Conversational step instructions, confirmed physical step completion, optional note and resumable persisted session state.
- Session access restricted to owner; unique request IDs, server-side locks, stale-preview checks and CSRF-protected writes.
- New additive `chat_batch_sessions` and `chat_batch_step_actions` tables, plus PHP/Node/MySQL/HTTP tests and certified ZIP.
- Explicitly no claim of automatic physical actions, camera proof, closed-browser prompts or live iPhone acceptance. Gelato is separate.

# Pizza Lab v1.5.16B — Proactive Foreground Kitchen Checks

- Scoped to Pizza Lab only; Gelato remains independent.
- Cook target in speech/text commands: "start timer for pizza #12 for 90 seconds".
- Rotation (~40%), pull-back (~75%), and planned-time-elapsed (100%) **check suggestions**; physical actions require chef confirmation.
- Server-side, restart-stable checkpoint evaluation while Agent Chat is foreground-open; deduped agent_alert cooking events and per-account delivery receipts.
- Chat notices and user-enabled spoken reminders; no guarantee for locked phones, closed browsers, or voice autoplay.
- New additive MySQL reminder tables and authenticated HTTP tests; run upgrade.php after backup.

# Pizza Lab v1.5.16A — Persistent Kitchen Activity

- Server-authoritative cooking timer state, UTC elapsed timing and authenticated kitchen snapshot reads.
- Per-pizza serialized, audited start/pause/resume/stop/reset; reject duplicate active starts and conflicting event replays.
- Agent Chat timer status display with reload and device-visible recovery.
- Additive kitchen_activity_timers migration; preserve v1.5.15 actions and private uploads.
- Automated test coverage, distinct from physical iPhone/oven acceptance. Reminders and background notification delivery are not part of Section A.

# Pizza Lab v1.5.15 — Conversational Kitchen Operations

## A. Confirmed voice production logging — PR #26
- Exact batch-code and named-refrigerator temperatures, and confirmed dough balling, all with user/time/source attribution.
- A unique per-user action ID and atomic database transaction prevent duplicate changes. Unsupported or ambiguous actions stay unsaved.
- Added schema and idempotent upgrade path for the new records.

## B. Cooking assistant — PR #27
- Explicit pizza test IDs for timer start/stop and chef-confirmed rotation, pull-back and finish events.
- Spoken confirmations preserve their declared source; reminders and suggestions cannot count as chef-performed actions.

## C. Photo evidence accuracy — PR #28
- Claude live analysis selects the exact saved scan ID associated with the photo capture and validates that it belongs to the selected pizza.
- Chat communicates capture failures and retains manual bounding-box and camera teardown protections.

## D. Authenticated integration and packaging — PR #29
- Disposable MySQL/HTTP tests for CSRF, confirmation gate, deduplicated writes, conflicts, measured values, recorded actor/source, and cooking activities.
- PHP 8.2/8.3/8.4, Node, application package integrity and SHA-256.

**Acceptance boundary:** Real iPhone microphone/camera, kitchen noise, oven operation and live Claude requests remain to be verified on the actual installation. Back up database, config and private uploads; run Super Admin upgrade before production use.

---

## Previous release — v1.5.14 Workday Awareness

## Section A — Recorded workday awareness (PR #23)
- On-page, read-only workday strip: unique day, multiple days requiring selection or no day with confirmed creation.
- Only explicitly logged batch events and linked pizzas are displayed. Rated-only crispness averages preserve null when absent.

## Section B — Evidence-based suggestions (PR #24)
- Conservative next-observation suggestions based on recorded workdays, batch count and pizza ratings, with caveats for missing data and samples below three.
- A user click, not a background process, initiates any Claude request. The bounded workday snapshot is inserted only during user-initiated analysis; no data mutations.

## Section C — Disposable MySQL/HTTP certification (PR #25)
- Authenticated team login; verify no-day, one-day, ambiguous-day, explicit selection and invalid-date behavior.
- Prove an unlinked pizza is not inferred from a batch, linked batches are the only records surfaced and unrated crispness is not zero.
- PHP 8.2, 8.3 and 8.4 source checks, Node contracts and SHA-256-certified deployment package.

**Limitations:** Server/browser integration testing is not a live iPhone or Claude API acceptance test. The client's calendar date is not authoritative payroll/shift evidence. No new schema migration. Back up before upgrading.

---

# Pizza Lab v1.5.13 — Kitchen Reliability & Acceptance

## C. Camera lifecycle and evidence recovery (PR #21)

- A late camera permission grant cannot restart a stopped tracker; unexpected hardware track-end and background transitions release camera access.
- In-flight capture fetches use AbortController; closing the tracker cancels requests. A request already committed by the server may have saved a photo, so the UI explicitly directs the chef to review existing photos before retrying.
- Voice recognition does not silently auto-restart after browser shutdown; the chef must enable it again.

## B. Kitchen microphone reliability (PR #20)

- One active push-to-talk session at a time. Stale browser speech callbacks cannot stop a later session.
- Leaving the active page stops capture, and speech response callbacks are guarded after interruption. Voice/photo command handoff stays intact.

## D. Certification extension

- Node simulated camera-lifecycle regression exercises late permission grants, disconnected tracks and cancellation after upload begins.
- Disposable MySQL upgrade smoke preserves seeded pizza evidence scores and photo metadata while testing backup confirmation.
- CI is not a physical-device or production restoration guarantee. Real iPhone voice/camera and live Claude requests must be accepted separately.

## A. Upgrade safety and repeatable CI
- Upgrade.php now requires a deliberate Super Admin backup acknowledgement before any schema changes; retains authenticated CSRF checks.
- Disposable MySQL and authenticated HTTP CI verify rejection without confirmation and idempotent schema upgrade after acknowledgement.
- Production backups, restoration and physical kitchen testing are required separately.

---

## Prior release — Pizza Lab v1.5.12 — Conversation, Kitchen Voice, Camera, and Certification

## Section A — Natural persistent conversation (PR #14)

- Per-user and evidence-scope-bounded saved reviews.
- Up to six previous turns in context, with 24-record bounded history, short follow-up rate limit, and hourly cost budget.
- Compact conversational message bubbles and optional expanded evidence.

## Section B — Kitchen voice reliability (PR #15)

- Clear push-to-talk microphone states, speech interruption, permission and error handling.
- Clears voice reply attribution after failures; voice photo requests hand off to the camera tracker.
- Web Speech API and HTTPS are required for supported voice use; device/browser behavior is not guaranteed.

## Section C — In-chat camera selection (PR #16)

- Camera evidence auto-selects only when a selected batch has exactly one matching pizza test.
- Ambiguity requires chef selection; no guessing based on the latest pizza.
- Existing private photo upload, bounding box editing, and user-triggered Claude analysis remain unchanged.

## Section D — MySQL and HTTP kitchen certification (PR #17)

- Disposable MySQL 8.0 fresh-schema creation and repeatability checks; seed team and recipe data.
- Authenticated HTTP smoke checks for team sign-in, CSRF, chat page, mic/camera/menu controls, role isolation and same-origin camera embed.
- PHP 8.2, 8.3 and 8.4 with Node regression checks plus reproducible deployment ZIP and SHA-256 verification.

## Deployment and limitations

This is the latest v1.5.12 source and package release. No new DB migration is needed for the v1.5.12 additions. Back up the database, config.php and private uploads. Live Anthropic/Claude requests, production database upgrades, iOS speech recognition and real cooking sessions require separate acceptance on the user's installation.

---

## Prior release — Pizza Lab v1.5.11 — Chat-first team account controls

- Minimal chat canvas; header hamburger and compact profile menu; unchanged sticky microphone, camera, message and send controls.
- On-chat dialogs: My Profile, Voice Settings, Change Password; secure POST logout.
- Profile name/email update, reauthentication for email change, individual voice playback preferences, and secure password change. No admin role escalation.
- No database migration; focused PHP/Node tests, CI and deploy checksum. Live MySQL, iPhone and Claude requests remain unverified.
