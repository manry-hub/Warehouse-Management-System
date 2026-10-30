# SETUPDOCS — Initialize Project Documentation

Use this prompt manually to populate or refine the PRD and SDD documents. It supports both existing and new projects. This task changes documentation only.

## Select a Mode First

Before reading the codebase or writing documents, ask the user to select one mode:

1. **Existing Project** — the project already has an implementation that is the primary source of evidence.
2. **New Project** — the project has not been built, or its implementation is not yet the source of truth.

Do not begin writing the PRD or SDD documents until the user has selected a mode.

## Target Documents

- `.agent-knowledge/static/templates/PRD-TEMPLATE.md` — immutable canonical structure; do not modify it.
- `.agent-knowledge/dynamic/docs/dev/PRD.md` — output that may be created or updated from the template.
- `.agent-knowledge/static/templates/SDD-TEMPLATE.md` — immutable canonical structure; do not modify it.
- `.agent-knowledge/dynamic/docs/dev/sdd/ARCHITECTURE.md` — architecture output.
- `.agent-knowledge/dynamic/docs/dev/sdd/TECHNOLOGYSTACK.md` — technology-stack output.
- `.agent-knowledge/dynamic/docs/dev/sdd/DATABASE.md` — database output.
- `.agent-knowledge/static/templates/ADR-TEMPLATE.md` — immutable canonical structure for architecture decision records.
- `.agent-knowledge/static/templates/SPEC-TEMPLATE.md` — immutable canonical structure for implementation-facing specifications.
- `.agent-knowledge/dynamic/docs/notes/ADR-{incremental-number}-{adr-summary}.md` — architecture decision record output.
- `.agent-knowledge/dynamic/docs/notes/SPEC-{incremental-number}-{spec-summary}.md` — specification output.

## Procedure

1. Read `AGENTS.md`, applicable local instructions, relevant lessons, both templates, and every existing output document. The templates are the canonical structure and must not be modified.
2. Follow the selected-mode procedure, then create or update `PRD.md`, `ARCHITECTURE.md`, `TECHNOLOGYSTACK.md`, and `DATABASE.md` using the templates' structure. Replace placeholders with verified information or open questions.

### Existing Project Mode

1. Map the relevant codebase before writing: stack and versions, entry points, primary flows, interfaces or APIs, important modules, authentication and authorization, integrations, data storage, and configuration and tests that help confirm behavior. Follow the project's declared package manager and versions.
2. When available and permitted by project instructions, use the `codebase-memory` skill for structural questions; verify material findings in source files.
3. Map information to template sections: vision and problem, target users, epics and user stories, MVP or post-MVP features, constraints and assumptions, success metrics, and out of scope.
4. Map technical evidence to Architecture, Technology Stack, and Database: system boundaries, components, data flows, security, deployment, technologies and versions, topology, schema, relationships, indexes, migrations, and seed data.
5. Separate **current state** (evidenced behavior and design), **business or product decisions** (stated intent or valid documentation), and **requested changes** (unimplemented plans or proposals).
6. Connect requested changes to the current project state. Do not represent a new feature as already built.

### New Project Mode

1. Collect the information needed by each template section: vision and problem, target users, epics and user stories, MVP and post-MVP features, constraints, assumptions, success metrics, and out of scope.
2. Collect the technical decisions needed by the SDD: project type, architectural boundaries and patterns, components, data flows, security, deployment, technologies and versions, database topology, schema, relationships, indexes, migrations, and seed data.
3. If material information is unavailable, ask the user and wait for an answer. Do not fill requirements, personas, business decisions, or technical decisions with assumptions.
4. Write the PRD as a requirements specification and the SDD documents as technical plans. Mark unmade decisions as open questions.
5. Do not represent features or behavior as implemented until evidence confirms that the project has been built.

## Completing the Documents

- Follow the headings, tables, and MVP, post-MVP, and out-of-scope separation in `PRD-TEMPLATE.md`. Use implementation evidence to record capabilities of an existing project; describe a new project's requirements as plans until implemented.
- Follow the three sections in `SDD-TEMPLATE.md`: Architecture for system design, components, security, deployment, and decisions; Technology Stack for layers, technologies, versions, and roles; and Database for topology, schema, relationships, indexes, migrations, and seed data. Do not duplicate the technology-stack table in Architecture.
- For a durable architectural decision, create an ADR from `ADR-TEMPLATE.md`. For a behavior, interface, data, or integration contract that needs independent implementation review, create a SPEC from `SPEC-TEMPLATE.md`. Link either document from the affected living documentation when useful.

## Writing and Research Rules

- Populate empty documents with established information. Preserve valid existing content and update only affected sections; do not overwrite a complete document with a codebase summary.
- Cross-check the PRD and all SDD documents so terminology, scope, feature status, priorities, and decisions do not conflict. Leave unanswered items as open questions.
- The topics above are scope guidance, not mandatory headings. Keep documents concise and easy to update.
- Use the language already used by the document. If all are empty, use the language of the user's request.
- Include references to codebase files or artifacts for material technical decisions when that helps readers verify them.
- Do not perform web research to populate routine documentation. If the user requests or supplies research, record its sources and dates and label it as a recommendation, approved decision, or current implementation.
- Do not change source code, configuration, dependencies, schema, or tests. Document mismatches between implementation and requirements; do not fix them in this task.

## Completion Criteria

- The user selected a mode before documentation was written.
- The PRD follows `PRD-TEMPLATE.md`, and ARCHITECTURE, TECHNOLOGYSTACK, and DATABASE follow their respective parts of `SDD-TEMPLATE.md`, without modifying those templates.
- Any ADR or SPEC created follows its canonical template and uses the required numbered filename.
- In an existing project, implementation facts are distinguishable from planned changes.
- In a new project, requirements and technical design are treated as plans or proposals, and unavailable information is recorded as an open question.
- Report changed documents, updates, codebase evidence used when applicable, and remaining open questions or decisions.
