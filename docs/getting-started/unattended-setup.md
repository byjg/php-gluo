---
sidebar_position: 60
title: Unattended Setup
---

# Unattended Setup Mode

The PostCreateScript supports unattended (non-interactive) setup via a JSON configuration file.

## How It Works

1. Place a `setup.json` file in one of the supported locations (see below)
2. Run `composer create-project`
3. The setup script will detect the file and run in unattended mode
4. The `setup.json` file remains for reuse across multiple projects

## Configuration File Locations

The script searches for `setup.json` in the following locations (in priority order):

### 1. Environment Variable (Highest Priority)

Use the `SETUP_JSON` environment variable to specify a custom location:

```bash
SETUP_JSON=/path/to/custom-setup.json composer create-project byjg/gluo my-project ^7.0
```

**Use cases:**
- Custom configuration locations
- CI/CD pipelines with specific configs
- Temporary overrides for specific projects

### 2. Parent Directory

The script looks in the directory where you run the `composer create-project` command:

```bash
/home/user/projects/setup.json          # ← Config file here
/home/user/projects/my-project/         # ← New project created here
```

**Use cases:**
- Project-specific configurations
- Quick one-off setups
- When you want config alongside projects

### 3. User Home Directory (Recommended for Personal Defaults)

The script checks your home directory:

**Linux/Mac:**
```bash
~/.gluo/setup.json
```

**Windows:**
```
C:\Users\YourName\.gluo\setup.json
```

**Use cases:**
- Personal default configurations
- Settings you want to reuse for all projects
- Keep your workspace clean

## Setup Examples

### Example 1: Personal Defaults in Home Directory

Create your personal defaults once:

```bash
# Linux/Mac
mkdir -p ~/.gluo
cat > ~/.gluo/setup.json << 'EOF'
{
  "git_user_name": "John Doe",
  "git_user_email": "john.doe@example.com",
  "install_frontend": true,
  "install_examples": false
}
EOF

# Now create projects anywhere - they'll use your defaults
cd ~/projects
composer create-project byjg/gluo project1 ^7.0
composer create-project byjg/gluo project2 ^7.0
```

### Example 2: Parent Directory (Quick Setup)

```bash
# Create config in your projects directory
cd ~/projects
cat > setup.json << 'EOF'
{
  "namespace": "MyApp",
  "composer_name": "mycompany/myapp",
  "install_frontend": true,
  "install_examples": false
}
EOF

# Create project in the same directory
composer create-project byjg/gluo my-project ^7.0
```

### Example 3: Environment Variable (CI/CD)

```bash
# Store config anywhere
cat > /etc/ci-configs/rest-setup.json << 'EOF'
{
  "git_user_name": "CI Bot",
  "git_user_email": "ci@company.com",
  "namespace": "AutoDeployApp",
  "install_frontend": true,
  "install_examples": false
}
EOF

# Use it with environment variable
SETUP_JSON=/etc/ci-configs/rest-setup.json composer create-project byjg/gluo production-app ^7.0
```

## Configuration Options

| Field              | Type    | Default                                           | Description                                     |
|--------------------|---------|---------------------------------------------------|-------------------------------------------------|
| `git_user_name`    | string  | Global git config or "Your Name"                  | Git user name for the project                   |
| `git_user_email`   | string  | Global git config or "your.email@example.com"     | Git user email for the project                  |
| `php_version`      | string  | Current PHP version                               | PHP version (8.3, 8.4, 8.5)                     |
| `namespace`        | string  | "MyRest"                                          | Project namespace (CamelCase)                   |
| `composer_name`    | string  | "me/myrest"                                       | Composer package name (vendor/package)          |
| `db_schema`        | string  | `mysql`                                           | One of: `mysql`, `postgres`, `sqlsrv`, `sqlite` |
| `db_host`          | string  | `mysql-container`                                 | Database host/container (ignored for SQLite)    |
| `db_user`          | string  | `root`                                            | Database username                               |
| `db_password`      | string  | `mysqlp455w0rd`                                   | Database password                               |
| `db_name_dev`      | string  | `localdev`                                        | Development database/schema name                |
| `db_name_test`     | string  | `localtest`                                       | Test database/schema name                       |
| `timezone`         | string  | "UTC"                                             | Server timezone                                 |
| `install_frontend` | boolean | true | Include the `frontend/` Vite app (login, reset, dashboard, profile) |
| `install_examples` | boolean | true | Include example code (Project/Task/Note + Sample; frontend example screens) |

> **Legacy compatibility:** The older `mysql_connection` field is still accepted. When present, it will be parsed and used to populate the new database fields automatically, but it is recommended to migrate to the explicit settings above for clarity and to support other drivers.

## All Fields Are Optional

If a field is not provided in `setup.json`, the default value will be used. You can provide only the fields you want to customize:

```json
{
  "namespace": "MyCustomApp",
  "composer_name": "company/custom-app",
  "install_frontend": true,
  "install_examples": false
}
```

## Priority Order

When multiple `setup.json` files exist, the script uses the **first one found**:

1. ✅ `SETUP_JSON` environment variable → **Highest priority**
2. ✅ Parent directory `../setup.json`
3. ✅ Home directory `~/.gluo/setup.json`

**Example:** If you have both a home directory config and use `SETUP_JSON`, the environment variable wins.

## Docker Warning in Unattended Mode

If Docker is not installed, the warning will be displayed, but the setup will continue automatically without waiting for user input.

## Interactive Mode

If `setup.json` does not exist, the setup runs in interactive mode (default behavior) and prompts for all configuration values.

## Security Note

The `setup.json` file is listed in `.gitignore` to prevent accidentally committing it to version control. 
If it contains sensitive information, consider adding it to your global gitignore as well.
