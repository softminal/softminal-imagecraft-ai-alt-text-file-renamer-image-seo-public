# Impact Analysis for: $ARGUMENTS

Analyze the impact of changes to the specified file(s) or feature area: `$ARGUMENTS`

## Step 1: Identify the target
Read the file(s) or identify the feature area mentioned in the arguments.

## Step 2: Trace all dependencies (upstream)
What does this file/feature DEPEND ON? Trace imports, class extensions, service usage, model usage, settings access, API endpoints called.

## Step 3: Trace all dependents (downstream)
What DEPENDS ON this file/feature? Search the entire codebase for:
- PHP: `use` statements, `new ClassName`, `ClassName::method()`, function calls
- Vue: `import` statements, API endpoint usage, component references
- Routes: Which endpoints use this controller/service
- Settings: Which views read/write the settings this code manages

## Step 4: Backwards compatibility check
- Does this file's public API (method signatures, return shapes) get used by other files?
- Would changing this file break any REST API response contract?
- Would changing this file affect any wp_options key?
- Would changing this file require a database migration?

## Step 5: Report
```
=== Impact Analysis: [target] ===

DEPENDS ON (upstream):
- [file] — [what it uses from there]

DEPENDED ON BY (downstream):
- [file] — [what it uses from target]

RISK AREAS:
- [specific things that could break]

SAFE TO CHANGE:
- [aspects that are isolated]

RECOMMENDED APPROACH:
- [how to make the change safely]
```
