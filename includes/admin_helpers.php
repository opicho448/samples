<?php
function adminStatusPill($status) {
    $normalized = strtolower((string)$status);
    $class = 'pending';
    if ($normalized === 'confirmed' || $normalized === 'paid' || $normalized === 'free') {
        $class = 'confirmed';
    } elseif ($normalized === 'rejected') {
        $class = 'rejected';
    }
    return '<span class="status-pill ' . $class . '">' . escape($status) . '</span>';
}

function adminApprovalActions($pending, $redirectView = 'overview', $compact = false) {
    ob_start();
    ?>
    <div class="admin-action-group">
        <form method="post" action="admin.php?view=<?= escape($redirectView) ?>" data-loading>
            <input type="hidden" name="attendee_id" value="<?= (int)$pending['id'] ?>">
            <input type="hidden" name="approval_action" value="approve">
            <input type="hidden" name="redirect_view" value="<?= escape($redirectView) ?>">
            <button type="submit" class="button small<?= $compact ? '' : '' ?>" onclick="return confirm('Approve this registration and send the ticket number?');">Approve</button>
        </form>
        <form method="post" action="admin.php?view=<?= escape($redirectView) ?>" data-loading>
            <input type="hidden" name="attendee_id" value="<?= (int)$pending['id'] ?>">
            <input type="hidden" name="approval_action" value="reject">
            <input type="hidden" name="redirect_view" value="<?= escape($redirectView) ?>">
            <button type="submit" class="button small btn-reject" onclick="return confirm('Reject this registration?');">Reject</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function adminPendingTable($items, $redirectView = 'overview', $compact = false) {
    if (empty($items)) {
        return '';
    }
    ob_start();
    ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Event</th>
                    <?php if (!$compact): ?><th>Email</th><th>Phone</th><?php endif; ?>
                    <th>Amount</th>
                    <th>M-Pesa ID</th>
                    <?php if (!$compact): ?><th>Proof</th><?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $pending): ?>
                    <tr>
                        <td><?= escape($pending['name']) ?></td>
                        <td><?= escape($pending['event_title']) ?></td>
                        <?php if (!$compact): ?>
                            <td><?= escape($pending['email']) ?></td>
                            <td><?= escape($pending['phone']) ?></td>
                        <?php endif; ?>
                        <td><?= (float)$pending['amount_paid'] > 0 ? '$' . number_format((float)$pending['amount_paid'], 2) : 'Free' ?></td>
                        <td><?= escape($pending['mpesa_transaction_id'] ?: '—') ?></td>
                        <?php if (!$compact): ?>
                            <td>
                                <?php if (!empty($pending['payment_proof_path'])): ?>
                                    <a href="<?= escape($pending['payment_proof_path']) ?>" target="_blank" rel="noopener">View</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td><?= adminApprovalActions($pending, $redirectView, $compact) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
