<?php
// Module9/project_list.php
// List all projects and quick actions

// include shared header if exists
if (file_exists(__DIR__ . '/../shared/header.php')) {
    include_once __DIR__ . '/../shared/header.php';
} else {
    // Simple fallback header
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<title>Project Management</title></head><body><div class="container my-4">';
    echo '<h1 class="mb-3">Project Management</h1>';
}

include_once __DIR__ . '/project_functions.php';
global $conn;

// Fetch projects safely
$projects = [];
if ($conn) {
    $res = db_query("SELECT * FROM projects ORDER BY created_at DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $projects[] = $r;
    }
}
?>

<div class="mb-3">
    <a class="btn btn-primary" href="project_add.php">+ Add Project</a>
    <a class="btn btn-outline-secondary" href="project_report.php">View Report</a>
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>#</th><th>Project</th><th>Dates</th><th>Status</th><th>Budget (used/planned)</th><th>Progress</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($projects)): ?>
        <tr><td colspan="7" class="text-center">No projects yet.</td></tr>
    <?php else: foreach($projects as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['project_id']) ?></td>
            <td><?= htmlspecialchars($p['project_name']) ?></td>
            <td><?= htmlspecialchars($p['start_date']) ?> → <?= htmlspecialchars($p['end_date']) ?></td>
            <td><?= htmlspecialchars($p['status']) ?></td>
            <td>₱<?= number_format($p['budget_actual'] ?? 0,2) ?> / ₱<?= number_format($p['budget_planned'] ?? ($p['budget_limit'] ?? 0),2) ?></td>
            <td><?= intval($p['percent_complete']) ?>%</td>
            <td>
                <a class="btn btn-sm btn-info" href="project_details.php?id=<?= $p['project_id'] ?>">Details</a>
                <a class="btn btn-sm btn-warning" href="project_edit.php?id=<?= $p['project_id'] ?>">Edit</a>
                <a class="btn btn-sm btn-secondary" href="task_manage.php?project_id=<?= $p['project_id'] ?>">Tasks</a>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php
// include footer if exists
if (file_exists(__DIR__ . '/../shared/footer.php')) {
    include_once __DIR__ . '/../shared/footer.php';
} else {
    echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
?>
