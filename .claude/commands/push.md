---
description: Commit & push the current branch, or cherry-pick specific commits onto a fresh branch cut from a base branch
allowed-tools: Bash(git add:*), Bash(git commit:*), Bash(git push:*), Bash(git diff:*), Bash(git log:*), Bash(git status:*), Bash(git fetch:*), Bash(git checkout:*), Bash(git branch:*), Bash(git cherry-pick:*), Bash(git rev-parse:*)
---

Use the AskUserQuestion tool to present the user with exactly these four clickable options before doing anything:

Question: "What would you like to do?"
Header: "Push options"
Options:
- "Push all" — description: "Stage everything (git add .) then commit and push"
- "Push what is already added" — description: "Commit and push what's already staged"
- "Cherry-pick to a new branch" — description: "Cut a fresh branch from a base branch and cherry-pick only the commits you choose, then push"
- "Cancel" — description: "Abort, do nothing"

If the user chooses **Cancel**, stop immediately and do nothing.

Once the user chooses, follow these steps:

**If "push all":**
1. Run `git add .`
2. Run `git diff --cached --stat` to see what's staged
3. Run `git log -5 --oneline` to match commit message style
4. Write a commit message: short imperative title + bullet points of what changed (simple, short, descriptive)
5. Commit and push to the current branch

**If "push what is already added":**
1. Run `git diff --cached --stat` to see what's already staged
2. Run `git log -5 --oneline` to match commit message style
3. Write a commit message: short imperative title + bullet points of what changed (simple, short, descriptive)
4. Commit and push to the current branch

**If "cherry-pick to a new branch":**
This isolates only the commits you choose onto a brand-new branch cut from a base branch (usually master) — no other history from prototype/dev tags along.

First, help the user identify what to pick, then gather the details:
1. Show the user their recent commits so they can copy the hash(es):
   `git log --oneline -15`
   (To pick from a different branch, run `git fetch origin` first, then `git log --oneline -15 <source-branch>`.)
2. Ask the user for anything they have not already told you:
   - the **base branch** to cut from (e.g. `origin/master`) — this is "the branch I use for master",
   - the **commit hash(es)** to cherry-pick, and which branch they live on,
   - the **new branch name** (e.g. `hotfix/my-fix`).

Then execute:
3. `git fetch origin`
4. If the new branch already exists, replace it: `git branch -D <new-branch>` (ignore error if absent) and `git push origin --delete <new-branch>` (ignore error if absent).
5. Create the new branch from the base: `git checkout -b <new-branch> <base-branch>`
6. Cherry-pick the commit(s), oldest first: `git cherry-pick <hash> [<hash2> ...]`
   - **If cherry-pick reports conflicts:** STOP. Show the conflicting files (`git status`) and tell the user to resolve them, then run `git cherry-pick --continue` (or `git cherry-pick --abort` to bail). Do not force anything.
7. Verify ONLY the intended work landed:
   `git log --oneline <base-branch>..HEAD`  (should list just your cherry-picked commit(s))
   `git diff --name-only <base-branch>..HEAD`  (should list only your files)
8. Push the new branch: `git push origin <new-branch>`
9. Return the user to the branch they started on: `git checkout -`
10. Report the branch, the commit(s) included, and the GitLab MR link from the push output.

**Command the user can run to check their own commits (share this in the report):**
- `git log --oneline -15` — recent commits on the current branch (find the hash to cherry-pick)
- `git log --oneline <base-branch>..HEAD` — confirm exactly which commits sit on top of the base

**Commit message rules:**
- Format: `type: short title` followed by bullet points
- No `Co-Authored-By` line — never add it
- Keep it simple and descriptive

**Rules:**
- Never force-push or force-delete anything the user did not confirm.
- For cherry-pick, only touch the new branch — never rewrite the base or the user's working branch.
