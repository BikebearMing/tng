<?php
/**
 * tng/search-results — the tng-search template body (?s=<query>&type=).
 * Hero (title, query field, count), type pills (links: All + each type with
 * results, counts), then either the grouped view (professionals row, insight
 * and award cards, page rows; "View all N" opens the type) or one type's
 * results, 12 at a time ("View more" = a link that shows 12 more).
 * Empty query or no results: a short note and the 404 page's links.
 * Engine: tng_search() in inc/search.php (also behind /wp-json/tng/v1/search).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_q     = tng_search_query();
$tng_types = tng_search_types();
$tng_type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$tng_type  = isset( $tng_types[ $tng_type ] ) ? $tng_type : '';
$tng_more  = isset( $_GET['more'] ) ? max( 1, min( 50, (int) $_GET['more'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
$tng_all   = tng_search( $tng_q, '', 5 );
$tng_total = array_sum( wp_list_pluck( $tng_all, 'count' ) );
$tng_type  = isset( $tng_all[ $tng_type ] ) ? $tng_type : '';
$tng_size  = 12 * $tng_more;

if ( '' === $tng_q ) {
	$tng_count = 'Search professionals, insights, awards and pages.';
} elseif ( $tng_total ) {
	// "111 results for “intellectual property”"
	$tng_count = sprintf( '%1$s %2$s for “%3$s”', number_format_i18n( $tng_total ), 1 === $tng_total ? 'result' : 'results', $tng_q );
} else {
	$tng_count = sprintf( 'No results for “%s”', $tng_q );
}

$tng_row = static function ( $row ) {
	return '<li><a href="' . esc_url( $row[1] ) . '" class="careers-jobs__row"><span class="h4">' . esc_html( $row[0] ) . '</span><span class="subhead careers-jobs__count">' . esc_html( $row[2] ) . '</span>' . tng_arrow_circle( true ) . '</a></li>';
};
?>
<section class="lp-hero srch-hero" data-section="search-hero">
	<?php echo tng_breadcrumbs( 'Search' ); // phpcs:ignore ?>

	<div class="lp-hero__content">
		<h1 class="h1 dark">Search results</h1>
		<div class="srch-hero__side">
			<form class="hp-footer__form srch-form" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
				<label class="visually-hidden" for="srch-q">Search the site</label>
				<input type="search" id="srch-q" name="s" value="<?php echo esc_attr( $tng_q ); ?>" placeholder="Search professionals, insights, awards and pages">
				<button type="submit">
					Search
					<?php echo tng_arrow( 1.5 ); // phpcs:ignore ?>
				</button>
			</form>
			<p class="body dark srch-hero__count"><?php echo esc_html( $tng_count ); ?></p>
		</div>
	</div>
</section>

<?php if ( $tng_total ) : ?>
<nav class="srch-tabs" aria-label="Result types" data-section="search-tabs">
	<ul class="job-list__filter srch-tabs__list">
		<li><a href="<?php echo esc_url( tng_search_url( $tng_q ) ); ?>" class="job-list__pill"<?php echo $tng_type ? '' : ' aria-current="page"'; ?>>All <span class="job-list__count"><?php echo esc_html( number_format_i18n( $tng_total ) ); ?></span></a></li>
		<?php foreach ( $tng_all as $tng_t => $tng_g ) : ?>
		<li><a href="<?php echo esc_url( tng_search_url( $tng_q, $tng_t ) ); ?>" class="job-list__pill"<?php echo $tng_t === $tng_type ? ' aria-current="page"' : ''; ?>><?php echo esc_html( 'awards' === $tng_t ? 'Awards' : $tng_g['label'] ); ?> <span class="job-list__count"><?php echo esc_html( number_format_i18n( $tng_g['count'] ) ); ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
<?php endif; ?>

<?php if ( ! $tng_total ) : ?>
<section class="ins-grid ins-grid--plain srch-empty" data-section="search-empty">
	<p class="body ins-grid__empty"><?php echo esc_html( '' === $tng_q ? 'Type a name, a practice or a topic in the field above.' : 'Check the spelling, try a shorter or more general term, or start from one of these pages:' ); ?></p>
	<ul class="careers-jobs__list srch-pages">
		<?php
		foreach ( array(
			array( 'Professionals', tng_page_url( 'professionals', home_url( '/' ) ), 'Find a professional' ),
			array( 'Services', tng_page_url( 'services', home_url( '/' ) ), 'Industries and practices' ),
			array( 'Insights', tng_page_url( 'insights', home_url( '/' ) ), 'Articles and updates' ),
			array( 'Contact', tng_page_url( 'contact', home_url( '/' ) ), 'Our offices' ),
		) as $tng_r ) {
			echo $tng_row( $tng_r ); // phpcs:ignore -- escaped in the closure
		}
		?>
	</ul>
</section>

<?php elseif ( ! $tng_type ) : ?>
	<?php
	foreach ( $tng_all as $tng_t => $tng_g ) :
		$tng_view = tng_search_url( $tng_q, $tng_t );
		if ( 'pages' === $tng_t ) :
			?>
<section class="pro-related srch-group srch-group--pages" id="pages" data-section="search-pages">
	<div class="pro-related__head">
		<h2 class="h2 pro-related__title"><?php echo esc_html( $tng_g['label'] ); ?></h2>
		<a href="<?php echo esc_url( $tng_view ); ?>" class="button-label link-arrow pro-related__link">
			<?php echo esc_html( 'View all ' . number_format_i18n( $tng_g['count'] ) ); ?>
			<?php echo tng_arrow( 1.5 ); // phpcs:ignore ?>
		</a>
	</div>
	<ul class="careers-jobs__list srch-pages">
		<?php
		foreach ( array_slice( $tng_g['items'], 0, 5 ) as $tng_r ) {
			echo $tng_row( $tng_r ); // phpcs:ignore -- escaped in the closure
		}
		?>
	</ul>
</section>
			<?php
		else :
			$tng_people = 'professionals' === $tng_t;
			$tng_cards  = '';
			foreach ( array_slice( $tng_g['items'], 0, $tng_people ? 4 : 3 ) as $tng_pid ) {
				$tng_cards .= $tng_people ? tng_pro_card( $tng_pid ) : tng_post_card( get_post( $tng_pid ) );
			}
			echo tng_related_section( // phpcs:ignore
				array(
					'id'           => $tng_t,
					'class'        => 'pro-related srch-group' . ( $tng_people ? ' pro-related--people srch-group--people' : '' ),
					'data_section' => 'search-' . $tng_t,
					'heading'      => $tng_g['label'],
					'link_label'   => 'View all ' . number_format_i18n( $tng_g['count'] ),
					'link_url'     => $tng_view,
					'cards'        => $tng_cards,
				)
			);
		endif;
	endforeach;
	?>

<?php else : ?>
	<?php
	$tng_g     = tng_search( $tng_q, $tng_type, $tng_size )[ $tng_type ];
	$tng_shown = count( $tng_g['items'] );
	$tng_next  = $tng_g['count'] > $tng_shown ? add_query_arg( 'more', $tng_more + 1, tng_search_url( $tng_q, $tng_type ) ) . '#results' : '';
	if ( 'professionals' === $tng_type ) {
		$tng_class = 'pro-grid';
		$tng_list  = 'pro-grid__list';
		$tng_items = implode( '', array_map( 'tng_pro_card', $tng_g['items'] ) );
	} elseif ( 'pages' === $tng_type ) {
		$tng_class = 'ins-grid ins-grid--plain';
		$tng_list  = 'careers-jobs__list srch-pages';
		$tng_items = implode( '', array_map( $tng_row, $tng_g['items'] ) );
	} else {
		$tng_class = 'ins-grid ins-grid--plain';
		$tng_list  = 'ins-grid__list';
		$tng_items = implode( '', array_map( 'tng_post_card', array_map( 'get_post', $tng_g['items'] ) ) );
	}
	?>
<section class="<?php echo esc_attr( $tng_class ); ?> srch-results" id="results" data-section="search-<?php echo esc_attr( $tng_type ); ?>">
	<ul class="<?php echo esc_attr( $tng_list ); ?>">
		<?php echo $tng_items; // phpcs:ignore -- built from escaping helpers ?>
	</ul>
	<div class="pro-grid__more ins-grid__more">
		<p class="pro-grid__count">Showing <strong><?php echo esc_html( number_format_i18n( $tng_shown ) ); ?></strong> out of <strong><?php echo esc_html( number_format_i18n( $tng_g['count'] ) ); ?></strong> results</p>
		<?php if ( $tng_next ) : ?>
		<a href="<?php echo esc_url( $tng_next ); ?>" class="button-label link-arrow pro-grid__view-more">
			View more
			<?php echo tng_arrow_down(); // phpcs:ignore ?>
		</a>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
