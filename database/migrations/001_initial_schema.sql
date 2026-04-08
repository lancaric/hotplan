-- =====================================================
-- HotPlan - Hotline Forwarding Management System
-- Database Schema v1.0
-- =====================================================

-- Enable foreign keys
PRAGMA foreign_keys = ON;

-- =====================================================
-- EMPLOYEES TABLE
-- Stores employee information and their phone numbers
-- =====================================================
CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone_internal VARCHAR(20),          -- Internal extension
    phone_mobile VARCHAR(20),            -- Mobile number for after-hours
    phone_primary VARCHAR(20),           -- Primary contact number
    is_active BOOLEAN DEFAULT 1,
    is_oncall BOOLEAN DEFAULT 0,         -- Currently on call
    priority INTEGER DEFAULT 100,        -- Lower = higher priority
    rotation_group_id INTEGER,           -- FK to rotation_groups
    metadata JSON,                       -- Additional data (department, role, etc.)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (rotation_group_id) REFERENCES rotation_groups(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_employees_active ON employees(is_active);
CREATE INDEX IF NOT EXISTS idx_employees_oncall ON employees(is_oncall);
CREATE INDEX IF NOT EXISTS idx_employees_rotation_group ON employees(rotation_group_id);

-- =====================================================
-- ROTATION GROUPS
-- Groups of employees who rotate on-call duties
-- =====================================================
CREATE TABLE IF NOT EXISTS rotation_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rotation_type TEXT NOT NULL DEFAULT 'weekly' CHECK (rotation_type IN ('weekly', 'daily', 'custom')),
    rotation_order JSON,                 -- Array of employee IDs in rotation order
    current_index INTEGER DEFAULT 0,     -- Current position in rotation
    rotation_start_date DATE,           -- When rotation started
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- HOLIDAYS TABLE
-- Company holidays and non-working days
-- =====================================================
CREATE TABLE IF NOT EXISTS holidays (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    is_recurring BOOLEAN DEFAULT 0,     -- Repeats every year
    country VARCHAR(10),                -- Country-specific holiday
    region VARCHAR(50),                 -- Region for regional holidays
    forward_to VARCHAR(50),             -- Override number for this holiday
    is_workday BOOLEAN DEFAULT 0,       -- Override: treat as workday
    priority INTEGER DEFAULT 50,        -- Holiday priority
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(date, country, region)
);

CREATE INDEX IF NOT EXISTS idx_holidays_date ON holidays(date);
CREATE INDEX IF NOT EXISTS idx_holidays_active ON holidays(is_active);

-- =====================================================
-- WORKING HOURS CONFIGURATION
-- Defines standard working hours per day of week
-- =====================================================
CREATE TABLE IF NOT EXISTS working_hours (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    day_of_week TINYINT NOT NULL,        -- 0=Sunday, 1=Monday, ... 6=Saturday
    is_working_day BOOLEAN DEFAULT 1,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    forward_to_internal VARCHAR(20),     -- Forward during work hours
    forward_to_external VARCHAR(20),     -- Alternative external number
    effective_from DATE,
    effective_until DATE,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    CHECK (day_of_week >= 0 AND day_of_week <= 6),
    CHECK (is_working_day = 0 OR start_time < end_time)
);

CREATE INDEX IF NOT EXISTS idx_working_hours_day ON working_hours(day_of_week, is_active);

-- =====================================================
-- FORWARDING RULES
-- Main rules table with priority-based evaluation
-- =====================================================
CREATE TABLE IF NOT EXISTS forwarding_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    rule_type TEXT NOT NULL CHECK (rule_type IN (
        'override',           -- Manual override (highest priority)
        'event',              -- One-time event
        'oncall_rotation',    -- Recurring on-call rotation
        'working_hours',      -- Based on working hours
        'holiday',            -- Holiday-specific
        'fallback'            -- Default fallback (lowest priority)
    )),
    
    priority INTEGER NOT NULL,           -- Lower number = higher priority
                                           -- 1-10: Override/Manual
                                           -- 11-20: Event/Specific time
                                           -- 21-30: On-call rotation
                                           -- 31-40: Holiday
                                           -- 41-50: Working hours
                                           -- 91-100: Fallback
    
    is_active BOOLEAN DEFAULT 1,
    is_recurring BOOLEAN DEFAULT 0,
    
    -- Time constraints
    valid_from DATETIME,
    valid_until DATETIME,
    days_of_week JSON,                  -- ["mon", "tue", "wed", ...] or null for all
    start_time TIME,                    -- Time range start (null = whole day)
    end_time TIME,                      -- Time range end (null = whole day)
    
    -- Target configuration
    forward_to VARCHAR(50) NOT NULL,
    target_type TEXT NOT NULL DEFAULT 'number' CHECK (target_type IN ('employee', 'group', 'number', 'voicemail', 'queue')),
    target_employee_id INTEGER,        -- FK to employees (if target_type='employee')
    target_group_id INTEGER,           -- FK to rotation_groups (if target_type='group')
    
    -- Conditions
    holiday_id INTEGER,                 -- Specific holiday reference
    requires_employee BOOLEAN DEFAULT 0,
    
    -- Meta
    description TEXT,
    created_by INTEGER,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (target_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (target_group_id) REFERENCES rotation_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (holiday_id) REFERENCES holidays(id) ON DELETE SET NULL,
    
    CHECK (priority >= 1 AND priority <= 100)
);

CREATE INDEX IF NOT EXISTS idx_rules_type ON forwarding_rules(rule_type);
CREATE INDEX IF NOT EXISTS idx_rules_priority ON forwarding_rules(priority);
CREATE INDEX IF NOT EXISTS idx_rules_active ON forwarding_rules(is_active);
CREATE INDEX IF NOT EXISTS idx_rules_time ON forwarding_rules(valid_from, valid_until);

-- =====================================================
-- ON-CALL ROTATIONS
-- Defines recurring on-call schedules
-- =====================================================
CREATE TABLE IF NOT EXISTS oncall_rotations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    group_id INTEGER NOT NULL,          -- FK to rotation_groups
    
    rotation_pattern TEXT NOT NULL DEFAULT 'weekly' CHECK (rotation_pattern IN (
        'weekly',                        -- Changes weekly
        'daily',                         -- Changes daily
        'biweekly',
        'custom'
    )),
    
    rotation_start_date DATE NOT NULL,
    rotation_direction TEXT NOT NULL DEFAULT 'forward' CHECK (rotation_direction IN ('forward', 'backward')),
    
    -- Time constraints
    is_24x7 BOOLEAN DEFAULT 0,           -- Full day coverage
    default_start_time TIME,            -- Default: 08:00
    default_end_time TIME,              -- Default: 17:00
    
    -- What to do during rotation
    during_hours_forward_to VARCHAR(50),
    after_hours_forward_to VARCHAR(50),
    use_employee_mobile BOOLEAN DEFAULT 1,
    
    -- Fallback if rotation is empty
    fallback_rule_id INTEGER,
    
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (group_id) REFERENCES rotation_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (fallback_rule_id) REFERENCES forwarding_rules(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_oncall_active ON oncall_rotations(is_active);

-- =====================================================
-- OVERRIDE RULES
-- Manual overrides that temporarily change forwarding
-- =====================================================
CREATE TABLE IF NOT EXISTS override_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    override_type TEXT NOT NULL CHECK (override_type IN (
        'temporary',                     -- Time-based override
        'indefinite',                    -- Until manually disabled
        'until_time',                    -- Until specific time
        'until_employee'                 -- Until specific employee takes over
    )),
    
    is_active BOOLEAN DEFAULT 1,
    
    -- Time constraints
    starts_at DATETIME,
    ends_at DATETIME,
    
    -- Override configuration
    forward_to VARCHAR(50) NOT NULL,
    reason TEXT,
    
    -- Source
    created_by INTEGER,
    source_employee_id INTEGER,         -- Employee who activated
    
    -- Linked rule to restore
    restored_rule_id INTEGER,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    
    FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (source_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (restored_rule_id) REFERENCES forwarding_rules(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_override_active ON override_rules(is_active);
CREATE INDEX IF NOT EXISTS idx_override_time ON override_rules(starts_at, ends_at);

-- =====================================================
-- OPTIONS / SETTINGS
-- System-wide configuration
-- =====================================================
CREATE TABLE IF NOT EXISTS options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    option_key VARCHAR(100) UNIQUE NOT NULL,
    option_value TEXT,
    option_type TEXT NOT NULL DEFAULT 'string' CHECK (option_type IN ('string', 'integer', 'boolean', 'json', 'array')),
    group_name VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_encrypted BOOLEAN DEFAULT 0,     -- Encrypt value (for passwords)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default settings
INSERT OR IGNORE INTO options (option_key, option_value, option_type, group_name, description) VALUES
-- VoIP Device Configuration
('voip.provider', 'sipura', 'string', 'voip', 'VoIP provider type'),
('voip.host', '10.11.49.84', 'string', 'voip', 'VoIP device hostname/IP'),
('voip.port', '80', 'integer', 'voip', 'VoIP device port'),
('voip.path', '/admin/bsipura.spa', 'string', 'voip', 'API endpoint path'),
('voip.timeout', '30', 'integer', 'voip', 'Request timeout in seconds'),
('voip.retry_count', '3', 'integer', 'voip', 'Number of retries on failure'),
('voip.retry_delay', '5', 'integer', 'voip', 'Delay between retries in seconds'),

-- Authentication
('voip.auth_type', 'digest', 'string', 'auth', 'HTTP authentication type'),
('voip.username', '', 'string', 'auth', 'Username for VoIP device'),
('voip.password', '', 'string', 'auth', 'Password for VoIP device (encrypted)'),

-- Forwarding Parameters
('voip.forward_param', '43567', 'string', 'voip', 'Parameter name for forwarding number'),
('voip.forward_prefix', '', 'string', 'voip', 'Prefix to add to forward number'),

-- Default Numbers
('default.forward_internal', '100', 'string', 'defaults', 'Default internal forwarding'),
('default.forward_external', '+421901234567', 'string', 'defaults', 'Default external forwarding'),
('default.forward_voicemail', '*97', 'string', 'defaults', 'Voicemail shortcut'),
('default.fallback', '', 'string', 'defaults', 'Fallback number if nothing else applies'),

-- Behavior Settings
('behavior.on_no_rule', 'fallback', 'string', 'behavior', 'Action when no rule matches: fallback|voicemail|nothing'),
('behavior.on_device_error', 'keep_last', 'string', 'behavior', 'Action on device error: keep_last|clear|retry'),
('behavior.on_multiple_match', 'priority', 'string', 'behavior', 'Multiple matching rules: priority|random|roundrobin'),
('behavior.enable_logging', '1', 'boolean', 'behavior', 'Enable detailed logging'),
('behavior.log_retention_days', '90', 'integer', 'behavior', 'Log retention period'),

-- Scheduler Settings
('scheduler.check_interval', '60', 'integer', 'scheduler', 'Check interval in seconds'),
('scheduler.enabled', '1', 'boolean', 'scheduler', 'Enable automatic scheduling'),
('scheduler.preload_minutes', '5', 'integer', 'scheduler', 'Minutes before event to activate'),

-- Timezone
('system.timezone', 'Europe/Bratislava', 'string', 'system', 'System timezone'),
('system.locale', 'sk_SK', 'string', 'system', 'System locale');

-- =====================================================
-- FORWARD LOG
-- Audit trail of all forwarding changes
-- =====================================================
CREATE TABLE IF NOT EXISTS forward_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- What triggered the change
    trigger_type TEXT NOT NULL CHECK (trigger_type IN (
        'scheduler',                     -- Scheduled check
        'manual',                        -- Manual change
        'api',                           -- API call
        'override',                      -- Override activated
        'system',                        -- System startup/recovery
        'rule_change'                     -- Rule was modified
    )),
    
    -- Context
    triggered_by VARCHAR(100),          -- User/system identifier
    rule_id INTEGER,                    -- FK to rule that was applied
    rule_name VARCHAR(255),
    rule_type VARCHAR(50),
    
    -- Decision details
    is_holiday BOOLEAN DEFAULT 0,
    is_working_hours BOOLEAN DEFAULT 1,
    current_time TIME,
    current_date DATE,
    day_of_week VARCHAR(10),
    
    -- Values
    previous_forward_to VARCHAR(50),
    new_forward_to VARCHAR(50),
    was_changed BOOLEAN DEFAULT 0,       -- Was the device actually updated?
    
    -- Result
    success BOOLEAN DEFAULT 1,
    error_message TEXT,
    device_response TEXT,               -- Raw response from device
    
    -- Performance
    request_duration_ms INTEGER,
    
    metadata JSON                       -- Additional context
);

CREATE INDEX IF NOT EXISTS idx_log_created ON forward_log(created_at);
CREATE INDEX IF NOT EXISTS idx_log_trigger ON forward_log(trigger_type);
CREATE INDEX IF NOT EXISTS idx_log_success ON forward_log(success);
CREATE INDEX IF NOT EXISTS idx_log_date ON forward_log("current_date");

-- =====================================================
-- STATE TRACKING
-- Stores current and last known state
-- =====================================================
CREATE TABLE IF NOT EXISTS system_state (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    state_key VARCHAR(100) UNIQUE NOT NULL,
    state_value TEXT,
    state_type TEXT NOT NULL DEFAULT 'string' CHECK (state_type IN ('string', 'integer', 'boolean', 'json')),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(100)
);

-- Initial state entries
INSERT OR IGNORE INTO system_state (state_key, state_value, state_type, updated_by) VALUES
('current_forward_to', '', 'string', 'system'),
('last_successful_forward_to', '', 'string', 'system'),
('last_device_response', '', 'string', 'system'),
('last_successful_change_at', NULL, 'string', 'system'),
('device_status', 'unknown', 'string', 'system'),
('consecutive_failures', '0', 'integer', 'system'),
('last_check_at', NULL, 'string', 'system'),
('scheduler_status', 'stopped', 'string', 'system');

-- =====================================================
-- API KEYS / INTEGRATION
-- For external API access
-- =====================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    secret_hash VARCHAR(255) NOT NULL,
    permissions JSON,                    -- ["read", "write", "override"]
    rate_limit INTEGER DEFAULT 100,     -- Requests per minute
    is_active BOOLEAN DEFAULT 1,
    last_used_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME
);

CREATE INDEX IF NOT EXISTS idx_api_key ON api_keys(api_key);

-- =====================================================
-- AUDIT TRAIL
-- Security and compliance audit log
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    action_type VARCHAR(50) NOT NULL,   -- CREATE, UPDATE, DELETE, OVERRIDE, ACCESS
    entity_type VARCHAR(50) NOT NULL,   -- employees, rules, holidays, etc.
    entity_id INTEGER,
    
    performed_by VARCHAR(100),          -- User or system
    performed_from VARCHAR(45),         -- IP address
    
    old_value JSON,
    new_value JSON,
    
    description TEXT,
    success BOOLEAN DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_performed_by ON audit_log(performed_by);
CREATE INDEX IF NOT EXISTS idx_audit_time ON audit_log(created_at);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- Active employees for rotation
DROP VIEW IF EXISTS v_active_employees;
CREATE VIEW v_active_employees AS
SELECT 
    e.*,
    rg.name as rotation_group_name
FROM employees e
LEFT JOIN rotation_groups rg ON e.rotation_group_id = rg.id
WHERE e.is_active = 1;

-- Current on-call employees
DROP VIEW IF EXISTS v_current_oncall;
CREATE VIEW v_current_oncall AS
SELECT 
    e.*,
    ocr.name as rotation_name,
    ocr.during_hours_forward_to,
    ocr.after_hours_forward_to,
    ocr.use_employee_mobile
FROM employees e
INNER JOIN oncall_rotations ocr ON e.rotation_group_id = ocr.group_id
WHERE e.is_oncall = 1 AND ocr.is_active = 1;

-- Upcoming holidays
DROP VIEW IF EXISTS v_upcoming_holidays;
CREATE VIEW v_upcoming_holidays AS
SELECT 
    h.*,
    CASE 
        WHEN h.is_recurring = 1 THEN DATE('now', 'localtime', 'start of year', '+' || (strftime('%j', 'now') - 1) || ' days')
        ELSE h.date
    END as effective_date
FROM holidays h
WHERE h.is_active = 1
AND h.date >= DATE('now', 'localtime')
ORDER BY h.date
LIMIT 30;

-- Recent forwarding changes
DROP VIEW IF EXISTS v_recent_forward_changes;
CREATE VIEW v_recent_forward_changes AS
SELECT 
    fl.*,
    fr.name as rule_name
FROM forward_log fl
LEFT JOIN forwarding_rules fr ON fl.rule_id = fr.id
ORDER BY fl.created_at DESC
LIMIT 100;

-- =====================================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- =====================================================

-- Auto-update updated_at timestamp
CREATE TRIGGER IF NOT EXISTS trg_employees_updated 
AFTER UPDATE ON employees
BEGIN
    UPDATE employees SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_rules_updated 
AFTER UPDATE ON forwarding_rules
BEGIN
    UPDATE forwarding_rules SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_holidays_updated 
AFTER UPDATE ON holidays
BEGIN
    UPDATE holidays SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_options_updated 
AFTER UPDATE ON options
BEGIN
    UPDATE options SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Log rule changes
CREATE TRIGGER IF NOT EXISTS trg_rule_change_audit
AFTER UPDATE ON forwarding_rules
FOR EACH ROW
WHEN OLD.is_active != NEW.is_active 
    OR OLD.forward_to != NEW.forward_to 
    OR OLD.priority != NEW.priority
BEGIN
    INSERT INTO audit_log (action_type, entity_type, entity_id, old_value, new_value, description)
    VALUES (
        'UPDATE',
        'forwarding_rules',
        NEW.id,
        json_object('is_active', OLD.is_active, 'forward_to', OLD.forward_to, 'priority', OLD.priority),
        json_object('is_active', NEW.is_active, 'forward_to', NEW.forward_to, 'priority', NEW.priority),
        'Forwarding rule modified'
    );
END;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

/*
NOTE: Sample data is optional. Run it separately (recommended) via `php public/cli.php db:seed`.

-- Sample employees
INSERT INTO employees (name, email, phone_internal, phone_mobile, phone_primary, priority) VALUES
('Ján Novák', 'jan.novak@company.sk', '101', '+421901111111', '101', 10),
('Mária Smitková', 'maria.smitkova@company.sk', '102', '+421902222222', '102', 20),
('Peter Horváth', 'peter.horvath@company.sk', '103', '+421903333333', '103', 30);

-- Sample rotation group
INSERT INTO rotation_groups (name, description, rotation_type) VALUES
('Helpdesk On-Call', 'Primary on-call rotation for helpdesk', 'weekly');

-- Assign employees to rotation
UPDATE employees SET rotation_group_id = 1 WHERE id IN (1, 2, 3);

-- Sample on-call rotation
INSERT INTO oncall_rotations (name, group_id, rotation_pattern, rotation_start_date, during_hours_forward_to, after_hours_forward_to, use_employee_mobile)
VALUES ('Helpdesk Primary', 1, 'weekly', DATE('now', 'weekday 1', '-7 days'), '101', NULL, 1);

-- Sample holidays
INSERT INTO holidays (name, date, is_recurring, forward_to) VALUES
('Christmas', '2024-12-25', 1, '+421901234567'),
('New Year', '2024-01-01', 1, '+421901234567'),
('Slovak National Uprising', '2024-08-29', 1, '102');

-- Sample working hours
INSERT INTO working_hours (day_of_week, is_working_day, start_time, end_time, forward_to_internal) VALUES
(1, 1, '08:00', '16:00', '100'),  -- Monday
(2, 1, '08:00', '16:00', '100'),  -- Tuesday
(3, 1, '08:00', '16:00', '100'),  -- Wednesday
(4, 1, '08:00', '16:00', '100'),  -- Thursday
(5, 1, '08:00', '14:00', '100'),  -- Friday (shorter)
(6, 0, '00:00', '00:00', NULL),   -- Saturday (non-working)
(0, 0, '00:00', '00:00', NULL);    -- Sunday (non-working)

-- Sample forwarding rules
INSERT INTO forwarding_rules (name, rule_type, priority, forward_to, description) VALUES
('Default Fallback', 'fallback', 100, '', 'Fallback when nothing else applies'),
('Helpdesk Primary', 'oncall_rotation', 25, '101', 'On-call rotation for helpdesk'),
('After Hours Mobile', 'working_hours', 45, '+421904444444', 'After hours mobile forwarding');
*/
