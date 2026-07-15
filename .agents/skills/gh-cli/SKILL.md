---
type: skill
name: gh-cli
description: Use the GitHub CLI (`gh`) to manage repositories, issues, pull requests, releases, and agent skills.
allowed-tools: Bash(gh:*)
---
# GitHub CLI (gh) Skill

Use the GitHub CLI (`gh`) to manage GitHub repositories, issues, pull requests, releases, and agent skills directly from the command line.

## Core Commands

### Repository Management (`gh repo`)
- `gh repo clone <repo>`: Clone a repository.
- `gh repo create`: Create a new repository.
- `gh repo fork`: Fork a repository.
- `gh repo view`: View a repository's README.
- `gh repo sync`: Sync a fork with its upstream.
- `gh repo list`: List repositories.
- `gh repo archive`: Archive a repository.
- `gh repo unarchive`: Unarchive a repository.
- `gh repo rename <new-name>`: Rename a repository.
- `gh repo edit`: Edit repository settings.
- `gh repo read-dir <path>`: List directory contents in a repo.
- `gh repo read-file <path>`: Read a file's content from a repo.
- `gh repo license`: Manage repository licenses.
- `gh repo gitignore`: Manage .gitignore files.
- `gh repo deploy-key add/delete/list`: Manage deploy keys.

### Issues (`gh issue`)
- `gh issue create`: Create a new issue.
- `gh issue list`: List issues.
- `gh issue view <number>`: View an issue.
- `gh issue comment <number> -b "message"`: Add a comment to an issue.
- `gh issue close <number>`: Close an issue.
- `gh issue reopen <number>`: Reopen an issue.
- `gh issue edit <number>`: Edit an issue.
- `gh issue delete <number>`: Delete an issue.

### Pull Requests (`gh pr`)
- `gh pr create`: Create a pull request.
- `gh pr list`: List pull requests.
- `gh pr checkout <number>`: Check out a pull request's branch.
- `gh pr view <number>`: View a pull request.
- `gh pr merge <number>`: Merge a pull request.
- `gh pr diff`: View the diff of a pull request.
- `gh pr review <number>`: Review a pull request.
- `gh pr ready <number>`: Mark a PR as ready for review.
- `gh pr close <number>`: Close a pull request.

### Releases (`gh release`)
- `gh release create <tag>`: Create a new release.
- `gh release list`: List releases.
- `gh release view <tag>`: View a release.
- `gh release download <tag>`: Download assets from a release.
- `gh release upload <tag> <file>`: Upload a file to a release.
- `gh release delete <tag>`: Delete a release.
- `gh release delete-asset <tag> <asset>`: Delete a release asset.
- `gh release verify <tag>`: Verify a release.

### Projects (`gh project`)
- `gh project create --owner <owner> --title <title>`: Create a new project.
- `gh project list --owner <owner>`: List projects for an owner.
- `gh project view <number> --owner <owner>`: View a project.
- `gh project item-add <number> --owner <owner> --url <url>`: Add an item to a project.
- `gh project item-list <number> --owner <owner>`: List items in a project.
- `gh project item-edit <number> --owner <owner> --id <item-id> --field <field> <value>`: Edit a project item field.
- `gh project field-list <number> --owner <owner>`: List fields in a project.

### Discussions (`gh discussion`)
- `gh discussion create --category <cat> --title <title> --body <body>`: Create a discussion.
- `gh discussion list`: List discussions.
- `gh discussion view <number>`: View a discussion.
- `gh discussion comment <number> -b "message"`: Add a comment to a discussion.

### Organization Management (`gh org`)
- `gh org list`: List organizations the authenticated user belongs to.

### Secrets and Variables (`gh secret`, `gh variable`)
- `gh secret set <name> -b "value"`: Set a secret for the current repo.
- `gh secret list`: List secrets.
- `gh secret delete <name>`: Delete a secret.
- `gh variable set <name> -b "value"`: Set a variable for the current repo.
- `gh variable get <name>`: Get a variable's value.
- `gh variable list`: List variables.
- `gh variable delete <name>`: Delete a variable.

### Repository Rulesets (`gh ruleset`)
- `gh ruleset list`: List repository rulesets.
- `gh ruleset view <name>`: View a ruleset.
- `gh ruleset check <ref>`: Check if a reference complies with rulesets.

### Authentication (`gh auth`)
- `gh auth login`: Authenticate with GitHub.
- `gh auth status`: Check authentication status.
- `gh auth logout`: Log out of GitHub.
- `gh auth refresh -s <scope>`: Refresh the auth token with additional scopes.

