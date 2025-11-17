<?php
// Module9/project_edit.php
if (file_exists(__DIR__ . '/../shared/header.php')) include_once __DIR__ . '/../shared/header.php';
else { echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><div class="container my-4"><h1>Edit Project</h1>'; }

include_once __DIR__ . '/project_functions.php';
global $conn;

$id = intval($_GET['id'] ?? 0);
$project = null;
if ($conn && $id) {
    $res = db_query("SELECT * FROM projects WHERE project_id = {$id} LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) $project = $row;
}
if (!$project) {
    echo "<div class='alert alert-warning'>Project not found.</div>";
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // simple update flow (you can expand)
    $name = $_POST['project_name'] ?? $project['project_name'];
    $desc = $_POST['description'] ?? $project['description'];
    $start = $_POST['start_date'] ?? $project['start_date'];
    $end = $_POST['end_date'] ?? $project['end_date'];
    $status = $_POST['status'] ?? $project['status'];
    $budget_planned = floatval($_POST['budget_planned'] ?? $project['budget_planned']);
    $budget_actual = floatval($_POST['budget_actual'] ?? $project['budget_actual']);

    $stmt = $conn->prepare("UPDATE projects SET project_name=?, description=?, start_date=?, end_date=?, status=?, budget_planned=?, budget_actual=? WHERE project_id=?");
    $stmt->bind_param("sssssddi", $name, $desc, $start, $end, $status, $budget_planned, $budget_actual, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) header("Location: project_details.php?id={$id}");
    else $errors[] = "Failed to update project.";
}
?>

<div class="card">
  <div class="card-body">
    <?php if ($errors) foreach($errors as $e) echo "<div class='alert alert-danger'>$e</div>"; ?>
    <form method="post">
      <div class="mb-3"><label class="form-label">Project Name</label>
        <input name="project_name" value="<?= htmlspecialchars($project['project_name']) ?>" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($project['description']) ?></textarea></div>
      <div class="row">
        <div class="col"><label>Start Date</label><input name="start_date" type="date" value="<?= $project['start_date'] ?>" class="form-control"></div>
        <div class="col"><label>End Date</label><input name="end_date" type="date" value="<?= $project['end_date'] ?>" class="form-control"></div>
      </div>
      <div class="mb-3 mt-3"><label>Status</label>
        <select name="status" class="form-select">
            <?php foreach(['Planning','Active','On Hold','Completed','Cancelled'] as $s): ?>
              <option <?= $project['status']==$s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3"><label>Budget Planned</label><input name="budget_planned" type="number" step="0.01" value="<?= $project['budget_planned'] ?>" class="form-control"></div>
      <div class="mb-3"><label>Budget Actual</label><input name="budget_actual" type="number" step="0.01" value="<?= $project['budget_actual'] ?>" class="form-control"></div>
      <button class="btn btn-primary">Save</button>
      <a href="project_list.php" class="btn btn-secondary">Back</a>
    </form>
  </div>
</div>

<?php if (file_exists(__DIR__ . '/../shared/footer.php')) include_once __DIR__ . '/../shared/footer.php';
else echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>'; ?>
