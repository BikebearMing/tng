<?php
/**
 * tng/insight-hero — navy hero with taxonomy pill rows and the featured
 * image. show_featured_image off (or no thumbnail) = the --no-image variant.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_in_hero', $tng_id ) ) {
	return;
}

$tng_image = tng_show( 'show_featured_image', $tng_id ) ? get_the_post_thumbnail_url( $tng_id, 'full' ) : '';

$tng_rows = array();
foreach ( array(
	'location' => 'Location',
	'industry' => 'Industries',
	'practice' => 'Practices',
) as $tng_tax => $tng_label ) {
	$tng_terms = get_the_terms( $tng_id, $tng_tax );
	if ( is_array( $tng_terms ) && $tng_terms ) {
		if ( 'location' === $tng_tax ) {
			$tng_terms = array_values( array_filter( $tng_terms, static function ( $t ) { return ! $t->parent; } ) );
			if ( ! $tng_terms ) {
				continue;
			}
		}
		$tng_rows[] = array( $tng_label, $tng_tax, $tng_terms );
	}
}
?>
<section class="in-hero<?php echo $tng_image ? '' : ' in-hero--no-image'; ?>" data-section="in-hero" data-toggle="show_in_hero">
	<?php echo tng_breadcrumbs( get_the_title( $tng_id ), array( array( 'Insights', '#' ) ) ); ?>

	<div class="in-hero__intro">
		<h1 class="h1 dark"><?php echo esc_html( get_the_title( $tng_id ) ); ?></h1>
		<p class="subhead in-hero__date"><time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d', $tng_id ) ); ?>"><?php echo esc_html( tng_hero_date( $tng_id ) ); ?></time></p>
	</div>

	<?php if ( $tng_rows ) : ?>
	<dl class="in-hero__meta" data-cap="3">
		<?php foreach ( $tng_rows as $tng_row ) : list( $tng_label, $tng_tax, $tng_terms ) = $tng_row; ?>
		<div class="in-hero__row">
			<dt class="subhead"><?php echo esc_html( $tng_label ); ?></dt>
			<dd>
				<ul class="in-hero__tags">
					<?php
					foreach ( $tng_terms as $tng_term ) :
						$tng_flag = 'location' === $tng_tax ? tng_location_flag( $tng_term ) : '';
						?>
					<li><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="in-hero__tag"><?php if ( $tng_flag ) : ?><img src="<?php echo esc_url( $tng_flag ); ?>" alt=""><?php endif; ?><?php echo esc_html( $tng_term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</dd>
		</div>
		<?php endforeach; ?>
	</dl>
	<?php endif; ?>

	<?php if ( $tng_image ) : ?>
	<img src="<?php echo esc_url( $tng_image ); ?>" alt="" class="in-hero__image">
	<?php endif; ?>
</section>
