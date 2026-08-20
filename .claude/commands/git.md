---
description: Git menu — choose pull, push, or pull and push
allowed-tools: Bash(git fetch:*), Bash(git merge:*), Bash(git diff:*), Bash(git stash:*), Bash(git status:*), Bash(git rev-parse:*), Bash(git add:*), Bash(git commit:*), Bash(git push:*), Bash(git log:*), Bash(git checkout:*), Bash(git branch:*), Bash(git cherry-pick:*), Read
---

Use the AskUserQuestion tool to present the user with exactly these four clickable options before doing anything:

Question: "What git action do you want?"
Header: "Git"
Options:
- "Pull" — description: "Fetch and merge origin/prototype into the current branch (stash conflicts)"
- "Push" — description: "Commit & push, or cherry-pick to a new branch"
- "Pull and Push" — description: "Pull first, then push"
- "Cancel" — description: "Abort, do nothing"

If the user chooses **Cancel**, stop immediately and do nothing.

To keep a single source of truth, this command reuses the existing `/pull` and `/push` procedures:

**If "Pull":**
1. Read `.claude/commands/pull.md` and follow its steps exactly.

**If "Push":**
1. Read `.claude/commands/push.md` and follow its steps exactly (including its own options menu).

**If "Pull and Push":**
1. First, Read `.claude/commands/pull.md` and follow it exactly.
2. Only if the pull finished without leaving the tree in a broken/half-merged state, then Read `.claude/commands/push.md` and follow it exactly.
3. If the pull ran into trouble (e.g. an aborted merge, unresolved state), STOP after the pull and report — do not push.

**Rules:**
- Do exactly what the chosen sub-procedure says; do not invent extra steps.
- Never force-push or discard the user's changes without confirmation.
