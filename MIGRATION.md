# Conduit Knowledge Migration Guide

## Overview

The Conduit Knowledge component replaces the built-in knowledge system that was removed in Conduit v2.13.0. This guide helps you migrate your data based on your situation.

## Quick Decision Tree

1. **Are you on Conduit < v2.13.0?**
   - ✅ Run `conduit knowledge:migrate-from-core`
   - Your data will be automatically migrated

2. **Are you on Conduit >= v2.13.0?**
   - Do you have a database backup from before v2.13.0?
     - ✅ Restore the backup, then run `conduit knowledge:migrate-from-core`
   - Do you have a JSON export of your knowledge data?
     - ✅ Run `conduit knowledge:import <backup.json>`
   - No backup available?
     - 😔 Legacy data cannot be recovered
     - 🚀 Start fresh with `conduit knowledge:add`

## Detailed Migration Scenarios

### Scenario 1: Fresh Conduit Installation

If you're starting fresh with Conduit v2.13.0+:

```bash
# Install the knowledge component
conduit install knowledge

# Start adding knowledge
conduit knowledge:add "My first knowledge entry"
```

### Scenario 2: Upgrading from Conduit < v2.13.0

**IMPORTANT**: Migrate BEFORE updating to v2.13.0!

```bash
# While still on old version
conduit know:backup /path/to/backup.json  # If available

# Install knowledge component
conduit install knowledge

# Migrate data
conduit knowledge:migrate-from-core

# Now safe to update Conduit
composer global update conduit-ui/conduit
```

### Scenario 3: Already Updated to v2.13.0+

If you updated without migrating first:

#### Option A: Restore Database Backup
```bash
# Restore your database backup that includes knowledge tables
mysql -u user -p conduit < backup.sql

# Run migration
conduit knowledge:migrate-from-core

# Clean up legacy tables (optional)
conduit knowledge:migrate-from-core --remove-core
```

#### Option B: Import from JSON Backup
```bash
# If you have a JSON export
conduit knowledge:import /path/to/knowledge-backup.json
```

#### Option C: Manual Recovery
If you have access to an old database backup:

1. Create a temporary database
2. Restore the backup there
3. Update your `.env` to point to it temporarily
4. Run the migration
5. Switch back to your main database

### Scenario 4: Team Migration

For teams using Conduit:

1. **Designate one person** to perform the migration
2. Have them create a backup: `conduit knowledge:migrate-from-core --backup=team-knowledge.json`
3. Share the backup file with the team
4. Team members import: `conduit knowledge:import team-knowledge.json`

## Troubleshooting

### "Core knowledge tables not found"

This means you're on Conduit v2.13.0+ where tables were removed. See Scenario 3 above.

### "No legacy knowledge data found to migrate"

The migration command now handles this gracefully. You can:
- Import from a backup file
- Start fresh with new entries

### Permission Errors

Ensure your database user has CREATE/DROP table permissions for migrations.

### Large Datasets

For large knowledge bases:
```bash
# Create backup first
conduit knowledge:migrate-from-core --backup=knowledge-backup.json

# Migrate in batches if needed
conduit knowledge:import knowledge-backup.json
```

## Best Practices

1. **Always backup** before migrating
2. **Test migration** on a development environment first
3. **Verify data** after migration with `conduit knowledge:search`
4. **Remove legacy tables** only after confirming successful migration

## Getting Help

- Report issues: https://github.com/jordanpartridge/conduit-knowledge/issues
- Documentation: Run `conduit knowledge --help`
- First-run guide: Run any knowledge command for helpful tips