## Agent Skills (`gh skill`)

Manage agent skills (specialized instructions and tools) from GitHub repositories.

### Commands
- `gh skill search <query>`: Search for skills.
- `gh skill install <repo> [<skill>]`: Install a skill.
- `gh skill list`: List installed skills.
- `gh skill update --all`: Update all installed skills.
- `gh skill preview <repo> [<skill>]`: Preview a skill's `SKILL.md` without installing.
- `gh skill publish`: Validate and publish a skill.

### Preview Examples
- `gh skill preview github/awesome-copilot documentation-writer`: Preview a specific skill.
- `gh skill preview github/awesome-copilot documentation-writer@v1.2.0`: Preview a specific version.
- `gh skill preview monalisa/skills-repo packages/agent-skills/code-review`: Preview from a nested path.

## GitHub Actions

### Workflow (`gh workflow`)
- `gh workflow list`: List workflows in the repo.
- `gh workflow run <name>`: Trigger a workflow.
- `gh workflow view <name>`: View workflow definition.

### Validation and Post-Merge Fork Sync

- Run `bapXphp update` before the final commit when source or documentation relationships changed.
- Use `bapXphp pr` and `bapXphp merge`; both run non-mutating `bapXphp ci` before calling `gh`.
- Upstream `main` push -> `repository_dispatch` -> downstream `merge-upstream` is the normal sync path. Do not click Sync fork or run `gh repo sync` unless event-driven sync failed.
- Compare both `main` SHAs through `gh api repos/<owner>/bapXphpAiBackend/commits/main --jq .sha`; do not rely only on the sync command's exit status.
- Keep manual Sync fork, `gh repo sync`, and `workflow_dispatch` as recovery paths only.
- Never use a forced sync when the fork has divergent commits. File an issue with the compare evidence instead.

### Runs (`gh run`)
- `gh run list`: List workflow runs.
- `gh run view <run-id>`: View details of a run.
- `gh run watch`: Watch a run in real-time.

## Miscellaneous

### Browser Transition (`gh browse`)
- `gh browse`: Open the home page of the current repository.
- `gh browse <path>`: Open a specific file or directory (e.g., `gh browse src/main.js`).
- `gh browse <number>`: Open issue or PR by number.
- `gh browse <file>:<line>`: Open file at a specific line (e.g., `gh browse main.go:312`).
- `gh browse --settings`: Open repository settings.
- `gh browse --wiki`: Open repository wiki.
- `gh browse --blame <file>`: Open blame view for a file.

### API and Search
- `gh api <endpoint>`: Make an authenticated HTTP request to the GitHub API.
  - Use `-f key=value` for parameters.
  - Use `--jq <query>` to filter JSON output.
  - Example: `gh api repos/{owner}/{repo}/issues --jq '.[].title'`
- `gh search <type> <query>`: Search GitHub.
  - Types: `code`, `commits`, `issues`, `prs`, `repos`.
  - Example: `gh search repos "react"`

### Other Utilities
- `gh config set <key> <value>`: Configure `gh` settings.
- `gh alias set <name> <command>`: Create a shortcut for a command.

## Remote Database Query Endpoint

### `/remotedb`

A secure API endpoint for live database queries and explicit authenticated record mutations used by the project CLI.

**Endpoint:** `POST ${APP_URL}/remotedb` (currently `https://sripanchamispiritual.com/remotedb`)

**Request:**
```bash
curl -X POST "${APP_URL}/remotedb" \
  -H "Content-Type: application/json" \
  -d '{"query":"SELECT * FROM products LIMIT 10"}'
```

**Response:**
```json
{"success": true, "data": [...]}
```

 **Security:**
- Query action accepts only `SELECT`, `SHOW`, `DESCRIBE`, and `EXPLAIN`.
- Mutation actions are `upsert`, `delete`, and `replace` against declared collections; `secrets` is never writable through this endpoint.
- Password is stored as `remote_db_password` in secrets (set via Admin → Integrations or `REMOTE_DB_PASSWORD` in `.env`). `DatabaseService` sends it automatically in every remote payload. The controller verifies with timing-safe `hash_equals()`.

**Usage:**
- Useful when `bapXphp db` cannot connect via direct MySQL.
- Password can be set in Admin → Integrations or `.env`. Leave blank for no password (backward compatible).
- Use for debugging and data exploration in production.
