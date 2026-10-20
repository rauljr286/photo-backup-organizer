<?php

/**
 * Single source of truth for application navigation.
 *
 * Every navigation surface — the desktop pill nav, the mobile bottom bar and
 * the "More" / account popovers — is rendered from this config so labels,
 * routes, icons and active-state matching never drift apart.
 *
 * Primary item fields:
 *   - label  : visible text
 *   - route  : named route resolved via route()
 *   - active : route name patterns passed to request()->routeIs()
 *   - icon    : inline SVG markup drawn inside a 24x24 viewBox
 *   - mobile  : 'tab' (bottom bar) or 'more' (inside the More popover)
 *   - desktop : set false to omit from the desktop pill nav (default true)
 *   - badge   : optional 'archived' to show the archived-photo count
 */

return [
    'primary' => [
        'photos' => [
            'label' => 'Photos',
            'route' => 'dashboard',
            'active' => ['dashboard', 'photos.*'],
            'mobile' => 'tab',
            'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path>',
        ],
        'albums' => [
            'label' => 'Albums',
            'route' => 'albums.index',
            'active' => ['albums.*'],
            'mobile' => 'tab',
            'icon' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
        ],
        'archive' => [
            'label' => 'Archive',
            'route' => 'archive',
            'active' => ['archive'],
            'mobile' => 'tab',
            'badge' => 'archived',
            'icon' => '<rect x="2" y="3" width="20" height="5" rx="1"></rect><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"></path><path d="M10 12h4"></path>',
        ],
        'private' => [
            'label' => 'Private',
            'route' => 'private',
            'active' => ['private'],
            'mobile' => 'more',
            'desktop' => false,
            'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        ],
        'insights' => [
            'label' => 'Insights',
            'route' => 'insights',
            'active' => ['insights'],
            'mobile' => 'more',
            'icon' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5a6 6 0 0 0-12 0c0 1 .2 2.2 1.5 3.5.8.8 1.3 1.5 1.5 2.5"></path><path d="M9 18h6"></path><path d="M10 22h4"></path>',
        ],
        'cleanup' => [
            'label' => 'Cleanup',
            'route' => 'cleanup',
            'active' => ['cleanup'],
            'mobile' => 'more',
            'icon' => '<path d="M12 2v9"></path><path d="M8 11h8"></path><path d="M8 11 5 21h14l-3-10Z"></path><path d="M10 11l-1 10"></path><path d="M14 11l1 10"></path>',
        ],
    ],

    'account' => [
        'profile' => [
            'label' => 'Profile & settings',
            'short' => 'Profile',
            'route' => 'profile',
            'active' => ['profile', 'profile.*', 'settings'],
            'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        ],
        'signout' => [
            'label' => 'Sign out',
            'icon' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
        ],
    ],
];
