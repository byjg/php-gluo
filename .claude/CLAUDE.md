# Project Instructions

## gluo-developer Skill

Invoke the `gluo-developer` skill before working on any of the following:

- Adding a new entity, endpoint, or CRUD feature
- Choosing between Repository pattern and ActiveRecord pattern
- Scaffolding with the code generator (`php gluo codegen:all`)
- Writing or updating controller tests
- Running or resetting migrations
- Registering services or repositories in DI config
- Updating OpenAPI docs / regenerating `openapi.json`
- Working with controllers, attributes (`RequireAuthenticated`, `RequireRole`, `ValidateRequest`), or serialization

The skill is published in the ByJG plugin catalog (https://opensource.byjg.com/docs/ai/agent-skills/gluo-developer).
A project created from the starter declares it in `.claude/settings.json`; the starter repository
itself loads its own copy from `.claude/skills/gluo-developer/`, which is the source. If it is not
available, install it with `/plugin marketplace add byjg/skills` and
`/plugin install gluo-developer@byjg`.
