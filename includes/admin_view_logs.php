<section class="admin-view-panel">
    <h2>System logs</h2>
    <p>Choose a file, view recent lines, paginate, or download.</p>
    <div class="log-controls">
        <form method="get" action="admin.php" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="view" value="logs">
            <label style="margin-right:.25rem;">File</label>
            <select name="file" onchange="this.form.submit()">
                <?php foreach ($logCandidates as $i => $p): ?>
                    <?php if (file_exists($p) && is_readable($p)): ?>
                        <option value="<?= $i ?>" <?= ((isset($_GET['file']) && (int)$_GET['file'] === $i) || (!isset($_GET['file']) && $i === 0)) ? 'selected' : '' ?>><?= escape($p) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <a class="button secondary" href="admin.php?view=logs&amp;file=<?= isset($_GET['file']) ? (int)$_GET['file'] : 0 ?>&amp;download=1">Download</a>
        </form>
    </div>
    <div class="log-viewer">
        <?php
        $selected = isset($_GET['file']) ? (int)$_GET['file'] : null;
        if ($selected === null) {
            foreach ($logCandidates as $i => $p) {
                if (file_exists($p) && is_readable($p)) {
                    $selected = $i;
                    break;
                }
            }
        }
        if ($selected === null || !isset($logCandidates[$selected]) || !file_exists($logCandidates[$selected]) || !is_readable($logCandidates[$selected])) {
            echo '<p>No readable log files found in common locations. Create <code>app.log</code> in the project root or check server logs.</p>';
        } else {
            $path = $logCandidates[$selected];
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                echo '<pre>Unable to read file.</pre>';
            } else {
                $linesPerPage = 400;
                $total = count($lines);
                $page = max(1, (int)($_GET['page'] ?? 1));
                $maxPage = max(1, (int)ceil($total / $linesPerPage));
                $start = max(0, $total - $page * $linesPerPage);
                $length = min($linesPerPage, $total - $start);
                $slice = array_slice($lines, $start, $length);
                echo '<h3>' . escape($path) . '</h3>';
                echo '<pre>' . escape(implode("\n", $slice)) . '</pre>';
                echo '<div class="log-pager admin-action-group">';
                if ($page > 1) {
                    echo '<a class="button secondary small" href="admin.php?view=logs&amp;file=' . $selected . '&amp;page=' . ($page - 1) . '">Previous</a> ';
                }
                if ($page < $maxPage) {
                    echo '<a class="button secondary small" href="admin.php?view=logs&amp;file=' . $selected . '&amp;page=' . ($page + 1) . '">Next</a> ';
                }
                echo '<span style="margin-left:0.75rem;">Page ' . $page . ' of ' . $maxPage . '</span>';
                echo '</div>';
            }
        }
        ?>
    </div>
</section>
