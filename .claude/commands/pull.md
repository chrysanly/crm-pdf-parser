---
description: Fetch and merge origin/prototype; on conflict, stash only the conflicting files and pull all
allowed-tools: Bash(git fetch:*), Bash(git merge:*), Bash(git diff:*), Bash(git stash:*), Bash(git status:*), Bash(git rev-parse:*)
---

Pull the latest from `origin/prototype` (the prototype branch) into the current branch. On conflict, set the conflicting files aside in a stash and complete the merge.

Follow these steps exactly:

1. Run `git fetch origin` to update remote refs.

2. Run `git merge origin/prototype`.

3. **If the merge succeeds cleanly** (no conflicts), report what was merged and stop.

4. **If the merge reports conflicts:**
   a. Run `git diff --name-only --diff-filter=U` to list ONLY the conflicting files.
   b. Run `git merge --abort` to undo the half-finished merge (returns the working tree to a clean state with the user's local changes intact).
   c. Stash ONLY the conflicting files, keeping their message:
      `git stash push -m "Conflict: merge conflict issue" -- <each conflicting file>`
   d. Re-run `git merge origin/prototype`. It should now complete cleanly since the conflicting local changes are stashed away.
   e. Report:
      - which files were stashed (and that the stash is named "Conflict: merge conflict issue"),
      - that the merge completed,
      - that the user can recover their changes later with `git stash list` / `git stash pop` and resolve manually.

**Rules:**
- Stash ONLY files that actually conflicted — never stash the whole working tree.
- Do not commit, push, or force anything.
- If `git merge --abort` or the re-merge fails, stop and report the exact git output instead of guessing.
