// One entry per screen. A signed-in user sees an entry only if they hold its permission.
// An entry without a "permission" is shown to everyone who is signed in.
// Each module adds its own entries here when its pages are merged. Example:
//   { label: 'Employees', href: '/employees', permission: 'employees.view' },
export const menu = [
    { label: 'Dashboard', href: '/dashboard' },
];