# CU Boulder User Invite Module

This Drupal module enables users with permissions to invite CU Boulder users to log in to their website.

Invite form path: `/admin/people/invite` (requires module permission `invite users`)

Administation form path: `/admin/config/people/invite` (requires module permission `administer user invite`, this is a dangerous permission meant for adminstrators of user accounts as it enables granting users any role)

Some quirks:
- **IMPORTANT**: No special validation of the user IdentiKey(s) entered into the invite form takes place. This should be resolved.
- **IMPORTANT**: The module must be configured after installation before it can be used. To resolve this, set the `roles` and `default_role` configuration as defined in the `config/install/ucb_user_invite.configuration.yml` project file.
- The `Reply-To` of the invitation email is set to that of the user account sending the invite.
- A confirmation email will also be delivered to the sender. The `Reply-To` of the confirmation email is set to the email address from basic site settings.
- Selecting `Authenticated user` as the role will enable invitation of a user with no special permissions. This isn't likely to be very useful but is an option.
- This module isn't meant to be used to create local accounts. If the SAML module is disabled for local testing then the accounts created by this module can be accessed with the password of `password`.