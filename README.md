# MageAustralia_B2bRegistration

Trade-account registration for Maho 26.5+. Turns a
[CustomForms](https://github.com/mageaustralia/maho-module-custom-forms) form
into a trade application flow: customers apply, admins approve or decline,
and approved applicants land in your wholesale customer group automatically.

## Features

- **Application pipeline** - each submission of a designated form becomes a
  `pending` application; approving assigns the customer to the configured
  B2B group, declining records the decision. New accounts can be created as
  part of the application (tracked via `is_new_account`)
- **Admin approval UI** - grid of applications with one-click approve /
  decline
- **Email notifications** - admin alert on new applications; applicant
  notified on approval/decline (registered HTML templates, responsive)
- **Field mapping** - map form-field keys to customer attributes via JSON
  config, so application data flows onto the customer record
- **Declarative schema** - `sql/schema.php`, idempotent with the legacy
  setup scripts

## Requirements

- Maho 26.5+ (tested on 26.7)
- PHP 8.3+
- [maho-module-custom-forms](https://github.com/mageaustralia/maho-module-custom-forms)

## Installation

Installed from GitHub (not on Packagist):

```bash
composer config repositories.maho-module-b2b-registration vcs https://github.com/mageaustralia/maho-module-b2b-registration
composer require mageaustralia/maho-module-b2b-registration
composer dump-autoload && ./maho migrate && ./maho cache:flush
```

## Configuration

System > Configuration > B2B Registration:

| Setting | What it does |
|---|---|
| Enabled | Master switch |
| Form codes | Which CustomForms form(s) count as trade applications |
| Email field | Which form field carries the applicant's email |
| B2B group | Customer group assigned on approval |
| Field mapping | JSON map of form-field key to customer attribute code |
| Admin notify emails | Recipients for new-application alerts |

## Part of the MageAustralia B2B suite

Pairs with [maho-module-b2b-access](https://github.com/mageaustralia/maho-module-b2b-access)
(require login / hide prices until approved) and
[maho-module-b2b-bulk-order](https://github.com/mageaustralia/maho-module-b2b-bulk-order)
(quick ordering for the customers you approve). The commercial **B2B Pro**
tier adds quoting, company accounts, order approvals, and net-terms
payment - contact us for access.

## License

OSL-3.0
