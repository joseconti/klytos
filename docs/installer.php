<?php
/**
 * Klytos — Web Installer (Bootstrap)
 *
 * This single file downloads the latest stable Klytos release from GitHub,
 * extracts it into the current directory, and redirects to the real installer.
 *
 * Usage:
 *   1. Upload this file to the document root of your domain.
 *   2. Visit https://your-site.com/installer.php
 *   3. Choose your language and click Install.
 *
 * Requirements: PHP >= 8.0, ZipArchive extension, allow_url_fopen or cURL.
 *
 * @package Klytos
 * @license Elastic License 2.0 (ELv2)
 * @copyright 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare( strict_types=1 );

// ─── Configuration ──────────────────────────────────────────────────────────
define( 'KLYTOS_REPO',       'joseconti/klytos' );
define( 'KLYTOS_ZIP_URL',    'https://github.com/' . KLYTOS_REPO . '/releases/latest/download/klytos.zip' );
define( 'KLYTOS_API_URL',    'https://api.github.com/repos/' . KLYTOS_REPO . '/releases/latest' );
define( 'KLYTOS_INSTALLER',  'install.php' );
define( 'KLYTOS_ZIP_FILE',   'klytos-latest.zip' );
define( 'KLYTOS_MIN_PHP',    '8.0.0' );

// ─── Translations ───────────────────────────────────────────────────────────
$translations = [
	'en' => [
		'title'           => 'Klytos Installer',
		'subtitle'        => 'Welcome to Klytos',
		'description'     => 'This wizard will download and install the latest stable version of Klytos on your server.',
		'select_language' => 'Select your language',
		'btn_install'     => 'Install Klytos',
		'step_download'   => 'Downloading latest release...',
		'step_extract'    => 'Extracting files...',
		'step_cleanup'    => 'Cleaning up...',
		'step_redirect'   => 'Redirecting to installer...',
		'error_php'       => 'Klytos requires PHP %s or higher. You are running PHP %s.',
		'error_zip'       => 'The ZipArchive PHP extension is required.',
		'error_write'     => 'The directory <code>%s</code> is not writable.',
		'error_download'  => 'Could not download the Klytos package. Please check your server\'s internet connection.',
		'error_extract'   => 'Could not extract the downloaded package.',
		'error_no_release'=> 'No stable release found. Please try again later.',
		'checking'        => 'Checking requirements...',
		'requirements_ok' => 'All requirements met.',
	],
	'es' => [
		'title'           => 'Instalador de Klytos',
		'subtitle'        => 'Bienvenido a Klytos',
		'description'     => 'Este asistente descargará e instalará la última versión estable de Klytos en tu servidor.',
		'select_language' => 'Selecciona tu idioma',
		'btn_install'     => 'Instalar Klytos',
		'step_download'   => 'Descargando última versión...',
		'step_extract'    => 'Extrayendo archivos...',
		'step_cleanup'    => 'Limpiando...',
		'step_redirect'   => 'Redirigiendo al instalador...',
		'error_php'       => 'Klytos requiere PHP %s o superior. Estás usando PHP %s.',
		'error_zip'       => 'La extensión ZipArchive de PHP es necesaria.',
		'error_write'     => 'El directorio <code>%s</code> no tiene permisos de escritura.',
		'error_download'  => 'No se pudo descargar el paquete de Klytos. Verifica la conexión a internet del servidor.',
		'error_extract'   => 'No se pudo extraer el paquete descargado.',
		'error_no_release'=> 'No se encontró una versión estable. Inténtalo de nuevo más tarde.',
		'checking'        => 'Comprobando requisitos...',
		'requirements_ok' => 'Todos los requisitos cumplidos.',
	],
];

// ─── Language detection ─────────────────────────────────────────────────────
$lang = 'en';
if ( isset( $_GET['lang'] ) && array_key_exists( $_GET['lang'], $translations ) ) {
	$lang = $_GET['lang'];
} elseif ( isset( $_POST['lang'] ) && array_key_exists( $_POST['lang'], $translations ) ) {
	$lang = $_POST['lang'];
}
$t = $translations[ $lang ];

// ─── Handle AJAX install request ────────────────────────────────────────────
if ( isset( $_POST['action'] ) && $_POST['action'] === 'install' ) {
	header( 'Content-Type: application/json; charset=utf-8' );

	$steps = [];
	$error = null;

	// 1. Check requirements.
	if ( version_compare( PHP_VERSION, KLYTOS_MIN_PHP, '<' ) ) {
		$error = sprintf( $t['error_php'], KLYTOS_MIN_PHP, PHP_VERSION );
	} elseif ( ! class_exists( 'ZipArchive' ) ) {
		$error = $t['error_zip'];
	} elseif ( ! is_writable( __DIR__ ) ) {
		$error = sprintf( $t['error_write'], __DIR__ );
	}

	if ( $error ) {
		echo json_encode( [ 'ok' => false, 'error' => $error ] );
		exit;
	}

	// 2. Get latest release download URL from GitHub API.
	$zip_url = null;
	$api_response = klytos_fetch_url( KLYTOS_API_URL );
	if ( $api_response ) {
		$release = json_decode( $api_response, true );
		if ( $release && ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( str_ends_with( $asset['name'], '.zip' ) ) {
					$zip_url = $asset['browser_download_url'];
					break;
				}
			}
		}
		// Fallback to zipball if no asset found.
		if ( ! $zip_url && ! empty( $release['zipball_url'] ) ) {
			$zip_url = $release['zipball_url'];
		}
	}

	if ( ! $zip_url ) {
		echo json_encode( [ 'ok' => false, 'error' => $t['error_no_release'] ] );
		exit;
	}

	// 3. Download.
	$zip_path = __DIR__ . '/' . KLYTOS_ZIP_FILE;
	$downloaded = klytos_download_file( $zip_url, $zip_path );
	if ( ! $downloaded ) {
		echo json_encode( [ 'ok' => false, 'error' => $t['error_download'] ] );
		exit;
	}

	// 4. Extract.
	$zip = new ZipArchive();
	if ( $zip->open( $zip_path ) !== true ) {
		@unlink( $zip_path );
		echo json_encode( [ 'ok' => false, 'error' => $t['error_extract'] ] );
		exit;
	}

	// GitHub zipballs contain a root folder like "joseconti-klytos-abc1234/".
	// We need to detect this and move files out.
	$root_prefix = '';
	if ( $zip->numFiles > 0 ) {
		$first_entry = $zip->getNameIndex( 0 );
		if ( str_contains( $first_entry, '/' ) ) {
			$root_prefix = explode( '/', $first_entry )[0] . '/';
		}
	}

	$tmp_dir = __DIR__ . '/klytos-tmp-' . bin2hex( random_bytes( 4 ) );
	$zip->extractTo( $tmp_dir );
	$zip->close();
	@unlink( $zip_path );

	// 5. Move files from extracted subfolder (or root) into the document root.
	$source_dir = $tmp_dir;
	if ( $root_prefix ) {
		$candidate = $tmp_dir . '/' . rtrim( $root_prefix, '/' );
		if ( is_dir( $candidate ) ) {
			$source_dir = $candidate;
		}
	}

	// If there's an "installer" subfolder inside, that's our content.
	if ( is_dir( $source_dir . '/installer' ) ) {
		$source_dir = $source_dir . '/installer';
	}

	// Extract into a dedicated "install" subdirectory so the CMS files
	// don't mix with the current directory (which may contain other sites/files).
	$install_dir = __DIR__ . '/install';
	if ( ! is_dir( $install_dir ) ) {
		mkdir( $install_dir, 0755, true );
	}
	klytos_move_contents( $source_dir, $install_dir );

	// 6. Clean up temporary directory.
	klytos_rmdir_recursive( $tmp_dir );

	// 7. Build redirect URL — point to install/install.php inside the new subdirectory.
	$scheme      = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$dir         = rtrim( dirname( $_SERVER['SCRIPT_NAME'] ), '/' );
	$redirect    = $scheme . '://' . $host . $dir . '/install/' . KLYTOS_INSTALLER . '?lang=' . $lang;

	echo json_encode( [ 'ok' => true, 'redirect' => $redirect ] );
	exit;
}

// ─── Helper functions ───────────────────────────────────────────────────────

/**
 * Fetch a URL's content using cURL or file_get_contents.
 */
