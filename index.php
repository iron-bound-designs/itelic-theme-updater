<?php
/**
 * The main template file.
 *
 * This theme doesn't output anything.  It just show how Exchange licensing works.
 *
 * @package WordPress
 * @subpackage EDD Sample Theme
 */
?>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<title>EDD Sample Theme</title>
	<link rel="stylesheet" type="text/css" media="all" href="<?php bloginfo( 'stylesheet_url' ); ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>


<article>
	<h1>Exchange Sample Theme</h1>
</article>

<?php wp_footer(); ?>

</body>
</html>