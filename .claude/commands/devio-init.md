---
description: Seed the devio engineering context (.claude/context + context-first CLAUDE.md) into a workspace
---

Scaffold the devio engineering standard into a target workspace so every future session reads it first.

Target directory: `$ARGUMENTS` (defaults to the current workspace when empty).

Do this:

1. **Read `.claude/context/RULES.md` first** — everything below complies with it.
2. If `scripts/devio-init.sh` exists in this repo, run it and report what it copied:
   ```bash
   bash scripts/devio-init.sh "$ARGUMENTS"
   ```
3. If the script is unavailable (a bare workspace with no standards source), create `.claude/context/` with `RULES.md`, `ARCHITECTURE.md`, `SCHEMA.md`, `DESIGN.md`, and `PRD.md` following the devio standard, plus a context-first `CLAUDE.md` that instructs reading `.claude/context/RULES.md` before any code.
4. **Never overwrite an existing `CLAUDE.md`** — merge the "read `.claude/context/` first" pointer into it instead.
5. Confirm to the user: `.claude/context/` is present, `CLAUDE.md` points to it, and RULES.md (SOLID/DRY/KISS + delivery bar) is the first thing read before writing code.
