// One entry per screen. A signed-in user sees an entry only if they hold its permission.
// An entry without a "permission" is shown to everyone who is signed in.
// The menu is only a convenience: the server still checks the permission on every route (NFR-SEC-03).

export const navigationGroups = [
    {
        title: 'Overview',
        items: [
            { label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
        ],
    },
    {
        title: 'Commercial & Sales',
        items: [
            { label: 'Sales', href: '/sales', permission: 'sales.view', icon: 'sales' },
            { label: 'Payments', href: '/payments', permission: 'payments.view' },
            { label: 'New sale', href: '/sales/create', permission: 'sales.create', icon: 'new-sale', badge: 'POS' },
            { label: 'Customers', href: '/customers', permission: 'customers.manage', icon: 'customers' },
        ],
    },
    {
        title: 'Catalogue & Stock',
        items: [
            { label: 'Stock overview', href: '/stock', permission: 'inventory.view', icon: 'stock' },
            { label: 'Low stock', href: '/stock/low', permission: 'inventory.view', icon: 'low-stock' },
            { label: 'Adjust stock', href: '/stock/adjust', permission: 'inventory.adjust', icon: 'stock-adjust' },
            { label: 'Products', href: '/products', permission: 'products.view', icon: 'products' },
            { label: 'Categories', href: '/categories', permission: 'products.view', icon: 'categories' },
            { label: 'Units', href: '/units', permission: 'products.view', icon: 'units' },
            { label: 'Warehouses', href: '/warehouses', permission: 'inventory.view', icon: 'warehouses' },
        ],
    },
    {
        title: 'Procurement',
        items: [
            { label: 'Purchase orders', href: '/purchase-orders', permission: 'purchase_orders.view', icon: 'purchase-orders' },
            { label: 'Goods receipts', href: '/goods-receipts', permission: 'goods_receipts.manage', icon: 'goods-receipts' },
            { label: 'Suppliers', href: '/suppliers', permission: 'suppliers.manage', icon: 'suppliers' },
        ],
    },
    {
        title: 'Workforce & Attendance',
        items: [
            { label: 'Attendance board', href: '/attendance/board', permission: 'attendance.view_all', icon: 'attendance-board' },
            { label: 'Attendance summary', href: '/attendance/summary', permission: 'attendance.view_all', icon: 'attendance-summary' },
            { label: 'Scanner', href: '/scanner', permission: 'attendance.scan', icon: 'scanner', badge: 'Kiosk' },
            { label: 'My QR', href: '/my-qr', permission: 'qr.own', icon: 'my-qr' },
            { label: 'My attendance', href: '/my-attendance', permission: 'attendance.view_own', icon: 'my-attendance' },
            { label: 'Employees', href: '/employees', permission: ['employees.view', 'employees.manage'], icon: 'employees' },
            { label: 'Organisation', href: '/organisation', permission: 'employees.view', icon: 'organisation' },
            { label: 'Departments', href: '/departments', permission: 'departments.manage', icon: 'departments' },
            { label: 'Positions', href: '/positions', permission: 'positions.manage', icon: 'positions' },
        ],
    },
    {
        title: 'System & Security',
        items: [
            { label: 'Users', href: '/users', permission: ['users.view', 'users.manage'], icon: 'users' },
            { label: 'Roles', href: '/roles', permission: 'roles.manage', icon: 'roles' },
            { label: 'Audit log', href: '/audit-logs', permission: 'audit.view', icon: 'audit-logs' },
            { label: 'Networks', href: '/networks', permission: 'networks.manage', icon: 'networks' },
        ],
    },
];

// Helper to determine if the user has any required permission for a menu item
export function canAccess(item, userPermissions = []) {
    if (!item.permission) {
        return true;
    }

    const permissions = Array.isArray(userPermissions) ? userPermissions : [];
    const required = Array.isArray(item.permission) ? item.permission : [item.permission];

    return required.some((permission) => permissions.includes(permission));
}

// Flat menu exported for backward compatibility
export const menu = navigationGroups.flatMap((group) => group.items);
