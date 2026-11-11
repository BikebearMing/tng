<?php
/**
 * tng/term-overview — overview on term pages. Three layouts, as in the mocks:
 *  - location:            label over the heading (left) + text (right)
 *  - industry:            heading (left) | label + text (from 729)
 *  - practice / subterm:  top-label layout; a practice shows its quote when
 *                         filled (else the heading); a subpractice adds --sub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'overview' ) ) {
	return;
}

$tng_ref     = tng_term_ref( $tng_term );
$tng_heading = tng_field( 'overview_heading', '', $tng_ref );
$tng_text    = tng_field( 'overview_text', '', $tng_ref );

if ( 'location' === $tng_term->taxonomy ) : ?>
<section class="loc-overview" id="overview" data-section="loc-overview" data-toggle="show_loc_overview">
	<p class="subhead pro-label loc-overview__label">Overview</p>
	<?php if ( $tng_heading ) : ?>
	<h2 class="h2 loc-overview__heading"><?php echo esc_html( $tng_heading ); ?></h2>
	<?php endif; ?>
	<div class="rich-text loc-overview__text">
		<?php echo wp_kses_post( $tng_text ); ?>
	</div>
</section>
	<?php
	return;
endif;

if ( 'industry' === $tng_term->taxonomy && ! tng_is_subterm( $tng_term ) ) :
	?>
<section class="svc-overview" id="overview" data-section="svc-overview" data-toggle="show_svc_overview">
	<h2 class="h2 svc-overview__heading"><?php echo esc_html( $tng_heading ); ?></h2>
	<div class="svc-overview__body">
		<p class="subhead pro-label">Overview</p>
		<div class="rich-text svc-overview__text">
			<?php echo wp_kses_post( $tng_text ); ?>
		</div>
	</div>
</section>
	<?php
	return;
endif;

$tng_sub   = tng_is_subterm( $tng_term );
$tng_quote = $tng_sub ? '' : tng_field( 'quote', '', $tng_ref );
?>
<section class="svc-overview svc-overview--top-label<?php echo $tng_sub ? ' svc-overview--sub' : ''; ?>" id="overview" data-section="svc-overview" data-toggle="show_svc_overview">
	<p class="subhead pro-label svc-overview__label">Overview</p>
	<?php if ( $tng_quote ) : ?>
	<figure class="svc-overview__quote">
		<blockquote class="h3"><?php echo esc_html( $tng_quote ); ?></blockquote>
		<?php $tng_source = tng_field( 'quote_source', '', $tng_ref ); ?>
		<?php if ( $tng_source ) : ?>
		<figcaption class="subhead"><?php echo esc_html( $tng_source ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<?php else : ?>
	<h2 class="h2 svc-overview__heading"><?php echo esc_html( $tng_heading ); ?></h2>
	<?php endif; ?>
	<div class="rich-text svc-overview__text">
		<?php echo wp_kses_post( $tng_text ); ?>
	</div>
</section>
