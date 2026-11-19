---
name: git-conventional-commit
description: automate git add and git commit workflows in a repository while enforcing conventional commits. use when creating commits, reviewing staged changes, generating commit messages from diffs, batching repository work, or maintaining a clean git history that follows feat, fix, docs, refactor, test, build, ci, chore, perf and style conventions.
---
# Workflow

1. Run `git status --short`.
2. Analyze modified, added and deleted files.
3. Group related changes into a single logical commit.
4. Run `git add` only for files that belong to the current logical change.
5. Generate a Conventional Commit message.
6. Run `git commit -m "<type>(optional-scope): <subject>"`.
7. Repeat until all changes are committed.

# Conventional Commit Rules

Use:

- feat: new functionality
- fix: bug fix
- docs: documentation only
- style: formatting only
- refactor: code restructuring without behavior change
- perf: performance improvement
- test: tests added or modified
- build: build tooling changes
- ci: CI/CD changes
- chore: maintenance tasks

Format:

```
type(scope): short description
```

Examples:

```
feat(vehicle): add motorcycle creation form
fix(auth): resolve login redirect loop
docs(readme): update installation guide
refactor(entity): simplify inheritance tree
```

# Commit Creation Heuristics

- Never mix unrelated changes in the same commit.
- Prefer several small commits over one large commit.
- Keep subject under 72 characters.
- Use imperative mood.
- Do not end subject with a period.
- Infer the scope from the main module, package, entity or feature.

# Expected Output

For each commit:

1. Explain why files belong together.
2. Show proposed `git add` commands.
3. Show proposed Conventional Commit message.
4. After approval (or if explicitly authorized), execute commit.

# Repository Review Checklist

Before committing:

- Verify `git diff`.
- Verify tests when available.
- Check generated files should be committed.
- Exclude temporary files, logs and secrets.
