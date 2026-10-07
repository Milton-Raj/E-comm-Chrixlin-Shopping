<?php

use App\Domain\Identity\PermissionCatalog;

it('only grants permissions that exist in the catalog', function () {
    $all = array_keys(PermissionCatalog::permissions());

    foreach (array_keys(PermissionCatalog::roles()) as $role) {
        expect(array_diff(PermissionCatalog::permissionsForRole($role), $all))->toBeEmpty();
    }
});

it('expands wildcards', function () {
    expect(PermissionCatalog::permissionsForRole('customer-support'))
        ->toContain('support.view', 'support.reply', 'support.assign');
});

it('keeps roles.manage away from administrators', function () {
    expect(PermissionCatalog::permissionsForRole('administrator'))->not->toContain('roles.manage');
});

it('gives customers no permissions', function () {
    expect(PermissionCatalog::permissionsForRole(PermissionCatalog::CUSTOMER))->toBeEmpty();
});
