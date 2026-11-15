# Runtime boundaries and shared motion implementation plan

**Goal:** isolate developer harness instructions from live business-agent rules, then improve motion/scroll behavior without replacing the PHP application.

**Architecture:** `.agents/` remains developer-only; the existing `AgentInstructionService` loads a fixed role-allowlisted `.bapX/` manifest, rejects symlinks/out-of-root reads and fingerprints actual Markdown. Existing PHP tool authorization remains authoritative. Both existing layouts load one progressive-enhancement motion asset; native scrolling, real controls and reduced-motion/no-JS reading stay intact.

**Source design decisions:** self-authored under the user's explicit execution delegation. Existing `Design.md` branding/content wins. Stageshow is an illustration kit: borrow causal pacing and rehearsal, not React scenes or fake controls. Scrollcraft provides source-grounded scroll/fallback checks; do not convert checkout/admin forms into pinned cinematic stories. No paid tools, generated imagery, model calls, React, video runtime or duplicated page layouts.

## Tasks
- [x] Reproduce latest reviewer failure and rerun the fixed trusted-default-branch audit, distinguishing provider blocks from CI defects.
- [x] Capture baseline public/admin screens and write failing PHP boundary and JavaScript motion tests.
- [x] Add `.bapX/supportagent.md`, `enterpriseagent.md`, `bapXcl/skill.md`, support/enterprise modules and fail-closed role-scoped loader.
- [x] Block direct HTTP access to `.agents/`, `.bapX/` and developer root instructions before static-file dispatch; verify encoded paths and both server configurations.
- [x] Replace old opacity-gated reveals with interruptible observed entrances, contextual panel/message motion, native scroll offsets and accessible return-to-top across both layouts.
- [x] Pause decorative motion offscreen/hidden; honor live reduced-motion changes, no-JS and unsupported APIs; preserve form/table/chat interactions.
- [x] Update content fingerprints, docs, developer skills and generated indexes; run PHP, offline eval, JS and real browser tests at 1280px and 375px.
- [ ] Push a ready-for-review PR, verify exact-head GitHub checks, merge only after acceptance and repeat safe live browser flows.

## Review focus
No arbitrary model/browser-supplied path may load instructions. A Support identity cannot receive Enterprise-only files. Missing instruction files must not fall back to developer docs. Motion must never hide content permanently, trap scrolling, fake a business result or change chart values. API/provider entitlements are not code-level approvals. The original main and any unrelated worktrees remain untouched.

## Verification findings

The historical reviewer failure was fixed by #256; a fresh trusted-default-branch
audit (run 43722328053) succeeded and published the explicit provider/token blocks.
Real browser testing then discovered an unrelated missing BlogService import
causing dashboard HTTP 503; it was isolated, regression-tested and merged as #258.
Motion testing discovered long media filenames widening the mobile page and an
obscured mobile-sidebar toggle. This branch fixes inner grid sizing and adds an
accessible close/backdrop/Escape/focus lifecycle. These are observed defects,
not speculative recommendations.

Local UI acceptance: public and authenticated admin routes at 1280px/375px;
reduced-motion live toggle; no-JS; missing observer/animation APIs; encoded and
plain private paths. The final browser result must be rerun after the last patch
and checked before publishing/merging this branch. No new vendor credential
or paid service has been provisioned.
