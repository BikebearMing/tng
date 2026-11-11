<?php
/**
 * acf/team-stories — "Hear it from our team": numbered ARIA tabs (role +
 * name) swapping a white card (quote, person, photo). main.js's shared tabs
 * handler drives it (.lp-team).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_team_stories' ) ) {
	return;
}

$tng_stories = array_values(
	array_filter(
		(array) tng_field( 'stories', array() ),
		static function ( $row ) {
			return ! empty( $row['role'] ) || ! empty( $row['name'] );
		}
	)
);
if ( ! $tng_stories ) {
	return;
}
$tng_slug = static function ( $i ) {
	return 'story-' . ( $i + 1 );
};
?>
<section class="lp-team has-parallax" data-section="team-stories" data-toggle="show_team_stories">
	<img src="<?php echo esc_url( tng_img_url( 'background', tng_texture_url() ) ); ?>" alt="" class="parallax-img">
	<div class="lp-team__side">
		<h2 class="h2 dark lp-team__heading"><?php echo wp_kses( tng_field( 'heading', 'Hear it from<br />our team' ), array( 'br' => array() ) ); ?></h2>
		<ul class="lp-team__tabs" role="tablist" aria-label="Team stories">
			<?php foreach ( $tng_stories as $tng_i => $tng_s ) : $tng_on = 0 === $tng_i; ?>
			<li>
				<button type="button" class="lp-team__tab<?php echo $tng_on ? ' is-active' : ''; ?>" role="tab" id="lp-team-tab-<?php echo esc_attr( $tng_slug( $tng_i ) ); ?>" aria-controls="lp-team-<?php echo esc_attr( $tng_slug( $tng_i ) ); ?>" aria-selected="<?php echo $tng_on ? 'true' : 'false'; ?>"<?php echo $tng_on ? '' : ' tabindex="-1"'; ?>>
					<span class="lp-team__num"><?php echo esc_html( sprintf( '%02d', $tng_i + 1 ) ); ?></span>
					<span class="lp-team__role"><?php echo esc_html( $tng_s['role'] ); ?></span>
					<span class="lp-team__name"><?php echo esc_html( $tng_s['name'] ); ?></span>
				</button>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<div class="lp-team__panels">
		<?php foreach ( $tng_stories as $tng_i => $tng_s ) : ?>
		<article class="lp-team__panel" id="lp-team-<?php echo esc_attr( $tng_slug( $tng_i ) ); ?>" role="tabpanel" aria-labelledby="lp-team-tab-<?php echo esc_attr( $tng_slug( $tng_i ) ); ?>"<?php echo 0 === $tng_i ? '' : ' hidden'; ?>>
			<div class="lp-team__body">
				<blockquote class="rich-text lp-team__quote">
					<?php echo wp_kses_post( isset( $tng_s['quote'] ) ? $tng_s['quote'] : '' ); ?>
				</blockquote>
				<p class="lp-team__person"><strong><?php echo esc_html( $tng_s['name'] ); ?></strong><span><?php echo esc_html( isset( $tng_s['position'] ) ? $tng_s['position'] : '' ); ?></span></p>
			</div>
			<img src="<?php echo esc_url( tng_img_value( isset( $tng_s['image'] ) ? $tng_s['image'] : '', tng_bundled_img( array( 'team-story-' . ( $tng_i + 1 ) . '.jpg', 'team-story.jpg' ) ) ) ); ?>" alt="" class="lp-team__image" loading="lazy">
		</article>
		<?php endforeach; ?>
	</div>
</section>
