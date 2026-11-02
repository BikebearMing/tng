<?php
/**
 * tng/pro-overview — biography column + facts (industry/practice pills,
 * languages, education). A facts group hides when it has nothing to show.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_pro_overview', $tng_id ) ) {
	return;
}

$tng_lead      = tng_field( 'lead', '', $tng_id );
$tng_biography = tng_field( 'biography', '', $tng_id );
$tng_education = tng_field( 'education', array(), $tng_id );

$tng_industries = get_the_terms( $tng_id, 'industry' );
$tng_industries = is_array( $tng_industries ) ? $tng_industries : array();
$tng_practices  = get_the_terms( $tng_id, 'practice' );
$tng_practices  = is_array( $tng_practices ) ? $tng_practices : array();
$tng_languages  = get_the_terms( $tng_id, 'language' );
$tng_languages  = is_array( $tng_languages ) ? wp_list_pluck( $tng_languages, 'name' ) : array();
?>
<section class="pro-overview" id="overview" data-section="pro-overview" data-toggle="show_pro_overview">
	<div class="pro-overview__bio">
		<p class="subhead pro-label">Biography</p>
		<?php if ( $tng_lead ) : ?>
		<p class="h3 pro-overview__lead"><?php echo esc_html( $tng_lead ); ?></p>
		<?php endif; ?>
		<?php if ( $tng_biography ) : ?>
		<div class="rich-text pro-overview__text">
			<?php echo wp_kses_post( $tng_biography ); ?>
		</div>
		<?php endif; ?>
	</div>

	<dl class="pro-facts">
		<?php if ( $tng_industries ) : ?>
		<div class="pro-facts__group">
			<dt class="subhead pro-label">Industries</dt>
			<dd>
				<ul class="pro-facts__tags">
					<?php foreach ( $tng_industries as $tng_term ) : ?>
					<li><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="pro-facts__tag"><?php echo esc_html( $tng_term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</dd>
		</div>
		<?php endif; ?>
		<?php if ( $tng_practices ) : ?>
		<div class="pro-facts__group">
			<dt class="subhead pro-label">Practices</dt>
			<dd>
				<ul class="pro-facts__tags">
					<?php foreach ( $tng_practices as $tng_term ) : ?>
					<li><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="pro-facts__tag"><?php echo esc_html( $tng_term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</dd>
		</div>
		<?php endif; ?>
		<?php if ( $tng_languages ) : ?>
		<div class="pro-facts__group">
			<dt class="subhead pro-label"><?php echo count( $tng_languages ) > 1 ? 'Languages' : 'Language'; ?></dt>
			<dd class="pro-facts__text"><?php echo esc_html( implode( ', ', $tng_languages ) ); ?></dd>
		</div>
		<?php endif; ?>
		<?php if ( $tng_education ) : ?>
		<div class="pro-facts__group">
			<dt class="subhead pro-label">Education</dt>
			<dd>
				<ul class="pro-facts__text pro-facts__list">
					<?php foreach ( $tng_education as $tng_row ) : ?>
					<li><?php echo wp_kses( $tng_row['entry'], array( 'br' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</dd>
		</div>
		<?php endif; ?>
	</dl>
</section>
