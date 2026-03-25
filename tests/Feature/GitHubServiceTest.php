<?php

use App\Services\GitHub;
use Illuminate\Support\Facades\Http;

it('returns the latest version tag from a GitHub release', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/aureuserp/releases/latest' => Http::response([
            'tag_name' => 'v1.2.3',
            'name'     => 'Version 1.2.3',
        ], 200),
    ]);

    $github = new GitHub;

    expect($github->latestVersion('aureuserp', 'aureuserp'))->toBe('v1.2.3');
});

it('returns null when the releases endpoint returns 404', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/nonexistent/releases/latest' => Http::response([], 404),
    ]);

    $github = new GitHub;

    expect($github->latestVersion('aureuserp', 'nonexistent'))->toBeNull();
});

it('returns null when a connection exception occurs', function () {
    Http::fake([
        'https://api.github.com/*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        },
    ]);

    $github = new GitHub;

    expect($github->latestVersion('aureuserp', 'aureuserp'))->toBeNull();
});

it('returns the latest release data', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/aureuserp/releases/latest' => Http::response([
            'tag_name'    => 'v2.0.0',
            'name'        => 'Version 2.0.0',
            'published_at' => '2025-01-01T00:00:00Z',
        ], 200),
    ]);

    $github = new GitHub;
    $release = $github->latestRelease('aureuserp', 'aureuserp');

    expect($release)
        ->toBeArray()
        ->and($release['tag_name'])->toBe('v2.0.0')
        ->and($release['name'])->toBe('Version 2.0.0');
});

it('returns a list of releases', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/aureuserp/releases*' => Http::response([
            ['tag_name' => 'v1.0.0', 'name' => 'Version 1.0.0'],
            ['tag_name' => 'v0.9.0', 'name' => 'Version 0.9.0'],
        ], 200),
    ]);

    $github = new GitHub;
    $releases = $github->releases('aureuserp', 'aureuserp');

    expect($releases)->toHaveCount(2)
        ->and($releases[0]['tag_name'])->toBe('v1.0.0');
});

it('returns an empty array when releases endpoint fails', function () {
    Http::fake([
        'https://api.github.com/repos/*/releases*' => Http::response([], 500),
    ]);

    $github = new GitHub;

    expect($github->releases('aureuserp', 'aureuserp'))->toBeEmpty();
});

it('returns repository metadata', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/aureuserp' => Http::response([
            'full_name'        => 'aureuserp/aureuserp',
            'description'      => 'Open-Source ERP',
            'stargazers_count' => 100,
        ], 200),
    ]);

    $github = new GitHub;
    $repo = $github->repository('aureuserp', 'aureuserp');

    expect($repo)
        ->toBeArray()
        ->and($repo['full_name'])->toBe('aureuserp/aureuserp');
});

it('sends the GitHub API version header in every request', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/aureuserp/releases/latest' => Http::response([
            'tag_name' => 'v1.0.0',
        ], 200),
    ]);

    $github = new GitHub;
    $github->latestRelease('aureuserp', 'aureuserp');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-GitHub-Api-Version', '2022-11-28')
            && $request->hasHeader('Accept', 'application/vnd.github+json');
    });
});

it('sends the Authorization header when a token is provided', function () {
    Http::fake([
        'https://api.github.com/repos/aureuserp/aureuserp/releases/latest' => Http::response([
            'tag_name' => 'v1.0.0',
        ], 200),
    ]);

    $github = new GitHub(token: 'ghp_test_token');
    $github->latestRelease('aureuserp', 'aureuserp');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ghp_test_token'));
});
