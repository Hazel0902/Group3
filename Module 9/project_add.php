<?php
// Module9/project_add.php
if (file_exists(__DIR__ . '/../shared/header.php')) include_once __DIR__ . '/../shared/header.php';
else { echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><div class="container my-4"><h1>Add Project</h1>'; }

include_once __DIR__ . '/project_functions.php';
global $conn;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'project_code' => $_POST['project_code'] ?? null,
        'project_name' => $_POST['project_name'] ?? null,
        'description'  => $_POST['description'] ?? null,
        'start_date'   => $_POST['start_date'] ?? null,
        'end_date'     => $_POST['end_date'] ?? null,
        'budget_planned' => floatval($_POST['budget_planned'] ?? 0),
        'created_by'   => intval($_POST['created_by'] ?? 0)
    ];
    $pid = createProject($data);
    if ($pid) {
        header("Location: project_details.php?id={$pid}");
        exit;
    } else {
        $errors[] = "Failed to create project. Check DB connection.";
    }
}
?>

<div class="card">
  <div class="card-body">
    <?php if ($errors) foreach($errors as $e) echo "<div class='alert alert-danger'>$e</div>"; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Project Code</label>
        <input name="project_code" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Project Name</label>
        <input name="project_name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control"></textarea>
      </div>
      <div class="row">
        <div class="col"><label>Start Date</label><input name="start_date" type="date" class="form-control"></div>
        <div class="col"><label>End Date</label><input name="end_date" type="date" class="form-control"></div>
      </div>
      <div class="mb-3 mt-3">
        <label class="form-label">Planned Budget (PHP)</label>
        <input name="budget_planned" type="number" step="0.01" class="form-control">
      </div>
      <button class="btn btn-primary">Save Project</button>
      <a href="project_list.php" class="btn btn-secondary">Back</a>
    </form>
  </div>
</div>

<?php if (file_exists(__DIR__ . '/../shared/footer.php')) include_once __DIR__ . '/../shared/footer.php';
else echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>'; ?>
