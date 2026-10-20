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
