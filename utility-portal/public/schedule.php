<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();

// Handle POST request to add a new task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    $title = $_POST['title'] ?? '';
    $task_date = $_POST['task_date'] ?? '';
    $task_time = $_POST['task_time'] ?? '';
    $assignee = $_POST['assignee'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';

    if (!empty($title) && !empty($task_date)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO scheduler_tasks (title, task_date, task_time, assignee, priority) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $task_date, $task_time, $assignee, $priority]);
            header("Location: schedule.php?start_date=" . urlencode($task_date) . "&success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding task: " . $e->getMessage();
        }
    } else {
        $error = "Title and Date are required.";
    }
}

$pageTitle = 'Scheduling';
require_once dirname(__DIR__) . '/src/header.php';

// Determine start date of the week (Monday)
$selectedDate = isset($_GET['start_date']) ? $_GET['start_date'] : '2026-01-13';
try {
    $datetime = new DateTime($selectedDate);
} catch (Exception $e) {
    $datetime = new DateTime('2026-01-13');
}
// Find Monday of this week
$dayOfWeek = $datetime->format('N');
if ($dayOfWeek != 1) {
    $datetime->modify('last Monday');
}
$startDate = $datetime->format('Y-m-d');

// Calculate end date (Sunday)
$endDatetime = clone $datetime;
$endDatetime->modify('+6 days');
$endDate = $endDatetime->format('Y-m-d');

$startLabel = $datetime->format('F d');
$endLabel = $endDatetime->format('F d, Y');

$prevWeekStart = clone $datetime;
$prevWeekStart->modify('-7 days');
$prevWeekParam = $prevWeekStart->format('Y-m-d');

$nextWeekStart = clone $datetime;
$nextWeekStart->modify('+7 days');
$nextWeekParam = $nextWeekStart->format('Y-m-d');


