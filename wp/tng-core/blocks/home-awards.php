<?php
/**
 * acf/home-awards — Awards & Rankings panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_awards' ) ) {
	return;
}
?>
<section class="hp-awards" data-section="awards" data-toggle="show_awards">
	<p class="subhead hp-awards__label"><?php echo esc_html( tng_field( 'label', 'Awards & Rankings' ) ); ?></p>

	<div class="hp-awards__panel">
		<div class="hp-awards__main">
			<h2 class="h2"><?php echo esc_html( tng_field( 'heading', 'Tilleke & Gibbins Achieves Top Results in Legal 500 Asia-Pacific 2026 Rankings' ) ); ?></h2>
			<img src="<?php echo esc_url( tng_img_url( 'logo', tng_asset( 'legal-500.png' ) ) ); ?>" alt="The Legal 500" class="hp-awards__logo">
		</div>

		<div class="hp-awards__text">
			<p class="body"><?php echo esc_html( tng_field( 'body', 'Tilleke & Gibbins has demonstrated continued excellence across all six jurisdictions where the firm operates in the 2026 edition of the Legal 500 Asia-Pacific rankings. The recently released rankings showcase the firm’s outstanding performance with 29 practice area recognitions and 34 individual rankings—an increase from 30 individual recognitions in 2025.' ) ); ?></p>
			<a href="<?php echo esc_url( tng_field( 'read_more_url', '#' ) ); ?>" class="button-label link-arrow">
				<?php echo esc_html( tng_field( 'read_more_label', 'Read more' ) ); ?>
				<?php echo tng_arrow( 1.5 ); ?>
			</a>
		</div>

		<a href="<?php echo esc_url( tng_field( 'card_url', '#' ) ); ?>" class="hp-awards__card">
			<h4 class="h4"><?php echo esc_html( tng_field( 'card_text', 'Learn more about our latest recognitions' ) ); ?></h4>
			<?php echo tng_arrow_circle( true ); ?>
		</a>
	</div>
</section>
