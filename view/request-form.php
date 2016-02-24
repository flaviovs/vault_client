<p>Use this form to securely receive information from an user:</p>

<ul>
  <li>The user will receive an e-mail with an URL, where he or she may input the information requested.</li>
  <li>You will receive a notification when the user submit the information. You will also receive an URL and a key, that can be used to access and unlock the information provided.</li>
</ul>

<form method="POST" action="/">
  <div id="element-req-email" class="form-group">
	<label for="req-email">Your e-mail address</label>
	<input type="email" name="disabled-req-email" value="<?php echo htmlspecialchars($req_email) ?>" disabled class="input-text">
  </div>

  <div id="element-user-email" class="form-group<?php echo empty($user_email_error) ? '' : ' error' ?>">
	<label for="user-email">User's e-mail address</label>
	<input type="email" name="user-email" value="<?php echo htmlspecialchars($user_email) ?>" required class="input-text">
	<?php if ( ! empty($user_email_error) ): ?>
	<div class="error"><?php echo $user_email_error ?></div>
	<?php endif ?>
  </div>

  <div class="form-group">
	<label for="instructions">Additional instructions</label>
	<textarea name="instructions" rows="5"><?php echo htmlspecialchars($instructions) ?></textarea>
	<div class="help">You can use basic HTML like &lt;b&gt;, &lt;i&gt;, and HTML lists.</div>

  </div>
  <input type="submit" value="Request">
</form>
