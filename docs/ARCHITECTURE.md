# HotPlan - Hotline Forwarding Management System

## Overview

HotPlan is a flexible system for managing VoIP/PBX hotline forwarding based on time, schedules, holidays, and on-call rotations.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PRESENTATION LAYER                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │   Web UI    │  │ Scheduler   │  │   Forms     │  │   Admin Panel       │  │
│  │   (Views)   │  │   Pages     │  │             │  │                     │  │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          BUSINESS LOGIC LAYER                               │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                     DECISION ENGINE                                    │    │
│  │  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────────────┐   │    │
│  │  │  Override  │ │  Event    │ │  OnCall   │ │    Fallback       │   │    │
│  │  │  Resolver │ │  Finder   │ │  Rotation │ │    Resolver       │   │    │
│  │  └───────────┘ └───────────┘ └───────────┘ └───────────────────┘   │    │
│  │                                                                      │    │
│  │  Priority Resolution: Override > Event > OnCall > Holiday > Hours   │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                     FORWARDING SERVICE                                │    │
│  │  ┌───────────────┐ ┌───────────────┐ ┌───────────────────────────┐   │    │
│  │  │ Change Detect │ │ State Manager │ │ Alert Handler             │   │    │
│  │  └───────────────┘ └───────────────┘ └───────────────────────────┘   │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          INTEGRATION LAYER                                   │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                   VoIP PROVIDER INTERFACE                            │    │
│  │                                                                      │    │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌────────────┐ │    │
│  │  │   Sipura     │ │    Cisco     │ │ Grandstream  │ │  Generic   │ │    │
│  │  │   Provider   │ │    Provider  │ │   Provider   │ │  Provider  │ │    │
│  │  └──────────────┘ └──────────────┘ └──────────────┘ └────────────┘ │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PERSISTENCE LAYER                                   │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────────────┐ │
│  │   SQLite     │ │    MySQL     │ │   PostgreSQL │ │   File Storage       │ │
│  │   (Default)  │ │              │ │              │ │   (Config, Logs)     │ │
│  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Directory Structure

```
hotplan/
├── config/
│   ├── config.example.yaml      # Example configuration
│   └── credentials.env           # Secure credentials (not in git)
│
├── database/
│   ├── migrations/
│   │   └── 001_initial_schema.sql # Database schema
│   └── seeds/
│
├── src/
│   ├── Config/
│   │   └── ConfigLoader.php      # Configuration management
│   │
│   ├── Entities/
│   │   ├── BaseEntity.php        # Base entity class
│   │   ├── Employee.php          # Employee entity
│   │   ├── ForwardingRule.php    # Forwarding rule entity
│   │   ├── Holiday.php           # Holiday entity
│   │   ├── OverrideRule.php      # Override rule entity
│   │   ├── OnCallRotation.php   # On-call rotation entity
│   │   ├── RotationGroup.php     # Rotation group entity
│   │   └── WorkingHours.php     # Working hours entity
│   │
│   ├── Decision/
│   │   └── DecisionEngine.php    # Core decision logic
│   │
│   ├── VoIP/
│   │   ├── VoIPProviderInterface.php  # Provider interface
│   │   ├── AbstractVoIPProvider.php  # Base provider
│   │   └── Sipura/
│   │       └── SipuraProvider.php    # Sipura device implementation
│   │
│   ├── Services/
│   │   └── ForwardingService.php     # Main forwarding service
│   │
│   ├── Repositories/
│   │   ├── BaseRepository.php        # Base repository
│   │   ├── EmployeeRepository.php    # Employee data access
│   │   ├── RuleRepository.php        # Rule data access
│   │   ├── HolidayRepository.php    # Holiday data access
│   │   ├── OverrideRepository.php    # Override data access
│   │   ├── OnCallRepository.php      # On-call data access
│   │   └── StateRepository.php       # System state data access
│   │
│   ├── Scheduler/
│   │   └── ForwardingScheduler.php   # Scheduler service
│   │
│   ├── Logging/
│   │   └── ForwardLogger.php         # Logging service
│   │
│   └── Database/
│       └── Connection.php            # Database connection
│
├── tests/
│   └── DecisionEngineTest.php        # Unit tests
│
├── logs/                             # Log files (created at runtime)
├── data/                             # SQLite database (created at runtime)
│
├── public/
│   ├── index.php                    # Web entry point
│   ├── cli.php                      # CLI entry point
│   └── api.php                      # API entry point
│
└── docs/
    ├── ARCHITECTURE.md              # This file
    ├── API.md                       # API documentation
    └── SETUP.md                     # Setup guide
```

