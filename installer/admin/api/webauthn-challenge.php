<?php
/**
 * Klytos Admin API — WebAuthn Challenge Endpoint
 * Handles passkey registration and authentication challenges.
 *
 * @license    Elastic License 2.0 (ELv2)
 * @copyright  Copyright (c) 2025 Jose Conti
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

$auth = $app->getAuth();

// Must be authenticated or have a pending 2FA challenge.
if (!$auth->isAuthenticated() && !$auth->is2faPending()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// CSRF validation.
$csrf = $input['csrf'] ?? '';
if (!$auth->validateCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action    = $input['action'] ?? '';
$twoFactor = $app->getTwoFactor();

// Resolve user ID.
$userId = null;
if ($auth->isAuthenticated()) {
    $username = $auth->getUsername();
    $users = $app->getStorage()->list('users');
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username) {
            $userId = $u['id'];
            break;
        }
    }
} elseif ($auth->is2faPending()) {
    $userId = $auth->get2faPendingUserId();
}

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$rpId = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Remove port from rpId.
$rpId = explode(':', $rpId)[0];

$siteConfig = $app->getSiteConfig()->get();

if ($action === 'register_challenge') {
    $user = $app->getStorage()->read('users', $userId);
    $options = $twoFactor->createPasskeyRegistrationChallenge(
        $userId,
        $user['username'] ?? '',
        $user['display_name'] ?? $user['username'] ?? '',
        $rpId,
        $siteConfig['site_name'] ?? 'Klytos'
    );
    echo json_encode($options);

} elseif ($action === 'register_complete') {
    $attestation = $input['attestation'] ?? [];
    $label       = trim($input['label'] ?? '');

    try {
        $result = $twoFactor->completePasskeyRegistration($userId, $attestation, $rpId, $label);
        echo json_encode(['success' => true, 'passkey' => $result]);
    } catch (\RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($action === 'auth_challenge') {
    $options = $twoFactor->createPasskeyAuthChallenge($userId, $rpId);
    echo json_encode($options);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}
