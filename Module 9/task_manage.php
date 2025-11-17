<?php
// Module9/task_manage.php
if (file_exists(__DIR__ . '/../shared/header.php')) include_once __DIR__ . '/../shared/header.php';
else { echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><div class="container my-4"><h1>Task Management</h1>'; }

include_once __DIR__ . '/project_functions.php';
global $conn;

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) { echo "<div class='alert alert-warning'>Project ID missing</div>"; exit; }

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskData = [
        'project_id' => $project_id,
        'task_name' => $_POST['task_name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'assigned_employee_id' => intval($_POST['assigned_employee_id'] ?? 0),
        'start_date' => $_POST['start_date'] ?? null,
        'end_date' => $_POST['end_date'] ?? null,
        'estimated_hours' => floatval($_POST['estimated_hours'] ?? 0)
    ];
    $res = assignTaskToEmployee($taskData);
    if (!$res['ok']) $err = $res['message'];
    else header("Location: project_details.php?id={$project_id}"); // success
}

// fetch tasks for this project
$tasks = [];
if ($conn) {
    $r = db_query("SELECT * FROM project_tasks WHERE project_id = {$project_id} ORDER BY created_at DESC");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $tasks[] = $row;
}
?>

<div class="row">
  <div class="col-md-6">
    <div class="card mb-3"><div class="card-body">
      <h5>Add / Assign Task (Project <?= $project_id ?>)</h5>
      <?php if ($err) echo "<div class='alert alert-danger'>$err</div>"; ?>
      <form method="post">
        <div class="mb-2"><label>Task Name</label><input name="task_name" class="form-control" required></div>
        <div class="mb-2"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
        <div class="mb-2"><label>Assign Employee ID</label><input name="assigned_employee_id" class="form-control" placeholder="Enter employee id (from HR)"></div>
        <div class="row"><div class="col"><label>Start</label><input name="start_date" type="date" class="form-control"></div>
        <div class="col"><label>End</label><input name="end_date" type="date" class="form-control"></div></div>
        <div class="mb-2"><label>Estimated Hours</label><input name="estimated_hours" type="number" step="0.25" class="form-control"></div>
        <button class="btn btn-success">Assign Task</button>
      </form>
    </div></div>
  </div>

  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h5>Tasks for Project <?= $project_id ?></h5>
      <table class="table table-sm">
        <thead><tr><th>ID</th><th>Name</th><th>Assigned</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($tasks)): ?>
          <tr><td colspan="4" class="text-center">No tasks</td></tr>
        <?php else: foreach($tasks as $t): ?>
          <tr>
            <td><?= $t['task_id'] ?></td>
            <td><?= htmlspecialchars($t['task_name']) ?></td>
            <td><?= htmlspecialchars($t['assigned_employee_id']) ?></td>
            <td><?= htmlspecialchars($t['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<?php if (file_exists(__DIR__ . '/../shared/footer.php')) include_once __DIR__ . '/../shared/footer.php';
else echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>'; ?>