## Database Schema

### Core Tables

| Table | Purpose |
|-------|---------|
| `employees` | Employee information and phone numbers |
| `rotation_groups` | Groups for on-call rotation |
| `oncall_rotations` | On-call rotation schedules |
| `forwarding_rules` | Main rules with priorities |
| `override_rules` | Temporary manual overrides |
| `holidays` | Company holidays and special days |
| `working_hours` | Standard working hours per day |
| `options` | System configuration |
| `forward_log` | Forwarding change audit log |
| `system_state` | Current forwarding state |
| `audit_log` | Security audit trail |

### Rule Priority Ranges

| Priority | Type | Description |
|----------|------|-------------|
| 1-10 | Override | Manual overrides (highest) |
| 11-20 | Event | One-time specific events |
| 21-30 | OnCall | On-call rotation |
| 31-40 | Holiday | Holiday-specific rules |
| 41-50 | Working Hours | Time-based working hours |
| 91-100 | Fallback | Default fallback (lowest) |

## Decision Flow Algorithm

```
1. Get current DateTime
2. Check if holiday
3. Check if working hours
4. Check for active override
   └─ YES → Use override number
   └─ NO  → Continue
5. Find matching event rules
   └─ FOUND → Use event rule
   └─ NONE → Continue
6. Check on-call rotation
   └─ ACTIVE → Use rotation target
   └─ NONE → Continue
7. Check holiday rules
   └─ HOLIDAY + RULE → Use holiday rule
   └─ HOLIDAY ONLY → Use holiday forward_to
   └─ NO HOLIDAY → Continue
8. Check working hours
   └─ DURING HOURS → Use working hours target
   └─ AFTER HOURS → Use after-hours target
9. Apply fallback
   └─ fallback | voicemail | nothing | last_known
10. Compare with current device value
    └─ SAME → No device update needed
    └─ DIFFERENT → Send to VoIP device
11. Log decision
12. Handle errors (retry/alert)
```

## Edge Case Handling

### When No Rule Matches
- `fallback`: Use configured fallback number
- `voicemail`: Route to voicemail
- `nothing`: Leave current setting unchanged
- `last_known`: Keep last successful setting

### When Multiple Rules Match
- Configured by `behavior.on_multiple_match`:
  - `priority`: Use lowest priority number (highest priority rule)
  - `random`: Random selection
  - `roundrobin`: Rotate through matches
  - `first_match`: Use first match found

### When Device Fails
- Keep last successful setting
- Log error
- Retry with exponential backoff
- Alert after threshold (default: 5 failures)

### Holiday Handling
- Holidays can override working days
- Recurring holidays repeat annually
- Can specify custom forwarding number
- Can be marked as workday (reverse)

## VoIP Integration

### Supported Devices

| Provider | Type | Features |
|----------|------|----------|
| Sipura/Linksys SPA | sipura | Full support |
| Cisco | cisco | Full support (planned) |
| Grandstream | grandstream | Full support (planned) |
| Generic HTTP | generic | Basic forwarding |

### Sipura Integration Details

```php
// Configuration
$config = [
    'host' => '10.11.49.84',
    'port' => 80,
    'path' => '/admin/bsipura.spa',
    'username' => 'admin',
    'password' => getenv('VOIP_PASSWORD') ?: 'your-voip-password',
    'forward_param' => '43567',
];

// Set forwarding
$provider = new SipuraProvider($config);
$response = $provider->setForwardTo('+421901234567');

// Check response
if ($response->isSuccess()) {
    echo "Forwarding set successfully";
}
```