function klytos_fetch_url( string $url ): ?string {
	$ctx_opts = [
		'http' => [
			'method'          => 'GET',
			'header'          => "User-Agent: Klytos-Installer/1.0\r\n",
			'follow_location' => 1,
			'timeout'         => 30,
		],
	];

	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $url );
		curl_setopt_array( $ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT      => 'Klytos-Installer/1.0',
			CURLOPT_TIMEOUT        => 30,
		] );
		$body = curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		return ( $code >= 200 && $code < 300 && $body !== false ) ? $body : null;
	}

	$ctx  = stream_context_create( $ctx_opts );
	$body = @file_get_contents( $url, false, $ctx );
	return $body !== false ? $body : null;
}

/**
 * Download a file from a URL to a local path.
 */
function klytos_download_file( string $url, string $dest ): bool {
	if ( function_exists( 'curl_init' ) ) {
		$fp = fopen( $dest, 'w' );
		if ( ! $fp ) {
			return false;
		}
		$ch = curl_init( $url );
		curl_setopt_array( $ch, [
			CURLOPT_FILE           => $fp,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT      => 'Klytos-Installer/1.0',
			CURLOPT_TIMEOUT        => 120,
		] );
		curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		fclose( $fp );
		if ( $code < 200 || $code >= 300 ) {
			@unlink( $dest );
			return false;
		}
		return true;
	}

	$ctx_opts = [
		'http' => [
			'method'          => 'GET',
			'header'          => "User-Agent: Klytos-Installer/1.0\r\n",
			'follow_location' => 1,
			'timeout'         => 120,
		],
	];
	$ctx  = stream_context_create( $ctx_opts );
	$data = @file_get_contents( $url, false, $ctx );
	if ( $data === false ) {
		return false;
	}
	return file_put_contents( $dest, $data ) !== false;
}

