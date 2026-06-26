<section class="admin-view-panel">
    <h2>Send a message to all registered users</h2>
    <form method="post" action="admin.php?view=message" data-loading>
        <input type="hidden" name="message_action" value="broadcast">
        <label>Subject</label>
        <input type="text" name="subject" value="<?= escape($_POST['subject'] ?? 'Announcement') ?>">
        <label>Message</label>
        <textarea name="message" rows="6" placeholder="Write your announcement here..."><?= escape($_POST['message'] ?? '') ?></textarea>
        <div class="admin-action-group">
            <input type="submit" value="Send broadcast" class="button">
            <a class="button secondary" href="admin.php?view=overview">Cancel</a>
        </div>
    </form>
</section>
