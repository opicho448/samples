<section class="admin-metrics">
    <article class="admin-metric-card">
        <div class="admin-metric-label">Total Registrations</div>
        <div class="admin-metric-value"><?= number_format($totalRegistrations) ?></div>
        <div class="admin-metric-trend <?= $registrationTrendPct > 0 ? 'up' : ($registrationTrendPct < 0 ? 'down' : 'neutral') ?>">
            <?php if ($registrationTrendPct > 0): ?>
                ↑ <?= abs($registrationTrendPct) ?>% vs prior 7 days
            <?php elseif ($registrationTrendPct < 0): ?>
                ↓ <?= abs($registrationTrendPct) ?>% vs prior 7 days
            <?php else: ?>
                No change vs prior 7 days
            <?php endif; ?>
        </div>
    </article>
    <article class="admin-metric-card<?= $pendingCount === 0 ? ' pending-clear' : '' ?>">
        <div class="admin-metric-label">Pending Approvals</div>
        <?php if ($pendingCount === 0): ?>
            <div class="admin-metric-value">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M9 12.5 11 14.5 15.5 9.5M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                All Clear
            </div>
        <?php else: ?>
            <div class="admin-metric-value"><?= number_format($pendingCount) ?></div>
            <div class="admin-metric-trend down">Requires review</div>
        <?php endif; ?>
    </article>
    <article class="admin-metric-card">
        <div class="admin-metric-label">Total Revenue</div>
        <div class="admin-metric-value">$<?= number_format($totalRevenue, 2) ?></div>
        <div class="admin-metric-trend neutral">Confirmed payments only</div>
    </article>
    <article class="admin-metric-card">
        <div class="admin-metric-label">Active Events</div>
        <div class="admin-metric-value"><?= number_format($activeEvents) ?></div>
        <div class="admin-metric-trend neutral">Upcoming on calendar</div>
    </article>
</section>

<?php if ($pendingCount === 0): ?>
    <div class="admin-status-banner ok">
        <div class="admin-status-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M9 12.5 11 14.5 15.5 9.5M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
            <strong>All systems normal</strong>
            <span>No registrations are waiting for payment verification.</span>
        </div>
    </div>
<?php else: ?>
    <div class="admin-status-banner alert">
        <div class="admin-status-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.3 4.5 2.6 17.5A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.8-2.5L13.7 4.5a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
            <strong>Priority alert: <?= (int)$pendingCount ?> registration<?= $pendingCount === 1 ? '' : 's' ?> pending</strong>
            <span>Payment proof needs your review before ticket numbers can be issued.</span>
        </div>
        <a class="button" href="#" data-open-approvals-modal>Review Now</a>
    </div>
<?php endif; ?>

<div class="admin-grid-2">
    <article class="admin-widget">
        <div class="admin-widget-header">
            <div>
                <h2>Registration Trends</h2>
                <p>Last 30 days</p>
            </div>
        </div>
        <div class="admin-chart-wrap">
            <canvas id="registrationTrendChart" aria-label="Registration trends chart"></canvas>
        </div>
    </article>

    <article class="admin-widget">
        <div class="admin-widget-header">
            <div>
                <h2>Pending Approvals</h2>
                <p>Latest <?= min(5, $pendingCount) ?> of <?= (int)$pendingCount ?></p>
            </div>
            <?php if ($pendingCount > 0): ?>
                <a class="button secondary small" href="admin.php?view=approvals">View all</a>
            <?php endif; ?>
        </div>
        <?php if ($pendingCount === 0): ?>
            <p style="color:#64748b;margin:0;">You're caught up. New registrations will appear here for review.</p>
        <?php else: ?>
            <?= adminPendingTable($pendingPreview, 'overview', true) ?>
        <?php endif; ?>
    </article>
</div>

<script>
window.adminChartData = <?= json_encode($registrationChart, JSON_UNESCAPED_UNICODE) ?>;
</script>
