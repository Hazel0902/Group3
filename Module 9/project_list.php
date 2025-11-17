//<?php
// Module9/project_list.php
if (file_exists(_DIR_ . '/../shared/header.php')) include_once _DIR_ . '/../shared/header.php';
else { echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><div class="container my-4">'; }

include_once _DIR_ . '/project_functions.php';
global $conn;

$r = db_query("SELECT * FROM projects ORDER BY created_at DESC");
?>

<div class="container my-4">
  <h3>Projects</h3>
  <a href="project_form.php" class="btn btn-primary mb-3">Add New Project</a>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>Code</th>
        <th>Name</th>
        <th>Status</th>
        <th>Start</th>
        <th>End</th>
        <th>% Complete</th>
        <th>Budget (Planned / Actual)</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while($row = mysqli_fetch_assoc($r)) { ?>
        <tr>
          <td><?= htmlspecialchars($row['project_code']) ?></td>
          <td><?= htmlspecialchars($row['project_name']) ?></td>
          <td><?= htmlspecialchars($row['status']) ?></td>
          <td><?= htmlspecialchars($row['start_date']) ?></td>
          <td><?= htmlspecialchars($row['end_date']) ?></td>
          <td><?= intval($row['percent_complete']) ?>%</td>
          <td><?= number_format($row['budget_planned'],2) ?> / <?= number_format($row['budget_actual'],2) ?></td>
          <td>
            <a href="project_form.php?project_id=<?= $row['project_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
            <a href="task_list.php?project_id=<?= $row['project_id'] ?>" class="btn btn-sm btn-info">Tasks</a>
            <a href="project_report.php?project_id=<?= $row['project_id'] ?>" class="btn btn-sm btn-secondary">Report</a>
          </td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</div>

<?php
if (file_exists(_DIR_ . '/../shared/footer.php')) include_once _DIR_ . '/../shared/footer.php';
else echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
?>
