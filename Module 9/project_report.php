<?php
// Module9/project_report.php
if (file_exists(__DIR__ . '/../shared/header.php')) include_once __DIR__ . '/../shared/header.php';
else { echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><div class="container my-4">'; }

include_once __DIR__ . '/project_functions.php';
global $conn;

$total = 0; $completed = 0; $overbudget = 0;
if ($conn) {
    $r = db_query("SELECT COUNT(*) AS c FROM projects");
    if ($r && $row = mysqli_fetch_assoc($r)) $total = intval($row['c']);
    $r = db_query("SELECT COUNT(*) AS c FROM projects WHERE status='Completed'");
    if ($r && $row = mysqli_fetch_assoc($r)) $completed = intval($row['c']);
    // simple overbudget check using local fields
    $r = db_query("SELECT COUNT(*) AS c FROM projects WHERE budget_actual > budget_planned");
    if ($r && $row = mysqli_fetch_assoc($r)) $overbudget = intval($row['c']);
}
?>

<div class="row">
  <div class="col-md-4"><div class="card p-3"><h5>Total Projects</h5><h2><?= $total ?></h2></div></div>
  <div class="col-md-4"><div class="card p-3"><h5>Completed</h5><h2><?= $completed ?></h2></div></div>
  <div class="col-md-4"><div class="card p-3"><h5>Over Budget</h5><h2><?= $overbudget ?></h2></div></div>
</div>

<div class="mt-4">
  <a class="btn btn-secondary" href="project_list.php">Back to Projects</a>
</div>

<?php
if (file_exists(__DIR__ . '/../shared/footer.php')) include_once __DIR__ . '/../shared/footer.php';
else echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
?>
