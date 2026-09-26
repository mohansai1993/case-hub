<?php

/*
|--------------------------------------------------------------------------
| Admin panel permission catalogue
|--------------------------------------------------------------------------
|
| Single source of truth for every permission a role can be granted.
| Keys are stable identifiers ("module.action") stored in role_permissions;
| labels are display-only, so they can be reworded without a data migration.
|
| Adding a permission = adding a line here, then ticking it on the role.
| The Super Admin role implicitly holds every permission (see Admin::can()).
|
| Roles & Permissions and Staff management are intentionally NOT listed:
| they are Super Admin only and cannot be delegated.
|
*/

return [

    'modules' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                'dashboard.view' => 'View Dashboard',
            ],
        ],
        'clients' => [
            'label' => 'Client Management',
            'permissions' => [
                'clients.view' => 'View Clients',
                'clients.create' => 'Add Client',
                'clients.update' => 'Edit Client',
                'clients.delete' => 'Delete Client',
            ],
        ],
        'lawyers' => [
            'label' => 'Lawyer Management',
            'permissions' => [
                'lawyers.view' => 'View Lawyers',
                'lawyers.create' => 'Add Lawyer',
                'lawyers.update' => 'Edit Lawyer',
                'lawyers.delete' => 'Delete Lawyer',
                'lawyers.verify' => 'Verify Lawyer',
            ],
        ],
        'subscriptions' => [
            'label' => 'Subscription Management',
            'permissions' => [
                'subscriptions.view' => 'View Subscriptions',
                'subscriptions.manage' => 'Manage Subscriptions',
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'permissions' => [
                'notifications.view' => 'View Notifications',
                'notifications.create' => 'Create Notifications',
            ],
        ],
        'settings' => [
            'label' => 'Settings',
            'permissions' => [
                'settings.view' => 'View Settings',
                'settings.manage' => 'Manage Settings',
            ],
        ],
    ],

    /*
    | Where an admin lands after login: the first entry whose permission they
    | hold. Order matters. Super Admin always gets the first entry.
    */
    'landing' => [
        'dashboard.view' => 'admin.dashboard',
        'clients.view' => 'admin.clients',
        'lawyers.view' => 'admin.lawyers',
        'subscriptions.view' => 'admin.subscriptions',
        'notifications.view' => 'admin.notifications',
        'settings.view' => 'admin.settings',
    ],

];
