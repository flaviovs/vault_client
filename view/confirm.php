<p>Here's a summary of your request:</p>
<table>
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
	<td colspan="2"><pre><?php echo htmlspecialchars($instructions) ?></pre></td>
  </tr>
</table>

<form method="POST" action="<?php echo $action ?>">
  <input type="hidden" name="timestamp" value="<?php echo $timestamp ?>">
  <input type="hidden" name="req_email" value="<?php echo htmlspecialchars($req_email) ?>">
  <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user_email) ?>">
  <input type="hidden" name="instructions" value="<?php echo htmlspecialchars($instructions) ?>">

  <p>Please enter the confirmation token that was sent to your email and hit "Confirm" to confirm the request.</p>

  <div class="form-group">
	<input type="text" name="token" value="" required>
  </div>
  <input type="submit" value="Confirm">
</form>
