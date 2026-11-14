<?php
/**
 * acf/home-insights — insights by industry: tabs from the Industry taxonomy,
 * per-tab feature (latest insight in that industry) + 3-row list. All tab
 * panels are embedded as JSON; tng-wp.js swaps the visible one on tab click.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_insights' ) ) {
	return;
}

$tng_term_ids = get_field( 'industries' );
if ( $tng_term_ids ) {
	$tng_tabs = array_filter( array_map( static function ( $id ) {
		$t = get_term( is_object( $id ) ? $id->term_id : (int) $id, 'industry' );
		return ( $t && ! is_wp_error( $t ) ) ? $t : null;
	}, (array) $tng_term_ids ) );
} else {
	$tng_tabs = get_terms( array( 'taxonomy' => 'industry', 'hide_empty' => false, 'number' => 5 ) );
	$tng_tabs = is_wp_error( $tng_tabs ) ? array() : $tng_tabs;
}

// One data set per tab: feature = latest in the industry, list = next 3.
$tng_panels = array();
foreach ( $tng_tabs as $tng_term ) {
	$tng_posts = get_posts(
		array(
			'post_type'      => 'insight',
			'posts_per_page' => 4,
			'tax_query'      => array(
				array(
					'taxonomy' => 'industry',
					'terms'    => $tng_term->term_id,
				),
			),
		)
	);
	$tng_feature = array_shift( $tng_posts );
	$tng_panels[] = array(
		'feature' => $tng_feature ? array(
			// Plain text: tng-wp.js escapes it once when it builds the panel
			// (get_the_title() is texturized, so "&" arrives as &#038;).
			'title'   => html_entity_decode( get_the_title( $tng_feature ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'excerpt' => html_entity_decode( get_the_excerpt( $tng_feature ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'date'    => tng_card_date( $tng_feature ),
			'url'     => get_permalink( $tng_feature ),
			'image'   => get_the_post_thumbnail_url( $tng_feature, 'large' ) ?: tng_bundled_img( array( 'home-insights-' . $tng_term->slug . '.jpg', 'home-insights-feature.jpg' ) ),
		) : null,
		'list'    => array_map(
			static function ( $p ) {
				return array(
					'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'date'  => tng_card_date( $p ),
					'url'   => get_permalink( $p ),
				);
			},
			$tng_posts
		),
	);
}

$tng_first = isset( $tng_panels[0] ) ? $tng_panels[0] : array( 'feature' => null, 'list' => array() );
?>
<section class="hp-insights" data-section="insights" data-toggle="show_insights">
	<p class="subhead hp-insights__label"><?php echo esc_html( tng_field( 'label', 'Insights' ) ); ?></p>

	<div class="hp-insights__tabs">
		<ul class="hp-insights__tab-list" role="tablist">
			<?php foreach ( $tng_tabs as $tng_i => $tng_term ) : ?>
			<li><button type="button" class="h3 hp-insights__tab<?php echo 0 === $tng_i ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo 0 === $tng_i ? 'true' : 'false'; ?>" data-tab-index="<?php echo esc_attr( $tng_i ); ?>"><?php echo esc_html( $tng_term->name ); ?></button></li>
			<?php endforeach; ?>
		</ul>
		<a href="<?php echo esc_url( tng_link_or( tng_field( 'view_all_url', '#' ), tng_page_url( 'insights' ) ) ); ?>" class="button-label link-arrow">
			View all
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
	</div>

	<div class="hp-insights__body">
		<?php if ( $tng_first['feature'] ) : $tng_f = $tng_first['feature']; ?>
		<a href="<?php echo esc_url( $tng_f['url'] ); ?>" class="hp-insights__feature">
			<img src="<?php echo esc_url( $tng_f['image'] ); ?>" alt="">
			<div class="hp-insights__card">
				<h3 class="h3"><?php echo esc_html( $tng_f['title'] ); ?></h3>
				<p class="body"><?php echo esc_html( $tng_f['excerpt'] ); ?></p>
				<div class="hp-insights__card-foot">
					<span class="subhead"><?php echo esc_html( $tng_f['date'] ); ?></span>
					<?php echo tng_arrow_circle( false ); ?>
				</div>
			</div>
		</a>
		<?php endif; ?>

		<ul class="hp-insights__list">
			<?php foreach ( $tng_first['list'] as $tng_row ) : ?>
			<li class="hp-insights__item">
				<a href="<?php echo esc_url( $tng_row['url'] ); ?>">
					<h4 class="h4"><?php echo esc_html( $tng_row['title'] ); ?></h4>
					<span class="subhead"><?php echo esc_html( $tng_row['date'] ); ?></span>
					<?php echo tng_arrow_circle( false ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<script type="application/json" class="hp-insights__data"><?php echo wp_json_encode( $tng_panels ); ?></script>
</section>
