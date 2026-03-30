
<div align="center" style="text-align: center">
<img src="docs/logo.webp" width="250">

# Prompt Flow

A powerful Laravel-based system for managing programming projects through AI-powered webhook integrations. Receive commands via Telegram, Linear, or Web API, manage projects with AI assistance, and execute tasks using OpenCode or Claude Code CLI.

![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php)
![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)
![AI](https://img.shields.io/badge/AI-SDK-FF6C37?style=for-the-badge)
</div>

---

## Features

### 🤖 CLI-Powered Intent Analysis
Automatically identifies which project you're referring to by analyzing your message against registered projects. A CLI tool analyzes the input and returns structured JSON to determine intent and route to the appropriate Action.

```bash
# Add a project
cd /home/path-to-project-x
pf link

# Chat with your bot:
# "Add track system to the authentication module"
# "Show me the git history of project x"
# "What are the projects in the /path/to/project-y directory?
```

### 📱 Multi-Channel Webhooks
Receive requests from multiple sources:
- **Telegram Bot** — Send commands directly to your Telegram bot
- **Web API** — RESTful endpoint for custom integrations
- **Linear** — AI-powered issue processing via webhooks
- **Nightwatch** — AI-powered exception handling via Laravel Nightwatch
- **CLI** — Run commands directly from the command line

---

## Installation

### 1. Clone

```bash
git clone https://github.com/b7s/prompt-flow.git && cd prompt-flow
```

### 2. Configure Environment Variables

Copy `.env.example` to `.env` and configure the key variables. For a complete reference, see [Environment Variables Reference](#environment-variables-reference).

### 3. Install

```bash
php artisan install
```

This will:
- ✅ Detect your operating system (Linux, macOS, Windows)
- ✅ Check if Supervisor is installed
- ✅ Create Supervisor configuration automatically
- ✅ Set up the global `pf` CLI command
- ✅ Provide next steps instructions

### 4. Start server:

If you want to quickly show your application to use on Telegram/API

**To run Local, you need to run your app:**

```bash
php artisan serve
```
Copy the IP address and port and use it in one of the following services above.

> [Learn here](docs/create-php-server.md) how to create a server that will always keep the service online, even when you restart your computer.

### Access out of your local machine:

* [Cloudflare Tunnel](https://developers.cloudflare.com/tunnel/setup/) (better and free)
* [Ngrok](https://ngrok.com/)
* [Tailscale](http://tailscale.com/)

If you want to put it into production, go with:

* [Railway](https://railway.com/) / [Render](https://render.com/) for something simpler. 
* VPS + Nginx + Laravel (DigitalOcean, Hetzner, etc)

---

## Usage

### Interactive CLI Manager

Manage your projects with a beautiful terminal interface:

```bash
php artisan projects
```

Or use the global `pf` command on any folder:

```bash
pf projects
```

> To see all available commands for "pf", use `pf -h`

**Available Operations:**
- 📋 **List Projects** — View all projects in a formatted table
- ➕ **Add Project** — Register a new local project
- ✏️ **Edit Project** — Update project details
- 🗑️ **Remove Project** — Delete a project
- 🔍 **Search Projects** — Find projects by name or path
- 🔑 **Manage API Keys** — Generate and manage Bearer API keys for use in web requests

### Global CLI (pf)

After running `php artisan install`, you can use the `pf` command from any folder. It automatically finds your PromptFlow Laravel app by searching parent directories.

```bash
# Link current folder as a project
pf link

# Unlink current folder from PromptFlow
pf unlink            # Prompts for confirmation
pf unlink --force    # Skips confirmation

# Open interactive manager
pf projects
```

If the global command is not available, create a symlink:

```bash
sudo ln -s /path/to/your-prompt-flow-project/bin/pf /usr/local/bin/pf
```

### Quick Project Linking

Link a project from any folder:

Use the global `pf` command from any directory:

```bash
cd /path/to/my-project
pf link
```

This will:
- Use the current folder as the project path
- Auto-detect project name from folder or `composer.json`/`package.json`
- Auto-detect project type (Laravel, Node, React, Vue, Go, Rust, etc.)
- Auto-detect framework and features
- Auto-detect description from `composer.json`, `package.json` or README
- Set status to Active

**Detected Project Types:**
- Laravel, Symfony, Rails, Django
- Node.js, React, Vue, Next.js, Nuxt, Svelte, Astro
- Bun, Deno
- Go, Rust, Python
- Flutter

**Options:**
```bash
# Custom name
pf link --name="my-project"

# With description
pf link --description="My awesome project"

# With CLI preference
pf link --cli=claudecode

# All options
pf link --name="my-project" --description="..." --cli=claudecode
```

### Run AI-Powered Prompts

Execute AI-powered commands on your projects directly from the CLI:

```bash
# Run a prompt
pf run "list projects"

# Run a prompt on a specific project
pf run "add login functionality to project my-project"

# Run a prompt to analyze code
pf run "understand the authentication flow"
```

The AI will:
1. Analyze your request and identify the target project
2. Execute the appropriate tool (list projects, execute command, etc.)
3. Return plain text results

**Examples:**
```bash
# List all projects
pf run "show me my projects"

# Add a new project
pf run "add new project called my-app at /path/to/my-app"

# Execute a command on a project
pf run "run composer install on project my-project"

# Continue a previous session
pf run "continue from history 1"
```

### Unlink a Project

Unlink (remove) a project from PromptFlow:

```bash
cd /path/to/my-project
pf unlink              # Prompts for confirmation
pf unlink --force      # Skips confirmation
```

### API Endpoints

All endpoints require Bearer token authentication:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/webhook` | POST | Main webhook endpoint |
| `/api/webhook/telegram` | POST | Telegram-specific webhook |
| `/api/webhook/whatsapp` | POST | WhatsApp-specific webhook |
| `/api/webhook/linear` | POST | Linear issue webhook |
| `/api/webhook/nightwatch` | POST | Laravel Nightwatch webhook |

**Request Format:**

```json
{
    "message": "Add user registration to the authentication module",
    "channel": "telegram",
    "chat_id": 123456789
}
```

### Example Usage with Telegram

1) Create a bot: https://core.telegram.org/bots/tutorial#obtain-your-bot-token
2) Configure the bot to send messages to your webhook:
    * Add the Token from Telegram `@BotFather` to your `.env` file
    * Register your app webhook url:
        * `php artisan telegram:activate` Direct call – when you ready
        * `php artisan install` Also activates Telegram
3) Send a message to your bot: `Add validation to the login form`

**What happens:**
1. ✅ Respond with "Processing..."
2. 🧠 Analyze which project you're referring to
3. ⚡ Execute the task using the configured CLI
4. 📤 Send the result back to your bot

### Linear Integration

Automate your Linear workflow with AI-powered issue processing:

1. **Create a Linear API key**: Go to Settings > API > Create a personal API key
2. **Get your Organization ID**: Find it in the URL (e.g., `linear.app/team/your-org-id/...`)
3. **Configure your `.env`** with the Linear variables 
4. **Create a webhook**: In Linear Settings > API > Webhooks, add your endpoint:
   - URL: `https://your-app.com/api/webhook/linear`
   - Events: Issues (create, update)
5. **Set up Telegram notifications** (optional): Configure `TELEGRAM_CHAT_ID` to receive notifications
   - Unique identifier for the target chat or username of the target channel (in the format "@channelusername")

**What happens:**
- When a new issue is created/updated in Linear, the system receives the webhook
- CLI-based analysis determines what action to take
- The CLI executes the task on the linked project
- On completion, the system:
  - Updates the issue status to "Done"
  - Adds a comment with the result
  - Adds a ✅ reaction
  - Sends a Telegram notification (if configured)

### Nightwatch Integration

Automate exception handling with AI-powered processing via Laravel Nightwatch:

1. **Get your webhook secret**: In Nightwatch dashboard, go to Issues > Webhooks > Edit (or create new)
2. **Configure your `.env`**:
   ```
   NIGHTWATCH_ENABLED=true
   NIGHTWATCH_WEBHOOK_SECRET=your_webhook_secret
   ```
3. **Set up the webhook URL** in Nightwatch:
   - URL: `https://your-app.com/api/webhook/nightwatch`
4. **Set up Telegram notifications** (optional): Configure `TELEGRAM_CHAT_ID` to receive notifications

**What happens:**
- When a new exception is detected (`issue.opened` with type=`exception`), the system:
  - Sends a Telegram notification (if configured)
  - Analyzes the exception with CLI
  - Executes a fix on the linked project using the configured CLI
  - Sends completion/error notification
- Performance issues (`slow-route`, `slow-job`, `slow-command`, `slow-scheduled-task`) only send Telegram notifications without CLI dispatch
- Resolved, reopened, and ignored events are logged and optionally notified via Telegram

### Prompt History & Continuation

Every AI prompt execution is automatically recorded, allowing you to review past interactions and continue from where you left off.

**How it works:**
1. When you run a prompt on a project, it's stored with the AI response
2. The CLI session ID is saved so continuations can reuse the same session
3. You can list history for any project or globally
4. Continuing from history passes the full context to continue the conversation

**Commands:**
- **Show history**: "show history", "list history", "what have you done"
- **Continue**: "continue from history [id]", "continue what we were doing"

The system automatically continues in the same CLI session when possible, maintaining conversation context across multiple interactions.

---

## Configuration

### Environment Variables Reference

| Variable | Description | Required | Example |
|----------|-------------|----------|---------|
| `APP_EXTERNAL_URL` | Public URL for webhooks (ngrok, cloudflare tunnel, VPS) | Yes | `https://your-external-app-url.com` |
| `DEFAULT_CLI` | Default CLI tool to use | No | `opencode` or `claudecode` |
| `AI_FLOW_PROVIDER` | AI provider | No | `anthropic`, `openai`, `ollama` |
| `AI_FLOW_MODEL` | AI model to use | No | `claude-sonnet-4-6` |
| `AI_FLOW_API_KEY` | AI API key (if not using default provider key) | No | `sk-ant-...` |
| `TELEGRAM_BOT_TOKEN` | Telegram bot token from @BotFather | No | `123456789:ABC-DEF...` |
| `TELEGRAM_CHAT_ID` | Telegram chat ID or channel username | No | `@yourBotUsername` |
| `TELEGRAM_ENABLED` | Enable Telegram integration | No | `true` |
| `WHATSAPP_API_KEY` | WhatsApp Business API key | No | `your-whatsapp-api-key` |
| `WHATSAPP_ENABLED` | Enable WhatsApp integration | No | `true` |
| `WEB_ENABLED` | Enable Web API | No | `true` |
| `LINEAR_TRIGGER_STATUS` | Linear status that triggers AI processing | No | `backlog` |
| `LINEAR_MOVE_TO_WHEN_FINISH` | Status to move issue when task completes | No | `done` |
| `LINEAR_API_KEY` | Linear API key | No | `lin_api_...` |
| `LINEAR_ORGANIZATION_ID` | Linear organization ID | No | `your-organization-id` |
| `LINEAR_WEBHOOK_SECRET` | Linear webhook secret for verification | No | `your-webhook-secret` |
| `LINEAR_ENABLED` | Enable Linear integration | No | `true` |
| `NIGHTWATCH_WEBHOOK_SECRET` | Nightwatch webhook secret for verification | No | `your-webhook-secret` |
| `NIGHTWATCH_ENABLED` | Enable Nightwatch integration | No | `true` |

> **Note:** `APP_EXTERNAL_URL` is required for Telegram, Linear, etc., webhooks to work.
> Can be the same as `APP_URL` if you're running on the same server.

### AI Providers (for CLI Tool Execution)

The AI is used by the CLI tool (OpenCode/Claude Code) for executing tasks, not for decision making. The AI provider configuration is passed to the CLI tool:

| Provider | Environment Variable | Model |
|----------|---------------------|-------|
| Anthropic | `AI_FLOW_PROVIDER=anthropic` | `claude-sonnet-4-6` |
| OpenAI | `AI_FLOW_PROVIDER=openai` | `gpt-5` |
| Ollama | `AI_FLOW_PROVIDER=ollama` | `llama3` |
| Gemini | `AI_FLOW_PROVIDER=gemini` | `gemini-3` |

The system uses CLI-based intent analysis - the CLI tool analyzes user input and returns structured JSON that routes to the appropriate Action class.

---

## Testing

Run the test suite:

```bash
php artisan test --compact
```

---

## Architecture Highlights

### Action Pattern
The system uses a command-style Action pattern for handling user requests:

```
User Message → CLI Analysis (JSON) → ActionDispatcher → Action Classes → Response
```

- **ActionDispatcher** — Routes requests to appropriate Action class based on CLI analysis
- **Action Classes** — 16+ specialized actions in `app/Actions/`:
  - `ExecutePromptAction` — Executes AI prompts on projects
  - `ListProjectsAction` — Lists all projects
  - `AddProjectAction` — Adds a new project
  - `RemoveProjectAction` — Removes a project
  - `EditProjectAction` — Edits project details
  - `ShowHistoryAction` — Shows prompt history
  - `ContinueHistoryAction` — Continues from history
  - `SelectProjectAction` — Selects active project
  - `ListSessionsAction` — Lists CLI sessions
  - And more...

### Service-Oriented Design
All business logic is encapsulated in services:
- **ProjectService** — Project CRUD operations
- **ApiKeyService** — API key generation and validation
- **CliAnalysisService** — CLI-based intent analysis (returns JSON)
- **CliExecutorService** — CLI command execution
- **ResponseService** — Bot response handling

### Queue-Based Processing
Webhook jobs are dispatched to the queue, allowing:
- Instant bot responses
- Background CLI execution
- Retry on failure
- Scalability
- **Deduplication** — 24-hour cache prevents duplicate processing from webhook retries

---


## System Flow

```
┌────────────────────────────────────────────────────────────────────────────┐
│                                INPUT CHANNELS                              │
├────────────────┬────────────────┬────────────────┬────────────┬────────────┤
│   Telegram     │    WhatsApp    │    Web API     │   Linear   │ Nightwatch │
│      Bot       │    Business    │    RESTful     │  Webhook   │  Webhook   │
└───────┬────────┴───────┬────────┴───────┬────────┴─────┬──────┴─────┬──────┘
        │                │                │              │            │
        └────────────────┴────────────────┴──────────────┴────────────┘
                                        │
                                        ▼
                      ┌─────────────────────────────────┐
                      │         Webhook Endpoint        │
                      │          /api/webhook/*         │
                      │       (Bearer Token Auth)       │
                      └─────────────────┬───────────────┘
                                        │
                                        ▼
                      ┌─────────────────────────────────┐
                      │      Queue-Based Processing     │
                      │    (Instant Response + Async)   │
                      │    + Deduplication (24h cache)  │
                      └─────────────────┬───────────────┘
                                        │
                                        ▼
                      ┌─────────────────────────────────┐
                      │     CLI Analysis (NDJSON)       │
                      │  • Identifies target project    │
                      │  • Determines user intent       │
                      │  • Returns structured JSON       │
                      └─────────────────┬───────────────┘
                                        │
                                        ▼
                      ┌─────────────────────────────────┐
                      │        Action Dispatcher        │
                      │   Routes to appropriate Action  │
                      └─────────────────┬───────────────┘
                                        │
                                        ▼
                      ┌─────────────────────────────────┐
                      │        Action Classes           │
                      │   ┌─────────────────────────┐   │
                      │   │ • ExecutePromptAction   │   │
                      │   │ • ListProjectsAction   │   │
                      │   │ • AddProjectAction     │   │
                      │   │ • ShowHistoryAction   │   │
                      │   │ • ContinueHistoryAction│   │
                      │   │ • ...and more          │   │
                      │   └─────────────────────────┘   │
                      └─────────────────┬───────────────┘
                                        │
                                        ▼
                      ┌────────────────────────────────────┐
                      │         Response Service           │
                      │   • Formats result                 │
                      │   • Sends to origin channel        │
                      │   • Updates Linear (if applicable) │
                      └────────────────────────────────────┘
                                        │
          ┌─────────────────────────────┼─────────────────────────────┐
          │                             │                             │
          ▼                             ▼                             ▼
  ┌───────────────┐          ┌───────────────────┐          ┌─────────────────┐
  │   Telegram    │          │    WhatsApp       │          │     Linear      │
  │   Response    │          │    Response       │          │  • Status: Done │
  │               │          │                   │          │  • Comment      │
  │               │          │                   │          │  • Reaction ✅  │
  └───────────────┘          └───────────────────┘          └─────────────────┘
```

### Flow Summary

1. **Receive** — User sends command via Telegram/WhatsApp/API/Linear/Nightwatch
2. **Authenticate** — Validate Bearer token (except webhooks in skip list)
3. **Deduplicate** — Check 24h cache to prevent duplicate processing
4. **Queue** — Dispatch a job for async processing (instant confirmation response)
5. **CLI Analyze** — Run CLI tool to analyze intent, returns structured JSON
6. **Dispatch Action** — ActionDispatcher routes to appropriate Action class
7. **Execute** — Action performs the requested operation
8. **Record** — Store prompt and response in history (for prompt actions)
9. **Respond** — Send a result back to the originating channel

---

## Requirements

- PHP 8.4+
- Laravel 13.x
- Laravel AI SDK
- Laravel Prompts
- SQLite (default) or MySQL/PostgreSQL

---

## License

MIT License. See `LICENSE` for details.

---

## Credits

Built with:
- [Laravel](https://laravel.com)
- [Laravel AI SDK](https://github.com/laravel/ai)
- [Laravel Prompts](https://github.com/laravel/prompts)
- [OpenCode](https://opencode.ai)
- [Claude Code](https://docs.anthropic.com/en/docs/claude-code)
