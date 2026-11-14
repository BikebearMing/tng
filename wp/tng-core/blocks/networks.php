<?php
/**
 * acf/networks — Global Reach: grey panel, heading, then per network a label
 * over a rule, a navy logo tile (links to the network) and the text +
 * link-arrow from 729. Logo fallback: the footer's bundled network logos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_networks' ) ) {
	return;
}

$tng_rows = array_values(
	array_filter(
		(array) tng_field( 'networks', array() ),
		static function ( $row ) {
			return ! empty( $row['title'] );
		}
	)
);
if ( ! $tng_rows ) {
	return;
}
// Bundled logos (white on navy) and their 1.5x widths, by network name
$tng_logos = array(
	'lex mundi'         => 'lexmundi',
	'multilaw'          => 'multilaw',
	'drew network asia' => 'dna',
);
?>
<section class="reach-networks" data-section="networks" data-toggle="show_networks">
	<h2 class="h2 reach-networks__heading"><?php echo esc_html( tng_field( 'heading', 'We are proud to be a member of:' ) ); ?></h2>
	<?php
	foreach ( $tng_rows as $tng_r ) :
		$tng_key  = strtolower( trim( $tng_r['title'] ) );
		$tng_mod  = isset( $tng_logos[ $tng_key ] ) ? $tng_logos[ $tng_key ] : '';
		$tng_logo = tng_img_value( isset( $tng_r['logo'] ) ? $tng_r['logo'] : '', $tng_mod ? tng_asset( $tng_mod . '.png' ) : '' );
		$tng_url  = isset( $tng_r['link_url'] ) ? $tng_r['link_url'] : '';
		?>
	<article class="reach-network">
		<h3 class="subhead pro-label reach-network__label"><?php echo esc_html( $tng_r['title'] ); ?></h3>
		<?php if ( $tng_logo ) : ?>
		<a href="<?php echo esc_url( $tng_url ? $tng_url : '#' ); ?>" class="reach-network__logo" target="_blank" rel="noopener"><img src="<?php echo esc_url( $tng_logo ); ?>" alt="<?php echo esc_attr( ! empty( $tng_r['logo_alt'] ) ? $tng_r['logo_alt'] : $tng_r['title'] ); ?>" class="reach-network__logo-img<?php echo $tng_mod ? ' reach-network__logo-img--' . esc_attr( $tng_mod ) : ''; ?>"></a>
		<?php endif; ?>
		<div class="reach-network__body">
			<div class="rich-text reach-network__text">
				<?php echo wp_kses_post( isset( $tng_r['text'] ) ? $tng_r['text'] : '' ); ?>
			</div>
			<?php if ( $tng_url && ! empty( $tng_r['link_label'] ) ) : ?>
			<a href="<?php echo esc_url( $tng_url ); ?>" class="button-label link-arrow reach-network__link" target="_blank" rel="noopener">
				<?php echo esc_html( $tng_r['link_label'] ); ?>
				<?php echo tng_arrow( 1.5 ); ?>
			</a>
			<?php endif; ?>
		</div>
	</article>
	<?php endforeach; ?>
</section>
