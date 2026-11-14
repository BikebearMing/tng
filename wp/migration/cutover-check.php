<?php
// Self-check for tng_cutover_replace() (wp/tng-core/inc/import.php): php wp/migration/cutover-check.php
define( 'ABSPATH', __DIR__ ); 
function add_action() {} 
function is_serialized( $data ) { // WP core, trimmed
	if ( ! is_string( $data ) ) return false;
	$data = trim( $data );
	if ( 'N;' === $data ) return true;
	if ( strlen( $data ) < 4 || ':' !== $data[1] ) return false;
	return (bool) preg_match( '/^[aOsbid]:/', $data ) && in_array( substr( $data, -1 ), array( ';', '}' ), true );
}
$src = file_get_contents( '/Users/karminglaw/Desktop/tng/wp/tng-core/inc/import.php' );
$a = strpos( $src, 'function tng_cutover_replace' ); $b = strpos( $src, '/** The columns the cutover scans' );
eval( substr( $src, $a, $b - $a ) );
$ok = 0; $bad = 0;
$eq = function ( $l, $g, $w ) use ( &$ok, &$bad ) { if ( $g === $w ) { $ok++; } else { $bad++; echo "FAIL $l\n got  " . var_export( $g, true ) . "\n want " . var_export( $w, true ) . "\n"; } };
$F = 'tng.mydemobb.com'; $T = 'www.tilleke.com';
$n = 0; $eq( 'plain', tng_cutover_replace( 'a https://tng.mydemobb.com/x b', $F, $T, $n ), 'a https://www.tilleke.com/x b' ); $eq( 'n', $n, 1 );
$n = 0; $eq( 'http', tng_cutover_replace( 'http://tng.mydemobb.com', $F, $T, $n ), 'https://www.tilleke.com' );
$n = 0; $eq( 'json', tng_cutover_replace( '{"u":"https:\/\/tng.mydemobb.com\/a","v":"http:\/\/TNG.mydemobb.com"}', $F, $T, $n ), '{"u":"https:\/\/www.tilleke.com\/a","v":"https:\/\/www.tilleke.com"}' ); $eq( 'json n', $n, 2 );
$ser = serialize( array( 'links' => array( array( 'url' => 'https://tng.mydemobb.com/about/', 'label' => 'About' ) ), 'n' => 3, 'nested' => serialize( 'https://tng.mydemobb.com/' ) ) );
$n = 0; $out = tng_cutover_replace( $ser, $F, $T, $n );
$eq( 'ser valid', is_array( unserialize( $out ) ), true );
$u = unserialize( $out );
$eq( 'ser value', $u['links'][0]['url'], 'https://www.tilleke.com/about/' );
$eq( 'ser nested', unserialize( $u['nested'] ), 'https://www.tilleke.com/' );
$eq( 'ser n', $n, 2 );
$block = '<!-- wp:acf/more-from {"name":"acf/more-from","data":{"mfr_rows_0_url":"https://tng.mydemobb.com/about/history/"}} -->';
$n = 0; $eq( 'block', tng_cutover_replace( $block, $F, $T, $n ), str_replace( 'tng.mydemobb.com', 'www.tilleke.com', $block ) );
$n = 0; $eq( 'untouched', tng_cutover_replace( 'https://example.com', $F, $T, $n ), 'https://example.com' ); $eq( 'untouched n', $n, 0 );
$n = 0; $eq( 'bool false ser', tng_cutover_replace( 'b:0;', $F, $T, $n ), 'b:0;' );
echo "cutover self-check: $ok passed, $bad failed\n"; exit( $bad ? 1 : 0 );
