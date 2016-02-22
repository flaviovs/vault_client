<!DOCTYPE html>
<html>
  <head>
	<title><?php echo $title ?></title>
  </head>
  <body>
	<main>
	  <h1><?php echo $title ?></h1>
	  <?php if ( $messages ): ?>
	  <aside id="messages"><?php echo $messages ?></aside>
	  <?php endif ?>
	  <?php echo $contents ?>
	</main>

 </body>
</html>
