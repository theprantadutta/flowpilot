<?php

use App\Enums\Permission;
use App\Enums\Role;

test('the owner holds every permission', function () {
    expect(Role::Owner->permissions())->toBe(Permission::cases());
});

test('only the owner manages billing', function (Role $role) {
    expect($role->allows(Permission::BillingManage))->toBeFalse();
})->with(array_filter(Role::cases(), fn (Role $role) => $role !== Role::Owner));

test('the auditor can read and audit but change nothing', function () {
    $changing = array_filter(
        Role::Auditor->permissions(),
        fn (Permission $permission) => ! str_ends_with($permission->value, '.view')
            && ! in_array($permission, [Permission::ReportsExport], true),
    );

    expect(Role::Auditor->allows(Permission::AuditView))->toBeTrue()
        ->and($changing)->toBe([]);
});

test('only finance, procurement and management roles can decide approvals', function (Role $role, bool $canDecide) {
    expect($role->allows(Permission::ApprovalsApprove))->toBe($canDecide)
        ->and($role->allows(Permission::ApprovalsReject))->toBe($canDecide);
})->with([
    'owner' => [Role::Owner, true],
    'admin' => [Role::Admin, true],
    'manager' => [Role::Manager, true],
    'finance' => [Role::Finance, true],
    'procurement' => [Role::Procurement, true],
    'operations' => [Role::Operations, false],
    'employee' => [Role::Employee, false],
    'auditor' => [Role::Auditor, false],
]);

test('nobody can hand out ownership', function (Role $actor) {
    expect($actor->canAssign(Role::Owner))->toBeFalse();
})->with(Role::cases());

test('a role can only assign roles below itself, except the owner', function () {
    expect(Role::Owner->canAssign(Role::Admin))->toBeTrue()
        ->and(Role::Admin->canAssign(Role::Admin))->toBeFalse()
        ->and(Role::Admin->canAssign(Role::Manager))->toBeTrue()
        ->and(Role::Manager->canAssign(Role::Finance))->toBeTrue()
        ->and(Role::Finance->canAssign(Role::Procurement))->toBeFalse();
});
