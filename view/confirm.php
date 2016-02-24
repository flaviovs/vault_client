<p>Here's a summary of your request:</p>
<table id="confirm-input">
  <tr>
	<th>Your e-mail:</th>
	<td><?php echo htmlspecialchars($req_email) ?></td>
  </tr>
  <tr>
	<th>User's e-mail:</th>
	<td><?php echo htmlspecialchars($user_email) ?></td>
  </tr>
  <tr>
	<th colspan="2">Additional instructions</th>
  </tr>
  <tr>
	<td colspan="2"><pre><?php echo $instructions ?></pre></td>
  </tr>
</table>

<form method="POST" action="<?php echo $action ?>">
  <input type="hidden" name="timestamp" value="<?php echo $timestamp ?>">
  <input type="hidden" name="req_email" value="<?php echo htmlspecialchars($req_email) ?>">
  <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user_email) ?>">
  <input type="hidden" name="instructions" value="<?php echo $instructions ?>">

  <p>A confirmation token was just sent to your e-mail address. Please copy and paste it below. Hit "Confirm" to confirm the request.</p>

  <div class="form-group">
	<input type="text" name="token" value="" required class="input-text" placeholder="Paste the confirmation token here..." autocomplete="off">
  </div>
  <input type="submit" value="Confirm">
</form>
