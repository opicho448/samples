<section class="admin-view-panel">
    <div class="admin-widget-header">
        <div>
            <h2>Approvals</h2>
            <p>Verify payment proof before issuing ticket numbers</p>
        </div>
    </div>

    <?php if (empty($pendingApprovals)): ?>
        <div class="admin-status-banner ok">
            <div class="admin-status-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M9 12.5 11 14.5 15.5 9.5M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div>
                <strong>All systems normal</strong>
                <span>No registrations awaiting approval.</span>
            </div>
        </div>
    <?php else: ?>
        <?= adminPendingTable($pendingApprovals, 'approvals', false) ?>
    <?php endif; ?>
</section>
