-- =====================================================
-- HotPlan - Sample Data (optional)
-- Safe to run multiple times (idempotent)
-- =====================================================

-- Sample employees (email is UNIQUE)
INSERT OR IGNORE INTO employees (name, email, phone_internal, phone_mobile, phone_primary, priority) VALUES
('Ján Novák', 'jan.novak@company.sk', '101', '+421901111111', '101', 10),
('Mária Smitková', 'maria.smitkova@company.sk', '102', '+421902222222', '102', 20),
('Peter Horváth', 'peter.horvath@company.sk', '103', '+421903333333', '103', 30);

-- Sample rotation group (avoid duplicates by name)
INSERT INTO rotation_groups (name, description, rotation_type)
SELECT 'Helpdesk On-Call', 'Primary on-call rotation for helpdesk', 'weekly'
WHERE NOT EXISTS (SELECT 1 FROM rotation_groups WHERE name = 'Helpdesk On-Call');

-- Assign employees to rotation group by lookup (safe if re-run)
UPDATE employees
SET rotation_group_id = (SELECT id FROM rotation_groups WHERE name = 'Helpdesk On-Call' LIMIT 1)
WHERE email IN ('jan.novak@company.sk', 'maria.smitkova@company.sk', 'peter.horvath@company.sk');

-- Sample on-call rotation (avoid duplicates by name)
INSERT INTO oncall_rotations (name, group_id, rotation_pattern, rotation_start_date, during_hours_forward_to, after_hours_forward_to, use_employee_mobile)
SELECT
  'Helpdesk Primary',
  (SELECT id FROM rotation_groups WHERE name = 'Helpdesk On-Call' LIMIT 1),
  'weekly',
  DATE('now', 'weekday 1', '-7 days'),
  '101',
  NULL,
  1
WHERE NOT EXISTS (SELECT 1 FROM oncall_rotations WHERE name = 'Helpdesk Primary');

-- Sample holidays (avoid duplicates by name + date)
INSERT INTO holidays (name, date, is_recurring, forward_to)
SELECT 'Christmas', '2024-12-25', 1, '+421901234567'
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE name = 'Christmas' AND date = '2024-12-25');

INSERT INTO holidays (name, date, is_recurring, forward_to)
SELECT 'New Year', '2024-01-01', 1, '+421901234567'
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE name = 'New Year' AND date = '2024-01-01');

INSERT INTO holidays (name, date, is_recurring, forward_to)
SELECT 'Slovak National Uprising', '2024-08-29', 1, '102'
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE name = 'Slovak National Uprising' AND date = '2024-08-29');

-- Sample working hours (avoid duplicates by day_of_week)
INSERT INTO working_hours (day_of_week, is_working_day, start_time, end_time, forward_to_internal)
SELECT 1, 1, '08:00', '16:00', '100'
WHERE NOT EXISTS (SELECT 1 FROM working_hours WHERE day_of_week = 1 AND is_active = 1);

INSERT INTO working_hours (day_of_week, is_working_day, start_time, end_time, forward_to_internal)
SELECT 2, 1, '08:00', '16:00', '100'
WHERE NOT EXISTS (SELECT 1 FROM working_hours WHERE day_of_week = 2 AND is_active = 1);

INSERT INTO working_hours (day_of_week, is_working_day, start_time, end_time, forward_to_internal)
SELECT 3, 1, '08:00', '16:00', '100'
WHERE NOT EXISTS (SELECT 1 FROM working_hours WHERE day_of_week = 3 AND is_active = 1);

INSERT INTO working_hours (day_of_week, is_working_day, start_time, end_time, forward_to_internal)
SELECT 4, 1, '08:00', '16:00', '100'
WHERE NOT EXISTS (SELECT 1 FROM working_hours WHERE day_of_week = 4 AND is_active = 1);

INSERT INTO working_hours (day_of_week, is_working_day, start_time, end_time, forward_to_internal)
SELECT 5, 1, '08:00', '14:00', '100'
WHERE NOT EXISTS (SELECT 1 FROM working_hours WHERE day_of_week = 5 AND is_active = 1);

-- Sample forwarding rules (avoid duplicates by name)
INSERT INTO forwarding_rules (name, rule_type, priority, forward_to, description)
SELECT 'Default Fallback', 'fallback', 100, '', 'Fallback when nothing else applies'
WHERE NOT EXISTS (SELECT 1 FROM forwarding_rules WHERE name = 'Default Fallback');

INSERT INTO forwarding_rules (name, rule_type, priority, forward_to, description)
SELECT 'Helpdesk Primary', 'oncall_rotation', 25, '101', 'On-call rotation for helpdesk'
WHERE NOT EXISTS (SELECT 1 FROM forwarding_rules WHERE name = 'Helpdesk Primary');

INSERT INTO forwarding_rules (name, rule_type, priority, forward_to, description)
SELECT 'After Hours Mobile', 'working_hours', 45, '+421904444444', 'After hours mobile forwarding'
WHERE NOT EXISTS (SELECT 1 FROM forwarding_rules WHERE name = 'After Hours Mobile');

