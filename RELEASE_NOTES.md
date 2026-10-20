# Pizza Lab v1.5.9 — In-chat Camera Tracker

- Open/close live pizza camera directly from the sticky Agent Chat footer, without navigating away.
- Reuse existing manual/assisted bounding-box tracker, authenticated image upload, cooking event logs, and Claude visual analysis.
- Send a saved photo preview and Claude observation into the chat conversation with same-origin validated messages.
- Support 'Pizza, take photo' through the chat microphone when the camera is active and a pizza test is selected.
- Explicit camera start and selection are required; closing the panel releases camera/microphone tracks.
- Only the protected embedded tracker may be framed by its own origin; all other pages stay frame-denied.
- Keep an open-full-screen fallback for browsers that restrict embedded camera permissions.

Source/CI checks do not replace live MySQL, iPhone Safari, or Claude API acceptance. No database migration is needed.
