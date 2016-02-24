<!DOCTYPE html>
<html>
  <head>
	<title><?php echo $title ?></title>
	<link rel="stylesheet" href="/css/main.css?1">
  </head>
  <body>
	<main>
<?php if ( $user ): ?>
	  <header>
		<div id="welcome">Howdy, <b><?php echo htmlspecialchars($user->name) ?></b>!</div>
		<nav>
		  <a href="/logout">Logout</a>
		</nav>
	  </header>
<?php endif ?>

	  <h1><?php echo $title ?></h1>
	  <?php if ( $messages ): ?>
	  <aside id="messages"><?php echo $messages ?></aside>
	  <?php endif ?>
	  <?php echo $contents ?>
	</main>

 </body>
</html>