try {
    $stmt = $pdo->prepare("SELECT b.*, s.store_name, s.store_code, s.location_type, uc.utility_type, uc.provider_name, uc.account_number 
                           FROM bills b
                           JOIN stores s ON b.store_id = s.id
                           JOIN utility_connections uc ON b.connection_id = uc.id
                           WHERE b.due_date BETWEEN ? AND ?
                           ORDER BY b.due_date ASC");
    $stmt->execute([$startDate, $endDate]);
    $dbBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbBills = [];
}

// Group dbBills by due_date
$billsByDate = [];
foreach ($dbBills as $bill) {
    $dateKey = $bill['due_date'];
    if (!isset($billsByDate[$dateKey])) {
        $billsByDate[$dateKey] = [];
    }
    $billsByDate[$dateKey][] = $bill;
}

$schedule = [];
$currentDay = clone $datetime;
for ($i = 0; $i < 7; $i++) {
    $dateStr = $currentDay->format('Y-m-d');
    $schedule[$dateStr] = [
        'day_name' => $currentDay->format('D'),
        'day_num' => (int)$currentDay->format('d'),
        'events' => []
    ];
    $currentDay->modify('+1 day');
}

// Fetch database scheduler tasks for the week
try {
    $stmt = $pdo->prepare("SELECT * FROM scheduler_tasks WHERE task_date BETWEEN ? AND ? ORDER BY task_date ASC, task_time ASC");
    $stmt->execute([$startDate, $endDate]);
    $dbTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbTasks = [];
}

// Group dbTasks by date and add to schedule
foreach ($dbTasks as $task) {
    $dateKey = $task['task_date'];
    if (isset($schedule[$dateKey])) {
        $schedule[$dateKey]['events'][] = [
            'title' => $task['title'],
            'time' => $task['task_time'],
            'assignee' => $task['assignee'],
            'priority' => $task['priority']
        ];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-primary-dark">Scheduling</h3>
        <p class="text-muted mb-0">Overview of utility bill payment dues on real-time .</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="schedule.php?start_date=<?php echo $prevWeekParam; ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-chevron-left"></i> Prev Week
        </a>
        <form method="GET" action="schedule.php" class="d-flex align-items-center gap-2 m-0 bg-primary-light border border-primary-subtle rounded px-2 py-1.5">
            <i class="bi bi-calendar3 text-primary"></i>
            <input type="date" name="start_date" class="form-control form-control-sm border-0 p-0 text-primary fw-semibold bg-transparent" style="cursor: pointer; width: 120px; font-size: 0.85rem; outline: none; box-shadow: none;" value="<?php echo $startDate; ?>" onchange="this.form.submit()">
            <span class="text-primary small fw-semibold" style="border-left: 1px solid rgba(30,90,168,0.2); padding-left: 8px;">
                Week: <?php echo $startLabel . ' - ' . $endLabel; ?>
            </span>
        </form>
        <a href="schedule.php?start_date=<?php echo $nextWeekParam; ?>" class="btn btn-outline-primary btn-sm">
            Next Week <i class="bi bi-chevron-right"></i>
        </a>
        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1 ms-2" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <i class="bi bi-plus-circle"></i> Add Task/Reminder
        </button>
    </div>
</div>

<!-- Calendar Filters -->
<div class="card-command mb-4 py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="small fw-semibold text-secondary"><i class="bi bi-funnel-fill text-primary"></i> Filter Priority:</span>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active btn-filter-priority" data-priority="all">All</button>
                <button type="button" class="btn btn-outline-primary btn-filter-priority" data-priority="high">High</button>
                <button type="button" class="btn btn-outline-primary btn-filter-priority" data-priority="medium">Medium</button>
                <button type="button" class="btn btn-outline-primary btn-filter-priority" data-priority="low">Low</button>
            </div>
        </div>
        
        <div class="d-flex gap-4 align-items-center">
            <span class="small text-muted"><span class="badge bg-danger-light text-danger border border-danger-subtle me-1"><i class="bi bi-wallet2"></i></span> Payment Due Date</span>
            <span class="small text-muted"><span class="badge bg-primary me-1">&nbsp;</span> Task Events</span>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="row row-cols-1 row-cols-md-4 row-cols-lg-7 g-3 mb-4">
    <?php foreach ($schedule as $dateStr => $day): ?>
        <?php 
        $hasBills = isset($billsByDate[$dateStr]);
        $dayBills = $hasBills ? $billsByDate[$dateStr] : [];
        ?>
        <div class="col">
            <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden bg-white">
                <!-- Day Header -->
                <div class="text-center py-2 border-bottom bg-primary-dark text-white">
                    <div class="small text-uppercase fw-bold" style="font-size:0.75rem; opacity:0.8;"><?php echo $day['day_name']; ?></div>
                    <div class="fs-4 fw-bold" style="font-family:'Outfit';"><?php echo $day['day_num']; ?></div>
                </div>
                
                <!-- Day Body -->
                <div class="p-2 d-flex flex-column gap-2" style="min-height: 280px; background: #FAFBFD;">
                    
                    <!-- Dynamic Payment Due Warnings -->
                    <?php if ($hasBills): ?>
                        <?php foreach ($dayBills as $bill): ?>
                            <div class="card border-0 border-start border-4 border-danger p-2 shadow-xs rounded-2 mb-1" style="background: #FFF5F5;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="badge bg-danger text-white rounded-pill px-2" style="font-size: 0.62rem; font-weight:700;">
                                        PAYMENT DUE
                                    </span>
                                </div>
                                <div class="fw-bold mt-1 text-dark" style="font-size: 0.78rem;">
                                    <?php echo e($bill['store_code']); ?> &mdash; <?php echo e($bill['utility_type']); ?>
                                </div>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    Amt: <strong>$<?php echo number_format($bill['amount'], 2); ?></strong><br>
                                    Vendor: <?php echo e($bill['provider_name']); ?>
                                </div>
                                <div class="mt-1">
                                    <a href="bills_list.php?bill_id=<?php echo $bill['id']; ?>" class="small text-danger fw-semibold d-inline-flex align-items-center gap-1" style="font-size:0.68rem; text-decoration:none;">
                                        <i class="bi bi-wallet2"></i> Pay Ledger
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Task Events -->
                    <?php foreach ($day['events'] as $event): ?>
                        <?php 
                        $priorityColor = '';
                        if ($event['priority'] === 'high') {
                            $priorityColor = 'border-primary';
                        } elseif ($event['priority'] === 'medium') {
                            $priorityColor = 'border-warning';
                        } else {
                            $priorityColor = 'border-secondary-subtle';
                        }
                        ?>
                        <div class="card border-0 border-start border-3 <?php echo $priorityColor; ?> p-2 bg-white shadow-xs rounded-2 event-card" data-priority="<?php echo $event['priority']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge <?php echo $event['priority'] === 'high' ? 'bg-primary-light text-primary' : ($event['priority'] === 'medium' ? 'bg-warning-light text-warning-dark' : 'bg-light text-secondary'); ?> rounded-pill px-1.5 py-0.5" style="font-size: 0.6rem; font-weight:700;">
                                    <?php echo strtoupper($event['priority']); ?>
                                </span>
                                <span class="text-muted" style="font-size: 0.65rem; font-weight: 500;">
                                    <?php echo $event['time']; ?>
                                </span>
                            </div>
                            <div class="fw-semibold mt-1 text-dark" style="font-size: 0.75rem; line-height:1.2;">
                                <?php echo e($event['title']); ?>
                            </div>
                            <div class="text-muted mt-0.5" style="font-size: 0.7rem;">
                                <i class="bi bi-person-fill" style="font-size:0.68rem;"></i> <?php echo e($event['assignee']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="schedule.php">
                <input type="hidden" name="action" value="add_task">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Add Job Task / Reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="task_title" class="form-label">Task Title / Job Name</label>
                        <input type="text" class="form-control" id="task_title" name="title" required placeholder="e.g. Roof Installation - Phase 2">
                    </div>
                    <div class="mb-3">
                        <label for="task_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="task_date" name="task_date" required value="<?php echo $startDate; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="task_time" class="form-label">Time</label>
                        <input type="text" class="form-control" id="task_time" name="task_time" placeholder="e.g. 9:00 AM" required>
                    </div>
                    <div class="mb-3">
                        <label for="assignee" class="form-label">Assignee</label>
                        <input type="text" class="form-control" id="assignee" name="assignee" placeholder="e.g. Crew A (4) or Mike Johnson" required>
                    </div>
                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Priority filter handler
    $('.btn-filter-priority').click(function() {
        $('.btn-filter-priority').removeClass('active');
        $(this).addClass('active');
        
        const selectedPriority = $(this).data('priority');
        
        if (selectedPriority === 'all') {
            $('.event-card').fadeIn(150);
        } else {
            $('.event-card').each(function() {
                if ($(this).data('priority') === selectedPriority) {
                    $(this).fadeIn(150);
                } else {
                    $(this).fadeOut(150);
                }
            });
        }
    });

    <?php if (isset($_GET['success'])): ?>
    Swal.fire({
        title: 'Success!',
        text: 'Job task/reminder added to the schedule successfully.',
        icon: 'success',
        confirmButtonColor: '#164888'
    });
    // clean up query param
    window.history.replaceState({}, document.title, window.location.pathname);
    <?php endif; ?>
});
</script>

<?php
require_once dirname(__DIR__) . '/src/footer.php';
?>
