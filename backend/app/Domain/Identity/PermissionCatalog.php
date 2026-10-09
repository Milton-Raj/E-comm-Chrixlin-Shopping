<?php

namespace App\Domain\Identity;

/**
 * Single source of truth for permissions and default roles (SECURITY.md §4).
 * Seeded by PermissionSeeder in every environment.
 */
final class PermissionCatalog
{
    public const SUPER_ADMIN = 'super-admin';

    public const CUSTOMER = 'customer';

    /**
     * @return array<string, string> permission => description
     */
    public static function permissions(): array
    {
        return [
            'dashboard.view' => 'View admin dashboard',
            'products.view' => 'View products',
            'products.create' => 'Create products',
            'products.edit' => 'Edit products and variants',
            'products.delete' => 'Archive products',
            'categories.manage' => 'Manage categories',
            'brands.manage' => 'Manage brands',
            'attributes.manage' => 'Manage attributes and values',
            'reviews.moderate' => 'Approve or reject reviews',
            'inventory.view' => 'View stock and ledger',
            'inventory.edit' => 'Adjust stock',
            'orders.view' => 'View orders',
            'orders.edit' => 'Edit orders and change status',
            'orders.cancel' => 'Cancel orders',
            'orders.refund' => 'Refund orders',
            'shipments.manage' => 'Create shipments and tracking',
            'customers.view' => 'View customers',
            'customers.edit' => 'Edit or block customers',
            'digital.manage' => 'Manage digital files and settings',
            'downloads.view' => 'View download logs',
            'entitlements.revoke' => 'Revoke, restore or reset download entitlements',
            'coupons.manage' => 'Manage coupons',
            'promotions.manage' => 'Manage promotions',
            'subscribers.manage' => 'Manage newsletter subscribers',
            'content.manage' => 'Manage pages, blog, banners, menus and FAQs',
            'media.manage' => 'Manage the media library',
            'support.view' => 'View support tickets',
            'support.reply' => 'Reply to support tickets',
            'support.assign' => 'Assign support tickets',
            'reports.view' => 'View reports',
            'reports.finance' => 'View financial reports',
            'imports.run' => 'Run bulk imports',
            'exports.run' => 'Run bulk exports',
            'users.manage' => 'Manage staff accounts',
            'roles.manage' => 'Manage roles and permissions',
            'settings.manage' => 'Manage store settings',
            'audit.view' => 'View the audit log',
        ];
    }

    /**
     * Permissions grouped by the admin page they unlock, for the role editor's checkboxes.
     * Every catalog permission appears in exactly one group (tested).
     *
     * @return list<array{label: string, permissions: list<string>}>
     */
    public static function groups(): array
    {
        return [
            ['label' => 'Dashboard', 'permissions' => ['dashboard.view']],
            ['label' => 'Products', 'permissions' => ['products.view', 'products.create', 'products.edit', 'products.delete', 'attributes.manage', 'reviews.moderate']],
            ['label' => 'Categories & brands', 'permissions' => ['categories.manage', 'brands.manage']],
            ['label' => 'Inventory', 'permissions' => ['inventory.view', 'inventory.edit']],
            ['label' => 'Orders & shipping', 'permissions' => ['orders.view', 'orders.edit', 'orders.cancel', 'orders.refund', 'shipments.manage']],
            ['label' => 'Customers', 'permissions' => ['customers.view', 'customers.edit']],
            ['label' => 'Digital products', 'permissions' => ['digital.manage', 'downloads.view', 'entitlements.revoke']],
            ['label' => 'Coupons & marketing', 'permissions' => ['coupons.manage', 'promotions.manage', 'subscribers.manage']],
            ['label' => 'Reports & exports', 'permissions' => ['reports.view', 'reports.finance', 'exports.run', 'imports.run']],
            ['label' => 'Content', 'permissions' => ['content.manage', 'media.manage']],
            ['label' => 'Support', 'permissions' => ['support.view', 'support.reply', 'support.assign']],
            ['label' => 'Staff & roles', 'permissions' => ['users.manage', 'roles.manage']],
            ['label' => 'Settings', 'permissions' => ['settings.manage']],
            ['label' => 'Audit log', 'permissions' => ['audit.view']],
        ];
    }

    /**
     * Default role => permissions. `super-admin` gets everything via Gate::before.
     * Wildcards like `products.*` expand against the catalog.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            self::SUPER_ADMIN => [],
            'administrator' => array_values(array_diff(array_keys(self::permissions()), ['roles.manage'])),
            'product-manager' => ['dashboard.view', 'products.*', 'categories.manage', 'brands.manage', 'attributes.manage', 'media.manage', 'inventory.view', 'digital.manage', 'reviews.moderate', 'imports.run', 'exports.run'],
            'order-manager' => ['dashboard.view', 'orders.view', 'orders.edit', 'orders.cancel', 'shipments.manage', 'customers.view', 'inventory.view', 'downloads.view'],
            'customer-support' => ['orders.view', 'customers.view', 'support.*', 'downloads.view', 'entitlements.revoke'],
            'inventory-manager' => ['dashboard.view', 'inventory.view', 'inventory.edit', 'products.view', 'imports.run', 'exports.run'],
            'marketing-manager' => ['dashboard.view', 'coupons.manage', 'promotions.manage', 'subscribers.manage', 'content.manage', 'media.manage', 'reports.view'],
            'content-manager' => ['content.manage', 'media.manage'],
            'finance-manager' => ['dashboard.view', 'orders.view', 'orders.refund', 'reports.view', 'reports.finance', 'exports.run'],
            self::CUSTOMER => [],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsForRole(string $role): array
    {
        $all = array_keys(self::permissions());
        $expanded = [];

        foreach (self::roles()[$role] ?? [] as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                array_push($expanded, ...array_filter($all, fn (string $p) => str_starts_with($p, $prefix)));
            } else {
                $expanded[] = $pattern;
            }
        }

        return array_values(array_unique($expanded));
    }
}
