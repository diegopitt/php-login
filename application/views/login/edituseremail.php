<div class="container">
    <h1>Change your email adress</h1>
    <?php $this->renderFeedbackMessages(); ?>

    <form action="<?php echo URL; ?>login/edituseremail_action" method="post">
        <label>New email adress:</label>
        <input type="text" name="user_email" required />
        <input type="submit" value="Submit" />
    </form>
</div>
