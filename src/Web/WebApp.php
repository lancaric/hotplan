<?php

declare(strict_types=1);

namespace HotPlan\Web;

use HotPlan\Config\ConfigLoader;
use HotPlan\Database\Connection;
use HotPlan\Decision\DecisionEngine;
use HotPlan\Logging\ForwardLogger;
use HotPlan\Repositories\EmployeeRepository;
use HotPlan\Repositories\HolidayRepository;
use HotPlan\Repositories\OnCallRepository;
use HotPlan\Repositories\OverrideRepository;
use HotPlan\Repositories\RuleRepository;
use HotPlan\Repositories\WorkingHoursRepository;
use HotPlan\Services\ForwardingService;

final class WebApp
{

    public function __construct(
        private readonly ConfigLoader $config,
        private readonly Connection $db,
        private readonly ForwardLogger $logger,
        private readonly DecisionEngine $decisionEngine,
        private readonly ForwardingService $forwardingService,
    ) {}

    public function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Normalize
        $path = rtrim($path, '/') ?: '/';

        if ($this->isWebAuthRequired() && !$this->isAuthExemptPath($path) && !$this->isAuthed()) {
            $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            $this->redirect('/login?next=' . $next);
        }

        try {
            if ($method === 'POST') {
                $this->handlePost($path);
                return;
            }

            $this->handleGet($path);
        } catch (\Throwable $e) {
            $this->flash('err', $e->getMessage());
            $this->redirect('/');
        }
    }

    private function isWebAuthRequired(): bool
    {
        return (bool) ($this->config->get('security.web_auth_required', true) ?? true);
    }

    private function isAuthed(): bool
    {
        return ($_SESSION['auth'] ?? null) === true;
    }

    private function isAuthExemptPath(string $path): bool
    {
        if ($path === '/login' || $path === '/logout') {
            return true;
        }

        return str_starts_with($path, '/assets/');
    }

    private function getAdminUsername(): string
    {
        $env = getenv('HOTPLAN_ADMIN_USERNAME');
        if ($env !== false && $env !== '') {
            return $env;
        }

        $cfg = (string) ($this->config->get('security.web_admin_username', 'admin') ?? 'admin');
        if (str_starts_with($cfg, '${') && str_ends_with($cfg, '}')) {
            return 'admin';
        }

        return $cfg;
    }

    private function getAdminPassword(): string
    {
        $env = getenv('HOTPLAN_ADMIN_PASSWORD');
        if ($env !== false && $env !== '') {
            return $env;
        }

        $cfg = (string) ($this->config->get('security.web_admin_password', 'admin') ?? 'admin');
        if (str_starts_with($cfg, '${') && str_ends_with($cfg, '}')) {
            return 'admin';
        }

        return $cfg;
    }

    private function handleGet(string $path): void
    {
        switch ($path) {
            case '/login':
                $this->pageLogin();
                return;
            case '/logout':
                $this->actionLogout();
                return;
            case '/':
                $this->pageDashboard();
                return;
            case '/calendar':
                $this->pageCalendar();
                return;
            case '/employees':
                $this->pageEmployees();
                return;
            case '/rules':
                $this->pageRules();
                return;
            case '/overrides':
                $this->pageOverrides();
                return;
            case '/holidays':
                $this->pageHolidays();
                return;
            case '/working-hours':
                $this->pageWorkingHours();
                return;
            case '/oncall':
                $this->pageOnCallRotations();
                return;
            case '/groups':
                $this->pageGroups();
                return;
            case '/config':
                $this->pageConfig();
                return;
            case '/logs':
                $this->pageLogs();
                return;
            default:
                http_response_code(404);
                $this->layout('Not Found', function () use ($path) {
                    View::render('not_found.php', ['path' => $path]);
                });
        }
    }

    private function handlePost(string $path): void
    {
        switch ($path) {
            case '/login':
                $this->actionLogin();
                return;
            case '/actions/run-cycle':
                $this->actionRunCycle();
                return;
            case '/employees/save':
                $this->actionEmployeeSave();
                return;
            case '/employees/delete':
                $this->actionEmployeeDelete();
                return;
            case '/rules/save':
                $this->actionRuleSave();
                return;
            case '/rules/delete':
                $this->actionRuleDelete();
                return;
            case '/overrides/create':
                $this->actionOverrideCreate();
                return;
            case '/calendar/create':
                $this->actionCalendarCreate();
                return;
            case '/overrides/clear':
                $this->actionOverrideClearAll();
                return;
            case '/holidays/save':
                $this->actionHolidaySave();
                return;
            case '/holidays/import-sk':
                $this->actionHolidayImportSk();
                return;
            case '/holidays/delete':
                $this->actionHolidayDelete();
                return;
            case '/working-hours/save':
                $this->actionWorkingHoursSave();
                return;
            case '/oncall/save':
                $this->actionOnCallSave();
                return;
            case '/oncall/delete':
                $this->actionOnCallDelete();
                return;
            case '/groups/save':
                $this->actionGroupSave();
                return;
            case '/groups/delete':
                $this->actionGroupDelete();
                return;
            case '/config/save':
                $this->actionConfigSave();
                return;
            default:
                http_response_code(404);
                $this->flash('err', 'Unknown action');
                $this->redirect('/');
        }
    }

    private function normalizeDateTime(?string $raw): ?string
    {
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }

        // HTML datetime-local: "YYYY-MM-DDTHH:MM" (optionally with seconds)
        $v = str_replace('T', ' ', $v);

        // Add seconds if missing
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $v)) {
            $v .= ':00';
        }

        return $v;
    }

    private function normalizeTime(?string $raw): ?string
    {
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $v)) {
            return $v . ':00';
        }

        return $v;
    }

    // ---------------- Pages ----------------

    private function pageDashboard(): void
    {
        $state = $this->forwardingService->getState();
        $device = $this->forwardingService->getDeviceStatus();
        $logs = $this->logger->getRecentLogs(30);

        $this->layout('Dashboard', function () use ($state, $device, $logs) {
            View::render('dashboard.php', [
                'state' => $state,
                'device' => $device,
                'logs' => $logs,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageLogin(): void
    {
        if ($this->isAuthed()) {
            $this->redirect('/');
        }

        $next = (string) ($_GET['next'] ?? '/');
        if ($next === '' || !str_starts_with($next, '/')) {
            $next = '/';
        }

        $this->layout('Prihlásenie', function () use ($next) {
            View::render('login.php', [
                'next' => $next,
                'flash' => $this->consumeFlash(),
            ]);
        }, authed: false);
    }

    private function pageCalendar(): void
    {
        $ym = $_GET['ym'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $ym)) {
            $ym = date('Y-m');
        }

        $year = (int) substr((string) $ym, 0, 4);
        $month = (int) substr((string) $ym, 5, 2);
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01 12:00:00', $year, $month));
        $startDow = (int) $first->format('N'); // 1..7 (Mon..Sun)
        $daysInMonth = (int) $first->format('t');

        $onCallRepo = new OnCallRepository($this->db);
        $employeeRepo = new EmployeeRepository($this->db);
        $holidayRepo = new HolidayRepository($this->db);
        $rotations = $onCallRepo->findAllActive();
        $employeeOptions = $this->buildCalendarEmployeeOptions();

        $monthStart = $first->setTime(0, 0, 0);
        $monthEnd = $first->setDate($year, $month, $daysInMonth)->setTime(23, 59, 59);
        $calendarEvents = $this->loadCalendarEvents($monthStart, $monthEnd, $employeeOptions);

        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dt = $first->setDate($year, $month, $d);
            $dayKey = $dt->format('Y-m-d');

            $holiday = $holidayRepo->findForDate($dt);
            $rot = [];
            foreach ($rotations as $r) {
                $empId = $onCallRepo->getCurrentOnCallEmployeeId($r->getGroupId(), $dt);
                $emp = $empId ? $employeeRepo->findById($empId) : null;
                $rot[] = [
                    'rotation' => $r->getName(),
                    'employee' => $emp?->getName(),
                ];
            }

            // CFWD segments for the day (helps visualize which rule is actually active)
            $segments = [];
            $windows = [
                ['from' => '00:00', 'to' => '08:00', 'at' => [3, 0]],
                ['from' => '08:00', 'to' => '16:00', 'at' => [12, 0]],
                ['from' => '16:00', 'to' => '22:00', 'at' => [18, 0]],
                ['from' => '22:00', 'to' => '24:00', 'at' => [23, 0]],
            ];

            foreach ($windows as $w) {
                $at = $dt->setTime($w['at'][0], $w['at'][1], 0);
                $decision = $this->decisionEngine->decide($at);
                $segments[] = [
                    'from' => $w['from'],
                    'to' => $w['to'],
                    'forward_to' => $decision->forwardTo ?? '',
                    'reason' => $decision->reason,
                    'rule' => $decision->matchedRule?->getName(),
                ];
            }

            // Merge adjacent segments with the same decision
            $merged = [];
            foreach ($segments as $s) {
                $lastIdx = count($merged) - 1;
                if ($lastIdx >= 0) {
                    $last = $merged[$lastIdx];
                    if (($last['forward_to'] ?? '') === ($s['forward_to'] ?? '') && ($last['reason'] ?? '') === ($s['reason'] ?? '')) {
                        $merged[$lastIdx]['to'] = $s['to'];
                        continue;
                    }
                }
                $merged[] = $s;
            }

            $days[$dayKey] = [
                'date' => $dt,
                'holiday' => $holiday?->getName(),
                'rotations' => $rot,
                'segments' => $merged,
                'events' => $calendarEvents[$dayKey] ?? [],
            ];
        }

        $prev = $first->modify('-1 month')->format('Y-m');
        $next = $first->modify('+1 month')->format('Y-m');

        $this->layout('Kalendár služieb', function () use ($ym, $first, $startDow, $daysInMonth, $days, $prev, $next) {
            View::render('calendar.php', [
                'ym' => $ym,
                'first' => $first,
                'startDow' => $startDow,
                'daysInMonth' => $daysInMonth,
                'days' => $days,
                'prev' => $prev,
                'next' => $next,
                'employees' => $this->buildCalendarEmployeeOptions(),
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageEmployees(): void
    {
        $employeeRepo = new EmployeeRepository($this->db);
        $employees = $employeeRepo->findAll();

        $groups = $this->db->fetchAll('SELECT * FROM rotation_groups ORDER BY name');

        $editId = (int) ($_GET['edit'] ?? 0);
        $editEmployee = null;
        if ($editId > 0) {
            $editEmployee = $this->db->fetch('SELECT * FROM employees WHERE id = ? LIMIT 1', [$editId]);
        }

        $showForm = $editId > 0 || isset($_GET['add']);

        $this->layout('Zamestnanci', function () use ($employees, $groups, $editEmployee, $showForm) {
            View::render('employees.php', [
                'employees' => $employees,
                'groups' => $groups,
                'editEmployee' => $editEmployee,
                'showForm' => $showForm,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageRules(): void
    {
        $rules = $this->db->fetchAll('SELECT * FROM forwarding_rules ORDER BY priority ASC');
        $employees = $this->db->fetchAll('SELECT id,name FROM employees WHERE is_active = 1 ORDER BY name');
        $groups = $this->db->fetchAll('SELECT id,name FROM rotation_groups ORDER BY name');

        $holidays = $this->db->fetchAll('SELECT id,name,date FROM holidays ORDER BY date DESC');

        $editId = (int) ($_GET['edit'] ?? 0);
        $editRule = $editId > 0
            ? $this->db->fetch('SELECT * FROM forwarding_rules WHERE id = ? LIMIT 1', [$editId])
            : null;
        $showForm = $editId > 0 || isset($_GET['add']);

        $this->layout('Pravidlá', function () use ($rules, $employees, $groups, $holidays, $editRule, $showForm) {
            View::render('rules.php', [
                'rules' => $rules,
                'employees' => $employees,
                'groups' => $groups,
                'holidays' => $holidays,
                'editRule' => $editRule,
                'showForm' => $showForm,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageOverrides(): void
    {
        $active = $this->db->fetchAll('SELECT * FROM override_rules WHERE is_active = 1 ORDER BY created_at DESC');
        $this->layout('Override', function () use ($active) {
            View::render('overrides.php', [
                'active' => $active,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageHolidays(): void
    {
        $holidays = $this->db->fetchAll('SELECT * FROM holidays ORDER BY date DESC');

        $editId = (int) ($_GET['edit'] ?? 0);
        $editHoliday = $editId > 0
            ? $this->db->fetch('SELECT * FROM holidays WHERE id = ? LIMIT 1', [$editId])
            : null;
        $showForm = $editId > 0 || isset($_GET['add']);

        $this->layout('Sviatky', function () use ($holidays, $editHoliday, $showForm) {
            View::render('holidays.php', [
                'holidays' => $holidays,
                'editHoliday' => $editHoliday,
                'showForm' => $showForm,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageWorkingHours(): void
    {
        $repo = new WorkingHoursRepository($this->db);
        $rows = array_map(static fn($w) => $w->toArray(), $repo->findWeekSchedule());

        $editId = (int) ($_GET['edit'] ?? 0);
        $editRow = $editId > 0
            ? $this->db->fetch('SELECT * FROM working_hours WHERE id = ? LIMIT 1', [$editId])
            : null;
        $showForm = $editId > 0 || isset($_GET['add']);

        $this->layout('Pracovné hodiny', function () use ($rows, $editRow, $showForm) {
            View::render('working_hours.php', [
                'rows' => $rows,
                'editRow' => $editRow,
                'showForm' => $showForm,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageOnCallRotations(): void
    {
        $rotations = $this->db->fetchAll('SELECT * FROM oncall_rotations ORDER BY is_active DESC, name');
        $groups = $this->db->fetchAll('SELECT id,name FROM rotation_groups ORDER BY name');

        $editId = (int) ($_GET['edit'] ?? 0);
        $editRotation = $editId > 0
            ? $this->db->fetch('SELECT * FROM oncall_rotations WHERE id = ? LIMIT 1', [$editId])
            : null;
        $showForm = $editId > 0 || isset($_GET['add']);

        $this->layout('On-call rotácie', function () use ($rotations, $groups, $editRotation, $showForm) {
            View::render('oncall.php', [
                'rotations' => $rotations,
                'groups' => $groups,
                'editRotation' => $editRotation,
                'showForm' => $showForm,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageGroups(): void
    {
        $groups = $this->db->fetchAll('SELECT * FROM rotation_groups ORDER BY name');

        $editId = (int) ($_GET['edit'] ?? 0);
        $editGroup = $editId > 0
            ? $this->db->fetch('SELECT * FROM rotation_groups WHERE id = ? LIMIT 1', [$editId])
            : null;
        $showForm = $editId > 0 || isset($_GET['add']);

        $this->layout('Skupiny', function () use ($groups, $editGroup, $showForm) {
            View::render('groups.php', [
                'groups' => $groups,
                'editGroup' => $editGroup,
                'showForm' => $showForm,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageConfig(): void
    {
        $path = dirname(__DIR__, 2) . '/config/config.yaml';
        $content = is_file($path) ? file_get_contents($path) : '';

        $this->layout('Konfigurácia', function () use ($path, $content) {
            View::render('config.php', [
                'path' => $path,
                'content' => $content,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    private function pageLogs(): void
    {
        $lines = (int) ($_GET['lines'] ?? 200);
        $lines = max(20, min(2000, $lines));
        $logs = $this->logger->getRecentLogs($lines);

        $this->layout('Logy', function () use ($logs, $lines) {
            View::render('logs.php', [
                'logs' => $logs,
                'lines' => $lines,
                'flash' => $this->consumeFlash(),
            ]);
        });
    }

    // ---------------- Actions ----------------

    private function actionRunCycle(): void
    {
        $result = $this->forwardingService->executeCycle();
        $this->flash($result->isSuccess() ? 'ok' : 'err', 'Cycle: ' . ($result->errorMessage ?? 'OK'));
        $this->redirect('/');
    }

    private function actionEmployeeSave(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone_internal' => trim((string) ($_POST['phone_internal'] ?? '')) ?: null,
            'phone_mobile' => trim((string) ($_POST['phone_mobile'] ?? '')) ?: null,
            'phone_primary' => trim((string) ($_POST['phone_primary'] ?? '')) ?: null,
            'priority' => (int) ($_POST['priority'] ?? 100),
            'rotation_group_id' => ($_POST['rotation_group_id'] ?? '') !== '' ? (int) $_POST['rotation_group_id'] : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_oncall' => isset($_POST['is_oncall']) ? 1 : 0,
        ];

        if ($data['name'] === '') {
            throw new \RuntimeException('Employee name is required');
        }

        if ($id === null) {
            $repo = new EmployeeRepository($this->db);
            $repo->create($data);
            $this->flash('ok', 'Employee created');
        } else {
            $repo = new EmployeeRepository($this->db);
            $repo->updateEmployee($id, $data);
            $this->flash('ok', 'Employee updated');
        }

        $this->redirect('/employees');
    }

    private function actionEmployeeDelete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Invalid employee id');
        }
        $repo = new EmployeeRepository($this->db);
        $repo->delete($id);
        $this->flash('ok', 'Employee deleted');
        $this->redirect('/employees');
    }

    private function actionRuleSave(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $days = $_POST['days_of_week'] ?? null;
        $daysJson = null;
        if (is_array($days) && $days !== []) {
            $daysJson = json_encode(array_map('intval', $days));
        }

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'rule_type' => (string) ($_POST['rule_type'] ?? 'fallback'),
            'priority' => (int) ($_POST['priority'] ?? 100),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'valid_from' => $this->normalizeDateTime($_POST['valid_from'] ?? null),
            'valid_until' => $this->normalizeDateTime($_POST['valid_until'] ?? null),
            'days_of_week' => $daysJson,
            'start_time' => $this->normalizeTime($_POST['start_time'] ?? null),
            'end_time' => $this->normalizeTime($_POST['end_time'] ?? null),
            'forward_to' => trim((string) ($_POST['forward_to'] ?? '')),
            'target_type' => (string) ($_POST['target_type'] ?? 'number'),
            'target_employee_id' => ($_POST['target_employee_id'] ?? '') !== '' ? (int) $_POST['target_employee_id'] : null,
            'target_group_id' => ($_POST['target_group_id'] ?? '') !== '' ? (int) $_POST['target_group_id'] : null,
            'holiday_id' => ($_POST['holiday_id'] ?? '') !== '' ? (int) $_POST['holiday_id'] : null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];

        if ($data['name'] === '') {
            throw new \RuntimeException('Rule name is required');
        }

        if ($data['forward_to'] === '' && $data['rule_type'] !== 'fallback') {
            throw new \RuntimeException('forward_to is required (except fallback)');
        }

        $repo = new RuleRepository($this->db);
        if ($id === null) {
            $repo->create($data);
            $this->flash('ok', 'Rule created');
        } else {
            $repo->updateRule($id, $data);
            $this->flash('ok', 'Rule updated');
        }

        $this->redirect('/rules');
    }

    private function actionRuleDelete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Invalid rule id');
        }
        $repo = new RuleRepository($this->db);
        $repo->delete($id);
        $this->flash('ok', 'Rule deleted');
        $this->redirect('/rules');
    }

    private function actionOverrideCreate(): void
    {
        $startsAt = $this->normalizeDateTime($_POST['starts_at'] ?? null);
        $endsAt = $this->normalizeDateTime($_POST['ends_at'] ?? null);
        $expiresAt = $this->normalizeDateTime($_POST['expires_at'] ?? null);

        // UX-friendly defaults:
        // - if user fills "expires_at", treat it as "until time"
        // - otherwise treat override as indefinite
        $overrideType = (string) ($_POST['override_type'] ?? '');
        if ($overrideType === '') {
            $overrideType = $expiresAt !== null ? 'until_time' : 'indefinite';
        }

        // If only expires_at was provided, map it to ends_at as well (safe for legacy queries).
        if ($endsAt === null && $expiresAt !== null) {
            $endsAt = $expiresAt;
        }

        $data = [
            'override_type' => $overrideType,
            'is_active' => 1,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'forward_to' => trim((string) ($_POST['forward_to'] ?? '')),
            'reason' => trim((string) ($_POST['reason'] ?? '')) ?: null,
            'expires_at' => $expiresAt,
        ];

        if ($data['forward_to'] === '') {
            throw new \RuntimeException('forward_to is required');
        }

        $repo = new OverrideRepository($this->db);
        $repo->createOverride($data);
        $this->flash('ok', 'Override created');
        $this->redirect('/overrides');
    }

    private function actionOverrideClearAll(): void
    {
        $repo = new OverrideRepository($this->db);
        $repo->deactivateAll();
        $this->flash('ok', 'Overrides cleared');
        $this->redirect('/overrides');
    }

    private function actionHolidaySave(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'date' => trim((string) ($_POST['date'] ?? '')),
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            'country' => trim((string) ($_POST['country'] ?? '')) ?: null,
            'region' => trim((string) ($_POST['region'] ?? '')) ?: null,
            'forward_to' => trim((string) ($_POST['forward_to'] ?? '')) ?: null,
            'is_workday' => isset($_POST['is_workday']) ? 1 : 0,
            'priority' => (int) ($_POST['priority'] ?? 50),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['name'] === '' || $data['date'] === '') {
            throw new \RuntimeException('Holiday name and date are required');
        }

        $repo = new HolidayRepository($this->db);
        if ($id === null) {
            $repo->create($data);
            $this->flash('ok', 'Holiday created');
        } else {
            $repo->updateHoliday($id, $data);
            $this->flash('ok', 'Holiday updated');
        }

        $this->redirect('/holidays');
    }

    private function actionHolidayDelete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Invalid holiday id');
        }
        $repo = new HolidayRepository($this->db);
        $repo->delete($id);
        $this->flash('ok', 'Holiday deleted');
        $this->redirect('/holidays');
    }

    private function actionHolidayImportSk(): void
    {
        $yearFrom = 2020;
        $yearTo = 2035;
        $created = $this->importSlovakHolidays($yearFrom, $yearTo);
        $this->flash('ok', sprintf('Import slovenskych sviatkov dokoncený (%d novych zaznamov)', $created));
        $this->redirect('/holidays');
    }

    private function actionWorkingHoursSave(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $day = (int) ($_POST['day_of_week'] ?? -1);
        if ($day < 0 || $day > 6) {
            throw new \RuntimeException('Invalid day_of_week');
        }

        $data = [
            'day_of_week' => $day,
            'is_working_day' => isset($_POST['is_working_day']) ? 1 : 0,
            'start_time' => $this->normalizeTime((string) ($_POST['start_time'] ?? '00:00')) ?? '00:00:00',
            'end_time' => $this->normalizeTime((string) ($_POST['end_time'] ?? '00:00')) ?? '00:00:00',
            'forward_to_internal' => trim((string) ($_POST['forward_to_internal'] ?? '')) ?: null,
            'forward_to_external' => trim((string) ($_POST['forward_to_external'] ?? '')) ?: null,
            'is_active' => 1,
        ];

        $repo = new WorkingHoursRepository($this->db);
        if ($id !== null && $id > 0) {
            $repo->updateHours($id, $data);
        } else {
            $row = $this->db->fetch('SELECT id FROM working_hours WHERE day_of_week = ? AND is_active = 1 LIMIT 1', [$day]);
            if ($row === null) {
                $repo->create($data);
            } else {
                $repo->updateHours((int) $row['id'], $data);
            }
        }

        $this->flash('ok', 'Working hours saved');
        $this->redirect('/working-hours');
    }

    private function actionOnCallSave(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'group_id' => (int) ($_POST['group_id'] ?? 0),
            'rotation_pattern' => (string) ($_POST['rotation_pattern'] ?? 'weekly'),
            'rotation_start_date' => trim((string) ($_POST['rotation_start_date'] ?? '')),
            'rotation_direction' => (string) ($_POST['rotation_direction'] ?? 'forward'),
            'is_24x7' => isset($_POST['is_24x7']) ? 1 : 0,
            'default_start_time' => $this->normalizeTime($_POST['default_start_time'] ?? null),
            'default_end_time' => $this->normalizeTime($_POST['default_end_time'] ?? null),
            'during_hours_forward_to' => trim((string) ($_POST['during_hours_forward_to'] ?? '')) ?: null,
            'after_hours_forward_to' => trim((string) ($_POST['after_hours_forward_to'] ?? '')) ?: null,
            'use_employee_mobile' => isset($_POST['use_employee_mobile']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['name'] === '' || $data['group_id'] <= 0 || $data['rotation_start_date'] === '') {
            throw new \RuntimeException('name, group and start date are required');
        }

        $repo = new OnCallRepository($this->db);
        if ($id === null) {
            $repo->create($data);
            $this->flash('ok', 'Rotation created');
        } else {
            $repo->updateRotation($id, $data);
            $this->flash('ok', 'Rotation updated');
        }

        $this->redirect('/oncall');
    }

    private function actionOnCallDelete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Invalid rotation id');
        }
        $repo = new OnCallRepository($this->db);
        $repo->delete($id);
        $this->flash('ok', 'Rotation deleted');
        $this->redirect('/oncall');
    }

    private function actionConfigSave(): void
    {
        $path = dirname(__DIR__, 2) . '/config/config.yaml';
        $content = (string) ($_POST['content'] ?? '');
        if ($content === '') {
            throw new \RuntimeException('Config content is empty');
        }
        file_put_contents($path, $content);
        $this->flash('ok', 'Config saved. Refreshing config...');
        ConfigLoader::getInstance()->reload();
        $this->redirect('/config');
    }

    private function actionLogin(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $next = (string) ($_POST['next'] ?? '/');
        if ($next === '' || !str_starts_with($next, '/')) {
            $next = '/';
        }

        $expectedUser = $this->getAdminUsername();
        $expectedPass = $this->getAdminPassword();

        $ok = hash_equals($expectedUser, $username) && hash_equals($expectedPass, $password);
        if (!$ok) {
            usleep(250_000);
            $this->flash('err', 'Nesprávne meno alebo heslo');
            $this->redirect('/login?next=' . urlencode($next));
        }

        $_SESSION['auth'] = true;
        $_SESSION['admin_user'] = $username;
        $this->redirect($next);
    }

    private function actionLogout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->redirect('/login');
    }

    private function actionGroupSave(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'rotation_type' => (string) ($_POST['rotation_type'] ?? 'weekly'),
            'current_index' => (int) ($_POST['current_index'] ?? 0),
            'rotation_start_date' => trim((string) ($_POST['rotation_start_date'] ?? '')) ?: null,
        ];

        if ($data['name'] === '') {
            throw new \RuntimeException('Group name is required');
        }

        if ($id === null) {
            $this->db->insert('rotation_groups', $data);
            $this->flash('ok', 'Group created');
        } else {
            $this->db->update('rotation_groups', $data, 'id = ?', [$id]);
            $this->flash('ok', 'Group updated');
        }

        $this->redirect('/groups');
    }

    private function actionGroupDelete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Invalid group id');
        }
        $this->db->delete('rotation_groups', 'id = ?', [$id]);
        $this->flash('ok', 'Group deleted');
        $this->redirect('/groups');
    }

    private function importSlovakHolidays(int $yearFrom, int $yearTo): int
    {
        $created = 0;
        $fixed = [
            ['name' => 'Deň vzniku Slovenskej republiky', 'month_day' => '01-01'],
            ['name' => 'Zjavenie Pána', 'month_day' => '01-06'],
            ['name' => 'Sviatok práce', 'month_day' => '05-01'],
            ['name' => 'Deň víťazstva nad fašizmom', 'month_day' => '05-08'],
            ['name' => 'Sviatok svätého Cyrila a Metoda', 'month_day' => '07-05'],
            ['name' => 'Výročie SNP', 'month_day' => '08-29'],
            ['name' => 'Deň Ústavy Slovenskej republiky', 'month_day' => '09-01'],
            ['name' => 'Sedembolestná Panna Mária', 'month_day' => '09-15'],
            ['name' => 'Sviatok všetkých svätých', 'month_day' => '11-01'],
            ['name' => 'Deň boja za slobodu a demokraciu', 'month_day' => '11-17'],
            ['name' => 'Štedrý deň', 'month_day' => '12-24'],
            ['name' => 'Prvý sviatok vianočný', 'month_day' => '12-25'],
            ['name' => 'Druhý sviatok vianočný', 'month_day' => '12-26'],
        ];

        foreach ($fixed as $holiday) {
            $date = '2000-' . $holiday['month_day'];
            $created += $this->upsertHolidayRecord([
                'name' => $holiday['name'],
                'date' => $date,
                'is_recurring' => 1,
                'country' => 'SK',
                'region' => null,
                'forward_to' => null,
                'is_workday' => 0,
                'priority' => 50,
                'is_active' => 1,
            ]);
        }

        for ($year = $yearFrom; $year <= $yearTo; $year++) {
            $easterSunday = $this->calculateEasterSunday($year);
            $goodFriday = $easterSunday->modify('-2 days');
            $easterMonday = $easterSunday->modify('+1 day');

            $created += $this->upsertHolidayRecord([
                'name' => 'Veľký piatok',
                'date' => $goodFriday->format('Y-m-d'),
                'is_recurring' => 0,
                'country' => 'SK',
                'region' => null,
                'forward_to' => null,
                'is_workday' => 0,
                'priority' => 50,
                'is_active' => 1,
            ]);

            $created += $this->upsertHolidayRecord([
                'name' => 'Veľkonočný pondelok',
                'date' => $easterMonday->format('Y-m-d'),
                'is_recurring' => 0,
                'country' => 'SK',
                'region' => null,
                'forward_to' => null,
                'is_workday' => 0,
                'priority' => 50,
                'is_active' => 1,
            ]);
        }

        return $created;
    }

    private function upsertHolidayRecord(array $data): int
    {
        $existing = $this->db->fetch(
            'SELECT id FROM holidays WHERE date = ? AND ((country = ? ) OR (country IS NULL AND ? IS NULL)) AND ((region = ?) OR (region IS NULL AND ? IS NULL)) LIMIT 1',
            [$data['date'], $data['country'], $data['country'], $data['region'], $data['region']]
        );

        $repo = new HolidayRepository($this->db);
        if ($existing !== null) {
            $repo->updateHoliday((int) $existing['id'], $data);
            return 0;
        }

        $repo->create($data);
        return 1;
    }

    private function calculateEasterSunday(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));
    }

    private function actionCalendarCreate(): void
    {
        $selectionStart = trim((string) ($_POST['selection_start'] ?? ''));
        $selectionEnd = trim((string) ($_POST['selection_end'] ?? ''));
        $ym = trim((string) ($_POST['ym'] ?? date('Y-m')));
        $actionMode = trim((string) ($_POST['action_mode'] ?? 'add'));
        $eventRefsRaw = trim((string) ($_POST['event_refs'] ?? '[]'));
        $selectionEventsRaw = trim((string) ($_POST['selection_events_data'] ?? '[]'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectionStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectionEnd)) {
            throw new \RuntimeException('Invalid calendar range');
        }

        $rangeStart = new \DateTimeImmutable($selectionStart . ' 00:00:00');
        $rangeEnd = new \DateTimeImmutable($selectionEnd . ' 00:00:00');
        if ($rangeEnd < $rangeStart) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        $eventRefs = json_decode($eventRefsRaw, true);
        $eventRefs = is_array($eventRefs) ? $eventRefs : [];
        $selectionEvents = json_decode($selectionEventsRaw, true);
        $selectionEvents = is_array($selectionEvents) ? $selectionEvents : [];

        $overrideRepo = new OverrideRepository($this->db);

        if ($actionMode === 'delete') {
            $removed = $this->deactivateCalendarEventRefs($eventRefs, $overrideRepo);
            $this->flash('ok', sprintf('Z kalendara bolo odstranene %d planov', $removed));
            $redirectYm = preg_match('/^\d{4}-\d{2}$/', $ym) ? $ym : $rangeStart->format('Y-m');
            $this->redirect('/calendar?ym=' . urlencode($redirectYm));
        }

        if ($actionMode === 'copy') {
            if ($selectionEvents === []) {
                throw new \RuntimeException('Vo vybranom rozsahu nie su ziadne udalosti na kopirovanie');
            }

            $copyStartRaw = trim((string) ($_POST['copy_target_start'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $copyStartRaw)) {
                throw new \RuntimeException('Zvol cielovy datum kopie');
            }

            $repeatPattern = trim((string) ($_POST['repeat_pattern'] ?? 'none'));
            $repeatEndMode = trim((string) ($_POST['repeat_end_mode'] ?? 'count'));
            $repeatCount = max(1, (int) ($_POST['repeat_count'] ?? '1'));
            $repeatUntilRaw = trim((string) ($_POST['repeat_until'] ?? ''));

            $created = $this->createCalendarTemplateCopies(
                $selectionEvents,
                $rangeStart,
                new \DateTimeImmutable($copyStartRaw . ' 00:00:00'),
                $repeatPattern,
                $repeatEndMode,
                $repeatCount,
                $repeatUntilRaw,
                $overrideRepo
            );

            $this->flash('ok', sprintf('Do kalendara bolo skopirovanych %d planov', $created));
            $redirectYm = preg_match('/^\d{4}-\d{2}$/', $ym) ? $ym : $rangeStart->format('Y-m');
            $this->redirect('/calendar?ym=' . urlencode($redirectYm));
        }

        $timeFrom = $this->normalizeTime($_POST['time_from'] ?? null);
        $timeTo = $this->normalizeTime($_POST['time_to'] ?? null);
        $title = trim((string) ($_POST['title'] ?? ''));
        $targetMode = trim((string) ($_POST['target_mode'] ?? 'employee'));
        $employeeId = (int) ($_POST['target_employee_id'] ?? 0);
        $targetNumber = trim((string) ($_POST['target_number'] ?? ''));
        $repeatPattern = trim((string) ($_POST['repeat_pattern'] ?? 'none'));
        $repeatEndMode = trim((string) ($_POST['repeat_end_mode'] ?? 'count'));
        $repeatCount = max(1, (int) ($_POST['repeat_count'] ?? '1'));
        $repeatUntilRaw = trim((string) ($_POST['repeat_until'] ?? ''));

        if ($timeFrom === null || $timeTo === null) {
            throw new \RuntimeException('Cas od a do je povinny');
        }

        $target = $this->resolveCalendarTarget($targetMode, $employeeId, $targetNumber);
        if ($title === '') {
            $title = $this->buildCalendarAutoTitle($target['employee_name'], $timeFrom, $timeTo);
        }

        if ($actionMode === 'update') {
            $this->deactivateCalendarEventRefs($eventRefs, $overrideRepo);
        }

        $created = $this->createCalendarRangeEvents(
            $rangeStart,
            $rangeEnd,
            $timeFrom,
            $timeTo,
            $title,
            $target,
            $repeatPattern,
            $repeatEndMode,
            $repeatCount,
            $repeatUntilRaw,
            $overrideRepo
        );

        $this->flash('ok', sprintf('Do kalendara bolo ulozenych %d planov', $created));
        $redirectYm = preg_match('/^\d{4}-\d{2}$/', $ym) ? $ym : $rangeStart->format('Y-m');
        $this->redirect('/calendar?ym=' . urlencode($redirectYm));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCalendarEmployeeOptions(): array
    {
        $employees = (new EmployeeRepository($this->db))->findActive();

        return array_values(array_map(static function ($employee): array {
            $forwardTo = trim((string) ($employee->getPhoneInternal() ?? ''));
            $source = 'interne';
            if ($forwardTo === '') {
                $forwardTo = trim((string) ($employee->getPhoneMobile() ?? ''));
                $source = 'mobil';
            }
            if ($forwardTo === '') {
                $forwardTo = trim((string) ($employee->getPhonePrimary() ?? ''));
                $source = 'primarne';
            }

            return [
                'id' => $employee->getId(),
                'name' => $employee->getName(),
                'forward_to' => $forwardTo,
                'number_source' => $source,
                'disabled' => $forwardTo === '',
                'disabled_reason' => $forwardTo === '' ? 'Zamestnanec nema vyplnene ziadne cislo na presmerovanie.' : null,
            ];
        }, $employees));
    }

    /**
     * @param array<int, array<string, mixed>> $employeeOptions
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadCalendarEvents(\DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd, array $employeeOptions): array
    {
        $employeeMap = [];
        foreach ($employeeOptions as $employee) {
            $employeeMap[(int) ($employee['id'] ?? 0)] = (string) ($employee['name'] ?? '');
        }

        $rows = $this->db->fetchAll(
            "SELECT *
             FROM override_rules
             WHERE is_active = 1
             AND COALESCE(ends_at, expires_at, '9999-12-31 23:59:59') >= ?
             AND COALESCE(starts_at, created_at, '1970-01-01 00:00:00') <= ?
             ORDER BY COALESCE(starts_at, created_at) ASC, id ASC",
            [$monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]
        );

        $eventsByDay = [];
        foreach ($rows as $row) {
            $startAtRaw = trim((string) ($row['starts_at'] ?? $row['created_at'] ?? ''));
            $endAtRaw = trim((string) ($row['ends_at'] ?? $row['expires_at'] ?? $startAtRaw));
            if ($startAtRaw === '') {
                continue;
            }

            $startAt = new \DateTimeImmutable($startAtRaw);
            $endAt = new \DateTimeImmutable($endAtRaw !== '' ? $endAtRaw : $startAtRaw);
            if ($endAt < $startAt) {
                $endAt = $startAt;
            }

            $sourceEmployeeId = isset($row['source_employee_id']) && $row['source_employee_id'] !== null
                ? (int) $row['source_employee_id']
                : null;
            $employeeName = ($sourceEmployeeId !== null && $sourceEmployeeId > 0) ? ($employeeMap[$sourceEmployeeId] ?? '') : '';
            $title = trim((string) ($row['reason'] ?? ''));
            if ($title === '') {
                $title = $employeeName !== '' ? $employeeName : trim((string) ($row['forward_to'] ?? 'Plan'));
            }

            $dayStart = $startAt->setTime(0, 0, 0);
            $dayEnd = $endAt->setTime(0, 0, 0);
            for ($cursor = $dayStart; $cursor <= $dayEnd; $cursor = $cursor->modify('+1 day')) {
                $dayKey = $cursor->format('Y-m-d');
                $eventsByDay[$dayKey][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'kind' => 'override',
                    'title' => $title,
                    'employee_name' => $employeeName,
                    'forward_to' => (string) ($row['forward_to'] ?? ''),
                    'time_from' => $startAt->format('H:i'),
                    'time_to' => $endAt->format('H:i'),
                    'target_employee_id' => ($sourceEmployeeId !== null && $sourceEmployeeId > 0) ? $sourceEmployeeId : null,
                    'start_date' => $startAt->format('Y-m-d'),
                ];
            }
        }

        return $eventsByDay;
    }

    /**
     * @return array{forward_to: string, target_employee_id: ?int, employee_name: string}
     */
    private function resolveCalendarTarget(string $targetMode, int $employeeId, string $targetNumber): array
    {
        if ($targetMode === 'number') {
            $targetNumber = trim($targetNumber);
            if ($targetNumber === '') {
                throw new \RuntimeException('Zadaj cislo pre presmerovanie');
            }

            return [
                'forward_to' => $targetNumber,
                'target_employee_id' => null,
                'employee_name' => $targetNumber,
            ];
        }

        if ($employeeId <= 0) {
            throw new \RuntimeException('Vyber osobu pre presmerovanie');
        }

        $employee = (new EmployeeRepository($this->db))->findById($employeeId);
        if ($employee === null || !$employee->isActive()) {
            throw new \RuntimeException('Vybrana osoba neexistuje alebo nie je aktivna');
        }

        $forwardTo = trim((string) ($employee->getPhoneInternal() ?? ''));
        if ($forwardTo === '') {
            $forwardTo = trim((string) ($employee->getPhoneMobile() ?? ''));
        }
        if ($forwardTo === '') {
            $forwardTo = trim((string) ($employee->getPhonePrimary() ?? ''));
        }
        if ($forwardTo === '') {
            throw new \RuntimeException('Vybrana osoba nema nastavene cislo na presmerovanie');
        }

        return [
            'forward_to' => $forwardTo,
            'target_employee_id' => $employee->getId(),
            'employee_name' => $employee->getName(),
        ];
    }

    private function buildCalendarAutoTitle(string $label, string $timeFrom, string $timeTo): string
    {
        $label = trim($label);
        $from = substr($timeFrom, 0, 5);
        $to = substr($timeTo, 0, 5);

        if ($label === '') {
            return sprintf('Presmerovanie %s - %s', $from, $to);
        }

        return sprintf('%s %s - %s', $label, $from, $to);
    }

    private function createCalendarRangeEvents(
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
        string $timeFrom,
        string $timeTo,
        string $title,
        array $target,
        string $repeatPattern,
        string $repeatEndMode,
        int $repeatCount,
        string $repeatUntilRaw,
        OverrideRepository $overrideRepo,
    ): int {
        $rangeLength = $rangeStart->diff($rangeEnd)->days ?? 0;
        $starts = $this->buildCalendarOccurrenceStarts($rangeStart, $repeatPattern, $repeatEndMode, $repeatCount, $repeatUntilRaw);
        $created = 0;

        foreach ($starts as $occurrenceStart) {
            $occurrenceEnd = $occurrenceStart->modify('+' . $rangeLength . ' days');
            for ($cursor = $occurrenceStart; $cursor <= $occurrenceEnd; $cursor = $cursor->modify('+1 day')) {
                $startsAt = $cursor->format('Y-m-d') . ' ' . $timeFrom;
                $overnight = $timeTo <= $timeFrom;
                $endDate = $overnight ? $cursor->modify('+1 day') : $cursor;
                $endsAt = $endDate->format('Y-m-d') . ' ' . $timeTo;

                $overrideRepo->createOverride([
                    'override_type' => 'until_time',
                    'is_active' => 1,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'expires_at' => $endsAt,
                    'forward_to' => $target['forward_to'],
                    'reason' => $title,
                    'source_employee_id' => $target['target_employee_id'],
                    'created_by' => null,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param array<int, array<string, mixed>> $templateEvents
     */
    private function createCalendarTemplateCopies(
        array $templateEvents,
        \DateTimeImmutable $sourceStart,
        \DateTimeImmutable $destinationStart,
        string $repeatPattern,
        string $repeatEndMode,
        int $repeatCount,
        string $repeatUntilRaw,
        OverrideRepository $overrideRepo,
    ): int {
        $occurrenceStarts = $this->buildCalendarOccurrenceStarts($destinationStart, $repeatPattern, $repeatEndMode, $repeatCount, $repeatUntilRaw);
        $created = 0;

        foreach ($occurrenceStarts as $occurrenceStart) {
            foreach ($templateEvents as $event) {
                $selectionDateRaw = (string) ($event['selection_date'] ?? '');
                $forwardTo = trim((string) ($event['forward_to'] ?? ''));
                $timeFrom = $this->normalizeTime((string) ($event['time_from'] ?? ''));
                $timeTo = $this->normalizeTime((string) ($event['time_to'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectionDateRaw) || $forwardTo === '' || $timeFrom === null || $timeTo === null) {
                    continue;
                }

                $templateDate = new \DateTimeImmutable($selectionDateRaw . ' 00:00:00');
                $offsetDays = $sourceStart->diff($templateDate)->invert === 1
                    ? -($sourceStart->diff($templateDate)->days ?? 0)
                    : ($sourceStart->diff($templateDate)->days ?? 0);
                $targetDate = $occurrenceStart->modify(($offsetDays >= 0 ? '+' : '') . $offsetDays . ' days');
                $overnight = $timeTo <= $timeFrom;
                $startsAt = $targetDate->format('Y-m-d') . ' ' . $timeFrom;
                $endDate = $overnight ? $targetDate->modify('+1 day') : $targetDate;
                $endsAt = $endDate->format('Y-m-d') . ' ' . $timeTo;

                $overrideRepo->createOverride([
                    'override_type' => 'until_time',
                    'is_active' => 1,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'expires_at' => $endsAt,
                    'forward_to' => $forwardTo,
                    'reason' => trim((string) ($event['title'] ?? '')) !== '' ? (string) $event['title'] : $forwardTo,
                    'source_employee_id' => isset($event['target_employee_id']) && (int) $event['target_employee_id'] > 0
                        ? (int) $event['target_employee_id']
                        : null,
                    'created_by' => null,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array<int, \DateTimeImmutable>
     */
    private function buildCalendarOccurrenceStarts(
        \DateTimeImmutable $start,
        string $repeatPattern,
        string $repeatEndMode,
        int $repeatCount,
        string $repeatUntilRaw,
    ): array {
        $repeatPattern = in_array($repeatPattern, ['none', 'daily', 'weekly', 'monthly'], true) ? $repeatPattern : 'none';
        if ($repeatPattern === 'none') {
            return [$start];
        }

        $intervalSpec = match ($repeatPattern) {
            'daily' => 'P1D',
            'weekly' => 'P1W',
            'monthly' => 'P1M',
            default => 'P1D',
        };

        $repeatUntil = null;
        if ($repeatEndMode === 'until') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repeatUntilRaw)) {
                throw new \RuntimeException('Zadaj datum dokedy sa ma opakovat');
            }
            $repeatUntil = new \DateTimeImmutable($repeatUntilRaw . ' 23:59:59');
        }

        $starts = [];
        $current = $start;
        for ($i = 0; $i < 120; $i++) {
            if ($repeatEndMode === 'count' && $i >= $repeatCount) {
                break;
            }
            if ($repeatUntil !== null && $current > $repeatUntil) {
                break;
            }
            $starts[] = $current;
            $current = $current->add(new \DateInterval($intervalSpec));
        }

        if ($starts === []) {
            throw new \RuntimeException('Opakovanie nevytvorilo ziadne terminy');
        }

        return $starts;
    }

    /**
     * @param array<int, mixed> $eventRefs
     */
    private function deactivateCalendarEventRefs(array $eventRefs, OverrideRepository $overrideRepo): int
    {
        $removed = 0;
        $seen = [];
        foreach ($eventRefs as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $id = (int) ($ref['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $removed += $overrideRepo->deactivate($id) ? 1 : 0;
        }

        return $removed;
    }

    // ---------------- Layout / Helpers ----------------

    private function layout(string $title, callable $content, bool $authed = true): void
    {
        View::render('layout.php', [
            'title' => $title,
            'content' => $content,
            'authed' => $authed && $this->isAuthed(),
            'adminUser' => $_SESSION['admin_user'] ?? null,
        ]);
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to, true, 303);
        exit;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }
}
