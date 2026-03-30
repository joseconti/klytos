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
		border-radius:1.25rem;
		display:flex;
		align-items:center;
		justify-content:center;
	}
	.logo img{width:80px;height:80px;border-radius:1.25rem;}
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
	<div class="logo"><img src="data:image/png;base64,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" alt="Klytos"></div>
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