/**
 * Recursively move contents from source into destination.
 */
function klytos_move_contents( string $src, string $dst ): void {
	$items = scandir( $src );
	if ( $items === false ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( $item === '.' || $item === '..' ) {
			continue;
		}
		// Don't overwrite installer.php itself.
		if ( $item === 'installer.php' ) {
			continue;
		}
		$src_path = $src . '/' . $item;
		$dst_path = $dst . '/' . $item;
		if ( is_dir( $src_path ) ) {
			if ( ! is_dir( $dst_path ) ) {
				mkdir( $dst_path, 0755, true );
			}
			klytos_move_contents( $src_path, $dst_path );
		} else {
			rename( $src_path, $dst_path );
		}
	}
}

/**
 * Recursively remove a directory.
 */
function klytos_rmdir_recursive( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( $items === false ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( $item === '.' || $item === '..' ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			klytos_rmdir_recursive( $path );
		} else {
			@unlink( $path );
		}
	}
	@rmdir( $dir );
}

// ─── HTML UI ────────────────────────────────────────────────────────────────
$errors = [];
if ( version_compare( PHP_VERSION, KLYTOS_MIN_PHP, '<' ) ) {
	$errors[] = sprintf( $t['error_php'], KLYTOS_MIN_PHP, PHP_VERSION );
}
if ( ! class_exists( 'ZipArchive' ) ) {
	$errors[] = $t['error_zip'];
}
if ( ! is_writable( __DIR__ ) ) {
	$errors[] = sprintf( $t['error_write'], __DIR__ );
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $t['title']; ?></title>
<style>
	*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
	body{
		font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
		background:#0f172a;
		color:#e2e8f0;
		min-height:100vh;
		display:flex;
		align-items:center;
		justify-content:center;
		padding:2rem;
	}
	.card{
		background:#1e293b;
		border-radius:1rem;
		box-shadow:0 25px 60px rgba(0,0,0,.4);
		max-width:500px;
		width:100%;
		padding:2.5rem;
		text-align:center;
		border:1px solid #334155;
	}
	.logo{
		width:80px;
		height:80px;
		margin:0 auto 1.5rem;
		background:linear-gradient(135deg,#6366f1,#8b5cf6);
		border-radius:1.25rem;
		display:flex;
		align-items:center;
		justify-content:center;
		font-size:2rem;
		font-weight:700;
		color:#fff;
		letter-spacing:-.02em;
	}
	h1{
		font-size:1.5rem;
		font-weight:700;
		margin-bottom:.5rem;
		color:#f8fafc;
	}
	p.desc{
		font-size:.925rem;
		color:#94a3b8;
		line-height:1.6;
		margin-bottom:1.75rem;
	}
	.lang-selector{
		display:flex;
		gap:.5rem;
		justify-content:center;
		margin-bottom:1.75rem;
	}
	.lang-btn{
		padding:.5rem 1.25rem;
		border-radius:.5rem;
		border:2px solid #334155;
		background:transparent;
		color:#94a3b8;
		font-size:.875rem;
		font-weight:600;
		cursor:pointer;
		transition:all .2s;
	}
	.lang-btn:hover{background:#334155;color:#e2e8f0}
	.lang-btn.active{
		border-color:#6366f1;
		background:rgba(99,102,241,.15);
		color:#a5b4fc;
	}
	.btn-install{
		display:inline-flex;
		align-items:center;
		gap:.5rem;
		padding:.875rem 2rem;
		border:none;
		border-radius:.625rem;
		background:linear-gradient(135deg,#6366f1,#8b5cf6);
		color:#fff;
		font-size:1rem;
		font-weight:600;
		cursor:pointer;
		transition:all .25s;
		width:100%;
		justify-content:center;
	}
	.btn-install:hover{
		transform:translateY(-1px);
		box-shadow:0 8px 24px rgba(99,102,241,.4);
	}
	.btn-install:disabled{
		opacity:.6;
		cursor:not-allowed;
		transform:none;
		box-shadow:none;
	}
	.progress{
		margin-top:1.5rem;
		display:none;
	}
	.progress.visible{display:block}
	.step{
		display:flex;
		align-items:center;
		gap:.625rem;
		padding:.5rem 0;
		font-size:.875rem;
		color:#64748b;
		transition:color .3s;
	}
	.step.active{color:#a5b4fc}
	.step.done{color:#34d399}
	.step .icon{width:1.25rem;text-align:center;flex-shrink:0}
	.spinner{
		display:inline-block;
		width:1rem;height:1rem;
		border:2px solid #6366f1;
		border-top-color:transparent;
		border-radius:50%;
		animation:spin .6s linear infinite;
	}
	@keyframes spin{to{transform:rotate(360deg)}}
	.error-box{
		background:rgba(239,68,68,.12);
		border:1px solid rgba(239,68,68,.3);
		border-radius:.5rem;
		padding:1rem;
		margin-bottom:1.25rem;
		color:#fca5a5;
		font-size:.875rem;
		text-align:left;
	}
	.error-box code{
		background:rgba(239,68,68,.15);
		padding:.125rem .375rem;
		border-radius:.25rem;
		font-size:.8125rem;
	}
</style>
</head>
<body>

<div class="card">
	<div class="logo">K</div>
	<h1><?php echo $t['subtitle']; ?></h1>
	<p class="desc"><?php echo $t['description']; ?></p>

	<!-- Language selector -->
	<div class="lang-selector">
		<button class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>"
			onclick="switchLang('en')">English</button>
		<button class="lang-btn <?php echo $lang === 'es' ? 'active' : ''; ?>"
			onclick="switchLang('es')">Español</button>
	</div>

	<?php if ( $errors ) : ?>
		<div class="error-box">
			<?php foreach ( $errors as $err ) : ?>
				<p><?php echo $err; ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<button class="btn-install" id="btnInstall"
		<?php echo $errors ? 'disabled' : ''; ?>
		onclick="startInstall()">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
			stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
			<polyline points="7 10 12 15 17 10"/>
			<line x1="12" y1="15" x2="12" y2="3"/>
		</svg>
		<?php echo $t['btn_install']; ?>
	</button>

	<div class="progress" id="progress">
		<div class="step" id="stepDownload">
			<span class="icon"></span>
			<?php echo $t['step_download']; ?>
		</div>
		<div class="step" id="stepExtract">
			<span class="icon"></span>
			<?php echo $t['step_extract']; ?>
		</div>
		<div class="step" id="stepCleanup">
			<span class="icon"></span>
			<?php echo $t['step_cleanup']; ?>
		</div>
		<div class="step" id="stepRedirect">
			<span class="icon"></span>
			<?php echo $t['step_redirect']; ?>
		</div>
	</div>
</div>

<script>
function switchLang(code) {
	window.location.href = 'installer.php?lang=' + code;
}

function setStep(id, state) {
	var el = document.getElementById(id);
	el.className = 'step ' + state;
	var icon = el.querySelector('.icon');
	if (state === 'active') {
		icon.innerHTML = '<span class="spinner"></span>';
	} else if (state === 'done') {
		icon.innerHTML = '&#10003;';
	}
}

function startInstall() {
	var btn = document.getElementById('btnInstall');
	btn.disabled = true;
	btn.textContent = '<?php echo addslashes( $t['checking'] ); ?>';

	var progress = document.getElementById('progress');
	progress.classList.add('visible');

	setStep('stepDownload', 'active');

	var form = new FormData();
	form.append('action', 'install');
	form.append('lang', '<?php echo $lang; ?>');

	fetch('installer.php', { method: 'POST', body: form })
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!data.ok) {
				setStep('stepDownload', '');
				btn.disabled = false;
				btn.textContent = '<?php echo addslashes( $t['btn_install'] ); ?>';
				progress.classList.remove('visible');
				alert(data.error);
				return;
			}
			setStep('stepDownload', 'done');
			setStep('stepExtract', 'done');
			setStep('stepCleanup', 'done');
			setStep('stepRedirect', 'active');
			setTimeout(function() {
				setStep('stepRedirect', 'done');
				window.location.href = data.redirect;
			}, 800);
		})
		.catch(function(err) {
			btn.disabled = false;
			btn.textContent = '<?php echo addslashes( $t['btn_install'] ); ?>';
			progress.classList.remove('visible');
			alert('Error: ' + err.message);
		});
}
</script>

</body>
</html>
