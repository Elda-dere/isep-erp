// One entry per screen. A signed-in user sees an entry only if they hold its permission.
// An entry without a "permission" is shown to everyone who is signed in.
// The menu is only a convenience: the server still checks the permission on every route.
// Each module adds its own entries here when its pages are merged. Example:
//   { label: 'Employees', href: '/employees', permission: 'employees.view' },
export const menu = [
    { label: 'Dashboard', href: '/dashboard' },
 feature/departments-positions
    { label: 'Positions', href: '/positions', permission: 'positions.manage' },
    { label: 'Departments', href: '/departments', permission: 'departments.manage' },

 feature/role-admin
 main
    { label: 'Roles', href: '/roles', permission: 'roles.manage' },

    { label: 'Users', href: '/users', permission: 'users.view' },
 main
    { label: 'Audit log', href: '/audit-logs', permission: 'audit.view' },
    // Catalogue
    { label: 'Products', href: '/products', permission: 'products.view' },
    // Inventory: warehouses and stock use the same "view" permission
    { label: 'Warehouses', href: '/warehouses', permission: 'inventory.view' },
    { label: 'Stock', href: '/stock', permission: 'inventory.view' },
];

