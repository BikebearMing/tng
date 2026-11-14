<?php
/**
 * tng/insight-article — sidebar (related professionals + share) and the
 * WYSIWYG body. The body is the post content wrapped in .in-content.rich-text
 * so every element is styled by tag.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();

$tng_related = array();
if ( tng_show( 'show_related_professionals', $tng_id ) ) {
	$tng_ids = tng_rel_ids( 'related_professionals', $tng_id );
	foreach ( $tng_ids as $tng_pid ) {
		if ( 'publish' === get_post_status( $tng_pid ) ) {
			$tng_related[] = $tng_pid;
		}
	}
}
// Former professionals (no page on the new site): plain names after the linked cards.
$tng_former = tng_show( 'show_related_professionals', $tng_id ) ? tng_former_authors( $tng_id ) : array();

$tng_content = apply_filters( 'the_content', get_the_content( null, false, $tng_id ) );
?>
<section class="in-article" data-section="in-article">
	<aside class="in-article__aside">
		<?php if ( $tng_related || $tng_former ) : ?>
		<div class="in-related" data-toggle="show_related_professionals">
			<div class="in-related__head">
				<h2 class="h3"><?php echo esc_html( tng_field( 'related_heading', 'Related professionals', $tng_id ) ); ?></h2>
				<svg class="in-related__plus" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 1v22M1 12h22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
			</div>

			<ul class="in-related__list">
				<?php foreach ( $tng_related as $tng_pid ) : ?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $tng_pid ) ); ?>" class="in-related__card">
						<img src="<?php echo esc_url( tng_pro_photo( $tng_pid ) ); ?>" alt="" class="in-related__img" loading="lazy">
						<span class="in-related__info">
							<span class="h4 in-related__name"><?php echo esc_html( get_the_title( $tng_pid ) ); ?></span>
							<?php if ( tng_field( 'phone', '', $tng_pid ) ) : ?>
							<span class="in-related__contact"><?php echo esc_html( tng_field( 'phone', '', $tng_pid ) ); ?></span>
							<?php endif; ?>
							<?php if ( tng_field( 'email', '', $tng_pid ) ) : ?>
							<span class="in-related__contact"><?php echo esc_html( tng_field( 'email', '', $tng_pid ) ); ?></span>
							<?php endif; ?>
						</span>
						<?php echo tng_arrow_circle( false, 'in-related__arrow' ); ?>
					</a>
				</li>
				<?php endforeach; ?>
				<?php foreach ( $tng_former as $tng_name ) : ?>
				<li>
					<span class="in-related__card in-related__card--former">
						<span class="in-related__info">
							<span class="h4 in-related__name"><?php echo esc_html( $tng_name ); ?></span>
						</span>
					</span>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<div class="in-share">
			<?php echo tng_share_buttons(); // phpcs:ignore ?>
		</div>
	</aside>

	<div class="in-content rich-text<?php echo 'award' === get_post_type( $tng_id ) ? ' txt-content' : ''; ?>">
		<?php echo $tng_content; // phpcs:ignore -- post content, already filtered ?>
	</div>
</section>
