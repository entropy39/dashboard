<?php
declare(strict_types=1);

function dashboard_mode(): string {
    $mode = getenv('DASHBOARD_MODE');

    return is_string($mode) && strtolower(trim($mode)) === 'demo' ? 'demo' : 'live';
}

function dashboard_is_demo(): bool {
    return dashboard_mode() === 'demo';
}

function dashboard_reject_demo_api(): void {
    if (!dashboard_is_demo()) {
        return;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => false,
        'data' => null,
        'error' => 'This endpoint is unavailable in demo mode.',
    ]);
    exit;
}

function dashboard_version(): ?string {
    $repository = dirname(__DIR__);
    if (!is_dir($repository . '/.git') || !is_executable('/usr/bin/git') || !function_exists('proc_open')) {
        return null;
    }

    $process = @proc_open(
        ['/usr/bin/git', '-C', $repository, 'tag', '--points-at', 'HEAD', '--sort=-version:refname'],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        return null;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        return null;
    }

    foreach (preg_split('/\R/', trim($stdout)) ?: [] as $tag) {
        if (preg_match('/^v[0-9]+(?:\.[0-9]+)+(?:[-+][0-9A-Za-z.-]+)?$/', $tag) === 1) {
            return $tag;
        }
    }

    return null;
}
