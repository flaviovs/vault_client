<form method="POST" action="/">

  <div class="form-group<?php echo empty($req_email_error) ? '' : ' error' ?>">
	<label for="req-email">Your e-mail address*</label>
	<input type="email" name="req-email" value="<?php echo htmlspecialchars($req_email) ?>" required>
	<?php if ( ! empty($req_email_error) ): ?>
	<div class="error"><?php echo $req_email_error ?></div>
	<?php endif ?>
  </div>

  <div class="form-group<?php echo empty($user_email_error) ? '' : ' error' ?>">
	<label for="user-email">User e-mail address*</label>
	<input type="email" name="user-email" value="<?php echo htmlspecialchars($user_email) ?>" required>
	<?php if ( ! empty($user_email_error) ): ?>
	<div class="error"><?php echo $user_email_error ?></div>
	<?php endif ?>
  </div>

  <div class="form-group">
	<label for="instructions">Additional instructions</label>
	<textarea name="instructions"><?php echo htmlspecialchars($instructions) ?></textarea>
  </div>
  <input type="submit" value="Request">
</form>
