# Repository guidance

This repository is the static GitHub Pages backup for the CSDL main website at
https://csdl.postech.ac.kr/. Read README.md for the file map and update workflow.
Keep the site as plain HTML, CSS, JavaScript, and local media. Do not add a build
system or dependencies unless the task requires them. Preserve relative asset
links and the main-site link on all five pages. Use confirmed public information
from the main site or its source project when updating members and publications.
Use `php scripts/sync-content.php fetch` for these updates, or the documented
sudo exporter piped into the normal-user `import` command. Do not edit generated
HTML blocks by hand. Commit the public snapshot and generated pages together.
Keep the historical archive pages unchanged. See README.md for source scope.

Follow these guidance while implementing steps:
1. write extremely simple code, it should be "skimmable" and you should still be able to understand it
2. minimize possible states by reducing number of arguments, remove or narrow any state
3. use discriminated unions to reduce number of states the code can be in
4. exhaustively handle any objects with multiple different types, fail on unknown type
5. don't write defensive code, assume the values are always what types tell you they are; avoid excessive fallbacks or backward compatibility unless there is an extremely clear justification
6. use asserts when loading data, and always be highly opinionated about the parameters you pass around. don't let things be optional if not strictly required
7. remove any changes that are not strictly required
8. bias for fewer lines of code
9. no complex or clever code
10. don't break out into too many function, that's hard to read
11. early returns are great
12. use asserts instead of try catches or default values when you do expect something to exist
13. never pass overrides except strictly necessary, keep argument count low
14. don't make arguments optional if they are actually required
15. Do not introduce one/two-time use variables, prefer inlining
16. Declare things as close as possible to their site of first use
17. When changing site structure, preview steps, or content maintenance, update
    `README.md` in the same change.

Documentation guidance:
Use concise, ASD-STE100-inspired plain technical language in user docs, docstrings, and comments. Regularly fix unclear, outdated, or missing documentation before it becomes documentation debt.

Dependency guidance:
The static site has no package manager or runtime dependencies. Do not add Python
or Node tooling for routine content edits. The content importer uses PHP CLI
with DOM/libxml and HTTPS support, without third-party packages. The optional
DB export also uses MySQLi, and normal-user import uses POSIX. If Python tooling becomes necessary,
use `python3 -m uv` to manage its environment and dependencies.

Verification guidance:
1. Run `git diff --check`.
2. Check that changed local links and media paths exist with exact filename case.
3. Preview changed pages in a browser at desktop and mobile widths. Check page
   navigation, main-site links, and tabs on pages that have them.
4. Check member and publication changes against the confirmed source. Preserve
   publication categories, year order, author order, and links.
5. For content sync changes, run `php tests/content.php`,
   `php tests/database.php`, and `php scripts/sync-content.php check`. Lint
   changed PHP files with `php -l`. DB exports must use read-only transactions,
   fixed public-field queries, and normal-user writes to this repo only.
6. Record checks that could not run and why. Python lint and typecheck do not
   apply to this static site.

Commit guidance:
## 1. The "Golden Seven" Rules
These rules were popularized by Chris Beams and are widely considered the standard for professional development.

1.  **Separate subject from body with a blank line.**
2.  **Limit the subject line to 50 characters.** (Keep it concise).
3.  **Capitalize the subject line.**
4.  **Do not end the subject line with a period.**
5.  **Use the imperative mood in the subject line.** (e.g., "Fix bug" instead of "Fixed bug").
6.  **Wrap the body at 72 characters.** (This ensures readability in terminal-based tools).
7.  **Use the body to explain *what* and *why* vs. *how*.**

---

## 2. Use the Imperative Mood
A Git commit should be viewed as an **instruction** for changing the state of the repository. A good trick is to complete this sentence:

> "If applied, this commit will **[your subject line]**"

* **Correct:** Refactor subsystem X for readability
* **Incorrect:** Refactored subsystem X or Refactoring subsystem X

---

## 3. Structure with Conventional Commits
Many modern teams use the **Conventional Commits** specification. This adds a machine-readable prefix to your messages, which is great for automated changelog generation.

**Format:** <type>(<scope>): <description>

### Common Types:
* feat: A new feature for the user.
* fix: A bug fix.
* docs: Documentation only changes.
* style: Changes that do not affect the meaning of the code (white-space, formatting, etc.).
* refactor: A code change that neither fixes a bug nor adds a feature.
* test: Adding missing tests or correcting existing tests.
* chore: Changes to the build process or auxiliary tools and libraries.

**Example:**
feat(auth): add OAuth2 provider support

---

## 4. Focus on the "Why"
The code shows you **how** the change was made, but it often fails to explain **why** it was necessary. Use the body of the commit message to:
* Explain the context of the problem.
* Explain why this specific solution was chosen over others.
* Mention any side effects or breaking changes.
* Prefer a multi-line commit message with a subject and body for all
  non-trivial changes. A title-only commit is acceptable only for extremely
  small mechanical edits where the subject fully explains the change.

---

## 5. Single Author and Sign-off
1. Every commit has exactly one author, and the author and committer must be
   the same identity: the repository owner's configured git user.
2. Do not add `Co-Authored-By`, `Co-authored-by`, or any other co-author,
   attribution, or tool tagline trailers to commit messages.
3. End every commit message with exactly one sign-off line:

   `Signed-off-by: Kyumin Cho <kmcho@postech.ac.kr>`

   `git commit -s` generates it when `user.name` and `user.email` match.
4. If a commit lands with extra attribution trailers, rewrite the local
   history (for example `git filter-branch --msg-filter`) before pushing.

---

## 6. Summary Table: Good vs. Bad Examples

| Feature | Bad Example | Good Example |
| :--- | :--- | :--- |
| **Clarity** | Fixed stuff | fix: resolve race condition in login handler |
| **Mood** | I added more logs | feat: add debug logging to API middleware |
| **Length** | This is a very long subject line that explains every single file I touched today... | refactor: simplify database connection logic |
| **Context** | Update README | docs: update setup instructions for Docker |

---

##  The "Atomic" Commit
Try to keep your commits **atomic**. This means one commit should address exactly one logical change. If you find yourself using the word "and" in your subject line (e.g., "Fix typo and add login feature"), you should probably split it into two separate commits.

## Check for Broken Commits
Sometimes there is a commit with broken commit messages that does not follow previous commit guidelines + raw characters like \n being visible, or -s not being enforced.
After making commit check immediately if there is no broken commit message.
