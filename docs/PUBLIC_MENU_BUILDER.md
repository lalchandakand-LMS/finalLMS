# Public menu builder

Administrators can manage additional public navigation links from Admin > Main menu builder. The builder supports site-relative paths (for example /about) and HTTPS destinations. JavaScript, data, protocol-relative and credential-bearing destinations are rejected.

Menu items can be enabled/hidden, opened in a new tab, assigned to one top-level parent, and reordered by dragging top-level rows. Submenus are intentionally limited to one level for predictable mobile navigation. Deleting a parent promotes its child links to top-level so no link is silently lost.

The shared public navigation renders the same configured items for the responsive website drawer. Fixed Home/Courses and LMS account links remain system-owned and cannot be accidentally deleted by menu editing.

Only administrators can manage the menu. Hiding a menu item is presentation only; it does not grant or revoke access to the linked route. Protect destination routes independently.
