# Doc-Audit Skill

Systematic documentation gap finder. Detects code-doc drift, stale references, and undocumented features. Run before closing any feature branch.

## Detection Rules

1. **Route-doc mismatch**: Every route in `ProjectMapService::registry()` should appear in at least one `docs/pages/` or `docs/modules/` file. Run `grep -r "$route_path" docs/` per route.

2. **Service-doc gap**: Every service in `app/Services/*.php` should be referenced in at least one doc. Exceptions: utility services (`RateLimiter`, `MarkdownRenderer`, `SeoService`, `ImageOptimizerService`, `SchemaService`, `StoragePermissionService`) may be covered by module docs rather than dedicated pages.

3. **Controller-doc coverage**: Every public method on every controller should be listed or implied in `docs/modules/` or `docs/pages/`.

4. **Schema-field completeness**: Every field in `storage/schema/collections.php` should appear in admin forms and agent context. Cross-reference `SchemaService::adminFields()` and `AgentContextService::agentContextFields()`.

5. **Blog-content freshness**: Blog posts in `content/blog/posts/` should cover all major site features: returns, shipping, payment methods, product care.

6. **Doc-index completeness**: `docs/README.md` must list every file under `docs/pages/` and `docs/modules/`. Run `diff <(ls docs/pages/) <(grep -oP 'pages/\K[^)]+' docs/README.md | sort)`.

7. **AGENTS.md known-issues freshness**: Every time a feature is removed or a route is commented out, add a Known Issues entry.

8. **AI agent panel**: `/admin/agent` routes and `BlogDraftService` must be documented in their own module or page doc.

## Report Format

Report gaps as a markdown table:

```
| Gap Type | File | Detail | Priority |
|----------|------|--------|----------|
| stale-doc | docs/pages/blog.md | mentions products.json | P0 |
| missing-doc | docs/modules/agent-panel.md | admin AI agent | P1 |
```

Priorities:
- **P0**: Incorrect/misleading (will cause wrong agent behavior)
- **P1**: Missing entirely (feature undocumented)
- **P2**: Broken reference (file referenced but missing)
- **P3**: Stale wording (functional but uses outdated terminology)

## File Issue

After audit, create or update a GitHub issue titled `Doc sync: <summary>` in `bapxmediahub/bapXphpAiBackend` with the full report table. Link to specific source lines with permalinks.

## Recovery

For each P0-P1 gap:
1. Read the affected source code (controller/service/view/route)
2. Update the doc to match current behavior
3. Run `bapXphp update && bapXphp ci` to regenerate maps
4. Run `php tests/run.php` to verify no regressions