## Security

### Credentials Storage

Credentials should NEVER be hardcoded. Options:

1. **Environment Variables** (Recommended)
   ```bash
   export VOIP_USERNAME="admin"
   export VOIP_PASSWORD="secret"
   ```

2. **Separate Credentials File**
   ```yaml
   # config/credentials.env
   voip_username=admin
   voip_password=secret
   ```

3. **Environment-Specific Config**
   ```yaml
   # config/config.production.yaml
   credentials:
     voip_username: "${VOIP_USERNAME}"
     voip_password: "${VOIP_PASSWORD}"
   ```

### Authentication
- HTTP Digest authentication for Sipura devices
- Support for Basic and Digest auth
- Credentials encrypted at rest in database

## Logging

### Log Files

| File | Purpose |
|------|---------|
| `logs/hotplan.log` | General application logs |
| `logs/device.log` | VoIP device communication |
| `logs/audit.log` | Security and compliance audit |

### Log Levels
- `debug`: Detailed debugging information
- `info`: Normal operation events
- `warning`: Potential issues
- `error`: Errors that need attention
- `critical`: System failures

### Rotation
- Automatic rotation when file exceeds limit
- Configurable max size (default: 10MB)
- Configurable number of backup files (default: 5)

## Scheduler

### Modes

1. **Daemon Mode**
   - Continuous running
   - Checks at configured interval
   - Best for always-on systems

2. **Cron Mode**
   - Triggered by system scheduler
   - Single execution per trigger
   - Better for low-resource environments

### Configuration
```yaml
scheduler:
  enabled: true
  check_interval: 60      # seconds
  preload_minutes: 5     # activate rules early
```

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/status` | GET | Get current forwarding status |
| `/api/forward` | POST | Set forwarding manually |
| `/api/forward/clear` | POST | Clear forwarding |
| `/api/decide` | GET | Get decision for specific time |
| `/api/override` | POST | Create temporary override |
| `/api/rules` | GET | List all rules |
| `/api/rules` | POST | Create new rule |
| `/api/scheduler/status` | GET | Get scheduler status |
| `/api/scheduler/start` | POST | Start scheduler |
| `/api/scheduler/stop` | POST | Stop scheduler |

## Recommendations for Refactoring Existing Code

1. **Separate VoIP Communication**
   - Move cURL code to VoIP provider
   - Create interface for testability
   - Add retry logic and error handling

2. **State Management**
   - Store last forwarding value in database
   - Compare before sending to device
   - Persist device state across restarts

3. **Decision Engine**
   - Extract rule evaluation to separate class
   - Make priority resolution configurable
   - Add logging to decision process

4. **Configuration**
   - Move hardcoded values to config
   - Use environment variables
   - Support multiple environments

5. **Testing**
   - Add unit tests for decision logic
   - Mock VoIP provider for testing
   - Test edge cases

## Performance Considerations

1. **Database Queries**
   - Index on frequently queried columns
   - Cache holiday lookups
   - Batch rule loading

2. **Device Communication**
   - Only send when value changes
   - Use efficient retry strategy
   - Connection pooling where possible

3. **Memory**
   - Lazy load entities
   - Limit log file sizes
   - Clean up old state data

## Future Enhancements

1. **Multi-Device Support**
   - Multiple VoIP devices
   - Load balancing
   - Failover routing

2. **Advanced Scheduling**
   - Complex recurring patterns
   - Conditional rules
   - Integration with external calendars

3. **Reporting**
   - Forwarding statistics
   - Employee on-call reports
   - Holiday coverage analysis

4. **Integration**
   - Slack/Teams notifications
   - External calendar sync
   - SMS alerts

---

For setup instructions, see [SETUP.md](SETUP.md).
For API documentation, see [API.md](API.md).
